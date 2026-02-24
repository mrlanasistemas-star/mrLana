<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequisicionController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\CorporativoController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\ConceptoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\Exports\EmpleadoExportController;
use App\Http\Controllers\Exports\CorporativoExportController;
use App\Http\Controllers\Exports\SucursalExportController;
use App\Http\Controllers\Exports\AreaExportController;
use App\Http\Controllers\Exports\ConceptoExportController;
use App\Http\Controllers\Exports\RequisicionExportController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Dashboard\ContadorDashboardController;
use App\Http\Controllers\Dashboard\ColaboradorDashboardController;
use App\Http\Controllers\RequisicionPagoController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\RequisicionComprobanteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\RequisicionAjusteController;


Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth','verified'])
    ->name('dashboard');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/dashboard/admin', [AdminDashboardController::class, 'index'])
        ->name('dashboard.admin');

    Route::get('/dashboard/contador', [ContadorDashboardController::class, 'index'])
        ->name('dashboard.contador');

    Route::get('/dashboard/colaborador', [ColaboradorDashboardController::class, 'index'])
        ->name('dashboard.colaborador');
});

Route::middleware('auth')->group(function () {

    // =========================
    // Perfil de usuario
    // =========================
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // =========================
    // CRUDs base (corporativos, sucursales, áreas, empleados, conceptos, proveedores)
    // =========================
    Route::resource('corporativos', CorporativoController::class)->only(['index','store','update','destroy']);
    Route::post('corporativos/logo', [CorporativoController::class, 'uploadLogo'])->name('corporativos.logo');
    Route::patch('corporativos/{corporativo}/activate', [CorporativoController::class, 'activate'])->name('corporativos.activate');
    Route::get('corporativos/{corporativo}/sucursales-inactivas', [CorporativoController::class, 'inactiveSucursales'])->name('corporativos.inactiveSucursales');
    Route::get('corporativos/{corporativo}/areas-inactivas', [CorporativoController::class, 'inactiveAreas'])->name('corporativos.inactiveAreas');

    Route::resource('sucursales', SucursalController::class)
        ->parameters(['sucursales' => 'sucursal'])
        ->only(['index','store','update','destroy']);
    Route::post('/sucursales/bulk-destroy', [SucursalController::class, 'bulkDestroy'])->name('sucursales.bulkDestroy');
    Route::patch('sucursales/{sucursal}/activate', [SucursalController::class, 'activate'])->name('sucursales.activate');

    Route::resource('areas', AreaController::class)->only(['index','store','update','destroy']);
    Route::post('/areas/bulk-destroy', [AreaController::class, 'bulkDestroy'])->name('areas.bulkDestroy');
    Route::patch('areas/{area}/activate', [AreaController::class, 'activate'])->name('areas.activate');

    Route::resource('empleados', EmpleadoController::class)->only(['index','store','update','destroy']);
    Route::post('/empleados/bulk-destroy', [EmpleadoController::class, 'bulkDestroy'])->name('empleados.bulkDestroy');
    Route::patch('empleados/{empleado}/activate', [EmpleadoController::class, 'activate'])->name('empleados.activate');

    Route::resource('conceptos', ConceptoController::class)->only(['index','store','update','destroy']);
    Route::post('/conceptos/bulk-destroy', [ConceptoController::class, 'bulkDestroy'])->name('conceptos.bulkDestroy');
    Route::patch('conceptos/{concepto}/activate', [ConceptoController::class, 'activate'])->name('conceptos.activate');

    Route::resource('proveedores', ProveedorController::class)->only(['index','store','update','destroy']);
    Route::post('/proveedores/bulk-destroy', [ProveedorController::class, 'bulkDestroy'])->name('proveedores.bulkDestroy');
    Route::patch('proveedores/{proveedor}/activate', [ProveedorController::class, 'activate'])->name('proveedores.activate');

    // =========================
    // Requisiciones
    // =========================

    // Recurso principal adaptado: sólo index, create, store, update y destroy (show/print/pagar no se usan en la versión actual).
    Route::resource('requisiciones', RequisicionController::class)
        ->only(['index','create','store','update','destroy']);
    Route::get('/requisicione/{requisicion}', [RequisicionController::class, 'show'])
        ->name('requisiciones.show');
    // Eliminación masiva de requisiciones
    Route::delete('/requisiciones/bulk-destroy', [RequisicionController::class, 'bulkDestroy'])
        ->name('requisiciones.bulkDestroy');
    // Alias para la vista de creación (si tu frontend usa /requisiciones/registrar)
    Route::get('/requisiciones/registrar', [RequisicionController::class, 'create'])
        ->name('requisiciones.registrar');

    Route::get('/requisicione/{requisicion}/pdf', [\App\Http\Controllers\RequisicionController::class, 'pdf'])
    ->name('requisiciones.print');

    // Pagos
    Route::get('/requisiciones/{requisicion}/pagar', [RequisicionPagoController::class, 'create'])
        ->name('requisiciones.pagar');

    Route::post('/requisiciones/{requisicion}/pagar', [RequisicionPagoController::class, 'store'])
        ->name('requisiciones.pagar.store');

    Route::get('/requisiciones/{requisicion}/comprobar', [RequisicionComprobanteController::class, 'create'])
        ->name('requisiciones.comprobar');

    Route::post('/requisiciones/{requisicion}/comprobar', [RequisicionComprobanteController::class, 'store'])
        ->name('requisiciones.comprobar.store');

    Route::delete('/comprobantes/{comprobante}', [RequisicionComprobanteController::class, 'destroy'])
    ->name('comprobantes.destroy')
    ->middleware(['web','auth']);

    Route::patch('/comprobantes/{comprobante}/review', [RequisicionComprobanteController::class, 'review'])
        ->name('comprobantes.review');

    Route::post('/folios', [FolioController::class, 'store'])->name('folios.store');
    Route::patch('/folios/{folio}', [FolioController::class, 'update'])->name('folios.update');
    Route::get('/folios', [FolioController::class, 'index'])->name('folios.index');

    // Notify (para que tu composable encuentre requisiciones.comprobaciones.notify)
    Route::post('/requisiciones/{requisicion}/comprobaciones/notify', [RequisicionComprobanteController::class, 'notify'])
        ->name('requisiciones.comprobaciones.notify');

    // Página de Ajustes
    Route::get('/requisiciones/{requisicion}/ajustes', [RequisicionController::class, 'ajustes'])
        ->name('requisiciones.ajustes');

    // Crear ajuste
    Route::post('/requisiciones/{requisicion}/ajustes', [RequisicionAjusteController::class, 'store'])
        ->name('requisiciones.ajustes.store');

    // Aprobar / rechazar
    Route::patch('/requisiciones/ajustes/{ajuste}/review', [RequisicionAjusteController::class, 'review'])
        ->name('requisiciones.ajustes.review');

    // Aplicar (impacta monto_total)
    Route::post('/requisiciones/ajustes/{ajuste}/apply', [RequisicionAjusteController::class, 'apply'])
        ->name('requisiciones.ajustes.apply');

    // Cancelar (si quieres: para que el colaborador cancele mientras está PENDIENTE)
    Route::post('/requisiciones/ajustes/{ajuste}/cancel', [RequisicionAjusteController::class, 'cancel'])
        ->name('requisiciones.ajustes.cancel');

    // =========================
    // Plantillas
    // =========================
    // Gestión de plantillas para guardar requisiciones preconfiguradas
    Route::resource('plantillas', PlantillaController::class)
        ->except(['show']);

    // Obtener una plantilla con sus detalles (para precargar datos en una requisición)
    Route::get('plantillas/{plantilla}', [PlantillaController::class, 'show'])
        ->name('plantillas.show');

    Route::put('plantillas/{plantilla}/reactivar', [\App\Http\Controllers\PlantillaController::class, 'reactivate'])
    ->name('plantillas.reactivate');


    // Logs del sistema
    Route::get('/system-logs', [SystemLogController::class, 'index'])
        ->name('systemlogs.index');

    // =========================
    // Reportes (exports)
    // =========================
    Route::get('/exports/empleados/pdf', [EmpleadoExportController::class, 'pdf'])->name('empleados.export.pdf');
    Route::get('/exports/empleados/excel', [EmpleadoExportController::class, 'excel'])->name('empleados.export.excel');

    Route::get('/exports/corporativos/pdf', [CorporativoExportController::class, 'pdf'])->name('corporativos.export.pdf');
    Route::get('/exports/corporativos/excel', [CorporativoExportController::class, 'excel'])->name('corporativos.export.excel');

    Route::get('/exports/sucursales/pdf', [SucursalExportController::class, 'pdf'])->name('sucursales.export.pdf');
    Route::get('/exports/sucursales/excel', [SucursalExportController::class, 'excel'])->name('sucursales.export.excel');

    Route::get('/exports/areas/pdf', [AreaExportController::class, 'pdf'])->name('areas.export.pdf');
    Route::get('/exports/areas/excel', [AreaExportController::class, 'excel'])->name('areas.export.excel');

    Route::get('/exports/conceptos/pdf', [ConceptoExportController::class, 'pdf'])->name('conceptos.export.pdf');
    Route::get('/exports/conceptos/excel', [ConceptoExportController::class, 'excel'])->name('conceptos.export.excel');

    Route::get('/exports/requisiciones/pdf', [RequisicionExportController::class, 'pdf'])->name('requisiciones.export.pdf');
    Route::get('/exports/requisiciones/excel', [RequisicionExportController::class, 'excel'])->name('requisiciones.export.excel');
});

require __DIR__.'/auth.php';
