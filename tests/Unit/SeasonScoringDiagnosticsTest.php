<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\SeasonScoringDiagnostics;

class SeasonScoringDiagnosticsTest extends TestCase
{
    private function collect(string $log, string $operation = '', string $fault = '', array $secrets = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ur_diag_');
        $this->assertNotFalse($path);
        try {
            $this->assertNotFalse(file_put_contents($path, $log));

            return SeasonScoringDiagnostics::fromFile($path, $operation, $fault, $secrets);
        } finally {
            $this->assertTrue(unlink($path));
        }
    }

    public function test_extracts_missing_objects_and_codes_without_returning_queries_or_database_names(): void
    {
        $table = "[02-Nov-2026 12:00:00 UTC] StatCalculator::getBaseStats SQLSTATE[42S02]: Base table or view not found: 1146 Table 'private_schema.synthetic_missing_table' doesn't exist";
        $column = "ItemService SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.synthetic_missing_column' in 'field list' (SQL: SELECT private_player_value FROM users WHERE password='fixturecredential')";
        $summary = $this->collect($table."\n".$column."\n".$table."\n");
        $this->assertSame(['42S02', '42S22'], $summary['diagnostic_sqlstates']);
        $this->assertSame(3, $summary['unexpected_diagnostic_count']);
        $this->assertSame([
            ['sqlstate' => '42S02', 'kind' => 'missing_table', 'driver_code' => 1146, 'identifier' => 'synthetic_missing_table'],
            ['sqlstate' => '42S22', 'kind' => 'missing_column', 'driver_code' => 1054, 'identifier' => 'u.synthetic_missing_column'],
        ], $summary['diagnostic_details']);
        $this->assertSame([], $summary['diagnostic_collection_errors']);
        $this->assertFalse($summary['diagnostic_details_truncated']);
        $context = SeasonScoringDiagnostics::failureContext($summary + ['raw_log' => $column, 'result' => ['secret' => 'fixturecredential']]);
        foreach (['SELECT', 'private_schema', 'private_player_value', 'fixturecredential', 'raw_log', 'secret'] as $private) {
            $this->assertStringNotContainsString($private, $context);
        }
        $this->assertStringContainsString('synthetic_missing_table', $context);
        $this->assertStringContainsString('u.synthetic_missing_column', $context);
        $this->assertStringContainsString('42S22', $context);
    }

    public function test_unrecognized_errors_expose_only_error_metadata(): void
    {
        $summary = $this->collect("PDO SQLSTATE[28000]: Invalid authorization specification: 1045 Access denied for user 'privateuser' with password 'fixturecredential' mysql://privateuser:fixturecredential@privatehost/game\n"
            ."PDO SQLSTATE[42000]: Syntax error or access violation: 1064 near SELECT private_player_value FROM users\n");
        $this->assertSame(['28000', '42000'], $summary['diagnostic_sqlstates']);
        $this->assertSame([
            ['sqlstate' => '28000', 'kind' => 'sql_error', 'driver_code' => 1045],
            ['sqlstate' => '42000', 'kind' => 'sql_error', 'driver_code' => 1064],
        ], $summary['diagnostic_details']);
        $context = SeasonScoringDiagnostics::failureContext($summary);
        foreach (['privateuser', 'fixturecredential', 'privatehost', 'private_player_value', 'SELECT', 'mysql://'] as $private) {
            $this->assertStringNotContainsString($private, $context);
        }
    }

    public static function unsafeIdentifiers(): array
    {
        return [
            'query_fragment' => ['SELECT private_player_value', []],
            'email_value' => ['privateplayer@example.invalid', []],
            'long_token' => [str_repeat('a', 130), []],
            'control_character' => ["private\tvalue", []],
            'known_credential' => ['prefix_fixturecredential_suffix', ['fixturecredential']],
        ];
    }

    #[DataProvider('unsafeIdentifiers')]
    public function test_unsafe_or_sensitive_identifiers_are_redacted(string $identifier, array $secrets): void
    {
        $summary = $this->collect("SQLSTATE[42S22]: Column not found: 1054 Unknown column '$identifier' in 'field list'", secrets: $secrets);
        $this->assertSame(['42S22'], $summary['diagnostic_sqlstates']);
        $this->assertSame('[redacted]', $summary['diagnostic_details'][0]['identifier']);
        $this->assertStringNotContainsString($identifier, json_encode($summary, JSON_THROW_ON_ERROR));
    }

    public function test_detail_cap_does_not_stop_sqlstate_or_unexpected_error_detection(): void
    {
        $lines = [];
        foreach (range(1, SeasonScoringDiagnostics::MAX_DETAILS + 2) as $index) {
            $lines[] = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'column_$index' in 'field list'";
        }
        $lines[] = "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'schema.late_table' doesn't exist";
        $summary = $this->collect(implode("\n", $lines));
        $this->assertCount(SeasonScoringDiagnostics::MAX_DETAILS, $summary['diagnostic_details']);
        $this->assertTrue($summary['diagnostic_details_truncated']);
        $this->assertSame(['42S22', '42S02'], $summary['diagnostic_sqlstates']);
        $this->assertSame(count($lines), $summary['unexpected_diagnostic_count']);
        $this->assertSame([], $summary['diagnostic_collection_errors']);
        $this->assertLessThan(4096, strlen(SeasonScoringDiagnostics::failureContext($summary)));
    }

    public function test_oversize_log_or_line_fails_collection_explicitly(): void
    {
        $line = $this->collect(str_repeat('x', SeasonScoringDiagnostics::MAX_LINE_BYTES + 1));
        $this->assertSame(['line_size_limit'], $line['diagnostic_collection_errors']);
        $log = $this->collect(str_repeat(str_repeat('x', 4095)."\n", 65));
        $this->assertSame(['log_size_limit'], $log['diagnostic_collection_errors']);
        $this->assertStringNotContainsString(str_repeat('x', 100), SeasonScoringDiagnostics::failureContext($log));
    }

    public function test_too_many_distinct_sqlstates_fail_collection_instead_of_growing_output(): void
    {
        $lines = [];
        foreach (range(1, SeasonScoringDiagnostics::MAX_DETAILS + 1) as $index) {
            $lines[] = 'SQLSTATE['.sprintf('A%04d', $index).']: Synthetic SQL error';
        }
        $summary = $this->collect(implode("\n", $lines));
        $this->assertSame(['sqlstate_limit'], $summary['diagnostic_collection_errors']);
        $this->assertCount(SeasonScoringDiagnostics::MAX_DETAILS, $summary['diagnostic_sqlstates']);
    }

    public function test_known_fault_allowances_do_not_hide_real_sql_errors(): void
    {
        $expected = "[02-Nov-2026 UTC] SeasonService::onCultivation Synthetic scoring fault: before_category\n";
        $summary = $this->collect($expected, 'cultivate', 'before_category');
        $this->assertSame(0, $summary['unexpected_diagnostic_count']);
        $this->assertSame([], $summary['diagnostic_sqlstates']);
        $sql = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'missing_column' in 'field list'\n";
        $mixed = $this->collect($expected.$sql, 'cultivate', 'before_category');
        $this->assertSame(1, $mixed['unexpected_diagnostic_count']);
        $this->assertSame(['42S22'], $mixed['diagnostic_sqlstates']);
        $challenge = $this->collect("Battle simulation failed: There is already an active transaction\nChallenge acceptance failed: There is no active transaction\n", 'challenge');
        $this->assertSame(0, $challenge['unexpected_diagnostic_count']);
        $this->assertSame([], $challenge['diagnostic_collection_errors']);
    }

    public function test_quiet_worker_and_nonregular_log_are_distinguished(): void
    {
        $quiet = SeasonScoringDiagnostics::fromFile(sys_get_temp_dir().'/missing_ur_diag_'.bin2hex(random_bytes(8)));
        $this->assertSame([], $quiet['diagnostic_sqlstates']);
        $this->assertSame([], $quiet['diagnostic_collection_errors']);
        $this->assertSame(0, $quiet['unexpected_diagnostic_count']);
        $invalid = SeasonScoringDiagnostics::fromFile(sys_get_temp_dir());
        $this->assertSame(['log_not_regular'], $invalid['diagnostic_collection_errors']);
    }

    public function test_independent_worker_transports_sanitized_details_in_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ur_diag_');
        $this->assertNotFalse($path);
        $worker = new Process([PHP_BINARY, '-r',
            'require $argv[1]; file_put_contents($argv[2], stream_get_contents(STDIN)); echo json_encode(Tests\\Support\\SeasonScoringDiagnostics::fromFile($argv[2]), JSON_THROW_ON_ERROR);',
            dirname(__DIR__).'/Support/SeasonScoringDiagnostics.php', $path,
        ], null, null, "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'private_schema.worker_table' doesn't exist (SQL: SELECT private_player_value)\n", 5);
        try {
            $this->assertSame(0, $worker->run());
            $summary = json_decode($worker->getOutput(), true, 32, JSON_THROW_ON_ERROR);
            $this->assertSame(['42S02'], $summary['diagnostic_sqlstates']);
            $this->assertSame('worker_table', $summary['diagnostic_details'][0]['identifier']);
            $this->assertSame([], $summary['diagnostic_collection_errors']);
            $this->assertStringNotContainsString('private_schema', $worker->getOutput());
            $this->assertStringNotContainsString('private_player_value', $worker->getOutput());
        } finally {
            if ($worker->isRunning()) {
                $worker->stop(0.2);
            }
            $this->assertFalse($worker->isRunning());
            $this->assertTrue(unlink($path));
        }
    }
}
