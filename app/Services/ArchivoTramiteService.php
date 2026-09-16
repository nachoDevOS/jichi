<?php

namespace App\Services;

use App\Http\Controllers\StorageController;
use App\Support\Archivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================================
 *  SUBIR ARCHIVOS DENTRO DE UNA OPERACIÓN TRANSACCIONAL
 * ============================================================================
 *
 * El problema que resuelve esta clase es uno solo, y conviene entenderlo antes
 * de tocarla:
 *
 *     UNA TRANSACCIÓN DE BASE DE DATOS NO DESHACE ESCRITURAS EN DISCO.
 *
 * Si se sube el archivo DENTRO de la transacción y después falla cualquier
 * cosa, el ROLLBACK borra las filas pero el archivo queda escrito para siempre:
 * un adjunto que no pertenece a ningún trámite, ocupando lugar, sin forma de
 * saber después si sobra o no. Y si el disco es s3, ni siquiera se puede borrar
 * fácil (ver App\Support\Archivos::borrar).
 *
 * La salida es invertir el orden:
 *
 *     1. subir todos los archivos            <- fuera de la transacción
 *     2. abrir la transacción y escribir las filas con las rutas
 *     3. si algo falla: rollback + borrar los archivos del paso 1
 *
 * Así el único desperdicio posible es un archivo huérfano en el caso raro de
 * que el paso 3 también falle, y de eso sí se puede volver. Al revés no.
 *
 * El paso 1 va primero también por otra razón práctica: subir a s3 puede tardar
 * segundos, y tener una transacción abierta mientras tanto mantiene filas
 * bloqueadas en la base sin ninguna necesidad.
 */
class ArchivoTramiteService
{
    public function __construct(private readonly StorageController $storage) {}

    /**
     * Guarda un archivo y devuelve lo que hay que escribir en la columna.
     *
     * Puede ser una ruta ('tramites/September2026/aB3x...jpg') o una dirección
     * completa, según el disco configurado. Quien decide es StorageController,
     * que es el único punto del sistema que escribe en disco.
     */
    public function guardar(UploadedFile $archivo, string $carpeta): string
    {
        return $this->storage->file($archivo, $carpeta);
    }

    /**
     * Sube varios archivos de una vez. Si uno falla, borra los que ya subieron.
     *
     * Sin ese barrido, un fallo en el segundo archivo dejaría el primero tirado
     * en el disco antes incluso de haber tocado la base de datos.
     *
     * @param  array<string, UploadedFile|null>  $archivos  columna => archivo
     * @return array<string, string|null> columna => ruta guardada
     */
    public function guardarVarios(array $archivos, string $carpeta): array
    {
        $rutas = [];

        try {
            foreach ($archivos as $columna => $archivo) {
                $rutas[$columna] = $archivo instanceof UploadedFile
                    ? $this->guardar($archivo, $carpeta)
                    : null;
            }
        } catch (\Throwable $e) {
            $this->descartar(array_values(array_filter($rutas)));

            throw $e;
        }

        return $rutas;
    }

    /**
     * Borra archivos que quedaron sin dueño porque la transacción se deshizo.
     *
     * NO PROPAGA ERRORES, a propósito. Este método se llama desde el catch de
     * una operación que ya falló: si acá saltara otra excepción, taparía la
     * original y el operador vería «no se pudo borrar un archivo» en vez del
     * motivo real por el que su trámite no se registró. El archivo huérfano es
     * un problema menor y queda anotado en el log para limpiarlo después.
     *
     * @param  array<int, string|null>  $rutas
     */
    public function descartar(array $rutas): void
    {
        foreach (array_filter($rutas) as $ruta) {
            try {
                Archivos::borrar($ruta);
            } catch (\Throwable $e) {
                Log::warning('No se pudo borrar un archivo huérfano tras un rollback.', [
                    'ruta' => $ruta,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
