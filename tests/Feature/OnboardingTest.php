<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_is_redirected_to_welcome_from_hub_until_onboarding_begins(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 0,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)->get(route('game.hub', absolute: false))
            ->assertRedirect(route('onboarding.welcome', absolute: false));
    }

    public function test_begin_onboarding_allows_hub(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 0,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)->post(route('onboarding.begin', absolute: false))
            ->assertRedirect(route('game.hub', absolute: false));

        $user->refresh();
        $this->assertSame(1, (int) $user->onboarding_step);
    }

    public function test_skip_marks_onboarding_complete(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 0,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)->post(route('onboarding.skip', absolute: false))
            ->assertRedirect(route('game.hub', absolute: false));

        $user->refresh();
        $this->assertNotNull($user->onboarding_completed_at);
    }
}
