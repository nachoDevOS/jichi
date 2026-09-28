<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 *  Dónde está un archivo guardado, y cómo se abre
 */
class Archivos
{
    /**
     * El disco donde el sistema guarda y busca los adjuntos.
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
         * Filas viejas: las que se cargaron cuando el sistema guardaba la
         * dirección completa en vez de la ruta.
         */
        if (Str::startsWith($valor, ['http://', 'https://'])) {
            return $valor;
        }

        return Storage::disk(self::disco())->url($valor);
    }

    /**
     * El contenido crudo de un archivo guardado. NULL si no hay nada, o si el
     * archivo que la fila dice tener ya no está en el disco.
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
     */
    public static function borrar(?string $valor): void
    {
        if (blank($valor)) {
            return;
        }

        /*
         * Lo viejo guardado como dirección completa no se puede borrar.
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
