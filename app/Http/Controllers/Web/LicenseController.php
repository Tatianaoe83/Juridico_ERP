<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\License;
use App\Services\LicenseCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // La empresa es una unidad de negocio: se filtra por su id.
        $company = (int) $request->query('company') ?: null;

        // La autoridad se elige de lo ya capturado, así que se compara completa;
        // un valor que nadie tiene deja la tabla vacía y eso es lo correcto.
        $authority = trim((string) $request->query('authority', '')) ?: null;

        // Año y mes van por separado: así se puede pedir todo 2027, todos los
        // noviembres, o noviembre de 2027. Un valor fuera de rango se ignora.
        $year = preg_match('/^\d{4}$/', (string) $request->query('year', '')) ? (int) $request->query('year') : null;
        $month = preg_match('/^(0?[1-9]|1[0-2])$/', (string) $request->query('month', '')) ? (int) $request->query('month') : null;

        $licenses = License::with(['creator', 'businessUnit'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('businessUnit', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhere('authority', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->inStatus($status))
            ->when($company, fn ($query) => $query->where('business_unit_id', $company))
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
        $counts = License::statusCounts();

        return Inertia::render('Licenses/Index', [
            'licenses' => [
                'data' => $licenses->getCollection()->map(fn (License $license) => [
                    'id' => $license->id,
                    'name' => $license->name,
                    'business_unit_id' => $license->business_unit_id,
                    'company' => $license->businessUnit?->name,
                    'authority' => $license->authority,
                    'valid_until' => $license->valid_until?->toDateString(),
                    'status' => $license->status(),
                    // Para el modal de detalle: evita pedir el registro otra vez.
                    'comments' => $license->comments,
                    'created_at' => $license->created_at?->toIso8601String(),
                    'created_by' => $license->creator?->name,
                ]),
                'meta' => [
                    'total' => $licenses->total(),
                    'per_page' => $licenses->perPage(),
                    'current_page' => $licenses->currentPage(),
                    'last_page' => $licenses->lastPage(),
                ],
            ],
            'stats' => [
                'total' => array_sum($counts),
                ...$counts,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company' => $company ? (string) $company : null,
                'authority' => $authority,
                'year' => $year ? (string) $year : null,
                'month' => $month ? str_pad((string) $month, 2, '0', STR_PAD_LEFT) : null,
            ],
            // Las opciones salen de lo capturado: nunca se ofrece un filtro que
            // no devuelva nada. Las empresas, solo las que ya tienen licencias.
            'options' => [
                'companies' => BusinessUnit::whereHas('licenses')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (BusinessUnit $unit) => ['value' => (string) $unit->id, 'label' => $unit->name])
                    ->all(),
                'authorities' => $this->distinct('authority'),
                'years' => $this->years(),
            ],
            // La empresa se elige del catálogo de unidades de negocio.
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
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

        $agendada = $this->calendar->sync($license->load('creator', 'businessUnit'), $request->user());

        return to_route('licenses.index')
            ->with('success', "Se registró {$license->name}.".$this->calendarNote($agendada));
    }

    /** PATCH /licencias/{license} */
    public function update(Request $request, License $license): RedirectResponse
    {
        $license->update($this->validated($request));

        // Outlook solo se toca si se movió la vigencia: actualizar el evento le
        // vuelve a llegar el aviso a los compartidos, y con el mismo día no hay
        // nada nuevo que avisar. Si aún no tiene evento, se crea.
        $moved = $license->wasChanged('valid_until') || ! $license->calendar_event_id;

        $note = $moved
            ? $this->calendarNote($this->calendar->sync($license->load('creator', 'businessUnit'), $request->user()))
            : '';

        return to_route('licenses.index')
            ->with('success', "Se actualizó {$license->name}.".$note);
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
    private function calendarNote(bool $agendada): string
    {
        return $agendada
            ? ' Se agendó en el calendario.'
            : ' No se pudo agendar en el calendario: revisa la conexión con Outlook.';
    }

    /**
     * Mismos campos al crear y al editar. El estatus no se guarda: sale de
     * la vigencia al consultar (License::status()).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        // Nombre, autoridad y comentarios se guardan siempre en mayúsculas, lleguen como lleguen.
        foreach (['name', 'authority', 'comments'] as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => mb_strtoupper($request->input($field))]);
            }
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // La empresa es una unidad de negocio del catálogo.
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'authority' => ['required', 'string', 'max:255'],
            'valid_until' => ['required', 'date'],
            'comments' => ['nullable', 'string'],
        ], [
            'name.required' => 'Escribe el nombre o trámite.',
            'business_unit_id.required' => 'Elige la unidad de negocio.',
            'business_unit_id.exists' => 'Elige una unidad de negocio del catálogo.',
            'authority.required' => 'Escribe la autoridad.',
            'valid_until.required' => 'Pon la fecha de vencimiento.',
            'valid_until.date' => 'La vigencia no es una fecha válida.',
        ]);
    }
}
