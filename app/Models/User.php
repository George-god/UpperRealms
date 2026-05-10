<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * Game accounts use password_hash (not Laravel's default password column).
     */
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'is_admin',
        'realm_id',
        'level',
        'chi',
        'max_chi',
        'attack',
        'defense',
        'wins',
        'losses',
        'rating',
        'admin_level',
        'onboarding_step',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'last_cultivation_at' => 'datetime',
            'last_breakthrough_at' => 'datetime',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'rating' => 'decimal:2',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
