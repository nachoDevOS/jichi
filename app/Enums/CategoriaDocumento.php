<?php

namespace App\Enums;

enum CategoriaDocumento: string
{
    case Certificacion = 'certificacion';
    case Credencial = 'credencial';
    case Permiso = 'permiso';
    case Licencia = 'licencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Certificacion => 'Certificación',
            self::Credencial => 'Credencial / Carnet',
            self::Permiso => 'Permiso',
            self::Licencia => 'Licencia',
        };
    }

    /**
     * Vista Blade usada por DomPDF para renderizar el documento.
     */
    public function plantilla(): string
    {
        return match ($this) {
            self::Certificacion => 'documentos.certificacion',
            self::Credencial => 'documentos.credencial',
            self::Permiso => 'documentos.permiso',
            self::Licencia => 'documentos.licencia',
        };
    }

    /**
     * Tamaño y orientación de página que DomPDF debe usar.
     *
     * @return array{papel: string|array<int, float>, orientacion: string}
     */
    public function formatoPagina(): array
    {
        return match ($this) {
            // Formato tarjeta CR80 (85.6 x 54 mm) expresado en puntos.
            self::Credencial => ['papel' => [0.0, 0.0, 242.65, 153.07], 'orientacion' => 'landscape'],
            default => ['papel' => 'letter', 'orientacion' => 'portrait'],
        };
    }

    public function requiereFoto(): bool
    {
        return $this === self::Credencial;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->etiqueta(),
        ], self::cases());
    }
}
