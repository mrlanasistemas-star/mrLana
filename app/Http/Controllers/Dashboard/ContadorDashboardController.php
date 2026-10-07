<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardProfile;
use App\Support\BusinessDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContadorDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        abort_unless(DashboardProfile::Financiero->allowedFor($user), 403);

        $tz = BusinessDate::timezone();
        $now = Carbon::now($tz);

        $start14 = $now->copy()->subDays(13)->startOfDay();
        $startMonth = $now->copy()->startOfMonth()->startOfDay();
        $endMonth = $now->copy()->endOfMonth()->endOfDay();

        $capturadas = DB::table('requisicions')->where('status', 'CAPTURADA')->count();
        $autorizadas = DB::table('requisicions')->where('status', 'PAGO_AUTORIZADO')->count();
        $porComprobar = DB::table('requisicions')->where('status', 'POR_COMPROBAR')->count();

        $montoPagadoMes = (float) DB::table('requisicions')
            ->where('status', 'PAGADA')
            ->whereBetween('fecha_pago', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->sum('monto_total');

        // Activity/Amounts 14 días por fecha_solicitud
        $activityRows = DB::table('requisicions')
            ->selectRaw('DATE(fecha_solicitud) as d, COUNT(*) as qty')
            ->where('fecha_solicitud', '>=', $start14)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

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

            $activityDaily[] = ['name' => $label, 'value' => (int) ($activityRows[$day]->qty ?? 0)];
            $amountsDaily[] = ['name' => $label, 'value' => (float) ($amountRows[$day]->monto ?? 0)];
        }

        $kpis = [
            ['label' => 'Capturadas',          'value' => number_format($capturadas),  'hint' => 'Pendientes de autorización'],
            ['label' => 'Autorizadas',         'value' => number_format($autorizadas), 'hint' => 'Pendientes de pago'],
            ['label' => 'Por comprobar',       'value' => number_format($porComprobar), 'hint' => 'Pendientes de evidencia'],
            ['label' => 'Pagado (mes)',        'value' => '$ '.number_format($montoPagadoMes, 2), 'hint' => 'Por fecha de pago'],
        ];

        return Inertia::render('Dashboard/ContadorDashboard', [
            'dashboard' => [
                'headline' => 'Panel financiero',
                'subheadline' => 'Autorización, pago y control operativo (14 días).',
                'userName' => $user->name,
                'userRole' => $user->getRoleNames()->implode(', '),
                'exportSegment' => DashboardProfile::Financiero->exportSegment(),
                'canExport' => $user->can('reportes.dashboard'),
                'kpis' => $kpis,
                'activityDaily' => $activityDaily,
                'amountsDaily' => $amountsDaily,
            ],
        ]);
    }
}
