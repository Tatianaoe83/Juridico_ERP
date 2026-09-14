<?php

namespace App\Services\Microsoft;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * El buzón general (MS_MAILBOX) en modo aplicación.
 *
 * La app entra con su propio token (client credentials): nadie inicia sesión
 * con este correo ni hay refresh_token que se caduque. Lo único que vence es
 * el client secret en Azure. Conviene que sea un buzón compartido de Exchange:
 * no tiene contraseña ni requiere licencia.
 *
 * El acceso se limita a este buzón con una ApplicationAccessPolicy de Exchange;
 * sin ella el permiso de aplicación alcanza a todos los buzones del tenant.
 */
final class SharedMailbox implements CalendarOwner
{
    private function __construct(private readonly string $email) {}

    /** El buzón configurado, o null si falta MS_MAILBOX. */
    public static function configured(): ?self
    {
        $email = config('services.microsoft.mailbox');

        return filled($email) ? new self(Str::lower($email)) : null;
    }

    public function graphRoot(): string
    {
        return '/users/'.rawurlencode($this->email);
    }

    /** El buzón entero es de la app: se usa su calendario principal. */
    public function calendarId(): ?string
    {
        return null;
    }

    public function mailboxEmail(): string
    {
        return $this->email;
    }

    /** Lo concede el administrador en Azure, no cada usuario. */
    public function canReadCalendar(): bool
    {
        return true;
    }

    public function canWriteCalendar(): bool
    {
        return true;
    }

    public function hasDedicatedCalendar(): bool
    {
        return true;
    }

    public function calendarVersion(): int
    {
        return (int) Cache::get($this->calendarVersionKey(), 0);
    }

    public function bumpCalendarVersion(): void
    {
        Cache::forever($this->calendarVersionKey(), $this->calendarVersion() + 1);
    }

    /** No hay registro local del buzón donde guardarlo. */
    public function markSynced(): void {}

    private function calendarVersionKey(): string
    {
        return "graph:calendar_version:mailbox:{$this->email}";
    }
}
