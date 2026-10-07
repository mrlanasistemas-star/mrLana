# Despliegue seguro en el VPS

Guía para publicar el incremento de **roles y permisos, notificaciones,
ajustes auditados, configuración y PDF** sin perder información.

> Regla principal: **nunca migres producción sin un respaldo verificado y sin
> haber validado la migración sobre una copia de esa misma base.**

Los ejemplos suponen Ubuntu/Debian, Nginx, PHP-FPM 8.3, el proyecto en
`/var/www/mrlana` y el usuario `www-data`. Ajusta rutas y versiones a tu servidor.

---

## 0. Requisitos del servidor

| Componente | Versión / nota |
|---|---|
| PHP | 8.2 o superior con `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `xml`, `bcmath`, `fileinfo` |
| MySQL / MariaDB | MySQL 8 o MariaDB 10.6+. Las tablas nuevas se crean en InnoDB |
| Composer | 2.x |
| Node.js | 20 LTS o superior (compilar el frontend y para Browsershot) |
| Google Chrome o Chromium | Para PDF con Browsershot. Sin Chrome se usa DomPDF automáticamente |
| Supervisor o systemd | Para el worker de colas y Reverb |

---

## 1. Respaldo (obligatorio)

```bash
cd /var/www/mrlana
php artisan down --retry=60          # opcional pero recomendado: evita escrituras durante el respaldo
mkdir -p ~/respaldos && chmod 700 ~/respaldos
FECHA=$(date +%Y%m%d-%H%M)

# Si hay tablas MyISAM, --single-transaction NO garantiza consistencia; con la
# aplicación en mantenimiento, --lock-tables la asegura.
mysqldump -u USUARIO -p --routines --triggers --events \
  --single-transaction --lock-tables --default-character-set=utf8mb4 \
  NOMBRE_BD | gzip > ~/respaldos/mrlana-$FECHA.sql.gz

# Verifica que el respaldo no esté vacío ni truncado
gunzip -t ~/respaldos/mrlana-$FECHA.sql.gz && ls -lh ~/respaldos/mrlana-$FECHA.sql.gz
zcat ~/respaldos/mrlana-$FECHA.sql.gz | tail -1     # debe terminar con "-- Dump completed"

# Respalda también los archivos subidos
tar czf ~/respaldos/storage-$FECHA.tar.gz storage/app
```

- Guarda los respaldos **fuera** de la carpeta pública y **nunca** los subas a Git
  (contienen datos personales). Copia uno fuera del servidor.
- Respalda también el `.env` actual: `cp .env ~/respaldos/env-$FECHA`.

---

## 2. Revisión previa (solo lectura)

```bash
php artisan erp:check-deploy
```

Reporta, sin modificar nada:

- **Colaboradores con más de una cuenta** (`users.empleado_id` duplicado). Bloquea
  la migración: deja una sola cuenta por colaborador. Consulta manual equivalente:

  ```sql
  SELECT empleado_id, GROUP_CONCAT(id ORDER BY id) AS usuarios, COUNT(*) AS total
  FROM users WHERE empleado_id IS NOT NULL
  GROUP BY empleado_id HAVING COUNT(*) > 1;

  -- Desvincular la cuenta sobrante (decide cuál conservar):
  UPDATE users SET empleado_id = NULL WHERE id = <id_sobrante>;
  ```

- **Tablas MyISAM**: se convierten a InnoDB al migrar (`ALTER TABLE … ENGINE=InnoDB`
  conserva datos e índices, pero bloquea cada tabla mientras la copia).
- **Mapeo de `users.rol`** → rol nuevo: `ADMIN` → Administrador,
  `CONTADOR` → Contabilidad, cualquier otro valor → Colaborador.
  `users.rol` se conserva como dato legado.
- Migraciones pendientes y configuración (`APP_DEBUG`, cola, `storage:link`, PDF).

---

## 3. Validar la migración sobre una copia

Hazlo en el VPS o en tu equipo con el respaldo del paso 1. El script crea una base
temporal, restaura el respaldo, migra **solo esa copia** y compara el número de
registros de cada tabla antes y después:

```bash
php artisan config:clear      # el script se niega a correr con la configuración cacheada
DB_USERNAME=USUARIO DB_PASSWORD='***' scripts/validar-migracion.sh ~/respaldos/mrlana-$FECHA.sql.gz
```

Resultado esperado: `✓ Migración validada: ningún registro existente se perdió`,
0 tablas MyISAM y 0 usuarios sin rol. La evidencia (pretend, salida y conteos) queda
en `storage/logs/validacion-migracion-*/`. La duración mostrada estima la ventana
de mantenimiento. El usuario de MySQL necesita permiso `CREATE`/`DROP` de bases.

Si algo falla, **no migres producción**: corrige y repite.

---

## 4. Despliegue

```bash
cd /var/www/mrlana
php artisan down --retry=60                     # si no lo hiciste en el paso 1

git fetch origin && git checkout main && git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction

# Variables nuevas: compáralas con .env.example (sección 6) antes de compilar.
npm ci && npm run build                         # VITE_REVERB_* se incrustan aquí

php artisan migrate --pretend --force           # revisa las sentencias
php artisan migrate --force

php artisan storage:link                        # logo y comprobantes públicos (una sola vez)
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

php artisan queue:restart                       # el worker toma el código nuevo
sudo supervisorctl restart mrlana-reverb        # o: sudo systemctl restart mrlana-reverb

php artisan up
```

Permisos de escritura (una vez): `sudo chown -R www-data:www-data storage bootstrap/cache`.

### Comprobaciones después de publicar

1. Inicia sesión como Administrador: Usuarios, Roles, Notificaciones y Configuración abren sin error.
2. Inicia sesión como Contabilidad y como Colaborador: el menú solo muestra lo permitido.
3. Descarga un PDF y un Excel (p. ej. Requisiciones).
4. Solicita un ajuste con un colaborador y verifica que Contabilidad reciba la notificación.
5. `php artisan queue:failed` debe estar vacío; revisa `storage/logs/laravel.log`.
6. Da de baja a un colaborador de prueba con cuenta: su cuenta debe quedar desactivada y no poder iniciar sesión.

### Autorizador de pagos anteriores (opcional)

Los pagos autorizados antes de esta versión muestran «No registrado» en «Autorizó». El comando
siguiente lo recupera **solo** cuando la bitácora lo identifica sin ambigüedad (mismo usuario
existente y registro junto a la fecha de autorización); el resto se queda como está.

```bash
php artisan erp:backfill-autorizador-pago --dry-run   # revisa la tabla: Recuperable / Ambiguo / No registrado
php artisan erp:backfill-autorizador-pago             # guarda solo los «Recuperable» y lo anota en bitácora
```

---

## 5. Plan de reversión

**No uses `php artisan migrate:rollback` en producción como plan principal.** Las
migraciones se niegan a revertir si borrarían información real (roles
personalizados, asignaciones, notificaciones, auditoría de ajustes, configuración).
El plan de reversión es restaurar el respaldo:

```bash
php artisan down
git checkout <commit_anterior>                   # el que estaba publicado
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Restaurar la base (reemplaza el contenido actual por el del respaldo)
mysql -u USUARIO -p -e "DROP DATABASE NOMBRE_BD; CREATE DATABASE NOMBRE_BD CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
zcat ~/respaldos/mrlana-$FECHA.sql.gz | mysql -u USUARIO -p NOMBRE_BD

php artisan optimize:clear && php artisan config:cache
php artisan queue:restart
php artisan up
```

Todo lo capturado entre la migración y la restauración se pierde; por eso conviene
publicar en un horario de baja actividad y revisar rápido.

Solo en una emergencia, con un respaldo verificado, puedes permitir temporalmente un
rollback destructivo con `ERP_ALLOW_DESTRUCTIVE_ROLLBACK=true` (y `php artisan config:clear`).
Vuelve a ponerlo en `false` al terminar.

---

## 6. Variables de entorno nuevas

Ver `.env.example`. Resumen para producción:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.tu-dominio.com
APP_LOCALE=es
APP_FALLBACK_LOCALE=en

QUEUE_CONNECTION=database        # requiere worker (sección 7)

ERP_URL=https://erp.tu-dominio.com
ERP_SUPPORT_URL=https://soporte.tu-dominio.com
ERP_NOTIFY_EMAIL=
ERP_ALLOW_REGISTRATION=false
ERP_BUSINESS_TIMEZONE=America/Mexico_City
ERP_MOBILE_APP_URL=              # respaldo si no se define en Configuración
REQUISICION_NOTIFY_TO=           # correos separados por comas (opcional)
ERP_ALLOW_DESTRUCTIVE_ROLLBACK=false

ERP_PDF_DRIVER=browsershot
BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_NO_SANDBOX=true
BROWSERSHOT_TIMEOUT=60

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<generado>
REVERB_APP_KEY=<generado>
REVERB_APP_SECRET=<generado>
REVERB_HOST=erp.tu-dominio.com   # host público que usa el navegador
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1     # Reverb escucha local; Nginx lo publica
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Genera credenciales de Reverb con `php artisan reverb:install` (o valores aleatorios
largos). Si cambias cualquier `VITE_*`, vuelve a ejecutar `npm run build`.

---

## 7. Worker de colas (obligatorio)

Las notificaciones internas y los correos se encolan en la tabla `jobs`.
**Sin un worker activo, no se entregan.**

### Supervisor — `/etc/supervisor/conf.d/mrlana-worker.conf`

```ini
[program:mrlana-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mrlana/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/mrlana/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

### Alternativa systemd — `/etc/systemd/system/mrlana-worker.service`

```ini
[Unit]
Description=MR-Lana queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/mrlana
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload && sudo systemctl enable --now mrlana-worker
```

Tras cada despliegue: `php artisan queue:restart`.

---

## 8. Reverb (WebSocket en tiempo real)

Opcional: sin Reverb, la campana consulta el servidor cada 45 segundos.

### Supervisor — `/etc/supervisor/conf.d/mrlana-reverb.conf`

```ini
[program:mrlana-reverb]
command=php /var/www/mrlana/artisan reverb:start --host=127.0.0.1 --port=8080
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/mrlana/storage/logs/reverb.log
```

(Con systemd, igual que el worker cambiando `ExecStart` por el comando anterior.)

Aumenta el límite de archivos abiertos si esperas muchas conexiones
(`minfds=10000` en `[supervisord]` o `LimitNOFILE=10000` en systemd).

---

## 9. Servidor web

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name erp.tu-dominio.com;
    root /var/www/mrlana/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/erp.tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/erp.tu-dominio.com/privkey.pem;

    client_max_body_size 20M;          # comprobantes y logo
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Reverb: WebSocket y API de difusión
    location ~ ^/(app|apps)/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_read_timeout 60s;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 120;      # PDF grandes
    }

    location ~ /\.(?!well-known).* { deny all; }
}

server {
    listen 80;
    server_name erp.tu-dominio.com;
    return 301 https://$host$request_uri;
}
```

### Apache

Habilita `mod_rewrite`, `mod_proxy`, `mod_proxy_http` y `mod_proxy_wstunnel`.

```apache
<VirtualHost *:443>
    ServerName erp.tu-dominio.com
    DocumentRoot /var/www/mrlana/public

    <Directory /var/www/mrlana/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Reverb
    ProxyPreserveHost On
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/(app|apps)/(.*) ws://127.0.0.1:8080/$1/$2 [P,L]
    ProxyPass        /apps http://127.0.0.1:8080/apps
    ProxyPassReverse /apps http://127.0.0.1:8080/apps

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/erp.tu-dominio.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/erp.tu-dominio.com/privkey.pem
</VirtualHost>
```

---

## 10. PDF con Browsershot (Chrome headless)

```bash
# Chrome estable (o: sudo apt install chromium)
wget -q https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo apt install -y ./google-chrome-stable_current_amd64.deb
which google-chrome node npm      # rutas para BROWSERSHOT_*

# Dependencia de Node usada por Browsershot (ya está en package.json)
cd /var/www/mrlana && npm ci

# www-data necesita un HOME escribible para el perfil temporal de Chrome
sudo mkdir -p /var/www/.cache && sudo chown www-data:www-data /var/www/.cache
```

- `BROWSERSHOT_NO_SANDBOX=true` es necesario cuando PHP corre como `www-data`.
- Si Chrome falla o no existe, el sistema genera el PDF con **DomPDF** sin
  interrumpir la descarga y registra el error en `storage/logs/laravel.log`.
- Prueba: descarga cualquier PDF del sistema y revisa el log; si aparece un error
  de Browsershot, corrige rutas/permisos (el PDF se habrá generado con DomPDF).

---

## 11. Aplicación AppView («Descargar aplicación»)

La URL se define en **Configuración → Descargar aplicación** (o con
`ERP_MOBILE_APP_URL` como respaldo). Sin URL, el botón aparece deshabilitado.
El contenedor nativo de la AppView debe abrir los enlaces externos
(`target="_blank"`) en el navegador del sistema.

---

## 12. Cachés

```bash
php artisan optimize:clear     # limpia config, rutas, vistas, eventos y caché de aplicación
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan permission:cache-reset   # si cambian permisos fuera de la interfaz
```

Los cambios en Configuración (colores, logo, URL) limpian su caché automáticamente.
