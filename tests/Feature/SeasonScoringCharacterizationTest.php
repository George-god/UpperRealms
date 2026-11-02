<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DisposableSeasonScoringDatabase;
use Tests\Support\SeasonScoringDiagnostics;

/** Baseline observations, including defects. Passing does NOT mean the proposed protocol exists. */
class SeasonScoringCharacterizationTest extends DisposableSeasonScoringDatabase
{
    public static function paths(): array
    {
        return ['laravel' => ['laravel'], 'legacy' => ['legacy']];
    }

    private function runAction(string $path, string $action, array $options = []): array
    {
        $probe = $this->finishScoring($this->startScoring($path, $action, $options));
        $context = SeasonScoringDiagnostics::failureContext($probe);
        $this->assertSame([], $probe['diagnostic_collection_errors'], 'Diagnostic collection failed.'.$context);
        $this->assertSame([], $probe['diagnostic_sqlstates'], 'Unexpected SQL errors invalidate a gameplay characterization.'.$context);
        $this->assertSame(0, $probe['unexpected_diagnostic_count'], 'Unexpected ancillary errors invalidate the fixture; do not treat swallowed failures as a pass.'.$context);

        return $probe;
    }

    #[DataProvider('paths')]
    public function test_existing_categories_weights_and_non_idempotent_repetition(string $path): void
    {
        $season = $this->season('+2 DAY');
        DB::table('seasons')->where('id', $season)->update([
            'weight_pvp' => 1.5, 'weight_boss' => 0.02, 'weight_cultivation' => 2.25, 'weight_sect' => 3,
        ]);
        $this->assertTrue($this->runAction($path, 'categories')['result']['completed']);
        $row = DB::table('season_rankings')->where('season_id', $season)->where('user_id', 1)->first();
        $this->assertSame([10, 123, 2, 3, 30], array_map('intval', [
            $row->score_pvp, $row->score_world_boss, $row->score_cultivation, $row->score_sect, $row->total_score,
        ]));
        $this->runAction($path, 'categories');
        $row = DB::table('season_rankings')->where('season_id', $season)->where('user_id', 1)->first();
        $this->assertSame([20, 246, 4, 6, 61], array_map('intval', [
            $row->score_pvp, $row->score_world_boss, $row->score_cultivation, $row->score_sect, $row->total_score,
        ]));
        // No operation-key input or durable replay exists in these hooks today.
    }

    #[DataProvider('paths')]
    public function test_cultivation_scores_actual_gain_after_gameplay_commit(string $path): void
    {
        $this->season('+2 DAY');
        $probe = $this->runAction($path, 'cultivate');
        $result = $probe['result'];
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['chi_gained']);
        $this->assertSame(100 + $result['chi_gained'], (int) DB::table('users')->find(1)->chi);
        $this->assertSame(max(1, (int) floor($result['chi_gained'] / 10)), (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->assertContains('before_category', $probe['events']);
        $this->assertContains('after_commit', $probe['events']);
        $this->assertLessThan(array_search('before_category', $probe['events'], true), array_search('after_commit', $probe['events'], true));
        $this->assertFalse($probe['transaction_open']);
    }

    #[DataProvider('paths')]
    public function test_existing_zero_gain_cultivation_still_scores_one_point(string $path): void
    {
        $this->season('+2 DAY');
        DB::table('users')->where('id', 1)->update(['chi' => 1000]);
        $result = $this->runAction($path, 'cultivate')['result'];
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['chi_gained']);
        $this->assertSame(1, (int) DB::table('season_rankings')->value('score_cultivation'));
    }

    #[DataProvider('paths')]
    public function test_existing_no_season_allows_unscored_gameplay_without_replacement(string $path): void
    {
        $result = $this->runAction($path, 'cultivate')['result'];
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(100, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(0, DB::table('seasons')->count());
        $this->assertSame(0, DB::table('season_rankings')->count());
    }

    #[DataProvider('paths')]
    public function test_existing_scoring_failure_does_not_roll_back_cultivation(string $path): void
    {
        $this->season('+2 DAY');
        $probe = $this->runAction($path, 'cultivate', ['fault' => 'before_category']);
        $this->assertTrue($probe['result']['success']);
        $this->assertGreaterThan(100, (int) DB::table('users')->find(1)->chi);
        $this->assertContains('after_commit', $probe['events']);
        $this->assertContains('before_category', $probe['events']);
        $this->assertNotContains('after_category', $probe['events']);
        $this->assertSame(1, DB::table('season_rankings')->count());
        $this->assertSame(0, (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->assertFalse($probe['transaction_open']);
    }

    #[DataProvider('paths')]
    public function test_existing_total_failure_leaves_category_and_gameplay_committed(string $path): void
    {
        $this->season('+2 DAY');
        $probe = $this->runAction($path, 'cultivate', ['fault' => 'before_total']);
        $this->assertTrue($probe['result']['success']);
        $this->assertGreaterThan(100, (int) DB::table('users')->find(1)->chi);
        $this->assertGreaterThan(0, (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->assertSame(0, (int) DB::table('season_rankings')->value('total_score'));
    }

    #[DataProvider('paths')]
    public function test_real_commit_acknowledgement_loss_can_persist_unscored_cultivation(string $path): void
    {
        $this->season('+2 DAY');
        $probe = $this->runAction($path, 'cultivate', ['fault' => 'after_commit']);
        $this->assertFalse($probe['result']['success']);
        $this->assertContains('after_commit', $probe['events']);
        $this->assertGreaterThan(100, (int) DB::table('users')->find(1)->chi, 'Observer sees a real commit, not a throw before persistence.');
        $this->assertNotNull(DB::table('users')->find(1)->last_cultivation_at);
        $this->assertSame(0, DB::table('season_rankings')->count());
        $this->assertFalse($probe['transaction_open']);
    }

    #[DataProvider('paths')]
    public function test_sect_donation_preserves_gold_units_exp_and_season_score(string $path): void
    {
        $this->season('+2 DAY');
        DB::table('sects')->insert(['id' => 1, 'name' => 'Synthetic sect', 'leader_id' => 2, 'leader_user_id' => 2]);
        DB::table('sect_members')->insert(['sect_id' => 1, 'user_id' => 1]);
        $result = $this->runAction($path, 'donate')['result'];
        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['contribution_gain']);
        $this->assertSame(6, $result['sect_exp_gain']);
        $this->assertSame(9650, (int) DB::table('users')->find(1)->gold);
        $this->assertSame(3, (int) DB::table('sect_members')->value('contribution'));
        $this->assertSame(6, (int) DB::table('sects')->value('sect_exp'));
        $this->assertSame(3, (int) DB::table('season_rankings')->value('score_sect'));
        $this->assertSame(15, (int) DB::table('season_rankings')->value('total_score'));
    }

    #[DataProvider('paths')]
    public function test_standalone_pvp_persists_battle_rewards_and_winner_only_score(string $path): void
    {
        $this->season('+2 DAY');
        $result = $this->runAction($path, 'battle')['result'];
        $this->assertTrue($result['success']);
        $winner = (int) $result['winner_id'];
        $loser = $winner === 1 ? 2 : 1;
        $this->assertSame(1, DB::table('battles')->count());
        $this->assertGreaterThan(0, DB::table('battle_logs')->count());
        $this->assertSame($winner, (int) DB::table('battles')->value('winner_id'));
        $this->assertSame(1, (int) DB::table('users')->find($winner)->wins);
        $this->assertSame(1, (int) DB::table('users')->find($loser)->losses);
        $rating = (float) DB::table('users')->find($winner)->rating;
        $this->assertSame(10000 + 25 + (int) floor($rating / 100), (int) DB::table('users')->find($winner)->gold);
        $this->assertSame(10000, (int) DB::table('users')->find($loser)->gold);
        $this->assertSame(1, DB::table('season_rankings')->count());
        $this->assertSame($winner, (int) DB::table('season_rankings')->value('user_id'));
        $this->assertSame(10, (int) DB::table('season_rankings')->value('score_pvp'));
        $this->assertSame(2, DB::table('dao_records')->count());
    }

    #[DataProvider('paths')]
    public function test_existing_challenge_battle_nested_transaction_failure(string $path): void
    {
        $this->season('+2 DAY');
        $challenge = DB::table('battle_challenges')->insertGetId([
            'challenger_id' => 1, 'defender_id' => 2, 'attacker_use_techniques' => false,
            'expires_at' => DB::raw('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
        ]);
        $probe = $this->runAction($path, 'challenge', ['challenge_id' => $challenge]);
        $this->assertFalse($probe['result']['success']);
        $this->assertSame(2, count(array_keys($probe['events'], 'before_begin', true)));
        $this->assertSame(1, count(array_keys($probe['events'], 'after_begin', true)));
        $this->assertSame('pending', DB::table('battle_challenges')->value('status'));
        $this->assertSame(5, (int) DB::table('users')->find(1)->pvp_stamina);
        $this->assertSame(0, DB::table('battles')->count());
        $this->assertSame(0, DB::table('season_rankings')->count());
        $this->assertFalse($probe['transaction_open']);
    }

    #[DataProvider('paths')]
    public function test_existing_missing_challenge_returns_with_an_open_transaction(string $path): void
    {
        $probe = $this->runAction($path, 'challenge', ['challenge_id' => 999]);
        $this->assertFalse($probe['result']['success']);
        $this->assertTrue($probe['transaction_open'], 'Baseline early return does not explicitly end the transaction.');
        $this->assertContains('after_begin', $probe['events']);
        $this->assertNotContains('before_commit', $probe['events']);
        $this->assertNotContains('before_rollback', $probe['events']);
        $this->assertSame(0, DB::table('battles')->count());
        // Child exit closes this test session; do not keep its leaked transaction in the parent.
    }

    #[DataProvider('paths')]
    public function test_boss_damage_cooldown_and_score_are_based_on_actual_damage(string $path): void
    {
        $this->season('+2 DAY');
        $this->bossFixture(1000000);
        $probe = $this->runAction($path, 'boss');
        $result = $probe['result'];
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['damage_dealt']);
        $this->assertSame(1000000 - $result['damage_dealt'], (int) DB::table('world_bosses')->value('current_hp'));
        $this->assertSame($result['damage_dealt'], (int) DB::table('boss_damage_log')->value('damage_dealt'));
        $this->assertSame($result['damage_dealt'], (int) DB::table('season_rankings')->value('score_world_boss'));
        $this->assertSame((int) floor(0.01 * $result['damage_dealt']), (int) DB::table('season_rankings')->value('total_score'));
        $this->assertNotNull(DB::table('users')->find(1)->last_boss_attack_at);
        $again = $this->runAction($path, 'boss')['result'];
        $this->assertFalse($again['success']);
        $this->assertGreaterThan(0, $again['cooldown_remaining']);
        $this->assertSame($result['damage_dealt'], (int) DB::table('boss_damage_log')->value('damage_dealt'));
    }

    #[DataProvider('paths')]
    public function test_killing_blow_caps_score_and_preserves_boss_rewards(string $path): void
    {
        $this->season('+2 DAY');
        $this->bossFixture(1);
        $result = $this->runAction($path, 'boss')['result'];
        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_dead']);
        $this->assertSame(1, $result['damage_dealt']);
        $this->assertSame(1, (int) DB::table('season_rankings')->value('score_world_boss'));
        $this->assertSame(11500, (int) DB::table('users')->find(1)->gold);
        $this->assertSame(10, (int) DB::table('users')->find(1)->spirit_stones);
        $this->assertSame(1, DB::table('boss_rewards')->count());
        $this->assertNotNull(DB::table('world_bosses')->value('rewards_distributed_at'));
        $rewards = $this->runAction($path, 'boss_rewards')['result'];
        $this->assertSame([
            ['gold' => 1500, 'spirit_stones' => 10], ['gold' => 1000, 'spirit_stones' => 7],
            ['gold' => 700, 'spirit_stones' => 5], ['gold' => 300, 'spirit_stones' => 2],
            ['gold' => 300, 'spirit_stones' => 2], ['gold' => 100, 'spirit_stones' => 1],
        ], $rewards);
    }

    public function test_sql_barrier_observes_gameplay_commit_before_seasonal_update(): void
    {
        $this->season('+2 DAY');
        $child = $this->startScoring('laravel', 'cultivate', ['barrier' => 'before_category']);
        $this->awaitScoringBarrier($child, 'before_category');
        $this->assertGreaterThan(100, (int) DB::table('users')->find(1)->chi);
        $this->assertSame(0, (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->releaseScoringBarrier($child, 'before_category');
        $probe = $this->finishScoring($child);
        $context = SeasonScoringDiagnostics::failureContext($probe);
        $this->assertSame([], $probe['diagnostic_collection_errors'], 'Diagnostic collection failed.'.$context);
        $this->assertSame([], $probe['diagnostic_sqlstates'], $context);
        $this->assertSame(0, $probe['unexpected_diagnostic_count'], $context);
        $this->assertTrue($probe['result']['success'], $context);
        $this->assertGreaterThan(0, (int) DB::table('season_rankings')->value('score_cultivation'));
        $this->assertNotSame((int) DB::scalar('SELECT CONNECTION_ID()'), $probe['connection_id']);
    }

    private function bossFixture(int $hp): void
    {
        DB::table('world_bosses')->insert([
            'name' => 'Synthetic boss', 'max_hp' => $hp, 'current_hp' => $hp,
            'spawn_time' => DB::raw('NOW()'), 'end_time' => DB::raw('DATE_ADD(NOW(), INTERVAL 1 DAY)'),
        ]);
    }
}
