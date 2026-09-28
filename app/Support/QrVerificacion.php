<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 *  El bloque de verificación de un documento impreso
 *
 *  Lo usan los cuatro PDF que se entregan. Vivía privado adentro de
 *  CarnetImpresionController; se sacó acá el 22/09/2026 cuando el recibo, la
 *  autorización y el permiso de faena pasaron a llevar QR.
 */
class QrVerificacion
{
    /**
     * El QR, el código legible y la dirección que va adentro.
     *
     * Null si el documento todavía no tiene código: ahí no hay nada que
     * verificar y el papel sale sin el bloque.
     *
     * @return array{qr: string, codigo: string, url: string}|null
     */
    public static function de(Model $documento): ?array
    {
        $codigo = $documento->codigo?->codigo;

        if ($codigo === null) {
            return null;
        }

        // Ruta relativa + APP_URL: `route()` absoluta usaría el host de la petición
        // y el QR saldría impreso con la IP del operador. Ver «Trampas» en CLAUDE.md.
        $url = rtrim((string) config('app.url'), '/').route('verificar.show', $codigo, false);

        return [
            'qr' => self::png($url),
            'codigo' => (string) $documento->codigo_legible,
            'url' => $url,
        ];
    }

    /**
     * El PNG como data URI, o vacío si no se pudo dibujar.
     */
    private static function png(string $url): string
    {
        try {
            return CodigoQr::dataUri($url);
        } catch (Throwable) {
            // Sin QR el documento sale igual y el código escrito sigue
            // sirviendo para verificarlo. Es preferible a dejar a ventanilla
            // sin imprimir.
            return '';
        }
    }
}
