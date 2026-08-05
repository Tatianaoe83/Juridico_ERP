<?php

namespace App\Services\Microsoft;

use App\Models\MicrosoftAccount;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Traduce un perfil de Microsoft Graph a un usuario local.
 * Se usa tanto en el alta por SSO como en la vinculación del calendario.
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
     * Busca al usuario dueño de este perfil; lo da de alta si es su primera vez.
     */
    public function resolveUser(array $profile, string $email): User
    {
        // Ya vinculado: el id de Microsoft manda sobre el correo, que puede cambiar.
        $linked = MicrosoftAccount::where('microsoft_id', $profile['id'])->first();

        if ($linked) {
            return $linked->user;
        }

        // Mismo correo: adopta la cuenta local que ya existía.
        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        return $this->provision($profile, $email);
    }

    /** Alta nueva. Sin contraseña utilizable: solo se entra por Microsoft. */
    private function provision(array $profile, string $email): User
    {
        $user = User::create([
            'name' => $profile['displayName'] ?? Str::before($email, '@'),
            'email' => $email,
            'password' => Str::password(64),
        ]);

        // Microsoft ya verificó el correo, no hace falta repetirlo.
        $user->forceFill(['email_verified_at' => now()])->save();

        if (Role::where('name', AuthService::DEFAULT_ROLE)->where('guard_name', 'web')->exists()) {
            $user->syncRoles(AuthService::DEFAULT_ROLE);
        }

        return $user;
    }
}
