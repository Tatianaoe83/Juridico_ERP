<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Licencia, permiso o trámite que la empresa debe mantener vigente.
 */
class License extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
        ];
    }
}
