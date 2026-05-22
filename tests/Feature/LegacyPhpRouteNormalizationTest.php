<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyPhpRouteNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_page_with_php_extension_is_reachable(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/game/classic/game.php')
            ->assertOk();
    }

    public function test_classic_page_without_php_extension_is_reachable(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/game/classic/game')
            ->assertOk();
    }

    public function test_pages_alias_accepts_php_suffix(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/game/pages/game.php')
            ->assertOk();
    }

    public function test_query_string_preserved_on_normalized_classic_url(): void
    {
        $user = User::factory()->create([
            'onboarding_step' => 99,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/game/classic/game.php?needs_login=1')
            ->assertOk();
    }
}
