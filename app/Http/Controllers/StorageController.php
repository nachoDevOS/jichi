<?php

namespace App\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 *  EL ÚNICO PUNTO DEL SISTEMA QUE ESCRIBE ARCHIVOS EN DISCO
 * ============================================================================
 *
 * Ninguna otra clase llama a `Storage::put()`, `->store()` ni `->storeAs()`.
 * Todo archivo que entra al sistema —la foto del beneficiario, la fotocopia del
 * carnet, el certificado de la asociación, la boleta de cada depósito— pasa por
 * `file()`.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UN EMBUDO Y NO CADA CONTROLADOR SUBIENDO LO SUYO
 * ----------------------------------------------------------------------------
 *
 * Porque hay tres reglas que tienen que valer para TODOS los archivos, y una
 * regla repartida por cinco controladores es una regla que tarde o temprano
 * queda distinta en uno de ellos:
 *
 *   1. EL LÍMITE DE PESO. Máximo 3 MB, sin excepción (ver `verificarPeso()`).
 *   2. EL NOMBRE. Nunca el que traía el archivo del usuario.
 *   3. EN QUÉ DISCO SE ESCRIBE. Local o s3, según la configuración.
 *
 * Con el embudo, agregar un módulo nuevo con adjuntos hereda las tres sin que
 * nadie tenga que acordarse.
 *
 * ----------------------------------------------------------------------------
 *  EL NOMBRE DEL ARCHIVO SE INVENTA, NO SE CONSERVA
 * ----------------------------------------------------------------------------
 *
 * `Str::random(20).time()` y la extensión, y nada más. El nombre original del
 * usuario no se usa nunca, y eso NO es por estética:
 *
 *   - un nombre como `../../.env.jpg` o con caracteres raros puede escaparse de
 *     la carpeta prevista según cómo lo trate el sistema de archivos;
 *   - dos personas suben `carnet.jpg` el mismo día y el segundo pisa al primero;
 *   - el nombre original suele traer datos personales («ci-juan-perez.jpg») que
 *     después quedan a la vista en la URL del archivo.
 *
 * Con un nombre aleatorio los tres problemas desaparecen de una vez.
 *
 * ----------------------------------------------------------------------------
 *  NO SE USA env() ACÁ, Y ES UNA REGLA DEL PROYECTO
 * ----------------------------------------------------------------------------
 *
 * En producción se corre `php artisan config:cache`, y desde ese momento `env()`
 * devuelve NULL en todo archivo que no esté en `config/`. El error es silencioso:
 * el sistema creería que el disco no es s3 y escribiría los adjuntos en el
 * servidor local sin avisar a nadie, hasta que alguien note que las boletas
 * nuevas no aparecen en el bucket.
 *
 * Por eso todo sale de `config(...)`.
 */
class StorageController extends Controller
{
    /**
     * ========================================================================
     *  GUARDA UN ARCHIVO Y DEVUELVE SIEMPRE UNA RUTA, NUNCA UNA URL
     * ========================================================================
     *
     *   'tramites/September2026/aB3x...1789.pdf'
     *
     * Lo mismo con el disco local y con s3. La dirección para abrirlo se arma
     * AL LEER, con `App\Support\Archivos::url()`.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ NO SE GUARDA LA URL COMPLETA, QUE ES LO QUE HACÍA ANTES
     * ------------------------------------------------------------------------
     *
     * Guardar la dirección parece más cómodo —ya está lista para poner en un
     * enlace— y trae tres problemas, los tres reales:
     *
     *   1. NO SE PUEDE BORRAR. Desde una dirección completa no hay forma de
     *      volver a la clave del objeto sin conocer el prefijo del bucket. Cada
     *      adjunto reemplazado quedaba ocupando lugar en s3 para siempre.
     *
     *   2. SE CONGELA EL DOMINIO. La dirección queda escrita en la base el día
     *      de la carga. Si mañana cambia el bucket, el endpoint o el CDN, todos
     *      los enlaces viejos apuntan a donde ya no está el archivo, y hay que
     *      salir a reescribir filas.
     *
     *   3. CONVIVEN DOS FORMAS EN LA MISMA COLUMNA. Según cómo estuviera
     *      configurado el sistema el día de la carga, la columna guarda una ruta
     *      o una dirección — y todo el que la lea tiene que acordarse de
     *      distinguirlas.
     *
     * Con la ruta sola, los tres desaparecen: se borra con la clave que se tiene,
     * el dominio sale de la configuración actual, y hay una sola forma posible.
     *
     * ------------------------------------------------------------------------
     *  EL PREFIJO DEL BUCKET NO SE ESCRIBE ACÁ
     * ------------------------------------------------------------------------
     *
     * El disco s3 se configura con `'root' => env('AWS_ROOT')` en
     * config/filesystems.php, y Flysystem lo antepone solo en cada operación.
     * Agregarlo a mano además —como se hacía— lo duplicaba: `dev/dev/tramites/`.
     *
     * @param  string  $folder  Carpeta lógica: 'beneficiarios', 'tramites', 'pagos'.
     *
     * @throws ValidationException si el archivo supera el límite del sistema.
     */
    public function file(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        $this->verificarPeso($file);

        $ext = $file->getClientOriginalExtension();
        $newFileName = Str::random(20).time().'.'.$ext;

        // Se agrupa por mes ('September2026') para que ninguna carpeta junte
        // decenas de miles de archivos: listarla se vuelve lentísimo, y en s3
        // los prefijos muy poblados también se degradan.
        $directory = $folder.'/'.date('FY');
        $path = $directory.'/'.$newFileName;

        // Cuál es el disco activo lo decide App\Support\Archivos, que es el
        // mismo que después arma la URL y borra: si lo decidiera cada uno por su
        // cuenta, escribir y leer podrían terminar mirando discos distintos.
        $activeDisk = config('filesystems.default') === 's3' ? 's3' : $disk;

        Storage::disk($activeDisk)->makeDirectory($directory);

        /*
         * file_get_contents() carga el archivo entero en memoria antes de
         * escribirlo. Con un tope de 3 MB eso es inofensivo —y es justamente el
         * tope lo que lo vuelve inofensivo—: sin el límite de arriba, veinte
         * cargas simultáneas de 500 MB voltearían el servidor de PHP antes de
         * llegar a tocar el disco.
         */
        Storage::disk($activeDisk)->put($path, file_get_contents($file), 'public');

        return $path;
    }

    /**
     * ========================================================================
     *  EL LÍMITE DE 3 MB — la última línea de defensa
     * ========================================================================
     *
     * Los formularios YA validan el peso, uno por uno, con la regla `max:` y un
     * mensaje que le dice al operador cuánto pesa el archivo que eligió. Esta
     * comprobación no reemplaza a aquella: normalmente no se dispara nunca.
     *
     * Está igual, y por un motivo concreto: la regla del formulario es una
     * CONVENCIÓN —hay que acordarse de escribirla en cada Form Request nuevo—
     * mientras que esto es ESTRUCTURAL. El día que alguien agregue un módulo con
     * adjuntos y se olvide el `max:`, el archivo igual no entra: PHP acepta
     * hasta `upload_max_filesize`, que en este servidor son 2 GB.
     *
     * Los kilobytes son de 1024 bytes, igual que los cuenta la regla `max` de
     * Laravel y que los mide el navegador con `archivo.size`. Así un archivo que
     * pasa el control del navegador pasa también acá, sin casos raros justo en
     * el límite.
     *
     * Se lanza ValidationException y no una excepción cualquiera para que el
     * error llegue como un mensaje bajo el formulario —y como un 422 si algún
     * día esto se consume desde una API— en vez de como un error 500 que no le
     * explica nada a nadie.
     *
     * @throws ValidationException
     */
    private function verificarPeso(UploadedFile $file): void
    {
        $maxKb = (int) config('jichi.archivos.max_kb');
        $maxBytes = $maxKb * 1024;

        if ($file->getSize() <= $maxBytes) {
            return;
        }

        $maxMb = round($maxKb / 1024, 1);
        $pesaMb = round($file->getSize() / 1024 / 1024, 1);

        throw ValidationException::withMessages([
            'archivo' => "El archivo pesa {$pesaMb} MB y el máximo permitido es {$maxMb} MB.",
        ]);
    }
}
