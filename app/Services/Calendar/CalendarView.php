<?php

namespace App\Services\Calendar;

use App\Models\MicrosoftAccount;

/**
 * Un calendario al que alguien tiene acceso, junto con qué puede hacer en él.
 *
 * Existe para que los controladores dejen de razonar sobre `microsoftAccount`
 * directo: lo que importa no es de quién es la cuenta, sino con qué token se
 * habla con Graph y qué se permite hacer con ella.
 */
final readonly class CalendarView
{
    public function __construct(
        /** La cuenta cuyo token se usa contra Graph: siempre la del dueño. */
        public MicrosoftAccount $account,
        public int $ownerId,
        public string $ownerName,
        public string $ownerEmail,
        /** 'owner' cuando es el propio, o el rol de Graph si es compartido. */
        public string $role,
    ) {}

    public function own(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Crear y editar eventos. El dueño siempre puede; un invitado necesita el
     * rol `write` de Outlook.
     */
    public function canWrite(): bool
    {
        return $this->own() || $this->role === 'write';
    }

    /** Solo el dueño reparte accesos: un invitado no re-comparte. */
    public function canShare(): bool
    {
        return $this->own();
    }

    /**
     * Lo que necesita el front para pintarlo y para el selector.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'owner_id' => $this->ownerId,
            'owner_name' => $this->ownerName,
            'owner_email' => $this->ownerEmail,
            'email' => $this->account->email,
            'display_name' => $this->account->display_name,
            'role' => $this->role,
            'own' => $this->own(),
            'can_write' => $this->canWrite(),
            'can_share' => $this->canShare(),
            'synced_at' => $this->account->synced_at?->toIso8601String(),
        ];
    }
}
