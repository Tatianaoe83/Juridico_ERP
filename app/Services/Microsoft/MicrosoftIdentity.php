<?php

namespace App\Services\Microsoft;

use App\Models\MicrosoftAccount;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Traduce un perfil de Microsoft Graph a un usuario local.
 * Se usa tanto en el inicio de sesión por SSO como en la vinculación del calendario.
 */
class MicrosoftIdentity
{
    /** El correo con el que se conoce a la cuenta dentro del tenant. */
    public function email(array $profile): string
    {
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;

        if (! $email) {
            throw new RuntimeException('El perfil de Microsoft no trae correo.');
        }

        return Str::lower($email);
    }

    /**
     * Rechaza cuentas fuera del dominio permitido. Sirve contra invitados
     * externos del tenant, que sí pueden autenticarse pero no son del equipo.
     * Sin MS_ALLOWED_DOMAIN configurado, no filtra nada.
     */
    public function domainAllowed(string $email): bool
    {
        $domain = config('services.microsoft.allowed_domain');

        return blank($domain) || Str::endsWith($email, '@'.Str::lower($domain));
    }

    /**
     * Busca al usuario dueño de este perfil. Null si no tiene cuenta: aquí
     * nadie se da de alta solo, las cuentas las crea un administrador.
     */
    public function resolveUser(array $profile, string $email): ?User
    {
        // Ya vinculado: el id de Microsoft manda sobre el correo, que puede cambiar.
        $linked = MicrosoftAccount::where('microsoft_id', $profile['id'])->first();

        if ($linked) {
            return $linked->user;
        }

        // Mismo correo: la cuenta que el administrador creó para esa persona.
        return User::where('email', $email)->first();
    }
}
