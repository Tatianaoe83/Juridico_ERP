<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Afianzadora: emite las fianzas. Es otra razón social que la aseguradora. */
class BondingCompany extends Model
{
    protected $guarded = [];

    public function bonds(): HasMany
    {
        return $this->hasMany(Bond::class);
    }
}
