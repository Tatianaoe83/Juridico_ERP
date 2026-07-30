<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Crear eventos necesita ReadWrite. Una cuenta vinculada antes de que la
     * app pidiera ese permiso solo tiene Calendars.Read y hay que reconectarla.
     */
    public function canWriteCalendar(): bool
    {
        return str_contains((string) $this->scopes, 'Calendars.ReadWrite');
    }

    /**
     * Las respuestas de Graph se cachean por rango de fechas, así que crear un
     * evento no basta para que aparezca. Este número va dentro de la clave:
     * al subirlo, todo lo cacheado de esta cuenta deja de usarse.
     */
    public function calendarVersion(): int
    {
        return (int) Cache::get($this->calendarVersionKey(), 0);
    }

    public function bumpCalendarVersion(): void
    {
        Cache::forever($this->calendarVersionKey(), $this->calendarVersion() + 1);
    }

    private function calendarVersionKey(): string
    {
        return "graph:calendar_version:{$this->id}";
    }
}
