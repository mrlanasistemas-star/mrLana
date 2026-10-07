<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 200);
        $limit = max(1, min($limit, 500));
        $folios = Folio::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('folio', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'folio', 'monto_total']);

        return response()->json($folios);
    }

    /**
     * Crear folio
     * POST /folios  -> folios.store
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:100', 'unique:folios,folio'],
            'monto_total' => ['nullable', 'numeric', 'min:0'],
        ]);
        $folio = Folio::create([
            'folio' => $data['folio'],
            'monto_total' => $data['monto_total'] ?? null,
            'user_registro_id' => $request->user()->id,
        ]);
        // Si es Inertia: regresar redirect (no JSON)
        if ($request->header('X-Inertia')) {
            return redirect()->back(303)->with([
                'success' => 'Folio creado.',
                'folio_created_id' => $folio->id,
            ]);
        }

        // Si NO es Inertia (API/axios): sí puedes devolver JSON
        return response()->json([
            'ok' => true,
            'message' => 'Folio creado.',
            'data' => $folio->only(['id', 'folio', 'monto_total']),
        ], 201);
    }

    /**
     * Actualizar folio
     * PATCH /folios/{folio} -> folios.update
     */
    /**
     * Requiere el permiso "Editar folios de factura" (middleware de la ruta).
     */
    public function update(Request $request, Folio $folio)
    {
        $data = $request->validate([
            'folio' => [
                'required',
                'string',
                'max:100',
                Rule::unique('folios', 'folio')->ignore($folio->id),
            ],
            'monto_total' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);
        $folio->update([
            'folio' => $data['folio'],
            'monto_total' => $data['monto_total'] ?? null,
        ]);
        // Si viene de Inertia: redirect con flash
        if ($request->header('X-Inertia')) {
            return redirect()->back(303)->with([
                'success' => 'Folio actualizado.',
                'folio_updated_id' => $folio->id,
            ]);
        }

        // Si NO es Inertia (API): JSON
        return response()->json([
            'ok' => true,
            'message' => 'Folio actualizado.',
            'data' => $folio->only(['id', 'folio', 'monto_total']),
        ]);
    }
}
