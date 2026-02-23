<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plantilla\PlantillaStoreRequest;
use App\Http\Requests\Plantilla\PlantillaUpdateRequest;
use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Plantilla;
use App\Models\Proveedor;
use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlantillaController extends Controller {

    /**
     * Listado de plantillas.
     * COLABORADOR: sólo las suyas. ADMIN/CONTADOR: todas.
     */
    public function index(Request $request): Response {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));

        $filters = [
            'q'       => trim((string)$request->input('q', '')),
            'status'  => trim((string)$request->input('status', '')),
            'perPage' => (int)$request->input('perPage', 20),
            'sort'    => trim((string)$request->input('sort', 'nombre')),
            'dir'     => strtolower((string)$request->input('dir', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];

        if ($filters['perPage'] <= 0) $filters['perPage'] = 20;
        if ($filters['perPage'] > 100) $filters['perPage'] = 100;

        $allowedSort = ['nombre', 'status', 'monto_total', 'created_at', 'updated_at'];
        if (!in_array($filters['sort'], $allowedSort, true)) {
            $filters['sort'] = 'nombre';
        }

        $query = Plantilla::query()
            ->with([
                'sucursal:id,nombre,codigo',
                'solicitante:id,nombre,apellido_paterno,apellido_materno',
                'proveedor:id,razon_social',
                'concepto:id,nombre',
            ])
            ->when($rol === 'COLABORADOR', fn($qq) => $qq->where('user_id', $user->id))
            ->when($filters['q'] !== '', function ($qq) use ($filters) {
                $q = $filters['q'];
                $qq->where(function ($sub) use ($q) {
                    $sub->where('nombre', 'like', "%{$q}%")
                        ->orWhere('observaciones', 'like', "%{$q}%");
                });
            })
            ->when($filters['status'] !== '', fn($qq) => $qq->where('status', $filters['status']))
            ->orderBy($filters['sort'], $filters['dir']);

        $paginator = $query->paginate($filters['perPage'])->withQueryString();

        // data
        $data = $paginator->getCollection()->map(function (Plantilla $p) {
            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'status' => $p->status,
                'monto_subtotal' => (string)$p->monto_subtotal,
                'monto_total' => (string)$p->monto_total,
                'fecha_solicitud' => $p->fecha_solicitud ? (string)$p->fecha_solicitud : null,
                'fecha_autorizacion' => $p->fecha_autorizacion ? (string)$p->fecha_autorizacion : null,

                'sucursal' => $p->sucursal ? [
                    'id' => $p->sucursal->id,
                    'nombre' => $p->sucursal->nombre,
                    'codigo' => $p->sucursal->codigo,
                ] : null,

                'solicitante' => $p->solicitante ? [
                    'id' => $p->solicitante->id,
                    'nombre' => trim(
                        $p->solicitante->nombre . ' ' .
                        ($p->solicitante->apellido_paterno ?? '') . ' ' .
                        ($p->solicitante->apellido_materno ?? '')
                    ),
                ] : null,

                'proveedor' => $p->proveedor ? [
                    'id' => $p->proveedor->id,
                    'nombre' => (string)$p->proveedor->razon_social,
                ] : null,

                'concepto' => $p->concepto ? [
                    'id' => $p->concepto->id,
                    'nombre' => (string)$p->concepto->nombre,
                ] : null,

                'observaciones' => $p->observaciones,
            ];
        })->values()->all();

        // links (del paginator)
        $linksRaw = $paginator->toArray()['links'] ?? [];
        $links = collect($linksRaw)->map(function ($l) {
            $label = (string)($l['label'] ?? '');
            $clean = trim(strip_tags(html_entity_decode($label)));

            // opcional: español sin cambiar locale global
            if (str_contains($clean, 'Previous')) {
                $label = str_replace('Previous', 'Atrás', $label);
                $clean = 'Atrás';
            }
            if (str_contains($clean, 'Next')) {
                $label = str_replace('Next', 'Siguiente', $label);
                $clean = 'Siguiente';
            }

            return [
                'url' => $l['url'] ?? null,
                'label' => $label,
                'active' => (bool)($l['active'] ?? false),
                'cleanLabel' => $clean,
            ];
        })->values()->all();

        // meta
        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return Inertia::render('Plantillas/Index', [
            'plantillas' => [
                'data' => $data,
                'links' => $links,
                'meta' => $meta,
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Formulario create.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Plantillas/Create', [
            'catalogos' => $this->catalogos(),
        ]);
    }

    /**
     * Guardar plantilla.
     */
    public function store(PlantillaStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $detalles = $data['detalles'] ?? [];
        unset($data['detalles']);

        // Deducir comprador_corp_id desde sucursal si no viene
        if (!empty($data['sucursal_id']) && empty($data['comprador_corp_id'])) {
            $sucursal = Sucursal::select('id', 'corporativo_id')->find($data['sucursal_id']);
            $data['comprador_corp_id'] = $sucursal?->corporativo_id;
        }

        $data['user_id'] = $user->id;
        $data['status']  = 'BORRADOR';

        $plantilla = Plantilla::create($data);

        if (!empty($detalles)) {
            $plantilla->detalles()->createMany($detalles);
        }

        return redirect()->route('plantillas.index')
            ->with('success', 'Plantilla guardada.');
    }

    /**
     * Edit form (Inertia).
     * Aquí NO mandamos Resource para evitar "data wrapper" y que Vue reciba todo plano.
     */
    public function edit(Request $request, Plantilla $plantilla): Response
    {
        $rol = $this->guardPlantillaAccess($request, $plantilla);

        $plantilla->load(['detalles', 'sucursal', 'solicitante', 'proveedor', 'concepto']);

        return Inertia::render('Plantillas/Edit', [
            'plantilla' => $this->formatPlantillaForForm($plantilla),
            'catalogos' => $this->catalogos(),
            'routes' => [
                'index'  => route('plantillas.index'),
                'update' => route('plantillas.update', $plantilla),
            ],
            'ui' => ['rol' => $rol],
        ]);
    }

    /**
     * Update.
     */
    public function update(PlantillaUpdateRequest $request, Plantilla $plantilla): RedirectResponse
    {
        $this->guardPlantillaAccess($request, $plantilla);

        $data = $request->validated();
        $detalles = $data['detalles'] ?? [];
        unset($data['detalles']);

        if (!empty($data['sucursal_id']) && empty($data['comprador_corp_id'])) {
            $sucursal = Sucursal::select('id', 'corporativo_id')->find($data['sucursal_id']);
            $data['comprador_corp_id'] = $sucursal?->corporativo_id;
        }

        $plantilla->update($data);

        // Estrategia simple: reemplazo total de detalles
        $plantilla->detalles()->delete();
        if (!empty($detalles)) {
            $plantilla->detalles()->createMany($detalles);
        }

        return redirect()->route('plantillas.index')
            ->with('success', 'Plantilla actualizada.');
    }

    /**
     * Soft delete (status).
     */
    public function destroy(Request $request, Plantilla $plantilla): RedirectResponse
    {
        $this->guardPlantillaAccess($request, $plantilla);

        $plantilla->update(['status' => 'ELIMINADA']);

        return redirect()->route('plantillas.index')
            ->with('success', 'Plantilla eliminada.');
    }

    /**
     * JSON show para precargar (uso futuro en requisiciones).
     * Mandamos la misma estructura "plana" que edit().
     */
    public function show(Request $request, Plantilla $plantilla)
    {
        $this->guardPlantillaAccess($request, $plantilla);

        $plantilla->load(['detalles', 'sucursal', 'solicitante', 'proveedor', 'concepto']);

        return response()->json([
            'plantilla' => $this->formatPlantillaForForm($plantilla),
        ]);
    }

    public function reactivate(Request $request, Plantilla $plantilla): RedirectResponse
    {
        $this->guardPlantillaAccess($request, $plantilla);

        $plantilla->update(['status' => 'BORRADOR']);

        return redirect()->route('plantillas.index')
            ->with('success', 'Plantilla reactivada.');
    }

    /**
     * Catálogos para Create/Edit.
     */
    private function catalogos(): array
    {
        $corporativos = Corporativo::select('id', 'nombre', 'activo')
            ->orderBy('nombre')
            ->get();

        $sucursales = Sucursal::select('id', 'nombre', 'codigo', 'corporativo_id', 'activo')
            ->orderBy('nombre')
            ->get();

        $conceptos = Concepto::select('id', 'nombre', 'activo')
            ->orderBy('nombre')
            ->get();

        $proveedores = Proveedor::select('id', 'razon_social')
            ->orderBy('razon_social')
            ->limit(500)
            ->get()
            ->map(fn($p) => ['id' => $p->id, 'nombre' => $p->razon_social])
            ->values();

        $empleados = Empleado::select('id', 'nombre', 'apellido_paterno', 'apellido_materno', 'sucursal_id', 'puesto', 'activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'nombre'      => trim($e->nombre . ' ' . $e->apellido_paterno . ' ' . ($e->apellido_materno ?? '')),
                'sucursal_id' => $e->sucursal_id,
                'puesto'      => $e->puesto,
                'activo'      => $e->activo,
            ])
            ->values();

        return [
            'corporativos' => $corporativos,
            'sucursales'   => $sucursales,
            'empleados'    => $empleados,
            'conceptos'    => $conceptos,
            'proveedores'  => $proveedores,
        ];
    }

    /**
     * Asegura acceso por rol.
     * Retorna el rol por si lo quieres en UI.
     */
    private function guardPlantillaAccess(Request $request, Plantilla $plantilla): string
    {
        $user = $request->user();
        $rol  = strtoupper((string)($user->rol ?? 'COLABORADOR'));

        if ($rol === 'COLABORADOR' && (int)$plantilla->user_id !== (int)$user->id) {
            abort(403);
        }

        return $rol;
    }

    /**
     * Normaliza la plantilla para que el frontend NO dependa de Resources.
     * Esto es lo que tu usePlantillaCreate() necesita para precargar todo.
     */
    private function formatPlantillaForForm(Plantilla $plantilla): array
    {
        // Fecha para tu DatePicker (Y-m-d)
        $fechaSolicitud = $plantilla->fecha_solicitud
            ? \Carbon\Carbon::parse($plantilla->fecha_solicitud)->format('Y-m-d')
            : '';

        $fechaAutorizacion = $plantilla->fecha_autorizacion
            ? \Carbon\Carbon::parse($plantilla->fecha_autorizacion)->format('Y-m-d')
            : '';

        $detalles = collect($plantilla->detalles ?? [])
            ->map(fn($d) => [
                'id' => $d->id ?? null,
                'sucursal_id' => $d->sucursal_id ?? null,
                'cantidad' => (float)($d->cantidad ?? 1),
                'descripcion' => (string)($d->descripcion ?? ''),
                'precio_unitario' => (float)($d->precio_unitario ?? 0),
                'genera_iva' => (bool)($d->genera_iva ?? true),
                'subtotal' => (float)($d->subtotal ?? 0),
                'iva' => (float)($d->iva ?? 0),
                'total' => (float)($d->total ?? 0),
            ])
            ->values()
            ->all();

        return [
            'id' => $plantilla->id,
            'nombre' => (string)$plantilla->nombre,
            'status' => (string)$plantilla->status,

            // ids directos para precargar selects
            'sucursal_id' => $plantilla->sucursal_id,
            'solicitante_id' => $plantilla->solicitante_id,
            'comprador_corp_id' => $plantilla->comprador_corp_id,
            'proveedor_id' => $plantilla->proveedor_id,
            'concepto_id' => $plantilla->concepto_id,

            'monto_subtotal' => (float)($plantilla->monto_subtotal ?? 0),
            'monto_total' => (float)($plantilla->monto_total ?? 0),

            'fecha_solicitud' => $fechaSolicitud,
            'fecha_autorizacion' => $fechaAutorizacion,
            'observaciones' => $plantilla->observaciones,

            'detalles' => $detalles,
        ];
    }
}
