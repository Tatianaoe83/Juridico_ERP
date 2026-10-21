<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Aseguradora: emite las pólizas de obra, de flotilla y de maquinaria. */
class Insurer extends Model
{
    protected $guarded = [];

    protected $casts = ['preferred' => 'boolean'];

    /** Pólizas de flotilla y de maquinaria. */
    public function unitPolicies(): HasMany
    {
        return $this->hasMany(UnitPolicy::class);
    }

    public function constructionPolicies(): HasMany
    {
        return $this->hasMany(ConstructionPolicy::class);
    }
}
