<?php

namespace App\Support\Reports;

use App\Services\Dashboard\DashboardDataService;
use Carbon\Carbon;

/**
 * Resumen de un conjunto de requisiciones exportadas (las filas que arma
 * RequisicionExportController::buildRows()). Lo usan el PDF y el Excel para
 * mostrar lo mismo: indicadores generales y agrupaciones por estatus,
 * solicitante, concepto, proveedor, sucursal y mes de registro.
 *
 * Criterios:
 *  - «Monto» es el total efectuado de la requisición (lo que se pide, ya con
 *    ajustes aplicados).
 *  - «Pendiente de pago» suma lo que falta por pagar de las requisiciones que
 *    siguen en camino de pagarse (excluye borradores, pagos rechazados y
 *    eliminadas).
 *  - «Pendiente de comprobar» suma lo pagado que aún no tiene comprobantes
 *    aprobados (excluye eliminadas).
 *  - Cada grupo trae: name, count, total, pagado, comprobado y pct (porcentaje
 *    del monto total). Los grupos se ordenan por monto, de mayor a menor;
 *    los estatus siguen el orden del flujo y los meses el cronológico.
 */
final class RequisicionesSummary
{
    /** Colores de estatus (los mismos de la interfaz). */
    public const STATUS_COLORS = [
        'BORRADOR' => '#94A3B8',
        'CAPTURADA' => '#0EA5E9',
        'PAGO_AUTORIZADO' => '#F59E0B',
        'PAGO_RECHAZADO' => '#F43F5E',
        'PAGADA' => '#10B981',
        'POR_COMPROBAR' => '#8B5CF6',
        'COMPROBACION_ACEPTADA' => '#14B8A6',
        'COMPROBACION_RECHAZADA' => '#D946EF',
        'ELIMINADA' => '#71717A',
    ];

    private const SIN_PAGO = ['BORRADOR', 'PAGO_RECHAZADO', 'ELIMINADA'];

    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** @var array{count: int, monto: float, pagado: float, comprobado: float, pendiente_pago: float, pendiente_comprobar: float, promedio: float, solicitantes: int, proveedores: int, pct_pagado: float, pct_comprobado: float} */
    public array $totals;

    /** @var list<array<string, mixed>> */
    public array $estatus;

    /** @var list<array<string, mixed>> */
    public array $solicitantes;

    /** @var list<array<string, mixed>> */
    public array $conceptos;

    /** @var list<array<string, mixed>> */
    public array $proveedores;

    /** @var list<array<string, mixed>> */
    public array $sucursales;

    /** @var list<array<string, mixed>> Cronológico, incluye meses sin registros dentro del rango. */
    public array $meses;

    /** @var array{desde: ?string, hasta: ?string} Fechas de registro (Y-m-d) más antigua y más reciente. */
    public array $periodo;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(array $rows)
    {
        $monto = 0.0;
        $pagado = 0.0;
        $comprobado = 0.0;
        $pendientePago = 0.0;
        $pendienteComprobar = 0.0;
        $fechas = [];

        foreach ($rows as $r) {
            $total = (float) ($r['total_efectuado'] ?? 0);
            $pag = (float) ($r['pagado'] ?? 0);
            $com = (float) ($r['comprobado'] ?? 0);

            $monto += $total;
            $pagado += $pag;
            $comprobado += $com;
            $pendientePago += self::pendientePago($r);
            $pendienteComprobar += self::pendienteComprobar($r);
            if (! empty($r['fecha_captura'])) {
                $fechas[] = substr((string) $r['fecha_captura'], 0, 10);
            }
        }

        $count = count($rows);
        sort($fechas);

        $this->solicitantes = self::group($rows, fn ($r) => $r['solicitante'] ?? null, 'Sin solicitante', $monto);
        $this->conceptos = self::group($rows, fn ($r) => $r['concepto'] ?? null, 'Sin concepto', $monto);
        $this->proveedores = self::group($rows, fn ($r) => $r['proveedor'] ?? null, 'Sin proveedor', $monto);
        $this->sucursales = self::group($rows, fn ($r) => $r['sucursal'] ?? null, 'Sin sucursal', $monto);
        $this->estatus = $this->groupStatus($rows, $monto);
        $this->meses = $this->groupMonths($rows, $monto);
        $this->periodo = ['desde' => $fechas[0] ?? null, 'hasta' => $fechas ? end($fechas) : null];

        $this->totals = [
            'count' => $count,
            'monto' => round($monto, 2),
            'pagado' => round($pagado, 2),
            'comprobado' => round($comprobado, 2),
            'pendiente_pago' => round($pendientePago, 2),
            'pendiente_comprobar' => round($pendienteComprobar, 2),
            'promedio' => $count > 0 ? round($monto / $count, 2) : 0.0,
            'solicitantes' => count(array_filter($this->solicitantes, fn ($g) => ! $g['vacio'])),
            'proveedores' => count(array_filter($this->proveedores, fn ($g) => ! $g['vacio'])),
            'pct_pagado' => $monto > 0 ? round($pagado / $monto * 100, 1) : 0.0,
            'pct_comprobado' => $pagado > 0 ? round($comprobado / $pagado * 100, 1) : 0.0,
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    /**
     * Los primeros $n grupos y el resto acumulado en «Otros».
     *
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    public static function top(array $groups, int $n, string $otros = 'Otros'): array
    {
        if (count($groups) <= $n) {
            return $groups;
        }

        $head = array_slice($groups, 0, $n);
        $rest = array_slice($groups, $n);
        $head[] = [
            'name' => $otros.' ('.count($rest).')',
            'count' => array_sum(array_column($rest, 'count')),
            'total' => round(array_sum(array_column($rest, 'total')), 2),
            'pagado' => round(array_sum(array_column($rest, 'pagado')), 2),
            'comprobado' => round(array_sum(array_column($rest, 'comprobado')), 2),
            'pct' => round(array_sum(array_column($rest, 'pct')), 1),
            'vacio' => false,
            'otros' => true,
        ];

        return $head;
    }

    /** Lo que falta por pagar de una requisición que sigue en camino de pagarse. */
    public static function pendientePago(array $r): float
    {
        if (in_array((string) ($r['estatus'] ?? ''), self::SIN_PAGO, true)) {
            return 0.0;
        }

        return round(max(0, (float) ($r['total_efectuado'] ?? 0) - (float) ($r['pagado'] ?? 0)), 2);
    }

    /** Lo pagado que aún no tiene comprobantes aprobados. */
    public static function pendienteComprobar(array $r): float
    {
        if (($r['estatus'] ?? '') === 'ELIMINADA') {
            return 0.0;
        }

        return round(max(0, (float) ($r['pagado'] ?? 0) - (float) ($r['comprobado'] ?? 0)), 2);
    }

    /** ¿Abarca más de un mes de registro? */
    public function spansMonths(): bool
    {
        return count($this->meses) > 1;
    }

    /** Texto legible del periodo, p. ej. «12 feb 2026 al 7 oct 2026». */
    public function periodoLabel(): ?string
    {
        if (! $this->periodo['desde']) {
            return null;
        }
        $fmt = function (string $ymd) {
            $d = Carbon::parse($ymd);

            return $d->day.' '.self::MESES[$d->month - 1].' '.$d->year;
        };

        return $this->periodo['desde'] === $this->periodo['hasta']
            ? $fmt($this->periodo['desde'])
            : $fmt($this->periodo['desde']).' al '.$fmt($this->periodo['hasta']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function group(array $rows, callable $key, string $empty, float $monto): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $name = trim((string) ($key($r) ?? ''));
            $vacio = $name === '';
            $name = $vacio ? $empty : $name;
            $k = mb_strtolower($name);
            $groups[$k] ??= ['name' => $name, 'count' => 0, 'total' => 0.0, 'pagado' => 0.0, 'comprobado' => 0.0, 'pct' => 0.0, 'vacio' => $vacio];
            self::accumulate($groups[$k], $r);
        }

        $groups = array_values(array_map(fn ($g) => self::finish($g, $monto), $groups));
        usort($groups, fn ($a, $b) => [$b['total'], $b['count'], $a['name']] <=> [$a['total'], $a['count'], $b['name']]);

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupStatus(array $rows, float $monto): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $status = (string) ($r['estatus'] ?? '');
            $groups[$status] ??= [
                'key' => $status,
                'name' => DashboardDataService::STATUS_LABELS[$status] ?? ($status !== '' ? ucfirst(mb_strtolower(str_replace('_', ' ', $status))) : 'Sin estatus'),
                'color' => self::STATUS_COLORS[$status] ?? '#A1A1AA',
                'count' => 0, 'total' => 0.0, 'pagado' => 0.0, 'comprobado' => 0.0, 'pct' => 0.0, 'vacio' => false,
            ];
            self::accumulate($groups[$status], $r);
        }

        // Orden del flujo de la requisición.
        $order = array_flip(array_keys(self::STATUS_COLORS));
        uksort($groups, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return array_values(array_map(fn ($g) => self::finish($g, $monto), $groups));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupMonths(array $rows, float $monto): array
    {
        $groups = [];
        foreach ($rows as $r) {
            if (empty($r['fecha_captura'])) {
                continue;
            }
            $ym = substr((string) $r['fecha_captura'], 0, 7);
            $groups[$ym] ??= ['key' => $ym, 'name' => '', 'count' => 0, 'total' => 0.0, 'pagado' => 0.0, 'comprobado' => 0.0, 'pct' => 0.0, 'vacio' => false];
            self::accumulate($groups[$ym], $r);
        }
        if ($groups === []) {
            return [];
        }

        ksort($groups);
        $keys = array_keys($groups);
        $cursor = Carbon::createFromFormat('Y-m-d', $keys[0].'-01')->startOfMonth();
        $last = Carbon::createFromFormat('Y-m-d', end($keys).'-01')->startOfMonth();
        $sameYear = $cursor->year === $last->year;

        $out = [];
        while ($cursor->lte($last)) {
            $ym = $cursor->format('Y-m');
            $g = $groups[$ym] ?? ['key' => $ym, 'count' => 0, 'total' => 0.0, 'pagado' => 0.0, 'comprobado' => 0.0, 'pct' => 0.0, 'vacio' => false];
            $g['name'] = self::MESES[$cursor->month - 1].($sameYear ? '' : ' '.$cursor->format('y'));
            $g['label'] = self::MESES[$cursor->month - 1].' '.$cursor->year;
            $out[] = self::finish($g, $monto);
            $cursor->addMonth();
        }

        return $out;
    }

    private static function accumulate(array &$g, array $r): void
    {
        $g['count']++;
        $g['total'] += (float) ($r['total_efectuado'] ?? 0);
        $g['pagado'] += (float) ($r['pagado'] ?? 0);
        $g['comprobado'] += (float) ($r['comprobado'] ?? 0);
    }

    private static function finish(array $g, float $monto): array
    {
        $g['total'] = round($g['total'], 2);
        $g['pagado'] = round($g['pagado'], 2);
        $g['comprobado'] = round($g['comprobado'], 2);
        $g['pct'] = $monto > 0 ? round($g['total'] / $monto * 100, 1) : 0.0;

        return $g;
    }
}
