<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unidad de negocio de la empresa. Catálogo compartido: hoy lo usan las
 * unidades de la flotilla y va a servir para los demás módulos.
 */
class BusinessUnit extends Model
{
    protected $guarded = [];

    /** Unidades de la flotilla que pertenecen a esta unidad de negocio. */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** Licencias y permisos de esta unidad de negocio. */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function bonds(): HasMany
    {
        return $this->hasMany(Bond::class);
    }

    /** Pólizas de maquinaria: la vehicular toma la de su unidad. */
    public function unitPolicies(): HasMany
    {
        return $this->hasMany(UnitPolicy::class);
    }

    public function constructionPolicies(): HasMany
    {
        return $this->hasMany(ConstructionPolicy::class);
    }
}
