<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora del sistema: quién hizo qué, sobre qué registro, cuándo y desde
 * dónde. Permite rastrear el historial completo de un registro (tabla + id).
 */
class SystemLogController extends Controller
{
    /** Nombre del módulo por tabla. */
    public const MODULES = [
        'requisicions' => 'Requisiciones',
        'detalles' => 'Partidas de requisición',
        'pagos' => 'Pagos',
        'comprobantes' => 'Comprobantes',
        'folios' => 'Folios',
        'plantillas' => 'Plantillas',
        'plantilla_detalles' => 'Partidas de plantilla',
        'proveedors' => 'Proveedores',
        'conceptos' => 'Conceptos',
        'corporativos' => 'Corporativos',
        'sucursals' => 'Sucursales',
        'areas' => 'Áreas',
        'empleados' => 'Colaboradores',
        'users' => 'Usuarios',
        'requisicion_recurrencias' => 'Recurrencias',
    ];

    /** Acciones con su etiqueta. ELIMINACION antigua = baja lógica de versiones previas. */
    public const ACTIONS = [
        'CREACION' => 'Creación',
        'ACTUALIZACION' => 'Actualización',
        'CAMBIO_ESTATUS' => 'Cambio de estatus',
        'BAJA' => 'Baja (eliminación lógica)',
        'REACTIVACION' => 'Reactivación',
        'ACTIVACION' => 'Reactivación (versión anterior)',
        'ELIMINACION' => 'Eliminación',
    ];

    public function index(Request $request): Response
    {
        $f = $this->filters($request);

        $base = SystemLog::query()
            ->when($f['from'], fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($f['to'], fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->when($f['tabla'], fn ($q, $t) => $q->where('tabla', $t))
            ->when($f['registro_id'], fn ($q, $id) => $q->where('registro_id', $id))
            ->when($f['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['ip'], fn ($q, $ip) => $q->where('ip_address', 'like', "%{$ip}%"))
            ->when($f['q'], function ($q, $term) {
                $q->where(fn ($w) => $w->where('descripcion', 'like', "%{$term}%")
                    ->orWhere('etiqueta', 'like', "%{$term}%")
                    ->orWhereRaw('CAST(registro_id AS CHAR) = ?', [$term]));
            });

        // Conteo por acción con los demás filtros (para los chips).
        $counts = (clone $base)->selectRaw('accion, COUNT(*) as c')->groupBy('accion')->pluck('c', 'accion');

        $logs = (clone $base)
            ->when($f['accion'], fn ($q, $a) => $f['accion'] === 'BAJA' ? $q->whereIn('accion', ['BAJA', 'ELIMINACION']) : $q->where('accion', $a))
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate($f['perPage'])
            ->withQueryString();

        $logs->getCollection()->transform(fn (SystemLog $l) => [
            'id' => $l->id,
            'accion' => $l->accion,
            'accion_label' => self::ACTIONS[$l->accion] ?? ucfirst(strtolower(str_replace('_', ' ', $l->accion))),
            'tabla' => $l->tabla,
            'modulo' => self::MODULES[$l->tabla] ?? ucfirst(str_replace('_', ' ', $l->tabla)),
            'registro_id' => $l->registro_id,
            'etiqueta' => $l->etiqueta,
            'descripcion' => $l->descripcion,
            'cambios' => $l->cambios,
            'ip_address' => $l->ip_address,
            'user_agent' => $l->user_agent,
            'user' => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name, 'email' => $l->user->email] : null,
            'created_at' => optional($l->created_at)->toISOString(),
        ]);

        $tablas = SystemLog::query()->select('tabla')->distinct()->pluck('tabla')
            ->map(fn ($t) => ['id' => $t, 'nombre' => self::MODULES[$t] ?? ucfirst(str_replace('_', ' ', $t))])
            ->sortBy('nombre')->values();

        return Inertia::render('SystemLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'from' => $f['from'] ? $request->query('from') : null,
                'to' => $f['to'] ? $request->query('to') : null,
                'tabla' => $f['tabla'],
                'registro_id' => $f['registro_id'],
                'accion' => $f['accion'],
                'user_id' => $f['user_id'],
                'ip' => $f['ip'],
                'q' => $f['q'],
                'perPage' => $f['perPage'],
            ],
            'counts' => [
                'total' => (int) $counts->sum(),
                'by_action' => $counts->map(fn ($c) => (int) $c),
            ],
            'tablas' => $tablas,
            'acciones' => collect(self::ACTIONS)->except('ACTIVACION', 'ELIMINACION')->map(fn ($label, $id) => ['id' => $id, 'nombre' => $label])->values(),
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name as nombre', 'email']),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $tz = BusinessDate::timezone();
        $date = function (?string $v, bool $end) use ($tz): ?CarbonImmutable {
            if (! $v || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            $d = CarbonImmutable::createFromFormat('Y-m-d', $v, $tz);

            // La bitácora guarda en UTC; se convierte el día de negocio completo.
            return ($end ? $d->endOfDay() : $d->startOfDay())->utc();
        };
        $str = fn (string $k) => ($v = trim((string) $request->query($k, ''))) !== '' ? $v : null;
        $int = fn (string $k) => is_numeric($request->query($k)) && (int) $request->query($k) > 0 ? (int) $request->query($k) : null;

        return [
            'from' => $date($str('from'), false),
            'to' => $date($str('to'), true),
            'tabla' => $str('tabla'),
            'registro_id' => $int('registro_id'),
            'accion' => array_key_exists((string) $request->query('accion'), self::ACTIONS) ? $request->query('accion') : null,
            'user_id' => $int('user_id'),
            'ip' => $str('ip'),
            'q' => $str('q'),
            'perPage' => in_array((int) $request->query('perPage'), [15, 30, 50, 100], true) ? (int) $request->query('perPage') : 30,
        ];
    }
}
