<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CalendarEventNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Modo aplicación: todos trabajan sobre el buzón general con el token de la
 * app, sin que nadie tenga que conectar su Outlook.
 */
class SharedMailboxCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.microsoft.com/v1.0/users/agenda%40proser.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config([
            'services.microsoft.mode' => 'application',
            'services.microsoft.mailbox' => 'Agenda@proser.test',
            'services.microsoft.client_id' => 'client',
            'services.microsoft.client_secret' => 'secret',
            'services.microsoft.tenant' => 'tenant',
        ]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            self::GRAPH.'/calendar/calendarPermissions' => Http::response(['value' => [
                ['id' => 'p1', 'emailAddress' => ['address' => 'socio@proser.test'], 'role' => 'read', 'isRemovable' => true],
            ]]),
            self::GRAPH.'/events' => Http::response($this->graphEvent()),
        ]);
    }

    private function userWithoutOutlook(): User
    {
        return User::factory()->create(['email' => 'abogado@proser.test'])->syncRoles('user');
    }

    /** @return array<string, mixed> */
    private function graphEvent(): array
    {
        return [
            'id' => 'evt-1',
            'subject' => 'Audiencia',
            'start' => ['dateTime' => '2026-09-20T10:00:00'],
            'end' => ['dateTime' => '2026-09-20T11:00:00'],
            'isAllDay' => false,
            'organizer' => ['emailAddress' => ['name' => 'Agenda', 'address' => 'agenda@proser.test']],
            'attendees' => [
                ['emailAddress' => ['address' => 'socio@proser.test'], 'status' => ['response' => 'none']],
            ],
        ];
    }

    public function test_crea_el_evento_en_el_buzon_general_con_el_token_de_la_app(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithoutOutlook())
            ->post('/eventos', [
                'title' => 'Audiencia',
                'starts_at' => '2026-09-20 10:00',
                'ends_at' => '2026-09-20 11:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::GRAPH.'/events'
            && $request->hasHeader('Authorization', 'Bearer app-token')
            // Los que tienen acceso al calendario van como invitados.
            && $request['attendees'][0]['emailAddress']['address'] === 'socio@proser.test');

        // Nadie tuvo que vincular su cuenta.
        $this->assertDatabaseCount('microsoft_accounts', 0);
    }

    public function test_la_confirmacion_va_a_quien_hizo_el_cambio_y_no_al_buzon_general(): void
    {
        Notification::fake();

        $this->actingAs($this->userWithoutOutlook())->post('/eventos', [
            'title' => 'Audiencia',
            'starts_at' => '2026-09-20 10:00',
            'ends_at' => '2026-09-20 11:00',
        ]);

        $recipients = [];
        Notification::assertSentOnDemand(
            CalendarEventNotification::class,
            function (CalendarEventNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$recipients) {
                $recipients[] = $notifiable->routes['mail'];

                return true;
            },
        );

        $this->assertContains('abogado@proser.test', $recipients);
        $this->assertNotContains('agenda@proser.test', $recipients);
    }

    public function test_el_calendario_muestra_el_buzon_general(): void
    {
        $this->actingAs($this->userWithoutOutlook())
            ->get('/calendario')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('mode', 'application')
                ->where('connection.email', 'agenda@proser.test'));
    }

    public function test_sin_buzon_configurado_no_deja_crear(): void
    {
        config(['services.microsoft.mailbox' => null]);

        $this->actingAs($this->userWithoutOutlook())
            ->post('/eventos', [
                'title' => 'Audiencia',
                'starts_at' => '2026-09-20 10:00',
                'ends_at' => '2026-09-20 11:00',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_en_modo_delegado_sigue_exigiendo_cuenta_vinculada(): void
    {
        config(['services.microsoft.mode' => 'delegated']);

        $this->actingAs($this->userWithoutOutlook())
            ->post('/eventos', [
                'title' => 'Audiencia',
                'starts_at' => '2026-09-20 10:00',
                'ends_at' => '2026-09-20 11:00',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
