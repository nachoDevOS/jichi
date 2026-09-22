<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 *  EL BLOQUE DE VERIFICACIÓN DE UN DOCUMENTO IMPRESO
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

        /*
         * EL DOMINIO SALE DE `APP_URL`, Y HAY QUE FORZARLO.
         *
         * `route()` absoluta usa el host de LA PETICIÓN, no `app.url`: un
         * operador que entre al panel por `http://192.168.1.50:8000` imprimiría
         * carnets con el QR apuntando a esa IP, muerto para cualquier teléfono
         * fuera de la red. Por eso se arma la ruta RELATIVA y se le pega el
         * dominio configurado.
         *
         * OJO: lo que diga `APP_URL` queda IMPRESO en el papel.
         */
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
