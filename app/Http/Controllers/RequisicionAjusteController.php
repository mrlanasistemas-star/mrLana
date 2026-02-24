<?php

namespace App\Http\Controllers;

use App\Http\Requests\Requisiciones\AjusteStoreRequest;
use App\Http\Requests\Requisiciones\AjusteReviewRequest;
use App\Models\Ajuste;
use App\Models\Requisicion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RequisicionAjusteController extends Controller {

    private function role(): string {
        $u = auth()->user();
        return strtoupper(trim((string) ($u->rol ?? $u->role ?? 'COLABORADOR')));
    }

    private function isAdminOrContador(): bool {
        return in_array($this->role(), ['ADMIN', 'CONTADOR'], true);
    }

    /**
     * Crea ajuste (PENDIENTE).
     * Importante: en tu BD NO existen "descripcion" ni "fecha"; existen "motivo" y "fecha_registro".
     * Pero aceptamos ambos para no romper UI.
     */
    public function store(AjusteStoreRequest $request, Requisicion $requisicion): RedirectResponse
    {
        $data = $request->validated();

        $tipo = strtoupper((string) ($data['tipo'] ?? ''));
        $delta = (float) ($data['monto'] ?? 0);

        // Guardrail mínimo (por si el request deja pasar vacío)
        if ($delta <= 0) {
            return back()->withErrors(['monto' => 'El monto debe ser mayor a 0.'])->withInput();
        }

        // Fecha registro obligatoria en BD
        $fechaRegistro = $data['fecha_registro'] ?? $data['fecha'] ?? null;
        $fechaRegistro = $fechaRegistro
            ? Carbon::parse($fechaRegistro)->startOfDay()
            : now();

        // sentido NOT NULL en migración
        $sentido = strtoupper((string) ($data['sentido'] ?? ''));
        if (!in_array($sentido, ['A_FAVOR_EMPRESA', 'A_FAVOR_SOLICITANTE'], true)) {
            // default por tipo
            $sentido = ($tipo === 'DEVOLUCION') ? 'A_FAVOR_EMPRESA' : 'A_FAVOR_SOLICITANTE';
        }

        // Signo del impacto (solo informativo aquí; el apply lo recalcula con lock)
        $signo = ($sentido === 'A_FAVOR_EMPRESA') ? -1 : 1;

        $anterior = (float) $requisicion->monto_total;
        $nuevo = $anterior + ($signo * $delta);

        // UI manda "descripcion"; BD es "motivo"
        $motivo = $data['descripcion'] ?? $data['motivo'] ?? null;

        Ajuste::create([
            'requisicion_id'   => $requisicion->id,
            'tipo'             => $tipo,
            'sentido'          => $sentido,
            'monto'            => $delta,
            'monto_anterior'   => $anterior,
            'monto_nuevo'      => $nuevo,
            'estatus'          => 'PENDIENTE',

            'metodo'           => $data['metodo'] ?? null,
            'referencia'       => $data['referencia'] ?? null,
            'motivo'           => $motivo,
            'notas'            => $data['notas'] ?? null,

            'fecha_registro'   => $fechaRegistro,
            'user_registro_id' => auth()->id(),
        ]);

        return back()->with('success', 'Ajuste creado y enviado a revisión.');
    }

    /**
     * Admin/Contador aprueba o rechaza.
     * Guarda comentario (si llega) en "notas".
     */
    public function review(AjusteReviewRequest $request, Ajuste $ajuste): RedirectResponse
    {
        abort_unless($this->isAdminOrContador(), 403);

        if ($ajuste->estatus !== 'PENDIENTE') {
            return back()->with('error', 'Solo se puede revisar un ajuste PENDIENTE.');
        }

        $data = $request->validated();
        $accion = strtoupper((string) ($data['accion'] ?? ''));

        $ajuste->estatus = ($accion === 'APROBAR') ? 'APROBADO' : 'RECHAZADO';
        $ajuste->user_resuelve_id = auth()->id();
        $ajuste->fecha_resolucion = now();

        // Comentario de rechazo/aprobación (opcional)
        if (!empty($data['comentario_revision'])) {
            $ajuste->notas = trim((string) $data['comentario_revision']);
        }

        $ajuste->save();

        return back()->with('success', 'Revisión aplicada.');
    }

    /**
     * Aplica ajuste (solo APROBADO).
     * Por negocio: DEVOLUCION = baja prioridad -> NO toca monto_total.
     */
    public function apply(Ajuste $ajuste): RedirectResponse
    {
        abort_unless($this->isAdminOrContador(), 403);

        if ($ajuste->estatus === 'APLICADO') {
            return back()->with('error', 'Este ajuste ya fue aplicado.');
        }

        if ($ajuste->estatus !== 'APROBADO') {
            return back()->with('error', 'Solo se puede aplicar un ajuste APROBADO.');
        }

        DB::transaction(function () use ($ajuste) {
            $req = Requisicion::query()
                ->whereKey($ajuste->requisicion_id)
                ->lockForUpdate()
                ->firstOrFail();

            // DEVOLUCION: no es core en tu flujo -> solo marcar aplicado
            if ($ajuste->tipo !== 'DEVOLUCION') {
                $signo = ($ajuste->sentido === 'A_FAVOR_EMPRESA') ? -1 : 1;

                $actual = (float) $req->monto_total;
                $nuevo = $actual + ($signo * (float) $ajuste->monto);

                // Recalcula auditoría con el valor real al momento de aplicar
                $ajuste->monto_anterior = $actual;
                $ajuste->monto_nuevo = $nuevo;

                $req->monto_total = $nuevo;
                $req->save();

                $this->syncComprobacionStatus($req);
            }

            $ajuste->estatus = 'APLICADO';
            $ajuste->save();
        });

        return back()->with('success', 'Ajuste aplicado correctamente.');
    }

    /**
     * Cancela ajuste (solo PENDIENTE).
     * Permitido: dueño (user_registro_id) o Admin/Contador
     */
    public function cancel(Ajuste $ajuste): RedirectResponse
    {
        $isOwner = (int) $ajuste->user_registro_id === (int) auth()->id();
        abort_unless($isOwner || $this->isAdminOrContador(), 403);

        if ($ajuste->estatus !== 'PENDIENTE') {
            return back()->with('error', 'Solo se puede cancelar un ajuste PENDIENTE.');
        }

        $ajuste->estatus = 'CANCELADO';
        $ajuste->save();

        return back()->with('success', 'Ajuste cancelado.');
    }

    /**
     * Mantén status de comprobación alineado al total.
     * (No toca requisiciones fuera de flujo)
     */
    private function syncComprobacionStatus(Requisicion $req): void
    {
        if (!in_array($req->status, ['POR_COMPROBAR', 'COMPROBACION_ACEPTADA', 'COMPROBACION_RECHAZADA'], true)) {
            return;
        }

        // Nota: asumo que existe relación comprobantes() en tu modelo Requisicion
        $aprobados = (float) $req->comprobantes()
            ->where('estatus', 'APROBADO')
            ->sum('monto');

        $total = (float) $req->monto_total;

        $req->status = ($aprobados >= $total) ? 'COMPROBACION_ACEPTADA' : 'POR_COMPROBAR';
        $req->save();
    }

}
