<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ============================================================================
 *  CÓMO SE ABRE UN ARCHIVO GUARDADO
 * ============================================================================
 *
 * `StorageController::file()` devuelve dos cosas distintas según el disco:
 *
 *   FILESYSTEM_DISK=public → 'tramites/TRA-PESCA-2026-0038/aB3x...1789.jpg'
 *   FILESYSTEM_DISK=s3     → 'https://gadbeni.sfo3.digitaloceanspaces.com/...'
 *
 * O sea que la columna de la base guarda a veces una ruta y a veces una
 * dirección completa, según cómo estuviera configurado el sistema el día que se
 * subió el archivo. Y como el mismo expediente puede tener papeles cargados
 * antes y después de un cambio de disco, las dos formas conviven en la MISMA
 * tabla.
 *
 * Pasar cualquiera de las dos por `Storage::url()` no funciona: con la ruta da
 * bien, pero con la dirección completa la pega detrás del dominio local y sale
 * algo como `http://jichi.test/storage/https://gadbeni.sfo3...`, que no abre
 * nada.
 *
 * Por eso esta función mira qué recibió antes de decidir. Es una línea, y evita
 * que la ficha del trámite muestre enlaces rotos.
 */
class Archivos
{
    /**
     * La dirección para abrir un archivo. NULL si no hay nada guardado.
     */
    public static function url(?string $valor): ?string
    {
        if (blank($valor)) {
            return null;
        }

        // Ya es una dirección completa: se devuelve tal cual.
        if (Str::startsWith($valor, ['http://', 'https://'])) {
            return $valor;
        }

        return Storage::disk('public')->url($valor);
    }

    /**
     * Borra un archivo guardado, si se puede.
     *
     * ------------------------------------------------------------------------
     *  LO QUE ESTÁ EN LA NUBE NO SE BORRA DESDE ACÁ
     * ------------------------------------------------------------------------
     *
     * Cuando lo guardado es una ruta, se borra del disco y listo. Cuando es una
     * dirección completa —lo que devuelve StorageController con el disco en
     * s3— no hay forma de volver de esa dirección a la clave del objeto sin
     * conocer el prefijo del bucket, así que el archivo queda donde está.
     *
     * No es un olvido: es la consecuencia de guardar direcciones en vez de
     * rutas. El efecto práctico es que reemplazar un comprobante deja el
     * anterior ocupando lugar en el bucket. Nada se rompe, pero conviene saberlo
     * — se arregla el día que StorageController devuelva la ruta también en s3.
     */
    public static function borrar(?string $valor): void
    {
        if (blank($valor) || Str::startsWith($valor, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($valor);
    }
}
