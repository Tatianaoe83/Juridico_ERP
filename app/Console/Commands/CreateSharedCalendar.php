<?php

namespace App\Console\Commands;

use App\Models\MicrosoftAccount;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crea el calendario dedicado donde viven los eventos de la app y devuelve su
 * id para MS_CALENDAR_ID. Se corre una vez por entorno.
 */
class CreateSharedCalendar extends Command
{
    protected $signature = 'calendario:crear
                            {nombre? : Nombre visible; por defecto MS_CALENDAR_NAME}
                            {--user= : Id del usuario dueño; por defecto la última cuenta vinculada}';

    protected $description = 'Repara el calendario dedicado de una cuenta vinculada';

    public function handle(MicrosoftGraph $graph): int
    {
        $account = $this->option('user')
            ? MicrosoftAccount::where('user_id', $this->option('user'))->first()
            : MicrosoftAccount::latest('id')->first();

        if (! $account) {
            $this->error('No hay ninguna cuenta de Microsoft vinculada.');

            return self::FAILURE;
        }

        if (! $account->canWriteCalendar()) {
            $this->error("La cuenta {$account->email} no tiene Calendars.ReadWrite. Reconéctala.");

            return self::FAILURE;
        }

        $existing = $account->calendar_id;

        try {
            $calendarId = $graph->ensureCalendar($account, $this->argument('nombre'));
        } catch (Throwable $e) {
            $this->error('Graph rechazó la petición: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        $this->info($calendarId === $existing
            ? "{$account->email} ya tenía su calendario. No se creó otro."
            : "Calendario creado en {$account->email} y guardado en la cuenta.");

        $this->line("calendar_id: {$calendarId}");
        $this->newLine();

        return self::SUCCESS;
    }
}
