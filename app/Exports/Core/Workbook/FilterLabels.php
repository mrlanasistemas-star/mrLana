<?php

namespace App\Exports\Core\Workbook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Traduce los parámetros de los listados de catálogos a filtros legibles. */
final class FilterLabels
{
    /**
     * @param  array<string, string>  $sorts  campo => etiqueta legible
     * @param  array{0: string, 1: string}  $words  [activos, de baja]
     * @return array<string, ?string>
     */
    public static function catalog(Request $request, array $sorts, array $words = ['Activos', 'De baja'], string $statusParam = 'activo', bool $withCorporativo = false): array
    {
        $q = trim((string) $request->get('q', ''));
        $status = strtolower(trim((string) $request->get($statusParam, 'all')));
        $out = [];

        if ($withCorporativo && ($id = (int) $request->get('corporativo_id'))) {
            $out['Corporativo'] = DB::table('corporativos')->where('id', $id)->value('nombre') ?? "#{$id}";
        }
        $out['Búsqueda'] = $q !== '' ? "«{$q}»" : null;
        $out['Estatus'] = match ($status) {
            '1', 'true', 'activo' => 'Solo '.mb_strtolower($words[0]),
            '0', 'false', 'inactivo', 'baja' => 'Solo '.mb_strtolower($words[1]),
            default => null,
        };
        $out['Orden'] = self::order($request, $sorts);

        return $out;
    }

    /** «Nombre (A a Z)», «Fecha de alta (más reciente primero)»… */
    public static function order(Request $request, array $sorts): ?string
    {
        $sort = (string) $request->get('sort', '');
        if ($sort === '') {
            return null;
        }
        $dir = strtolower((string) $request->get('dir', 'asc'));
        // Corporativos usa «nombre_asc», «id_desc».
        if (preg_match('/^(.+)_(asc|desc)$/', $sort, $m) && ! isset($sorts[$sort])) {
            [$sort, $dir] = [$m[1], $m[2]];
        }
        if (! isset($sorts[$sort])) {
            return null;
        }
        $isDate = str_contains($sort, '_at') || str_contains($sort, 'fecha');
        $isText = ! $isDate && ! in_array($sort, ['id', 'activo', 'status'], true);
        $how = match (true) {
            $isDate => $dir === 'desc' ? 'más reciente primero' : 'más antiguo primero',
            $isText => $dir === 'desc' ? 'Z a A' : 'A a Z',
            $sort === 'id' => $dir === 'desc' ? 'más reciente primero' : 'más antiguo primero',
            default => $dir === 'desc' ? 'descendente' : 'ascendente',
        };

        return "{$sorts[$sort]} ({$how})";
    }
}
