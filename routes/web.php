<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ConceptoController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CorporativoController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Exports\AreaExportController;
use App\Http\Controllers\Exports\ColaboradorExportController;
use App\Http\Controllers\Exports\ConceptoExportController;
use App\Http\Controllers\Exports\CorporativoExportController;
use App\Http\Controllers\Exports\DashboardExportController;
use App\Http\Controllers\Exports\ProveedorExportController;
use App\Http\Controllers\Exports\RequisicionExportController;
use App\Http\Controllers\Exports\SucursalExportController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RequisicionAjusteController;
use App\Http\Controllers\RequisicionComprobanteController;
use App\Http\Controllers\RequisicionController;
use App\Http\Controllers\RequisicionEliminacionController;
use App\Http\Controllers\RequisicionPagoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\UsuarioController;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Support\Facades\Route;

/*
| Autorización en dos capas:
| 1. Middleware `permission`: la persona tiene el permiso de la acción o
|    algún nivel de alcance del módulo (PermissionCatalog::anyScope).
| 2. Policies / scopes (AccessScope): el registro concreto está dentro de su
|    alcance (propio, sucursal, corporativo o global).
| Ocultar botones en Vue no es seguridad. Ninguna ruta decide por segmentos
| de URL como "admin" o "contador".
*/

$scope = fn (string $module) => 'permission:'.PermissionCatalog::anyScope($module);
$any = fn (string ...$permissions) => 'permission:'.implode('|', $permissions);

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard: la vista (personal, sucursal, corporativo o general) se elige con ?vista=.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // URLs anteriores: redirigen a la vista equivalente; la autorización la decide index().
    Route::get('/dashboard/admin', fn () => redirect()->route('dashboard', ['vista' => 'general'] + request()->query()))->name('dashboard.admin');
    Route::get('/dashboard/contador', fn () => redirect()->route('dashboard', ['vista' => 'general'] + request()->query()))->name('dashboard.contador');
    Route::get('/dashboard/colaborador', fn () => redirect()->route('dashboard', ['vista' => 'personal'] + request()->query()))->name('dashboard.colaborador');
});

Route::middleware('auth')->group(function () use ($scope, $any) {

    // =========================
    // Ayuda: centro de ayuda y recorridos (cualquier cuenta autenticada)
    // =========================
    Route::get('/ayuda', fn () => redirect()->route('ayuda.guia'))->name('ayuda.index');
    Route::get('/ayuda/guia', [AyudaController::class, 'guia'])->name('ayuda.guia');

    // Exportaciones del dashboard: {vista} = personal|sucursal|corporativo|general.
    Route::middleware('permission:reportes.dashboard')->group(function () {
        Route::get('/exports/dashboard/{vista}/pdf', [DashboardExportController::class, 'pdf'])->name('dashboard.export.pdf');
        Route::get('/exports/dashboard/{vista}/excel', [DashboardExportController::class, 'excel'])->name('dashboard.export.excel');
    });

    // =========================
    // Perfil de usuario (siempre disponible para la propia cuenta)
    // =========================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // =========================
    // Notificaciones: las propias (campana) y la consulta administrativa de todas
    // =========================
    Route::middleware($scope('notificaciones'))->group(function () {
        Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notificaciones.index');
        Route::get('/notificaciones/recientes', [NotificationController::class, 'recent'])->name('notificaciones.recent');
        Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'markAsRead'])->name('notificaciones.read');
        Route::post('/notificaciones/leer-todas', [NotificationController::class, 'markAllAsRead'])->name('notificaciones.readAll');
    });
    Route::get('/notificaciones/todas', [NotificationController::class, 'all'])
        ->middleware('permission:notificaciones.ver_todas')->name('notificaciones.all');

    // =========================
    // Catálogos organizacionales
    // =========================
    Route::resource('corporativos', CorporativoController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $scope('corporativos'))
        ->middlewareFor('store', 'permission:corporativos.registrar')
        ->middlewareFor('update', 'permission:corporativos.editar')
        ->middlewareFor('destroy', 'permission:corporativos.desactivar');
    Route::post('corporativos/logo', [CorporativoController::class, 'uploadLogo'])->middleware($any('corporativos.registrar', 'corporativos.editar'))->name('corporativos.logo');
    Route::patch('corporativos/{corporativo}/activate', [CorporativoController::class, 'activate'])->middleware('permission:corporativos.reactivar')->name('corporativos.activate');
    Route::get('corporativos/{corporativo}/sucursales-inactivas', [CorporativoController::class, 'inactiveSucursales'])->middleware($scope('corporativos'))->name('corporativos.inactiveSucursales');
    Route::get('corporativos/{corporativo}/areas-inactivas', [CorporativoController::class, 'inactiveAreas'])->middleware($scope('corporativos'))->name('corporativos.inactiveAreas');

    Route::resource('sucursales', SucursalController::class)
        ->parameters(['sucursales' => 'sucursal'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $scope('sucursales'))
        ->middlewareFor('store', 'permission:sucursales.registrar')
        ->middlewareFor('update', 'permission:sucursales.editar')
        ->middlewareFor('destroy', 'permission:sucursales.desactivar');
    Route::post('/sucursales/bulk-destroy', [SucursalController::class, 'bulkDestroy'])->middleware('permission:sucursales.desactivar')->name('sucursales.bulkDestroy');
    Route::patch('sucursales/{sucursal}/activate', [SucursalController::class, 'activate'])->middleware('permission:sucursales.reactivar')->name('sucursales.activate');

    Route::resource('areas', AreaController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $scope('areas'))
        ->middlewareFor('store', 'permission:areas.registrar')
        ->middlewareFor('update', 'permission:areas.editar')
        ->middlewareFor('destroy', 'permission:areas.desactivar');
    Route::post('/areas/bulk-destroy', [AreaController::class, 'bulkDestroy'])->middleware('permission:areas.desactivar')->name('areas.bulkDestroy');
    Route::patch('areas/{area}/activate', [AreaController::class, 'activate'])->middleware('permission:areas.reactivar')->name('areas.activate');

    // =========================
    // Colaboradores (personas) — tabla interna `empleados`
    // =========================
    Route::resource('colaboradores', ColaboradorController::class)
        ->parameters(['colaboradores' => 'empleado'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $scope('colaboradores'))
        ->middlewareFor('store', 'permission:colaboradores.registrar')
        ->middlewareFor('update', 'permission:colaboradores.editar')
        ->middlewareFor('destroy', 'permission:colaboradores.desactivar');
    Route::post('/colaboradores/bulk-destroy', [ColaboradorController::class, 'bulkDestroy'])->middleware('permission:colaboradores.desactivar')->name('colaboradores.bulkDestroy');
    Route::patch('colaboradores/{empleado}/activate', [ColaboradorController::class, 'activate'])->middleware('permission:colaboradores.reactivar')->name('colaboradores.activate');

    // Alias de compatibilidad: URLs anteriores del módulo "Empleados".
    Route::permanentRedirect('/empleados', '/colaboradores');
    Route::get('/exports/empleados/{format}', fn (string $format) => redirect()->route(
        $format === 'excel' ? 'colaboradores.export.excel' : 'colaboradores.export.pdf',
        request()->query()
    ))->whereIn('format', ['pdf', 'excel']);

    // =========================
    // Usuarios (cuentas de acceso)
    // =========================
    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware($scope('usuarios'))->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->middleware('permission:usuarios.registrar')->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('permission:usuarios.registrar')->name('usuarios.store');
    Route::get('/usuarios/{user}/editar', [UsuarioController::class, 'edit'])->middleware($scope('usuarios'))->name('usuarios.edit');
    Route::put('/usuarios/{user}', [UsuarioController::class, 'update'])->middleware('permission:usuarios.editar')->name('usuarios.update');
    Route::patch('/usuarios/{user}/desactivar', [UsuarioController::class, 'deactivate'])->middleware('permission:usuarios.desactivar')->name('usuarios.deactivate');
    Route::patch('/usuarios/{user}/reactivar', [UsuarioController::class, 'activate'])->middleware('permission:usuarios.reactivar')->name('usuarios.activate');
    Route::post('/usuarios/{user}/restablecer-contrasena', [UsuarioController::class, 'resetPassword'])->middleware('permission:usuarios.restablecer_contrasena')->name('usuarios.resetPassword');

    // =========================
    // Roles y permisos
    // =========================
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.ver')->name('roles.index');
    Route::get('/roles/crear', [RoleController::class, 'create'])->middleware('permission:roles.registrar')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.registrar')->name('roles.store');
    Route::get('/roles/{role}/editar', [RoleController::class, 'edit'])->middleware('permission:roles.ver')->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.editar')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.eliminar')->name('roles.destroy');

    // =========================
    // Configuración
    // =========================
    Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->middleware($any('configuracion.ver', 'configuracion.administrar'))->name('configuracion.edit');
    Route::put('/configuracion', [ConfiguracionController::class, 'update'])->middleware('permission:configuracion.administrar')->name('configuracion.update');
    Route::post('/configuracion/restablecer', [ConfiguracionController::class, 'reset'])->middleware('permission:configuracion.administrar')->name('configuracion.reset');

    // =========================
    // Conceptos y proveedores
    // =========================
    Route::resource('conceptos', ConceptoController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', 'permission:conceptos.ver')
        ->middlewareFor('store', 'permission:conceptos.registrar')
        ->middlewareFor('update', 'permission:conceptos.editar')
        ->middlewareFor('destroy', 'permission:conceptos.desactivar');
    Route::post('/conceptos/bulk-destroy', [ConceptoController::class, 'bulkDestroy'])->middleware('permission:conceptos.desactivar')->name('conceptos.bulkDestroy');
    Route::patch('conceptos/{concepto}/activate', [ConceptoController::class, 'activate'])->middleware('permission:conceptos.reactivar')->name('conceptos.activate');

    Route::resource('proveedores', ProveedorController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $scope('proveedores'))
        ->middlewareFor('store', 'permission:proveedores.registrar')
        ->middlewareFor('update', 'permission:proveedores.editar')
        ->middlewareFor('destroy', 'permission:proveedores.desactivar');
    Route::post('/proveedores/bulk-destroy', [ProveedorController::class, 'bulkDestroy'])->middleware('permission:proveedores.desactivar')->name('proveedores.bulkDestroy');
    Route::patch('proveedores/{proveedor}/activate', [ProveedorController::class, 'activate'])->middleware('permission:proveedores.reactivar')->name('proveedores.activate');

    // =========================
    // Requisiciones
    // =========================
    $verRequisiciones = $scope('requisiciones');

    // Debe declararse antes del recurso para que DELETE /requisiciones/{requisicion} no la capture.
    Route::delete('/requisiciones/bulk-destroy', [RequisicionController::class, 'bulkDestroy'])->middleware('permission:requisiciones.eliminar')
        ->name('requisiciones.bulkDestroy');

    Route::resource('requisiciones', RequisicionController::class)
        ->parameters(['requisiciones' => 'requisicion'])
        ->only(['index', 'create', 'store', 'update', 'destroy'])
        ->middlewareFor('index', $verRequisiciones)
        ->middlewareFor(['create', 'store'], 'permission:requisiciones.registrar')
        ->middlewareFor('update', $any('requisiciones.editar', 'requisiciones.editar_cualquiera'))
        ->middlewareFor('destroy', $verRequisiciones);
    Route::get('/requisicione/{requisicion}', [RequisicionController::class, 'show'])->middleware($verRequisiciones)
        ->name('requisiciones.show');
    // Alias para la vista de creación
    Route::get('/requisiciones/registrar', [RequisicionController::class, 'create'])->middleware('permission:requisiciones.registrar')
        ->name('requisiciones.registrar');

    Route::post('/requisiciones/guardar', [RequisicionController::class, 'storeDraft'])->middleware(['permission:requisiciones.registrar', 'permission:requisiciones.guardar_borrador'])
        ->name('requisiciones.storeDraft');
    Route::post('/requisiciones/enviar', [RequisicionController::class, 'storeCaptured'])->middleware(['permission:requisiciones.registrar', 'permission:requisiciones.enviar'])
        ->name('requisiciones.storeCaptured');

    Route::get('/requisicione/{requisicion}/pdf', [RequisicionController::class, 'pdf'])->middleware('permission:requisiciones.imprimir')
        ->name('requisiciones.print');

    // Enviar a autorización una requisición en borrador
    Route::post('/requisiciones/{requisicion}/capturar', [RequisicionController::class, 'capture'])
        ->middleware(['verified', 'permission:requisiciones.enviar'])
        ->name('requisiciones.capturar');

    // Solicitudes de eliminación (se solicita y se autoriza por separado)
    Route::post('/requisiciones/{requisicion}/solicitar-eliminacion', [RequisicionEliminacionController::class, 'store'])
        ->middleware('permission:requisiciones.solicitar_eliminacion')
        ->name('requisiciones.eliminacion.store');
    Route::patch('/requisiciones/eliminaciones/{solicitud}/revisar', [RequisicionEliminacionController::class, 'review'])
        ->middleware('permission:requisiciones.autorizar_eliminacion')
        ->name('requisiciones.eliminacion.review');
    Route::post('/requisiciones/eliminaciones/{solicitud}/cancelar', [RequisicionEliminacionController::class, 'cancel'])
        ->middleware($any('requisiciones.solicitar_eliminacion', 'requisiciones.autorizar_eliminacion'))
        ->name('requisiciones.eliminacion.cancel');

    // Pagos (heredan el alcance de la requisición)
    Route::post('/requisiciones/{requisicion}/autorizar-pago', [RequisicionPagoController::class, 'authorizePago'])
        ->middleware(['verified', 'permission:pagos.autorizar'])
        ->name('requisiciones.autorizarPago');
    Route::post('/requisiciones/{requisicion}/rechazar-pago', [RequisicionPagoController::class, 'rejectPago'])
        ->middleware(['verified', 'permission:pagos.rechazar'])
        ->name('requisiciones.rechazarPago');
    Route::get('/requisiciones/{requisicion}/pagar', [RequisicionPagoController::class, 'create'])->middleware($scope('pagos'))
        ->name('requisiciones.pagar');
    Route::post('/requisiciones/{requisicion}/pagar', [RequisicionPagoController::class, 'store'])->middleware('permission:pagos.registrar')
        ->name('requisiciones.pagar.store');
    Route::post('/requisiciones/{requisicion}/pagar/fecha-general', [RequisicionPagoController::class, 'updateFechaPagoGeneral'])->middleware('permission:pagos.editar')
        ->name('requisiciones.pagar.fechaGeneral');

    // Comprobaciones (heredan el alcance de la requisición)
    $subir = $any('comprobaciones.subir', 'comprobaciones.subir_cualquiera');
    Route::get('/requisiciones/{requisicion}/comprobar', [RequisicionComprobanteController::class, 'create'])->middleware($scope('comprobaciones'))
        ->name('requisiciones.comprobar');
    Route::post('/requisiciones/{requisicion}/comprobar', [RequisicionComprobanteController::class, 'store'])->middleware($subir)
        ->name('requisiciones.comprobar.store');
    Route::delete('/comprobantes/{comprobante}', [RequisicionComprobanteController::class, 'destroy'])->middleware($any('comprobaciones.eliminar', 'comprobaciones.eliminar_propios'))
        ->name('comprobantes.destroy');
    Route::patch('/comprobantes/{comprobante}/review', [RequisicionComprobanteController::class, 'review'])->middleware('permission:comprobaciones.revisar')
        ->name('comprobantes.review');
    Route::post('/requisiciones/{requisicion}/comprobaciones/notify', [RequisicionComprobanteController::class, 'notify'])->middleware($subir)
        ->name('requisiciones.comprobaciones.notify');

    // Módulos de consulta: comprobantes y pagos dentro del alcance.
    Route::get('/comprobantes', [ComprobanteController::class, 'index'])->middleware($scope('comprobaciones'))->name('comprobantes.index');
    Route::get('/comprobantes/{comprobante}/archivo', [ComprobanteController::class, 'archivo'])->middleware($scope('comprobaciones'))->name('comprobantes.archivo');
    Route::get('/pagos', [PagoController::class, 'index'])->middleware($scope('pagos'))->name('pagos.index');
    Route::get('/pagos/{pago}/archivo', [PagoController::class, 'archivo'])->middleware('permission:pagos.descargar')->name('pagos.archivo');
    Route::middleware('permission:comprobaciones.exportar')->group(function () {
        Route::get('/exports/comprobantes/pdf', [ComprobanteController::class, 'pdf'])->name('comprobantes.export.pdf');
        Route::get('/exports/comprobantes/excel', [ComprobanteController::class, 'excel'])->name('comprobantes.export.excel');
    });
    Route::middleware('permission:pagos.exportar')->group(function () {
        Route::get('/exports/pagos/pdf', [PagoController::class, 'pdf'])->name('pagos.export.pdf');
        Route::get('/exports/pagos/excel', [PagoController::class, 'excel'])->name('pagos.export.excel');
    });

    Route::get('/folios', [FolioController::class, 'index'])->middleware($scope('comprobaciones'))->name('folios.index');
    Route::post('/folios', [FolioController::class, 'store'])->middleware($subir)->name('folios.store');
    Route::patch('/folios/{folio}', [FolioController::class, 'update'])->middleware('permission:comprobaciones.administrar_folios')->name('folios.update');

    // Ajustes de monto (heredan el alcance de la requisición)
    Route::get('/requisiciones/{requisicion}/ajustes', [RequisicionController::class, 'ajustes'])->middleware($scope('ajustes'))
        ->name('requisiciones.ajustes');
    Route::post('/requisiciones/{requisicion}/ajustes', [RequisicionAjusteController::class, 'store'])->middleware($any('ajustes.solicitar', 'ajustes.solicitar_cualquiera'))
        ->name('requisiciones.ajustes.store');
    Route::patch('/requisiciones/ajustes/{ajuste}/review', [RequisicionAjusteController::class, 'review'])->middleware($any('ajustes.autorizar', 'ajustes.rechazar'))
        ->name('requisiciones.ajustes.review');
    Route::post('/requisiciones/ajustes/{ajuste}/apply', [RequisicionAjusteController::class, 'apply'])->middleware('permission:ajustes.aplicar')
        ->name('requisiciones.ajustes.apply');
    Route::post('/requisiciones/ajustes/{ajuste}/cancel', [RequisicionAjusteController::class, 'cancel'])->middleware($any('ajustes.solicitar', 'ajustes.solicitar_cualquiera', 'ajustes.autorizar', 'ajustes.rechazar'))
        ->name('requisiciones.ajustes.cancel');

    // =========================
    // Plantillas
    // =========================
    $verPlantillas = $scope('plantillas');

    Route::resource('plantillas', PlantillaController::class)
        ->except(['show'])
        ->middlewareFor('index', $verPlantillas)
        ->middlewareFor(['create', 'store'], 'permission:plantillas.registrar')
        ->middlewareFor(['edit', 'update'], $any('plantillas.editar', 'plantillas.editar_cualquiera'))
        ->middlewareFor('destroy', $any('plantillas.eliminar', 'plantillas.eliminar_cualquiera'));
    Route::get('plantillas/{plantilla}', [PlantillaController::class, 'show'])->middleware($verPlantillas)
        ->name('plantillas.show');
    Route::put('plantillas/{plantilla}/reactivar', [PlantillaController::class, 'reactivate'])->middleware($any('plantillas.eliminar', 'plantillas.eliminar_cualquiera'))
        ->name('plantillas.reactivate');

    // Bitácora del sistema (solo lectura)
    Route::get('/system-logs', [SystemLogController::class, 'index'])->middleware($scope('logs'))
        ->name('systemlogs.index');

    // =========================
    // Reportes (exports): mismo alcance y filtros que cada listado
    // =========================
    Route::middleware('permission:colaboradores.exportar')->group(function () {
        Route::get('/exports/colaboradores/pdf', [ColaboradorExportController::class, 'pdf'])->name('colaboradores.export.pdf');
        Route::get('/exports/colaboradores/excel', [ColaboradorExportController::class, 'excel'])->name('colaboradores.export.excel');
    });
    Route::middleware('permission:corporativos.exportar')->group(function () {
        Route::get('/exports/corporativos/pdf', [CorporativoExportController::class, 'pdf'])->name('corporativos.export.pdf');
        Route::get('/exports/corporativos/excel', [CorporativoExportController::class, 'excel'])->name('corporativos.export.excel');
    });
    Route::middleware('permission:sucursales.exportar')->group(function () {
        Route::get('/exports/sucursales/pdf', [SucursalExportController::class, 'pdf'])->name('sucursales.export.pdf');
        Route::get('/exports/sucursales/excel', [SucursalExportController::class, 'excel'])->name('sucursales.export.excel');
    });
    Route::middleware('permission:areas.exportar')->group(function () {
        Route::get('/exports/areas/pdf', [AreaExportController::class, 'pdf'])->name('areas.export.pdf');
        Route::get('/exports/areas/excel', [AreaExportController::class, 'excel'])->name('areas.export.excel');
    });
    Route::middleware('permission:conceptos.exportar')->group(function () {
        Route::get('/exports/conceptos/pdf', [ConceptoExportController::class, 'pdf'])->name('conceptos.export.pdf');
        Route::get('/exports/conceptos/excel', [ConceptoExportController::class, 'excel'])->name('conceptos.export.excel');
    });
    Route::middleware('permission:proveedores.exportar')->group(function () {
        Route::get('/exports/proveedores/pdf', [ProveedorExportController::class, 'pdf'])->name('proveedores.export.pdf');
        Route::get('/exports/proveedores/excel', [ProveedorExportController::class, 'excel'])->name('proveedores.export.excel');
    });
    Route::middleware('permission:requisiciones.exportar')->group(function () {
        Route::get('/exports/requisiciones/pdf', [RequisicionExportController::class, 'pdf'])->name('requisiciones.export.pdf');
        Route::get('/exports/requisiciones/excel', [RequisicionExportController::class, 'excel'])->name('requisiciones.export.excel');
    });
});

require __DIR__.'/auth.php';
