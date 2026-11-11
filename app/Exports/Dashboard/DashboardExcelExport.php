<?php

namespace App\Exports\Dashboard;

use App\Exports\Core\Workbook\Block;
use App\Exports\Core\Workbook\DataSheet;
use App\Exports\Core\Workbook\FiltersSheet;
use App\Exports\Core\Workbook\SummarySheet;
use App\Exports\Core\Workbook\Theme;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

/**
 * Libro del dashboard: «Resumen» con los indicadores del periodo, la
 * comparación con el periodo anterior y tablas con gráficas nativas
 * (tendencia, estatus, concepto, sucursal, proveedor, 12 meses y
 * comprobantes); «Tendencia» con la serie del periodo y «Filtros».
 *
 * Recibe el arreglo de DashboardDataService::build() sin transformar.
 */
class DashboardExcelExport implements WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    /**
     * @param  array<string, mixed>  $data  Resultado de DashboardDataService::build().
     * @param  array<string, string>  $filters  Filtros legibles.
     * @param  array<string, mixed>  $meta  generated_at, generated_by.
     * @param  bool  $includeMixes  Estatus y comprobantes (no en la vista personal).
     */
    public function __construct(
        private array $data,
        private array $filters = [],
        private array $meta = [],
        private bool $includeMixes = true,
    ) {
        $this->filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }

    public function sheets(): array
    {
        $period = $this->data['period'] ?? [];
        $meta = $this->meta + [
            'subtitle' => trim(($this->data['subheadline'] ?? '').' Periodo: '.($period['label'] ?? '').' ('.($period['from'] ?? '').' al '.($period['to'] ?? '').').'),
        ];
        $granularity = ($this->data['trend']['granularity'] ?? 'day') === 'month' ? 'Mes' : 'Día';

        return [
            new SummarySheet($this->data['headline'] ?? 'Dashboard', $this->kpis(), $this->blocks(), $this->filters, $meta),
            new DataSheet('Tendencia', 'Tendencia del periodo ('.mb_strtolower($granularity === 'Mes' ? 'por mes' : 'por día').')', [
                ['label' => $granularity, 'value' => 'name', 'width' => 14],
                ['label' => 'Requisiciones', 'value' => 'count', 'type' => 'int', 'width' => 15, 'total' => true],
                ['label' => 'Monto solicitado', 'value' => 'monto', 'type' => 'money', 'width' => 18, 'total' => true],
            ], $this->data['trend']['points'] ?? [], 'periodos'),
            new FiltersSheet($this->filters, array_filter([
                'Reporte' => $this->data['headline'] ?? null,
                'Alcance' => $this->data['subheadline'] ?? null,
                'Periodo comparado' => $period['compare'] ?? null,
                'Generado' => $this->meta['generated_at'] ?? null,
                'Generado por' => $this->meta['generated_by'] ?? null,
                'Nota' => 'Las requisiciones eliminadas no suman a montos ni conteos, salvo que se filtre por ese estatus.',
            ])),
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => (string) config('app.name', 'ERP'),
            'title' => (string) ($this->data['headline'] ?? 'Dashboard'),
        ];
    }

    private function kpis(): array
    {
        return array_map(function (array $c) {
            $hint = $c['hint'] ?? '';
            if (($c['delta'] ?? null) !== null) {
                $sign = $c['delta'] > 0 ? '+' : '';
                $hint .= ' · '.$sign.number_format((float) $c['delta'], 1).'% vs. periodo anterior';
            }

            return [
                'label' => $c['label'],
                'value' => $c['value'],
                'format' => ($c['format'] ?? '') === 'money' ? Theme::MONEY : Theme::INT,
                'hint' => $hint,
            ];
        }, $this->data['cards'] ?? []);
    }

    private function blocks(): array
    {
        $d = $this->data;
        $blocks = [];

        // Comparación con el periodo anterior (solo indicadores con referencia).
        $compare = array_values(array_filter($d['cards'] ?? [], fn ($c) => ($c['previous'] ?? null) !== null));
        if ($compare) {
            $rows = [];
            $rowFormats = [];
            foreach ($compare as $c) {
                $f = ($c['format'] ?? '') === 'money' ? Theme::MONEY : Theme::INT;
                $rows[] = [$c['label'], $c['value'], $c['previous'], '=IF(C{r}=0,"",B{r}/C{r}-1)'];
                $rowFormats[] = [1 => $f, 2 => $f];
            }
            $blocks[] = Block::table('Comparación con el periodo anterior', ['Indicador', 'Periodo actual', 'Periodo anterior', 'Variación'], [null, Theme::INT, Theme::INT, Theme::PCT], $rows, [
                'rowFormats' => $rowFormats,
                'total' => false,
                'note' => 'Periodo anterior: '.($d['period']['compare'] ?? '').'.',
            ]);
        }

        $trend = array_map(fn ($p) => ['name' => $p['name'], 'count' => $p['count'], 'amount' => $p['monto'], 'color' => null], $d['trend']['points'] ?? []);
        $monthTrend = ($d['trend']['granularity'] ?? 'day') === 'month';
        $blocks[] = Block::groups($monthTrend ? 'Tendencia por mes' : 'Tendencia por día', $monthTrend ? 'Mes' : 'Día', $trend, 'Requisiciones', 'Monto', 'column', 'Monto solicitado en el periodo');

        if ($this->includeMixes) {
            $status = [];
            foreach ($d['statusMix'] ?? [] as $s) {
                if (($s['value'] ?? 0) > 0) {
                    $status[] = ['name' => $s['name'], 'count' => $s['value'], 'amount' => $s['monto'] ?? 0, 'color' => Theme::REQUISICION_STATUS[$s['key'] ?? ''] ?? null];
                }
            }
            $blocks[] = Block::groups('Por estatus', 'Estatus', $status, 'Requisiciones', 'Monto', 'donut', 'Requisiciones por estatus', 'count');
        }

        $top = fn (array $items) => array_map(fn ($i) => ['name' => $i['name'], 'count' => $i['count'], 'amount' => $i['value'], 'color' => null], $items);
        $blocks[] = Block::groups('Por concepto', 'Concepto', $top($d['byConcepto'] ?? []), 'Requisiciones', 'Monto', 'bar', 'Conceptos con mayor gasto');
        if (! empty($d['bySucursal'])) {
            $blocks[] = Block::groups('Por sucursal', 'Sucursal', $top($d['bySucursal']), 'Requisiciones', 'Monto', 'bar', 'Gasto por sucursal');
        }
        $blocks[] = Block::groups('Por proveedor', 'Proveedor', $top($d['byProveedor'] ?? []), 'Requisiciones', 'Monto', 'bar', 'Proveedores con mayor gasto');

        if (! empty($d['monthly'])) {
            $blocks[] = Block::groups('Últimos 12 meses', 'Mes', $top($d['monthly']), 'Requisiciones', 'Monto', 'column', 'Gasto mensual (12 meses)',
                note: 'Respeta los filtros, no el periodo seleccionado.');
        }

        if ($this->includeMixes) {
            $palette = Theme::palette();
            $docs = [];
            foreach (array_values($d['comprobantesMix'] ?? []) as $i => $c) {
                if (($c['value'] ?? 0) > 0) {
                    $docs[] = ['name' => $c['name'], 'count' => $c['value'], 'amount' => 0, 'color' => $palette[$i % count($palette)]];
                }
            }
            $blocks[] = Block::groups('Comprobantes del periodo', 'Tipo de documento', $docs, 'Comprobantes', null, 'donut', 'Comprobantes por tipo');
        }

        return $blocks;
    }
}
