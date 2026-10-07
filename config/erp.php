<?php

return [
    'notify_email' => env('ERP_NOTIFY_EMAIL'),
    'erp_url' => env('ERP_URL', 'https://erp.mr-lana.com'),
    'support_url' => env('ERP_SUPPORT_URL', 'https://soporte.mr-lana.com'),

    'allow_registration' => (bool) env('ERP_ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de negocio
    |--------------------------------------------------------------------------
    | Se usa para determinar "hoy" en validaciones de negocio (p. ej. la fecha
    | de solicitud de una requisición), sin depender del reloj del navegador.
    */
    'business_timezone' => env('ERP_BUSINESS_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Descarga de la aplicación móvil (AppView)
    |--------------------------------------------------------------------------
    | Valor de respaldo cuando no se ha configurado una URL desde el módulo
    | Configuración. Si ambos están vacíos, el botón se muestra deshabilitado.
    */
    'mobile_app_url' => env('ERP_MOBILE_APP_URL'),

    /*
    |--------------------------------------------------------------------------
    | Correos de respaldo para notificaciones de requisiciones
    |--------------------------------------------------------------------------
    | Lista separada por comas. Canal secundario: las notificaciones internas
    | (campana) se envían por rol; el correo nunca revierte una operación.
    */
    'requisicion_notify_to' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('REQUISICION_NOTIFY_TO', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Generación de PDF
    |--------------------------------------------------------------------------
    | driver: "browsershot" (Chrome headless, alta calidad) o "dompdf".
    | Si Browsershot falla (p. ej. Chrome no instalado) se usa DomPDF como
    | respaldo automático para no interrumpir la descarga.
    */
    'pdf' => [
        'driver' => env('ERP_PDF_DRIVER', 'browsershot'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', true),
        'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reversión de migraciones
    |--------------------------------------------------------------------------
    | Por seguridad, revertir una migración que borraría información real
    | (roles personalizados, notificaciones, ajustes auditados…) se detiene.
    | El plan de reversión es restaurar el respaldo (ver docs/despliegue.md).
    | Solo activa esto de forma temporal y con un respaldo verificado.
    */
    'allow_destructive_rollback' => (bool) env('ERP_ALLOW_DESTRUCTIVE_ROLLBACK', false),
];
