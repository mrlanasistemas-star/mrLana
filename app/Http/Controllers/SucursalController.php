<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sucursal\StoreSucursalRequest;
use App\Http\Requests\Sucursal\UpdateSucursalRequest;
use App\Http\Resources\SucursalResource;
use App\Models\Corporativo;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SucursalController extends Controller
{
    /**
     * Index: lista sucursales con filtros + paginación.
     * Reglas:
     * - Por defecto muestra SOLO activas (activo=1).
     * - El combo de corporativos SOLO trae corporativos activos.
     */
    public function index(Request $request)
    {

        // Obtener filtros desde query params
        $filters = [
            'q' => (string) $request->query('q', ''),
            'corporativo_id' => $request->query('corporativo_id', ''),
            'activo' => (string) $request->query('activo', '1'), // default: activos
            'perPage' => (int) $request->query('perPage', 15),

            'sort' => (string) $request->query('sort', 'nombre'),
            'dir' => (string) $request->query('dir', 'asc'),
        ];

        // Validar perPage
        $filters['perPage'] = ($filters['perPage'] > 0 && $filters['perPage'] <= 100) ? $filters['perPage'] : 15;

        // Validar sort y dir
        $dir = strtolower($filters['dir']) === 'desc' ? 'desc' : 'asc';
        $sort = in_array($filters['sort'], ['nombre', 'id'], true) ? $filters['sort'] : 'nombre';

        // Construir query con filtros
        $user = $request->user();

        // Solo sucursales dentro del alcance (mi sucursal, mi corporativo o todas)
        $query = Sucursal::query()
            ->visibleTo($user)
            ->with(['corporativo:id,nombre,codigo,activo'])
            ->when($filters['q'], function ($q) use ($filters) {
                $v = trim($filters['q']);
                $q->where(function ($qq) use ($v) {
                    $qq->where('nombre', 'like', "%{$v}%")
                        ->orWhere('codigo', 'like', "%{$v}%")
                        ->orWhere('ciudad', 'like', "%{$v}%")
                        ->orWhere('estado', 'like', "%{$v}%")
                        ->orWhere('direccion', 'like', "%{$v}%");
                });
            })
            ->when($filters['corporativo_id'] !== '' && $filters['corporativo_id'] !== null, function ($q) use ($filters) {
                $q->where('corporativo_id', (int) $filters['corporativo_id']);
            })
            ->when($filters['activo'] === '1' || $filters['activo'] === '0', function ($q) use ($filters) {
                $q->where('activo', $filters['activo'] === '1');
            })
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc');

        $paginator = $query->paginate($filters['perPage'])->withQueryString();

        // Retornar datos a la vista Inertia
        return Inertia::render('Sucursales/Index', [
            'sucursales' => [
                'data' => SucursalResource::collection($paginator)->resolve(),
                'links' => $paginator->linkCollection(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters,

            // SOLO corporativos activos y dentro del alcance para el filtro/selector
            'corporativos' => $this->corporativosPermitidos($user)
                ->select('id', 'nombre', 'codigo')
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    /**
     * Metodo para registrar sucursal.
     */
    public function store(StoreSucursalRequest $request)
    {
        $data = $request->validated();
        $this->assertCorporativoPermitido($request, (int) $data['corporativo_id']);

        // Por regla, toda sucursal nueva se crea como activa.
        $data['activo'] = true;

        Sucursal::create($data);

        return back()->with('success', 'Sucursal registrada correctamente.');
    }

    /**
     * Update: SOLO actualiza campos editables.
     * Regla: NO se permite cambiar "activo" desde aquí.
     */
    public function update(UpdateSucursalRequest $request, Sucursal $sucursal)
    {
        $this->authorize('update', $sucursal);
        $data = $request->validated();
        if (isset($data['corporativo_id']) && (int) $data['corporativo_id'] !== (int) $sucursal->corporativo_id) {
            $this->assertCorporativoPermitido($request, (int) $data['corporativo_id']);
        }

        // Blindaje: el estado NO se toca aquí, aunque venga en el payload.
        unset($data['activo']);

        $sucursal->update($data);

        return back()->with('success', 'Sucursal actualizada correctamente.');
    }

    /**
     * Destroy: baja lógica (activo=false).
     * Regla: si ya está inactiva, no hace nada.
     */
    public function destroy(Sucursal $sucursal)
    {
        $this->authorize('delete', $sucursal);
        if (! $sucursal->activo) {
            return back()->with('success', 'La sucursal ya se encontraba dada de baja.');
        }

        $sucursal->update(['activo' => false]);

        return back()->with('success', 'Sucursal eliminada correctamente.');
    }

    /**
     * Activate: reactivación dedicada (botón Activar).
     * Regla: si ya está activa, no hace nada.
     */
    public function activate(Sucursal $sucursal)
    {
        $this->authorize('restore', $sucursal);
        // Si corporativo está en baja -> no permitir
        $corp = $sucursal->corporativo()->select('id', 'activo')->first();
        if (! $corp || ! $corp->activo) {
            return back()->with('error', 'No puedes activar esta sucursal porque su corporativo está dado de baja.');
        }

        $sucursal->update(['activo' => true]);

        return back()->with('success', 'Sucursal activada correctamente.');
    }

    /** Corporativos en los que puede registrar o mover sucursales: todos (global) o el propio. */
    private function corporativosPermitidos(\App\Models\User $user)
    {
        $q = Corporativo::query();

        return \App\Support\Permissions\AccessScope::for($user, 'sucursales') === \App\Support\Permissions\Scope::Global
            ? $q
            : $q->whereKey(\App\Support\Permissions\AccessScope::corporativoId($user) ?? 0);
    }

    private function assertCorporativoPermitido(Request $request, int $corporativoId): void
    {
        if (! $this->corporativosPermitidos($request->user())->whereKey($corporativoId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'corporativo_id' => 'Solo puedes registrar sucursales en tu corporativo.',
            ]);
        }
    }

    /**
     * bulkDestroy: baja lógica masiva (NO delete físico).
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        Sucursal::query()
            ->visibleTo($request->user())
            ->whereIn('id', $data['ids'])
            ->where('activo', true)
            ->updateEach(['activo' => false]);

        return back()->with('success', 'Sucursales dadas de baja correctamente.');
    }
}
