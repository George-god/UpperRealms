<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\GameplayOperationLedger;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DisposableSeasonScoringDatabase;

/** Linux only; inherited harness verifies loopback, random schema, token and disposable opt-in. */
final class CultivationOperationTest extends DisposableSeasonScoringDatabase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared(GameplayOperationLedger::ddl());
    }

    public static function paths(): array { return ['laravel' => ['laravel'], 'legacy' => ['legacy']]; }

    private function action(string $path, array $options = []): array
    {
        return $this->finishScoring($this->startScoring($path, 'cultivate', $options));
    }

    #[DataProvider('paths')]
    public function test_atomic_scoring_replay_cooldown_and_original_snapshot(string $path): void
    {
        $this->season('+2 DAY');
        DB::table('seasons')->update(['weight_cultivation' => 2.25]);
        $first = $this->action($path);
        $result = $first['result'];
        $this->assertTrue($result['success'], json_encode($first));
        $this->assertSame('committed', $result['operation_status']);
        $this->assertSame([], $first['diagnostic_sqlstates']);
        $this->assertSame(0, $first['unexpected_diagnostic_count']);
        $points = max(1, (int) floor($result['chi_gained'] / 10));
        $this->assertSame($points, (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->assertSame((int) floor($points * 2.25), (int) DB::table('season_rankings')->value('total_score'));
        $this->assertLessThan(array_search('before_commit', $first['events']), array_search('after_ledger', $first['events']));
        DB::table('users')->where('id', 1)->update(['chi' => 7]);
        $replay = $this->action($path)['result'];
        $this->assertSame('replayed', $replay['operation_status']);
        $this->assertSame($result['display'], $replay['display']);
        $this->assertSame(7, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
        $cooldown = $this->action($path, ['key' => 'distinct_cultivation_key_0002'])['result'];
        $this->assertFalse($cooldown['success']);
        $this->assertGreaterThan(0, $cooldown['cooldown_remaining']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_zero_gain_and_no_season_replacement(string $path): void
    {
        $sid = $this->season('+2 DAY');
        DB::table('users')->where('id', 1)->update(['chi' => 1000]);
        $result = $this->action($path)['result'];
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['chi_gained']);
        $this->assertTrue($result['realm_level_cap_reached']);
        $this->assertFalse($result['level_up']);
        $this->assertSame(1, (int) DB::table('season_rankings')->value('score_cultivation'));
        DB::table('seasons')->where('id', $sid)->update(['status' => 'ended']);
        DB::table('users')->where('id', 1)->update(['last_cultivation_at' => null]);
        $result = $this->action($path, ['key' => 'without_season_key_0003'])['result'];
        $this->assertTrue($result['success']);
        $this->assertNotSame($sid, $result['scoring']['season_id']);
        $this->assertTrue($result['maintenance']['committed']);
        $this->assertSame(2, DB::table('seasons')->count());
    }
    public static function faults(): array
    {
        $cases = [];
        foreach (['laravel', 'legacy'] as $path) {
            foreach (['before_category', 'before_total', 'before_ledger', 'before_cave'] as $fault) {
                $cases[$path.'_'.$fault] = [$path, $fault];
            }
        }
        return $cases;
    }

    #[DataProvider('faults')]
    public function test_required_database_errors_roll_back_all_effects(string $path, string $fault): void
    {
        $this->season('+2 DAY');
        $result = $this->action($path, ['fault' => $fault])['result'];
        $this->assertFalse($result['success']);
        $this->assertSame('rolled_back', $result['operation_status']);
        $this->assertSame(100, (int) DB::table('users')->find(1)->chi);
        $this->assertNull(DB::table('users')->find(1)->last_cultivation_at);
        $this->assertSame(0, DB::table('season_rankings')->count());
        $this->assertSame(0, DB::table('player_caves')->count());
        $this->assertSame(0, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_real_commit_acknowledgement_loss_resolves_on_fresh_pdo(string $path): void
    {
        $this->season('+2 DAY');
        $result = $this->action($path, ['fault' => 'after_commit'])['result'];
        $this->assertSame('uncertain', $result['operation_status']);
        $this->assertTrue($result['connection_quarantined']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
        $chi = (int) DB::table('users')->find(1)->chi;
        $replay = $this->action($path)['result'];
        $this->assertTrue($replay['success']);
        $this->assertSame('replayed', $replay['operation_status']);
        $this->assertSame($chi, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(1, DB::table('season_rankings')->count());
    }

    #[DataProvider('paths')]
    public function test_conflict_is_checked_before_cooldown(string $path): void
    {
        $this->action($path);
        DB::table('season_gameplay_operations')->update(['fingerprint' => str_repeat('0', 64)]);
        $result = $this->action($path)['result'];
        $this->assertSame('conflict', $result['operation_status']);
        $this->assertFalse($result['success']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_level_growth_and_its_failure_share_the_root_transaction(string $path): void
    {
        $this->season('+2 DAY');
        DB::table('realms')->where('id', 1)->update(['max_level' => 10]);
        DB::table('users')->where('id', 1)->update(['chi' => 1000]);
        $before = DB::table('users')->find(1);
        $failure = $this->action($path, ['fault' => 'before_attribute_growth'])['result'];
        $this->assertFalse($failure['success']);
        $this->assertSame((int) $before->level, (int) DB::table('users')->find(1)->level);
        $this->assertSame((int) $before->max_chi, (int) DB::table('users')->find(1)->max_chi);
        $this->assertSame(0, DB::table('season_gameplay_operations')->count());
        $result = $this->action($path)['result'];
        $this->assertTrue($result['success']);
        $this->assertTrue($result['level_up']);
        $this->assertNotEmpty($result['attribute_stat_gains']);
        $this->assertSame(0, $result['chi_gained']);
        $this->assertSame(1, (int) DB::table('season_rankings')->value('score_cultivation'));
    }

    #[DataProvider('paths')]
    public function test_required_maintenance_notifications_roll_back_rewards_and_titles(string $path): void
    {
        $sid = $this->season('-1 DAY');
        DB::table('season_rankings')->insert(['season_id' => $sid, 'user_id' => 1, 'score_cultivation' => 1, 'total_score' => 1]);
        $result = $this->action($path, ['fault' => 'before_notification'])['result'];
        $this->assertFalse($result['success']);
        $this->assertFalse($result['maintenance']['committed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $sid)->value('status'));
        $this->assertSame(10000, (int) DB::table('users')->find(1)->gold);
        $this->assertSame(0, DB::table('user_titles')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    #[DataProvider('paths')]
    public function test_real_rollback_and_release_failures_are_quarantined(string $path): void
    {
        $this->season('+2 DAY');
        $result = $this->action($path, ['fault' => 'before_category', 'rollback' => 'throw'])['result'];
        $this->assertSame('uncertain', $result['operation_status']);
        $this->assertTrue($result['connection_quarantined']);
        $this->assertSame(0, DB::table('season_gameplay_operations')->count());
        $result = $this->action($path, ['fault' => 'after_release'])['result'];
        $this->assertTrue($result['success']);
        $this->assertSame('committed_cleanup_failed', $result['operation_status']);
        $this->assertTrue($result['gameplay_committed']);
        $this->assertTrue($result['connection_quarantined']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_overlapping_eligibility_fails_closed(string $path): void
    {
        $this->season('+2 DAY');
        $this->season('+3 DAY');
        $result = $this->action($path)['result'];
        $this->assertFalse($result['success']);
        $this->assertSame(100, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(0, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_future_schedule_and_committed_maintenance_are_explicit(string $path): void
    {
        $sid = $this->season('+2 DAY');
        DB::table('seasons')->where('id', $sid)->update(['starts_at' => DB::raw('DATE_ADD(NOW(), INTERVAL 1 DAY)')]);
        $this->assertFalse($this->action($path)['result']['success']);
        $this->assertSame(100, (int) DB::table('users')->find(1)->chi);
        DB::table('seasons')->where('id', $sid)->update([
            'starts_at' => DB::raw('DATE_SUB(NOW(), INTERVAL 2 DAY)'),
            'ends_at' => DB::raw('DATE_SUB(NOW(), INTERVAL 1 DAY)'),
        ]);
        $result = $this->action($path, ['fault' => 'before_category'])['result'];
        $this->assertFalse($result['success']);
        $this->assertTrue($result['maintenance']['committed']);
        $this->assertSame([$sid], $result['maintenance']['finalized']);
        $this->assertSame('ended', DB::table('seasons')->where('id', $sid)->value('status'));
        $this->assertSame(100, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(0, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_worker_death_rolls_back_and_a_fresh_connection_can_retry(string $path): void
    {
        $this->season('+2 DAY');
        $first = $this->startScoring($path, 'cultivate', ['barrier' => 'before_ledger']);
        $this->awaitScoringBarrier($first, 'before_ledger');
        $first->stop(1);
        $this->assertFalse($first->isRunning());
        $retry = $this->action($path)['result'];
        $this->assertTrue($retry['success']);
        $this->assertSame('committed', $retry['operation_status']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
        $this->assertSame(100 + $retry['chi_gained'], (int) DB::table('users')->find(1)->chi);
    }

    #[DataProvider('paths')]
    public function test_same_key_workers_serialize_and_partial_writes_are_invisible(string $path): void
    {
        $this->season('+2 DAY');
        $first = $this->startScoring($path, 'cultivate', ['barrier' => 'before_ledger']);
        $this->awaitScoringBarrier($first, 'before_ledger');
        $this->assertSame(100, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(0, DB::table('season_rankings')->count());
        $second = $this->startScoring($path, 'cultivate');
        $this->awaitScoringLockWait($first, $second);
        $this->releaseScoringBarrier($first, 'before_ledger');
        $a = $this->finishScoring($first)['result'];
        $b = $this->finishScoring($second)['result'];
        $this->assertTrue($a['success']);
        $this->assertSame('replayed', $b['operation_status']);
        $this->assertSame($a['chi_gained'], $b['chi_gained']);
        $this->assertSame(1, DB::table('season_gameplay_operations')->count());
    }

    #[DataProvider('paths')]
    public function test_pinned_admission_survives_crossing_end_boundary(string $path): void
    {
        $sid = $this->season('+2 DAY');
        DB::table('seasons')->where('id', $sid)->update([
            'starts_at' => '2026-11-06 00:00:00', 'ends_at' => '2026-11-06 00:01:00',
        ]);
        // Child's DB clock is deterministic. Parent's wall clock is irrelevant.
        $clock = (string) strtotime('2026-11-06 00:00:59 UTC');
        $first = $this->startScoring($path, 'cultivate', ['clock' => $clock, 'barrier' => 'before_gameplay',
            'advance_at' => 'before_gameplay', 'advance_clock' => (string) strtotime('2026-11-06 00:01:01 UTC')]);
        $this->awaitScoringBarrier($first, 'before_gameplay');
        $this->releaseScoringBarrier($first, 'before_gameplay');
        $result = $this->finishScoring($first)['result'];
        $this->assertTrue($result['success']);
        $this->assertSame($sid, $result['scoring']['season_id']);
        DB::table('users')->where('id', 1)->update(['last_cultivation_at' => null]);
        $result = $this->action($path, ['key' => 'at_end_boundary_key_0002', 'clock' => (string) strtotime('2026-11-06 00:01:00 UTC')])['result'];
        $this->assertTrue($result['success']);
        $this->assertNotSame($sid, $result['scoring']['season_id']);
        $this->assertSame('ended', DB::table('seasons')->where('id', $sid)->value('status'));
    }

    #[DataProvider('paths')]
    public function test_finalization_waits_for_admitted_gameplay_and_includes_its_score(string $path): void
    {
        $sid = $this->season('+2 DAY');
        DB::table('seasons')->where('id', $sid)->update([
            'starts_at' => '2026-11-06 00:00:00', 'ends_at' => '2026-11-06 00:01:00',
        ]);
        $first = $this->startScoring($path, 'cultivate', [
            'clock' => (string) strtotime('2026-11-06 00:00:59 UTC'), 'barrier' => 'before_ledger',
        ]);
        $this->awaitScoringBarrier($first, 'before_ledger');
        $finalizer = $this->startScoring($path, 'finalize', ['clock' => (string) strtotime('2026-11-06 00:01:00 UTC')]);
        $this->awaitScoringLockWait($first, $finalizer);
        $this->assertSame('active', DB::table('seasons')->where('id', $sid)->value('status'));
        $this->releaseScoringBarrier($first, 'before_ledger');
        $this->assertTrue($this->finishScoring($first)['result']['success']);
        $this->assertTrue($this->finishScoring($finalizer)['result']['ok']);
        $this->assertSame('ended', DB::table('seasons')->where('id', $sid)->value('status'));
        $this->assertSame(1, (int) DB::table('season_rankings')->where('season_id', $sid)->value('rank_cultivation'));
    }

    #[DataProvider('paths')]
    public function test_different_keys_serialize_on_cooldown_and_lock_wait_is_bounded(string $path): void
    {
        $this->season('+2 DAY');
        $first = $this->startScoring($path, 'cultivate', ['barrier' => 'before_ledger']);
        $this->awaitScoringBarrier($first, 'before_ledger');
        $start = hrtime(true);
        $blocked = $this->action($path, ['key' => 'second_concurrent_key_0002'])['result'];
        $elapsed = (hrtime(true) - $start) / 1e9;
        $this->assertFalse($blocked['success']);
        $this->assertLessThan(7.0, $elapsed);
        $this->releaseScoringBarrier($first, 'before_ledger');
        $this->assertTrue($this->finishScoring($first)['result']['success']);
        $again = $this->action($path, ['key' => 'second_concurrent_key_0002'])['result'];
        $this->assertFalse($again['success']);
        $this->assertGreaterThan(0, $again['cooldown_remaining']);
        $this->assertSame(1, DB::table('season_rankings')->count());
    }

    #[DataProvider('paths')]
    public function test_bounded_performance_sample(string $path): void
    {
        $this->season('+2 DAY');
        $samples = [];
        $held = [];
        $operations = [];
        for ($i = 0; $i < 5; $i++) {
            DB::table('users')->where('id', 1)->update(['last_cultivation_at' => null]);
            $start = hrtime(true);
            $probe = $this->action($path, ['key' => 'performance_sample_key_000'.$i]);
            $result = $probe['result'];
            $samples[] = (hrtime(true) - $start) / 1e6;
            $operations[] = $probe['operation_ms'];
            $held[] = ($probe['event_times']['before_release'][0] - $probe['event_times']['after_acquire'][0]) / 1e6;
            $this->assertTrue($result['success']);
        }
        sort($samples);
        sort($held);
        sort($operations);
        fwrite(STDERR, sprintf("\nCultivation %s n=5 worker median=%.2fms max=%.2fms operation median=%.2fms max=%.2fms mutex-held median=%.2fms max=%.2fms\n",
            $path, $samples[2], max($samples), $operations[2], max($operations), $held[2], max($held)));
        $this->assertSame(5, DB::table('season_gameplay_operations')->count());
    }
}
