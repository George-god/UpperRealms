<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStoryProgress extends Model
{
    protected $table = 'user_story_progress';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'current_step',
        'completed_steps',
        'flags',
        'intro_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'completed_steps' => 'array',
            'flags' => 'array',
            'intro_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->intro_completed_at !== null;
    }
}
