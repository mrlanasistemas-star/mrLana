<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    /**
     * LISTADO
     * Yo dejo multi-tenant simple:
     * - ADMIN/CONTADOR puede ver todo y filtrar por dueño
     * - demás solo lo propio (y por defecto ACTIVO)
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $q = trim((string) $request->get('q', ''));
        $status = strtoupper(trim((string) $request->get('status', '')));
        $ownerId = $request->integer('user_duenio_id') ?: $request->integer('ownerId');

        $sort = (string) $request->get('sort', 'created_at');
        $dir = strtolower((string) $request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->get('perPage', 10);
        $perPage = $perPage > 0 ? $perPage : 10;

        $isAdminLike = $user->can('proveedores.ver_todos');

        $query = Proveedor::query()
            ->select(['id', 'user_duenio_id', 'razon_social', 'rfc', 'clabe', 'banco', 'status', 'created_at', 'updated_at']);

        // Multi-tenant (yo lo mantengo para que no vean proveedores ajenos por URL)
        if (! $isAdminLike) {
            $query->where('user_duenio_id', $user->id);
            // si no eres admin/contador, yo fuerzo ACTIVO siempre
            $query->where('status', 'ACTIVO');
        }

        // Filtro por dueño (solo si admin/contador)
        if ($isAdminLike && ! empty($ownerId)) {
            $query->where('user_duenio_id', (int) $ownerId);
        }

        // Filtro status (solo si admin/contador)
        if ($isAdminLike && in_array($status, ['ACTIVO', 'INACTIVO'], true)) {
            $query->where('status', $status);
        }

        // Búsqueda libre
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('razon_social', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('clabe', 'like', "%{$q}%")
                    ->orWhere('banco', 'like', "%{$q}%");
            });
        }
        // Orden seguro
        $allowedSort = ['created_at', 'razon_social', 'status'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'created_at';
        }

        $rows = $query
            ->orderBy($sort, $dir)
            ->paginate($perPage)
            ->withQueryString();

        // Options para filtro "Usuario dueño"
        $owners = [];
        if ($isAdminLike) {
            $owners = User::query()
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id' => (int) $u->id,
                    'nombre' => (string) $u->name,
                    'codigo' => (string) $u->email,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Proveedores/Index', [
            'filters' => [
                'q' => $q,
                'status' => $isAdminLike ? ($status ?: '') : 'ACTIVO',
                'user_duenio_id' => $isAdminLike ? ($ownerId ?: null) : null,
                'sort' => $sort,
                'dir' => $dir,
                'perPage' => $perPage,
            ],
            'rows' => $rows,
            'owners' => $owners,
            'can' => [
                'registrar' => $user->can('proveedores.registrar'),
                'editar' => $user->can('proveedores.editar'),
                'desactivar' => $user->can('proveedores.desactivar'),
                'reactivar' => $user->can('proveedores.reactivar'),
                'exportar' => $user->can('proveedores.exportar'),
            ],
            'canDelete' => $user->can('proveedores.desactivar'),
        ]);
    }

    /**
     * CREAR
     * Yo fuerzo:
     * - user_duenio_id del usuario logueado
     * - status = ACTIVO
     * - clabe solo dígitos
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:200'],
            'rfc' => ['required', 'string', 'max:20'],
            'clabe' => ['required', 'string', 'max:30'],
            'banco' => ['required', 'string', 'max:120'],
        ]);

        $data['rfc'] = strtoupper(preg_replace('/\s+/', '', (string) $data['rfc']));
        $data['clabe'] = preg_replace('/\D/', '', (string) $data['clabe']); // solo números
        $data['user_duenio_id'] = (int) $user->id;
        $data['status'] = 'ACTIVO';

        Proveedor::create($data);

        return back()->with('success', 'Proveedor creado.');
    }

    /**
     * ACTUALIZAR
     * Nota: el parámetro del resource por default es {proveedore},
     * por eso aquí lo llamo $proveedore (si no, Laravel NO lo bindea y te inserta “vacío”).
     */
    public function update(Request $request, Proveedor $proveedore): RedirectResponse
    {
        $this->authorize('update', $proveedore);

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:200'],
            'rfc' => ['required', 'string', 'max:20'],
            'clabe' => ['required', 'string', 'max:30'],
            'banco' => ['required', 'string', 'max:120'],
        ]);

        $data['rfc'] = strtoupper(preg_replace('/\s+/', '', (string) $data['rfc']));
        $data['clabe'] = preg_replace('/\D/', '', (string) $data['clabe']); // solo números

        // Yo NO toco status aquí.
        $proveedore->update($data);

        return back()->with('success', 'Proveedor actualizado.');
    }

    /**
     * ELIMINAR (lógico)
     * En UI es “Eliminar”, pero yo solo marco INACTIVO.
     */
    public function destroy(Request $request, Proveedor $proveedore): RedirectResponse
    {
        $this->authorize('delete', $proveedore);

        if (strtoupper((string) $proveedore->status) === 'INACTIVO') {
            // Yo no hago “doble eliminación”
            return back()->with('success', 'Proveedor eliminado.');
        }

        $proveedore->status = 'INACTIVO';
        $proveedore->save();

        return back()->with('success', 'Proveedor eliminado.');
    }

    /**
     * ELIMINAR SELECCIONADOS (lógico)
     * Yo solo actualizo ACTIVO -> INACTIVO.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        Proveedor::query()
            ->whereIn('id', $data['ids'])
            ->when(! $request->user()->can('proveedores.ver_todos'), fn ($q) => $q->where('user_duenio_id', $request->user()->id))
            ->where('status', 'ACTIVO')
            ->updateEach(['status' => 'INACTIVO']);

        return back()->with('success', 'Proveedores eliminados.');
    }

    // PATCH /proveedores/{proveedor}/activate
    public function activate(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $this->authorize('restore', $proveedor);

        $proveedor->status = 'ACTIVO';
        $proveedor->save();

        return back()->with('success', 'Proveedor reactivado.');
    }
}
