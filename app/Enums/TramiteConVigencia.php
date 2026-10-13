<?php

namespace App\Enums;

/**
 * Los trámites cuya vigencia configura la unidad. Los valores iniciales son los
 * de la resolución; se usan mientras nadie guarde otra cosa.
 */
enum TramiteConVigencia: string
{
    case Autorizacion = 'autorizacion';
    case CarnetPescador = 'carnet_pescador';
    case CarnetComercializador = 'carnet_comercializador';
    case Faena = 'faena';
    case Guia = 'guia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Autorizacion => 'Autorización de pesca',
            self::CarnetPescador => 'Carnet de pescador',
            self::CarnetComercializador => 'Carnet de comercializador',
            self::Faena => 'Permiso de faena',
            self::Guia => 'Guía de transporte',
        };
    }

    /** La vigencia del carnet de cada actividad. */
    public static function carnetDe(TipoActor $actor): self
    {
        return match ($actor) {
            TipoActor::Pescador => self::CarnetPescador,
            TipoActor::Comercializador => self::CarnetComercializador,
        };
    }

    public function modoInicial(): ModoVigencia
    {
        return match ($this) {
            self::Autorizacion, self::CarnetPescador, self::CarnetComercializador => ModoVigencia::FinDeAnio,
            self::Faena, self::Guia => ModoVigencia::Dias,
        };
    }

    public function diasIniciales(): int
    {
        return match ($this) {
            self::Autorizacion, self::CarnetPescador, self::CarnetComercializador => 365,
            self::Faena => 30,
            self::Guia => 5,
        };
    }

    /** Las claves en `configuraciones`. */
    public function claveModo(): string
    {
        return "vigencia.{$this->value}.modo";
    }

    public function claveDias(): string
    {
        return "vigencia.{$this->value}.dias";
    }
}
