<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * ============================================================================
 *  EL CÓDIGO QR DE LOS DOCUMENTOS IMPRESOS
 * ============================================================================
 *
 * Devuelve un PNG en base64 listo para meter en un `<img>` de una plantilla que
 * va a dibujar DomPDF.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NO SE USA simple-qrcode, QUE ESTÁ INSTALADO
 * ----------------------------------------------------------------------------
 *
 * Porque su salida PNG necesita la extensión **imagick**, y este servidor no la
 * tiene (`php -m` lista gd, no imagick). Con eso, `QrCode::format('png')` lanza
 * «Extension 'Imagick' is required» y el carnet no sale.
 *
 * La otra salida que ofrece es SVG. DomPDF trae php-svg-lib y lo dibujaría,
 * pero un QR es justamente el elemento donde no conviene depender de un
 * renderizador aproximado: si los módulos salen medio píxel corridos la cámara
 * deja de leerlo, y eso no se descubre hasta que alguien intenta verificar un
 * carnet en la calle.
 *
 * Acá se usa directamente **BaconQrCode** —la librería que simple-qrcode trae
 * adentro— para obtener la matriz de módulos, y se pinta con **gd**, cuadrito
 * por cuadrito. Son treinta líneas, no dependen de ninguna extensión que no
 * esté, y el resultado es un PNG de píxeles exactos: cada módulo mide un número
 * entero de píxeles y no hay interpolación posible.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NO SE GUARDA EN DISCO
 * ----------------------------------------------------------------------------
 *
 * Mismo criterio que el PDF del recibo: la imagen se deduce de la firma del
 * carnet, así que el QR de mañana sale idéntico al de hoy. Guardarlo sería un
 * archivo más que limpiar —y con el disco en s3, uno que no se puede borrar—.
 * Por eso esto NO pasa por StorageController: no escribe nada.
 */
class CodigoQr
{
    /**
     * Cuántos píxeles mide cada módulo (cada cuadradito) del código.
     *
     * Con 8 px por módulo un QR de versión 3 sale de unos 300 px de lado: más
     * que suficiente para dibujarlo a 60 pt en el carnet sin que la impresora
     * tenga que inventar nada, y liviano (unos 2 KB en PNG).
     */
    private const PIXELES_POR_MODULO = 8;

    /**
     * El margen blanco alrededor, en módulos.
     *
     * NO ES DECORACIÓN: la norma del QR lo llama «zona tranquila» y pide cuatro
     * módulos. Sin ella, el fondo verde del carnet toca los cuadritos del borde
     * y muchos lectores no encuentran dónde empieza el código.
     */
    private const MODULOS_DE_MARGEN = 4;

    /**
     * El PNG del QR como data URI, listo para el `src` de un `<img>`.
     *
     * Va embebido y no como ruta por lo mismo que las imágenes del recibo:
     * DomPDF resolvería una ruta contra el disco con las restricciones de
     * `chroot` y en producción terminaría en un recuadro vacío.
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
         *
         * El carnet es un plástico que vive en el bolsillo de alguien que
         * trabaja en el río: se raya, se moja y se despinta. Subir el nivel
         * agranda el código unos pocos módulos y compra que siga funcionando
         * con el QR maltratado, que es la única condición en la que alguien lo
         * va a escanear.
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
