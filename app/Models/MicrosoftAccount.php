<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MicrosoftAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** El token de acceso caduca en ~1h; se renueva con un margen de 2 min. */
    public function tokenExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->subMinutes(2)->isPast();
    }

    /**
     * Una cuenta vinculada solo con el SSO trae permisos de identidad.
     * Leer el calendario es un consentimiento aparte que puede no haberse dado.
     */
    public function canReadCalendar(): bool
    {
        return str_contains((string) $this->scopes, 'Calendars.Read');
    }
}
