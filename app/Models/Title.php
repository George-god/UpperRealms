<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Title extends Model
{
    protected $guarded = [];

    public function userTitles(): HasMany
    {
        return $this->hasMany(UserTitle::class, 'title_id');
    }
}
