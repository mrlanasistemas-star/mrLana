<?php

use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('erp:sync-permissions', function (RoleSynchronizer $synchronizer) {
    $synchronizer->sync();
    $this->info('Catálogo de permisos, roles iniciales y asignaciones sincronizados.');
})->purpose('Sincroniza el catálogo de permisos y los roles iniciales (idempotente)');
