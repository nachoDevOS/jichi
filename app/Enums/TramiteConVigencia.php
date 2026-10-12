<?php

namespace App\Enums;

/**
 * Los trámites cuya vigencia configura la unidad. Los valores iniciales son los
 * de la resolución; se usan mientras nadie guarde otra cosa.
 */
enum TramiteConVigencia: string
{
    case Autorizacion = 'autorizacion';
    case Faena = 'faena';
    case Guia = 'guia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Autorizacion => 'Autorización de pesca',
            self::Faena => 'Permiso de faena',
            self::Guia => 'Guía de transporte',
        };
    }

    public function modoInicial(): ModoVigencia
    {
        return $this === self::Autorizacion ? ModoVigencia::FinDeAnio : ModoVigencia::Dias;
    }

    public function diasIniciales(): int
    {
        return match ($this) {
            self::Autorizacion => 365,
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
