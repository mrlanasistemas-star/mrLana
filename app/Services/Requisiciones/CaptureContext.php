<?php

namespace App\Services\Requisiciones;

use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Permissions\AccessScope;
use App\Support\Permissions\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de captura de requisiciones y plantillas según los permisos
 * especiales (nunca según el permiso de ver requisiciones):
 *
 * - Sin permisos especiales: solicitante = colaborador de la cuenta;
 *   sucursal = su sucursal; corporativo = el de esa sucursal.
 * - "Elegir sucursal de mi corporativo": cualquier sucursal activa de su
 *   corporativo (el corporativo queda fijo).
 * - "Elegir otro corporativo comprador" + "Elegir sucursal de cualquier
 *   corporativo": cualquier corporativo y sucursal activos.
 * - "Elegir solicitante": colaboradores activos de las sucursales que puede
 *   elegir. Sin él, el solicitante es siempre el colaborador de la cuenta.
 *
 * El servidor reemplaza o rechaza cualquier valor fuera de estas reglas,
 * aunque se manipule la petición.
 */
final class CaptureContext
{
    private function __construct(
        private User $user,
        public readonly Scope $sucursalScope,
        public readonly bool $chooseSolicitante,
        public readonly ?Empleado $empleado,
        public readonly ?int $sucursalId,
        public readonly ?int $corporativoId,
    ) {}

    public static function for(User $user): self
    {
        $global = $user->can('requisiciones.elegir_corporativo') && $user->can('requisiciones.elegir_sucursal_global');

        $scope = match (true) {
            $global => Scope::Global,
            $user->can('requisiciones.elegir_sucursal_corporativo') && AccessScope::corporativoId($user) !== null => Scope::Corporativo,
            AccessScope::sucursalId($user) !== null => Scope::Sucursal,
            default => Scope::None,
        };

        return new self(
            $user,
            $scope,
            $user->can('requisiciones.elegir_solicitante'),
            AccessScope::empleado($user),
            AccessScope::sucursalId($user),
            AccessScope::corporativoId($user),
        );
    }

    public function corporativoFijo(): bool
    {
        return $this->sucursalScope !== Scope::Global;
    }

    public function sucursalFija(): bool
    {
        return $this->sucursalScope === Scope::Sucursal || $this->sucursalScope === Scope::None;
    }

    public function solicitanteFijo(): bool
    {
        return ! $this->chooseSolicitante;
    }

    /** Sucursales activas que se pueden elegir. */
    public function sucursales(): Builder
    {
        $q = Sucursal::query()->where('sucursals.activo', true);

        return match ($this->sucursalScope) {
            Scope::Global => $q,
            Scope::Corporativo => $q->where('sucursals.corporativo_id', $this->corporativoId),
            Scope::Sucursal => $q->whereKey($this->sucursalId),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /** Corporativos activos que se pueden elegir como comprador. */
    public function corporativos(): Builder
    {
        $q = Corporativo::query()->where('corporativos.activo', true);

        return $this->sucursalScope === Scope::Global
            ? $q
            : $q->whereKey($this->corporativoId ?? 0);
    }

    /** Colaboradores activos que pueden ser solicitantes. */
    public function solicitantes(): Builder
    {
        $q = Empleado::query()->where('empleados.activo', true);

        if ($this->solicitanteFijo()) {
            return $q->whereKey($this->empleado?->id ?? 0);
        }

        return $this->sucursalScope === Scope::Global
            ? $q
            : $q->whereIn('empleados.sucursal_id', $this->sucursales()->select('sucursals.id'));
    }

    /** Proveedores activos que puede utilizar. */
    public function proveedores(): Builder
    {
        return Proveedor::query()->usableBy($this->user);
    }

    /**
     * Valida y normaliza cabecera y detalles de una requisición o plantilla.
     * En edición ($current) se aceptan los valores que el registro ya tenía,
     * aunque hoy estén fuera de las reglas (p. ej. creado por otra persona).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $current  solicitante_id, sucursal_id, comprador_corp_id, proveedor_id actuales
     * @return array<string, mixed>
     */
    public function enforce(array $data, ?array $current = null, bool $partial = false): array
    {
        $keep = fn (string $field, $value) => $current !== null
            && $value !== null && (int) $value === (int) ($current[$field] ?? 0);

        // Solicitante
        $solicitante = $data['solicitante_id'] ?? null;
        if ($this->solicitanteFijo()) {
            $fixed = $current['solicitante_id'] ?? $this->empleado?->id;
            if ($fixed === null) {
                throw ValidationException::withMessages(['solicitante_id' => 'Tu usuario no está vinculado a un colaborador. Pide a un administrador que lo vincule.']);
            }
            if ($solicitante !== null && $solicitante !== '' && (int) $solicitante !== (int) $fixed) {
                throw ValidationException::withMessages(['solicitante_id' => 'No tienes permiso para registrar a nombre de otro solicitante.']);
            }
            $data['solicitante_id'] = (int) $fixed;
        } elseif ($solicitante !== null && $solicitante !== '') {
            if (! $keep('solicitante_id', $solicitante) && ! $this->solicitantes()->whereKey((int) $solicitante)->exists()) {
                throw ValidationException::withMessages(['solicitante_id' => 'El solicitante no está activo o está fuera de las sucursales que puedes elegir.']);
            }
        } elseif (! $partial) {
            throw ValidationException::withMessages(['solicitante_id' => 'El solicitante es obligatorio.']);
        }

        // Sucursal y corporativo
        $sucursalId = $data['sucursal_id'] ?? null;
        if ($this->sucursalFija() && $current === null) {
            if ($this->sucursalId === null) {
                throw ValidationException::withMessages(['sucursal_id' => 'Tu colaborador no tiene sucursal asignada. Pide a un administrador que la registre.']);
            }
            if ($sucursalId !== null && $sucursalId !== '' && (int) $sucursalId !== $this->sucursalId) {
                throw ValidationException::withMessages(['sucursal_id' => 'No tienes permiso para registrar en otra sucursal.']);
            }
            $sucursalId = $this->sucursalId;
        }

        if ($sucursalId !== null && $sucursalId !== '') {
            $sucursal = Sucursal::query()->select('id', 'corporativo_id', 'activo')->find((int) $sucursalId);
            $allowed = $keep('sucursal_id', $sucursalId)
                || ($sucursal && $this->sucursales()->whereKey($sucursal->id)->exists());

            if (! $sucursal || ! $allowed) {
                throw ValidationException::withMessages(['sucursal_id' => $this->corporativoFijo()
                    ? 'La sucursal no está activa o no pertenece a las sucursales que puedes elegir.'
                    : 'La sucursal seleccionada no está activa o no existe.']);
            }

            $corp = $data['comprador_corp_id'] ?? null;
            if ($corp !== null && $corp !== '' && (int) $corp !== (int) $sucursal->corporativo_id) {
                throw ValidationException::withMessages(['sucursal_id' => 'La sucursal no pertenece al corporativo seleccionado.']);
            }
            if ($this->corporativoFijo() && ! $keep('comprador_corp_id', $sucursal->corporativo_id) && (int) $sucursal->corporativo_id !== (int) $this->corporativoId) {
                throw ValidationException::withMessages(['comprador_corp_id' => 'No tienes permiso para elegir otro corporativo comprador.']);
            }

            $data['sucursal_id'] = (int) $sucursal->id;
            $data['comprador_corp_id'] = (int) $sucursal->corporativo_id;
        } elseif (! $partial) {
            throw ValidationException::withMessages(['sucursal_id' => 'La sucursal es obligatoria.']);
        }

        // Proveedor que puede utilizar
        $proveedor = $data['proveedor_id'] ?? null;
        if ($proveedor !== null && $proveedor !== '' && ! $keep('proveedor_id', $proveedor)
            && ! $this->proveedores()->whereKey((int) $proveedor)->exists()) {
            throw ValidationException::withMessages(['proveedor_id' => 'Selecciona un proveedor activo que tengas autorizado.']);
        }

        // Detalles: cada item se carga a una sucursal del mismo corporativo comprador.
        if (isset($data['detalles']) && is_array($data['detalles'])) {
            foreach ($data['detalles'] as $i => $d) {
                $det = $d['sucursal_id'] ?? null;
                if ($det === null || $det === '') {
                    $data['detalles'][$i]['sucursal_id'] = $data['sucursal_id'] ?? null;

                    continue;
                }
                if ((int) $det === (int) ($data['sucursal_id'] ?? 0)) {
                    continue;
                }
                $ok = $this->sucursales()->whereKey((int) $det)
                    ->where('sucursals.corporativo_id', $data['comprador_corp_id'] ?? 0)->exists();
                if (! $ok) {
                    throw ValidationException::withMessages(["detalles.{$i}.sucursal_id" => 'La sucursal del item debe ser una sucursal que puedas elegir del mismo corporativo comprador.']);
                }
            }
        }

        return $data;
    }

    /**
     * Datos de captura para la interfaz: solo los catálogos que la persona
     * puede usar (no se envían catálogos completos cuando no los necesita).
     *
     * @return array<string, mixed>
     */
    public function catalogos(): array
    {
        $empleados = $this->solicitantes()
            ->orderBy('nombre')->orderBy('apellido_paterno')
            ->limit(2000)
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno', 'sucursal_id', 'puesto', 'activo'])
            ->map(fn (Empleado $e) => [
                'id' => $e->id,
                'nombre' => trim($e->nombre.' '.$e->apellido_paterno.' '.($e->apellido_materno ?? '')),
                'sucursal_id' => $e->sucursal_id,
                'puesto' => $e->puesto,
                'activo' => (bool) $e->activo,
            ]);

        return [
            'corporativos' => $this->corporativos()->orderBy('nombre')->get(['id', 'nombre', 'activo']),
            'sucursales' => $this->sucursales()->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'corporativo_id', 'activo']),
            'empleados' => $empleados,
            'proveedores' => $this->proveedores()->orderBy('razon_social')->limit(1000)
                ->get(['id', 'razon_social', 'rfc', 'clabe', 'banco', 'status']),
            'captura' => [
                'solicitante_fijo' => $this->solicitanteFijo(),
                'sucursal_fija' => $this->sucursalFija(),
                'corporativo_fijo' => $this->corporativoFijo(),
                'alcance' => $this->sucursalScope->key(),
                'propio' => $this->empleado ? [
                    'solicitante_id' => $this->empleado->id,
                    'sucursal_id' => $this->sucursalId,
                    'corporativo_id' => $this->corporativoId,
                ] : null,
            ],
            // Compatibilidad con la interfaz anterior.
            'solicitante_fijo' => $this->solicitanteFijo(),
        ];
    }
}
