<?php

namespace App\Exports\Core\Workbook;

use Carbon\CarbonImmutable;

/**
 * Agrupaciones para las tablas del resumen: cada grupo es
 * ['name' => string, 'count' => int, 'amount' => float, 'color' => ?string].
 */
final class Groups
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * Agrupa por una etiqueta libre (corporativo, proveedor, banco…).
     *
     * @param  callable(array): mixed  $label
     * @param  (callable(array): (float|int|null))|null  $amount
     * @param  'amount'|'count'|'name'  $sort
     * @return list<array{name: string, count: int, amount: float, color: ?string}>
     */
    public static function by(array $rows, callable $label, ?callable $amount = null, string $sort = 'amount', string $empty = 'Sin asignar'): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $name = trim((string) ($label($row) ?? ''));
            $name = $name === '' || $name === '—' ? $empty : $name;
            $groups[$name] ??= ['name' => $name, 'count' => 0, 'amount' => 0.0, 'color' => null];
            $groups[$name]['count']++;
            $groups[$name]['amount'] += $amount ? (float) ($amount($row) ?? 0) : 0.0;
        }

        $groups = array_values($groups);
        usort($groups, match ($sort) {
            'name' => fn ($a, $b) => strcasecmp($a['name'], $b['name']),
            'count' => fn ($a, $b) => [$b['count'], $b['amount'], $a['name']] <=> [$a['count'], $a['amount'], $b['name']],
            default => fn ($a, $b) => [$b['amount'], $b['count'], $a['name']] <=> [$a['amount'], $a['count'], $b['name']],
        });

        return array_map(fn ($g) => ['amount' => round($g['amount'], 2)] + $g, $groups);
    }

    /**
     * Agrupa por un catálogo cerrado (estatus, tipo) respetando su orden y
     * colores; los valores que no traen registros se omiten.
     *
     * @param  callable(array): mixed  $key
     * @param  array<string, string>  $labels  clave => etiqueta
     * @param  array<string, string>  $colors  clave => HEX
     * @return list<array{name: string, count: int, amount: float, color: ?string}>
     */
    public static function fixed(array $rows, callable $key, array $labels, array $colors = [], ?callable $amount = null): array
    {
        $groups = [];
        foreach ($labels as $k => $name) {
            $groups[(string) $k] = ['name' => $name, 'count' => 0, 'amount' => 0.0, 'color' => isset($colors[$k]) ? Theme::hex($colors[$k]) : null];
        }
        foreach ($rows as $row) {
            $k = (string) ($key($row) ?? '');
            $groups[$k] ??= ['name' => $k === '' ? 'Sin dato' : $k, 'count' => 0, 'amount' => 0.0, 'color' => null];
            $groups[$k]['count']++;
            $groups[$k]['amount'] += $amount ? (float) ($amount($row) ?? 0) : 0.0;
        }

        $palette = Theme::palette();
        $out = [];
        foreach (array_values(array_filter($groups, fn ($g) => $g['count'] > 0)) as $i => $g) {
            $g['color'] ??= $palette[$i % count($palette)];
            $g['amount'] = round($g['amount'], 2);
            $out[] = $g;
        }

        return $out;
    }

    /**
     * Agrupa por mes, en orden cronológico y sin huecos entre el primer y el
     * último mes con registros.
     *
     * @param  callable(array): mixed  $date  Fecha en texto (Y-m-d…, ISO) o DateTime.
     * @return list<array{name: string, count: int, amount: float, color: ?string}>
     */
    public static function months(array $rows, callable $date, ?callable $amount = null): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $d = self::date($date($row));
            if (! $d) {
                continue;
            }
            $k = $d->format('Y-m');
            $groups[$k] ??= ['count' => 0, 'amount' => 0.0];
            $groups[$k]['count']++;
            $groups[$k]['amount'] += $amount ? (float) ($amount($row) ?? 0) : 0.0;
        }
        if (! $groups) {
            return [];
        }

        ksort($groups);
        $cursor = CarbonImmutable::createFromFormat('!Y-m', array_key_first($groups));
        $end = CarbonImmutable::createFromFormat('!Y-m', array_key_last($groups));
        $out = [];
        while ($cursor <= $end) {
            $k = $cursor->format('Y-m');
            $out[] = [
                'name' => self::MONTHS[$cursor->month - 1].' '.$cursor->year,
                'count' => $groups[$k]['count'] ?? 0,
                'amount' => round($groups[$k]['amount'] ?? 0, 2),
                'color' => null,
            ];
            $cursor = $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Deja los primeros $max grupos y junta el resto en «Otros (n)».
     *
     * @param  list<array{name: string, count: int, amount: float, color: ?string}>  $groups
     */
    public static function top(array $groups, int $max = 15): array
    {
        if (count($groups) <= $max) {
            return $groups;
        }
        $head = array_slice($groups, 0, $max - 1);
        $rest = array_slice($groups, $max - 1);
        $head[] = [
            'name' => 'Otros ('.count($rest).')',
            'count' => array_sum(array_column($rest, 'count')),
            'amount' => round(array_sum(array_column($rest, 'amount')), 2),
            'color' => null,
        ];

        return $head;
    }

    /** Cantidad de valores distintos (sin vacíos). */
    public static function distinct(array $rows, callable $value): int
    {
        $set = [];
        foreach ($rows as $row) {
            $v = trim((string) ($value($row) ?? ''));
            if ($v !== '' && $v !== '—') {
                $set[mb_strtolower($v)] = true;
            }
        }

        return count($set);
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '—') {
            return null;
        }
        try {
            $d = CarbonImmutable::parse($value);
            // Las fechas ISO con zona (created_at) se muestran en hora del negocio.
            if (preg_match('/T\d{2}:\d{2}.*(Z|[+-]\d{2}:?\d{2})$/', $value)) {
                $d = $d->setTimezone(\App\Support\BusinessDate::timezone());
            }

            return $d;
        } catch (\Throwable) {
            return null;
        }
    }
}
