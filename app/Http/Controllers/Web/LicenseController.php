<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Licencias y permisos (Cumplimiento). Por ahora solo el listado.
 */
class LicenseController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $licenses = License::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('authority', 'like', "%{$search}%")))
            // Lo que vence antes va primero; lo que no vence, al final.
            ->orderByRaw('valid_until is null')
            ->orderBy('valid_until')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Licenses/Index', [
            'licenses' => [
                'data' => $licenses->getCollection()->map(fn (License $license) => [
                    'id' => $license->id,
                    'name' => $license->name,
                    'company' => $license->company,
                    'authority' => $license->authority,
                    'valid_until' => $license->valid_until?->toDateString(),
                    'status' => $license->status,
                ]),
                'meta' => [
                    'total' => $licenses->total(),
                    'per_page' => $licenses->perPage(),
                    'current_page' => $licenses->currentPage(),
                    'last_page' => $licenses->lastPage(),
                ],
            ],
            'filters' => ['search' => $search],
        ]);
    }
}
