<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso previo de un pago: el evento que Outlook lanza tantos días antes del
 * vencimiento. El evento vive en Outlook; aquí solo queda su id.
 */
class UnitReminder extends Model
{
    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
