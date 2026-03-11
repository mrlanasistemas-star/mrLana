<?php

namespace App\Exports\Sheets;

use App\Exports\Core\BaseReportExport;
// No necesitamos implementar FromArray aquí porque la clase base lo hace

/**
 * Hoja de datos para el reporte de requisiciones.
 *
 * Extiende BaseReportExport para heredar el manejo de meta y filtros.
 * Las propiedades $rows y $filters son protegidas para que sean
 * compatibles con las visibilidades del padre.  Esta versión
 * omite la columna "Tipo" y "Corporativo" e incluye una columna
 * de "Fecha pago".
 */
class RequisicionesDataSheet extends BaseReportExport
{
    protected array $rows;
    protected array $filters;

    public function __construct(array $rows, array $filters, array $meta)
    {
        parent::__construct($rows, $filters, $meta);
        $this->rows    = $rows;
        $this->filters = $filters;
        // $meta es manejado por la clase base
    }

    /**
     * Encabezados de la hoja de Excel.  No se incluye "Tipo" ni
     * "Corporativo" para simplificar la tabla; se añade "Fecha pago".
     */
    public function headings(): array
    {
        // Encabezados claros y concisos. Se eliminan columnas repetidas como
        // "Comprador" y "Corporativo" y se dejan solo los datos
        // relevantes para cada ítem: folio, proveedor, concepto, descripción del
        // artículo, cantidad, precio unitario, impuestos del ítem y los montos
        // globales de la requisición.
        return [
            'Folio',
            'Proveedor',
            'Concepto',
            'Descripción',
            'Cantidad',
            'P. unitario',
            'IVA ítem',
            'Total ítem',
            'Subtotal requisición',
            'IVA requisición',
            'Total requisición',
            'Fecha captura',
        ];
    }

    // Eliminamos la implementación de array(); el método array() de la clase base
    // ya incluye meta, filtros y encabezados.  Nos limitamos a mapear cada fila
    // mediante mapRow().

    /**
     * Anchos de columna personalizados para que la hoja sea legible.
     */
    public function columnWidths(): array
    {
        // Asignamos anchos proporcionales para la nueva disposición de columnas.
        return [
            'A' => 12,  // Folio
            'B' => 26,  // Proveedor
            'C' => 22,  // Concepto
            'D' => 40,  // Descripción
            'E' => 12,  // Cantidad
            'F' => 16,  // P. unitario
            'G' => 14,  // IVA ítem
            'H' => 16,  // Total ítem
            'I' => 18,  // Subtotal requisición
            'J' => 18,  // IVA requisición
            'K' => 18,  // Total requisición
            'L' => 18,  // Fecha captura
        ];
    }

    /**
     * Satisface el método abstracto `mapRow` definido en la clase base.
     *
     * Dado que este export utiliza `FromArray` para entregar todas las
     * filas de una vez, podemos simplemente devolver la fila tal cual.
     *
     * @param array $row
     * @return array
     */
    protected function mapRow(array $r): array
    {
        // Reordenamos los campos para coincidir con los nuevos encabezados.
        return [
            $r['folio']          ?? '',
            $r['proveedor']      ?? '',
            $r['concepto']       ?? '',
            $r['descripcion_item'] ?? '',
            $r['cantidad']       ?? '',
            $r['precio_unitario'] ?? '',
            $r['iva_item']       ?? '',
            $r['total_item']     ?? '',
            $r['subtotal']       ?? '',
            $r['iva']            ?? '',
            $r['total']          ?? '',
            $r['fecha_captura']  ?? '',
        ];
    }
}
