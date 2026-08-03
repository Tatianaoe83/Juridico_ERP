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
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->syncRoles('superadmin');

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertTrue(
            $admin->fresh()->hasRole('superadmin'),
            'El seeder volvió a pisar el rol asignado desde la interfaz.',
        );
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
