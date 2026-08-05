<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceso de alguien al calendario de otra cuenta.
 *
 * Refleja un `calendarPermission` de Graph. Se sincroniza cada vez que el dueño
 * abre su pantalla de compartir, así que si alguien revoca el acceso desde
 * Outlook la app se entera sola.
 */
class CalendarShare extends Model
{
    /** Roles de Graph, de menos a más. */
    public const ROLES = ['freeBusyRead', 'limitedRead', 'read', 'write'];

    /** Los que permiten crear y editar eventos. */
    private const WRITABLE = ['write', 'owner'];

    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MicrosoftAccount::class, 'microsoft_account_id');
    }

    public function canWrite(): bool
    {
        return in_array($this->role, self::WRITABLE, true);
    }

    /**
     * `freeBusyRead` solo revela horarios ocupados, sin asunto ni detalles.
     * La app no tiene una vista para eso, así que ese nivel no abre el
     * calendario: se trata como acceso insuficiente.
     */
    public function canRead(): bool
    {
        return $this->role !== 'freeBusyRead';
    }
}
