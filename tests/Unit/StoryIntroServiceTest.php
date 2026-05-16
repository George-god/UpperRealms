<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserStoryProgress;
use App\Services\Story\StoryIntroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryIntroServiceTest extends TestCase
{
    use RefreshDatabase;

    private StoryIntroService $story;

    protected function setUp(): void
    {
        parent::setUp();
        $this->story = app(StoryIntroService::class);
    }

    public function test_new_player_starts_at_awakening(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);

        $scene = $this->story->getScene((int) $user->id);

        $this->assertSame('awakening', $scene['step_key']);
        $this->assertSame('Awakening', $scene['step']['title'] ?? null);
    }

    public function test_continue_action_advances_step(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);

        $result = $this->story->handleAction((int) $user->id, 'continue');

        $this->assertTrue($result['success']);
        $progress = UserStoryProgress::query()->find($user->id);
        $this->assertSame('explore_forest', $progress?->current_step);
    }

    public function test_inspect_tracks_flags_before_continue(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);
        UserStoryProgress::query()->create([
            'user_id' => $user->id,
            'current_step' => 'inspect_objects',
            'completed_steps' => [],
            'flags' => [],
        ]);

        $this->story->handleAction((int) $user->id, 'inspect', ['target' => 'relic']);
        $progress = UserStoryProgress::query()->find($user->id);

        $this->assertTrue($progress->flags['inspected']['relic'] ?? false);

        $blocked = $this->story->handleAction((int) $user->id, 'continue');
        $this->assertFalse($blocked['success']);

        $this->story->handleAction((int) $user->id, 'inspect', ['target' => 'shrine']);
        $ok = $this->story->handleAction((int) $user->id, 'continue');
        $this->assertTrue($ok['success']);
        $this->assertSame('meet_lin_mei', UserStoryProgress::query()->find($user->id)?->current_step);
    }

    public function test_complete_intro_marks_progress_and_onboarding(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);

        $result = $this->story->completeIntro((int) $user->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($this->story->isComplete((int) $user->id));

        $user->refresh();
        $this->assertSame(99, (int) $user->onboarding_step);
        $this->assertNotNull($user->onboarding_completed_at);
    }
}
