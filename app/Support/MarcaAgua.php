<?php

namespace App\Support;

/**
 * La marca «NO VÁLIDO» de la vista previa del portal, como PNG transparente.
 *
 * Imagen y no CSS: DomPDF no rota texto y su `opacity` no es confiable (ver
 * «Trampas» en CLAUDE.md). La transparencia va horneada en el canal alfa.
 */
class MarcaAgua
{
    private const FUENTE = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    /** Lado del lienzo cuadrado, en píxeles: se estira a la hoja con CSS. */
    private const LADO = 1200;

    public static function noValido(): string
    {
        $lienzo = imagecreatetruecolor(self::LADO, self::LADO);
        imagesavealpha($lienzo, true);
        imagealphablending($lienzo, false);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        imagealphablending($lienzo, true);

        // Rojo a ~40% de opacidad (alfa de GD: 0 opaco, 127 transparente).
        $tinta = imagecolorallocatealpha($lienzo, 200, 30, 40, 76);
        $fuente = base_path(self::FUENTE);
        $texto = 'NO VÁLIDO';
        $cuerpo = 150;
        $angulo = 35;

        // Centrado sobre la diagonal: la caja de imagettfbbox ya viene rotada.
        $caja = imagettfbbox($cuerpo, $angulo, $fuente, $texto);
        $ancho = max($caja[2], $caja[4]) - min($caja[0], $caja[6]);
        $alto = max($caja[1], $caja[3]) - min($caja[5], $caja[7]);
        $x = (int) ((self::LADO - $ancho) / 2 - min($caja[0], $caja[6]));
        $y = (int) ((self::LADO - $alto) / 2 - min($caja[5], $caja[7]));

        imagettftext($lienzo, $cuerpo, $angulo, $x, $y, $tinta, $fuente, $texto);

        ob_start();
        imagepng($lienzo);
        $png = (string) ob_get_clean();
        imagedestroy($lienzo);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
