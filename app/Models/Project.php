<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Obra: lo que asegura una póliza de obra, con su dirección. */
class Project extends Model
{
    protected $guarded = [];

    public function constructionPolicies(): HasMany
    {
        return $this->hasMany(ConstructionPolicy::class);
    }
}
