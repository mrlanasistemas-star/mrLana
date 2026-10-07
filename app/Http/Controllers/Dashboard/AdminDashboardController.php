<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardProfile;
use App\Support\BusinessDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        abort_unless(DashboardProfile::Ejecutivo->allowedFor($user), 403);
        $tz = BusinessDate::timezone();
        $now = Carbon::now($tz);

        $start14 = $now->copy()->subDays(13)->startOfDay();
        $start30 = $now->copy()->subDays(29)->startOfDay();
        $startMonth = $now->copy()->startOfMonth()->startOfDay();
        $endMonth = $now->copy()->endOfMonth()->endOfDay();

        // KPIs base
        $corporativosActivos = DB::table('corporativos')->where('activo', 1)->count();
        $sucursalesActivas = DB::table('sucursals')->where('activo', 1)->count();
        $empleadosActivos = DB::table('empleados')->where('activo', 1)->count();

        // Monto del mes por fecha_solicitud (TU BD REAL)
        $montoMes = (float) DB::table('requisicions')
            ->whereBetween('fecha_solicitud', [$startMonth, $endMonth])
            ->sum('monto_total');

        // ActivityDaily (últimos 14 días) por fecha_solicitud
        $activityRows = DB::table('requisicions')
            ->selectRaw('DATE(fecha_solicitud) as d, COUNT(*) as qty')
            ->where('fecha_solicitud', '>=', $start14)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        // AmountsDaily (últimos 14 días) por fecha_solicitud
        $amountRows = DB::table('requisicions')
            ->selectRaw('DATE(fecha_solicitud) as d, SUM(monto_total) as monto')
            ->where('fecha_solicitud', '>=', $start14)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $activityDaily = [];
        $amountsDaily = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start14->copy()->addDays($i)->toDateString();
            $label = Carbon::parse($day, $tz)->format('d M');

            $activityDaily[] = [
                'name' => $label,
                'value' => (int) (($activityRows[$day]->qty ?? 0)),
            ];

            $amountsDaily[] = [
                'name' => $label,
                'value' => (float) (($amountRows[$day]->monto ?? 0)),
            ];
        }

        // StatusMix (últimos 30 días) por fecha_solicitud + estatus reales
        $statusBase = [
            'BORRADOR' => 0,
            'ELIMINADA' => 0,
            'CAPTURADA' => 0,
            'PAGO_AUTORIZADO' => 0,
            'PAGO_RECHAZADO' => 0,
            'PAGADA' => 0,
            'POR_COMPROBAR' => 0,
            'COMPROBACION_ACEPTADA' => 0,
            'COMPROBACION_RECHAZADA' => 0,
        ];

        $statusRows = DB::table('requisicions')
            ->selectRaw('status as s, COUNT(*) as c')
            ->where('fecha_solicitud', '>=', $start30)
            ->groupBy('s')
            ->get();

        foreach ($statusRows as $r) {
            $k = (string) $r->s;
            if (array_key_exists($k, $statusBase)) {
                $statusBase[$k] = (int) $r->c;
            }
        }

        $statusMix = [];
        foreach ($statusBase as $name => $count) {
            $statusMix[] = ['name' => $name, 'value' => $count];
        }
        // ComprobantesMix (mes) -> tu BD real: fecha_emision (fallback a created_at)
        $compBase = ['FACTURA' => 0, 'TICKET' => 0, 'NOTA' => 0, 'OTRO' => 0];
        $compRows = DB::table('comprobantes')
            ->selectRaw('tipo_doc as t, COUNT(*) as c')
            ->where(function ($q) use ($startMonth, $endMonth) {
                // Si existe fecha_emision úsala
                $q->whereBetween('fecha_emision', [$startMonth->toDateString(), $endMonth->toDateString()])
                // Si fecha_emision viene null, usa created_at como fallback
                    ->orWhere(function ($q2) use ($startMonth, $endMonth) {
                        $q2->whereNull('fecha_emision')
                            ->whereBetween('created_at', [$startMonth, $endMonth]);
                    });
            })
            ->groupBy('t')
            ->get();

        foreach ($compRows as $r) {
            $k = (string) $r->t;
            if (array_key_exists($k, $compBase)) {
                $compBase[$k] = (int) $r->c;
            }
        }

        $comprobantesMix = [];
        foreach ($compBase as $name => $count) {
            $comprobantesMix[] = ['name' => $name, 'value' => $count];
        }

        $kpis = [
            ['label' => 'Corporativos activos', 'value' => number_format($corporativosActivos), 'hint' => 'Base operativa vigente'],
            ['label' => 'Sucursales activas',   'value' => number_format($sucursalesActivas),   'hint' => 'Cobertura actual'],
            ['label' => 'Colaboradores activos', 'value' => number_format($empleadosActivos),    'hint' => 'Personas en operación'],
            ['label' => 'Monto del mes',        'value' => '$ '.number_format($montoMes, 2),  'hint' => 'Por fecha de solicitud'],
        ];

        return Inertia::render('Dashboard/AdminDashboard', [
            'dashboard' => [
                'headline' => 'Panel ejecutivo',
                'subheadline' => 'KPIs globales + pulso de operación (14 días).',
                'userName' => $user->name,
                'userRole' => $user->getRoleNames()->implode(', '),
                'exportSegment' => DashboardProfile::Ejecutivo->exportSegment(),
                'canExport' => $user->can('reportes.dashboard'),
                'kpis' => $kpis,

                // Estas llaves son las que tu Vue consume
                'activityDaily' => $activityDaily,
                'amountsDaily' => $amountsDaily,
                'statusMix' => $statusMix,
                'comprobantesMix' => $comprobantesMix,
            ],
        ]);
    }
}
