<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Completa requisicions.pago_autorizado_por_id en autorizaciones anteriores a
 * que el sistema guardara ese dato, usando la bitácora como única fuente.
 *
 * Solo asigna cuando la evidencia es inequívoca: entradas de bitácora del
 * cambio de estatus CAPTURADA → PAGO_AUTORIZADO de esa requisición, todas del
 * mismo usuario existente, y la más reciente registrada junto a la fecha de
 * autorización. Cualquier otro caso se reporta y se deja "No registrado".
 */
class BackfillPagoAutorizadoPor extends Command
{
    protected $signature = 'erp:backfill-autorizador-pago
        {--dry-run : Solo muestra el resultado, sin guardar cambios}
        {--tolerancia=5 : Minutos máximos entre la bitácora y la fecha de autorización}';

    protected $description = 'Recupera desde la bitácora quién autorizó pagos anteriores (solo casos inequívocos).';

    /** Formato antiguo ("status: CAPTURADA -> PAGO_AUTORIZADO") y actual (con →). */
    private const PATTERN = '/(^|\n)\s*-\s*status:\s*CAPTURADA\s*(->|→)\s*PAGO_AUTORIZADO\s*($|\n)/u';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tolerancia = max(0, (int) $this->option('tolerancia'));

        $pendientes = DB::table('requisicions')
            ->whereNull('pago_autorizado_por_id')
            ->whereNotNull('fecha_autorizacion')
            ->orderBy('id')
            ->get(['id', 'folio', 'fecha_autorizacion']);

        if ($pendientes->isEmpty()) {
            $this->info('No hay pagos autorizados sin autorizador registrado.');

            return self::SUCCESS;
        }

        $usuarios = DB::table('users')->pluck('name', 'id');
        $resultados = $pendientes->map(fn ($req) => $this->resolver($req, $usuarios, $tolerancia));

        $asignables = $resultados->where('estado', 'asignable');
        $this->table(
            ['Folio', 'Resultado', 'Autorizó', 'Detalle'],
            $resultados->map(fn ($r) => [$r['folio'], $r['etiqueta'], $r['usuario'] ?? '—', $r['motivo']])->all(),
        );

        $this->newLine();
        $this->components->twoColumnDetail('Pagos sin autorizador', (string) $resultados->count());
        $this->components->twoColumnDetail('Recuperables (inequívocos)', (string) $asignables->count());
        $this->components->twoColumnDetail('Ambiguos (revisión manual)', (string) $resultados->where('estado', 'ambiguo')->count());
        $this->components->twoColumnDetail('Sin registro en bitácora', (string) $resultados->where('estado', 'sin_fuente')->count());

        if ($dryRun) {
            $this->warn('Modo --dry-run: no se guardó ningún cambio.');

            return self::SUCCESS;
        }

        $guardados = 0;
        DB::transaction(function () use ($asignables, &$guardados) {
            foreach ($asignables as $r) {
                $ok = DB::table('requisicions')
                    ->where('id', $r['id'])
                    ->whereNull('pago_autorizado_por_id')
                    ->update(['pago_autorizado_por_id' => $r['user_id']]);

                if ($ok === 1) {
                    $guardados++;
                    SystemLog::create([
                        'user_id' => null,
                        'accion' => 'ACTUALIZACION',
                        'tabla' => 'requisicions',
                        'registro_id' => $r['id'],
                        'etiqueta' => $r['folio'],
                        'descripcion' => "Autorizador del pago recuperado desde la bitácora (registro #{$r['log_id']}): {$r['usuario']}.",
                        'cambios' => ['pago_autorizado_por_id' => ['antes' => null, 'despues' => $r['user_id']]],
                    ]);
                }
            }
        });

        $this->info("Se registró el autorizador en {$guardados} requisición(es). Los demás casos siguen como «No registrado».");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, string>  $usuarios
     * @return array<string, mixed>
     */
    private function resolver(object $req, Collection $usuarios, int $tolerancia): array
    {
        $base = ['id' => $req->id, 'folio' => $req->folio ?: "#{$req->id}", 'usuario' => null, 'user_id' => null, 'log_id' => null];
        $sinFuente = fn (string $motivo) => $base + ['estado' => 'sin_fuente', 'etiqueta' => 'No registrado', 'motivo' => $motivo];
        $ambiguo = fn (string $motivo) => $base + ['estado' => 'ambiguo', 'etiqueta' => 'Ambiguo', 'motivo' => $motivo];

        $logs = SystemLog::query()
            ->where('tabla', 'requisicions')
            ->where('registro_id', $req->id)
            ->where('descripcion', 'like', '%PAGO_AUTORIZADO%')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'descripcion', 'created_at'])
            ->filter(fn (SystemLog $log) => preg_match(self::PATTERN, (string) $log->descripcion) === 1)
            ->values();

        if ($logs->isEmpty()) {
            return $sinFuente('La bitácora no tiene la autorización de esta requisición.');
        }

        if ($logs->contains(fn (SystemLog $log) => $log->user_id === null)) {
            return $ambiguo('Hay una autorización en bitácora sin usuario identificado.');
        }

        $ids = $logs->pluck('user_id')->map(fn ($id) => (int) $id)->unique();
        if ($ids->count() > 1) {
            return $ambiguo('La bitácora muestra autorizaciones de distintos usuarios.');
        }

        $userId = $ids->first();
        if (! $usuarios->has($userId)) {
            return $ambiguo('El usuario de la bitácora ya no existe.');
        }

        $ultimo = $logs->last();
        $diferencia = abs(Carbon::parse($ultimo->created_at)->diffInMinutes(Carbon::parse($req->fecha_autorizacion)));
        if ($diferencia > $tolerancia) {
            return $ambiguo("La bitácora no coincide con la fecha de autorización ({$diferencia} min de diferencia).");
        }

        return [
            'id' => $req->id,
            'folio' => $base['folio'],
            'estado' => 'asignable',
            'etiqueta' => 'Recuperable',
            'usuario' => $usuarios[$userId],
            'user_id' => $userId,
            'log_id' => $ultimo->id,
            'motivo' => $logs->count() > 1 ? "{$logs->count()} registros del mismo usuario." : 'Un registro en bitácora.',
        ];
    }
}
