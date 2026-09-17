<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\License;
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

    public function __construct(private readonly LicenseCalendar $calendar) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Un estado que no existe se ignora en vez de dejar la tabla vacía.
        $status = in_array($request->query('status'), License::STATUSES, true) ? $request->query('status') : null;

        $licenses = License::with(['notification', 'creator'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('authority', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
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
            'filters' => ['search' => $search, 'status' => $status],
            // Quién recibe la invitación y el correo: con quién está compartido
            // el calendario. Se muestra de solo lectura en el modal del aviso.
            'sharedWith' => $this->calendar->sharedWith($request->user()->calendarOwner()),
        ]);
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
            ->with('success', "Se registró {$license->name}.".$this->calendarNote($license, $agendada));
    }

    /** PATCH /licencias/{license} */
    public function update(Request $request, License $license): RedirectResponse
    {
        $license->update($this->validated($request));

        // La vigencia pudo moverse (o borrarse): el evento sigue al registro.
        $agendada = $this->calendar->sync($license->load('creator'), $request->user());

        return to_route('licenses.index')
            ->with('success', "Se actualizó {$license->name}.".$this->calendarNote($license, $agendada));
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
     * Agendar es un extra: si Outlook no respondió o la cuenta no está
     * vinculada, se dice en el mismo aviso en vez de fallar el guardado.
     */
    private function calendarNote(License $license, bool $agendada): string
    {
        if ($license->valid_until === null) {
            return '';
        }

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
            'valid_until' => ['nullable', 'date'],
            // La hora solo tiene sentido con fecha: sin vigencia no hay qué agendar.
            'valid_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'comments' => ['nullable', 'string'],
        ], [
            'name.required' => 'Escribe el nombre o trámite.',
            'valid_until.date' => 'La vigencia no es una fecha válida.',
            'valid_time.date_format' => 'La hora no es válida.',
        ]);

        return [
            ...$data,
            // Sin fecha, la hora sobra: se tira para no dejar un dato huérfano.
            'valid_time' => filled($data['valid_until'] ?? null) ? ($data['valid_time'] ?? null) : null,
            'status' => License::statusFor(isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : null),
        ];
    }
}
