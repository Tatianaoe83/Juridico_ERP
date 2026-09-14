<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta, edición y baja de usuarios desde la interfaz (/usuarios).
 */
class UserManagementTest extends TestCase
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

    public function test_superadmin_crea_un_usuario_con_rol(): void
    {
        $this->actingAs($this->withRole('superadmin'))
            ->post('/usuarios', [
                'name' => 'María López',
                'email' => 'maria@example.com',
                'password' => 'Secreta-123',
                'roles' => ['admin'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(User::where('email', 'maria@example.com')->firstOrFail()->hasRole('admin'));
    }

    public function test_admin_crea_usuarios_pero_no_reparte_roles(): void
    {
        // Sin roles.manage el rol se descarta: si no, un admin podría darse
        // de alta un superadmin.
        $this->actingAs($this->withRole('admin'))
            ->post('/usuarios', [
                'name' => 'Intruso',
                'email' => 'intruso@example.com',
                'password' => 'Secreta-123',
                'roles' => ['superadmin'],
            ])
            ->assertRedirect();

        $this->assertFalse(User::where('email', 'intruso@example.com')->firstOrFail()->hasRole('superadmin'));
    }

    public function test_un_usuario_normal_no_puede_crear_editar_ni_borrar(): void
    {
        $user = $this->withRole('user');
        $otro = $this->withRole('user');

        $this->actingAs($user)->post('/usuarios', [])->assertForbidden();
        $this->actingAs($user)->patch("/usuarios/{$otro->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete("/usuarios/{$otro->id}")->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otro->id]);
    }

    public function test_editar_sin_contrasena_conserva_la_actual(): void
    {
        $otro = $this->withRole('user');
        $hash = $otro->password;

        $this->actingAs($this->withRole('admin'))
            ->patch("/usuarios/{$otro->id}", ['name' => 'Nuevo nombre', 'email' => $otro->email, 'password' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $otro->refresh();
        $this->assertSame('Nuevo nombre', $otro->name);
        $this->assertSame($hash, $otro->password);
    }

    public function test_nadie_se_cambia_su_propio_rol_al_editarse(): void
    {
        $super = $this->withRole('superadmin');

        $this->actingAs($super)
            ->patch("/usuarios/{$super->id}", ['name' => $super->name, 'roles' => ['user']])
            ->assertRedirect();

        $this->assertTrue($super->fresh()->hasRole('superadmin'));
    }

    public function test_superadmin_borra_a_otros_pero_no_a_si_mismo(): void
    {
        $super = $this->withRole('superadmin');
        $otro = $this->withRole('user');

        $this->actingAs($super)->delete("/usuarios/{$otro->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $otro->id]);

        $this->actingAs($super)->delete("/usuarios/{$super->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_admin_no_borra_usuarios(): void
    {
        $otro = $this->withRole('user');

        $this->actingAs($this->withRole('admin'))->delete("/usuarios/{$otro->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $otro->id]);
    }
}
