<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\BondingCompany;
use App\Models\BusinessUnit;
use App\Models\Insurer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogos que comparten los módulos: unidades de negocio, aseguradoras,
 * afianzadoras, beneficiarios y obras. Son un nombre (las obras, además, su
 * dirección), así que una sola pantalla y un solo controlador los atienden.
 */
class CatalogController extends Controller
{
    /**
     * slug de la URL => [modelo, relaciones donde se usa, cómo se nombra uno,
     * si va en mayúsculas, campos además del nombre]. Las unidades de negocio
     * se escriben como nombre propio; las demás, en mayúsculas como en el
     * resto de los formularios.
     */
    public const CATALOGS = [
        'unidades-de-negocio' => [BusinessUnit::class, ['units', 'licenses', 'bonds', 'unitPolicies', 'constructionPolicies'], 'la unidad de negocio', false],
        'aseguradoras' => [Insurer::class, ['unitPolicies', 'constructionPolicies'], 'la aseguradora', true],
        'afianzadoras' => [BondingCompany::class, ['bonds'], 'la afianzadora', true],
        'beneficiarios' => [Beneficiary::class, ['bonds', 'unitPolicies'], 'el beneficiario', true],
        'obras' => [Project::class, ['constructionPolicies'], 'la obra', true, ['address']],
    ];

    /** GET /catalogos/{catalog} */
    public function index(string $catalog): Response
    {
        [$model, $uses] = self::CATALOGS[$catalog];

        return Inertia::render('Catalogs/Index', [
            'catalog' => $catalog,
            'items' => $model::withCount($uses)->orderBy('name')->get()->map(fn (Model $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'address' => $item->address,
                // En cuántos registros aparece: con alguno, no se puede borrar.
                'uses' => collect($uses)->sum(fn (string $relation) => $item->{Str::snake($relation).'_count'}),
                // Solo las aseguradoras tienen preferida.
                'preferred' => (bool) $item->preferred,
            ]),
        ]);
    }

    /** POST /catalogos/{catalog} */
    public function store(Request $request, string $catalog): RedirectResponse
    {
        [$model, , $label] = self::CATALOGS[$catalog];

        $item = $model::create($this->validated($request, $catalog));

        return to_route('catalogs.index', $catalog)->with('success', "Se agregó {$label} {$item->name}.");
    }

    /** PATCH /catalogos/{catalog}/{id} */
    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        [$model, , $label] = self::CATALOGS[$catalog];

        $item = $model::findOrFail($id);
        $item->update($this->validated($request, $catalog, $item));

        return to_route('catalogs.index', $catalog)->with('success', "Se actualizó {$label} {$item->name}.");
    }

    /**
     * DELETE /catalogos/{catalog}/{id}
     *
     * Lo que ya se usa no se borra: las pólizas y fianzas se quedarían sin el dato.
     */
    public function destroy(string $catalog, int $id): RedirectResponse
    {
        [$model, $uses, $label] = self::CATALOGS[$catalog];

        $item = $model::withCount($uses)->findOrFail($id);
        $count = collect($uses)->sum(fn (string $relation) => $item->{Str::snake($relation).'_count'});

        if ($count > 0) {
            return to_route('catalogs.index', $catalog)
                ->with('error', "No se puede eliminar {$label} {$item->name}: está en {$count} ".($count === 1 ? 'registro' : 'registros').'. Cámbialos primero.');
        }

        $item->delete();

        return to_route('catalogs.index', $catalog)->with('success', "Se eliminó {$label} {$item->name}.");
    }

    /**
     * PATCH /catalogos/aseguradoras/{insurer}/preferida
     *
     * La que ganó la subasta: sale por defecto en los automóviles
     * empresariales. Solo hay una; marcar otra le quita la marca a la
     * anterior y marcar la misma se la quita.
     */
    public function prefer(Insurer $insurer): RedirectResponse
    {
        $preferred = ! $insurer->preferred;

        DB::transaction(function () use ($insurer, $preferred) {
            Insurer::where('preferred', true)->update(['preferred' => false]);
            $insurer->update(['preferred' => $preferred]);
        });

        return to_route('catalogs.index', 'aseguradoras')->with('success', $preferred
            ? "{$insurer->name} sale ahora por defecto en los automóviles empresariales."
            : "{$insurer->name} ya no sale por defecto.");
    }

    /**
     * Las opciones de los formularios que usan los catálogos: [{ id, name }];
     * las aseguradoras traen además cuál es la preferida.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'insurers' => Insurer::orderBy('name')->get(['id', 'name', 'preferred']),
            'bondingCompanies' => BondingCompany::orderBy('name')->get(['id', 'name']),
            'beneficiaries' => Beneficiary::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'address']),
        ];
    }

    /**
     * El nombre, ya limpio y sin repetirse en su catálogo, y los campos
     * opcionales que lleve (la dirección de la obra).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $catalog, ?Model $item = null): array
    {
        [$model, , , $uppercase] = self::CATALOGS[$catalog];
        $extra = self::CATALOGS[$catalog][4] ?? [];

        $request->merge(collect(['name', ...$extra])
            ->mapWithKeys(function (string $field) use ($request, $uppercase) {
                $value = trim((string) $request->input($field));

                return [$field => $value === '' ? null : ($uppercase ? mb_strtoupper($value) : $value)];
            })
            ->all());

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique((new $model)->getTable(), 'name')->ignore($item)],
            ...collect($extra)->mapWithKeys(fn (string $field) => [$field => ['nullable', 'string', 'max:255']])->all(),
        ], [
            'name.required' => 'Escribe el nombre.',
            'name.unique' => 'Ya existe uno con ese nombre.',
        ]);
    }
}
