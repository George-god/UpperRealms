<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\SeasonService;
use Illuminate\Support\Facades\DB;
use Tests\Support\DisposableSeasonDatabase;

class SeasonProcessingTest extends DisposableSeasonDatabase
{
    public function test_rewards_ranks_titles_notifications_and_repeat_execution(): void
    {
        $season = $this->season('-1 DAY');
        foreach (range(1, 12) as $id) {
            DB::table('users')->insert(['id' => $id]);
            DB::table('season_rankings')->insert([
                'season_id' => $season, 'user_id' => $id,
                'total_score' => $id === 12 ? 0 : 100,
                'score_pvp' => $id === 2 ? 10 : 0,
                'score_world_boss' => $id === 3 ? 10 : 0,
                'score_cultivation' => $id === 4 ? 10 : 0,
                'score_sect' => $id === 5 ? 10 : 0,
            ]);
        }
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['processed']);
        $this->assertSame(17100, $result['detail'][0]['rewards']['gold']);
        foreach (range(1, 12) as $id) {
            $row = DB::table('users')->find($id);
            $gold = match (true) { $id === 1 => 5000, $id <= 3 => 2500, $id <= 10 => 1000, $id === 11 => 100, default => 0 };
            $stones = match (true) { $id === 1 => 50, $id <= 3 => 25, $id <= 10 => 10, default => 0 };
            $this->assertSame($gold, (int) $row->gold);
            $this->assertSame($stones, (int) $row->spirit_stones);
            $this->assertSame($id, (int) DB::table('season_rankings')->where('user_id', $id)->value('rank_overall'));
        }
        foreach ([[1, 13], [2, 14], [3, 14], [2, 15], [3, 16], [4, 17], [5, 18]] as [$user, $title]) {
            $this->assertTrue(DB::table('user_titles')->where(['user_id' => $user, 'title_id' => $title])->exists());
        }
        $this->assertSame(14, DB::table('notifications')->where('type', 'season_reward')->count());
        $before = DB::table('notifications')->count();
        $again = (new SeasonService)->processEndedSeasons();
        $this->assertSame(0, $again['processed']);
        $this->assertSame(17100, (int) DB::table('users')->sum('gold'));
        $this->assertSame($before, DB::table('notifications')->count());
        $this->assertSame(7, DB::table('user_titles')->count());
    }

    public function test_only_expired_active_seasons_are_processed_in_id_order(): void
    {
        $first = $this->season('-2 DAY');
        $future = $this->season('+2 DAY');
        $ended = $this->season('-3 DAY', 'ended');
        $last = $this->season('-1 DAY');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertSame([$first, $last], array_column($result['detail'], 'season_id'));
        $this->assertSame('active', DB::table('seasons')->where('id', $future)->value('status'));
        $this->assertSame('ended', DB::table('seasons')->where('id', $ended)->value('status'));
        $this->assertSame(4, DB::table('seasons')->count());
    }

    public function test_empty_database_gets_one_28_day_season_with_existing_weights(): void
    {
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['processed']);
        $row = DB::table('seasons')->first();
        $this->assertSame('active', $row->status);
        $this->assertSame(28, (int) DB::scalar('SELECT DATEDIFF(ends_at, starts_at) FROM seasons'));
        $this->assertEquals([1, 0.01, 1, 5], [$row->weight_pvp, $row->weight_boss, $row->weight_cultivation, $row->weight_sect]);
        (new SeasonService)->processEndedSeasons();
        $this->assertSame(1, DB::table('seasons')->count());
    }

    public function test_failure_rolls_back_ranks_and_season_status(): void
    {
        $id = $this->season('-1 DAY');
        DB::table('users')->insert(['id' => 1]);
        DB::table('season_rankings')->insert(['season_id' => $id, 'user_id' => 1, 'total_score' => 10]);
        DB::statement('ALTER TABLE users DROP COLUMN gold');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $id)->value('status'));
        $this->assertNull(DB::table('season_rankings')->value('rank_overall'));
        $this->assertSame(0, DB::table('user_titles')->count());
    }

    public function test_category_ties_use_lowest_user_id_and_zero_categories_grant_no_title(): void
    {
        $season = $this->season('-1 DAY');
        foreach ([2, 1] as $id) {
            DB::table('users')->insert(['id' => $id]);
            DB::table('season_rankings')->insert([
                'season_id' => $season, 'user_id' => $id, 'total_score' => 10, 'score_pvp' => 10,
            ]);
        }
        $this->assertTrue((new SeasonService)->processEndedSeasons()['ok']);
        $this->assertSame([1], DB::table('user_titles')->where('title_id', 15)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('user_titles')->whereIn('title_id', [16, 17, 18])->count());
        $this->assertSame(1, (int) DB::table('season_rankings')->where('user_id', 1)->value('rank_pvp'));
    }

    public function test_exact_expiry_boundary_is_not_yet_finalized(): void
    {
        // Freeze this disposable connection's database clock; application time mocking does not affect NOW().
        DB::statement('SET timestamp = 1800000000');
        $id = $this->season('0 SECOND');
        $result = (new SeasonService)->processEndedSeasons();
        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame('active', DB::table('seasons')->where('id', $id)->value('status'));
        // Existing behavior: equality is neither finalizable (<) nor a current active season (>).
        $this->assertSame(2, DB::table('seasons')->count());
    }

}
