<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Requisiciones\AjusteReviewRequest;
use App\Http\Requests\Requisiciones\AjusteStoreRequest;
use App\Mail\RequisicionAjusteAplicadoMail;
use App\Mail\RequisicionAjusteSolicitadoMail;
use App\Models\Ajuste;
use App\Models\Requisicion;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes de monto de una requisición.
 *
 * Cada transición de estado (revisar, aplicar, cancelar) se hace dentro de una
 * transacción con bloqueo de fila: una segunda petición concurrente sobre un
 * ajuste ya procesado recibe un mensaje controlado y no modifica el total.
 */
class RequisicionAjusteController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Solicita un ajuste (queda PENDIENTE) y notifica a los roles suscritos a "Ajustes".
     */
    public function store(AjusteStoreRequest $request, Requisicion $requisicion): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $tipo = (string) $data['tipo'];
        $delta = round((float) $data['monto'], 2);

        $sentido = $data['sentido'] ?? null;
        if (! $sentido) {
            $sentido = $tipo === 'DEVOLUCION' ? 'A_FAVOR_EMPRESA' : 'A_FAVOR_SOLICITANTE';
        }

        $anterior = (float) $requisicion->monto_total;
        $nuevo = $anterior + (($sentido === 'A_FAVOR_EMPRESA') ? -1 : 1) * $delta;

        $ajuste = DB::transaction(fn () => Ajuste::create([
            'requisicion_id' => $requisicion->id,
            'tipo' => $tipo,
            'sentido' => $sentido,
            'monto' => $delta,
            'monto_anterior' => $anterior,
            'monto_nuevo' => max(0, $nuevo),
            'estatus' => Ajuste::ESTATUS_PENDIENTE,
            'motivo' => $data['motivo'],
            'fecha_registro' => ! empty($data['fecha']) ? Carbon::createFromFormat('!Y-m-d', $data['fecha']) : now(),
            'user_registro_id' => $user->id,
        ]));

        $this->notifications->notify(
            topic: NotificationTopic::Ajustes,
            event: 'ajuste.solicitado',
            title: "Ajuste solicitado en {$requisicion->folio}",
            message: "{$user->name} solicitó un ajuste de $".number_format($delta, 2).' ('.$this->tipoLabel($tipo).').',
            severity: 'warning',
            url: route('requisiciones.ajustes', $requisicion->id, false),
            actor: $user,
            canSee: fn (User $u) => $u->can('viewAjustes', $requisicion),
        );

        // Correo como canal secundario; un fallo SMTP no revierte el ajuste.
        $to = config('erp.requisicion_notify_to', []);
        $mailed = $to === [] || $this->notifications->mail($to, new RequisicionAjusteSolicitadoMail(
            ajuste: $ajuste,
            requisicion: $requisicion,
            senderName: $user->name,
        ));

        return back()->with($mailed ? 'success' : 'warning', $mailed
            ? 'Ajuste enviado a revisión.'
            : 'Ajuste enviado a revisión. Se notificó en el sistema, pero no se pudo enviar el correo.');
    }

    /**
     * Aprueba o rechaza un ajuste PENDIENTE.
     */
    public function review(AjusteReviewRequest $request, Ajuste $ajuste): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $aprobar = $data['accion'] === 'APROBAR';
        abort_unless($user->can($aprobar ? 'approveAdjustment' : 'rejectAdjustment', $ajuste->requisicion), 403);

        $resultado = DB::transaction(function () use ($ajuste, $aprobar, $data, $user) {
            $locked = Ajuste::query()->whereKey($ajuste->id)->lockForUpdate()->first();

            if (! $locked || $locked->estatus !== Ajuste::ESTATUS_PENDIENTE) {
                return null;
            }

            $locked->forceFill([
                'estatus' => $aprobar ? Ajuste::ESTATUS_APROBADO : Ajuste::ESTATUS_RECHAZADO,
                'user_resuelve_id' => $user->id,
                'fecha_resolucion' => now(),
                'comentario_revision' => ($data['comentario_revision'] ?? '') !== '' ? $data['comentario_revision'] : null,
            ])->save();

            return $locked;
        });

        if (! $resultado) {
            return back()->with('error', 'Este ajuste ya fue revisado por otra persona. Actualiza la página para ver su estado.');
        }

        $requisicion = $resultado->requisicion;
        $this->notifyRequester(
            $resultado,
            event: $aprobar ? 'ajuste.aprobado' : 'ajuste.rechazado',
            title: $aprobar ? "Ajuste aprobado en {$requisicion->folio}" : "Ajuste rechazado en {$requisicion->folio}",
            message: $aprobar
                ? "{$user->name} aprobó tu ajuste de $".number_format((float) $resultado->monto, 2).'.'
                : "{$user->name} rechazó tu ajuste: ".str($resultado->comentario_revision)->limit(160),
            severity: $aprobar ? 'success' : 'danger',
            actor: $user,
        );

        return back()->with('success', $aprobar ? 'Ajuste aprobado.' : 'Ajuste rechazado.');
    }

    /**
     * Aplica un ajuste APROBADO al monto total de la requisición (una sola vez).
     */
    public function apply(Request $request, Ajuste $ajuste): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('applyAdjustment', $ajuste->requisicion), 403);

        $resultado = DB::transaction(function () use ($ajuste, $user) {
            $locked = Ajuste::query()->whereKey($ajuste->id)->lockForUpdate()->first();

            if (! $locked || $locked->estatus !== Ajuste::ESTATUS_APROBADO) {
                return null;
            }

            $req = Requisicion::query()->whereKey($locked->requisicion_id)->lockForUpdate()->firstOrFail();

            $signo = ($locked->sentido === 'A_FAVOR_EMPRESA') ? -1 : 1;
            $actual = (float) $req->monto_total;
            $nuevo = max(0, round($actual + ($signo * (float) $locked->monto), 2));

            $req->monto_total = $nuevo;
            $req->save();
            $this->syncComprobacionStatus($req);

            // Auditoría con los valores reales al momento de aplicar.
            $locked->forceFill([
                'monto_anterior' => $actual,
                'monto_nuevo' => $nuevo,
                'estatus' => Ajuste::ESTATUS_APLICADO,
                'user_aplica_id' => $user->id,
                'fecha_aplicacion' => now(),
            ])->save();

            return [$locked, $req];
        });

        if (! $resultado) {
            $fresh = $ajuste->fresh();

            return back()->with('error', $fresh?->estatus === Ajuste::ESTATUS_APLICADO
                ? 'Este ajuste ya fue aplicado. El monto no se modificó de nuevo.'
                : 'Solo se puede aplicar un ajuste aprobado.');
        }

        [$aplicado, $req] = $resultado;

        $this->notifyRequester(
            $aplicado,
            event: 'ajuste.aplicado',
            title: "Ajuste aplicado en {$req->folio}",
            message: 'El monto de la requisición ahora es $'.number_format((float) $req->monto_total, 2).'.',
            severity: 'success',
            actor: $user,
        );

        $colaborador = $req->solicitante?->user;
        if ($colaborador?->email) {
            $this->notifications->mail($colaborador->email, new RequisicionAjusteAplicadoMail(
                ajuste: $aplicado,
                requisicion: $req,
            ));
        }

        return back()->with('success', 'Ajuste aplicado correctamente.');
    }

    /**
     * Cancela un ajuste PENDIENTE (quien lo solicitó o quien puede revisarlo).
     */
    public function cancel(Request $request, Ajuste $ajuste): RedirectResponse
    {
        $user = $request->user();
        $isOwner = (int) $ajuste->user_registro_id === (int) $user->id;
        $req = $ajuste->requisicion;
        abort_unless($user->can('viewAjustes', $req), 403);
        abort_unless($isOwner || $user->can('approveAdjustment', $req) || $user->can('rejectAdjustment', $req), 403);

        $cancelado = DB::transaction(function () use ($ajuste, $user) {
            $locked = Ajuste::query()->whereKey($ajuste->id)->lockForUpdate()->first();
            if (! $locked || $locked->estatus !== Ajuste::ESTATUS_PENDIENTE) {
                return null;
            }

            $locked->forceFill([
                'estatus' => Ajuste::ESTATUS_CANCELADO,
                'user_resuelve_id' => $user->id,
                'fecha_resolucion' => now(),
            ])->save();

            return $locked;
        });

        if (! $cancelado) {
            return back()->with('error', 'Solo se puede cancelar un ajuste pendiente.');
        }

        $this->notifications->notify(
            topic: NotificationTopic::Ajustes,
            event: 'ajuste.cancelado',
            title: "Ajuste cancelado en {$cancelado->requisicion->folio}",
            message: "{$user->name} canceló un ajuste de $".number_format((float) $cancelado->monto, 2).'.',
            severity: 'info',
            url: route('requisiciones.ajustes', $cancelado->requisicion_id, false),
            direct: [$cancelado->solicitadoPor],
            actor: $user,
            canSee: fn (User $u) => $u->can('viewAjustes', $req),
        );

        return back()->with('success', 'Ajuste cancelado.');
    }

    /**
     * Notifica al solicitante del ajuste y al usuario del colaborador
     * solicitante de la requisición (sin duplicados).
     */
    private function notifyRequester(Ajuste $ajuste, string $event, string $title, string $message, string $severity, User $actor): void
    {
        $ajuste->loadMissing(['solicitadoPor', 'requisicion.solicitante.user']);

        $this->notifications->notifyDirect(
            topic: NotificationTopic::Ajustes,
            event: $event,
            title: $title,
            message: $message,
            direct: [$ajuste->solicitadoPor, $ajuste->requisicion?->solicitante?->user],
            severity: $severity,
            url: route('requisiciones.ajustes', $ajuste->requisicion_id, false),
            actor: $actor,
        );
    }

    private function tipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'DEVOLUCION' => 'devolución',
            'FALTANTE' => 'faltante',
            'INCREMENTO_AUTORIZADO' => 'incremento autorizado',
            default => strtolower($tipo),
        };
    }

    private function syncComprobacionStatus(Requisicion $req): void
    {
        if (! in_array($req->status, ['POR_COMPROBAR', 'COMPROBACION_ACEPTADA', 'COMPROBACION_RECHAZADA'], true)) {
            return;
        }
        $aprobados = (float) $req->comprobantes()
            ->where('estatus', 'APROBADO')
            ->sum('monto');
        $req->status = ($aprobados >= (float) $req->monto_total) ? 'COMPROBACION_ACEPTADA' : 'POR_COMPROBAR';
        $req->save();
    }
}
