<?php

namespace App\Traits;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Bitácora automática de cambios (system_logs).
 *
 * Acciones:
 * - CREACION / ACTUALIZACION / ELIMINACION (física)
 * - BAJA (eliminación lógica): activo → false, o status → ELIMINADA / INACTIVO
 * - REACTIVACION: activo → true, o status que sale de ELIMINADA / INACTIVO
 * - CAMBIO_ESTATUS: cualquier otro cambio de status (p. ej. CAPTURADA → PAGADA)
 *
 * Cada entrada guarda quién, cuándo, desde qué IP, el nombre legible del
 * registro y los cambios campo por campo (antes/después) en JSON.
 *
 * Las actualizaciones masivas deben usar ->updateEach([...]) (macro del
 * query builder) para que cada registro quede en la bitácora.
 */
trait LogsActivity
{
    /** Campos que no se registran (ruido o datos sensibles). */
    protected array $logIgnoreFields = [
        'updated_at', 'created_at', 'deleted_at', 'remember_token', 'password', 'current_jti',
    ];

    /** Valores de status que representan una baja lógica. */
    public const LOG_DELETED_STATUSES = ['ELIMINADA', 'INACTIVO', 'ELIMINADO', 'CANCELADA'];

    protected int $logMaxLen = 2000;

    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $model->writeSystemLog('CREACION', 'Registro creado: '.$model->logEntityName().'.');
        });

        static::updated(function (Model $model) {
            $result = $model->buildUpdateLog();
            if ($result !== null) {
                $model->writeSystemLog(...$result);
            }
        });

        static::deleted(function (Model $model) {
            $model->writeSystemLog('ELIMINACION', 'Eliminación definitiva: '.$model->logEntityName().'.', $model->snapshotForLog());
        });
    }

    /**
     * Registra una acción explícita (p. ej. tras una actualización atómica
     * que no dispara eventos del modelo).
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  campo => [antes, después]
     */
    public function auditLog(string $accion, string $descripcion, array $changes = []): void
    {
        $this->writeSystemLog($accion, $descripcion, $this->formatChanges($changes));
    }

    /** @return array{0: string, 1: string, 2: array}|null */
    protected function buildUpdateLog(): ?array
    {
        $changes = [];
        foreach ($this->getChanges() as $field => $new) {
            if (in_array($field, $this->logIgnoreFields, true)) {
                continue;
            }
            $old = $this->getOriginal($field);
            if ($this->normalizeForLog($old) === $this->normalizeForLog($new)) {
                continue;
            }
            $changes[$field] = [$old, $new];
        }

        if ($changes === []) {
            return null;
        }

        $entity = $this->logEntityName();
        $accion = 'ACTUALIZACION';
        $titulo = "Actualización: {$entity}.";

        if (array_key_exists('activo', $changes)) {
            $accion = (bool) $changes['activo'][1] ? 'REACTIVACION' : 'BAJA';
            $titulo = $accion === 'BAJA' ? "Baja (eliminación lógica): {$entity}." : "Reactivación: {$entity}.";
        } elseif (array_key_exists('status', $changes)) {
            [$old, $new] = $changes['status'];
            $wasDeleted = in_array(strtoupper((string) $old), self::LOG_DELETED_STATUSES, true);
            $isDeleted = in_array(strtoupper((string) $new), self::LOG_DELETED_STATUSES, true);
            [$accion, $titulo] = match (true) {
                $isDeleted && ! $wasDeleted => ['BAJA', "Baja (eliminación lógica): {$entity}."],
                $wasDeleted && ! $isDeleted => ['REACTIVACION', "Reactivación: {$entity}."],
                default => ['CAMBIO_ESTATUS', "Cambio de estatus: {$entity} ({$old} → {$new})."],
            };
        }

        $lines = [];
        foreach ($changes as $field => [$old, $new]) {
            $lines[] = '- '.$field.': '.$this->valueToShortString($old).' → '.$this->valueToShortString($new);
        }

        return [$accion, $titulo."\nCambios:\n".implode("\n", $lines), $this->formatChanges($changes)];
    }

    /** @return array<string, array{antes: mixed, despues: mixed}> */
    protected function formatChanges(array $changes): array
    {
        $out = [];
        foreach ($changes as $field => [$old, $new]) {
            $out[$field] = ['antes' => $this->valueForJson($old), 'despues' => $this->valueForJson($new)];
        }

        return $out;
    }

    /** Copia de los campos del registro al eliminarlo físicamente (para poder rastrearlo). */
    protected function snapshotForLog(): array
    {
        $out = [];
        foreach ($this->getAttributes() as $field => $value) {
            if (! in_array($field, $this->logIgnoreFields, true)) {
                $out[$field] = ['antes' => $this->valueForJson($value), 'despues' => null];
            }
        }

        return $out;
    }

    /** Nombre legible del registro: folio, nombre, razón social… */
    public function logLabel(): ?string
    {
        foreach (['folio', 'nombre', 'name', 'razon_social', 'titulo', 'title', 'codigo'] as $candidate) {
            $value = $this->getAttribute($candidate);
            if (is_scalar($value) && trim((string) $value) !== '') {
                $label = trim((string) $value);
                if ($candidate === 'nombre' && ($ap = $this->getAttribute('apellido_paterno'))) {
                    $label .= ' '.trim((string) $ap);
                }

                return mb_substr($label, 0, 255);
            }
        }

        return null;
    }

    protected function logEntityName(): string
    {
        $label = $this->logLabel();
        $ref = $this->getTable().'#'.$this->getKey();

        return $label ? "{$label} ({$ref})" : $ref;
    }

    protected function normalizeForLog(mixed $value): mixed
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_numeric($value)) {
            return (string) (float) $value;
        }

        return is_array($value) ? json_encode($value) : $value;
    }

    protected function valueForJson(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && mb_strlen($value) > 500) {
            return mb_substr($value, 0, 500).'…';
        }

        return is_object($value) ? (method_exists($value, '__toString') ? (string) $value : get_class($value)) : $value;
    }

    protected function valueToShortString(mixed $value): string
    {
        if (is_null($value)) {
            return 'vacío';
        }
        if (is_bool($value)) {
            return $value ? 'sí' : 'no';
        }
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[lista]';
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }
        $value = trim(is_object($value) ? (method_exists($value, '__toString') ? (string) $value : get_class($value)) : (string) $value);

        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120).'…' : $value;
    }

    protected function writeSystemLog(string $accion, string $descripcion, array $cambios = []): void
    {
        try {
            SystemLog::create([
                'user_id' => Auth::id(),
                'accion' => $accion,
                'tabla' => $this->getTable(),
                'registro_id' => $this->getKey(),
                'etiqueta' => $this->logLabel(),
                'ip_address' => Request::ip(),
                'user_agent' => mb_substr((string) Request::header('User-Agent'), 0, 255),
                'descripcion' => mb_substr($descripcion, 0, $this->logMaxLen),
                'cambios' => $cambios ?: null,
            ]);
        } catch (\Throwable $e) {
            // La bitácora nunca debe interrumpir la operación; el error queda en el log.
            report($e);
        }
    }
}
