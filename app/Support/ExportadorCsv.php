<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Un CSV que Excel en español abre con doble clic: UTF-8 con BOM (tildes y ñ), `;` entre columnas
 * y coma decimal. Sin paquete de Excel: no hay ninguno instalado. Ver docs/modulos/REPORTES.md.
 */
class ExportadorCsv
{
    /**
     * @param  list<string>  $encabezados
     * @param  iterable<array<int, mixed>>  $filas
     */
    public static function descargar(string $nombre, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ';', '"', '');

            foreach ($filas as $fila) {
                fputcsv($salida, array_map(self::celda(...), $fila), ';', '"', '');
            }

            fclose($salida);
        }, $nombre.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Con coma decimal y sin separador de miles: Excel en español lo lee como número. */
    public static function numero(float|int|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '');
    }

    /** Un texto que empieza con = + - @ Excel lo ejecuta como fórmula: se le antepone un apóstrofo. */
    private static function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        if ($texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! preg_match('/^-?\d+(,\d+)?$/', $texto)) {
            return "'".$texto;
        }

        return $texto;
    }
}
