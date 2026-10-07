<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Datos del dashboard (pantalla, PDF y Excel) con filtros.
 *
 * Filtros: periodo (preset o rango), corporativo, sucursal, concepto y estatus.
 * El perfil "personal" se limita a las requisiciones propias del usuario
 * (creadas por él o donde es el colaborador solicitante).
 * Las requisiciones eliminadas no suman a montos ni conteos, salvo que se
 * filtre explícitamente por ese estatus.
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

    public const PRESETS = [
        'mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        '30d' => 'Últimos 30 días',
        '90d' => 'Últimos 90 días',
        'anio' => 'Este año',
        'rango' => 'Personalizado',
    ];

    private const PAID = ['PAGADA', 'POR_COMPROBAR', 'COMPROBACION_ACEPTADA', 'COMPROBACION_RECHAZADA'];

    private const TOP = 8;

    /**
     * @param  array<string, mixed>  $input  Parámetros de la petición (query string).
     */
    public function build(DashboardProfile $profile, User $user, array $input = []): array
    {
        $f = $this->normalize($input);
        [$from, $to] = [$f['from'], $f['to']];
        $days = (int) $from->diffInDays($to) + 1;
        [$prevFrom, $prevTo] = [$from->subDays($days), $from->subSecond()];

        // Alcance base: perfil + filtros (sin periodo).
        $scope = fn (bool $withStatus = true): Builder => $this->scope($profile, $user, $f, $withStatus);
        $inPeriod = fn (Builder $q, CarbonImmutable $a, CarbonImmutable $b): Builder => $q->whereBetween('r.fecha_solicitud', [$a, $b]);

        $cur = $this->totals($inPeriod($scope(), $from, $to));
        $prev = $this->totals($inPeriod($scope(), $prevFrom, $prevTo));

        $cards = [
            $this->card('monto', 'Gasto solicitado', $cur['monto'], $prev['monto'], 'money', 'Suma de requisiciones del periodo'),
            $this->card('count', 'Requisiciones', $cur['count'], $prev['count'], 'int', 'Registradas en el periodo'),
            $this->card('avg', 'Ticket promedio', $cur['avg'], $prev['avg'], 'money', 'Monto promedio por requisición'),
            $this->card('paid', 'Pagado', $cur['paid'], $prev['paid'], 'money', 'Pagadas o en comprobación'),
            $this->card('pending', 'Pendientes de pago', $cur['pending'], null, 'int', 'Capturadas o autorizadas'),
            $this->card('toCheck', 'Por comprobar', $cur['toCheck'], null, 'int', 'Pagadas sin evidencia completa'),
        ];

        $trend = $this->trend($inPeriod($scope(), $from, $to), $from, $to);

        return [
            'profile' => $profile->value,
            'headline' => $profile->label(),
            'subheadline' => match ($profile) {
                DashboardProfile::Ejecutivo => 'Visión global del gasto y la operación.',
                DashboardProfile::Financiero => 'Autorización, pago y control de comprobación.',
                DashboardProfile::Personal => 'Tu actividad y pendientes.',
            },
            'userName' => $user->name,
            'userRole' => $user->getRoleNames()->implode(', '),
            'filters' => [
                'preset' => $f['preset'],
                'desde' => $from->toDateString(),
                'hasta' => $to->toDateString(),
                'corporativo_id' => $f['corporativo_id'],
                'sucursal_id' => $f['sucursal_id'],
                'concepto_id' => $f['concepto_id'],
                'status' => $f['status'],
            ],
            'period' => [
                'from' => $from->format('d/m/Y'),
                'to' => $to->format('d/m/Y'),
                'label' => self::PRESETS[$f['preset']] ?? 'Periodo',
                'month' => $from->locale('es')->isoFormat('MMMM YYYY'),
                'compare' => $prevFrom->format('d/m/Y').' – '.$prevTo->format('d/m/Y'),
            ],
            'cards' => $cards,
            // Compatibilidad con PDF/Excel.
            'kpis' => array_map(fn ($c) => ['label' => $c['label'], 'value' => $c['display'], 'hint' => $c['hint']], $cards),
            'trend' => $trend,
            'activityDaily' => array_map(fn ($p) => ['name' => $p['name'], 'value' => $p['count']], $trend['points']),
            'amountsDaily' => array_map(fn ($p) => ['name' => $p['name'], 'value' => $p['monto']], $trend['points']),
            'statusMix' => $this->statusMix($inPeriod($scope(false), $from, $to)),
            'byConcepto' => $this->top($inPeriod($scope(), $from, $to), 'conceptos', 'concepto_id', 'nombre'),
            'bySucursal' => $profile === DashboardProfile::Personal ? [] : $this->top($inPeriod($scope(), $from, $to), 'sucursals', 'sucursal_id', 'nombre'),
            'byProveedor' => $this->top($inPeriod($scope(), $from, $to), 'proveedors', 'proveedor_id', 'razon_social'),
            'monthly' => $this->monthly($scope(), $to),
            'comprobantesMix' => $this->comprobantes($scope(), $from, $to),
            'options' => $this->options(),
        ];
    }

    /** @return array<string, mixed> */
    private function normalize(array $in): array
    {
        $now = BusinessDate::now();
        $preset = array_key_exists($in['preset'] ?? '', self::PRESETS) ? $in['preset'] : 'mes';
        $date = function ($v): ?CarbonImmutable {
            if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $v, BusinessDate::timezone());
            } catch (\Throwable) {
                return null;
            }
        };

        [$from, $to] = match ($preset) {
            'mes_anterior' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            '30d' => [$now->subDays(29), $now],
            '90d' => [$now->subDays(89), $now],
            'anio' => [$now->startOfYear(), $now],
            'rango' => [$date($in['desde'] ?? null) ?? $now->startOfMonth(), $date($in['hasta'] ?? null) ?? $now],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        // Máximo tres años para mantener las consultas ligeras.
        if ($from->diffInDays($to) > 1096) {
            $from = $to->subDays(1096);
        }

        $int = fn ($v) => is_numeric($v) && (int) $v > 0 ? (int) $v : null;

        return [
            'preset' => $preset,
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'corporativo_id' => $int($in['corporativo_id'] ?? null),
            'sucursal_id' => $int($in['sucursal_id'] ?? null),
            'concepto_id' => $int($in['concepto_id'] ?? null),
            'status' => array_key_exists($in['status'] ?? '', self::STATUS_LABELS) ? $in['status'] : null,
        ];
    }

    private function scope(DashboardProfile $profile, User $user, array $f, bool $withStatus): Builder
    {
        $q = DB::table('requisicions as r');

        if ($profile === DashboardProfile::Personal) {
            $q->where(function ($w) use ($user) {
                $w->where('r.creada_por_user_id', $user->id);
                if ($user->empleado_id) {
                    $w->orWhere('r.solicitante_id', $user->empleado_id);
                }
            });
        }

        $q->when($f['corporativo_id'], fn ($w, $id) => $w->where('r.comprador_corp_id', $id))
            ->when($f['sucursal_id'], fn ($w, $id) => $w->where('r.sucursal_id', $id))
            ->when($f['concepto_id'], fn ($w, $id) => $w->where('r.concepto_id', $id));

        if ($withStatus) {
            $f['status'] ? $q->where('r.status', $f['status']) : $q->where('r.status', '!=', 'ELIMINADA');
        }

        return $q;
    }

    /** @return array<string, float|int> */
    private function totals(Builder $q): array
    {
        $paid = "'".implode("','", self::PAID)."'";
        $row = $q->selectRaw("
            COUNT(*) as c,
            COALESCE(SUM(r.monto_total), 0) as m,
            COALESCE(SUM(CASE WHEN r.status IN ({$paid}) THEN r.monto_total ELSE 0 END), 0) as p,
            SUM(CASE WHEN r.status IN ('CAPTURADA','PAGO_AUTORIZADO') THEN 1 ELSE 0 END) as pend,
            SUM(CASE WHEN r.status = 'POR_COMPROBAR' THEN 1 ELSE 0 END) as chk
        ")->first();

        $count = (int) ($row->c ?? 0);
        $monto = round((float) ($row->m ?? 0), 2);

        return [
            'count' => $count,
            'monto' => $monto,
            'avg' => $count ? round($monto / $count, 2) : 0.0,
            'paid' => round((float) ($row->p ?? 0), 2),
            'pending' => (int) ($row->pend ?? 0),
            'toCheck' => (int) ($row->chk ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function card(string $key, string $label, float|int $value, float|int|null $previous, string $format, string $hint): array
    {
        $delta = null;
        if ($previous !== null) {
            $delta = $previous > 0 ? round((($value - $previous) / $previous) * 100, 1) : ($value > 0 ? 100.0 : 0.0);
        }

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta' => $delta,
            'format' => $format,
            'display' => $format === 'money' ? '$'.number_format((float) $value, 2) : number_format((float) $value),
            'hint' => $hint,
        ];
    }

    /** Serie temporal: por día hasta 62 días; por mes en rangos mayores. */
    private function trend(Builder $q, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byMonth = $from->diffInDays($to) > 62;
        $bucket = $byMonth ? $this->monthExpr('r.fecha_solicitud') : 'DATE(r.fecha_solicitud)';

        $rows = $q->selectRaw("{$bucket} as b, COUNT(*) as c, COALESCE(SUM(r.monto_total), 0) as m")
            ->groupBy('b')->get()->keyBy('b');

        $points = [];
        $cursor = $byMonth ? $from->startOfMonth() : $from->startOfDay();
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $byMonth ? $cursor->format('Y-m') : $cursor->toDateString();
            $points[] = [
                'key' => $key,
                'name' => $byMonth ? ucfirst($cursor->locale('es')->isoFormat('MMM YY')) : $cursor->locale('es')->isoFormat('D MMM'),
                'count' => (int) ($rows[$key]->c ?? 0),
                'monto' => round((float) ($rows[$key]->m ?? 0), 2),
            ];
            $cursor = $byMonth ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return ['granularity' => $byMonth ? 'month' : 'day', 'points' => $points];
    }

    private function statusMix(Builder $q): array
    {
        $counts = $q->selectRaw('r.status as s, COUNT(*) as c, COALESCE(SUM(r.monto_total), 0) as m')->groupBy('s')->get()->keyBy('s');

        $out = [];
        foreach (self::STATUS_LABELS as $key => $label) {
            $out[] = ['key' => $key, 'name' => $label, 'value' => (int) ($counts[$key]->c ?? 0), 'monto' => round((float) ($counts[$key]->m ?? 0), 2)];
        }

        return $out;
    }

    /** Top por monto con el resto agrupado en "Otros". */
    private function top(Builder $q, string $table, string $fk, string $labelColumn): array
    {
        $rows = $q->leftJoin("{$table} as t", 't.id', '=', "r.{$fk}")
            ->selectRaw("r.{$fk} as id, MAX(t.{$labelColumn}) as name, COUNT(*) as c, COALESCE(SUM(r.monto_total), 0) as m")
            ->groupBy("r.{$fk}")
            ->orderByDesc('m')
            ->get();

        $items = $rows->take(self::TOP)->map(fn ($r) => [
            'id' => $r->id ? (int) $r->id : null,
            'name' => $r->name ?: 'Sin asignar',
            'value' => round((float) $r->m, 2),
            'count' => (int) $r->c,
        ])->values()->all();

        $rest = $rows->slice(self::TOP);
        if ($rest->isNotEmpty()) {
            $items[] = ['id' => null, 'name' => 'Otros ('.$rest->count().')', 'value' => round((float) $rest->sum('m'), 2), 'count' => (int) $rest->sum('c')];
        }

        return $items;
    }

    /** Últimos 12 meses hasta el fin del periodo (respeta filtros, no el periodo). */
    private function monthly(Builder $q, CarbonImmutable $to): array
    {
        $start = $to->startOfMonth()->subMonthsNoOverflow(11);
        $month = $this->monthExpr('r.fecha_solicitud');
        $rows = $q->whereBetween('r.fecha_solicitud', [$start, $to->endOfMonth()])
            ->selectRaw("{$month} as b, COUNT(*) as c, COALESCE(SUM(r.monto_total), 0) as m")
            ->groupBy('b')->get()->keyBy('b');

        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $key = $m->format('Y-m');
            $out[] = ['key' => $key, 'name' => ucfirst($m->locale('es')->isoFormat('MMM YY')), 'value' => round((float) ($rows[$key]->m ?? 0), 2), 'count' => (int) ($rows[$key]->c ?? 0)];
        }

        return $out;
    }

    private function comprobantes(Builder $scope, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = DB::table('comprobantes')
            ->whereIn('requisicion_id', $scope->select('r.id'))
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('fecha_emision', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(fn ($q2) => $q2->whereNull('fecha_emision')->whereBetween('created_at', [$from, $to]));
            })
            ->selectRaw('tipo_doc as t, COUNT(*) as c')->groupBy('t')->pluck('c', 't');

        $out = [];
        foreach (self::DOC_LABELS as $key => $label) {
            $out[] = ['key' => $key, 'name' => $label, 'value' => (int) ($counts[$key] ?? 0)];
        }

        return $out;
    }

    private function options(): array
    {
        return [
            'presets' => collect(self::PRESETS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'estatus' => collect(self::STATUS_LABELS)->map(fn ($nombre, $id) => compact('id', 'nombre'))->values(),
            'corporativos' => DB::table('corporativos')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
            'sucursales' => DB::table('sucursals')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre', 'corporativo_id']),
            'conceptos' => DB::table('conceptos')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function monthExpr(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
