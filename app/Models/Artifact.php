<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Artifact extends Model
{
    protected $guarded = [];

    public function userArtifacts(): HasMany
    {
        return $this->hasMany(UserArtifact::class, 'artifact_id');
    }
}
