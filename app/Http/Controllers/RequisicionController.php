<?php

namespace App\Http\Controllers;

use App\Enums\NotificationTopic;
use App\Http\Requests\Requisicion\BulkDestroyRequest;
use App\Http\Requests\Requisicion\RequisicionIndexRequest;
use App\Http\Requests\Requisicion\RequisicionStoreRequest;
use App\Http\Requests\Requisicion\RequisicionUpdateRequest;
use App\Http\Resources\RequisicionListResource;
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
use App\Services\Requisiciones\CaptureContext;
use App\Support\BusinessDate;
use App\Support\Requisiciones\DetallesCalculator;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\Scope;
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

    /**
     * Tope de filas por página con "Todas". Si hay más, la respuesta llega
     * paginada en bloques de este tamaño (pagination.all_truncated = true) en
     * lugar de enviar miles de tarjetas en una sola respuesta.
     */
    public const ALL_LIMIT = 1000;

    public function __construct(private NotificationService $notifications) {}

    public function index(RequisicionIndexRequest $request): Response
    {
        // Las mismas policies se evalúan en cada fila: memoiza alcances y permisos
        // durante la construcción del listado (ver AccessScope::memoize).
        return AccessScope::memoize(fn () => $this->buildIndex($request));
    }

    private function buildIndex(RequisicionIndexRequest $request): Response
    {
        $user = $request->user();
        // Quien ve más allá de lo propio no necesita borradores ajenos en "Activas".
        $verTodos = AccessScope::for($user, 'requisiciones')->value > Scope::Own->value;

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

        // Consulta base: alcance + filtros, SIN estatus/pestaña. Se clona para el
        // listado (que añade estatus/pestaña) y para los conteos por estatus, de
        // modo que ambos respeten exactamente los mismos filtros.
        $query = Requisicion::query()->visibleTo($user);

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

        // Conteos por estatus (accesos rápidos): mismos filtros que el listado,
        // sin estatus/pestaña. Quien ve más allá de lo propio no cuenta borradores
        // ajenos (igual que en "Activas"). Incluye la clave ELIMINADA.
        $conteos = (clone $query)
            ->when($verTodos, fn ($w) => $w->where(fn ($x) => $x
                ->where('status', '!=', 'BORRADOR')
                ->orWhere('creada_por_user_id', $user->id)))
            ->toBase()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($n) => (int) $n);

        $this->applyStatusTab($query, $status, $tab, $verTodos, $user);

        $total = $showAll ? (clone $query)->count() : null;
        $allTruncated = $showAll && $total > self::ALL_LIMIT;

        $query
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social',
                'concepto:id,nombre',
                'comprador:id,nombre',
                'eliminadaPor:id,name',
            ])
            // Precargados para que las policies no consulten por fila.
            ->withExists([
                'eliminacionSolicitudes as eliminacion_pendiente' => fn ($q) => $q->where('estatus', RequisicionEliminacionSolicitud::PENDIENTE),
                'pagos as tiene_pagos',
            ])
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc');

        // "Todas": una sola página hasta ALL_LIMIT filas; si hay más, bloques de
        // ALL_LIMIT (el total ya contado se pasa al paginador para no repetirlo).
        $requisiciones = $showAll
            ? $query->paginate(max(1, min((int) $total, self::ALL_LIMIT)), ['*'], 'page', null, $total)
            : $query->paginate($perPage);
        $requisiciones->withQueryString();

        $requisiciones->getCollection()->each(
            fn (Requisicion $r) => $r->setAttribute('can', $this->abilities($user, $r))
        );

        return Inertia::render('Requisiciones/Index', [
            'requisiciones' => RequisicionListResource::collection($requisiciones),
            // Closures: en recargas parciales (only/except) no se recalculan.
            'catalogos' => fn () => AccessScope::memoize(fn () => $this->filterCatalogos($user)),
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
            // Cuántas requisiciones hay en cada estatus con los filtros actuales
            // (accesos rápidos del listado).
            'conteos' => $conteos,
            'pagination' => [
                'default_per_page' => self::DEFAULT_PER_PAGE,
                'options' => self::PER_PAGE_OPTIONS,
                // "Todas" llega en bloques de como máximo all_limit filas.
                'all_limit' => self::ALL_LIMIT,
                // true cuando "Todas" no cupo en una página: hay más páginas
                // (requisiciones.meta.last_page > 1) y conviene avisarlo.
                'all_truncated' => $allTruncated,
            ],
        ]);
    }

    /** Filtro de estatus o pestaña del listado (no aplica a los conteos). */
    private function applyStatusTab($query, string $status, string $tab, bool $verTodos, User $user): void
    {
        if ($status === 'ELIMINADA' || $tab === 'ELIMINADAS') {
            $query->where('status', 'ELIMINADA');
        } else {
            $query->where('status', '!=', 'ELIMINADA');
        }

        if ($status !== '') {
            $query->where('status', $status);
        } elseif ($tab === 'BORRADOR') {
            $query->where('status', 'BORRADOR');
        } elseif ($tab === 'CAPTURADAS') {
            $query->whereNotIn('status', ['BORRADOR', 'ELIMINADA']);
        }

        // Quien ve todas no necesita los borradores ajenos en "Activas" (sí los propios).
        if ($verTodos && $status === '' && $tab === 'ACTIVAS') {
            $query->where(function ($w) use ($user) {
                $w->where('status', '!=', 'BORRADOR')
                    ->orWhere('creada_por_user_id', $user->id);
            });
        }
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
            'eliminadaPor:id,name',
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
                        && $user->can('reviewDeletion', $requisicion),
                    'cancelar' => $s->estatus === RequisicionEliminacionSolicitud::PENDIENTE
                        && ((int) $s->solicitado_por_id === (int) $user->id || $user->can('reviewDeletion', $requisicion)),
                ],
            ])->values(),
            'pdf' => [
                'can_print' => $user->can('print', $requisicion),
                'print_url' => route('requisiciones.print', $requisicion->id),
                'filename' => ($requisicion->folio ?? 'requisicion').'.pdf',
                'files' => $pagosFiles,
            ],
        ]);
    }

    public function pdf(Request $request, Requisicion $requisicion, PdfService $pdf)
    {
        $this->authorize('print', $requisicion);

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

        // Una plantilla no puede imponer solicitante, sucursal, corporativo o
        // proveedor que la persona no puede elegir: se sustituyen por sus
        // valores permitidos (y el servidor vuelve a validar al guardar).
        $capture = CaptureContext::for($user);
        if ($plantilla) {
            $plantilla = $plantilla->toArray();
            if ($capture->solicitanteFijo()) {
                $plantilla['solicitante_id'] = $capture->empleado?->id;
            }
            if ($capture->sucursalFija() || ! $capture->sucursales()->whereKey((int) ($plantilla['sucursal_id'] ?? 0))->exists()) {
                $plantilla['sucursal_id'] = $capture->sucursalFija() ? $capture->sucursalId : null;
                $plantilla['comprador_corp_id'] = $capture->sucursalFija() ? $capture->corporativoId : null;
            }
            if (! $capture->proveedores()->whereKey((int) ($plantilla['proveedor_id'] ?? 0))->exists()) {
                $plantilla['proveedor_id'] = null;
            }
        }

        return Inertia::render('Requisiciones/Create', [
            'catalogos' => $this->captureCatalogos($capture),
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

        // Solicitante, sucursal, corporativo y proveedor según los permisos de captura.
        $data = CaptureContext::for($user)->enforce($data);
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

        $requisicion = DB::transaction(function () use ($data, $cleanDetalles, $user) {
            $req = Requisicion::create($data);
            $req->detalles()->createMany($cleanDetalles);
            $this->logCapturaANombre($req, $user);

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

        $data = CaptureContext::for($user)->enforce($data, $requisicion->only(['solicitante_id', 'sucursal_id', 'comprador_corp_id', 'proveedor_id']));
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

        // Con pagos registrados el motivo es obligatorio: queda en el registro.
        $tienePagos = $requisicion->tienePagos();
        $data = $request->validate([
            'motivo' => [$tienePagos ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
        ], [
            'motivo.required' => 'Esta requisición tiene pagos registrados: explica por qué se elimina.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        \App\Services\Requisiciones\EliminarRequisicion::marcar($requisicion, $request->user(), $data['motivo'] ?? null);

        return redirect()->route('requisiciones.index')->with('success', $tienePagos
            ? 'Requisición eliminada. Quedó registrado que tenía pagos.'
            : 'Requisición eliminada.');
    }

    public function bulkDestroy(BulkDestroyRequest $request): RedirectResponse
    {
        $ids = $request->validated()['ids'];

        $user = $request->user();
        $candidatas = Requisicion::query()
            ->visibleTo($user)
            ->whereIn('id', $ids)
            ->where('status', '!=', 'ELIMINADA')
            ->withExists('pagos as tiene_pagos')
            ->get();

        // Las que tienen pagos se eliminan una por una, con su motivo.
        $conPagos = $candidatas->filter(fn (Requisicion $r) => $r->tienePagos());
        $updated = $candidatas
            ->reject(fn (Requisicion $r) => $r->tienePagos())
            ->filter(fn (Requisicion $r) => $user->can('delete', $r))
            ->each(fn (Requisicion $r) => \App\Services\Requisiciones\EliminarRequisicion::marcar($r, $user))
            ->count();

        $mensaje = $updated === 1 ? '1 requisición eliminada.' : "{$updated} requisiciones eliminadas.";
        if ($conPagos->isNotEmpty()) {
            $mensaje .= ' No se eliminaron '.$conPagos->count().' porque tienen pagos registrados ('.$conPagos->pluck('folio')->implode(', ').'): elimínalas una por una indicando el motivo.';
        }

        return redirect()->route('requisiciones.index')->with($conPagos->isNotEmpty() ? 'warning' : 'success', $mensaje);
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
                    'revisar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE
                        && ($user->can('approveAdjustment', $requisicion) || $user->can('rejectAdjustment', $requisicion)),
                    'aprobar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE && $user->can('approveAdjustment', $requisicion),
                    'rechazar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE && $user->can('rejectAdjustment', $requisicion),
                    'aplicar' => $a->estatus === Ajuste::ESTATUS_APROBADO && $user->can('applyAdjustment', $requisicion),
                    'cancelar' => $a->estatus === Ajuste::ESTATUS_PENDIENTE
                        && ((int) $a->user_registro_id === (int) $user->id || $user->can('approveAdjustment', $requisicion) || $user->can('rejectAdjustment', $requisicion)),
                    'cancelar_aprobacion' => $a->estatus === Ajuste::ESTATUS_APROBADO && $user->can('cancelApprovedAdjustment', $requisicion),
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
            'rechazar_pago' => $user->can('rejectPayment', $r),
            'imprimir' => $user->can('print', $r),
            'corregir' => $user->can('correct', $r),
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
            canSee: fn (User $u) => $u->can('view', $requisicion),
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

    /** Catálogos del formulario de captura (solo lo que la persona puede elegir). */
    private function captureCatalogos(CaptureContext $capture): array
    {
        return $capture->catalogos() + [
            'conceptos' => Concepto::select('id', 'nombre', 'activo')->where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    /**
     * Opciones de filtro del listado limitadas al alcance: solo corporativos,
     * sucursales, solicitantes y proveedores que aparecen en requisiciones
     * visibles (con alcance global, los catálogos activos).
     */
    private function filterCatalogos(User $user): array
    {
        $scope = AccessScope::for($user, 'requisiciones');
        $visible = Requisicion::query()->visibleTo($user);

        $corporativos = Corporativo::select('id', 'nombre', 'activo')->orderBy('nombre');
        $sucursales = Sucursal::select('id', 'nombre', 'codigo', 'corporativo_id', 'activo')->orderBy('nombre');
        if ($scope === Scope::Global) {
            $corporativos->where('activo', true);
            $sucursales->where('activo', true);
        } else {
            $corporativos->whereIn('id', (clone $visible)->select('comprador_corp_id'));
            $sucursales->whereIn('id', (clone $visible)->select('sucursal_id'));
        }

        // Solicitantes: quienes ya aparecen en requisiciones visibles y, además,
        // los colaboradores activos del alcance aunque aún no tengan ninguna
        // (un colaborador recién registrado debe salir en el filtro).
        $empleados = Empleado::query()
            ->where(function ($w) use ($visible, $scope, $user) {
                $w->whereIn('id', (clone $visible)->select('solicitante_id'));
                match ($scope) {
                    Scope::Global => $w->orWhere('activo', true),
                    Scope::Corporativo => $w->orWhere(fn ($a) => $a->where('activo', true)->whereIn(
                        'sucursal_id',
                        Sucursal::query()->where('corporativo_id', AccessScope::corporativoId($user) ?? 0)->select('id')
                    )),
                    Scope::Sucursal => $w->orWhere(fn ($a) => $a->where('activo', true)
                        ->where('sucursal_id', AccessScope::sucursalId($user) ?? 0)),
                    default => null,
                };
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'sucursal_id', 'activo'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'nombre' => trim($e->nombre.' '.$e->apellido_paterno.' '.($e->apellido_materno ?? '')),
                'sucursal_id' => $e->sucursal_id,
                'activo' => $e->activo,
            ]);

        return [
            'corporativos' => $corporativos->get(),
            'sucursales' => $sucursales->get(),
            'empleados' => $empleados,
            'conceptos' => Concepto::select('id', 'nombre', 'activo')->where('activo', true)->orderBy('nombre')->get(),
            // Solo lo que usa el filtro (sin CLABE ni banco: no hacen falta aquí).
            'proveedores' => Proveedor::select('id', 'razon_social', 'rfc', 'status')
                ->whereIn('id', (clone $visible)->select('proveedor_id'))
                ->orderBy('razon_social')->limit(1000)->get(),
            'solicitante_fijo' => $scope === Scope::Own,
        ];
    }

    /**
     * Bitácora explícita cuando alguien captura a nombre de otro colaborador:
     * quién capturó, para quién, en qué sucursal y corporativo.
     */
    private function logCapturaANombre(Requisicion $req, User $user): void
    {
        if ((int) $req->solicitante_id === (int) $user->empleado_id) {
            return;
        }

        $req->loadMissing(['solicitante:id,nombre,apellido_paterno', 'sucursal:id,nombre', 'comprador:id,nombre']);
        $solicitante = trim(($req->solicitante?->nombre ?? '').' '.($req->solicitante?->apellido_paterno ?? ''));

        $req->auditLog(
            'CAPTURA_A_NOMBRE',
            "{$user->name} capturó {$req->folio} a nombre de {$solicitante} (sucursal {$req->sucursal?->nombre}, corporativo {$req->comprador?->nombre}).",
            [
                'capturo_user_id' => [null, $user->id],
                'solicitante_id' => [null, $req->solicitante_id],
                'sucursal_id' => [null, $req->sucursal_id],
                'comprador_corp_id' => [null, $req->comprador_corp_id],
            ],
        );
    }

    private function makeFolio(): string
    {
        $prefix = 'REQ';

        do {
            $folio = $prefix.'-'.strtoupper(Str::random(5));
        } while (Requisicion::where('folio', $folio)->exists());

        return $folio;
    }

    /** Misma regla de items y montos que la corrección administrativa. */
    private function sanitizeDetalles(array $detalles): array
    {
        return DetallesCalculator::sanitize($detalles);
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
