<?php

declare(strict_types=1);

namespace Tests\Support;

/** Extract error metadata, never raw log messages, queries, values, paths or connection settings. */
final class SeasonScoringDiagnostics
{
    public const MAX_LOG_BYTES = 262144;

    public const MAX_LINE_BYTES = 8192;

    public const MAX_DETAILS = 20;

    public static function fromFile(string $path, string $operation = '', string $fault = '', array $secrets = []): array
    {
        $summary = [
            'diagnostic_sqlstates' => [],
            'unexpected_diagnostic_count' => 0,
            'diagnostic_details' => [],
            'diagnostic_details_truncated' => false,
            'diagnostic_collection_errors' => [],
        ];
        if (! file_exists($path)) {
            return $summary; // PHP creates no error log for a quiet successful operation.
        }
        if (! is_file($path)) {
            $summary['diagnostic_collection_errors'][] = 'log_not_regular';

            return $summary;
        }
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            $summary['diagnostic_collection_errors'][] = 'log_read_failed';

            return $summary;
        }
        $bytes = 0;
        $seen = [];
        try {
            while (($line = @fgets($stream, self::MAX_LINE_BYTES + 2)) !== false) {
                $bytes += strlen($line);
                if ($bytes > self::MAX_LOG_BYTES || strlen($line) > self::MAX_LINE_BYTES) {
                    $summary['diagnostic_collection_errors'][] = $bytes > self::MAX_LOG_BYTES ? 'log_size_limit' : 'line_size_limit';
                    break; // Collection errors are assertion failures, never a clean result.
                }
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    continue;
                }
                if (! self::expected($line, $operation, $fault)) {
                    $summary['unexpected_diagnostic_count']++;
                }
                preg_match_all('/SQLSTATE\[([A-Z0-9]{5})\]/', $line, $matches, PREG_OFFSET_CAPTURE);
                foreach ($matches[1] as $index => [$state]) {
                    if (! in_array($state, $summary['diagnostic_sqlstates'], true)) {
                        if (count($summary['diagnostic_sqlstates']) >= self::MAX_DETAILS) {
                            $summary['diagnostic_collection_errors'][] = 'sqlstate_limit';
                            break 2;
                        }
                        $summary['diagnostic_sqlstates'][] = $state;
                    }
                    $message = substr($line, $matches[0][$index][1]);
                    $detail = self::detail($state, $message, $secrets);
                    $key = json_encode($detail, JSON_THROW_ON_ERROR);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    if (count($summary['diagnostic_details']) >= self::MAX_DETAILS) {
                        $summary['diagnostic_details_truncated'] = true;
                        continue; // Continue checking the rest of the log for errors.
                    }
                    $seen[$key] = true;
                    $summary['diagnostic_details'][] = $detail;
                }
            }
            if (! feof($stream) && $summary['diagnostic_collection_errors'] === []) {
                $summary['diagnostic_collection_errors'][] = 'log_read_failed';
            }
        } finally {
            if (! fclose($stream)) {
                $summary['diagnostic_collection_errors'][] = 'log_close_failed';
            }
        }

        return $summary;
    }

    private static function expected(string $line, string $operation, string $fault): bool
    {
        $message = preg_replace('/^\[[^\]]+\] /', '', $line);
        if ($fault !== '' && str_ends_with($message, 'Synthetic scoring fault: '.$fault)) {
            return true;
        }

        return $operation === 'challenge' && in_array($message, [
            'Battle simulation failed: There is already an active transaction',
            'Challenge acceptance failed: There is no active transaction',
        ], true);
    }

    private static function detail(string $state, string $message, array $secrets): array
    {
        $detail = ['sqlstate' => $state, 'kind' => 'sql_error'];
        if (preg_match('/^SQLSTATE\[[A-Z0-9]{5}\]: [^:\r\n]{1,96}: ([0-9]{1,5})\b/', $message, $code)) {
            $detail['driver_code'] = (int) $code[1];
        }
        $pattern = match (true) {
            $state === '42S02' && ($detail['driver_code'] ?? null) === 1146 => "/\\bTable '([^'\\r\\n]{1,256})' doesn't exist\\b/",
            $state === '42S22' && ($detail['driver_code'] ?? null) === 1054 => "/\\bUnknown column '([^'\\r\\n]{1,256})' in '/",
            default => null,
        };
        if ($pattern !== null && preg_match($pattern, $message, $identifier)) {
            $detail['kind'] = $state === '42S02' ? 'missing_table' : 'missing_column';
            $value = $identifier[1];
            // Reject SQL fragments, literals, unusual names and long tokens rather than echoing them.
            $safe = preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})?$/D', $value) === 1;
            foreach ($secrets as $secret) {
                if (is_string($secret) && $secret !== '' && str_contains($value, $secret)) {
                    $safe = false;
                }
            }
            if ($safe && $detail['kind'] === 'missing_table') {
                $parts = explode('.', $value);
                $value = end($parts); // Drop schema/database name.
            }
            $detail['identifier'] = $safe ? $value : '[redacted]';
        }

        return $detail;
    }

    public static function failureContext(array $probe): string
    {
        // Do not serialize the whole worker result: it may contain gameplay or future debug data.
        $safe = array_intersect_key($probe, array_flip([
            'diagnostic_details', 'diagnostic_details_truncated', 'diagnostic_collection_errors',
        ]));

        return ' Safe SQL diagnostics: '.json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
