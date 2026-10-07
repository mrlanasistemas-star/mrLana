<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Datos del dashboard para reportes (PDF / Excel).
 *
 * El perfil "personal" se limita a las requisiciones propias del usuario
 * (creadas por él o donde es el colaborador solicitante).
 */
class DashboardDataService
{
    public const STATUS_LABELS = [
        'BORRADOR' => 'Borrador',
        'CAPTURADA' => 'Capturada',
        'PAGO_AUTORIZADO' => 'Pago autorizado',
        'PAGO_RECHAZADO' => 'Pago rechazado',
        'PAGADA' => 'Pagada',
        'POR_COMPROBAR' => 'Por comprobar',
        'COMPROBACION_ACEPTADA' => 'Comprobación aceptada',
        'COMPROBACION_RECHAZADA' => 'Comprobación rechazada',
        'ELIMINADA' => 'Eliminada',
    ];

    public const DOC_LABELS = ['FACTURA' => 'Factura', 'TICKET' => 'Ticket', 'NOTA' => 'Nota', 'OTRO' => 'Otro'];

    public function build(DashboardProfile $profile, User $user): array
    {
        $now = CarbonImmutable::now(BusinessDate::timezone());
        $startMonth = $now->startOfMonth();
        $endMonth = $now->endOfMonth();
        $start14 = $now->subDays(13)->startOfDay();
        $start30 = $now->subDays(29)->startOfDay();

        $scope = fn (): Builder => $this->requisiciones($profile, $user);

        $kpis = match ($profile) {
            DashboardProfile::Ejecutivo => [
                ['label' => 'Corporativos activos', 'value' => (string) DB::table('corporativos')->where('activo', 1)->count(), 'hint' => 'Base operativa vigente'],
                ['label' => 'Sucursales activas', 'value' => (string) DB::table('sucursals')->where('activo', 1)->count(), 'hint' => 'Cobertura actual'],
                ['label' => 'Colaboradores activos', 'value' => (string) DB::table('empleados')->where('activo', 1)->count(), 'hint' => 'Personas en operación'],
                ['label' => 'Monto del mes', 'value' => '$'.number_format((float) $scope()->whereBetween('fecha_solicitud', [$startMonth, $endMonth])->sum('monto_total'), 2), 'hint' => 'Por fecha de solicitud'],
            ],
            DashboardProfile::Financiero => [
                ['label' => 'Capturadas', 'value' => (string) $scope()->where('status', 'CAPTURADA')->count(), 'hint' => 'Pendientes de autorización'],
                ['label' => 'Autorizadas', 'value' => (string) $scope()->where('status', 'PAGO_AUTORIZADO')->count(), 'hint' => 'Pendientes de pago'],
                ['label' => 'Por comprobar', 'value' => (string) $scope()->where('status', 'POR_COMPROBAR')->count(), 'hint' => 'Pendientes de evidencia'],
                ['label' => 'Pagado (mes)', 'value' => '$'.number_format((float) $scope()->where('status', 'PAGADA')->whereBetween('fecha_pago', [$startMonth->toDateString(), $endMonth->toDateString()])->sum('monto_total'), 2), 'hint' => 'Por fecha de pago'],
            ],
            DashboardProfile::Personal => [
                ['label' => 'Mis requisiciones', 'value' => (string) $scope()->count(), 'hint' => 'Creadas por mí o como solicitante'],
                ['label' => 'Pendientes', 'value' => (string) $scope()->whereIn('status', ['CAPTURADA', 'PAGO_AUTORIZADO', 'POR_COMPROBAR'])->count(), 'hint' => 'En proceso'],
                ['label' => 'Pagadas (mes)', 'value' => (string) $scope()->where('status', 'PAGADA')->whereBetween('fecha_pago', [$startMonth->toDateString(), $endMonth->toDateString()])->count(), 'hint' => 'Por fecha de pago'],
                ['label' => 'Monto (mes)', 'value' => '$'.number_format((float) $scope()->whereBetween('fecha_solicitud', [$startMonth, $endMonth])->sum('monto_total'), 2), 'hint' => 'Por fecha de solicitud'],
            ],
        };

        $activityRows = $scope()
            ->selectRaw('DATE(fecha_solicitud) as d, COUNT(*) as qty, SUM(monto_total) as monto')
            ->where('fecha_solicitud', '>=', $start14)
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $activityDaily = [];
        $amountsDaily = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start14->addDays($i);
            $row = $activityRows[$day->toDateString()] ?? null;
            $label = $day->format('d/m');
            $activityDaily[] = ['name' => $label, 'value' => (int) ($row->qty ?? 0)];
            $amountsDaily[] = ['name' => $label, 'value' => round((float) ($row->monto ?? 0), 2)];
        }

        $statusCounts = $scope()
            ->selectRaw('status as s, COUNT(*) as c')
            ->where('fecha_solicitud', '>=', $start30)
            ->groupBy('s')
            ->pluck('c', 's');

        $statusMix = [];
        foreach (self::STATUS_LABELS as $key => $label) {
            $statusMix[] = ['key' => $key, 'name' => $label, 'value' => (int) ($statusCounts[$key] ?? 0)];
        }

        $compQuery = DB::table('comprobantes')
            ->whereIn('requisicion_id', $scope()->select('id'))
            ->where(function ($q) use ($startMonth, $endMonth) {
                $q->whereBetween('fecha_emision', [$startMonth->toDateString(), $endMonth->toDateString()])
                    ->orWhere(fn ($q2) => $q2->whereNull('fecha_emision')->whereBetween('created_at', [$startMonth, $endMonth]));
            });

        $compCounts = $compQuery->selectRaw('tipo_doc as t, COUNT(*) as c')->groupBy('t')->pluck('c', 't');

        $comprobantesMix = [];
        foreach (self::DOC_LABELS as $key => $label) {
            $comprobantesMix[] = ['key' => $key, 'name' => $label, 'value' => (int) ($compCounts[$key] ?? 0)];
        }

        $subheadline = match ($profile) {
            DashboardProfile::Ejecutivo => 'Indicadores globales y pulso de operación (14 días).',
            DashboardProfile::Financiero => 'Autorización, pago y control de comprobación.',
            DashboardProfile::Personal => 'Tu actividad y pendientes.',
        };

        return [
            'profile' => $profile->value,
            'headline' => $profile->label(),
            'subheadline' => $subheadline,
            'userName' => $user->name,
            'userRole' => $user->getRoleNames()->implode(', '),
            'period' => [
                'from' => $start14->format('d/m/Y'),
                'to' => $now->format('d/m/Y'),
                'month' => $now->locale('es')->isoFormat('MMMM YYYY'),
            ],
            'kpis' => $kpis,
            'activityDaily' => $activityDaily,
            'amountsDaily' => $amountsDaily,
            'statusMix' => $statusMix,
            'comprobantesMix' => $comprobantesMix,
        ];
    }

    private function requisiciones(DashboardProfile $profile, User $user): Builder
    {
        $query = DB::table('requisicions');

        if ($profile === DashboardProfile::Personal) {
            $query->where(function ($q) use ($user) {
                $q->where('creada_por_user_id', $user->id);
                if ($user->empleado_id) {
                    $q->orWhere('solicitante_id', $user->empleado_id);
                }
            });
        }

        return $query;
    }
}
