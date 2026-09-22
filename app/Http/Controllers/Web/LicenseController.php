<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\CalendarEvents;
use App\Services\LicenseCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Licencias y permisos (Cumplimiento): listado, alta, edición y baja.
 */
class LicenseController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly LicenseCalendar $calendar,
        private readonly CalendarEvents $events,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Un estado que no existe se ignora en vez de dejar la tabla vacía.
        $status = in_array($request->query('status'), License::STATUSES, true) ? $request->query('status') : null;

        // Empresa y autoridad se eligen de lo que ya está capturado, así que se
        // comparan completas; un valor que nadie tiene deja la tabla vacía y
        // eso es lo correcto: es lo que el filtro dice.
        $company = trim((string) $request->query('company', '')) ?: null;
        $authority = trim((string) $request->query('authority', '')) ?: null;

        // Año y mes van por separado: así se puede pedir todo 2027, todos los
        // noviembres, o noviembre de 2027. Un valor fuera de rango se ignora.
        $year = preg_match('/^\d{4}$/', (string) $request->query('year', '')) ? (int) $request->query('year') : null;
        $month = preg_match('/^(0?[1-9]|1[0-2])$/', (string) $request->query('month', '')) ? (int) $request->query('month') : null;

        $licenses = License::with(['notification', 'creator'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('authority', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($company, fn ($query) => $query->where('company', $company))
            ->when($authority, fn ($query) => $query->where('authority', $authority))
            ->when($year, fn ($query) => $query->whereYear('valid_until', $year))
            ->when($month, fn ($query) => $query->whereMonth('valid_until', $month))
            // Lo que vence antes va primero; lo que no vence, al final.
            ->orderByRaw('valid_until is null')
            ->orderBy('valid_until')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // El resumen es de todo el catálogo: no cambia con la búsqueda ni el filtro.
        $counts = License::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Licenses/Index', [
            'licenses' => [
                'data' => $licenses->getCollection()->map(fn (License $license) => [
                    'id' => $license->id,
                    'name' => $license->name,
                    'company' => $license->company,
                    'authority' => $license->authority,
                    'valid_until' => $license->valid_until?->toDateString(),
                    'valid_time' => $license->valid_time ? substr($license->valid_time, 0, 5) : null,
                    'status' => $license->status,
                    // Para el modal de detalle: evita pedir el registro otra vez.
                    'comments' => $license->comments,
                    'created_at' => $license->created_at?->toIso8601String(),
                    'created_by' => $license->creator?->name,
                    // Para el modal de la campana; null = sin recordatorio.
                    'notification' => $license->notification ? [
                        'minutes_before' => $license->notification->minutes_before,
                    ] : null,
                ]),
                'meta' => [
                    'total' => $licenses->total(),
                    'per_page' => $licenses->perPage(),
                    'current_page' => $licenses->currentPage(),
                    'last_page' => $licenses->lastPage(),
                ],
            ],
            'stats' => [
                'total' => (int) $counts->sum(),
                ...collect(License::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company' => $company,
                'authority' => $authority,
                'year' => $year ? (string) $year : null,
                'month' => $month ? str_pad((string) $month, 2, '0', STR_PAD_LEFT) : null,
            ],
            // Las opciones salen de lo capturado: nadie mantiene catálogos y
            // nunca se ofrece un filtro que no devuelva nada.
            'options' => [
                'companies' => $this->distinct('company'),
                'authorities' => $this->distinct('authority'),
                'years' => $this->years(),
            ],
            // Quién recibe la invitación y el correo: con quién está compartido
            // el calendario. Se muestra de solo lectura en el modal del aviso.
            'sharedWith' => $this->events->sharedWith($request->user()),
        ]);
    }

    /**
     * Valores distintos de una columna, para llenar un filtro.
     *
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return License::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /**
     * Años en los que vence algo. Los meses no salen de aquí: son los doce
     * siempre, para poder pedir «todos los noviembres» sin importar el año.
     *
     * @return list<string>
     */
    private function years(): array
    {
        return License::query()
            ->whereNotNull('valid_until')
            ->selectRaw('distinct year(valid_until) as year')
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($year) => (string) $year)
            ->all();
    }

    /** POST /licencias */
    public function store(Request $request): RedirectResponse
    {
        $license = License::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        $agendada = $this->calendar->sync($license->load('creator'), $request->user());

        return to_route('licenses.index')
            ->with('success', "Se registró {$license->name}.".$this->calendarNote($agendada));
    }

    /** PATCH /licencias/{license} */
    public function update(Request $request, License $license): RedirectResponse
    {
        $license->update($this->validated($request));

        // Mover la vigencia hacia atrás puede dejar el aviso en el pasado: ahí
        // ya no suena nadie, así que se quita y se dice, en vez de sincronizar
        // un evento con una alerta muerta.
        $vencido = $this->dropStaleReminder($license);

        // La vigencia pudo moverse (o borrarse): el evento sigue al registro.
        $agendada = $this->calendar->sync($license->load('creator', 'notification'), $request->user());

        return to_route('licenses.index')
            ->with('success', "Se actualizó {$license->name}.".$vencido.$this->calendarNote($agendada));
    }

    /** DELETE /licencias/{license} */
    public function destroy(Request $request, License $license): RedirectResponse
    {
        $name = $license->name;

        // Primero el evento: después del delete ya no hay de dónde sacar su id.
        $this->calendar->forget($license, $request->user());

        $license->delete();

        return to_route('licenses.index')->with('success', "Se eliminó {$name}.");
    }

    /**
     * Quita el aviso que quedó antes de «ahora» tras mover la vigencia: Outlook
     * no lanza alertas del pasado y dejarlo guardado solo engaña a quien lo ve
     * en la tabla. Devuelve la frase que se suma al mensaje de éxito.
     */
    private function dropStaleReminder(License $license): string
    {
        $minutes = $license->notification?->minutes_before;

        if ($minutes === null || ! $license->expiresAt()->copy()->subMinutes($minutes)->isPast()) {
            return '';
        }

        $license->notification()->delete();
        $license->unsetRelation('notification');

        return ' Se quitó el aviso: con la nueva vigencia ya había pasado.';
    }

    /**
     * Agendar es un extra: si Outlook no respondió o la cuenta no está
     * vinculada, se dice en el mismo aviso en vez de fallar el guardado.
     */
    private function calendarNote(bool $agendada): string
    {
        return $agendada
            ? ' Se agendó en el calendario.'
            : ' No se pudo agendar en el calendario: revisa la conexión con Outlook.';
    }

    /**
     * Mismos campos al crear y al editar. El estatus nunca viene del
     * formulario: se recalcula con la vigencia cada vez que se guarda.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['required', 'date'],
            // La hora solo tiene sentido con fecha: sin vigencia no hay qué agendar.
            'valid_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'comments' => ['nullable', 'string'],
        ], [
            'name.required' => 'Escribe el nombre o trámite.',
            'valid_until.required' => 'Pon la fecha de vencimiento.',
            'valid_until.date' => 'La vigencia no es una fecha válida.',
            'valid_time.date_format' => 'La hora no es válida.',
        ]);

        return [
            ...$data,
            'valid_time' => $data['valid_time'] ?? null,
            'status' => License::statusFor(Carbon::parse($data['valid_until'])),
        ];
    }
}
