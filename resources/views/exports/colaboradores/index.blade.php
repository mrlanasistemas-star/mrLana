@extends('exports.layouts.base-report')

@section('content')
    <table>
        <thead>
        <tr>
            <th style="width: 26px;">#</th>
            <th>Empleado</th>
            <th>Corporativo</th>
            <th>Sucursal</th>
            <th>Área</th>
            <th>Correo</th>
            <th style="width: 70px;">Estatus</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $r['empleado'] ?? '—' }}</strong><br>
                    <span class="muted">{{ $r['puesto'] ?? '—' }}</span>
                </td>
                <td>{{ $r['corporativo'] ?? '—' }}</td>
                <td>{{ $r['sucursal'] ?? '—' }}</td>
                <td>{{ $r['area'] ?? '—' }}</td>
                <td>{{ $r['correo'] ?? '—' }}</td>
                <td>
                    @if(($r['activo'] ?? false) == true)
                        <span class="badge badge-ok">Activo</span>
                    @else
                        <span class="badge badge-off">Inactivo</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="muted" style="text-align:center; padding: 18px;">
                    No hay registros con los filtros actuales.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:10px" class="muted">
        Total registros: <strong>{{ $totals['total'] ?? count($rows) }}</strong>
    </div>
@endsection
