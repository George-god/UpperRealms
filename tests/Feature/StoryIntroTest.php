<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserStoryProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryIntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_continue_action_advances_via_form_post(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);

        $response = $this->actingAs($user)->post(route('story.action'), [
            'action' => 'continue',
        ]);

        $response->assertRedirect(route('story.intro'));
        $this->assertSame(
            'explore_forest',
            UserStoryProgress::query()->find($user->id)?->current_step
        );
    }

    public function test_meet_lin_mei_page_renders_dialogue_and_advances(): void
    {
        $user = User::factory()->create(['onboarding_step' => 1]);
        UserStoryProgress::query()->create([
            'user_id' => $user->id,
            'current_step' => 'meet_lin_mei',
            'completed_steps' => [],
            'flags' => [],
        ]);

        $this->actingAs($user)
            ->get(route('story.intro'))
            ->assertOk()
            ->assertSee('Lin Mei')
            ->assertSee('Ready your stance');

        $this->actingAs($user)->post(route('story.action'), ['action' => 'continue'])
            ->assertRedirect(route('story.intro'));

        $this->assertSame('fight_wolf', UserStoryProgress::query()->find($user->id)?->current_step);
    }
}
