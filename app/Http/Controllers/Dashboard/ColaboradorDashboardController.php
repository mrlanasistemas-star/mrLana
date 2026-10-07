<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardProfile;
use App\Support\BusinessDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ColaboradorDashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        abort_unless(DashboardProfile::Personal->allowedFor($user), 403);

        $tz = BusinessDate::timezone();
        $now = Carbon::now($tz);

        $start14 = $now->copy()->subDays(13)->startOfDay();
        $startMonth = $now->copy()->startOfMonth()->startOfDay();
        $endMonth = $now->copy()->endOfMonth()->endOfDay();

        $userId = (int) $user->id;
        $empleadoId = $user->empleado_id ? (int) $user->empleado_id : null;

        // Scope “mío”: creado por mí o soy solicitante
        $scope = DB::table('requisicions')
            ->where(function ($q) use ($userId, $empleadoId) {
                $q->where('creada_por_user_id', $userId);
                if ($empleadoId) $q->orWhere('solicitante_id', $empleadoId);
            });

        $totalMias = (clone $scope)->count();

        $pendientes = (clone $scope)
            ->whereIn('status', ['CAPTURADA', 'PAGO_AUTORIZADO', 'POR_COMPROBAR'])
            ->count();

        $pagadasMes = (clone $scope)
            ->where('status', 'PAGADA')
            ->whereBetween('fecha_pago', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->count();

        $montoMes = (float) (clone $scope)
            ->whereBetween('fecha_solicitud', [$startMonth, $endMonth])
            ->sum('monto_total');

        // Activity/Amounts (14 días) por fecha_solicitud
        $activityRows = (clone $scope)
            ->selectRaw("DATE(fecha_solicitud) as d, COUNT(*) as qty")
            ->where('fecha_solicitud', '>=', $start14)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $amountRows = (clone $scope)
            ->selectRaw("DATE(fecha_solicitud) as d, SUM(monto_total) as monto")
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

            $activityDaily[] = ['name' => $label, 'value' => (int)($activityRows[$day]->qty ?? 0)];
            $amountsDaily[]  = ['name' => $label, 'value' => (float)($amountRows[$day]->monto ?? 0)];
        }

        $kpis = [
            ['label' => 'Mis requisiciones', 'value' => number_format($totalMias), 'hint' => 'Creadas por mí o solicitante'],
            ['label' => 'Pendientes',        'value' => number_format($pendientes), 'hint' => 'CAPTURADA / AUTORIZADA / POR COMPROBAR'],
            ['label' => 'Pagadas (mes)',     'value' => number_format($pagadasMes), 'hint' => 'Por fecha de pago'],
            ['label' => 'Monto (mes)',       'value' => '$ ' . number_format($montoMes, 2), 'hint' => 'Por fecha de solicitud'],
        ];

        return Inertia::render('Dashboard/ColaboradorDashboard', [
            'dashboard' => [
                'headline' => 'Mi operación',
                'subheadline' => 'Tus métricas (14 días) y foco de pendientes.',
                'userName' => $user->name,
                'userRole' => $user->getRoleNames()->implode(', '),
                'exportSegment' => DashboardProfile::Personal->exportSegment(),
                'canExport' => $user->can('reportes.dashboard'),
                'kpis' => $kpis,
                'activityDaily' => $activityDaily,
                'amountsDaily' => $amountsDaily,
            ],
        ]);
    }
}
