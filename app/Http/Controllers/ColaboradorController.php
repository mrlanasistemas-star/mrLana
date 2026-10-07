<?php

namespace App\Http\Controllers;

use App\Http\Requests\Empleado\StoreEmpleadoRequest;
use App\Http\Requests\Empleado\UpdateEmpleadoRequest;
use App\Mail\EmpleadoAccesoCreadoMail;
use App\Models\Area;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmpleadoController extends Controller {

    public function index(Request $request) {
        // Normalización de filtros
        $q = trim((string) $request->get('q', ''));
        $corporativoId = $request->get('corporativo_id', '');
        $sucursalId    = $request->get('sucursal_id', '');
        $areaId        = $request->get('area_id', '');
        $activo = $request->get('activo', '1');
        $activo = ($activo === '' || $activo === null) ? 'all' : (string) $activo;
        $activo = in_array($activo, ['all', '1', '0'], true) ? $activo : '1';
        $perPage = (int) ($request->get('per_page', $request->get('perPage', 15)));
        $perPage = max(10, min(100, $perPage));
        $sort = (string) $request->get('sort', 'nombre');
        $dir  = (string) $request->get('dir', 'asc');
        $sort = in_array($sort, ['nombre', 'id'], true) ? $sort : 'nombre';
        $dir  = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
        $corporativoId = ($corporativoId === '' || $corporativoId === null) ? null : (int) $corporativoId;
        $sucursalId    = ($sucursalId === '' || $sucursalId === null) ? null : (int) $sucursalId;
        $areaId        = ($areaId === '' || $areaId === null) ? null : (int) $areaId;

        $appends = [
            'q'              => $q,
            'corporativo_id' => $corporativoId ?? '',
            'sucursal_id'    => $sucursalId ?? '',
            'area_id'        => $areaId ?? '',
            'activo'         => $activo,
            'per_page'       => $perPage,
            'sort'           => $sort,
            'dir'            => $dir,
        ];

        // Query
        $query = Empleado::query()
            ->with([
                'sucursal:id,corporativo_id,nombre,codigo,activo',
                'sucursal.corporativo:id,nombre,codigo,activo',
                'area:id,corporativo_id,nombre,activo',
                'user:id,empleado_id,name,email,rol,activo',
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre', 'like', "%{$q}%")
                        ->orWhere('apellido_paterno', 'like', "%{$q}%")
                        ->orWhere('apellido_materno', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%")
                        ->orWhere('puesto', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($u) use ($q) {
                            $u->where('email', 'like', "%{$q}%")
                              ->orWhere('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('sucursal', fn ($s) => $s->where('nombre', 'like', "%{$q}%"))
                        ->orWhereHas('area', fn ($a) => $a->where('nombre', 'like', "%{$q}%"));
                });
            })
            ->when(!is_null($sucursalId), fn ($qq) => $qq->where('sucursal_id', $sucursalId))
            ->when(!is_null($areaId), fn ($qq) => $qq->where('area_id', $areaId))
            ->when($activo !== 'all', fn ($qq) => $qq->where('activo', (int) $activo))
            ->when(!is_null($corporativoId), function ($qq) use ($corporativoId) {
                $qq->whereHas('sucursal', fn ($s) => $s->where('corporativo_id', $corporativoId));
            });

        // Orden por "nombre completo" (apellido(s) + nombre) para que el A-Z sí se sienta natural
        if ($sort === 'id') {
            $query->orderBy('id', $dir);
        } else {
            $query->orderByRaw("TRIM(CONCAT(apellido_paterno,' ',COALESCE(apellido_materno,''),' ',nombre)) {$dir}");
        }
        $query->orderBy('id', 'asc');

        $empleados = $query->paginate($perPage)->appends($appends);

        return Inertia::render('Empleados/Index', [
            'empleados' => $empleados,

            'corporativos' => Corporativo::query()
                ->select(['id', 'nombre', 'codigo', 'activo'])
                ->orderBy('nombre')
                ->get(),

            'sucursales' => Sucursal::query()
                ->with('corporativo:id,nombre,codigo,activo')
                ->select(['id', 'corporativo_id', 'nombre', 'codigo', 'activo'])
                ->orderBy('nombre')
                ->get(),

            'areas' => Area::query()
                ->select(['id', 'corporativo_id', 'nombre', 'activo'])
                ->orderBy('nombre')
                ->get(),

            'filters' => [
                'q'              => $q,
                'corporativo_id' => $corporativoId,
                'sucursal_id'    => $sucursalId,
                'area_id'        => $areaId,
                'activo'         => $activo,
                'per_page'       => $perPage,
                'perPage'        => $perPage,
                'sort'           => $sort,
                'dir'            => $dir,
            ],
        ]);
    }

    public function store(StoreEmpleadoRequest $request) {
        $data = $request->validated();
        // Bloqueo organizacional (mensajes humanos)
        $this->assertOrgActivaOrFail((int) $data['sucursal_id'], $data['area_id'] !== null ? (int) $data['area_id'] : null);

        // Password 8 chars (A-Z0-9)
        $plainPassword = Str::upper(Str::random(8));

        try {
            $user = DB::transaction(function () use ($data, $plainPassword) {

                $empleado = Empleado::create([
                    'sucursal_id'      => (int) $data['sucursal_id'],
                    'area_id'          => $data['area_id'] !== null ? (int) $data['area_id'] : null,
                    'nombre'           => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'] ?? null,
                    'email'            => $data['user_email'],
                    'telefono'         => $data['telefono'] ?? null,
                    'puesto'           => $data['puesto'] ?? null,
                    'activo'           => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
                ]);

                $user = new User();
                $user->empleado_id = $empleado->id;
                $user->name        = $data['user_name'];
                $user->email       = $data['user_email'];
                $user->rol         = $data['user_rol'];
                $user->activo      = array_key_exists('user_activo', $data) ? (bool) $data['user_activo'] : true;
                $user->password    = Hash::make($plainPassword);
                $user->save();

                return $user;
            });

            // correo FUERA de la transacción
            Mail::to($user->email)->send(new EmpleadoAccesoCreadoMail($user, $plainPassword));

            return back()->with('success', 'Empleado creado. Se enviaron los accesos al correo.');
        } catch (\Throwable $e) {
            report($e);
            dd($e->getMessage(), $e->getFile(), $e->getLine());
            return back()->withErrors([
                'user_email' => 'Se creó el empleado, pero falló el envío del correo. Revisa la configuración SMTP / logs.',
            ]);
        }
    }

    public function update(UpdateEmpleadoRequest $request, Empleado $empleado) {

        $data = $request->validated();

        $this->assertOrgActivaOrFail((int) $data['sucursal_id'], $data['area_id'] !== null ? (int) $data['area_id'] : null);

        DB::transaction(function () use ($data, $empleado) {

            $empleado->update([
                'sucursal_id'      => (int) $data['sucursal_id'],
                'area_id'          => $data['area_id'] !== null ? (int) $data['area_id'] : null,
                'nombre'           => $data['nombre'],
                'apellido_paterno' => $data['apellido_paterno'],
                'apellido_materno' => $data['apellido_materno'] ?? null,
                'email'            => $data['user_email'], // mantenemos 1 sola fuente
                'telefono'         => $data['telefono'] ?? null,
                'puesto'           => $data['puesto'] ?? null,
                'activo'           => array_key_exists('activo', $data) ? (bool) $data['activo'] : $empleado->activo,
            ]);

            $user = $empleado->user()->first();

            if (!$user) {
                $user = new User();
                $user->empleado_id = $empleado->id;
            }

            $user->name   = $data['user_name'];
            $user->email  = $data['user_email'];
            $user->rol    = $data['user_rol'];

            if (array_key_exists('user_activo', $data)) {
                $user->activo = (bool) $data['user_activo'];
            }

            if (!empty($data['user_password'])) {
                $user->password = Hash::make($data['user_password']);
            }

            $user->save();
        });

        return back()->with('success', 'Empleado actualizado.');
    }

    public function destroy(Empleado $empleado) {

        if (!$empleado->activo) {
            return back()->with('success', 'El empleado ya estaba dado de baja.');
        }

        DB::transaction(function () use ($empleado) {
            $empleado->update(['activo' => false]);
            $empleado->user()->update(['activo' => false]);
        });

        return back()->with('success', 'Empleado dado de baja correctamente.');
    }

    public function activate(Request $request, Empleado $empleado) {

        $this->assertOrgActivaOrFail((int) $empleado->sucursal_id, $empleado->area_id ? (int) $empleado->area_id : null);

        DB::transaction(function () use ($empleado) {
            $empleado->update(['activo' => true]);
            $empleado->user()->update(['activo' => true]);
        });

        return back()->with('success', 'Empleado activado.');
    }

    public function bulkDestroy(Request $request) {

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('empleados', 'id')],
        ], [
            'ids.required' => 'Selecciona al menos un empleado.',
            'ids.array'    => 'Selección inválida.',
            'ids.min'      => 'Selecciona al menos un empleado.',
            'ids.*.exists' => 'Uno o más empleados no existen.',
        ]);

        DB::transaction(function () use ($data) {
            Empleado::query()->whereIn('id', $data['ids'])->update(['activo' => false]);
            User::query()->whereIn('empleado_id', $data['ids'])->update(['activo' => false]);
        });

        return back()->with('success', 'Empleados dados de baja.');
    }

    private function assertOrgActivaOrFail(int $sucursalId, ?int $areaId = null): void {

        $sucursal = Sucursal::query()
            ->select(['id', 'corporativo_id', 'activo'])
            ->with(['corporativo:id,activo'])
            ->find($sucursalId);

        if (!$sucursal) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'La sucursal seleccionada no existe.',
            ]);
        }

        if (!$sucursal->activo) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'La sucursal está dada de baja. Reactívala para poder continuar.',
            ]);
        }

        if ($sucursal->corporativo && !$sucursal->corporativo->activo) {
            throw ValidationException::withMessages([
                'corporativo_id' => 'El corporativo está dado de baja. Reactívalo para poder continuar.',
            ]);
        }

        if (!is_null($areaId)) {
            $area = Area::query()->select(['id', 'corporativo_id', 'activo'])->find($areaId);

            if (!$area) {
                throw ValidationException::withMessages([
                    'area_id' => 'El área seleccionada no existe.',
                ]);
            }

            if (!$area->activo) {
                throw ValidationException::withMessages([
                    'area_id' => 'El área está dada de baja. Reactívala para poder continuar.',
                ]);
            }

            if ((int) $area->corporativo_id !== (int) $sucursal->corporativo_id) {
                throw ValidationException::withMessages([
                    'area_id' => 'El área no pertenece al corporativo de la sucursal seleccionada.',
                ]);
            }
        }
    }

}
