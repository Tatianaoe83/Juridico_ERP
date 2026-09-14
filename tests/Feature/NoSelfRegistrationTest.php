<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nadie se da de alta solo: las cuentas las crea un administrador.
 */
class NoSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_existe_el_registro_web_ni_por_api(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@proser.test', 'password' => 'password'])
            ->assertNotFound();
        $this->postJson('/api/v1/auth/register', ['name' => 'X', 'email' => 'x@proser.test', 'password' => 'password'])
            ->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_microsoft_no_da_de_alta_a_quien_no_tiene_cuenta(): void
    {
        $this->fakeMicrosoft('nuevo@proser.test');

        $this->withSession(['microsoft_state' => 'state-ok'])
            ->get('/auth/microsoft/callback?code=abc&state=state-ok')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_microsoft_deja_entrar_a_una_cuenta_creada_previamente(): void
    {
        $user = User::factory()->create(['email' => 'abogado@proser.test']);
        $this->fakeMicrosoft('abogado@proser.test');

        $this->withSession(['microsoft_state' => 'state-ok'])
            ->get('/auth/microsoft/callback?code=abc&state=state-ok')
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    private function fakeMicrosoft(string $email): void
    {
        config([
            'services.microsoft.client_id' => 'client',
            'services.microsoft.client_secret' => 'secret',
            'services.microsoft.tenant' => 'tenant',
            'services.microsoft.allowed_domain' => null,
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'user-token',
                'refresh_token' => 'refresh',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/v1.0/me*' => Http::response([
                'id' => 'ms-'.$email,
                'displayName' => 'Persona',
                'mail' => $email,
            ]),
        ]);
    }
}
