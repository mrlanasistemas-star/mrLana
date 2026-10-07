<?php

namespace App\Http\Controllers;

use App\Http\Requests\Colaborador\StoreColaboradorRequest;
use App\Http\Requests\Colaborador\UpdateColaboradorRequest;
use App\Models\Area;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Services\Colaboradores\ColaboradorQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Colaboradores: personas de la organización (tabla interna `empleados`).
 * Un colaborador puede existir sin cuenta de acceso; las cuentas se
 * administran en el módulo Usuarios (relación uno a uno).
 */
class ColaboradorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $f = ColaboradorQuery::filters($request);

        $perPage = (int) $request->input('per_page', $request->input('perPage', 20));
        $perPage = in_array($perPage, [10, 15, 20, 50, 100], true) ? $perPage : 20;
        $sort = $request->input('sort') === 'id' ? 'id' : 'nombre';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $query = ColaboradorQuery::build($f)->with([
            'sucursal:id,corporativo_id,nombre,codigo,activo',
            'sucursal.corporativo:id,nombre,codigo,activo',
            'area:id,corporativo_id,nombre,activo',
            'user:id,empleado_id,name,email,activo',
            'user.roles:id,name',
        ]);

        if ($sort === 'id') {
            $query->orderBy('id', $dir);
        } else {
            $query->orderBy('apellido_paterno', $dir)->orderBy('apellido_materno', $dir)->orderBy('nombre', $dir);
        }

        $colaboradores = $query->orderBy('id')->paginate($perPage)->withQueryString();

        $colaboradores->getCollection()->transform(fn (Empleado $e) => [
            'id' => $e->id,
            'sucursal_id' => $e->sucursal_id,
            'area_id' => $e->area_id,
            'nombre' => $e->nombre,
            'apellido_paterno' => $e->apellido_paterno,
            'apellido_materno' => $e->apellido_materno,
            'nombre_completo' => trim("{$e->nombre} {$e->apellido_paterno} ".($e->apellido_materno ?? '')),
            'email' => $e->email,
            'telefono' => $e->telefono,
            'puesto' => $e->puesto,
            'activo' => (bool) $e->activo,
            'sucursal' => $e->sucursal ? [
                'id' => $e->sucursal->id,
                'nombre' => $e->sucursal->nombre,
                'codigo' => $e->sucursal->codigo,
                'corporativo_id' => $e->sucursal->corporativo_id,
                'corporativo' => $e->sucursal->corporativo ? [
                    'id' => $e->sucursal->corporativo->id,
                    'nombre' => $e->sucursal->corporativo->nombre,
                ] : null,
            ] : null,
            'area' => $e->area ? ['id' => $e->area->id, 'nombre' => $e->area->nombre] : null,
            'user' => $e->user ? [
                'id' => $e->user->id,
                'name' => $e->user->name,
                'email' => $e->user->email,
                'activo' => (bool) $e->user->activo,
                'roles' => $e->user->roles->pluck('name')->values(),
            ] : null,
        ]);

        return Inertia::render('Colaboradores/Index', [
            'colaboradores' => $colaboradores,
            'counts' => ColaboradorQuery::counts($f),
            'corporativos' => Corporativo::query()->select(['id', 'nombre', 'codigo', 'activo'])->orderBy('nombre')->get(),
            'sucursales' => Sucursal::query()->select(['id', 'corporativo_id', 'nombre', 'codigo', 'activo'])->orderBy('nombre')->get(),
            'areas' => Area::query()->select(['id', 'corporativo_id', 'nombre', 'activo'])->orderBy('nombre')->get(),
            'filters' => $f + ['per_page' => $perPage, 'sort' => $sort, 'dir' => $dir],
            'can' => [
                'registrar' => $user->can('colaboradores.registrar'),
                'editar' => $user->can('colaboradores.editar'),
                'desactivar' => $user->can('colaboradores.desactivar'),
                'reactivar' => $user->can('colaboradores.reactivar'),
                'exportar' => $user->can('colaboradores.exportar'),
                'crear_usuario' => $user->can('usuarios.registrar'),
                'ver_usuario' => $user->can('usuarios.ver'),
            ],
        ]);
    }

    public function store(StoreColaboradorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertOrgActivaOrFail((int) $data['sucursal_id'], $data['area_id'] ?? null);

        Empleado::create([
            ...$data,
            'activo' => $data['activo'] ?? true,
        ]);

        return back()->with('success', 'Colaborador registrado.');
    }

    public function update(UpdateColaboradorRequest $request, Empleado $empleado): RedirectResponse
    {
        $data = $request->validated();
        $this->assertOrgActivaOrFail((int) $data['sucursal_id'], $data['area_id'] ?? null);

        $empleado->update([
            ...$data,
            'activo' => $data['activo'] ?? $empleado->activo,
        ]);

        return back()->with('success', 'Colaborador actualizado.');
    }

    /**
     * Baja lógica. La cuenta de acceso vinculada (si existe) no se modifica:
     * se administra desde Usuarios para respetar la protección del último administrador.
     */
    public function destroy(Empleado $empleado): RedirectResponse
    {
        if (! $empleado->activo) {
            return back()->with('success', 'El colaborador ya estaba dado de baja.');
        }

        $empleado->update(['activo' => false]);

        return back()->with('success', $empleado->user()->where('activo', true)->exists()
            ? 'Colaborador dado de baja. Su cuenta de acceso sigue activa; desactívala en Usuarios si corresponde.'
            : 'Colaborador dado de baja.');
    }

    public function activate(Empleado $empleado): RedirectResponse
    {
        $this->assertOrgActivaOrFail((int) $empleado->sucursal_id, $empleado->area_id ? (int) $empleado->area_id : null);

        $empleado->update(['activo' => true]);

        return back()->with('success', 'Colaborador reactivado.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('empleados', 'id')],
        ], [
            'ids.required' => 'Selecciona al menos un colaborador.',
            'ids.array' => 'Selección inválida.',
            'ids.min' => 'Selecciona al menos un colaborador.',
            'ids.*.exists' => 'Uno o más colaboradores no existen.',
        ]);

        DB::transaction(fn () => Empleado::query()->whereIn('id', $data['ids'])->where('activo', true)->updateEach(['activo' => false]));

        return back()->with('success', 'Colaboradores dados de baja.');
    }

    private function assertOrgActivaOrFail(int $sucursalId, int|string|null $areaId = null): void
    {
        $areaId = $areaId === null || $areaId === '' ? null : (int) $areaId;

        $sucursal = Sucursal::query()
            ->select(['id', 'corporativo_id', 'activo'])
            ->with(['corporativo:id,activo'])
            ->find($sucursalId);

        if (! $sucursal) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal seleccionada no existe.']);
        }

        if (! $sucursal->activo) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal está dada de baja. Reactívala para poder continuar.']);
        }

        if ($sucursal->corporativo && ! $sucursal->corporativo->activo) {
            throw ValidationException::withMessages(['corporativo_id' => 'El corporativo está dado de baja. Reactívalo para poder continuar.']);
        }

        if ($areaId !== null) {
            $area = Area::query()->select(['id', 'corporativo_id', 'activo'])->find($areaId);

            if (! $area) {
                throw ValidationException::withMessages(['area_id' => 'El área seleccionada no existe.']);
            }

            if (! $area->activo) {
                throw ValidationException::withMessages(['area_id' => 'El área está dada de baja. Reactívala para poder continuar.']);
            }

            if ((int) $area->corporativo_id !== (int) $sucursal->corporativo_id) {
                throw ValidationException::withMessages(['area_id' => 'El área no pertenece al corporativo de la sucursal seleccionada.']);
            }
        }
    }
}
