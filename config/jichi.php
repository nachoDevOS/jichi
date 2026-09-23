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

    /*
    | Vacío = el formulario acepta cualquier correo; con valor, exige ese
    | dominio. No está en .env porque el módulo de Usuarios todavía no tiene
    | ruta. Ver GuardarUsuarioRequest.
    */
    'dominio_institucional' => env('JICHI_DOMINIO_INSTITUCIONAL'),

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
        'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'mimes' => 'application/pdf,image/jpeg,image/png,image/webp',

        // Las fotos NO: se imprimen en la credencial, y un PDF no se dibuja.
        'extensiones_imagen' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimes_imagen' => 'image/jpeg,image/png,image/webp',

        // Sin `prefijo_s3`: la carpeta raíz del bucket la aplica el disco
        // (`AWS_ROOT` en filesystems.php). Puesta acá además, se agregaba dos
        // veces: `dev/dev/tramites/...`.
    ],

    /*
    | UN solo valor para todas las guías: una tabla de una fila sería una
    | pantalla que nadie abre y una consulta por guía emitida.
    |
    | El DESCUENTO de piscicultura NO está acá sino en
    | `GuiaMovimiento::DESCUENTO_PISCICULTURA`: la tarifa es un número que la
    | unidad ajusta, el descuento es una REGLA de la resolución. En
    | configuración, alguien lo apaga y nadie sabe quién.
    */
    'guias' => [
        'tarifa_base' => (float) env('JICHI_GUIA_TARIFA_BASE', 50),
    ],

    // Mismo criterio que la guía: un número que se ajusta por resolución.
    'faenas' => [
        'tarifa_base' => (float) env('JICHI_FAENA_TARIFA_BASE', 15),
    ],

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

];
