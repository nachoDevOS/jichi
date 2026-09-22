<?php

namespace App\Support;

/**
 *  UN RÓTULO GIRADO 90°, DIBUJADO COMO IMAGEN
 *
 * DomPDF no tiene `transform` ni `writing-mode`: lo escrito con ellas sale
 * horizontal y sin avisar. Los rótulos rotados del talonario —las diez casillas
 * del cuadro D de la guía— se dibujan acá con GD y se embeben como PNG, igual
 * que el QR.
 *
 * Antes se apilaba una letra por renglón. Se leía, pero no es lo que dice el
 * papel: ahí «Eviscerado» va girado y entero, no en columna.
 */
class TextoVertical
{
    /** La tipografía que DomPDF embebe. Usar otra dejaría el rótulo distinto al resto. */
    private const FUENTE = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';

    /**
     * Cuántas veces más grande se dibuja antes de achicarlo en el PDF.
     *
     * A 1× el texto queda de 6 pt = 6 px y sale dentado. Con 6× el PNG va a
     * ~430 dpi y el visor lo baja a tamaño sin que se noten los escalones.
     */
    private const SUPERMUESTREO = 6;

    /** Aire alrededor del texto, en píxeles del lienzo grande. */
    private const MARGEN = 2;

    /**
     * El rótulo girado, como data URI listo para el `src` de un `<img>`.
     *
     * @param  float  $cuerpo  El cuerpo en PUNTOS, como si fuera CSS.
     */
    public static function dataUri(string $texto, float $cuerpo = 6.0, string $color = '#2e7d32'): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($texto, $cuerpo, $color));
    }

    /**
     * Cuánto mide el rótulo girado, en PUNTOS: [ancho, alto].
     *
     * Hace falta para declararlo en el `style` del `<img>`. ⚠️ El atributo
     * `width` de un `<img>` se mide en PÍXELES, así que ahí saldría un 25% más
     * chico. Ver CLAUDE.md.
     *
     * @return array{0: float, 1: float}
     */
    public static function medida(string $texto, float $cuerpo = 6.0): array
    {
        [$ancho, $alto] = self::cajaEnPixeles($texto, $cuerpo * self::SUPERMUESTREO);

        // Girado, el ancho y el alto se intercambian.
        return [$alto / self::SUPERMUESTREO, $ancho / self::SUPERMUESTREO];
    }

    /** El PNG crudo, con el texto leyéndose de abajo hacia arriba. */
    public static function png(string $texto, float $cuerpo = 6.0, string $color = '#2e7d32'): string
    {
        $fuente = base_path(self::FUENTE);
        $cuerpoPx = $cuerpo * self::SUPERMUESTREO;

        [$ancho, $alto, $izquierda, $arriba] = self::cajaEnPixeles($texto, $cuerpoPx);

        /*
         * SE DIBUJA DERECHO Y DESPUÉS SE GIRA LA IMAGEN ENTERA, en vez de
         * pasarle el ángulo a `imagettftext()`: con el ángulo, el origen que
         * espera GD es la base de la primera letra YA ROTADA, y calcularlo a
         * mano dejaba los rótulos fuera del lienzo —salían en blanco—.
         */
        $lienzo = imagecreatetruecolor($ancho, $alto);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);

        /*
         * FONDO TRANSPARENTE y no blanco: la celda del cuadro D tiene el suyo,
         * y un rectángulo blanco encima taparía el borde de la tabla justo
         * donde las dos se tocan.
         */
        $transparente = imagecolorallocatealpha($lienzo, 255, 255, 255, 127);
        imagefilledrectangle($lienzo, 0, 0, $ancho - 1, $alto - 1, $transparente);
        imagealphablending($lienzo, true);

        [$r, $g, $b] = self::rgb($color);
        $tinta = imagecolorallocate($lienzo, $r, $g, $b);

        // El origen de `imagettftext` es la BASE de la primera letra, así que
        // se corrige con el corrimiento que devolvió la caja: sin eso las
        // letras con cola —la «g» de «Congelado»— salen cortadas.
        imagettftext($lienzo, $cuerpoPx, 0, -$izquierda, -$arriba, $tinta, $fuente, $texto);

        // 90° antihorario: el texto pasa a leerse de ABAJO HACIA ARRIBA, que
        // es como está impreso el talonario.
        $girado = imagerotate($lienzo, 90, $transparente);
        imagealphablending($girado, false);
        imagesavealpha($girado, true);
        imagedestroy($lienzo);

        ob_start();
        imagepng($girado);
        imagedestroy($girado);

        return (string) ob_get_clean();
    }

    /**
     * La caja del texto SIN girar: [ancho, alto, corrimiento x, corrimiento y].
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private static function cajaEnPixeles(string $texto, float $cuerpoPx): array
    {
        $caja = imagettfbbox($cuerpoPx, 0, base_path(self::FUENTE), $texto);

        $xs = [$caja[0], $caja[2], $caja[4], $caja[6]];
        $ys = [$caja[1], $caja[3], $caja[5], $caja[7]];

        $ancho = (int) (max($xs) - min($xs)) + self::MARGEN * 2;
        $alto = (int) (max($ys) - min($ys)) + self::MARGEN * 2;

        return [$ancho, $alto, (int) min($xs) - self::MARGEN, (int) min($ys) - self::MARGEN];
    }

    /**
     * «#2e7d32» → [46, 125, 50].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
