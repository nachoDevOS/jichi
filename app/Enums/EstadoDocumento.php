<?php

namespace App\Enums;

enum EstadoDocumento: string
{
    case Vigente = 'vigente';
    case Vencido = 'vencido';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Vencido => 'Vencido',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vigente => 'emerald',
            self::Vencido => 'amber',
            self::Anulado => 'rose',
        };
    }

    /**
     * Texto mostrado en la pantalla pública de verificación por QR.
     */
    public function mensajePublico(): string
    {
        return match ($this) {
            self::Vigente => 'Documento válido y vigente.',
            self::Vencido => 'Documento auténtico pero VENCIDO. No habilita la actividad.',
            self::Anulado => 'Documento ANULADO por el Gobierno Autónomo Departamental del Beni.',
        };
    }

    public function esValido(): bool
    {
        return $this === self::Vigente;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado) => [
            'value' => $estado->value,
            'label' => $estado->etiqueta(),
            'color' => $estado->color(),
        ], self::cases());
    }
}
