<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Models\Requisicion;
use App\Models\RequisicionEliminacionSolicitud;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Solicitudes de eliminación de requisiciones.
 *
 * Un colaborador solicita eliminar una requisición en estatus CAPTURADA (aún
 * no autorizada ni pagada); Contabilidad la autoriza o rechaza. Al autorizar
 * se vuelve a verificar el estatus dentro de una transacción con bloqueo.
 */
class RequisicionEliminacionController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function store(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('view', $requisicion)) {
            abort(403);
        }

        if ($requisicion->status !== 'CAPTURADA') {
            return back()->with('error', 'Solo se puede solicitar la eliminación de requisiciones capturadas que aún no se han autorizado ni pagado.');
        }

        $this->authorize('requestDeletion', $requisicion);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'motivo.required' => 'Explica por qué se debe eliminar la requisición.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no debe exceder 2,000 caracteres.',
        ]);

        RequisicionEliminacionSolicitud::create([
            'requisicion_id' => $requisicion->id,
            'solicitado_por_id' => $user->id,
            'motivo' => trim($data['motivo']),
            'estatus' => RequisicionEliminacionSolicitud::PENDIENTE,
        ]);

        $this->notifications->notify(
            topic: NotificationTopic::Requisiciones,
            event: 'requisicion.eliminacion_solicitada',
            title: "Solicitud de eliminación: {$requisicion->folio}",
            message: "{$user->name} solicita eliminar la requisición. Motivo: ".str($data['motivo'])->limit(140),
            severity: 'warning',
            url: route('requisiciones.show', $requisicion->id, false),
            actor: $user,
        );

        return back()->with('success', 'Solicitud de eliminación enviada a Contabilidad.');
    }

    public function review(Request $request, RequisicionEliminacionSolicitud $solicitud): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('view', $solicitud->requisicion), 403);

        $data = $request->validate([
            'accion' => ['required', 'in:APROBAR,RECHAZAR'],
            'comentario_revision' => ['nullable', 'required_if:accion,RECHAZAR', 'string', 'max:2000'],
        ], [
            'accion.required' => 'Indica si autorizas o rechazas la eliminación.',
            'accion.in' => 'La acción no es válida.',
            'comentario_revision.required_if' => 'Escribe el motivo del rechazo.',
            'comentario_revision.max' => 'El comentario no debe exceder 2,000 caracteres.',
        ]);

        $aprobar = $data['accion'] === 'APROBAR';

        $resultado = DB::transaction(function () use ($solicitud, $aprobar, $data, $user) {
            $locked = RequisicionEliminacionSolicitud::query()->whereKey($solicitud->id)->lockForUpdate()->first();
            if (! $locked || $locked->estatus !== RequisicionEliminacionSolicitud::PENDIENTE) {
                return 'procesada';
            }

            $req = Requisicion::query()->whereKey($locked->requisicion_id)->lockForUpdate()->firstOrFail();

            if ($aprobar && $req->status !== 'CAPTURADA') {
                return 'estatus';
            }

            $locked->forceFill([
                'estatus' => $aprobar ? RequisicionEliminacionSolicitud::APROBADA : RequisicionEliminacionSolicitud::RECHAZADA,
                'revisado_por_id' => $user->id,
                'comentario_revision' => trim((string) ($data['comentario_revision'] ?? '')) ?: null,
                'fecha_resolucion' => now(),
            ])->save();

            if ($aprobar) {
                $req->update(['status' => 'ELIMINADA']);
            }

            return $locked;
        });

        if ($resultado === 'procesada') {
            return back()->with('error', 'Esta solicitud ya fue atendida por otra persona.');
        }

        if ($resultado === 'estatus') {
            return back()->with('error', 'La requisición ya avanzó de estatus (autorizada o pagada); no se puede eliminar. Rechaza la solicitud con un comentario.');
        }

        $requisicion = $resultado->requisicion;
        $this->notifications->notifyDirect(
            topic: NotificationTopic::Requisiciones,
            event: $aprobar ? 'requisicion.eliminacion_aprobada' : 'requisicion.eliminacion_rechazada',
            title: $aprobar ? "Eliminación autorizada: {$requisicion->folio}" : "Eliminación rechazada: {$requisicion->folio}",
            message: $aprobar
                ? "{$user->name} autorizó eliminar la requisición."
                : "{$user->name} rechazó la eliminación: ".str($resultado->comentario_revision)->limit(140),
            direct: [$resultado->solicitadoPor],
            severity: $aprobar ? 'success' : 'danger',
            url: route('requisiciones.show', $requisicion->id, false),
            actor: $user,
        );

        return back()->with('success', $aprobar ? 'Requisición eliminada.' : 'Solicitud de eliminación rechazada.');
    }

    public function cancel(Request $request, RequisicionEliminacionSolicitud $solicitud): RedirectResponse
    {
        $user = $request->user();
        $isOwner = (int) $solicitud->solicitado_por_id === (int) $user->id;
        abort_unless($isOwner || $user->can('requisiciones.autorizar_eliminacion'), 403);

        $updated = RequisicionEliminacionSolicitud::query()
            ->whereKey($solicitud->id)
            ->where('estatus', RequisicionEliminacionSolicitud::PENDIENTE)
            ->update([
                'estatus' => RequisicionEliminacionSolicitud::CANCELADA,
                'revisado_por_id' => $user->id,
                'fecha_resolucion' => now(),
            ]);

        return $updated === 1
            ? back()->with('success', 'Solicitud de eliminación cancelada.')
            : back()->with('error', 'Solo se puede cancelar una solicitud pendiente.');
    }
}
