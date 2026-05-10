<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bloodline extends Model
{
    protected $guarded = [];

    public function userBloodlines(): HasMany
    {
        return $this->hasMany(UserBloodline::class, 'bloodline_id');
    }
}
