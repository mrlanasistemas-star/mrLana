<?php

namespace App\Support\Requisiciones;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Limpia los items capturados y calcula subtotal, IVA (16 %) y total con la
 * misma regla para el alta, la edición de borradores y la corrección
 * administrativa. Los montos nunca se toman del cliente.
 */
final class DetallesCalculator
{
    public const IVA_RATE = 0.16;

    /**
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}  [items limpios, subtotal, total]
     */
    public static function sanitize(array $detalles): array
    {
        $montoSubtotal = 0.0;
        $montoTotal = 0.0;
        $clean = [];

        $hasGeneraIvaColumn = Schema::hasColumn('detalles', 'genera_iva');

        foreach (array_values($detalles) as $i => $d) {
            $cantidad = (float) ($d['cantidad'] ?? 0);
            $precio = (float) ($d['precio_unitario'] ?? 0);
            $desc = trim((string) ($d['descripcion'] ?? ''));

            if ($cantidad <= 0 || $desc === '') {
                throw ValidationException::withMessages([
                    "detalles.{$i}.descripcion" => 'Cada item debe tener descripción y cantidad mayor a 0.',
                ]);
            }

            $generaIva = (bool) ($d['genera_iva'] ?? true);
            $subtotal = round($cantidad * $precio, 2);
            $iva = $generaIva ? round($subtotal * self::IVA_RATE, 2) : 0.00;
            $total = round($subtotal + $iva, 2);

            $montoSubtotal += $subtotal;
            $montoTotal += $total;

            $row = [
                'sucursal_id' => ! empty($d['sucursal_id']) ? (int) $d['sucursal_id'] : null,
                'cantidad' => $cantidad,
                'descripcion' => $desc,
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
            ];

            if ($hasGeneraIvaColumn) {
                $row['genera_iva'] = $generaIva;
            }

            $clean[] = $row;
        }

        return [$clean, round($montoSubtotal, 2), round($montoTotal, 2)];
    }
}
