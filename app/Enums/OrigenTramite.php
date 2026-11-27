<?php

namespace App\Enums;

/**
 * Desde dónde se REGISTRÓ un trámite (26/11/2026): columna `origen_tramite` de las
 * cuatro tablas. Lo decide el controlador de cada entrada, nunca el formulario.
 * Ver docs/MER.md § Origen del trámite.
 */
enum OrigenTramite: string
{
    case Ventanilla = 'ventanilla';
    case PortalWeb = 'portal_web';
    case App = 'app';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ventanilla => 'Ventanilla SEDAG',
            self::PortalWeb => 'Portal web',
            self::App => 'App BeniDigital',
        };
    }

    /** Lo pidió el propio beneficiario (portal web o app), no un funcionario. */
    public function esEnLinea(): bool
    {
        return $this !== self::Ventanilla;
    }

    /** @return list<array{valor: string, etiqueta: string}> Para los filtros. */
    public static function opciones(): array
    {
        return array_map(fn (self $o): array => ['valor' => $o->value, 'etiqueta' => $o->etiqueta()], self::cases());
    }
}
