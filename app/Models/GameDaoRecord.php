<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameDaoRecord extends Model
{
    protected $table = 'dao_records';

    const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'user_id',
        'target_id',
        'description',
        'context_data',
    ];

    protected function casts(): array
    {
        return [
            'context_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
