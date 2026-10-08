<?php

namespace Tests\Concerns;

use App\Models\Concepto;
use App\Models\Corporativo;
use App\Models\Empleado;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\BusinessDate;
use App\Support\Permissions\PermissionCatalog;

/**
 * Datos mínimos del ERP para pruebas de feature.
 */
trait CreatesErpData
{
    protected Corporativo $corporativo;

    protected Sucursal $sucursal;

    protected Concepto $concepto;

    protected function setUpCatalogos(): void
    {
        $this->corporativo = Corporativo::create(['nombre' => 'Corporativo Prueba', 'activo' => true]);
        $this->sucursal = Sucursal::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Matriz', 'activo' => true]);
        $this->concepto = Concepto::create(['nombre' => 'Papelería', 'activo' => true]);
    }

    protected function makeEmpleado(array $attrs = []): Empleado
    {
        return Empleado::create($attrs + [
            'sucursal_id' => $this->sucursal->id,
            'nombre' => 'Ana',
            'apellido_paterno' => 'López',
            'activo' => true,
        ]);
    }

    protected function makeUser(string $role, bool $withEmpleado = true, array $attrs = []): User
    {
        $factory = match ($role) {
            PermissionCatalog::ROLE_ADMIN => User::factory()->admin(),
            PermissionCatalog::ROLE_CONTABILIDAD => User::factory()->contabilidad(),
            PermissionCatalog::ROLE_COLABORADOR => User::factory()->colaborador(),
            default => User::factory()->withRole($role),
        };

        return $factory->create($attrs + [
            'empleado_id' => $withEmpleado ? $this->makeEmpleado()->id : null,
        ]);
    }

    protected function makeProveedor(User $owner, string $status = 'ACTIVO'): Proveedor
    {
        return Proveedor::create([
            'user_duenio_id' => $owner->id,
            'razon_social' => 'Proveedor '.uniqid(),
            'rfc' => 'XAXX010101000',
            'clabe' => '012345678901234567',
            'banco' => 'BBVA',
            'status' => $status,
        ]);
    }

    protected function makeRequisicion(User $creator, array $attrs = []): Requisicion
    {
        $req = Requisicion::create($attrs + [
            'folio' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
            'status' => 'CAPTURADA',
            'solicitante_id' => $creator->empleado_id ?? $this->makeEmpleado()->id,
            'sucursal_id' => $this->sucursal->id,
            'comprador_corp_id' => $this->corporativo->id,
            'proveedor_id' => $this->makeProveedor($creator)->id,
            'concepto_id' => $this->concepto->id,
            'monto_subtotal' => 1000,
            'monto_total' => 1160,
            'fecha_solicitud' => BusinessDate::today(),
            'creada_por_user_id' => $creator->id,
        ]);

        $req->detalles()->create([
            'cantidad' => 1, 'descripcion' => 'Hojas', 'precio_unitario' => 1000,
            'subtotal' => 1000, 'iva' => 160, 'total' => 1160, 'genera_iva' => true,
        ]);

        return $req;
    }

    /** Payload válido para registrar una requisición. */
    protected function requisicionPayload(User $user, array $overrides = []): array
    {
        return array_replace([
            'solicitante_id' => $user->empleado_id,
            'comprador_corp_id' => $this->corporativo->id,
            'sucursal_id' => $this->sucursal->id,
            'concepto_id' => $this->concepto->id,
            'proveedor_id' => $this->makeProveedor($user)->id,
            'fecha_solicitud' => BusinessDate::todayString(),
            'fecha_pago_esperada' => null,
            'observaciones' => 'Prueba',
            'detalles' => [[
                'sucursal_id' => $this->sucursal->id,
                'cantidad' => 2,
                'descripcion' => 'Lápices',
                'precio_unitario' => 50,
                'genera_iva' => true,
            ]],
        ], $overrides);
    }

    protected function roleNamed(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    /* ---------------------------------------------------------------
     | Alcances: varios corporativos y roles con permisos exactos
     --------------------------------------------------------------- */

    protected Sucursal $sucursal2;

    protected Corporativo $corporativoB;

    protected Sucursal $sucursalB;

    /** Corporativo A (con dos sucursales) y corporativo B (con una). */
    protected function setUpOrganizacion(): void
    {
        $this->setUpCatalogos();
        $this->sucursal2 = Sucursal::create(['corporativo_id' => $this->corporativo->id, 'nombre' => 'Norte', 'activo' => true]);
        $this->corporativoB = Corporativo::create(['nombre' => 'Corporativo B', 'activo' => true]);
        $this->sucursalB = Sucursal::create(['corporativo_id' => $this->corporativoB->id, 'nombre' => 'Sur B', 'activo' => true]);
    }

    /** Rol con exactamente estos permisos (los crea si faltan). */
    protected function roleWith(array $permissions, ?string $name = null): Role
    {
        app(\App\Support\Permissions\RoleSynchronizer::class)->syncPermissions();
        $role = Role::create(['name' => $name ?? 'Rol '.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /** Usuario con un rol de permisos exactos y colaborador en la sucursal indicada. */
    protected function userWith(array $permissions, ?Sucursal $sucursal = null, bool $withEmpleado = true): User
    {
        $role = $this->roleWith($permissions);
        $empleado = $withEmpleado ? $this->makeEmpleado(['sucursal_id' => ($sucursal ?? $this->sucursal)->id, 'nombre' => 'Colab '.uniqid()]) : null;
        $user = User::factory()->create(['empleado_id' => $empleado?->id]);
        $user->assignRole($role);

        return $user;
    }

    /** Requisición en una sucursal concreta (el comprador es su corporativo). */
    protected function requisicionEn(Sucursal $sucursal, User $creator, array $attrs = []): Requisicion
    {
        return $this->makeRequisicion($creator, $attrs + [
            'sucursal_id' => $sucursal->id,
            'comprador_corp_id' => $sucursal->corporativo_id,
        ]);
    }

    /** Texto de todas las celdas de un Excel descargado (para verificar el alcance exportado). */
    protected function excelText(\Illuminate\Testing\TestResponse $response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        copy($response->baseResponse->getFile()->getPathname(), $path);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $text = '';
        foreach ($book->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, true, false) as $row) {
                $text .= implode('|', array_map(fn ($v) => (string) $v, $row))."\n";
            }
        }
        @unlink($path);

        return $text;
    }
}
