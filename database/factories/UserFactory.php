<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'is_admin' => false,
            'realm_id' => 1,
            'level' => 1,
            'chi' => 100,
            'max_chi' => 100,
            'attack' => 10,
            'defense' => 10,
            'wins' => 0,
            'losses' => 0,
            'rating' => 1000.00,
            'onboarding_step' => 1,
            'onboarding_completed_at' => now(),
        ];
    }
}
