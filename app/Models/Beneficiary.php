<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Beneficiario: ante quién se garantiza una fianza y, en flotillas, el
 * preferente de la póliza del proveedor (agencia, financiera o arrendadora).
 */
class Beneficiary extends Model
{
    protected $guarded = [];

    public function bonds(): HasMany
    {
        return $this->hasMany(Bond::class);
    }

    /** Pólizas de flotilla donde es el beneficiario preferente. */
    public function unitPolicies(): HasMany
    {
        return $this->hasMany(UnitPolicy::class, 'supplier_id');
    }
}
