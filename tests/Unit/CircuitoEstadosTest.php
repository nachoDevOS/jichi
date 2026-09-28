<?php

namespace Tests\Unit;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La tabla del circuito de REGLAS-NEGOCIO, estado por estado, para la
 * autorización, el carnet y la faena. Agregar un estado o mover un permiso
 * obliga a decidir acá de qué lado cae. Sin base de datos.
 */
class CircuitoEstadosTest extends TestCase
{
    private const NADA = [
        'habilita' => false, 'permiteEdicion' => false, 'permiteEliminacion' => false,
        'permitePagos' => false, 'permiteEnvio' => false, 'permiteRevision' => false,
        'estaAbierto' => false,
    ];

    /** El borrador: se corrige, se cobra, se presenta o se elimina. */
    private const BORRADOR = [
        'permiteEdicion' => true, 'permiteEliminacion' => true, 'permitePagos' => true,
        'permiteEnvio' => true, 'estaAbierto' => true,
    ];

    /** @return array<string, array{\UnitEnum, array<string, bool>}> */
    public static function autorizacion(): array
    {
        return [
            'autorización: pendiente' => [EstadoAprovechamiento::Pendiente, [...self::NADA, ...self::BORRADOR, 'permiteRevocacion' => false]],
            'autorización: en revisión' => [EstadoAprovechamiento::EnRevision, [...self::NADA, 'permiteRevision' => true, 'estaAbierto' => true, 'permiteRevocacion' => false]],
            'autorización: aprobado' => [EstadoAprovechamiento::Aprobado, [...self::NADA, 'habilita' => true, 'permiteRevocacion' => true]],
            'autorización: agotado' => [EstadoAprovechamiento::Agotado, [...self::NADA, 'permiteRevocacion' => true]],
            'autorización: vencido' => [EstadoAprovechamiento::Vencido, [...self::NADA, 'permiteRevocacion' => false]],
            'autorización: revocado' => [EstadoAprovechamiento::Revocado, [...self::NADA, 'permiteRevocacion' => false]],
        ];
    }

    /** @return array<string, array{\UnitEnum, array<string, bool>}> */
    public static function carnet(): array
    {
        return [
            'carnet: pendiente' => [EstadoCarnet::Pendiente, [...self::NADA, ...self::BORRADOR, 'permiteRevocacion' => false]],
            'carnet: en revisión' => [EstadoCarnet::EnRevision, [...self::NADA, 'permiteRevision' => true, 'estaAbierto' => true, 'permiteRevocacion' => false]],
            'carnet: aprobado' => [EstadoCarnet::Aprobado, [...self::NADA, 'habilita' => true, 'permiteRevocacion' => true]],
            'carnet: revocado' => [EstadoCarnet::Revocado, [...self::NADA, 'permiteRevocacion' => false]],
            'carnet: vencido' => [EstadoCarnet::Vencido, [...self::NADA, 'permiteRevocacion' => false]],
        ];
    }

    /** @return array<string, array{\UnitEnum, array<string, bool>}> */
    public static function faena(): array
    {
        return [
            'faena: pendiente' => [EstadoFaena::Pendiente, [...self::NADA, ...self::BORRADOR, 'reservaCupo' => true, 'consumeCupo' => false]],
            'faena: en revisión' => [EstadoFaena::EnRevision, [...self::NADA, 'permiteRevision' => true, 'estaAbierto' => true, 'reservaCupo' => true, 'consumeCupo' => false]],
            'faena: aprobado' => [EstadoFaena::Aprobado, [...self::NADA, 'habilita' => true, 'reservaCupo' => false, 'consumeCupo' => true]],
            'faena: completado' => [EstadoFaena::Completado, [...self::NADA, 'reservaCupo' => false, 'consumeCupo' => true]],
            'faena: vencido' => [EstadoFaena::Vencido, [...self::NADA, 'reservaCupo' => false, 'consumeCupo' => false]],
            'faena: revocado' => [EstadoFaena::Revocado, [...self::NADA, 'reservaCupo' => false, 'consumeCupo' => false]],
        ];
    }

    /** @param  array<string, bool>  $esperado */
    #[Test]
    #[DataProvider('autorizacion')]
    #[DataProvider('carnet')]
    #[DataProvider('faena')]
    public function cada_estado_permite_exactamente_lo_suyo(\UnitEnum $estado, array $esperado): void
    {
        foreach ($esperado as $metodo => $valor) {
            $this->assertSame($valor, $estado->{$metodo}(), $estado::class.'::'.$estado->name.'->'.$metodo.'()');
        }
    }

    #[Test]
    public function las_tablas_cubren_todos_los_estados(): void
    {
        $this->assertCount(count(EstadoAprovechamiento::cases()), self::autorizacion());
        $this->assertCount(count(EstadoCarnet::cases()), self::carnet());
        $this->assertCount(count(EstadoFaena::cases()), self::faena());
    }

    #[Test]
    public function una_faena_nunca_reserva_y_descuenta_a_la_vez(): void
    {
        foreach (EstadoFaena::cases() as $estado) {
            $this->assertFalse($estado->reservaCupo() && $estado->consumeCupo(), $estado->value);
        }
    }

    #[Test]
    public function cada_estado_tiene_etiqueta_y_color(): void
    {
        foreach ([...EstadoAprovechamiento::cases(), ...EstadoCarnet::cases(), ...EstadoFaena::cases()] as $estado) {
            $this->assertNotSame('', $estado->etiqueta(), $estado->value);
            $this->assertNotSame('', $estado->color(), $estado->value);
        }
    }
}
