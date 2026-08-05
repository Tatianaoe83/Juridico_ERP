<?php

namespace Tests\Feature;

use App\Models\CalendarShare;
use App\Models\MicrosoftAccount;
use App\Models\User;
use App\Services\Calendar\CalendarAccess;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fija que un calendario compartido se vea dentro de la app.
 *
 * Antes cada controlador leía `$user->microsoftAccount` y con eso decidía todo:
 * a quien le compartían un calendario le seguía saliendo «conecta tu cuenta», y
 * si la vinculaba acababa con un calendario propio y vacío en vez del que le
 * habían compartido.
 */
class CalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function access(): CalendarAccess
    {
        return app(CalendarAccess::class);
    }

    /** Un dueño con su cuenta vinculada y permiso de calendario. */
    private function owner(string $email = 'jefe@proser.com.mx'): User
    {
        $user = User::factory()->create(['email' => $email])->syncRoles('user');

        MicrosoftAccount::create([
            'user_id' => $user->id,
            'microsoft_id' => 'ms-'.$user->id,
            'email' => $email,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour(),
            'scopes' => 'openid profile email offline_access User.Read Calendars.ReadWrite',
            'calendar_id' => 'cal-'.$user->id,
        ]);

        return $user->fresh();
    }

    public function test_quien_no_vinculo_nada_no_ve_ningun_calendario(): void
    {
        $solo = User::factory()->create()->syncRoles('user');

        $this->assertCount(0, $this->access()->available($solo));
        $this->assertNull($this->access()->resolve($solo));
    }

    public function test_un_calendario_compartido_aparece_sin_vincular_cuenta(): void
    {
        $owner = $this->owner();
        $guest = User::factory()->create(['email' => 'becario@proser.com.mx'])->syncRoles('user');

        CalendarShare::create([
            'microsoft_account_id' => $owner->microsoftAccount->id,
            'email' => 'becario@proser.com.mx',
            'role' => 'read',
        ]);

        $view = $this->access()->resolve($guest->fresh());

        $this->assertNotNull($view, 'El invitado no vio el calendario que le compartieron.');
        $this->assertFalse($view->own());
        $this->assertSame($owner->id, $view->ownerId);
        // Se lee con el token del dueño: es lo que evita pedir permisos nuevos.
        $this->assertSame($owner->microsoftAccount->id, $view->account->id);
    }

    public function test_el_rol_de_outlook_decide_si_puede_escribir(): void
    {
        $owner = $this->owner();

        $lector = User::factory()->create(['email' => 'lector@proser.com.mx'])->syncRoles('user');
        $editor = User::factory()->create(['email' => 'editor@proser.com.mx'])->syncRoles('user');

        foreach ([['lector@proser.com.mx', 'read'], ['editor@proser.com.mx', 'write']] as [$email, $role]) {
            CalendarShare::create([
                'microsoft_account_id' => $owner->microsoftAccount->id,
                'email' => $email,
                'role' => $role,
            ]);
        }

        $this->assertFalse($this->access()->resolve($lector->fresh())->canWrite());
        $this->assertTrue($this->access()->resolve($editor->fresh())->canWrite());

        // Repartir accesos sigue siendo solo del dueño.
        $this->assertFalse($this->access()->resolve($editor->fresh())->canShare());
        $this->assertTrue($this->access()->resolve($owner)->canShare());
    }

    public function test_solo_disponibilidad_no_abre_el_calendario(): void
    {
        $owner = $this->owner();
        $guest = User::factory()->create(['email' => 'externo@proser.com.mx'])->syncRoles('user');

        // `freeBusyRead` no revela asunto ni detalles: la app no tiene vista
        // para eso, así que se trata como acceso insuficiente.
        CalendarShare::create([
            'microsoft_account_id' => $owner->microsoftAccount->id,
            'email' => 'externo@proser.com.mx',
            'role' => 'freeBusyRead',
        ]);

        $this->assertNull($this->access()->resolve($guest->fresh()));
    }

    public function test_el_propio_va_primero_y_el_selector_elige(): void
    {
        $owner = $this->owner();
        $otro = $this->owner('otro@proser.com.mx');

        CalendarShare::create([
            'microsoft_account_id' => $otro->microsoftAccount->id,
            'email' => $owner->email,
            'role' => 'write',
        ]);

        $available = $this->access()->available($owner->fresh());

        $this->assertCount(2, $available);
        $this->assertTrue($available->first()->own(), 'El propio debe ir primero.');

        // Con el id del dueño elegido se resuelve ese, no el primero.
        $picked = $this->access()->resolve($owner->fresh(), $otro->id);

        $this->assertSame($otro->id, $picked->ownerId);
    }

    public function test_reconciliar_refleja_lo_que_dice_outlook(): void
    {
        $owner = $this->owner();
        $account = $owner->microsoftAccount;

        CalendarShare::create([
            'microsoft_account_id' => $account->id,
            'email' => 'viejo@proser.com.mx',
            'role' => 'read',
        ]);

        // Lo que devolvería MicrosoftGraph::calendarPermissions(): el dueño
        // figura en su propia lista y hay entradas sin correo real.
        $this->access()->reconcile($account, [
            ['id' => 'p1', 'email' => $account->email, 'role' => 'owner'],
            ['id' => 'p2', 'email' => 'nuevo@proser.com.mx', 'role' => 'write'],
            ['id' => 'p3', 'email' => null, 'role' => 'read'],
        ]);

        $this->assertDatabaseMissing('calendar_shares', ['email' => 'viejo@proser.com.mx']);
        $this->assertDatabaseMissing('calendar_shares', ['email' => $account->email]);
        $this->assertDatabaseHas('calendar_shares', [
            'email' => 'nuevo@proser.com.mx',
            'role' => 'write',
            'permission_id' => 'p2',
        ]);
    }
}
