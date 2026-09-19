<?php

namespace App\Enums;

/**
 * Qué habilita una credencial: PESCAR o COMERCIALIZAR.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTÁ EN EL CARNET Y NO EN EL BENEFICIARIO
 * ----------------------------------------------------------------------------
 *
 * `beneficiarios` es una tabla UNIFICADA de personas y no tiene columna de rol,
 * a propósito: la misma persona pesca y además comercializa, y guardando el rol
 * en la ficha habría que duplicarla para representar eso. El rol es del
 * DOCUMENTO, no de la persona: quien hace las dos cosas saca dos carnets.
 *
 * ----------------------------------------------------------------------------
 *  DE ACÁ CUELGA QUÉ PUEDE EMITIR CADA CREDENCIAL
 * ----------------------------------------------------------------------------
 *
 *   pescador        ──< permisos_faena   (una por salida de extracción)
 *   comercializador ──< guias_movimiento (una por traslado de producto)
 *
 * Y también si el carnet lleva colgada una BOLSA MADRE: el cupo en kilos se
 * autoriza por volumen extraído, así que solo el pescador tiene
 * `aprovechamiento_id`. Por eso esa columna es nullable.
 */
enum TipoActor: string
{
    case Pescador = 'pescador';
    case Comercializador = 'comercializador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pescador => 'Pescador',
            self::Comercializador => 'Comercializador',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pescador => 'sky',
            self::Comercializador => 'violet',
        };
    }

    /**
     * ¿Esta credencial lleva colgada una bolsa madre de aprovechamiento?
     *
     * Se pregunta acá y NUNCA con un match sobre el nombre del tipo de carnet:
     * `tipos_carnet` es un catálogo que edita la unidad desde el panel y el
     * mismo documento figura como «Carnet de Pescador» o como «Pescador
     * Artesanal» según quién lo cargó.
     */
    public function requiereAprovechamiento(): bool
    {
        return $this === self::Pescador;
    }

    /** ¿Puede emitir permisos de faena? */
    public function emiteFaenas(): bool
    {
        return $this === self::Pescador;
    }

    /** ¿Puede emitir guías de movimiento? */
    public function emiteGuias(): bool
    {
        return $this === self::Comercializador;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
            'color' => $e->color(),
        ], self::cases());
    }
}
