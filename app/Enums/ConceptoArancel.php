<?php

namespace App\Enums;

/**
 * Qué servicio de SIREB cobra cada trámite: una fila de `aranceles_sireb` por
 * caso, con SOLO el servicio. La tarifa la elige ventanilla al emitir, entre las
 * de ese servicio. Uno nuevo es un caso más acá y una fila en el seeder.
 */
enum ConceptoArancel: string
{
    case Autorizacion = 'autorizacion';
    case Carnet = 'carnet';
    case Faena = 'faena';
    case Guia = 'guia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Autorizacion => 'Autorización de pesca',
            self::Carnet => 'Carnet',
            self::Faena => 'Permiso de faena',
            self::Guia => 'Guía de transporte',
        };
    }

    /** Qué elige ventanilla entre las tarifas del servicio, para que no haya que adivinar. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Autorizacion => 'Sus tarifas son los tramos de la escala, con su monto. Los kilos los escribe ventanilla.',
            self::Carnet => 'Sus tarifas son los carnets de pescador y de comercializador.',
            self::Faena => 'Ventanilla elige la tarifa de la salida al emitir el permiso.',
            self::Guia => 'Sus tarifas son los productos del cuadro D, con su precio por kilo.',
        };
    }
}
