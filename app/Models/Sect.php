<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sect extends Model
{
    protected $guarded = [];

    public function members(): HasMany
    {
        return $this->hasMany(SectMember::class, 'sect_id');
    }
}
