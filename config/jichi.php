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
    |
    | YA NO SE LEE EN TIEMPO DE EJECUCION: desde el 20/09/2026 la fuente es la
    | tabla `departamentos`, y esta lista es la SEMILLA con la que su migración
    | la llena. Se deja acá para no tener los nueve nombres escritos adentro de
    | una migración.
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
    | ficha del beneficiario y el formulario de trámite— y tenían que decir lo
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

        /*
        | NO HAY `prefijo_s3` ACÁ, Y SE SACÓ A PROPÓSITO.
        |
        | La carpeta raíz dentro del bucket sale de AWS_ROOT, pero la aplica el
        | disco: config/filesystems.php declara `'root' => env('AWS_ROOT')` y
        | Flysystem la antepone solo en cada operación.
        |
        | Antes existía además esta clave y StorageController la pegaba a mano al
        | armar la dirección. Eso significaba que la misma carpeta se agregaba dos
        | veces —`dev/dev/tramites/...`— y bastaba con que alguien mirara el
        | bucket para no entender nada. Una sola fuente, la del disco.
        */
    ],

    /*
    | Guías de movimiento: lo que cuesta amparar un traslado.
    |
    | LA TARIFA VIVE ACÁ Y NO EN UNA TABLA porque hoy es UN solo valor para
    | todas las guías: una tabla de una fila es una pantalla de mantenimiento
    | que nadie va a abrir y una consulta por cada guía emitida. El día que se
    | vuelva una escala —por destino, por volumen— pasa a `GuiaMovimiento::
    | montoACobrar()` leer esa tabla, y el resto del circuito de cobro no se
    | entera.
    |
    | El DESCUENTO de piscicultura no está acá sino en
    | `GuiaMovimiento::DESCUENTO_PISCICULTURA`, y la diferencia es deliberada:
    | la tarifa es un número que la unidad ajusta, el descuento es una REGLA de
    | la resolución. Puesto en configuración, alguien lo apaga desde una
    | pantalla y el sistema empieza a cobrarle de más al criadero sin que quede
    | registro de quién lo decidió.
    */
    'guias' => [
        'tarifa_base' => (float) env('JICHI_GUIA_TARIFA_BASE', 50),
    ],

    /*
    | Permiso de faena: lo que sale cada salida de pesca.
    |
    | La faena se cobra y se firma como el carnet y el aprovechamiento, así que
    | necesita su arancel. Va acá y no en una tabla por lo mismo que el de las
    | guías: es UN número que la unidad ajusta por resolución.
    */
    'faenas' => [
        'tarifa_base' => (float) env('JICHI_FAENA_TARIFA_BASE', 15),
    ],

    /*
    | Aprovechamiento pesquero: qué tan duro es el tope de la bolsa madre.
    |
    | ----------------------------------------------------------------------------
    |  ESTRICTO (por defecto): el cupo es un LÍMITE
    | ----------------------------------------------------------------------------
    |
    | Al emitir una faena se comprueba que los kilos entren en el saldo. Cuando
    | llega a cero el aprovechamiento pasa a `agotado` y no se emiten más
    | permisos: el pescador tiene que tramitar uno nuevo —elegir el tramo, pagar
    | en caja, recibo nuevo— o pedir una ampliación.
    |
    | Es el modo que la resolución describe, y por eso es el valor por defecto:
    | un sistema que arranca sin control y hay que acordarse de encender no
    | controla nada.
    |
    | ----------------------------------------------------------------------------
    |  FLEXIBLE: el cupo es una REFERENCIA
    | ----------------------------------------------------------------------------
    |
    | Se omite la comprobación del tope y las faenas se emiten sin límite,
    | aunque superen el volumen otorgado. Existe para dos situaciones reales:
    | poner al día un padrón donde el papel ya fue más allá del cupo, y arrancar
    | en una unidad que todavía no tiene la escala cargada del todo.
    |
    | LO QUE NO HACE ES BORRAR EL DATO. El exceso se sigue midiendo —ver
    | `AprovechamientoPesq::kilosExcedidos()`— y las pantallas lo muestran, así
    | que al volver a estricto se sabe exactamente quién está por encima.
    |
    | Se lee con config() y NUNCA con env() fuera de este archivo: con
    | `config:cache` activo, env() devuelve null y el error es silencioso — el
    | sistema creería que el modo es flexible y dejaría de controlar el cupo sin
    | avisar.
    */
    'aprovechamiento' => [
        'estricto' => (bool) env('APROVECHAMIENTO_ESTRICTO', true),
    ],

];
