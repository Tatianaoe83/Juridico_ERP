<?php

namespace App\Services\Microsoft;

/**
 * Dueño del calendario sobre el que opera la app.
 *
 * Dos implementaciones, una por modo:
 *  - MicrosoftAccount: la cuenta vinculada de cada usuario (delegado, /me).
 *  - SharedMailbox: el buzón general de la organización (aplicación, /users/{correo}).
 *
 * MicrosoftGraph solo habla con esta interfaz; el modo lo decide
 * User::calendarOwner().
 */
interface CalendarOwner
{
    /** Raíz de Graph del buzón: /me o /users/{correo}. */
    public function graphRoot(): string;

    /** Calendario concreto dentro del buzón. Null = el principal. */
    public function calendarId(): ?string;

    /** Correo del buzón: es el organizador de los eventos. */
    public function mailboxEmail(): string;

    public function canReadCalendar(): bool;

    public function canWriteCalendar(): bool;

    /**
     * El calendario es solo de la app. Si no lo es, compartirlo expondría
     * la agenda personal de alguien.
     */
    public function hasDedicatedCalendar(): bool;

    public function calendarVersion(): int;

    public function bumpCalendarVersion(): void;

    /** Deja constancia de la última lectura o escritura contra Graph. */
    public function markSynced(): void;
}
