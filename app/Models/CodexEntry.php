<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodexEntry extends Model
{
    protected $fillable = [
        'category',
        'entry_key',
        'title',
        'teaser',
        'body',
        'icon',
        'source_type',
        'source_id',
        'source_ref',
        'unlock_hint',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'source_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function userEntries(): HasMany
    {
        return $this->hasMany(UserCodexEntry::class);
    }
}
