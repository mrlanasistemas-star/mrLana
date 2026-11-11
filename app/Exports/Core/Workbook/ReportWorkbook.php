<?php

namespace App\Exports\Core\Workbook;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithProperties;

/**
 * Libro de Excel de un módulo: «Resumen» (indicadores, tablas y gráficas
 * nativas), hoja de datos con formato y «Filtros».
 *
 * WithPreCalculateFormulas es necesario para las gráficas: PhpSpreadsheet
 * solo guarda los valores de cada serie cuando precalcula; sin eso las
 * gráficas abren vacías.
 *
 * No sustituye a BaseReportExport (que sigue usando el Excel de requisiciones).
 */
abstract class ReportWorkbook implements WithMultipleSheets, WithPreCalculateFormulas, WithProperties
{
    /** @var array<string, string> */
    protected array $filters;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters  Etiqueta => valor legible; los vacíos se omiten.
     */
    public function __construct(protected array $rows, array $filters = [], protected array $meta = [])
    {
        // «2026-11-11 12:15» → «11/11/2026 12:15».
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(.*)$/', (string) ($this->meta['generated_at'] ?? ''), $m)) {
            $this->meta['generated_at'] = "{$m[3]}/{$m[2]}/{$m[1]}{$m[4]}";
        }

        $this->filters = [];
        foreach ($filters as $k => $v) {
            $v = is_array($v) ? implode(', ', array_filter($v)) : trim((string) $v);
            // Fechas de filtro en formato local (2026-11-01 → 01/11/2026).
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
                $v = "{$m[3]}/{$m[2]}/{$m[1]}";
            }
            if ($v !== '') {
                $this->filters[(string) $k] = $v;
            }
        }
    }

    /** Título grande de la hoja Resumen. */
    abstract protected function heading(): string;

    /** @return list<array<string, mixed>> */
    abstract protected function kpis(): array;

    /** @return list<array<string, mixed>> */
    abstract protected function blocks(): array;

    /** Nombre de la pestaña de datos (máx. 31 caracteres). */
    abstract protected function dataTitle(): string;

    /** @return list<array<string, mixed>> */
    abstract protected function columns(): array;

    /** Sustantivo en plural para los textos («áreas», «pagos»…). */
    protected function noun(): string
    {
        return 'registros';
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->heading(), $this->kpis(), $this->blocks(), $this->filters, $this->meta),
            new DataSheet($this->dataTitle(), $this->meta['title'] ?? $this->dataTitle(), $this->columns(), $this->rows, $this->noun()),
            new FiltersSheet($this->filters, array_filter([
                'Reporte' => $this->meta['title'] ?? null,
                'Generado' => $this->meta['generated_at'] ?? null,
                'Generado por' => $this->meta['generated_by'] ?? null,
                'Registros exportados' => number_format(count($this->rows)),
                'Nota' => 'Los totales de la hoja de datos usan SUBTOTAL: al filtrar solo suman lo visible.',
            ])),
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => (string) config('app.name', 'ERP'),
            'title' => (string) ($this->meta['title'] ?? $this->heading()),
            'description' => (string) ($this->meta['subtitle'] ?? ''),
        ];
    }

    /**
     * Tabla «activos / de baja» agrupada (por corporativo, sucursal…) con
     * barras apiladas.
     *
     * @param  callable(array): mixed  $group
     * @param  callable(array): bool  $active
     */
    protected function activeBreakdown(string $title, string $label, callable $group, callable $active, string $countLabel, string $chartTitle, array $words = ['Activos', 'De baja'], int $max = 15): array
    {
        $groups = [];
        foreach ($this->rows as $r) {
            $name = trim((string) ($group($r) ?? '')) ?: 'Sin asignar';
            $groups[$name] ??= [$name, 0, 0, 0];
            $groups[$name][1]++;
            $active($r) ? $groups[$name][2]++ : $groups[$name][3]++;
        }
        $rows = array_values($groups);
        usort($rows, fn ($a, $b) => [$b[1], $a[0]] <=> [$a[1], $b[0]]);
        if (count($rows) > $max) {
            $rest = array_slice($rows, $max - 1);
            $rows = array_slice($rows, 0, $max - 1);
            $rows[] = ['Otros ('.count($rest).')', array_sum(array_column($rest, 1)), array_sum(array_column($rest, 2)), array_sum(array_column($rest, 3))];
        }

        return Block::table($title, [$label, $countLabel, $words[0], $words[1]], [null, Theme::INT, Theme::INT, Theme::INT], $rows, [
            'share' => 1,
            'chart' => count($rows) > 1 ? ['type' => 'bar', 'values' => [2, 3], 'stacked' => true, 'title' => $chartTitle, 'colors' => [Theme::ACTIVE, Theme::INACTIVE]] : null,
        ]);
    }

    /** Bloque por estatus activo / de baja. */
    protected function activeStatus(callable $active, string $countLabel, array $words = ['Activos', 'De baja'], ?string $chartTitle = null): array
    {
        $groups = Groups::fixed($this->rows, fn ($r) => $active($r) ? '1' : '0', ['1' => $words[0], '0' => $words[1]], ['1' => Theme::ACTIVE, '0' => Theme::INACTIVE]);

        return Block::groups('Por estatus', 'Estatus', $groups, $countLabel, null, 'donut', $chartTitle ?? $countLabel.' por estatus');
    }

    /** Porcentaje legible para las notas de los indicadores. */
    protected static function pct(float|int $part, float|int $whole): string
    {
        return $whole > 0 ? number_format($part / $whole * 100, 1).'%' : '0%';
    }

    protected function count(callable $fn): int
    {
        return count(array_filter($this->rows, $fn));
    }

    protected function sum(callable $fn): float
    {
        return round(array_sum(array_map(fn ($r) => (float) ($fn($r) ?? 0), $this->rows)), 2);
    }
}
