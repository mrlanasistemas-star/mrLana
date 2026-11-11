<?php

namespace App\Http\Controllers;

use App\Http\Requests\Colaborador\StoreColaboradorRequest;
use App\Http\Requests\Colaborador\UpdateColaboradorRequest;
use App\Models\Area;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Colaboradores\ColaboradorQuery;
use App\Services\Users\AdministratorGuard;
use App\Services\Users\CuentasDeAcceso;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\Scope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Colaboradores: personas de la organización (tabla interna `empleados`) y
 * su acceso al sistema, en un solo módulo. Al registrar o editar a una
 * persona se le puede dar (o administrar) su cuenta en la misma pantalla;
 * un colaborador tiene como máximo una cuenta. La pestaña «Cuentas de
 * acceso» (UsuarioController) cubre las cuentas sin colaborador.
 */
class ColaboradorController extends Controller
{
    private const CAMPOS_PERSONA = ['sucursal_id', 'area_id', 'nombre', 'apellido_paterno', 'apellido_materno', 'email', 'telefono', 'puesto'];

    /** Nombres de campo del bloque de acceso para los mensajes de error. */
    private const CAMPOS_ACCESO = ['role_id' => 'acceso_role_id', 'activo' => 'acceso_activo', 'sucursal_id' => 'sucursal_id'];

    public function __construct(
        private AdministratorGuard $guard,
        private CuentasDeAcceso $cuentas,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        // Quien solo administra cuentas entra directo a «Cuentas de acceso».
        if (! AccessScope::for($user, 'colaboradores')->allows()) {
            return redirect()->route('usuarios.index');
        }
        $f = ColaboradorQuery::filters($request);

        $perPage = (int) $request->input('per_page', $request->input('perPage', 20));
        $perPage = in_array($perPage, [10, 15, 20, 50, 100], true) ? $perPage : 20;
        $sort = $request->input('sort') === 'id' ? 'id' : 'nombre';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';

        $vinculo = $user->can('usuarios.ver_vinculo');
        $query = ColaboradorQuery::build($f, $user)->with([
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
            'user' => $vinculo && $e->user ? [
                'id' => $e->user->id,
                'name' => $e->user->name,
                'email' => $e->user->email,
                'activo' => (bool) $e->user->activo,
                'roles' => $e->user->roles->pluck('name')->values(),
            ] : null,
        ]);

        return Inertia::render('Colaboradores/Index', [
            'colaboradores' => $colaboradores,
            'counts' => ColaboradorQuery::counts($f, $user),
            // Opciones de filtro y de alta limitadas a las sucursales del alcance.
            'corporativos' => Corporativo::query()->whereIn('id', $this->sucursalesPermitidas($user)->select('corporativo_id'))->select(['id', 'nombre', 'codigo', 'activo'])->orderBy('nombre')->get(),
            'sucursales' => $this->sucursalesPermitidas($user)->select(['id', 'corporativo_id', 'nombre', 'codigo', 'activo'])->orderBy('nombre')->get(),
            'areas' => Area::query()->whereIn('corporativo_id', $this->sucursalesPermitidas($user)->select('corporativo_id'))->select(['id', 'corporativo_id', 'nombre', 'activo'])->orderBy('nombre')->get(),
            'filters' => $f + ['per_page' => $perPage, 'sort' => $sort, 'dir' => $dir],
            'can' => [
                'registrar' => $user->can('colaboradores.registrar'),
                'editar' => $user->can('colaboradores.editar'),
                'desactivar' => $user->can('colaboradores.desactivar'),
                'reactivar' => $user->can('colaboradores.reactivar'),
                'exportar' => $user->can('colaboradores.exportar'),
                'crear_usuario' => $user->can('usuarios.registrar'),
                'ver_usuario' => $vinculo && AccessScope::for($user, 'usuarios')->allows(),
                'ver_vinculo' => $vinculo,
                'administrar_cuentas' => $vinculo && $user->canAny(['usuarios.editar', 'usuarios.cambiar_rol', 'usuarios.desactivar', 'usuarios.reactivar', 'usuarios.restablecer_contrasena']),
            ],
        ]);
    }

    /** Alta unificada: datos de la persona y, opcionalmente, su acceso al sistema. */
    public function create(Request $request): Response
    {
        return Inertia::render('Colaboradores/Form', $this->formProps($request, null));
    }

    /** Edición unificada: datos de la persona y su cuenta de acceso. */
    public function edit(Request $request, Empleado $empleado): Response
    {
        $this->authorize('view', $empleado);

        return Inertia::render('Colaboradores/Form', $this->formProps($request, $empleado));
    }

    /**
     * Registra a la persona y, si se pidió, su cuenta en la misma transacción.
     * El correo con la contraseña temporal se envía después: si falla, ambos
     * registros quedan creados y se puede reenviar el acceso.
     */
    public function store(StoreColaboradorRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $acceso = (bool) ($data['acceso'] ?? false);
        $puedePersona = $actor->can('colaboradores.registrar');
        abort_unless($puedePersona || $acceso, 403, 'Tu rol solo puede registrar personas junto con su acceso al sistema.');

        $sucursalId = (int) $data['sucursal_id'];
        if ($puedePersona) {
            $this->assertSucursalPermitida($request, $sucursalId);
        }
        $this->assertOrgActivaOrFail($sucursalId, $data['area_id'] ?? null);
        $role = $acceso ? $this->cuentas->prepararAlta($actor, (int) $data['acceso_role_id'], $sucursalId, self::CAMPOS_ACCESO) : null;

        $persona = Arr::only($data, self::CAMPOS_PERSONA);
        if ($acceso && empty($persona['email'])) {
            $persona['email'] = $data['acceso_email'];
        }
        $plainPassword = $role ? $this->cuentas->temporaryPassword() : null;

        $user = DB::transaction(function () use ($persona, $data, $role, $plainPassword) {
            $empleado = Empleado::create([...$persona, 'activo' => $data['activo'] ?? true]);

            return $role ? $this->cuentas->crear([
                'name' => CuentasDeAcceso::nombreDe($empleado),
                'email' => $data['acceso_email'],
                'empleado_id' => $empleado->id,
                'activo' => $data['acceso_activo'] ?? true,
            ], $role, $plainPassword) : null;
        });

        if (! $user) {
            return redirect()->route('colaboradores.index')->with('success', 'Colaborador registrado.');
        }

        return $this->accesoCreado($actor, $user, $role, $plainPassword, 'Colaborador registrado');
    }

    /**
     * Guarda los datos de la persona (con «Editar colaboradores») y, si se
     * envía el bloque de acceso, crea su cuenta o actualiza correo, rol y
     * estado con los mismos permisos que el módulo Usuarios.
     */
    public function update(UpdateColaboradorRequest $request, Empleado $empleado): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('view', $empleado), 403);
        $data = $request->validated();
        $puedePersona = $actor->can('update', $empleado);
        $acceso = (bool) ($data['acceso'] ?? false);
        abort_unless($puedePersona || $acceso, 403, 'No tienes permiso para editar colaboradores.');

        if ($puedePersona) {
            if ((int) $data['sucursal_id'] !== (int) $empleado->sucursal_id) {
                $this->assertSucursalPermitida($request, (int) $data['sucursal_id']);
            }
            $this->assertOrgActivaOrFail((int) $data['sucursal_id'], $data['area_id'] ?? null);
        }
        $sucursalId = $puedePersona ? (int) $data['sucursal_id'] : (int) $empleado->sucursal_id;

        $cuenta = $empleado->user;
        $role = null;
        $roleChanges = false;
        $activoAntes = $cuenta?->activo;
        if ($acceso && $cuenta) {
            abort_unless($actor->can('usuarios.ver_vinculo') && $actor->can('view', $cuenta), 403, 'No puedes administrar la cuenta de esta persona.');
            $role = Role::with('permissions:id,name')->findOrFail($data['acceso_role_id']);
            $roleChanges = $this->cuentas->validarCambios($actor, $cuenta, $role, (bool) ($data['acceso_activo'] ?? $cuenta->activo), $data['acceso_email'], self::CAMPOS_ACCESO);
        } elseif ($acceso) {
            $role = $this->cuentas->prepararAlta($actor, (int) $data['acceso_role_id'], $sucursalId, self::CAMPOS_ACCESO);
        }
        $nueva = $acceso && ! $cuenta;
        $plainPassword = $nueva ? $this->cuentas->temporaryPassword() : null;

        $user = DB::transaction(function () use ($empleado, $data, $puedePersona, $acceso, $cuenta, $role, $roleChanges, $nueva, $plainPassword, $actor) {
            $nombreAnterior = CuentasDeAcceso::nombreDe($empleado);
            if ($puedePersona) {
                $empleado->update([...Arr::only($data, self::CAMPOS_PERSONA), 'activo' => $data['activo'] ?? $empleado->activo]);
            }

            if ($nueva) {
                if (! $empleado->email) {
                    $empleado->update(['email' => $data['acceso_email']]);
                }

                return $this->cuentas->crear([
                    'name' => CuentasDeAcceso::nombreDe($empleado),
                    'email' => $data['acceso_email'],
                    'empleado_id' => $empleado->id,
                    'activo' => $data['acceso_activo'] ?? true,
                ], $role, $plainPassword);
            }

            if ($acceso && $cuenta) {
                $cuenta->fill(['email' => $data['acceso_email'], 'activo' => (bool) ($data['acceso_activo'] ?? $cuenta->activo)]);
                // El nombre de la cuenta sigue al de la persona, salvo que se haya personalizado.
                if ($cuenta->name === $nombreAnterior && $actor->can('update', $cuenta)) {
                    $cuenta->name = CuentasDeAcceso::nombreDe($empleado);
                }
                $roleChanges ? $this->cuentas->asignarRol($cuenta, $role) : $cuenta->save();
            }

            return $cuenta;
        });

        if ($nueva) {
            return $this->accesoCreado($actor, $user, $role, $plainPassword, 'Colaborador actualizado');
        }

        if ($acceso && $user) {
            if ($roleChanges) {
                $this->cuentas->notificar($actor, "El rol de {$user->name} cambió a «{$role->name}».", [$user], $user);
            }
            if ($activoAntes !== (bool) $user->activo) {
                $this->cuentas->notificar($actor, $user->activo ? "Se reactivó la cuenta de {$user->name}." : "Se desactivó la cuenta de {$user->name}.", [], $user);
            }
        }

        return redirect()->route('colaboradores.index')->with('success', 'Colaborador actualizado.');
    }

    /** Aviso de seguridad, correo con la contraseña temporal y mensaje final. */
    private function accesoCreado(User $actor, User $user, Role $role, string $plainPassword, string $prefijo): RedirectResponse
    {
        $this->cuentas->notificar($actor, "Se creó la cuenta de {$user->name} con el rol «{$role->name}».", [], $user);
        $mailed = $this->cuentas->enviarAcceso($user, $plainPassword);

        return redirect()->route('colaboradores.index')->with(
            $mailed ? 'success' : 'warning',
            $mailed
                ? "{$prefijo}. Se enviaron los datos de acceso a {$user->email}."
                : "{$prefijo} y su cuenta quedó creada, pero no se pudo enviar el correo con el acceso. Usa «Reenviar acceso» cuando el correo funcione."
        );
    }

    private function formProps(Request $request, ?Empleado $empleado): array
    {
        $actor = $request->user();
        $empleado?->load(['sucursal:id,corporativo_id,nombre,codigo,activo', 'user.roles:id,name', 'user.empleado.sucursal']);
        $cuenta = $empleado?->user;
        $verCuenta = $cuenta && $actor->can('usuarios.ver_vinculo') && $actor->can('view', $cuenta);
        $registrarPersona = $actor->can('colaboradores.registrar');
        $editarPersona = $empleado ? $actor->can('update', $empleado) : $registrarPersona;
        $crearCuenta = ! $cuenta && $actor->can('usuarios.registrar');

        // Sucursales para la persona (alcance de Colaboradores) y, marcadas,
        // las que además permiten darle acceso (alcance de Usuarios).
        $conAcceso = $actor->can('usuarios.registrar') ? $this->cuentas->sucursalesPermitidas($actor)->pluck('id')->all() : [];
        $base = $registrarPersona || $editarPersona
            ? $this->sucursalesPermitidas($actor)->where('activo', true)->whereHas('corporativo', fn ($c) => $c->where('activo', true))
            : Sucursal::query()->whereKey($conAcceso);
        $sucursales = $base->orWhere('id', $empleado?->sucursal_id ?? 0)
            ->orderBy('nombre')->get(['id', 'corporativo_id', 'nombre', 'codigo', 'activo'])
            ->map(fn (Sucursal $s) => $s->only(['id', 'corporativo_id', 'nombre', 'codigo', 'activo']) + ['permite_acceso' => in_array($s->id, $conAcceso, true)]);
        $corporativoIds = $sucursales->pluck('corporativo_id')->unique()->values();

        return [
            'colaborador' => $empleado ? [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'apellido_paterno' => $empleado->apellido_paterno,
                'apellido_materno' => $empleado->apellido_materno,
                'nombre_completo' => trim("{$empleado->nombre} {$empleado->apellido_paterno} ".($empleado->apellido_materno ?? '')),
                'email' => $empleado->email,
                'telefono' => $empleado->telefono,
                'puesto' => $empleado->puesto,
                'activo' => (bool) $empleado->activo,
                'sucursal_id' => $empleado->sucursal_id,
                'corporativo_id' => $empleado->sucursal?->corporativo_id,
                'area_id' => $empleado->area_id,
            ] : null,
            'cuenta' => $verCuenta ? [
                'id' => $cuenta->id,
                'name' => $cuenta->name,
                'email' => $cuenta->email,
                'activo' => (bool) $cuenta->activo,
                'roles' => $cuenta->roles->pluck('name')->values(),
                'role_id' => $cuenta->roles->first()?->id,
            ] : null,
            // La persona ya tiene una cuenta que quien edita no puede administrar.
            'cuentaOculta' => $cuenta !== null && ! $verCuenta,
            'roles' => $crearCuenta || $verCuenta ? $this->cuentas->rolesPara($actor, $verCuenta ? $cuenta : null) : [],
            'rolSugerido' => Role::where('name', PermissionCatalog::ROLE_COLABORADOR)->value('id'),
            'effectivePermissions' => $verCuenta ? $this->cuentas->permisosEfectivos($cuenta) : [],
            'corporativos' => Corporativo::query()->whereIn('id', $corporativoIds)->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'activo']),
            'sucursales' => $sucursales->values(),
            'areas' => Area::query()->whereIn('corporativo_id', $corporativoIds)->orderBy('nombre')->get(['id', 'corporativo_id', 'nombre', 'activo']),
            'accesoInicial' => $request->boolean('acceso'),
            'isSelf' => $cuenta !== null && $cuenta->is($actor),
            'can' => [
                'registrar_persona' => $registrarPersona,
                'editar_persona' => $editarPersona,
                'crear_cuenta' => $crearCuenta,
                'editar_cuenta' => $verCuenta && $actor->can('update', $cuenta),
                'cambiar_rol' => $verCuenta && $actor->can('changeRole', $cuenta),
                'desactivar_cuenta' => $verCuenta && $actor->can('delete', $cuenta) && ! $cuenta->is($actor),
                'reactivar_cuenta' => $verCuenta && $actor->can('restore', $cuenta),
                'restablecer' => $verCuenta && $actor->can('resetPassword', $cuenta),
            ],
        ];
    }

    /**
     * Baja lógica. Si el colaborador tiene una cuenta de acceso activa, también
     * se desactiva: una persona dada de baja no debe poder seguir entrando.
     * Reactivar al colaborador NO reactiva la cuenta (se hace desde Usuarios).
     */
    public function destroy(Request $request, Empleado $empleado): RedirectResponse
    {
        $this->authorize('delete', $empleado);
        if (! $empleado->activo) {
            return back()->with('success', 'El colaborador ya estaba dado de baja.');
        }

        $cuentas = $this->cuentasActivas($request, collect([$empleado->id]));

        DB::transaction(function () use ($empleado, $cuentas) {
            $empleado->update(['activo' => false]);
            $cuentas->each(fn (User $u) => $u->forceFill(['activo' => false])->save());
        });

        return back()->with('success', $cuentas->isNotEmpty()
            ? 'Colaborador dado de baja. Su cuenta de acceso también se desactivó.'
            : 'Colaborador dado de baja.');
    }

    public function activate(Empleado $empleado): RedirectResponse
    {
        $this->authorize('restore', $empleado);
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

        $ids = Empleado::query()->visibleTo($request->user())->whereIn('id', $data['ids'])->where('activo', true)->pluck('id');
        $cuentas = $this->cuentasActivas($request, $ids);

        DB::transaction(function () use ($ids, $cuentas) {
            Empleado::query()->whereIn('id', $ids)->updateEach(['activo' => false]);
            $cuentas->each(fn (User $u) => $u->forceFill(['activo' => false])->save());
        });

        return back()->with('success', $cuentas->isNotEmpty()
            ? 'Colaboradores dados de baja. Sus cuentas de acceso también se desactivaron.'
            : 'Colaboradores dados de baja.');
    }

    /**
     * Cuentas activas vinculadas a los colaboradores que se darán de baja.
     * Valida antes de modificar nada: no puedes darte de baja a ti mismo ni
     * dejar al sistema sin administradores activos.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $empleadoIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function cuentasActivas(Request $request, $empleadoIds)
    {
        $cuentas = User::query()->whereIn('empleado_id', $empleadoIds)->where('activo', true)->get();

        if ($cuentas->contains(fn (User $u) => $u->is($request->user()))) {
            throw ValidationException::withMessages(['ids' => 'No puedes dar de baja al colaborador vinculado a tu propia cuenta.']);
        }

        // Se simula la baja acumulada para que una baja masiva no elimine a todos los administradores.
        $this->guard->assertCanDeactivateMany($cuentas, 'ids');

        return $cuentas;
    }

    /**
     * Sucursales donde puede registrar o mover colaboradores, según su alcance
     * en Colaboradores: todas, las de su corporativo o solo la suya.
     */
    private function sucursalesPermitidas(User $user)
    {
        $q = Sucursal::query();

        return match (AccessScope::for($user, 'colaboradores')) {
            Scope::Global => $q,
            Scope::Corporativo => $q->where('corporativo_id', AccessScope::corporativoId($user) ?? 0),
            default => $q->whereKey(AccessScope::sucursalId($user) ?? 0),
        };
    }

    private function assertSucursalPermitida(Request $request, int $sucursalId): void
    {
        if (! $this->sucursalesPermitidas($request->user())->whereKey($sucursalId)->exists()) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal está fuera de tu alcance de colaboradores.']);
        }
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
