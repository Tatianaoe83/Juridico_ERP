<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fija las garantías de control de acceso.
 *
 * Existe porque durante mucho tiempo Spatie estuvo instalado sin que ninguna
 * ruta comprobara nada: los permisos vivían en la base de datos y viajaban al
 * front, pero cualquier sesión válida podía borrar usuarios.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function withRole(string $role): User
    {
        return User::factory()->create()->syncRoles($role);
    }

    public function test_un_usuario_normal_no_puede_listar_ni_borrar_cuentas(): void
    {
        $user = $this->withRole('user');
        $otro = $this->withRole('user');

        $this->actingAs($user)->getJson('/api/v1/users')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/users')->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/users/{$otro->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otro->id]);
    }

    public function test_el_alta_responde_403_y_no_422_sin_permiso(): void
    {
        // La validación del FormRequest corre antes que el controlador: sin
        // autorizar dentro del propio request, quien no tiene permiso vería
        // las reglas de validación en lugar de un rechazo.
        $this->actingAs($this->withRole('user'))
            ->postJson('/api/v1/users', [])
            ->assertForbidden();
    }

    public function test_admin_administra_usuarios_pero_no_los_borra(): void
    {
        $admin = $this->withRole('admin');
        $otro = $this->withRole('user');

        $this->actingAs($admin)->getJson('/api/v1/users')->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/users/{$otro->id}")->assertForbidden();
    }

    public function test_admin_no_puede_tocar_a_un_superadmin(): void
    {
        $admin = $this->withRole('admin');
        $super = $this->withRole('superadmin');

        $this->actingAs($admin)
            ->patchJson("/api/v1/users/{$super->id}", ['name' => 'Degradado'])
            ->assertForbidden();
    }

    public function test_superadmin_pasa_sin_tener_permisos_asignados(): void
    {
        $super = $this->withRole('superadmin');
        $otro = $this->withRole('user');

        // El rol no lleva ni un permiso: todo sale del Gate::before.
        $this->assertCount(0, $super->getAllPermissions());

        $this->actingAs($super)->getJson('/api/v1/users')->assertOk();
        $this->actingAs($super)->deleteJson("/api/v1/users/{$otro->id}")->assertNoContent();
    }

    public function test_nadie_puede_borrarse_a_si_mismo(): void
    {
        $super = $this->withRole('superadmin');

        // Gate::before deja pasar al superadmin, así que esta barandilla no
        // puede vivir en la Policy: se comprueba en el controlador.
        $this->actingAs($super)
            ->deleteJson("/api/v1/users/{$super->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_las_rutas_del_calendario_exigen_permiso(): void
    {
        $sinPermisos = User::factory()->create();

        $this->actingAs($sinPermisos)->get('/calendario')->assertForbidden();
        $this->actingAs($this->withRole('user'))->get('/calendario')->assertOk();
    }

    public function test_solo_roles_manage_entra_a_roles_y_permisos(): void
    {
        $this->actingAs($this->withRole('admin'))->get('/roles')->assertForbidden();
        $this->actingAs($this->withRole('admin'))->get('/permisos')->assertForbidden();

        $this->actingAs($this->withRole('superadmin'))->get('/roles')->assertOk();
        $this->actingAs($this->withRole('superadmin'))->get('/permisos')->assertOk();
    }

    public function test_la_matriz_concede_y_revoca_permisos(): void
    {
        $super = $this->withRole('superadmin');

        $this->actingAs($super)
            ->patch('/permisos', ['role' => 'user', 'permission' => 'users.view', 'granted' => true])
            ->assertRedirect();

        $this->assertTrue(Role::findByName('user')->hasPermissionTo('users.view'));

        $this->actingAs($super)
            ->patch('/permisos', ['role' => 'user', 'permission' => 'users.view', 'granted' => false])
            ->assertRedirect();

        $this->assertFalse(Role::findByName('user')->fresh()->hasPermissionTo('users.view'));
    }

    public function test_no_se_le_marcan_permisos_al_superadmin(): void
    {
        // Dárselos rompería la garantía de que hereda los futuros.
        $this->actingAs($this->withRole('superadmin'))
            ->patch('/permisos', ['role' => 'superadmin', 'permission' => 'users.view', 'granted' => true])
            ->assertStatus(422);

        $this->assertCount(0, Role::findByName('superadmin')->permissions);
    }

    public function test_volver_a_sembrar_no_degrada_a_quien_fue_promovido(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->syncRoles('superadmin');

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            $admin->fresh()->hasRole('superadmin'),
            'El seeder volvió a pisar el rol asignado desde la interfaz.',
        );
    }

    public function test_volver_a_sembrar_no_revierte_la_matriz_de_permisos(): void
    {
        // Lo configurado desde /permisos manda: si el seeder lo pisara, cada
        // despliegue desharía en silencio el trabajo del administrador.
        $admin = Role::findByName('admin');

        $admin->givePermissionTo('users.delete');
        $admin->revokePermissionTo('users.create');

        $this->seed(RoleSeeder::class);

        $admin = $admin->fresh();

        $this->assertTrue($admin->hasPermissionTo('users.delete'), 'El seeder revocó un permiso concedido a mano.');
        $this->assertFalse($admin->hasPermissionTo('users.create'), 'El seeder repuso un permiso retirado a mano.');
    }

    public function test_nadie_edita_los_permisos_de_su_propio_rol(): void
    {
        // `roles.manage` sirve para repartir capacidades, no para concedérselas:
        // sin esta barandilla, un admin entra a la matriz y se enciende lo que
        // le falte.
        Role::findByName('admin')->givePermissionTo('roles.manage');

        $admin = $this->withRole('admin');

        $this->actingAs($admin)
            ->patch('/permisos', ['role' => 'admin', 'permission' => 'users.delete', 'granted' => true])
            ->assertStatus(422);

        $this->assertFalse(Role::findByName('admin')->fresh()->hasPermissionTo('users.delete'));
    }

    public function test_no_se_puede_retirar_un_permiso_base(): void
    {
        // Sin `calendar.view` ese rol entra y choca con un 403 en la pantalla
        // inicial, sin menú y sin forma de salir.
        $this->actingAs($this->withRole('superadmin'))
            ->patch('/permisos', ['role' => 'user', 'permission' => 'calendar.view', 'granted' => false])
            ->assertStatus(422);

        $this->assertTrue(Role::findByName('user')->fresh()->hasPermissionTo('calendar.view'));
    }

    public function test_crear_usuarios_no_permite_repartir_roles(): void
    {
        // `users.create` autoriza a dar de alta. Sin `roles.manage`, el rol que
        // venga en el formulario se ignora.
        $this->actingAs($this->withRole('admin'))
            ->post('/usuarios', [
                'name' => 'Colado',
                'email' => 'colado@proser.com.mx',
                'password' => 'Str0ng!Passw0rd#2026',
                'roles' => ['superadmin'],
            ])
            ->assertRedirect();

        $creado = User::where('email', 'colado@proser.com.mx')->firstOrFail();

        $this->assertFalse($creado->hasRole('superadmin'));
        $this->assertTrue($creado->hasRole('user'));
    }

    public function test_la_ficha_y_la_edicion_web_respetan_la_policy(): void
    {
        $admin = $this->withRole('admin');
        $super = $this->withRole('superadmin');
        $otro = $this->withRole('user');

        // El admin administra cuentas, pero no las de quien está por encima.
        $this->actingAs($admin)->get("/usuarios/{$otro->id}")->assertOk();
        $this->actingAs($admin)->get("/usuarios/{$otro->id}/editar")->assertOk();
        $this->actingAs($admin)->get("/usuarios/{$super->id}/editar")->assertForbidden();

        // Borrar no está en su rol; el superadmin sí puede.
        $this->actingAs($admin)->delete("/usuarios/{$otro->id}")->assertForbidden();
        $this->actingAs($super)->delete("/usuarios/{$otro->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $otro->id]);
    }

    public function test_nadie_cambia_su_propio_rol_desde_la_edicion_web(): void
    {
        $super = $this->withRole('superadmin');

        // Degradarse dejaría al sistema sin quien administre, y Gate::before
        // deja pasar al superadmin: la barandilla va en el controlador.
        $this->actingAs($super)
            ->patch("/usuarios/{$super->id}", ['name' => 'Yo mismo', 'roles' => ['user']])
            ->assertStatus(422);

        $this->assertTrue($super->fresh()->hasRole('superadmin'));
    }

    public function test_solo_roles_manage_cambia_el_rol_de_alguien(): void
    {
        $admin = $this->withRole('admin');
        $super = $this->withRole('superadmin');
        $victima = $this->withRole('user');

        $this->actingAs($admin)
            ->patch("/usuarios/{$victima->id}/rol", ['role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($super)
            ->patch("/usuarios/{$victima->id}/rol", ['role' => 'admin'])
            ->assertRedirect();

        $this->assertTrue($victima->fresh()->hasRole('admin'));
    }
}
