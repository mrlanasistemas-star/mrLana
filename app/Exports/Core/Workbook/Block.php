<?php

namespace App\Exports\Core\Workbook;

/**
 * Constructores de bloques para SummarySheet.
 *
 * Un bloque es una tabla con encabezados, formatos por columna, filas de
 * valores (la primera columna es la etiqueta), colores opcionales por fila,
 * columna de porcentaje opcional ('share' = índice de la columna base) y
 * gráfica opcional:
 *   ['type' => 'donut'|'bar'|'column'|'line', 'value' => índice | 'values' => [índices], 'title' => …]
 */
final class Block
{
    /**
     * Tabla a partir de grupos (Groups::by/fixed/months).
     *
     * @param  list<array{name: string, count: int, amount: float, color: ?string}>  $groups
     * @param  string|null  $amountLabel  Encabezado de la columna de monto; null = sin monto.
     * @param  'donut'|'bar'|'column'|'line'|null  $chart
     * @param  'count'|'amount'  $chartOn  Columna que grafica (y base del porcentaje).
     */
    public static function groups(
        string $title,
        string $label,
        array $groups,
        string $countLabel,
        ?string $amountLabel = null,
        ?string $chart = null,
        ?string $chartTitle = null,
        string $chartOn = 'amount',
        ?string $note = null,
    ): array {
        $withAmount = $amountLabel !== null;
        $chartOn = $withAmount ? $chartOn : 'count';
        $headers = [$label, $countLabel];
        $formats = [null, Theme::INT];
        if ($withAmount) {
            $headers[] = $amountLabel;
            $formats[] = Theme::MONEY;
        }

        $rows = array_map(fn ($g) => $withAmount ? [$g['name'], $g['count'], $g['amount']] : [$g['name'], $g['count']], $groups);
        $valueIndex = $chartOn === 'amount' ? 2 : 1;

        return [
            'title' => $title,
            'note' => $note,
            'headers' => $headers,
            'formats' => $formats,
            'rows' => $rows,
            'colors' => array_map(fn ($g) => $g['color'] ?? null, $groups),
            'share' => $valueIndex,
            'shareLabel' => $chartOn === 'amount' ? '% del monto' : '% del total',
            'chart' => $chart ? ['type' => $chart, 'value' => $valueIndex, 'title' => $chartTitle ?? $title] : null,
        ];
    }

    /**
     * Tabla libre.
     *
     * @param  list<string>  $headers
     * @param  list<?string>  $formats
     * @param  list<list<mixed>>  $rows
     */
    public static function table(string $title, array $headers, array $formats, array $rows, array $options = []): array
    {
        return ['title' => $title, 'headers' => $headers, 'formats' => $formats, 'rows' => $rows] + $options;
    }
}
