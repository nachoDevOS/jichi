<?php

/*
| Configuración propia de Jichi.
|
| Todo valor del .env que el código necesite pasa por acá: con `config:cache`
| activo, env() devuelve null fuera de config/ y el error es silencioso.
*/

return [

    // Cambiarla antes de cualquier despliegue real.
    'password_semilla' => env('JICHI_SEED_PASSWORD', 'password'),

    'por_pagina' => (int) env('JICHI_POR_PAGINA', 15),

    // La SEMILLA de la tabla `departamentos`, que es la fuente en ejecución.
    // Vive acá para no tener los nueve nombres dentro de una migración.
    'expedido' => [
        'BN' => 'Beni',
        'CH' => 'Chuquisaca',
        'CB' => 'Cochabamba',
        'LP' => 'La Paz',
        'OR' => 'Oruro',
        'PD' => 'Pando',
        'PT' => 'Potosí',
        'SC' => 'Santa Cruz',
        'TJ' => 'Tarija',
    ],

    // Las piden dos pantallas: escritas dos veces, una se queda vieja.
    'provincias' => [
        'Cercado',
        'Moxos',
        'Marbán',
        'Yacuma',
        'Mamoré',
        'Iténez',
        'Ballivián',
        'Vaca Díez',
    ],

    /*
    | El tope vive acá porque tiene que decir lo MISMO en tres lugares: la
    | validación del servidor, el aviso del navegador y el texto de ayuda.
    | `max_kb` son KiB, como los cuenta la regla `max` de Laravel.
    */
    'archivos' => [
        'max_kb' => (int) env('JICHI_ARCHIVO_MAX_KB', 3072),

        // Los respaldos aceptan PDF: la certificación del gremio llega
        // escaneada desde una fotocopiadora.
        'mimes' => 'application/pdf,image/jpeg,image/png,image/webp',

        // Las fotos NO: se imprimen en la credencial, y un PDF no se dibuja.
        'extensiones_imagen' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimes_imagen' => 'image/jpeg,image/png,image/webp',

        // Sin `prefijo_s3`: la carpeta raíz del bucket la aplica el disco
        // (`AWS_ROOT` en filesystems.php). Puesta acá además, se agregaba dos
        // veces: `dev/dev/tramites/...`.
    ],

    // Los precios de la faena y de la guía NO viven acá: los pone SIREB
    // (Catálogos › Aranceles y › Productos). Ver docs/modulos/SIREB.md.

    /*
    | ESTRICTO: el cupo es un LÍMITE. Al emitir una faena se comprueba que los
    | kilos entren en el saldo, y en cero el cupo pasa a `agotado`. Es lo que
    | dice la resolución, y por eso es el valor por defecto.
    |
    | FLEXIBLE: el cupo es una REFERENCIA. Existe para poner al día un padrón
    | donde el papel ya fue más allá. NO borra el dato: el exceso se sigue
    | midiendo con `AprovechamientoPesq::kilosExcedidos()`.
    */
    'aprovechamiento' => [
        'estricto' => (bool) env('APROVECHAMIENTO_ESTRICTO', true),
    ],

    // Login centralizado del GAD (OAuth2). Apagado = login propio por correo.
    // Encendido, el login local queda solo para administradores. Ver docs/modulos/IBARE.md.
    'ibare' => [
        'activo' => (bool) env('IBARE_ACTIVO', false),
        'url' => rtrim((string) env('IBARE_URL', 'http://localhost:8000'), '/'),
        'client_id' => env('IBARE_CLIENT_ID', 'jichi'),
        'client_secret' => env('IBARE_CLIENT_SECRET'),
        'timeout' => (int) env('IBARE_TIMEOUT', 10),
    ],

    // Recaudaciones (SIREB): la fuente de los precios. Jichi guarda el código del
    // servicio y pide el monto al otorgar. Credencial de máquina aparte de la del
    // login; el token lo emite Ibare. Ver docs/modulos/SIREB.md.
    'sireb' => [
        'url' => rtrim((string) env('SIREB_URL'), '/'),
        // El Ibare que emite el token tiene que ser el mismo en el que confía ese
        // SIREB. Propio y sin caer en IBARE_URL: el del login puede ser otro.
        'ibare_url' => rtrim((string) env('SIREB_IBARE_URL'), '/'),
        'client_id' => env('SIREB_CLIENT_ID', 'sedag'),
        'client_secret' => env('SIREB_CLIENT_SECRET'),
        'timeout' => (int) env('SIREB_TIMEOUT', 10),
        'cache_minutos' => (int) env('SIREB_CACHE_MINUTOS', 10),
    ],

];
