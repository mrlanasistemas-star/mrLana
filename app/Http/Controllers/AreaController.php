<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Corporativo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    /**
     * =========================================================================
     * INDEX
     * -------------------------------------------------------------------------
     * Listado con filtros enterprise (MR-Lana):
     * - q: búsqueda por nombre
     * - corporativo_id: filtro por corporativo (id)
     * - activo: 'all' | '1' | '0'  (acepta '' por compat)
     * - per_page: paginación (snake) (acepta perPage por compat)
     * - sort: nombre | id
     * - dir: asc | desc
     *
     * Orden "enterprise":
     * 1) corporativo_id (para agrupar en UI)
     * 2) sort elegido (nombre/id) con dir
     * 3) fallback id asc
     * =========================================================================
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        // filtros
        $corporativoId = $request->get('corporativo_id', '');
        $activo = $request->get('activo', '1'); // ✅ DEFAULT: Activas

        // per_page preferido (snake). Soporta perPage por compat.
        $perPage = (int) $request->get('per_page', $request->get('perPage', 15));

        // sort/dir
        $sort = (string) $request->get('sort', 'nombre'); // nombre | id
        $dir = (string) $request->get('dir', 'asc');     // asc | desc

        // sanitización
        $sort = in_array($sort, ['nombre', 'id'], true) ? $sort : 'nombre';
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

        // perPage hardening (evita payloads enormes)
        if ($perPage < 10) {
            $perPage = 10;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        // normaliza corporativo_id
        $corporativoId = ($corporativoId === '' || $corporativoId === null) ? null : (int) $corporativoId;

        // normaliza activo (acepta '' por compat, lo trata como default '1')
        $activo = ($activo === '' || $activo === null) ? '1' : (string) $activo;
        $activo = in_array($activo, ['all', '1', '0'], true) ? $activo : '1'; // ✅ si viene basura => Activas

        $areasQuery = Area::query()
            ->with(['corporativo:id,nombre,codigo,activo'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%");
            })
            ->when(! is_null($corporativoId), function ($query) use ($corporativoId) {
                $query->where('corporativo_id', $corporativoId);
            })
            ->when($activo !== 'all', function ($query) use ($activo) {
                $query->where('activo', (int) $activo);
            });

        /**
         * Orden enterprise:
         * - Agrupar por corporativo y mandar nulls al final (mejor UX: "Sin corporativo" al final)
         * - Luego sort elegido
         * - Fallback id asc
         */
        $areasQuery->orderByRaw('CASE WHEN corporativo_id IS NULL THEN 1 ELSE 0 END ASC');
        $areasQuery->orderBy('corporativo_id', 'asc');

        if ($sort === 'nombre') {
            $areasQuery->orderBy('nombre', $dir);
        } else {
            $areasQuery->orderBy('id', $dir);
        }

        $areasQuery->orderBy('id', 'asc');

        $paginated = $areasQuery
            ->paginate($perPage)
            ->withQueryString();

        // corporativos para filtros y modal (front decide: activos vs todos)
        $corporativos = Corporativo::query()
            ->select(['id', 'nombre', 'codigo', 'activo'])
            ->orderBy('nombre')
            ->get();

        return inertia('Areas/Index', [
            'areas' => $paginated,
            'corporativos' => $corporativos,
            'filters' => [
                'q' => $q,
                'corporativo_id' => $corporativoId,
                'activo' => $activo,       // ✅ ahora default llega como '1'
                'per_page' => $perPage,     // snake standard
                'perPage' => $perPage,      // compat
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    /**
     * =========================================================================
     * STORE
     * -------------------------------------------------------------------------
     * Regla encadenada (negocio):
     * - Si corporativo_id viene y ese corporativo está en baja (activo = 0),
     *   NO se permite crear el área.
     * =========================================================================
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'corporativo_id' => ['nullable', 'integer', 'exists:corporativos,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['required', 'boolean'],
        ]);

        // Negocio: no crear bajo corporativo en baja
        if (! empty($data['corporativo_id'])) {
            $corp = Corporativo::query()
                ->select(['id', 'activo'])
                ->find((int) $data['corporativo_id']);

            if ($corp && ! $corp->activo) {
                return back()->withErrors([
                    'corporativo_id' => 'No puedes crear un área en un corporativo dado de baja.',
                ]);
            }
        }

        Area::create($data);

        return back()->with('success', 'Área creada.');
    }

    /**
     * =========================================================================
     * UPDATE
     * -------------------------------------------------------------------------
     * Regla encadenada (negocio):
     * - Si cambias/asignas corporativo_id a uno inactivo, bloquea.
     * =========================================================================
     */
    public function update(Request $request, Area $area)
    {
        $data = $request->validate([
            'corporativo_id' => ['nullable', 'integer', 'exists:corporativos,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['required', 'boolean'],
        ]);

        // Negocio: no permitir asignar/actualizar con corporativo en baja
        if (! empty($data['corporativo_id'])) {
            $corp = Corporativo::query()
                ->select(['id', 'activo'])
                ->find((int) $data['corporativo_id']);

            if ($corp && ! $corp->activo) {
                return back()->withErrors([
                    'corporativo_id' => 'No puedes asignar un área a un corporativo dado de baja.',
                ]);
            }
        }

        $area->update($data);

        return back()->with('success', 'Área actualizada.');
    }

    /**
     * Destroy: baja lógica (activo=false).
     * Regla: si ya está inactiva, no hace nada.
     */
    public function destroy(Area $area)
    {
        if (! $area->activo) {
            return back()->with('success', 'El área ya se encontraba dada de baja.');
        }

        $area->update(['activo' => false]);

        return back()->with('success', 'Área dada de baja correctamente.');
    }

    /**
     * =========================================================================
     * ACTIVATE (PATCH)
     * -------------------------------------------------------------------------
     * Regla encadenada:
     * - Si el área pertenece a un corporativo en baja => NO se activa.
     * - Si corporativo_id es null => se permite activar (si tu negocio lo permite).
     * =========================================================================
     */
    public function activate(Request $request, Area $area)
    {
        // Negocio: no permitir activar área bajo corporativo en baja
        $corp = Corporativo::query()
            ->select(['id', 'activo'])
            ->find((int) $area->corporativo_id);

        $area->update(['activo' => true]);

        return back()->with('success', 'Área activada.');
    }

    // Metodo adicional para eliminación masiva
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('areas', 'id')],
        ]);

        // Baja lógica (antes se borraban físicamente): conserva historial y relaciones.
        Area::query()
            ->whereIn('id', $data['ids'])
            ->where('activo', true)
            ->updateEach(['activo' => false]);

        return back()->with('success', 'Áreas dadas de baja.');
    }
}
