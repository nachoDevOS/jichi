<?php

namespace Tests\Unit;

use App\Enums\EstadoAprovechamiento;
use App\Models\AprovechamientoPesq;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * De qué autorización se le puede comprar pescado a un pescador. Sin consultas:
 * el TestCase de Laravel hace falta solo para los casts de fecha del modelo.
 */
class AutorizacionVendibleTest extends TestCase
{
    /** @return array<string, array{0: EstadoAprovechamiento, 1: string, 2: ?string}> */
    public static function casos(): array
    {
        return [
            'aprobada y en fecha' => [EstadoAprovechamiento::Aprobado, '+10 days', null],
            'aprobada, vence hoy' => [EstadoAprovechamiento::Aprobado, 'today', null],
            'aprobada pero vencida' => [EstadoAprovechamiento::Aprobado, '-1 day', 'su autorización venció'],
            'pendiente de pago' => [EstadoAprovechamiento::Pendiente, '+10 days', 'su autorización todavía no se pagó'],
            'revocada en fecha' => [EstadoAprovechamiento::Revocado, '+10 days', 'su autorización fue revocada'],
        ];
    }

    #[DataProvider('casos')]
    public function test_motivo_sin_guia(EstadoAprovechamiento $estado, string $vence, ?string $esperado): void
    {
        $a = new AprovechamientoPesq;
        $a->estado = $estado;
        $a->fecha_vencimiento = date('Y-m-d', strtotime($vence));

        $this->assertSame($esperado, $a->motivoSinGuia());
    }

    public function test_normalizar_codigo_acepta_guiones_espacios_y_minusculas(): void
    {
        $this->assertSame('76ZNFVFCVCMKGY8Z', AprovechamientoPesq::normalizarCodigo(' 76zn-fvfc vcmk-gy8z '));
    }
}
