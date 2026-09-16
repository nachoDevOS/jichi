<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ============================================================================
 *  DÓNDE ESTÁ UN ARCHIVO GUARDADO, Y CÓMO SE ABRE
 * ============================================================================
 *
 * `StorageController::file()` guarda siempre una RUTA:
 *
 *     'tramites/September2026/aB3x...1789.pdf'
 *
 * La misma con el disco local y con s3. La dirección para abrirla se arma acá,
 * AL LEER, con la configuración que el sistema tiene EN ESE MOMENTO.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ LA URL SE ARMA AL LEER Y NO SE GUARDA
 * ----------------------------------------------------------------------------
 *
 * Porque una dirección guardada queda congelada el día de la carga, y el lugar
 * donde viven los archivos cambia: se pasa de local a s3, cambia el bucket,
 * cambia el endpoint, se pone un CDN adelante. Cada uno de esos cambios dejaría
 * rotos todos los enlaces viejos y obligaría a salir a reescribir filas.
 *
 * Armada al leer, un cambio de disco es cambiar el `.env`: las rutas guardadas
 * siguen valiendo y los enlaces salen apuntando al lugar nuevo.
 *
 * Y sobre todo: **se puede borrar**. Desde una dirección completa no hay forma
 * de volver a la clave del objeto sin conocer el prefijo del bucket, y por eso
 * cada adjunto reemplazado quedaba ocupando lugar en s3 para siempre.
 */
class Archivos
{
    /**
     * El disco donde el sistema guarda y busca los adjuntos.
     *
     * Sale de `FILESYSTEM_DISK`, igual que para StorageController. Vive acá —en
     * un solo método— para que escribir, leer y borrar no puedan terminar
     * mirando discos distintos.
     */
    private static function disco(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    /**
     * La dirección para abrir un archivo. NULL si no hay nada guardado.
     */
    public static function url(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        /*
         * FILAS VIEJAS: las que se cargaron cuando el sistema guardaba la
         * dirección completa en vez de la ruta.
         *
         * Se devuelven tal cual. Pasarlas por `Storage::url()` las pegaría
         * detrás del dominio local y saldría algo como
         * `http://jichi.test/storage/https://gadbeni.sfo3...`, que no abre nada.
         *
         * Esta rama es compatibilidad hacia atrás y se puede sacar el día que no
         * queden filas así en la base.
         */
        if (Str::startsWith($valor, ['http://', 'https://'])) {
            return $valor;
        }

        return Storage::disk(self::disco())->url($valor);
    }

    /**
     * EL CONTENIDO CRUDO de un archivo guardado. NULL si no hay nada, o si el
     * archivo que la fila dice tener ya no está en el disco.
     *
     * Hace falta para IMPRIMIR. DomPDF corre del lado del servidor y no tiene
     * navegador: una `<img src="/storage/...">` la resolvería contra el disco
     * con las restricciones de `chroot` y en producción termina en un recuadro
     * vacío. La foto del carnet va embebida en base64, y para eso hay que leer
     * los bytes.
     *
     * NO SE LEE POR HTTP ni siquiera cuando el disco es s3: se pide por el
     * disco de Flysystem, igual que se borra. Ir por la URL obligaría al
     * servidor a salir a internet para dibujar un carnet —con su timeout y su
     * proxy— y fallaría en cualquier despliegue donde el bucket no sea público.
     *
     * Las filas viejas que guardaron la dirección completa no se pueden leer
     * por el mismo motivo por el que no se pueden borrar: desde una URL no hay
     * forma de reconstruir la clave del objeto. Ahí se devuelve null y el
     * documento sale sin foto, que es preferible a un error 500 que deje a
     * ventanilla sin poder imprimir nada.
     */
    public static function contenido(?string $valor): ?string
    {
        if (blank($valor) || Str::startsWith($valor, ['http://', 'https://'])) {
            return null;
        }

        $disco = Storage::disk(self::disco());

        return $disco->exists($valor) ? $disco->get($valor) : null;
    }

    /**
     * Borra un archivo guardado.
     *
     * Con la ruta —que es lo que hoy se guarda— se borra del disco activo, sea
     * local o s3. No hay nada especial que hacer: Flysystem resuelve el prefijo
     * del bucket solo, porque el disco lleva `'root' => env('AWS_ROOT')`.
     */
    public static function borrar(?string $valor): void
    {
        if (blank($valor)) {
            return;
        }

        /*
         * LO VIEJO GUARDADO COMO DIRECCIÓN COMPLETA NO SE PUEDE BORRAR.
         *
         * Desde una URL no se puede reconstruir la clave del objeto con
         * seguridad, y borrar la clave equivocada sería peor que no borrar. Se
         * anota en el log para poder limpiarlo a mano y se sigue: el archivo de
         * más no rompe nada, una excepción acá sí —este método se llama desde
         * los `catch` de operaciones que ya fallaron—.
         */
        if (Str::startsWith($valor, ['http://', 'https://'])) {
            Log::warning('Adjunto guardado como URL completa: no se puede borrar, queda ocupando lugar.', [
                'valor' => $valor,
            ]);

            return;
        }

        Storage::disk(self::disco())->delete($valor);
    }
}
