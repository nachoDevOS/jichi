<?php

namespace App\Enums;

/**
 * Qué servicio de SIREB cobra cada trámite: una fila de `aranceles_sireb` por
 * caso, con SOLO el servicio. La pone el seeder; un trámite nuevo es un caso
 * más acá. Los aranceles que registra la unidad no llevan concepto.
 */
enum ConceptoArancel: string
{
    case Autorizacion = 'autorizacion';
    case Carnet = 'carnet';
    case Faena = 'faena';
    case Guia = 'guia';

    /** La clave de la única tarifa definida de la faena en `arancel_tarifas`. */
    public const CLAVE_FAENA = 'faena';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Autorizacion => 'Autorización de pesca',
            self::Carnet => 'Carnet',
            self::Faena => 'Permiso de faena',
            self::Guia => 'Guía de transporte',
        };
    }

    /**
     * Las claves fijas del trámite en `arancel_tarifas`, con su etiqueta inicial.
     * Vacío: ventanilla elige entre todas las tarifas del servicio.
     *
     * @return array<string, string>
     */
    public function claves(): array
    {
        return match ($this) {
            self::Carnet => collect(TipoActor::cases())->mapWithKeys(fn (TipoActor $a): array => [$a->value => $a->nombreCarnet()])->all(),
            self::Faena => [self::CLAVE_FAENA => 'Permiso de faena'],
            default => [],
        };
    }

    /** Qué elige ventanilla entre las tarifas del servicio, para que no haya que adivinar. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Autorizacion => 'Sus tarifas son las escalas, con su monto y sus kilos (rango hasta).',
            self::Carnet => 'Una tarifa definida por actividad: pescador y comercializador.',
            self::Faena => 'Una tarifa definida: la del permiso. Ventanilla no la elige.',
            self::Guia => 'Sus tarifas son los productos del cuadro D, con su precio por kilo.',
        };
    }
}
