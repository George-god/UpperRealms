<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCodexEntry extends Model
{
    protected $fillable = [
        'user_id',
        'codex_entry_id',
        'unlocked_at',
        'first_seen_at',
        'is_new',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'codex_entry_id' => 'integer',
            'unlocked_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'is_new' => 'boolean',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CodexEntry::class, 'codex_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
