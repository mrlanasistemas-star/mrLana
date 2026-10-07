<?php

namespace App\Http\Controllers;

use App\Http\Requests\Concepto\StoreConceptoRequest;
use App\Http\Requests\Concepto\UpdateConceptoRequest;
use App\Models\Concepto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConceptoController extends Controller {

    // Lista paginada con filtros y ordenamiento.
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        // DEFAULT: Activos
        $activo = $request->get('activo', '1');

        // per_page preferido (snake). Soporta perPage por compat.
        $perPage = (int) $request->get('per_page', $request->get('perPage', 15));
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $sort = (string) $request->get('sort', 'id');
        $dir  = (string) $request->get('dir', 'desc');

        $sort = in_array($sort, ['id', 'nombre'], true) ? $sort : 'id';
        $dir  = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        // normaliza activo (acepta '' por compat)
        $activo = ($activo === '' || $activo === null) ? '1' : (string) $activo;
        $activo = in_array($activo, ['all', '1', '0'], true) ? $activo : '1';

        $q = Concepto::query();

        if ($search !== '') {
            $q->where('nombre', 'like', "%{$search}%");
        }

        if ($activo !== 'all') {
            $q->where('activo', (int) $activo);
        }

        // Orden consistente (y estable)
        $conceptos = $q->orderBy($sort, $dir)
            ->orderBy('id', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Conceptos/Index', [
            'conceptos' => $conceptos,
            'filters' => [
                'q' => $search,
                'activo' => $activo,      // default llega '1'
                'per_page' => $perPage,
                'perPage' => $perPage,    // compat
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function store(StoreConceptoRequest $request)
    {
        DB::transaction(function () use ($request) {
            Concepto::create([
                'nombre' => $request->validated('nombre'),
                'activo' => (bool) ($request->validated('activo') ?? true),
            ]);
        });

        return back()->with('success', 'Concepto creado.');
    }

    public function update(UpdateConceptoRequest $request, Concepto $concepto)
    {
        DB::transaction(function () use ($request, $concepto) {
            $concepto->update([
                'nombre' => $request->validated('nombre'),
                'activo' => (bool) ($request->validated('activo') ?? $concepto->activo),
            ]);
        });

        return back()->with('success', 'Concepto actualizado.');
    }

    /**
     * Baja lógica (activo=false). Si ya está inactivo, no repite la baja.
     * (Si tu UI quiere "Activar" en vez de "Eliminar", esto lo soporta perfecto)
     */
    public function destroy(Concepto $concepto)
    {
        if (!$concepto->activo) {
            return back()->with('success', 'El concepto ya se encontraba dado de baja.');
        }

        $concepto->update(['activo' => false]);

        return back()->with('success', 'Concepto dado de baja.');
    }

    /**
     * Activar (PATCH)
     */
    public function activate(Concepto $concepto)
    {
        if ($concepto->activo) {
            return back()->with('success', 'El concepto ya está activo.');
        }

        DB::transaction(function () use ($concepto) {
            $concepto->update(['activo' => true]);
        });

        return back()->with('success', 'Concepto activado.');
    }

    /**
     * Baja lógica masiva (NO delete físico)
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:conceptos,id'],
        ]);

        DB::transaction(function () use ($data) {
            Concepto::query()
                ->whereIn('id', $data['ids'])
                ->where('activo', true)
                ->updateEach(['activo' => false]);
        });

        return back()->with('success', 'Conceptos dados de baja.');
    }

}
