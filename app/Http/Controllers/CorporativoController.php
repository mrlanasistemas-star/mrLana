<?php

namespace App\Http\Controllers;

use App\Models\Corporativo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Http\Resources\CorporativoResource;
use App\Http\Requests\Corporativo\StoreCorporativoRequest;
use App\Http\Requests\Corporativo\UpdateCorporativoRequest;

class CorporativoController extends Controller {

    // Listado con filtros y paginación
    public function index(Request $request){

        // Declaración de variables de filtro
        $q       = trim((string) $request->query('q', ''));
        $activo  = (string) $request->query('activo', '1');
        $perPage = (int) $request->query('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;

        // Consulta base
        $query = Corporativo::query()->orderByDesc('id');

        // Filtro de búsqueda por string en varios campos
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                  ->orWhere('rfc', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('telefono', 'like', "%{$q}%")
                  ->orWhere('codigo', 'like', "%{$q}%");
            });
        }

        // Filtro por activo (1 = activos, 0 = inactivos)
        if ($activo === '1' || $activo === '0') {
            $query->where('activo', $activo === '1');
        }

        // Obtener paginador con resultados
        $paginator = $query->paginate($perPage)->withQueryString();

        // Retornar datos a la vista
        return Inertia::render('Corporativos/Index', [
            'corporativos' => [
                'data' => CorporativoResource::collection($paginator)->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                    'links'        => $paginator->linkCollection(),
                ],
            ],
            'filters' => [
                'q'        => $q,
                'activo'   => $activo,
                'per_page' => $perPage,
            ],
        ]);
    }

    // Metodo para registrar nuevo corporativo
    public function store(StoreCorporativoRequest $request){
        $data = $request->validated();

        // Si llega archivo directo (opcional)
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('corporativos/logos', 'public');
            $data['logo_path'] = Storage::url($path); // /storage/...
        }

        unset($data['logo']); // no es columna

        // IMPORTANTE: aquí ya viene logo_path
        Corporativo::create($data);

        return back()->with('success', 'Corporativo registrado correctamente.');
    }

    // Metodo para actualizar corporativo
    public function update(UpdateCorporativoRequest $request, Corporativo $corporativo)
    {
        $data = $request->validated();

        unset($data['activo']);

        $oldLogo = $corporativo->logo_path;

        // Si llega archivo directo (opcional)
        if ($request->hasFile('logo')) {
            $this->deletePublicLogoIfExists($oldLogo);

            $path = $request->file('logo')->store('corporativos/logos', 'public');
            $data['logo_path'] = Storage::url($path);
        }

        // Si llega logo_path (tu flujo real)
        if (array_key_exists('logo_path', $data)) {
            $newLogo = $data['logo_path'];

            // Cambió por otra ruta -> borra el anterior
            if ($newLogo && $newLogo !== $oldLogo) {
                $this->deletePublicLogoIfExists($oldLogo);
            }

            // Se quitó el logo -> borra el anterior
            if ($newLogo === null && $oldLogo) {
                $this->deletePublicLogoIfExists($oldLogo);
            }
        }

        unset($data['logo']);

        $corporativo->update($data);

        return back()->with('success', 'Corporativo actualizado correctamente.');
    }

    // Metodo para eliminar corporativo (baja lógica + cascada)
    public function destroy(Corporativo $corporativo) {
        if (!$corporativo->activo) {
            return redirect()
                ->route('corporativos.index')
                ->with('success', 'El corporativo ya se encontraba dado de baja.');
        }

        // Realizar la baja en una transacción
        DB::transaction(function () use ($corporativo) {

            // 1) Baja corporativo
            $corporativo->update(['activo' => false]);

            // 2) Baja sucursales relacionadas (en lote)
            $corporativo->sucursales()
                ->where('activo', true)
                ->updateEach(['activo' => false]);

            // 3) Baja en areas relacionadas (en lote)
            $corporativo->areas()
                ->where('activo', true)
                ->updateEach(['activo' => false]);

            // $corporativo->requisicionesComprador()->where('activo', true)->update(['activo' => false]);
        });

        return back()->with('success', 'Corporativo dado de baja y sus sucursales también.');
    }

    // Metodo para activar corporativo junto a sus relaciones
    public function activate(Request $request, Corporativo $corporativo) {

        // 1) Validación: sucursales + áreas (ambas opcionales)
        $validated = $request->validate([
            'sucursal_ids'   => ['nullable', 'array'],
            'sucursal_ids.*' => ['integer'],

            'area_ids'       => ['nullable', 'array'],
            'area_ids.*'     => ['integer'],
        ]);

        // 2) Normaliza IDs (int + únicos)
        $sucursalIds = collect($validated['sucursal_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        $areaIds = collect($validated['area_ids'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        // 3) Transacción
        DB::transaction(function () use ($corporativo, $sucursalIds, $areaIds) {
            // Activar corporativo
            $corporativo->update(['activo' => true]);

            // Activar SOLO sucursales seleccionadas (y que sean del corporativo)
            if ($sucursalIds->isNotEmpty()) {
                $corporativo->sucursales()
                    ->whereIn('id', $sucursalIds->all())
                    ->updateEach(['activo' => true]);
            }

            // Activar SOLO áreas seleccionadas (y que sean del corporativo)
            if ($areaIds->isNotEmpty()) {
                $corporativo->areas()
                    ->whereIn('id', $areaIds->all())
                    ->updateEach(['activo' => true]);
            }
        });

        return back()->with(
            'success',
            'Corporativo activado. Sucursales/áreas actualizadas según selección.'
        );
    }

    // Listado de sucursales inactivas de un corporativo
    public function inactiveSucursales(Corporativo $corporativo)
    {
        $rows = $corporativo->sucursales()
            ->select('id','nombre','codigo','ciudad','estado','activo')
            ->where('activo', false)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    // Listado de áreas inactivas de un corporativo
    public function inactiveAreas(Corporativo $corporativo)
    {
        $rows = $corporativo->areas()
            ->select('id','nombre','corporativo_id','activo')
            ->where('activo', false)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    // Metodo para subir logo
    public function uploadLogo(Request $request){
        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $path = $request->file('logo')->store('corporativos/logos', 'public');

        return response()->json([
            'logo_path' => Storage::url($path), // /storage/...
        ]);
    }

    // Elimina el logo del disco público si existe
    private function deletePublicLogoIfExists(?string $logoPath): void{
        if (!$logoPath) return;

        $clean = str_starts_with($logoPath, '/storage/')
            ? substr($logoPath, 9)
            : (str_starts_with($logoPath, 'storage/') ? substr($logoPath, 8) : null);

        if ($clean) {
            Storage::disk('public')->delete($clean);
        }
    }

}
