<?php

namespace App\Http\Controllers;

use App\Http\Requests\Requisicion\BulkDestroyRequest;
use App\Http\Requests\Requisicion\RequisicionIndexRequest;
use App\Http\Requests\Requisicion\RequisicionStoreRequest;
use App\Http\Requests\Requisicion\RequisicionUpdateRequest;
use App\Http\Resources\RequisicionResource;
use App\Mail\RequisicionEnviadaMail;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Plantilla;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Sucursal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;

class RequisicionController extends Controller {

    public function index(RequisicionIndexRequest $request): Response {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));
        // Ojo: si tu FormRequest no trae reglas para algún filtro, validated() lo tira.
        // Esto asegura que sigan pasando (sanitizados) sin romper seguridad.
        $v = $request->validated();
        $raw = array_merge($request->query(), $v);
        $perPage = (int)($raw['perPage'] ?? 10);
        if ($perPage <= 0) $perPage = 10;
        if ($perPage > 100) $perPage = 100;
        $dir = strtolower((string)($raw['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $sort = (string)($raw['sort'] ?? 'created_at');
        $sort = $this->normalizeSort($sort);
        $tab = strtoupper((string)($raw['tab'] ?? 'ACTIVAS'));
        $q   = trim((string)($raw['q'] ?? ''));
        $status          = (string)($raw['status'] ?? '');
        $compradorCorpId = $raw['comprador_corp_id'] ?? null;
        $sucursalId      = $raw['sucursal_id'] ?? null;
        $solicitanteId   = $raw['solicitante_id'] ?? null;
        $conceptoId      = $raw['concepto_id'] ?? null;
        $proveedorId     = $raw['proveedor_id'] ?? null;
        $tipo            = (string)($raw['tipo'] ?? '');
        $fechaFrom = $this->safeYmd($raw['fecha_from'] ?? null);
        $fechaTo   = $this->safeYmd($raw['fecha_to'] ?? null);
        $query = Requisicion::query()
            ->with([
                'sucursal:id,nombre,codigo,corporativo_id',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social,rfc,clabe,banco,status',
                'concepto:id,nombre',
                'comprador:id,nombre',
            ]);
        // Seguridad: colaborador sólo ve lo suyo
        if ($rol === 'COLABORADOR') {
            if ($user->empleado_id) {
                $query->where('solicitante_id', (int)$user->empleado_id);
            } else {
                $query->whereRaw('1=0');
            }
        }
        // Eliminar lógico: por default NO listamos ELIMINADA (salvo tab/estatus explícito)
        if ($status === 'ELIMINADA' || $tab === 'ELIMINADAS') {
            $query->where('status', 'ELIMINADA');
        } else {
            $query->where('status', '!=', 'ELIMINADA');
        }
        // Tabs (si no hay filtro status explícito)
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
                    // ya cubierto: != ELIMINADA
                    break;
            }
        } else {
            // Status explícito (para filtros avanzados)
            $query->where('status', $status);
        }
        // Búsqueda
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('folio', 'like', "%{$q}%")
                    ->orWhere('observaciones', 'like', "%{$q}%")
                    ->orWhereHas('proveedor', fn($p) => $p->where('razon_social', 'like', "%{$q}%"))
                    ->orWhereHas('concepto', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('comprador', fn($c) => $c->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('sucursal', fn($s) => $s->where('nombre', 'like', "%{$q}%"));
            });
        }
        if (!empty($compradorCorpId)) $query->where('comprador_corp_id', (int)$compradorCorpId);
        if (!empty($sucursalId))      $query->where('sucursal_id', (int)$sucursalId);
        // Admin/Contador pueden filtrar solicitante. Colaborador ya quedó forzado arriba.
        if ($rol !== 'COLABORADOR' && !empty($solicitanteId)) {
            $query->where('solicitante_id', (int)$solicitanteId);
        }
        if (!empty($conceptoId))  $query->where('concepto_id', (int)$conceptoId);
        if (!empty($proveedorId)) $query->where('proveedor_id', (int)$proveedorId);
        if ($tipo !== '')         $query->where('tipo', $tipo);
        // Rango de fechas de CAPTURA (created_at)
        if ($fechaFrom) $query->whereDate('created_at', '>=', $fechaFrom);
        if ($fechaTo)   $query->whereDate('created_at', '<=', $fechaTo);
        $requisiciones = $query
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
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
                'fecha_from' => $fechaFrom ?? '',
                'fecha_to' => $fechaTo ?? '',
                'perPage' => $perPage,
                'sort' => $this->denormalizeSortForUi($sort),
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, Requisicion $requisicion) {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? ''));
        if ($rol === 'COLABORADOR') {
            abort_unless(
                $user->empleado_id && (int)$requisicion->solicitante_id === (int)$user->empleado_id,
                403
            );
        }
        $with = [
            'sucursal:id,nombre,codigo,corporativo_id,activo',
            'solicitante:id,nombre,apellido_paterno,apellido_materno,puesto,activo',
            'proveedor:id,razon_social,rfc,clabe,banco,status',
            'concepto:id,nombre,activo',
            'comprador:id,nombre,logo_path',
            'detalles',
            'detalles.sucursal:id,nombre,codigo',
        ];
        if (method_exists($requisicion, 'pagos')) {
            $with[] = 'pagos:id,requisicion_id,monto,fecha_pago,tipo_pago,archivo_original,archivo_path,created_at';
        }
        if (method_exists($requisicion, 'creadaPor')) {
            $with[] = 'creadaPor:id,name,email';
        }
        if (method_exists($requisicion, 'comprobantes')) {
            $with[] = 'comprobantes:id,requisicion_id,tipo_doc,monto,user_carga_id,created_at,fecha_emision,estatus,archivo_original,archivo_path';
        }
        $requisicion->load($with);
        $detalles = collect($requisicion->detalles ?? [])->map(function ($d) {
            $cantidad = (float)($d->cantidad ?? 0);
            $precio   = (float)($d->precio_unitario ?? 0);
            $subtotal = (float)($d->subtotal ?? ($cantidad * $precio));
            $iva      = (float)($d->iva ?? 0);
            $total    = (float)($d->total ?? ($subtotal + $iva));
            return [
                'id' => $d->id,
                'sucursal' => $d->sucursal ? [
                    'id' => $d->sucursal->id,
                    'nombre' => $d->sucursal->nombre,
                    'codigo' => $d->sucursal->codigo,
                ] : null,
                'cantidad' => $cantidad,
                'descripcion' => (string)($d->descripcion ?? ''),
                'precio_unitario' => $precio,
                'genera_iva' => (bool)($d->genera_iva ?? false),
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
            ];
        })->values();
        $comprobantes = collect();
        if (isset($requisicion->comprobantes)) {
            $ids = collect($requisicion->comprobantes)->pluck('user_carga_id')->filter()->unique()->values();
            $usersById = $ids->isEmpty()
                ? collect()
                : User::select('id', 'name')->whereIn('id', $ids)->get()->keyBy('id');
            $comprobantes = collect($requisicion->comprobantes ?? [])
                ->map(function ($c) use ($usersById) {
                    $u = $usersById->get($c->user_carga_id);

                    $url = null;
                    if (!empty($c->archivo_path)) {
                        $url = Storage::disk('public')->url($c->archivo_path);
                    }

                    $label = $c->archivo_original ?: ('Comprobante #' . $c->id);

                    return [
                        'id' => $c->id,
                        'tipo_doc' => (string)($c->tipo_doc ?? 'OTRO'),
                        'fecha_emision' => $c->fecha_emision,
                        'monto' => (float)($c->monto ?? 0),
                        'total' => (float)($c->monto ?? 0),
                        'estatus' => (string)($c->estatus ?? 'PENDIENTE'),
                        'user_carga' => $c->user_carga_id ? [
                            'id' => (int)$c->user_carga_id,
                            'name' => (string)($u?->name ?? ('Usuario #' . (int)$c->user_carga_id)),
                        ] : null,
                        'created_at' => optional($c->created_at)->toISOString(),

                        // para preview
                        'archivo' => $url ? [
                            'label' => $label,
                            'url' => $url,
                        ] : null,
                    ];
                })
                ->values();
        }
        $pdf = [
            'can_print' => true,
            'print_url' => null,
            'filename' => ($requisicion->folio ?? 'requisicion') . '.pdf',
        ];
        try {
            $pdf['print_url'] = route('requisiciones.print', $requisicion->id);
        } catch (\Throwable $e) {
            // ignore
        }

        $pagosFiles = [];
        if (isset($requisicion->pagos)) {
            $pagosFiles = collect($requisicion->pagos ?? [])
                ->map(function ($p) {
                    if (empty($p->archivo_path)) return null;

                    $url = Storage::disk('public')->url($p->archivo_path);
                    return [
                        'label' => $p->archivo_original ?: ('Pago #' . $p->id),
                        'url' => $url,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }
        $pdf = [
            'can_print' => true,
            'print_url' => null,
            'filename' => ($requisicion->folio ?? 'requisicion') . '.pdf',
            'files' => $pagosFiles,
        ];
        return Inertia::render('Requisiciones/Show', [
            'requisicion' => (new RequisicionResource($requisicion))->resolve(),
            'detalles' => $detalles,
            'comprobantes' => $comprobantes,
            'pdf' => $pdf,
        ]);
    }

    public function pdf(Request $request, Requisicion $requisicion) {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? ''));
        if ($rol === 'COLABORADOR') {
            abort_unless(
                $user->empleado_id && (int)$requisicion->solicitante_id === (int)$user->empleado_id,
                403
            );
        }
        $rels = [
            'sucursal:id,nombre,codigo,corporativo_id',
            'solicitante:id,nombre,apellido_paterno,apellido_materno',
            'proveedor:id,razon_social,rfc',
            'concepto:id,nombre',
            'comprador:id,nombre',
            'detalles',
            'detalles.sucursal:id,nombre,codigo',
        ];
        if (method_exists($requisicion, 'comprobantes')) {
            $rels[] = 'comprobantes:id,requisicion_id,tipo_doc,fecha_emision,monto,archivo_path,archivo_original,estatus,user_carga_id,created_at';
        }
        $requisicion->load($rels);
        $filename = ($requisicion->folio ?? 'requisicion') . '.pdf';
        $pdf = Pdf::loadView('pdfs.requisicion', [
            'requisicion' => $requisicion,
        ])->setPaper('letter');
        return $pdf->stream($filename);
    }

    public function create(Request $request): Response {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));
        $plantilla = null;
        $plantillaId = $request->query('plantilla');
        if ($plantillaId) {
            $plantilla = Plantilla::query()->with(['detalles'])->find($plantillaId);
            if ($plantilla && $rol === 'COLABORADOR' && (int)$plantilla->user_id !== (int)$user->id) {
                abort(403);
            }
        }
        return Inertia::render('Requisiciones/Create', [
            'catalogos' => $this->catalogos($user),
            'plantilla' => $plantilla,
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));
        $data = $request->validated();
        $accion = strtoupper((string)($data['accion'] ?? 'BORRADOR'));
        unset($data['accion']);
        if ($rol === 'COLABORADOR') {
            if (!$user->empleado_id) {
                return back()->withErrors(['solicitante_id' => 'Tu usuario no tiene empleado ligado.']);
            }
            $data['solicitante_id'] = (int)$user->empleado_id;
            $data['fecha_autorizacion'] = null;
        }
        $corpId     = (int)($data['comprador_corp_id'] ?? 0);
        $sucursalId = (int)($data['sucursal_id'] ?? 0);
        $corporativo = Corporativo::select('id', 'activo')->find($corpId);
        if (!$corporativo || $corporativo->activo === false) {
            return back()->withErrors(['comprador_corp_id' => 'El corporativo seleccionado no está activo o no existe.']);
        }
        $sucursal = Sucursal::select('id', 'corporativo_id', 'activo')->find($sucursalId);
        if (!$sucursal || $sucursal->activo === false) {
            return back()->withErrors(['sucursal_id' => 'La sucursal seleccionada no está activa o no existe.']);
        }
        if ((int)$sucursal->corporativo_id !== $corpId) {
            return back()->withErrors(['sucursal_id' => 'La sucursal no pertenece al corporativo seleccionado.']);
        }
        $data['comprador_corp_id'] = (int)$sucursal->corporativo_id;
        $concepto = Concepto::select('id', 'activo')->find((int)($data['concepto_id'] ?? 0));
        if (!$concepto || $concepto->activo === false) {
            return back()->withErrors(['concepto_id' => 'El concepto seleccionado no está activo o no existe.']);
        }
        if (!empty($data['proveedor_id'])) {
            $prov = Proveedor::select('id', 'status')->find((int)$data['proveedor_id']);
            if (!$prov || strtoupper((string)$prov->status) !== 'ACTIVO') {
                return back()->withErrors(['proveedor_id' => 'El proveedor seleccionado no está activo o no existe.']);
            }
        }
        $detalles = $data['detalles'] ?? [];
        unset($data['detalles']);
        if (!is_array($detalles) || count($detalles) < 1) {
            return back()->withErrors(['detalles' => 'Agrega al menos un item.']);
        }
        // Fechas en Y-m-d (sin desfases)
        $data['fecha_solicitud'] = Carbon::createFromFormat('Y-m-d', (string)$data['fecha_solicitud'])->startOfDay();
        if (!empty($data['fecha_autorizacion'])) {
            $data['fecha_autorizacion'] = Carbon::createFromFormat('Y-m-d', (string)$data['fecha_autorizacion'])->startOfDay();
        } else {
            $data['fecha_autorizacion'] = null;
        }
        $data['creada_por_user_id'] = (int)$user->id;
        $data['status'] = ($accion === 'ENVIAR') ? 'CAPTURADA' : 'BORRADOR';
        // Folio: ya NO se cicla
        $data['folio'] = $this->makeFolio();
        [$cleanDetalles, $montoSubtotal, $montoTotal] = $this->sanitizeDetalles($detalles);
        $data['monto_subtotal'] = $montoSubtotal;
        $data['monto_total']    = $montoTotal;
        $requisicion = DB::transaction(function () use ($data, $cleanDetalles) {
            $req = Requisicion::create($data);
            $req->detalles()->createMany($cleanDetalles);
            return $req;
        });
        if ($accion === 'ENVIAR') {
            try {
                Mail::to(['mrlanaweb@outlook.com', 'jesus.arizmendi@mr-lana.com'])
                    ->send(new RequisicionEnviadaMail(
                        $requisicion->fresh(['detalles', 'sucursal', 'solicitante', 'concepto', 'proveedor', 'comprador'])
                    ));
            } catch (\Throwable $e) {
                report($e);
                return redirect()
                    ->route('requisiciones.index')
                    ->with('warning', 'Requisición guardada, pero no se pudo enviar el correo. Revisa Mail.');
            }
        }
        return redirect()
            ->route('requisiciones.index')
            ->with('success', $accion === 'ENVIAR'
                ? 'Requisición enviada y guardada correctamente.'
                : 'Requisición guardada como borrador.');
    }

    public function update(RequisicionUpdateRequest $request, Requisicion $requisicion): RedirectResponse {
        $data = $request->validated();
        $detalles = $data['detalles'] ?? null;
        unset($data['detalles']);
        if (!empty($data['fecha_solicitud']) && $this->safeYmd($data['fecha_solicitud'])) {
            $data['fecha_solicitud'] = Carbon::createFromFormat('Y-m-d', (string)$data['fecha_solicitud'])->startOfDay();
        }
        if (array_key_exists('fecha_autorizacion', $data)) {
            if (!empty($data['fecha_autorizacion']) && $this->safeYmd($data['fecha_autorizacion'])) {
                $data['fecha_autorizacion'] = Carbon::createFromFormat('Y-m-d', (string)$data['fecha_autorizacion'])->startOfDay();
            } else {
                $data['fecha_autorizacion'] = null;
            }
        }
        DB::transaction(function () use ($requisicion, $data, $detalles) {
            $requisicion->update($data);
            if (is_array($detalles)) {
                [$cleanDetalles, $montoSubtotal, $montoTotal] = $this->sanitizeDetalles($detalles);

                $requisicion->update([
                    'monto_subtotal' => $montoSubtotal,
                    'monto_total' => $montoTotal,
                ]);
                $requisicion->detalles()->delete();
                $requisicion->detalles()->createMany($cleanDetalles);
            }
        });
        return redirect()->route('requisiciones.index')->with('success', 'Requisición actualizada.');
    }

    public function destroy(Request $request, Requisicion $requisicion): RedirectResponse {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));

        if ($rol === 'COLABORADOR') {
            $empleadoId = $user->empleado_id;
            abort_unless($empleadoId && (int)$requisicion->solicitante_id === (int)$empleadoId, 403);
            abort_unless($requisicion->status === 'BORRADOR', 403);
        } elseif (!in_array($rol, ['ADMIN', 'CONTADOR'], true)) {
            abort(403);
        }

        $updated = Requisicion::query()
            ->whereKey($requisicion->id)
            ->update(['status' => 'ELIMINADA']);

        abort_if($updated === 0, 500, 'No se pudo marcar como eliminada.');

        return redirect()->route('requisiciones.index')->with('success', 'Requisición eliminada.');
    }

    public function bulkDestroy(BulkDestroyRequest $request): RedirectResponse {
        $rol = strtoupper((string)($request->user()->rol ?? 'COLABORADOR'));
        abort_unless(in_array($rol, ['ADMIN', 'CONTADOR'], true), 403);
        $ids = $request->validated()['ids'];
        Requisicion::query()
            ->whereIn('id', $ids)
            ->update(['status' => 'ELIMINADA']);
        return redirect()->route('requisiciones.index')->with('success', 'Requisiciones eliminadas.');
    }

    private function catalogos($user): array {
        $rol = strtoupper((string)($user->rol ?? 'COLABORADOR'));
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
            ->orderBy('razon_social')
            ->limit(1000)
            ->get();
        $empleadosQ = Empleado::select('id', 'nombre', 'apellido_paterno', 'apellido_materno', 'sucursal_id', 'activo')
            ->where('activo', true)
            ->orderBy('nombre');
        if ($rol === 'COLABORADOR' && $user->empleado_id) {
            $empleadosQ->where('id', $user->empleado_id);
        }
        $empleados = $empleadosQ->get()->map(fn($e) => [
            'id' => $e->id,
            'nombre' => trim($e->nombre . ' ' . $e->apellido_paterno . ' ' . ($e->apellido_materno ?? '')),
            'sucursal_id' => $e->sucursal_id,
            'activo' => $e->activo,
        ]);
        return [
            'corporativos' => $corporativos,
            'sucursales'   => $sucursales,
            'empleados'    => $empleados,
            'conceptos'    => $conceptos,
            'proveedores'  => $proveedores,
        ];
    }

    private function makeFolio(): string {
        $prefix = 'REQ-' . now()->format('Ymd');
        do {
            $folio = $prefix . '-' . strtoupper(Str::random(5));
        } while (Requisicion::where('folio', $folio)->exists());

        return $folio;
    }

    private function sanitizeDetalles(array $detalles): array {
        $ivaRate = 0.16;
        $montoSubtotal = 0.0;
        $montoTotal    = 0.0;
        $clean = [];
        // Si tu tabla no se llama "detalles", esto no rompe: solo omite genera_iva si no existe.
        $hasGeneraIvaColumn = Schema::hasColumn('detalles', 'genera_iva');
        foreach ($detalles as $i => $d) {
            $cantidad = (float)($d['cantidad'] ?? 0);
            $precio   = (float)($d['precio_unitario'] ?? 0);
            $desc     = trim((string)($d['descripcion'] ?? ''));

            if ($cantidad <= 0 || $desc === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "detalles.{$i}.descripcion" => 'Cada item debe tener descripción y cantidad > 0.',
                ]);
            }
            $generaIva = (bool)($d['genera_iva'] ?? true);
            $subtotal = round($cantidad * $precio, 2);
            $iva      = $generaIva ? round($subtotal * $ivaRate, 2) : 0.00;
            $total    = round($subtotal + $iva, 2);
            $montoSubtotal += $subtotal;
            $montoTotal    += $total;
            $row = [
                'sucursal_id' => !empty($d['sucursal_id']) ? (int)$d['sucursal_id'] : null,
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

    private function safeYmd($v): ?string {
        if (!is_string($v) || $v === '') return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private function normalizeSort(string $sort): string {
        // Acepta aliases “amigables” o camelCase que te puedan estar llegando
        $map = [
            'fecha_captura' => 'created_at',
            'createdAt' => 'created_at',
            'created_at' => 'created_at',
            'folio' => 'folio',
            'monto_total' => 'monto_total',
            'status' => 'status',
            'tipo' => 'tipo',
            'id' => 'id',
        ];

        $sort = trim($sort);
        return $map[$sort] ?? 'created_at';
    }

    private function denormalizeSortForUi(string $sort): string {
        // Mantén compatibilidad si tu front usa created_at
        return $sort;
    }

    public function ajustes(Requisicion $requisicion): Response {
        $requisicion->load(['ajustes' => fn($q) => $q->orderByDesc('id')]);
        return Inertia::render('Requisiciones/Ajustes', [
            'requisicionId' => $requisicion->id,
            'ajustes' => $requisicion->ajustes->map(fn($a) => [
                'id' => $a->id,
                'tipo' => $a->tipo,
                'monto' => (string) $a->monto,
                'descripcion' => $a->descripcion,
                'fecha' => optional($a->fecha)->format('Y-m-d'),
                'estatus' => $a->estatus,
            ])->values(),
        ]);
    }

}
