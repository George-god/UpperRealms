<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Realm extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'realm_id');
    }
}
