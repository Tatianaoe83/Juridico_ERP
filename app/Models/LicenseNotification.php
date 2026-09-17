<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso de que una licencia está por vencer.
 *
 * Solo guarda cuándo: los minutos antes del vencimiento que se eligen en la
 * campana. Avisar es trabajo de Outlook, que lanza su notificación en Outlook
 * y Teams a esa hora. Uno por licencia y sin recurrencia.
 */
class LicenseNotification extends Model
{
    protected $table = 'reminder_licenses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'minutes_before' => 'integer',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
