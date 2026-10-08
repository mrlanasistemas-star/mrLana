<?php

use App\Models\Role;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionTransition;
use App\Support\Permissions\RoleSynchronizer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('erp:sync-permissions', function (RoleSynchronizer $synchronizer) {
    $synchronizer->sync();
    $this->info('Catálogo de permisos, roles iniciales y asignaciones sincronizados.');
})->purpose('Sincroniza el catálogo de permisos y los roles iniciales (idempotente)');

Artisan::command('erp:permissions-transition {--dry-run : Solo muestra lo que se agregaría} {--force : Vuelve a aplicarla aunque ya esté registrada}', function (PermissionTransition $transition) {
    // La simulación solo lee: funciona antes de migrar.
    if ($this->option('dry-run')) {
        foreach (Role::query()->with('permissions:id,name')->orderBy('name')->get() as $role) {
            $had = $role->permissions->pluck('name')->all();
            $add = $role->name === PermissionCatalog::ROLE_ADMIN
                ? array_values(array_diff(PermissionCatalog::all(), $had))
                : PermissionTransition::mapFor($had);
            $this->components->twoColumnDetail($role->name, $add === [] ? 'sin cambios' : count($add).' permiso(s)');
            foreach ($add as $p) {
                $this->line('    + '.PermissionCatalog::label($p));
            }
        }

        return 0;
    }

    if (! Schema::hasTable('permission_transitions')) {
        $this->error('Falta la tabla permission_transitions. Ejecuta primero `php artisan migrate`.');

        return 1;
    }

    if ($transition->alreadyApplied() && ! $this->option('force')) {
        $this->warn('La transición ya se aplicó. Usa --force solo si confirmas que quieres volver a agregar permisos a los roles.');

        return 0;
    }

    $report = $transition->run();
    if ($report === []) {
        $this->info('Sin cambios: todos los roles ya tenían sus permisos equivalentes.');
    }
    foreach ($report as $role => $perms) {
        $this->components->twoColumnDetail($role, count($perms).' permiso(s) agregados');
    }

    return 0;
})->purpose('Aplica la transición a permisos con alcance (solo agrega permisos; nunca quita)');
