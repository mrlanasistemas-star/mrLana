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
        $this->accountsOfInactiveColaboradores();
        $this->accountsWithoutColaborador();
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

    /**
     * Antes, dar de baja a un colaborador dejaba su cuenta activa. Se listan
     * esos casos para decidir (desde Usuarios) si deben desactivarse; no se
     * cambian automáticamente porque pueden incluir administradores.
     */
    private function accountsOfInactiveColaboradores(): void
    {
        if (! Schema::hasColumn('users', 'empleado_id') || ! Schema::hasColumn('users', 'activo') || ! Schema::hasColumn('empleados', 'activo')) {
            return;
        }

        $rows = DB::table('users')
            ->join('empleados', 'empleados.id', '=', 'users.empleado_id')
            ->where('users.activo', true)
            ->where('empleados.activo', false)
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email']);

        if ($rows->isEmpty()) {
            $this->components->twoColumnDetail('Cuentas activas de colaboradores dados de baja', '<fg=green>ninguna</>');

            return;
        }

        $this->components->warn("Cuentas activas vinculadas a colaboradores dados de baja ({$rows->count()}). Pueden seguir entrando; desactívalas en Usuarios si corresponde:");
        $this->table(['user.id', 'Nombre', 'Correo'], $rows->map(fn ($u) => (array) $u)->all());
    }

    /**
     * Una cuenta sin colaborador no es solicitante: no puede crear
     * requisiciones a su nombre. La migración vincula las inequívocas; las
     * que queden se corrigen en Usuarios eligiendo colaborador o sucursal.
     */
    private function accountsWithoutColaborador(): void
    {
        if (! Schema::hasColumn('users', 'empleado_id') || ! Schema::hasColumn('users', 'activo')) {
            return;
        }

        $rows = DB::table('users')->whereNull('empleado_id')->where('activo', true)->orderBy('id')->get(['id', 'name', 'email']);

        if ($rows->isEmpty()) {
            $this->components->twoColumnDetail('Cuentas activas sin colaborador', '<fg=green>ninguna</>');

            return;
        }

        $this->components->warn("Cuentas activas sin colaborador vinculado ({$rows->count()}). No pueden solicitar requisiciones a su nombre; edítalas en Usuarios y elige su colaborador o su sucursal:");
        $this->table(['user.id', 'Nombre', 'Correo'], $rows->map(fn ($u) => (array) $u)->all());
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
        $this->pdfEngine();
    }

    /**
     * Genera un PDF mínimo con Chrome (Browsershot) para saber con qué motor
     * salen los reportes. Si Chrome no funciona no bloquea el despliegue (los
     * PDF salen con DomPDF), pero se avisa con el error y cómo corregirlo.
     */
    private function pdfEngine(): void
    {
        $driver = (string) config('erp.pdf.driver');
        $chrome = config('erp.pdf.chrome_path');
        $node = config('erp.pdf.node_binary');

        if ($driver !== 'browsershot') {
            $this->components->twoColumnDetail('PDF', "<fg=yellow>DomPDF</> (ERP_PDF_DRIVER={$driver}; Chrome desactivado)");

            return;
        }

        $this->components->twoColumnDetail('PDF · Chrome', $chrome ? $chrome.(is_file($chrome) ? '' : ' <fg=yellow>(no existe ese archivo)</>') : 'el que instaló Puppeteer (autodetección)');
        if ($node) {
            $this->components->twoColumnDetail('PDF · Node', $node.(is_file($node) ? '' : ' <fg=yellow>(no existe ese archivo)</>'));
        }

        // Chrome de Puppeteer se busca en el HOME de quien ejecuta: la prueba
        // solo refleja al sitio si se corre con el mismo usuario que PHP-FPM.
        $user = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user())
            : get_current_user();
        $this->components->twoColumnDetail('PDF · prueba hecha como', $user.($user === 'www-data' ? '' : ' <fg=gray>(si PHP-FPM usa otro usuario: sudo -u www-data php artisan erp:check-deploy)</>'));

        $probe = app(\App\Services\Pdf\PdfService::class)->probeChrome(20);
        $seconds = number_format($probe['seconds'], 1).' s';

        if ($probe['ok']) {
            $this->components->twoColumnDetail('PDF · motor en uso', "<fg=green>Chrome (Browsershot) funciona</> · prueba en {$seconds}");

            return;
        }

        $this->components->twoColumnDetail('PDF · motor en uso', "<fg=yellow>DomPDF (respaldo)</> · Chrome falló en {$seconds}");
        $error = (string) $probe['error'];
        $this->components->warn('Chrome no pudo generar el PDF de prueba. Los reportes se generan con DomPDF (más simples). Error:');
        $this->line('  '.mb_strimwidth(preg_replace('/\s+/', ' ', $error) ?? $error, 0, 600, '…'));

        $this->newLine();
        $this->line('  <options=bold>Cómo activar Chrome</> (ejecuta en la carpeta del proyecto y como el usuario que corre PHP, p. ej. www-data):');
        $lower = mb_strtolower($error);
        $hints = [];
        if (str_contains($lower, 'configured executablepath')) {
            $hints[] = 'BROWSERSHOT_CHROME_PATH apunta a un archivo que no existe: corrígelo con la salida de `which chromium google-chrome` o déjalo vacío para usar el Chrome de Puppeteer.';
        } elseif (str_contains($lower, 'could not find chrome') || str_contains($lower, 'could not find expected browser') || str_contains($lower, 'browser was not found')) {
            // Browsershot usa el «chrome-headless-shell» de Puppeteer; el mensaje trae el nombre exacto.
            $browser = preg_match('/puppeteer browsers install ([a-z-]+)/', $error, $m) ? $m[1] : 'chrome-headless-shell';
            $hints[] = 'Puppeteer no descargó Chrome (npm bloqueó su postinstall). Descárgalo para el usuario del sitio:';
            $hints[] = "  sudo -u www-data -H npx puppeteer browsers install {$browser}";
        }
        if (str_contains($lower, 'error while loading shared libraries') || str_contains($lower, 'libnss3') || str_contains($lower, 'libgbm')) {
            $hints[] = 'Faltan librerías del sistema para Chrome (en Ubuntu 22.04 usa libasound2 en lugar de libasound2t64):';
            $hints[] = '  sudo apt install -y libnss3 libatk-bridge2.0-0 libgbm1 libxkbcommon0 libasound2t64 libcups2 libdrm2 libxcomposite1 libxdamage1 libxrandr2 libpango-1.0-0';
        }
        if (str_contains($lower, 'cannot find module')) {
            $hints[] = 'Falta la dependencia de Node: en la carpeta del proyecto, npm ci (y luego el paso de Chrome).';
        }
        if (str_contains($lower, 'node: not found') || str_contains($lower, 'exit code: 127') || str_contains($lower, 'no such file or directory')) {
            $hints[] = 'PHP no encuentra node/npm: fija BROWSERSHOT_NODE_BINARY y BROWSERSHOT_NPM_BINARY con la salida de `which node npm`.';
        }
        if (str_contains($lower, 'eacces') || str_contains($lower, 'permission denied')) {
            $hints[] = 'Permisos: www-data necesita un HOME escribible (sudo mkdir -p /var/www/.cache && sudo chown -R www-data:www-data /var/www/.cache).';
        }
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            $hints[] = 'Chrome tardó demasiado: revisa memoria del servidor y que BROWSERSHOT_NO_SANDBOX=true.';
        }
        $hints[] = 'Alternativa: instala Google Chrome del sistema (paquete .deb, ver docs/despliegue.md §10; el chromium de Ubuntu es snap y suele fallar con www-data)';
        $hints[] = '  y fija su ruta en .env: BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome (ver `which google-chrome`).';
        $hints[] = 'Después: php artisan config:cache y vuelve a correr php artisan erp:check-deploy.';
        foreach (array_unique($hints) as $h) {
            // Las líneas con sangría son comandos o continuación del punto anterior.
            $this->line(str_starts_with($h, '  ') ? '      '.ltrim($h) : '  · '.$h);
        }
    }
}
