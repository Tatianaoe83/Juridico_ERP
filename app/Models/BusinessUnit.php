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
}
