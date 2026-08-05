<?php

namespace App\Services\Calendar;

use App\Models\CalendarShare;
use App\Models\MicrosoftAccount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Qué calendarios puede ver una persona.
 *
 * Antes cada controlador leía `$user->microsoftAccount` y con eso decidía todo,
 * así que quien no había vinculado su cuenta no veía nada —aunque le hubieran
 * compartido un calendario—. Aquí se resuelve en un solo lugar: el propio, si
 * lo tiene, más los que otros compartieron con su correo.
 *
 * Los compartidos se leen con el token del dueño. No hace falta que el invitado
 * vincule nada ni conceder permisos nuevos en Graph.
 */
class CalendarAccess
{
    /**
     * Todos los calendarios que este usuario puede abrir, el propio primero.
     *
     * @return Collection<int, CalendarView>
     */
    public function available(User $user): Collection
    {
        return collect([$this->own($user), ...$this->shared($user)])
            ->filter()
            ->values();
    }

    /**
     * El calendario que se está viendo.
     *
     * @param  int|null  $ownerId  Dueño elegido en el selector; el primero si no viene.
     */
    public function resolve(User $user, ?int $ownerId = null): ?CalendarView
    {
        $available = $this->available($user);

        if ($ownerId) {
            $picked = $available->firstWhere('ownerId', $ownerId);

            if ($picked) {
                return $picked;
            }
        }

        return $available->first();
    }

    /** El calendario propio, si vinculó su cuenta y concedió el calendario. */
    private function own(User $user): ?CalendarView
    {
        $account = $user->microsoftAccount;

        if (! $account?->canReadCalendar()) {
            return null;
        }

        return new CalendarView(
            account: $account,
            ownerId: $user->id,
            ownerName: $user->name,
            ownerEmail: $user->email,
            role: 'owner',
        );
    }

    /**
     * Los que otros compartieron con este usuario.
     *
     * Se busca por su correo de la app y también por el de su cuenta de
     * Microsoft: pueden no ser el mismo, y compartir siempre se hace contra un
     * buzón de Outlook.
     *
     * @return array<int, CalendarView>
     */
    private function shared(User $user): array
    {
        $emails = collect([$user->email, $user->microsoftAccount?->email])
            ->filter()
            ->map(fn (string $email) => Str::lower($email))
            ->unique()
            ->all();

        return CalendarShare::query()
            ->whereIn('email', $emails)
            ->with('account.user')
            ->get()
            // Un calendario compartido consigo mismo ya salió como propio.
            ->reject(fn (CalendarShare $share) => $share->account?->user_id === $user->id)
            ->filter(fn (CalendarShare $share) => $share->canRead() && $share->account?->user)
            ->map(fn (CalendarShare $share) => new CalendarView(
                account: $share->account,
                ownerId: $share->account->user->id,
                ownerName: $share->account->user->name,
                ownerEmail: $share->account->email,
                role: $share->role,
            ))
            ->values()
            ->all();
    }

    /**
     * Deja la tabla igual a lo que dice Outlook.
     *
     * Se llama cada vez que el dueño abre su pantalla de compartir, así que un
     * acceso quitado desde Outlook desaparece de la app sin que nadie lo toque.
     *
     * @param  array<int, array<string, mixed>>  $permissions  Lo que devuelve MicrosoftGraph::calendarPermissions()
     */
    public function reconcile(MicrosoftAccount $account, array $permissions): void
    {
        $seen = [];

        foreach ($permissions as $permission) {
            $email = Str::lower((string) ($permission['email'] ?? ''));

            // El dueño figura en su propia lista, y Outlook mete entradas sin
            // correo real como el acceso público: ninguna es un compartido.
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $email === Str::lower($account->email)) {
                continue;
            }

            CalendarShare::updateOrCreate(
                ['microsoft_account_id' => $account->id, 'email' => $email],
                ['role' => $permission['role'] ?? 'read', 'permission_id' => $permission['id'] ?? null],
            );

            $seen[] = $email;
        }

        CalendarShare::where('microsoft_account_id', $account->id)
            ->whereNotIn('email', $seen)
            ->delete();
    }
}
