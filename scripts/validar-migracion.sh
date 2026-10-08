#!/usr/bin/env bash
#
# Valida las migraciones pendientes sobre una COPIA de la base de datos.
# Nunca modifica la base original: restaura el respaldo en una base temporal,
# migra ahí y compara el número de registros de cada tabla antes y después.
#
# Uso (desde la raíz del proyecto):
#   DB_USERNAME=usuario DB_PASSWORD='***' scripts/validar-migracion.sh respaldo.sql[.gz] [--keep]
#
# Variables opcionales: DB_HOST (127.0.0.1), DB_PORT (3306), DB_CONNECTION (mariadb),
# MYSQL_BIN (mysql). --keep conserva la base temporal para revisarla a mano.
#
# El usuario de base de datos necesita permiso para CREATE/DROP DATABASE.
set -euo pipefail

DUMP="${1:-}"
KEEP="${2:-}"
[[ -f "$DUMP" ]] || { echo "Uso: $0 respaldo.sql[.gz] [--keep]"; exit 1; }

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:?Define DB_USERNAME}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_CONNECTION="${DB_CONNECTION:-mariadb}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"
TMP_DB="erp_migtest_$(date +%Y%m%d%H%M%S)"
OUT="storage/logs/validacion-migracion-$(date +%Y%m%d%H%M%S)"
mkdir -p "$OUT"

if [[ -f bootstrap/cache/config.php ]]; then
  echo "La configuración está cacheada (bootstrap/cache/config.php): Laravel ignoraría la base temporal."
  echo "Ejecuta 'php artisan config:clear' (o usa una copia del proyecto) y vuelve a intentar."
  exit 1
fi

export MYSQL_PWD="$DB_PASSWORD"
# tr: el cliente de Windows devuelve CRLF.
sql() { "$MYSQL_BIN" -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" "$@" | tr -d '\r'; }

counts() {
  sql -N "$TMP_DB" -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$TMP_DB' AND table_type='BASE TABLE' ORDER BY table_name" \
    | while read -r t; do echo -e "$t\t$(sql -N "$TMP_DB" -e "SELECT COUNT(*) FROM \`$t\`")"; done
}

cleanup() {
  if [[ "$KEEP" != "--keep" ]]; then sql -e "DROP DATABASE IF EXISTS \`$TMP_DB\`" || true; fi
}
trap cleanup EXIT

echo "1/6 Restaurando el respaldo en la base temporal $TMP_DB…"
sql -e "CREATE DATABASE \`$TMP_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if [[ "$DUMP" == *.gz ]]; then gunzip -c "$DUMP" | sql "$TMP_DB"; else sql "$TMP_DB" < "$DUMP"; fi

# Laravel apunta solo a la base temporal; sin correos, difusión ni caché compartida.
export DB_CONNECTION DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE="$TMP_DB"
export CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync BROADCAST_CONNECTION=null MAIL_MAILER=log

echo "2/6 Confirmando que Laravel usa la base temporal…"
php artisan erp:check-deploy | tee "$OUT/check-deploy.txt" || true
grep -q "$TMP_DB" "$OUT/check-deploy.txt" || { echo "Laravel no está usando $TMP_DB. Abortado."; exit 1; }

counts > "$OUT/antes.tsv"
sql -N -e "SELECT table_name, engine FROM information_schema.tables WHERE table_schema='$TMP_DB' AND table_type='BASE TABLE'" > "$OUT/motores-antes.tsv"

echo "3/6 Simulación (--pretend)…"
php artisan migrate --pretend --force > "$OUT/pretend.sql"
echo "   Sentencias guardadas en $OUT/pretend.sql"

echo "4/6 Migrando la copia…"
START=$(date +%s)
php artisan migrate --force | tee "$OUT/migrate.txt"
echo "   Duración: $(( $(date +%s) - START )) s (estimado de la ventana de mantenimiento)."

echo "5/6 Comparando registros…"
counts > "$OUT/despues.tsv"
FAIL=0
while IFS=$'\t' read -r t n; do
  after=$(awk -F'\t' -v t="$t" '$1==t{print $2}' "$OUT/despues.tsv")
  if [[ "$after" != "$n" ]]; then
    # Tablas de sistema que cambian legítimamente al migrar.
    if [[ "$t" == "migrations" || "$t" == "cache" || "$t" == "jobs" ]]; then continue; fi
    # Catálogo de permisos y asignaciones a roles: las transiciones solo agregan.
    # Un aumento es esperado; una disminución sí es un error.
    if [[ "$t" == "permissions" || "$t" == "role_has_permissions" ]] && [[ -n "$after" && "$after" -gt "$n" ]]; then
      echo "   + $t: antes $n, después $after (solo se agregaron registros)"; continue
    fi
    echo "   ✗ $t: antes $n, después ${after:-(no existe)}"; FAIL=1
  fi
done < "$OUT/antes.tsv"

MYISAM=$(sql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$TMP_DB' AND engine='MyISAM'")
SIN_ROL=$(sql -N "$TMP_DB" -e "SELECT COUNT(*) FROM users u WHERE NOT EXISTS (SELECT 1 FROM model_has_roles m WHERE m.model_id=u.id AND m.model_type LIKE '%User')")
echo "   Tablas MyISAM restantes: $MYISAM"
echo "   Usuarios sin rol: $SIN_ROL"
sql -N "$TMP_DB" -e "SELECT UPPER(COALESCE(u.rol,'')), r.name, COUNT(*) FROM users u JOIN model_has_roles m ON m.model_id=u.id AND m.model_type LIKE '%User' JOIN roles r ON r.id=m.role_id GROUP BY 1,2" \
  | awk -F'\t' '{printf "   users.rol=%s → %s: %s usuario(s)\n", ($1==""?"(vacío)":$1), $2, $3}'
[[ "$MYISAM" == "0" && "$SIN_ROL" == "0" ]] || FAIL=1

echo "6/6 Resultado"
if [[ $FAIL -eq 0 ]]; then
  echo "   ✓ Migración validada: ningún registro existente se perdió. Evidencia en $OUT/"
else
  echo "   ✗ Revisa las diferencias anteriores antes de migrar producción. Evidencia en $OUT/"
  exit 1
fi
