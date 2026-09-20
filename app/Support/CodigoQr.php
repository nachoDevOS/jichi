<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 *  EL CÓDIGO QR DE LOS DOCUMENTOS IMPRESOS
 */
class CodigoQr
{
    /**
     * Cuántos píxeles mide cada módulo (cada cuadradito) del código.
     */
    private const PIXELES_POR_MODULO = 8;

    /**
     * El margen blanco alrededor, en módulos.
     */
    private const MODULOS_DE_MARGEN = 4;

    /**
     * El PNG del QR como data URI, listo para el `src` de un `<img>`.
     */
    public static function dataUri(string $texto): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($texto));
    }

    /**
     * El PNG crudo.
     */
    public static function png(string $texto): string
    {
        /*
         * CORRECCIÓN DE ERRORES EN NIVEL «Q» —el 25% del código puede perderse
         * y seguir leyéndose—, y no el «L» que viene por defecto.
         */
        $qr = Encoder::encode($texto, ErrorCorrectionLevel::Q(), Encoder::DEFAULT_BYTE_MODE_ECODING);

        $matriz = $qr->getMatrix();
        $modulos = $matriz->getWidth();
        $lado = ($modulos + self::MODULOS_DE_MARGEN * 2) * self::PIXELES_POR_MODULO;

        $imagen = imagecreatetruecolor($lado, $lado);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $negro = imagecolorallocate($imagen, 0, 0, 0);

        imagefilledrectangle($imagen, 0, 0, $lado - 1, $lado - 1, $blanco);

        for ($y = 0; $y < $modulos; $y++) {
            for ($x = 0; $x < $modulos; $x++) {
                if ($matriz->get($x, $y) !== 1) {
                    continue;
                }

                $izquierda = ($x + self::MODULOS_DE_MARGEN) * self::PIXELES_POR_MODULO;
                $arriba = ($y + self::MODULOS_DE_MARGEN) * self::PIXELES_POR_MODULO;

                imagefilledrectangle(
                    $imagen,
                    $izquierda,
                    $arriba,
                    $izquierda + self::PIXELES_POR_MODULO - 1,
                    $arriba + self::PIXELES_POR_MODULO - 1,
                    $negro,
                );
            }
        }

        // imagepng() escribe en la salida estándar; el buffer es la forma de
        // quedarse con los bytes sin pasar por un archivo temporal.
        ob_start();
        imagepng($imagen);
        $png = (string) ob_get_clean();

        imagedestroy($imagen);

        return $png;
    }
}
