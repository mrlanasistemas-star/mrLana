<?php

namespace App\Console\Commands;

use App\Support\Permissions\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión previa al despliegue. Solo lee: no modifica la base de datos.
 *
 * Detecta lo que haría fallar o volvería riesgosa la migración del
 * incremento de permisos (duplicados en users.empleado_id, tablas MyISAM,
 * valores legados de users.rol) y valida la configuración del servidor.
 */
class CheckDeploy extends Command
{
    protected $signature = 'erp:check-deploy';

    protected $description = 'Revisa la base de datos y el entorno antes de migrar (solo lectura)';

    private int $blocking = 0;

    public function handle(): int
    {
        $this->database();
        $this->duplicatedEmpleados();
        $this->legacyRoles();
        $this->pendingMigrations();
        $this->environment();

        $this->newLine();
        if ($this->blocking > 0) {
            $this->error("Hay {$this->blocking} problema(s) que bloquean la migración. Corrígelos antes de continuar.");

            return self::FAILURE;
        }
        $this->info('Sin bloqueos. Continúa con el respaldo y `php artisan migrate --pretend`.');

        return self::SUCCESS;
    }

    private function database(): void
    {
        $driver = DB::getDriverName();
        $this->components->twoColumnDetail('Base de datos', DB::getDatabaseName()." ({$driver})");

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->components->twoColumnDetail('Versión del servidor', (string) DB::scalar('SELECT VERSION()'));
        $this->components->twoColumnDetail('Motor predeterminado', (string) DB::scalar('SELECT @@default_storage_engine'));

        $myisam = collect(DB::select(
            "SELECT table_name AS nombre, table_rows AS filas FROM information_schema.tables
             WHERE table_schema = ? AND table_type = 'BASE TABLE' AND engine = 'MyISAM' ORDER BY table_name",
            [DB::getDatabaseName()]
        ));

        if ($myisam->isEmpty()) {
            $this->components->twoColumnDetail('Tablas MyISAM', '<fg=green>ninguna</>');
        } else {
            $this->components->warn(
                "Tablas MyISAM ({$myisam->count()}): se convertirán a InnoDB al migrar. "
                .'ALTER TABLE bloquea cada tabla mientras se copia; programa una ventana de mantenimiento.'
            );
            $this->table(['Tabla', 'Filas aprox.'], $myisam->map(fn ($t) => [$t->nombre, $t->filas])->all());
        }
    }

    private function duplicatedEmpleados(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'empleado_id')) {
            return;
        }

        $dups = DB::table('users')
            ->whereNotNull('empleado_id')
            ->whereIn('empleado_id', fn ($q) => $q->select('empleado_id')->from('users')
                ->whereNotNull('empleado_id')->groupBy('empleado_id')->havingRaw('COUNT(*) > 1'))
            ->orderBy('empleado_id')->orderBy('id')
            ->get(['id', 'empleado_id', 'name', 'email']);

        if ($dups->isEmpty()) {
            $this->components->twoColumnDetail('Colaboradores con varias cuentas', '<fg=green>ninguno</>');

            return;
        }

        $this->blocking++;
        $this->components->error('Colaboradores con más de una cuenta (users.empleado_id duplicado). Deja una sola cuenta vinculada por colaborador:');
        $this->table(['user.id', 'empleado_id', 'Nombre', 'Correo'], $dups->map(fn ($u) => (array) $u)->all());
        $this->line('  Ejemplo para desvincular una cuenta sobrante: UPDATE users SET empleado_id = NULL WHERE id = <user.id>;');
    }

    private function legacyRoles(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'rol')) {
            return;
        }

        $rows = DB::table('users')->selectRaw('UPPER(COALESCE(rol, \'\')) AS rol, COUNT(*) AS total')->groupBy(DB::raw('UPPER(COALESCE(rol, \'\'))'))->get();
        $this->table(
            ['users.rol', 'Usuarios', 'Rol asignado al migrar'],
            $rows->map(fn ($r) => [
                $r->rol === '' ? '(vacío)' : $r->rol,
                $r->total,
                PermissionCatalog::LEGACY_ROLE_MAP[$r->rol] ?? PermissionCatalog::ROLE_COLABORADOR.' (valor no reconocido)',
            ])->all(),
        );
    }

    private function pendingMigrations(): void
    {
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
        $pending = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => basename($f, '.php'))
            ->reject(fn ($m) => in_array($m, $ran, true))
            ->values();

        $this->components->twoColumnDetail('Migraciones pendientes', (string) $pending->count());
        $pending->each(fn ($m) => $this->line("  · {$m}"));
    }

    private function environment(): void
    {
        $this->newLine();
        $checks = [
            'APP_KEY definido' => filled(config('app.key')),
            'APP_DEBUG desactivado' => ! config('app.debug'),
            'Cola en base de datos (requiere worker)' => config('queue.default') === 'database',
            'storage:link creado' => is_link(public_path('storage')) || is_dir(public_path('storage')),
            'Zona horaria de negocio' => filled(config('erp.business_timezone')),
        ];
        foreach ($checks as $label => $ok) {
            $this->components->twoColumnDetail($label, $ok ? '<fg=green>sí</>' : '<fg=yellow>revisar</>');
        }

        $this->components->twoColumnDetail('Difusión (BROADCAST_CONNECTION)', (string) config('broadcasting.default'));
        $driver = config('erp.pdf.driver');
        $chrome = config('erp.pdf.chrome_path');
        $this->components->twoColumnDetail('PDF', $driver.($driver === 'browsershot'
            ? ' · Chrome: '.($chrome ? ($chrome.(is_file($chrome) ? '' : ' <fg=yellow>(no encontrado; se usará DomPDF)</>')) : 'autodetección')
            : ''));
    }
}
