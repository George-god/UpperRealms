<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectMember extends Model
{
    protected $guarded = [];

    public function sect(): BelongsTo
    {
        return $this->belongsTo(Sect::class, 'sect_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
