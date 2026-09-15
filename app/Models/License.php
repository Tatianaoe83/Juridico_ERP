<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Licencia, permiso o trámite que la empresa debe mantener vigente.
 */
class License extends Model
{
    /** Los mismos valores que el enum de la columna `status`. */
    public const STATUSES = ['active', 'expiring', 'expired', 'in_progress'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
        ];
    }
}
