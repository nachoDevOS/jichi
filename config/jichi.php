<?php

/*
|--------------------------------------------------------------------------
| Configuración propia de Jichi
|--------------------------------------------------------------------------
|
| Todo valor que venga del archivo .env y que el código necesite leer debe
| pasar por acá. ¿Por qué? Porque en producción se ejecuta `php artisan
| config:cache`, y a partir de ese momento la función env() devuelve null
| en cualquier parte del código que no sea un archivo de config/.
|
| Regla de oro de Laravel:
|   - env('LO_QUE_SEA')  -> SOLO dentro de config/
|   - config('jichi.lo_que_sea') -> en todo el resto del sistema
|
*/

return [

    /*
    | Contraseña con la que se crean los usuarios institucionales al ejecutar
    | `php artisan db:seed`. Se define en .env con JICHI_SEED_PASSWORD.
    |
    | IMPORTANTE: cambiarla antes de cualquier despliegue real.
    */
    'password_semilla' => env('JICHI_SEED_PASSWORD', 'password'),

    /*
    | URL pública base que se imprime dentro del código QR de cada documento.
    | Debe apuntar al dominio real del sistema, porque el ciudadano escanea
    | el QR desde su teléfono, fuera de la red departamental.
    */
    'url_verificacion' => env('JICHI_URL_VERIFICACION'),

    /*
    | Cantidad de filas por página en los listados del panel.
    */
    'por_pagina' => (int) env('JICHI_POR_PAGINA', 15),

    /*
    | Dominio de correo de la Gobernación, para las cuentas de los
    | funcionarios (ver GuardarUsuarioRequest).
    |
    | Queda VACÍO a propósito por defecto. Sin valor, el formulario acepta
    | cualquier correo válido; con valor, exige que termine en este dominio.
    | Se deja optativo porque en desarrollo y en las pruebas las cuentas se
    | crean con correos inventados, y una regla fija ahí solo estorbaría.
    |
    | Se escribe sin arroba: beniautonomo.gob.bo
    */
    'dominio_institucional' => env('JICHI_DOMINIO_INSTITUCIONAL'),

    /*
    | Los nueve departamentos de Bolivia, para el campo "expedido" de la
    | cédula de identidad. Es la lista que imprime el SEGIP y no cambia.
    */
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

    /*
    | Las ocho provincias del Beni.
    |
    | Viven acá y no en cada formulario porque las piden dos pantallas —la
    | ficha del solicitante y la cédula de pescador— y tenían que decir lo
    | mismo. Escritas dos veces, tarde o temprano una se queda sin actualizar.
    */
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
    | Qué archivos acepta el sistema, y hasta cuánto pesan.
    |
    | El límite vive acá y no repartido por los formularios porque tiene que
    | decir lo MISMO en tres lugares: la regla de validación del servidor, el
    | control del navegador que avisa antes de subir, y el texto de ayuda que
    | lee el operador. Escrito tres veces, alcanza con cambiar uno y que los
    | otros dos sigan diciendo el número viejo — y ahí el operador lee «hasta
    | 4 MB» y el sistema le rechaza un archivo de 3,5 MB sin que nadie entienda
    | por qué.
    |
    | El frontend lo recibe por Inertia; ver HandleInertiaRequests::share().
    |
    | OJO: `max_kb` son kilobytes de 1024 bytes, como los cuenta la regla `max`
    | de Laravel. 3072 KB = 3 MiB exactos, que es lo mismo que mide el
    | navegador con `archivo.size`.
    */
    'archivos' => [
        'max_kb' => (int) env('JICHI_ARCHIVO_MAX_KB', 3072),

        /*
        | Los respaldos de un trámite se aceptan en PDF además de imagen: la
        | certificación de la asociación suele llegar escaneada desde una
        | fotocopiadora, y esas máquinas devuelven PDF.
        */
        'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
        'mimes' => 'application/pdf,image/jpeg,image/png,image/webp',

        /*
        | Las fotografías, en cambio, solo pueden ser imagen: se imprimen en la
        | credencial, y un PDF ahí no se puede dibujar.
        */
        'extensiones_imagen' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimes_imagen' => 'image/jpeg,image/png,image/webp',
    ],

];
