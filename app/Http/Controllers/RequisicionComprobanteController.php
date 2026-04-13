<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Requisicion;
use App\Models\Folio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequisicionComprobacionesNotifyMail;
use App\Mail\RequisicionComprobadaMail;
use App\Mail\ComprobanteRechazadoMail;

class RequisicionComprobanteController extends Controller {

    public function create(Requisicion $requisicion) {
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

        $canReview = $this->canReview();
        return Inertia::render('Requisiciones/Comprobar', [
            'requisicion' => [
                'data' => [
                    'id' => $requisicion->id,
                    'folio' => $requisicion->folio,
                    'concepto' => $conceptoNombre ?: '—',
                    'monto_total' => (float) $requisicion->monto_total,
                    'solicitante_nombre' => $solicitante
                    ? trim(($solicitante->nombre ?? '') . ' ' . ($solicitante->apellido ?? ''))
                    : '—',
                    'status' => (string) ($requisicion->status ?? ''),
                    // Facturación (ajusta nombres si tu tabla usa otros)
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

            // Shape que tu Vue ya usa: c.monto, c.archivo, c.estatus, c.comentario_revision
            'comprobantes' => [
                'data' => collect($comprobantes)->map(function ($c) {
                    $url = null;
                    if (!empty($c->archivo_path)) {
                        $url = Storage::disk('public')->url($c->archivo_path);
                    }
                    return [
                        'id' => (int) $c->id,
                        'fecha_emision' => $c->fecha_emision,
                        'tipo_doc' => $c->tipo_doc,
                        'monto' => (float) ($c->monto ?? 0),
                        'estatus' => $c->estatus ?? 'PENDIENTE',
                        'comentario_revision' => $c->comentario_revision,
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
            'canReview' => $canReview,
        ]);
    }

    public function store(Request $request, Requisicion $requisicion) {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,docx,doc'],
            'tipo_doc' => ['required', 'in:FACTURA,TICKET,NOTA,OTRO'],
            'fecha_emision' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'min:0'],
        ]);
        return DB::transaction(function () use ($data, $request, $requisicion) {
            // Pendiente contra lo ya cargado (sum de monto)
            $sumNoRechazados = (float) $requisicion->comprobantes()
                ->where('estatus', '!=', 'RECHAZADO')
                ->sum('monto');
            $pendiente = max(0, (float) $requisicion->monto_total - $sumNoRechazados);
            $monto = round((float) $data['monto'], 2);
            // No exceder pendiente
            if ($pendiente > 0 && $monto > ($pendiente + 0.00001)) {
                return back()->withErrors([
                    'monto' => "El monto ($monto) supera el pendiente ($pendiente).",
                ]);
            }
            // Si pendiente es 0 => solo permito 0
            if ($pendiente <= 0 && abs($monto) > 0.00001) {
                return back()->withErrors([
                    'monto' => "Pendiente en 0. Solo se permite monto 0.00.",
                ]);
            }
            $file = $request->file('archivo');
            $folder = "requisiciones/{$requisicion->id}/comprobantes";
            $stored = $file->storePublicly($folder, 'public');
            $comprobante = new Comprobante();
            // forceFill para ignorar problemas de $fillable (y que NO te lo guarde en 0)
            $comprobante->forceFill([
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
                'user_carga_id' => (int) auth()->id(),
            ])->save();
            return back()->with('success', 'Comprobante cargado.');
        });
    }

    public function destroy(Comprobante $comprobante) {
        $rol = strtoupper((string) (auth()->user()?->rol ?? 'COLABORADOR'));
        abort_unless(in_array($rol, ['ADMIN', 'CONTADOR'], true), 403);
        return DB::transaction(function () use ($comprobante) {
            // borra archivo si existe
            if (!empty($comprobante->archivo_path)) {
                Storage::disk('public')->delete($comprobante->archivo_path);
            }
            $requisicion = $comprobante->requisicion()->first();
            $comprobante->delete();
            // opcional: recalcula status global
            if ($requisicion && isset($requisicion->status) && $requisicion->status !== 'ELIMINADA') {
                $sumAprobados = (float) $requisicion->comprobantes()
                    ->where('estatus', 'APROBADO')
                    ->sum('monto');
                $requisicion->update([
                    'status' => ((float) $requisicion->monto_total <= $sumAprobados)
                        ? 'COMPROBACION_ACEPTADA'
                        : 'POR_COMPROBAR',
                ]);
            }
            return back()->with('success', 'Comprobante eliminado.');
        });
    }

    public function review(Request $request, Comprobante $comprobante) {
        abort_unless($this->canReview(), 403);
        $data = $request->validate([
            'estatus' => ['required', 'in:APROBADO,RECHAZADO'],
            'comentario_revision' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['estatus'] === 'RECHAZADO' && trim((string) ($data['comentario_revision'] ?? '')) === '') {
            return back()->withErrors([
                'comentario_revision' => 'Escribe el motivo del rechazo.',
            ]);
        }
        return DB::transaction(function () use ($data, $comprobante) {
            $estatusAnteriorComprobante = (string) ($comprobante->estatus ?? 'PENDIENTE');
            $comprobante->forceFill([
                'estatus' => $data['estatus'],
                'comentario_revision' => $data['estatus'] === 'RECHAZADO'
                    ? trim((string) $data['comentario_revision'])
                    : null,
                'user_revision_id' => (int) auth()->id(),
                'revisado_at' => now(),
            ])->save();
            $req = $comprobante->requisicion()->first();
            if ($req) {
                $statusAnteriorReq = (string) ($req->status ?? '');
                $sumAprobados = (float) $req->comprobantes()
                    ->where('estatus', 'APROBADO')
                    ->sum('monto');
                if ($sumAprobados + 0.00001 >= (float) $req->monto_total) {
                    $req->update(['status' => 'COMPROBACION_ACEPTADA']);
                } else {
                    if ((string) $req->status === 'COMPROBACION_ACEPTADA') {
                        $req->update(['status' => 'POR_COMPROBAR']);
                    } elseif ((string) $req->status !== 'ELIMINADA') {
                        $req->update(['status' => 'POR_COMPROBAR']);
                    }
                }
                $req->refresh();
                $emailColaborador = $this->getSolicitanteEmail($req);
                // 1) Si rechazan un comprobante -> correo al colaborador
                if ($data['estatus'] === 'RECHAZADO' && $emailColaborador) {
                    Mail::to($emailColaborador)->send(
                        new ComprobanteRechazadoMail($req, $comprobante)
                    );
                }
                // 2) Si la requisición acaba de pasar a COMPROBACION_ACEPTADA -> correo final
                if (
                    $emailColaborador &&
                    $statusAnteriorReq !== 'COMPROBACION_ACEPTADA' &&
                    (string) $req->status === 'COMPROBACION_ACEPTADA'
                ) {
                    Mail::to($emailColaborador)->send(
                        new RequisicionComprobadaMail($req)
                    );
                }
            }
            return back()->with('success', 'Revisión aplicada.');
        });
    }

    private function canReview(): bool {
        $user = auth()->user();
        if (!$user) return false;
        $rol = strtoupper((string) ($user->rol ?? 'COLABORADOR'));
        // Roles reales en tu sistema
        return in_array($rol, ['ADMIN', 'CONTADOR'], true);
    }

    private function getSolicitanteEmail(Requisicion $requisicion): ?string {
        $user = DB::table('users')
            ->where('empleado_id', $requisicion->solicitante_id)
            ->first();
        $email = $user->email ?? null;
        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function notify(Request $request, Requisicion $requisicion) {
        $role = strtoupper((string) (auth()->user()?->rol ?? auth()->user()?->role ?? ''));
        abort_unless(in_array($role, ['COLABORADOR', 'ADMIN', 'CONTADOR'], true), 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        // Validar que ya subieron comprobantes por el total de la requisición
        $sumCargados = (float) $requisicion->comprobantes()->sum('monto');
        $totalReq = (float) $requisicion->monto_total;
        if (($sumCargados + 0.00001) < $totalReq) {
            return back()->withErrors([
                'notify' => 'Aún no se ha cargado el monto total de comprobaciones.',
            ]);
        }
        // Cambiar estatus solo cuando el usuario ya decidió notificar
        if ((string) $requisicion->status !== 'ELIMINADA' && (string) $requisicion->status !== 'COMPROBACION_ACEPTADA') {
            $requisicion->update([
                'status' => 'POR_COMPROBAR',
            ]);
            $requisicion->refresh();
        }
        $rawTo = env('REQUISICION_NOTIFY_TO');
        abort_if(blank($rawTo), 500, 'Configura REQUISICION_NOTIFY_TO en .env');
        $to = collect(explode(',', $rawTo))
            ->map(fn ($email) => trim($email))
            ->filter()
            ->values()
            ->all();

        abort_if(empty($to), 500, 'No hay correos válidos en REQUISICION_NOTIFY_TO');
        Mail::to($to)->send(new RequisicionComprobacionesNotifyMail(
            requisicion: $requisicion,
            messageText: $data['message'],
            senderName: auth()->user()?->name ?? 'Sistema'
        ));
        return redirect()->back(303)->with('success', 'Notificación enviada.');
    }

}
