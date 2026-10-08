<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Mail\ComprobanteRechazadoMail;
use App\Mail\RequisicionComprobacionesNotifyMail;
use App\Mail\RequisicionComprobadaMail;
use App\Models\Comprobante;
use App\Models\Folio;
use App\Models\Requisicion;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RequisicionComprobanteController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function create(Request $request, Requisicion $requisicion)
    {
        $this->authorize('viewComprobaciones', $requisicion);
        $user = $request->user();

        $solicitante = DB::table('empleados')->where('id', $requisicion->solicitante_id)->first();
        $conceptoNombre = DB::table('conceptos')->where('id', $requisicion->concepto_id)->value('nombre');
        $corp = DB::table('corporativos')->where('id', $requisicion->comprador_corp_id)->first();
        $comprobantes = DB::table('comprobantes')
            ->where('requisicion_id', $requisicion->id)
            ->orderByDesc('id')
            ->get();

        $totalReq = (float) $requisicion->monto_total;
        $sumCargados = (float) $comprobantes->sum('monto');
        $sumAprobados = (float) $comprobantes->where('estatus', 'APROBADO')->sum('monto');

        return Inertia::render('Requisiciones/Comprobar', [
            'requisicion' => [
                'data' => [
                    'id' => $requisicion->id,
                    'folio' => $requisicion->folio,
                    'concepto' => $conceptoNombre ?: '—',
                    'monto_total' => (float) $requisicion->monto_total,
                    'solicitante_nombre' => $solicitante
                        ? trim(($solicitante->nombre ?? '').' '.($solicitante->apellido_paterno ?? '').' '.($solicitante->apellido_materno ?? ''))
                        : '—',
                    'status' => (string) ($requisicion->status ?? ''),
                    // Datos de facturación del corporativo comprador
                    'razon_social' => $corp->nombre ?? '—',
                    'rfc' => $corp->rfc ?? '—',
                    'direccion' => $corp->direccion ?? '—',
                    'telefono' => $corp->telefono ?? '—',
                    'correo' => $corp->email ?? '—',
                ],
            ],

            'folios' => Folio::query()
                ->select('id', 'folio', 'monto_total')
                ->orderByDesc('id')
                ->get(),

            'comprobantes' => [
                'data' => collect($comprobantes)->map(function ($c) use ($user, $requisicion) {
                    // Archivo por la ruta protegida (valida el alcance en cada descarga).
                    $url = ! empty($c->archivo_path) ? route('comprobantes.archivo', $c->id) : null;

                    return [
                        'id' => (int) $c->id,
                        'fecha_emision' => $c->fecha_emision,
                        'tipo_doc' => $c->tipo_doc,
                        'monto' => (float) ($c->monto ?? 0),
                        'estatus' => $c->estatus ?? 'PENDIENTE',
                        'comentario_revision' => $c->comentario_revision,
                        'can_delete' => $user->can('deleteComprobante', [$requisicion, (new Comprobante)->forceFill((array) $c)]),
                        'archivo' => $url ? [
                            'label' => $c->archivo_original ?: 'Ver archivo',
                            'url' => $url,
                        ] : null,
                    ];
                })->values(),
            ],
            'totales' => [
                'cargado' => $sumCargados,
                'aprobado' => $sumAprobados,
                'pendiente_por_comprobar' => max(0, $totalReq - $sumAprobados),
                'pendiente_por_cargar' => max(0, $totalReq - $sumCargados),
            ],
            'tipoDocOptions' => [
                ['id' => 'FACTURA', 'nombre' => 'Factura'],
                ['id' => 'TICKET',  'nombre' => 'Ticket'],
                ['id' => 'NOTA',    'nombre' => 'Nota'],
                ['id' => 'OTRO',    'nombre' => 'Otro'],
            ],
            'canReview' => $user->can('reviewComprobante', $requisicion),
            'can' => [
                'revisar' => $user->can('reviewComprobante', $requisicion),
                'aceptar' => $user->can('reviewComprobante', [$requisicion, 'APROBADO']),
                'rechazar' => $user->can('reviewComprobante', [$requisicion, 'RECHAZADO']),
                'subir' => $user->can('uploadComprobante', $requisicion),
                'eliminar' => $user->can('deleteComprobante', $requisicion),
                'administrar_folios' => $user->can('comprobaciones.administrar_folios'),
                'ver_ajustes' => $user->can('viewAjustes', $requisicion),
                'solicitar_ajuste' => $user->can('requestAdjustment', $requisicion),
            ],
        ]);
    }

    public function store(Request $request, Requisicion $requisicion)
    {
        $this->authorize('uploadComprobante', $requisicion);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,docx,doc'],
            'tipo_doc' => ['required', 'in:FACTURA,TICKET,NOTA,OTRO'],
            'fecha_emision' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0'],
        ], [
            'archivo.required' => 'Sube el archivo del comprobante.',
            'archivo.max' => 'El archivo no debe pesar más de 10 MB.',
            'archivo.mimes' => 'Formato no permitido. Usa PDF, imagen, Excel o Word.',
            'tipo_doc.required' => 'Selecciona el tipo de comprobante.',
            'fecha_emision.required' => 'Indica la fecha de emisión.',
            'monto.required' => 'Captura el monto del comprobante.',
        ]);

        return DB::transaction(function () use ($data, $request, $requisicion) {
            // Pendiente contra lo ya cargado (sin contar rechazados)
            $sumNoRechazados = (float) $requisicion->comprobantes()
                ->where('estatus', '!=', 'RECHAZADO')
                ->sum('monto');
            $pendiente = max(0, (float) $requisicion->monto_total - $sumNoRechazados);
            $monto = round((float) $data['monto'], 2);

            if ($pendiente > 0 && $monto > ($pendiente + 0.00001)) {
                return back()->withErrors([
                    'monto' => 'El monto ($'.number_format($monto, 2).') supera lo pendiente ($'.number_format($pendiente, 2).').',
                ]);
            }
            if ($pendiente <= 0 && abs($monto) > 0.00001) {
                return back()->withErrors([
                    'monto' => 'No hay monto pendiente por comprobar. Solo se permite $0.00.',
                ]);
            }

            $file = $request->file('archivo');
            $stored = $file->storePublicly("requisiciones/{$requisicion->id}/comprobantes", 'public');

            (new Comprobante)->forceFill([
                'requisicion_id' => $requisicion->id,
                'tipo_doc' => $data['tipo_doc'],
                'fecha_emision' => $data['fecha_emision'],
                'monto' => $monto,
                'archivo_path' => $stored,
                'archivo_original' => $file->getClientOriginalName(),
                'estatus' => 'PENDIENTE',
                'comentario_revision' => null,
                'user_revision_id' => null,
                'revisado_at' => null,
                'user_carga_id' => (int) $request->user()->id,
            ])->save();

            return back()->with('success', 'Comprobante cargado.');
        });
    }

    public function destroy(Request $request, Comprobante $comprobante)
    {
        $requisicion = $comprobante->requisicion()->first();
        abort_unless($requisicion && $request->user()->can('deleteComprobante', [$requisicion, $comprobante]), 403);

        DB::transaction(function () use ($comprobante, $requisicion) {
            $path = $comprobante->archivo_path;
            $comprobante->delete();

            // Recalcula el estatus global
            if ($requisicion->status !== 'ELIMINADA') {
                $sumAprobados = (float) $requisicion->comprobantes()
                    ->where('estatus', 'APROBADO')
                    ->sum('monto');
                $requisicion->update([
                    'status' => ((float) $requisicion->monto_total <= $sumAprobados)
                        ? 'COMPROBACION_ACEPTADA'
                        : 'POR_COMPROBAR',
                ]);
            }

            if (! empty($path)) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
        });

        return back()->with('success', 'Comprobante eliminado.');
    }

    public function review(Request $request, Comprobante $comprobante)
    {
        $requisicion = $comprobante->requisicion()->first();
        abort_unless($requisicion && $request->user()->can('reviewComprobante', $requisicion), 403);

        $data = $request->validate([
            'estatus' => ['required', 'in:APROBADO,RECHAZADO'],
            'comentario_revision' => ['nullable', 'required_if:estatus,RECHAZADO', 'string', 'max:2000'],
        ], [
            'estatus.required' => 'Indica si apruebas o rechazas el comprobante.',
            'comentario_revision.required_if' => 'Escribe el motivo del rechazo.',
            'comentario_revision.max' => 'El comentario no debe exceder 2,000 caracteres.',
        ]);

        // La decisión concreta exige su permiso: aceptar o rechazar.
        abort_unless($request->user()->can('reviewComprobante', [$requisicion, $data['estatus']]), 403);

        [$req, $statusAnteriorReq] = DB::transaction(function () use ($data, $comprobante, $request) {
            $comprobante->forceFill([
                'estatus' => $data['estatus'],
                'comentario_revision' => $data['estatus'] === 'RECHAZADO'
                    ? trim((string) $data['comentario_revision'])
                    : null,
                'user_revision_id' => (int) $request->user()->id,
                'revisado_at' => now(),
            ])->save();

            $req = Requisicion::query()->whereKey($comprobante->requisicion_id)->lockForUpdate()->first();
            $statusAnterior = (string) ($req->status ?? '');

            if ($req && $req->status !== 'ELIMINADA') {
                $sumAprobados = (float) $req->comprobantes()->where('estatus', 'APROBADO')->sum('monto');
                $req->update([
                    'status' => ($sumAprobados + 0.00001 >= (float) $req->monto_total)
                        ? 'COMPROBACION_ACEPTADA'
                        : 'POR_COMPROBAR',
                ]);
            }

            return [$req?->fresh(['solicitante.user', 'creadaPor']), $statusAnterior];
        });

        // Notificaciones y correo fuera de la transacción.
        if ($req) {
            $destinatarios = [$req->solicitante?->user, $req->creadaPor];
            $actor = $request->user();
            $url = route('requisiciones.comprobar', $req->id, false);

            if ($data['estatus'] === 'RECHAZADO') {
                $this->notifications->notifyDirect(
                    topic: NotificationTopic::Comprobaciones,
                    event: 'comprobante.rechazado',
                    title: "Comprobante rechazado: {$req->folio}",
                    message: 'Motivo: '.str($comprobante->comentario_revision)->limit(160),
                    direct: $destinatarios,
                    severity: 'danger',
                    url: $url,
                    actor: $actor,
                );

                $email = $req->solicitante?->user?->email;
                if ($email) {
                    $this->notifications->mail($email, new ComprobanteRechazadoMail($req, $comprobante));
                }
            } elseif ($statusAnteriorReq !== 'COMPROBACION_ACEPTADA' && $req->status === 'COMPROBACION_ACEPTADA') {
                $this->notifications->notifyDirect(
                    topic: NotificationTopic::Comprobaciones,
                    event: 'comprobacion.aceptada',
                    title: "Comprobación aceptada: {$req->folio}",
                    message: 'Todos tus comprobantes fueron aprobados. La requisición quedó comprobada.',
                    direct: $destinatarios,
                    severity: 'success',
                    url: route('requisiciones.show', $req->id, false),
                    actor: $actor,
                );

                $email = $req->solicitante?->user?->email;
                if ($email) {
                    $this->notifications->mail($email, new RequisicionComprobadaMail($req));
                }
            }
        }

        return back()->with('success', 'Revisión aplicada.');
    }

    public function notify(Request $request, Requisicion $requisicion)
    {
        $this->authorize('uploadComprobante', $requisicion);

        // canal "sistema": solo campana de Contabilidad; "correo": solo correo (comportamiento original).
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'canal' => ['nullable', 'in:sistema,correo,whatsapp'],
        ], [
            'message.required' => 'Escribe un mensaje para Contabilidad.',
            'message.max' => 'El mensaje no debe exceder 2,000 caracteres.',
            'canal.in' => 'El canal de notificación no es válido.',
        ]);
        $canal = $data['canal'] ?? 'correo';

        // Validar que ya subieron comprobantes por el total de la requisición
        $sumCargados = (float) $requisicion->comprobantes()->sum('monto');
        if (($sumCargados + 0.00001) < (float) $requisicion->monto_total) {
            return back()->withErrors([
                'notify' => 'Aún no se ha cargado el monto total de comprobaciones.',
            ]);
        }

        // Cambiar estatus solo cuando el usuario ya decidió notificar
        if (! in_array((string) $requisicion->status, ['ELIMINADA', 'COMPROBACION_ACEPTADA'], true)) {
            $requisicion->update(['status' => 'POR_COMPROBAR']);
            $requisicion->refresh();
        }

        $user = $request->user();

        // WhatsApp lo abre el navegador; aquí solo queda registrado el cambio de estatus.
        if ($canal === 'whatsapp') {
            return redirect()->back(303)->with('success', 'Requisición lista para revisión. Abre WhatsApp para avisar a Contabilidad.');
        }

        if ($canal === 'sistema') {
            $recipients = $this->notifications->notify(
                topic: NotificationTopic::Comprobaciones,
                event: 'comprobantes.enviados',
                title: "Comprobantes listos para revisión: {$requisicion->folio}",
                message: "{$user->name}: ".str($data['message'])->limit(180),
                severity: 'info',
                url: route('requisiciones.comprobar', $requisicion->id, false),
                actor: $user,
                canSee: fn ($u) => $u->can('viewComprobaciones', $requisicion),
            );

            return redirect()->back(303)->with(
                $recipients->isNotEmpty() ? 'success' : 'warning',
                $recipients->isNotEmpty()
                    ? 'Contabilidad recibió el aviso en el sistema.'
                    : 'No hay usuarios de Contabilidad configurados para recibir avisos de comprobaciones.'
            );
        }

        $to = config('erp.requisicion_notify_to', []);
        if ($to === []) {
            return back()->withErrors([
                'notify' => 'No hay correos configurados para Contabilidad (REQUISICION_NOTIFY_TO). Usa el aviso en el sistema.',
            ]);
        }

        $mailed = $this->notifications->mail($to, new RequisicionComprobacionesNotifyMail(
            requisicion: $requisicion,
            messageText: $data['message'],
            senderName: $user->name,
        ));

        return redirect()->back(303)->with($mailed ? 'success' : 'error', $mailed
            ? 'Correo enviado a Contabilidad.'
            : 'No se pudo enviar el correo. Intenta de nuevo o usa el aviso en el sistema.');
    }
}
