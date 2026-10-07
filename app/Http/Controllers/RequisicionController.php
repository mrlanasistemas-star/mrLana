<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Requisicion\BulkDestroyRequest;
use App\Http\Requests\Requisicion\RequisicionIndexRequest;
use App\Http\Requests\Requisicion\RequisicionStoreRequest;
use App\Http\Requests\Requisicion\RequisicionUpdateRequest;
use App\Http\Resources\RequisicionResource;
use App\Mail\RequisicionEnviadaMail;
use App\Models\Ajuste;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Plantilla;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\RequisicionEliminacionSolicitud;
use App\Models\Sucursal;
use App\Models\User;
use App\Rules\ActiveProveedor;
use App\Services\Notifications\NotificationService;
use App\Services\Pdf\PdfService;
use App\Support\BusinessDate;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RequisicionController extends Controller
{
    /** Valor predeterminado de "por página" (alineado con el frontend). */
    public const DEFAULT_PER_PAGE = 20;

    /** Opciones válidas de "por página"; 'all' muestra todos. */
    public const PER_PAGE_OPTIONS = [10, 15, 20, 50];

    public function __construct(private NotificationService $notifications) {}

    public function index(RequisicionIndexRequest $request): Response
    {
        $user = $request->user();
        $verTodos = $user->can('requisiciones.ver_todos');

        $v = $request->validated();
        $raw = array_merge($request->query(), $v);

        [$perPage, $showAll] = self::resolvePerPage($raw['perPage'] ?? null);

        $dir = strtolower((string) ($raw['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sort = $this->normalizeSort((string) ($raw['sort'] ?? 'created_at'));

        $tab = strtoupper((string) ($raw['tab'] ?? 'ACTIVAS'));
        $q = trim((string) ($raw['q'] ?? ''));

        $status = (string) ($raw['status'] ?? '');
        $compradorCorpId = $raw['comprador_corp_id'] ?? null;
        $sucursalId = $raw['sucursal_id'] ?? null;
        $solicitanteId = $raw['solicitante_id'] ?? null;
        $conceptoId = $raw['concepto_id'] ?? null;
        $proveedorId = $raw['proveedor_id'] ?? null;
        $tipo = (string) ($raw['tipo'] ?? '');

        // Fecha de registro (created_at) — usa nuevo nombre o alias
        $fechaRegistroFrom = $this->safeYmd($raw['fecha_registro_from'] ?? $raw['fecha_from'] ?? null);
        $fechaRegistroTo = $this->safeYmd($raw['fecha_registro_to'] ?? $raw['fecha_to'] ?? null);
        // Fecha de pago general (requisicions.fecha_pago)
        $fechaPagoFrom = $this->safeYmd($raw['fecha_pago_from'] ?? null);
        $fechaPagoTo = $this->safeYmd($raw['fecha_pago_to'] ?? null);

        $query = Requisicion::query()
            ->visibleTo($user)
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'sucursal.corporativo:id,nombre',
                'solicitante:id,nombre,apellido_paterno,apellido_materno,puesto',
                'proveedor:id,razon_social,rfc,clabe,banco,status',
                'concepto:id,nombre',
                'comprador:id,nombre',
            ])
            ->withExists(['eliminacionSolicitudes as eliminacion_pendiente' => fn ($q) => $q->where('estatus', RequisicionEliminacionSolicitud::PENDIENTE)]);

        if ($status === 'ELIMINADA' || $tab === 'ELIMINADAS') {
            $query->where('status', 'ELIMINADA');
        } else {
            $query->where('status', '!=', 'ELIMINADA');
        }

        if ($status === '') {
            switch ($tab) {
                case 'BORRADOR':
                    $query->where('status', 'BORRADOR');
                    break;
                case 'CAPTURADAS':
                    $query->whereNotIn('status', ['BORRADOR', 'ELIMINADA']);
                    break;
                case 'ELIMINADAS':
                    $query->where('status', 'ELIMINADA');
                    break;
                case 'ACTIVAS':
                default:
                    break;
            }
        } else {
            $query->where('status', $status);
        }

        // Quien ve todas no necesita los borradores ajenos en "Activas" (sí los propios).
        if ($verTodos && $status === '' && $tab === 'ACTIVAS') {
            $query->where(function ($w) use ($user) {
                $w->where('status', '!=', 'BORRADOR')
                    ->orWhere('creada_por_user_id', $user->id);
            });
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('folio', 'like', "%{$q}%")
                    ->orWhere('observaciones', 'like', "%{$q}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('razon_social', 'like', "%{$q}%"))
                    ->orWhereHas('concepto', fn ($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('comprador', fn ($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('sucursal', fn ($s) => $s
                        ->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                    )
                    ->orWhereHas('sucursal.corporativo', fn ($c) => $c->where('nombre', 'like', "%{$q}%"));
            });
        }

        if (! empty($compradorCorpId)) {
            $query->where('comprador_corp_id', (int) $compradorCorpId);
        }
        if (! empty($sucursalId)) {
            $query->where('sucursal_id', (int) $sucursalId);
        }

        if ($verTodos && ! empty($solicitanteId)) {
            $query->where('solicitante_id', (int) $solicitanteId);
        }

        if (! empty($conceptoId)) {
            $query->where('concepto_id', (int) $conceptoId);
        }
        if (! empty($proveedorId)) {
            $query->where('proveedor_id', (int) $proveedorId);
        }
        if ($tipo !== '' && Schema::hasColumn('requisicions', 'tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($fechaRegistroFrom) {
            $query->whereDate('created_at', '>=', $fechaRegistroFrom);
        }
        if ($fechaRegistroTo) {
            $query->whereDate('created_at', '<=', $fechaRegistroTo);
        }
        if ($fechaPagoFrom) {
            $query->whereDate('fecha_pago', '>=', $fechaPagoFrom);
        }
        if ($fechaPagoTo) {
            $query->whereDate('fecha_pago', '<=', $fechaPagoTo);
        }

        $total = $showAll ? (clone $query)->count() : null;

        $requisiciones = $query
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($showAll ? max(1, (int) $total) : $perPage)
            ->withQueryString();

        $requisiciones->getCollection()->each(
            fn (Requisicion $r) => $r->setAttribute('can', $this->abilities($user, $r))
        );

        return Inertia::render('Requisiciones/Index', [
            'requisiciones' => RequisicionResource::collection($requisiciones),
            'catalogos' => $this->catalogos($user),
            'filters' => [
                'tab' => $tab,
                'q' => $q,
                'status' => $status,
                'comprador_corp_id' => $compradorCorpId ?? '',
                'sucursal_id' => $sucursalId ?? '',
                'solicitante_id' => $solicitanteId ?? '',
                'concepto_id' => $conceptoId ?? '',
                'proveedor_id' => $proveedorId ?? '',
                'tipo' => $tipo,
                'fecha_registro_from' => $fechaRegistroFrom ?? '',
                'fecha_registro_to' => $fechaRegistroTo ?? '',
                'fecha_pago_from' => $fechaPagoFrom ?? '',
                'fecha_pago_to' => $fechaPagoTo ?? '',
                // Mantén los aliases originales para backward compat
                'fecha_from' => $fechaRegistroFrom ?? '',
                'fecha_to' => $fechaRegistroTo ?? '',
                'perPage' => $showAll ? 'all' : $perPage,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'pagination' => [
                'default_per_page' => self::DEFAULT_PER_PAGE,
                'options' => self::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    /**
     * Normaliza "por página": predeterminado 20, opciones 10/15/20/50 o "all".
     *
     * @return array{0: int, 1: bool}
     */
    public static function resolvePerPage(mixed $raw): array
    {
        if ($raw === 'all' || $raw === 'todos') {
            return [self::DEFAULT_PER_PAGE, true];
        }

        $perPage = (int) $raw;

        return [in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE, false];
    }

    public function show(Request $request, Requisicion $requisicion)
    {
        $this->authorize('view', $requisicion);
        $user = $request->user();

        $requisicion->load([
            'sucursal:id,nombre,codigo,corporativo_id,activo',
            'solicitante:id,nombre,apellido_paterno,apellido_materno,puesto,activo',
            'proveedor:id,razon_social,rfc,clabe,banco,status',
            'concepto:id,nombre,activo',
            'comprador:id,nombre,logo_path',
            'detalles',
            'detalles.sucursal:id,nombre,codigo',
            'ajustes.solicitadoPor:id,name',
            'ajustes.resueltoPor:id,name',
            'ajustes.aplicadoPor:id,name',
            'pagos:id,requisicion_id,monto,fecha_pago,tipo_pago,archivo_original,archivo_path,created_at',
            'creadaPor:id,name,email',
            'comprobantes:id,requisicion_id,tipo_doc,monto,user_carga_id,created_at,fecha_emision,estatus,archivo_original,archivo_path',
            'eliminacionSolicitudes' => fn ($q) => $q->latest('id'),
            'eliminacionSolicitudes.solicitadoPor:id,name',
            'eliminacionSolicitudes.revisadoPor:id,name',
        ]);

        $detalles = collect($requisicion->detalles ?? [])->map(function ($d) {
            $cantidad = (float) ($d->cantidad ?? 0);
            $precio = (float) ($d->precio_unitario ?? 0);
            $subtotal = (float) ($d->subtotal ?? ($cantidad * $precio));
            $iva = (float) ($d->iva ?? 0);
            $total = (float) ($d->total ?? ($subtotal + $iva));

            return [
                'id' => $d->id,
                'sucursal' => $d->sucursal ? [
                    'id' => $d->sucursal->id,
                    'nombre' => $d->sucursal->nombre,
                    'codigo' => $d->sucursal->codigo,
                ] : null,
                'cantidad' => $cantidad,
                'descripcion' => (string) ($d->descripcion ?? ''),
                'precio_unitario' => $precio,
                'genera_iva' => (bool) ($d->genera_iva ?? false),
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
            ];
        })->values();

        $ids = collect($requisicion->comprobantes)->pluck('user_carga_id')->filter()->unique()->values();
        $usersById = $ids->isEmpty()
            ? collect()
            : User::select('id', 'name')->whereIn('id', $ids)->get()->keyBy('id');

        $comprobantes = collect($requisicion->comprobantes ?? [])
            ->map(function ($c) use ($usersById) {
                $u = $usersById->get($c->user_carga_id);
                $url = ! empty($c->archivo_path) ? Storage::disk('public')->url($c->archivo_path) : null;

                return [
                    'id' => $c->id,
                    'tipo_doc' => (string) ($c->tipo_doc ?? 'OTRO'),
                    'fecha_emision' => $c->fecha_emision,
                    'monto' => (float) ($c->monto ?? 0),
                    'total' => (float) ($c->monto ?? 0),
                    'estatus' => (string) ($c->estatus ?? 'PENDIENTE'),
                    'user_carga' => $c->user_carga_id ? [
                        'id' => (int) $c->user_carga_id,
                        'name' => (string) ($u?->name ?? ('Usuario #'.(int) $c->user_carga_id)),
                    ] : null,
                    'created_at' => optional($c->created_at)->toISOString(),
                    'archivo' => $url ? [
                        'label' => $c->archivo_original ?: ('Comprobante #'.$c->id),
                        'url' => $url,
                    ] : null,
                ];
            })
            ->values();

        $ajustes = $requisicion->ajustes->sortByDesc('id')->map(fn (Ajuste $a) => self::ajusteToArray($a))->values();

        $totalItemsOriginal = (float) $detalles->sum('total');
        $totalFinal = (float) $requisicion->monto_total;

        $auditoria = [
            'total_items_original' => $totalItemsOriginal,
            // Neto real actual, no suma histórica de eventos
            'total_ajustes_aplicados' => round($totalFinal - $totalItemsOriginal, 2),
            'total_final' => $totalFinal,
        ];

        $pagosFiles = collect($requisicion->pagos ?? [])
            ->filter(fn ($p) => ! empty($p->archivo_path))
            ->map(fn ($p) => [
                'label' => $p->archivo_original ?: ('Pago #'.$p->id),
                'url' => Storage::disk('public')->url($p->archivo_path),
            ])
            ->values()
            ->all();

        $requisicion->setAttribute('can', $this->abilities($user, $requisicion));

        return Inertia::render('Requisiciones/Show', [
            'requisicion' => (new RequisicionResource($requisicion))->resolve(),
            'detalles' => $detalles,
            'comprobantes' => $comprobantes,
            'ajustes' => $ajustes,
            'auditoria' => $auditoria,
            'eliminaciones' => $requisicion->eliminacionSolicitudes->map(fn ($s) => [
                'id' => $s->id,
                'estatus' => $s->estatus,
                'motivo' => $s->motivo,
                'comentario_revision' => $s->comentario_revision,
                'solicitado_por' => $s->solicitadoPor?->name,
                'revisado_por' => $s->revisadoPor?->name,
                'fecha_solicitud' => optional($s->created_at)->toISOString(),
                'fecha_resolucion' => optional($s->fecha_resolucion)->toISOString(),
                'can' => [
                    'revisar' => $s->estatus === RequisicionEliminacionSolicitud::PENDIENTE
                        && $user->can('requisiciones.autorizar_eliminacion'),
                    'cancelar' => $s->estatus === RequisicionEliminacionSolicitud::PENDIENTE
                        && ((int) $s->solicitado_por_id === (int) $user->id || $user->can('requisiciones.autorizar_eliminacion')),
                ],
            ])->values(),
            'pdf' => [
                'can_print' => true,
                'print_url' => route('requisiciones.print', $requisicion->id),
                'filename' => ($requisicion->folio ?? 'requisicion').'.pdf',
                'files' => $pagosFiles,
            ],
        ]);
    }

    public function pdf(Request $request, Requisicion $requisicion, PdfService $pdf)
    {
        $this->authorize('view', $requisicion);

        $requisicion->load([
            'sucursal:id,nombre,codigo,corporativo_id',
            'solicitante:id,nombre,apellido_paterno,apellido_materno',
            'proveedor:id,razon_social,rfc,clabe,banco',
            'concepto:id,nombre',
            'comprador:id,nombre,rfc,direccion,telefono,email,logo_path',
            'detalles',
            'detalles.sucursal:id,nombre,codigo',
            'ajustes.solicitadoPor:id,name',
            'ajustes.resueltoPor:id,name',
            'ajustes.aplicadoPor:id,name',
            'pagos:id,requisicion_id,monto,fecha_pago,tipo_pago',
            'creadaPor:id,name',
            'comprobantes:id,requisicion_id,tipo_doc,fecha_emision,monto,archivo_path,archivo_original,estatus,user_carga_id,created_at',
        ]);

        $totalItemsOriginal = (float) collect($requisicion->detalles ?? [])->sum(fn ($d) => (float) ($d->total ?? 0));
        $totalFinalAuditado = (float) $requisicion->monto_total;

        return $pdf->inline('pdfs.requisicion', [
            'requisicion' => $requisicion,
            'totalItemsOriginal' => $totalItemsOriginal,
            'totalAjustesAplicados' => round($totalFinalAuditado - $totalItemsOriginal, 2),
            'totalFinalAuditado' => $totalFinalAuditado,
            'generatedBy' => $request->user()?->name,
        ], ($requisicion->folio ?? 'requisicion').'.pdf', ['paper' => 'letter']);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $plantilla = null;
        if ($plantillaId = $request->query('plantilla')) {
            $plantilla = Plantilla::query()->with(['detalles'])->find($plantillaId);

            if ($plantilla) {
                $this->authorize('view', $plantilla);
            }
        }

        return Inertia::render('Requisiciones/Create', [
            'catalogos' => $this->catalogos($user),
            'plantilla' => $plantilla,
            'today' => BusinessDate::todayString(),
        ]);
    }

    /** La ruta define la acción; se ignora cualquier "accion" enviada por el cliente. */
    public function storeDraft(RequisicionStoreRequest $request): RedirectResponse
    {
        return $this->persist($request, 'BORRADOR');
    }

    public function storeCaptured(RequisicionStoreRequest $request): RedirectResponse
    {
        return $this->persist($request, 'ENVIAR');
    }

    public function capture(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('capture', $requisicion);

        // Un borrador histórico sin proveedor (o con proveedor inactivo) no puede enviarse.
        $proveedorActivo = $requisicion->proveedor_id !== null && Proveedor::query()
            ->whereKey($requisicion->proveedor_id)
            ->where('status', 'ACTIVO')
            ->exists();

        if (! $proveedorActivo) {
            throw ValidationException::withMessages(['proveedor_id' => ActiveProveedor::MESSAGE]);
        }

        $captured = DB::transaction(function () use ($requisicion) {
            return Requisicion::query()
                ->whereKey($requisicion->id)
                ->where('status', 'BORRADOR')
                ->update(['status' => 'CAPTURADA']) === 1;
        });

        if (! $captured) {
            return redirect()->back()->with('error', 'La requisición ya no es borrador.');
        }

        // La actualización atómica no dispara eventos del modelo: se registra explícitamente.
        $requisicion->auditLog('CAMBIO_ESTATUS', "Requisición enviada a autorización: {$requisicion->folio}.", ['status' => ['BORRADOR', 'CAPTURADA']]);

        $requisicion->refresh();
        $mailed = $this->notifyEnviada($requisicion, $request->user());

        return redirect()
            ->route('requisiciones.show', $requisicion->id)
            ->with($mailed ? 'success' : 'warning', $mailed
                ? 'Requisición enviada correctamente.'
                : 'Requisición enviada. Contabilidad fue notificada en el sistema, pero no se pudo enviar el correo.');
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        return $this->persist($request, strtoupper((string) $request->validated('accion', 'BORRADOR')));
    }

    private function persist(RequisicionStoreRequest $request, string $accion): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();
        unset($data['accion']);

        // Sin "ver todas", el solicitante siempre es el colaborador vinculado a la cuenta.
        if (! $user->can('requisiciones.ver_todos')) {
            if (! $user->empleado_id) {
                return back()->withErrors(['solicitante_id' => 'Tu usuario no está vinculado a un colaborador. Pide a un administrador que lo vincule.']);
            }
            $data['solicitante_id'] = (int) $user->empleado_id;
        }

        $data = $this->assertCatalogosActivos($data);

        $detalles = $data['detalles'];
        unset($data['detalles']);

        $data['fecha_solicitud'] = Carbon::createFromFormat('!Y-m-d', (string) $data['fecha_solicitud']);
        $data['fecha_pago_esperada'] = ! empty($data['fecha_pago_esperada'])
            ? Carbon::createFromFormat('!Y-m-d', (string) $data['fecha_pago_esperada'])
            : null;

        $data['creada_por_user_id'] = (int) $user->id;
        $data['status'] = ($accion === 'ENVIAR') ? 'CAPTURADA' : 'BORRADOR';
        $data['folio'] = $this->makeFolio();

        [$cleanDetalles, $montoSubtotal, $montoTotal] = $this->sanitizeDetalles($detalles);

        $data['monto_subtotal'] = $montoSubtotal;
        $data['monto_total'] = $montoTotal;

        $requisicion = DB::transaction(function () use ($data, $cleanDetalles) {
            $req = Requisicion::create($data);
            $req->detalles()->createMany($cleanDetalles);

            return $req;
        });

        if ($accion === 'ENVIAR' && ! $this->notifyEnviada($requisicion, $user)) {
            return redirect()
                ->route('requisiciones.index')
                ->with('warning', 'Requisición enviada. Contabilidad fue notificada en el sistema, pero no se pudo enviar el correo.');
        }

        return redirect()
            ->route('requisiciones.index')
            ->with('success', $accion === 'ENVIAR'
                ? 'Requisición enviada y guardada correctamente.'
                : 'Requisición guardada como borrador.');
    }

    public function update(RequisicionUpdateRequest $request, Requisicion $requisicion): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! $user->can('requisiciones.ver_todos')) {
            $data['solicitante_id'] = (int) $requisicion->solicitante_id;
        }

        $data = $this->assertCatalogosActivos($data);

        $detalles = $data['detalles'];
        unset($data['detalles']);

        $data['fecha_solicitud'] = Carbon::createFromFormat('!Y-m-d', (string) $data['fecha_solicitud']);
        $data['fecha_pago_esperada'] = ! empty($data['fecha_pago_esperada'])
            ? Carbon::createFromFormat('!Y-m-d', (string) $data['fecha_pago_esperada'])
            : null;

        [$cleanDetalles, $montoSubtotal, $montoTotal] = $this->sanitizeDetalles($detalles);
        $data['monto_subtotal'] = $montoSubtotal;
        $data['monto_total'] = $montoTotal;

        DB::transaction(function () use ($requisicion, $data, $cleanDetalles) {
            $requisicion->update($data);
            $requisicion->detalles()->delete();
            $requisicion->detalles()->createMany($cleanDetalles);
        });

        return redirect()->route('requisiciones.index')->with('success', 'Requisición actualizada.');
    }

    public function destroy(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('delete', $requisicion);

        $requisicion->forceFill(['status' => 'ELIMINADA'])->save();

        return redirect()->route('requisiciones.index')->with('success', 'Requisición eliminada.');
    }

    public function bulkDestroy(BulkDestroyRequest $request): RedirectResponse
    {
        $ids = $request->validated()['ids'];

        $updated = Requisicion::query()
            ->visibleTo($request->user())
            ->whereIn('id', $ids)
            ->where('status', '!=', 'ELIMINADA')
            ->updateEach(['status' => 'ELIMINADA']);

        return redirect()->route('requisiciones.index')->with('success', $updated === 1
            ? '1 requisición eliminada.'
            : "{$updated} requisiciones eliminadas.");
    }

    public function ajustes(Request $request, Requisicion $requisicion): Response
    {
        $this->authorize('viewAjustes', $requisicion);
        $user = $request->user();

        $requisicion->load([
            'ajustes' => fn ($q) => $q->orderByDesc('id'),
            'ajustes.solicitadoPor:id,name',
            'ajustes.resueltoPor:id,name',
            'ajustes.aplicadoPor:id,name',
            'concepto:id,nombre',
            'proveedor:id,razon_social',
            'solicitante:id,nombre,apellido_paterno,apellido_materno',
        ]);

        return Inertia::render('Requisiciones/Ajustes', [
            'requisicion' => [
                'id' => $requisicion->id,
                'folio' => $requisicion->folio,
                'status' => $requisicion->status,
                'monto_total' => (float) $requisicion->monto_total,
                'concepto' => $requisicion->concepto?->nombre,
                'proveedor' => $requisicion->proveedor?->razon_social,
                'solicitante' => $requisicion->solicitante
                    ? trim($requisicion->solicitante->nombre.' '.$requisicion->solicitante->apellido_paterno.' '.($requisicion->solicitante->apellido_materno ?? ''))
                    : null,
            ],
            'ajustes' => $requisicion->ajustes->map(fn (Ajuste $a) => self::ajusteToArray($a) + [
                'can' => [
                    'revisar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE && $user->can('ajustes.revisar'),
                    'aplicar' => $a->estatus === Ajuste::ESTATUS_APROBADO && $user->can('ajustes.aplicar'),
                    'cancelar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE
                        && ((int) $a->user_registro_id === (int) $user->id || $user->can('ajustes.revisar')),
                ],
            ])->values(),
            'can' => [
                'solicitar' => $user->can('requestAdjustment', $requisicion),
            ],
            'today' => BusinessDate::todayString(),
        ]);
    }

    /** Representación de un ajuste con su auditoría completa. */
    public static function ajusteToArray(Ajuste $a): array
    {
        return [
            'id' => (int) $a->id,
            'tipo' => (string) $a->tipo,
            'sentido' => (string) ($a->sentido ?? ''),
            'monto' => (float) ($a->monto ?? 0),
            'monto_anterior' => (float) ($a->monto_anterior ?? 0),
            'monto_nuevo' => (float) ($a->monto_nuevo ?? 0),
            'estatus' => (string) ($a->estatus ?? Ajuste::ESTATUS_PENDIENTE),
            'motivo' => $a->motivo,
            'notas' => $a->notas,
            'comentario_revision' => $a->comentario_revision,
            'solicitado_por' => $a->solicitadoPor?->name,
            'resuelto_por' => $a->resueltoPor?->name,
            'aplicado_por' => $a->aplicadoPor?->name,
            'fecha_registro' => optional($a->fecha_registro)->format('Y-m-d'),
            'fecha_solicitud' => optional($a->created_at ?? $a->fecha_registro)->toISOString(),
            'fecha_resolucion' => optional($a->fecha_resolucion)->toISOString(),
            'fecha_aplicacion' => optional($a->fecha_aplicacion)->toISOString(),
        ];
    }

    /**
     * Acciones disponibles para el usuario sobre una requisición. Solo sirven
     * para pintar la interfaz; cada endpoint vuelve a autorizar en el servidor.
     *
     * @return array<string, bool>
     */
    private function abilities(User $user, Requisicion $r): array
    {
        return [
            'ver' => $user->can('view', $r),
            'editar' => $user->can('update', $r),
            'capturar' => $user->can('capture', $r),
            'eliminar' => $user->can('delete', $r),
            'solicitar_eliminacion' => $user->can('requestDeletion', $r),
            'ver_ajustes' => $user->can('viewAjustes', $r),
            'solicitar_ajuste' => $user->can('requestAdjustment', $r),
            'ver_pagos' => $user->can('viewPayments', $r),
            'autorizar_pago' => $user->can('authorizePayment', $r),
            'registrar_pago' => $user->can('registerPayment', $r),
            'ver_comprobaciones' => $user->can('viewComprobaciones', $r),
            'subir_comprobante' => $user->can('uploadComprobante', $r),
        ];
    }

    /**
     * Notifica una requisición enviada: campana a los roles suscritos a
     * "Requisiciones" y correo de respaldo. Devuelve si el correo se envió
     * (o no había destinatarios configurados).
     */
    private function notifyEnviada(Requisicion $requisicion, User $actor): bool
    {
        $this->notifications->notify(
            topic: NotificationTopic::Requisiciones,
            event: 'requisicion.enviada',
            title: "Nueva requisición {$requisicion->folio}",
            message: "{$actor->name} envió una requisición por $".number_format((float) $requisicion->monto_total, 2).' para autorización de pago.',
            severity: 'info',
            url: route('requisiciones.show', $requisicion->id, false),
            actor: $actor,
        );

        $to = config('erp.requisicion_notify_to', []);
        if ($to === []) {
            return true;
        }

        return $this->notifications->mail($to, new RequisicionEnviadaMail(
            $requisicion->fresh(['detalles', 'sucursal', 'solicitante', 'concepto', 'proveedor', 'comprador'])
        ));
    }

    /**
     * Verifica que corporativo, sucursal, concepto y proveedor estén activos y
     * sean coherentes entre sí. Lanza errores de validación con mensajes humanos.
     */
    private function assertCatalogosActivos(array $data): array
    {
        $corpId = (int) ($data['comprador_corp_id'] ?? 0);
        $sucursalId = (int) ($data['sucursal_id'] ?? 0);

        $corporativo = Corporativo::select('id', 'activo')->find($corpId);
        if (! $corporativo || $corporativo->activo === false) {
            throw ValidationException::withMessages(['comprador_corp_id' => 'El corporativo seleccionado no está activo o no existe.']);
        }

        $sucursal = Sucursal::select('id', 'corporativo_id', 'activo')->find($sucursalId);
        if (! $sucursal || $sucursal->activo === false) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal seleccionada no está activa o no existe.']);
        }

        if ((int) $sucursal->corporativo_id !== $corpId) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no pertenece al corporativo seleccionado.']);
        }

        $data['comprador_corp_id'] = (int) $sucursal->corporativo_id;

        $concepto = Concepto::select('id', 'activo')->find((int) ($data['concepto_id'] ?? 0));
        if (! $concepto || $concepto->activo === false) {
            throw ValidationException::withMessages(['concepto_id' => 'El concepto seleccionado no está activo o no existe.']);
        }

        return $data;
    }

    private function catalogos(User $user): array
    {
        $verTodos = $user->can('requisiciones.ver_todos');

        $corporativos = Corporativo::select('id', 'nombre', 'activo')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $sucursales = Sucursal::select('id', 'nombre', 'codigo', 'corporativo_id', 'activo')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $conceptos = Concepto::select('id', 'nombre', 'activo')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $proveedores = Proveedor::select('id', 'razon_social', 'rfc', 'clabe', 'banco', 'status')
            ->where('status', 'ACTIVO')
            ->when(! $user->can('proveedores.ver_todos'), fn ($q) => $q->where('user_duenio_id', $user->id))
            ->orderBy('razon_social')
            ->limit(1000)
            ->get();

        $empleadosQ = Empleado::select('id', 'nombre', 'apellido_paterno', 'apellido_materno', 'sucursal_id', 'activo')
            ->where('activo', true)
            ->orderBy('nombre');

        if (! $verTodos) {
            $empleadosQ->where('id', (int) $user->empleado_id);
        }

        $empleados = $empleadosQ->get()->map(fn ($e) => [
            'id' => $e->id,
            'nombre' => trim($e->nombre.' '.$e->apellido_paterno.' '.($e->apellido_materno ?? '')),
            'sucursal_id' => $e->sucursal_id,
            'activo' => $e->activo,
        ]);

        return [
            'corporativos' => $corporativos,
            'sucursales' => $sucursales,
            'empleados' => $empleados,
            'conceptos' => $conceptos,
            'proveedores' => $proveedores,
            'solicitante_fijo' => ! $verTodos,
        ];
    }

    private function makeFolio(): string
    {
        $prefix = 'REQ';

        do {
            $folio = $prefix.'-'.strtoupper(Str::random(5));
        } while (Requisicion::where('folio', $folio)->exists());

        return $folio;
    }

    private function sanitizeDetalles(array $detalles): array
    {
        $ivaRate = 0.16;
        $montoSubtotal = 0.0;
        $montoTotal = 0.0;
        $clean = [];

        $hasGeneraIvaColumn = Schema::hasColumn('detalles', 'genera_iva');

        foreach ($detalles as $i => $d) {
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
            $iva = $generaIva ? round($subtotal * $ivaRate, 2) : 0.00;
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

    private function safeYmd($v): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function normalizeSort(string $sort): string
    {
        $map = [
            'fecha_captura' => 'created_at',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
            'folio' => 'folio',
            'monto_total' => 'monto_total',
            'status' => 'status',
            'id' => 'id',
        ];

        return $map[trim($sort)] ?? 'created_at';
    }
}
