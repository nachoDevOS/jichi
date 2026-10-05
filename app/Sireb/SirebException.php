<?php

namespace App\Sireb;

use RuntimeException;

/**
 * No se pudo obtener un precio de Recaudaciones. El mensaje es el que ve el funcionario.
 */
class SirebException extends RuntimeException
{
    /** El código de error de SIREB, si lo hubo. */
    public ?string $codigo = null;

    /** No es de SIREB: lo pone Jichi al ver la liquidación ya pagada. */
    public const PAGADA = 'LIQUIDACION_PAGADA';

    /** Ídem: hay un pago cargado que SIREB todavía no validó. */
    public const PAGO_EN_REVISION = 'PAGO_EN_REVISION';

    public static function noResponde(): self
    {
        return new self('Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos o avise a la Unidad de Sistemas.');
    }

    // 401/403: credencial vencida, rotada o el sistema `sedag` dado de baja en SIREB.
    public static function rechazado(int $estado): self
    {
        return new self("Recaudaciones (SIREB) rechazó la consulta (HTTP {$estado}). Avise a la Unidad de Sistemas: la credencial de Jichi no está habilitada.");
    }

    /** 404/422 de una liquidación; el código (`TARIFA_NO_VIGENTE`, `PAGO_ACTIVO`…) queda para quien llama. */
    public static function liquidacionRechazada(string $codigo): self
    {
        $e = new self("Recaudaciones (SIREB) no aceptó la liquidación ({$codigo}).");
        $e->codigo = $codigo;

        return $e;
    }

    public static function liquidacionPagada(): self
    {
        $e = new self('El pago de este trámite ya fue validado en Recaudaciones: no se puede eliminar ni corregir. '.
            'Use «Verificar pago» para que quede aprobado.');
        $e->codigo = self::PAGADA;

        return $e;
    }

    public static function pagoEnRevision(): self
    {
        $e = new self('Este trámite tiene un pago cargado en Recaudaciones que todavía está en revisión: '.
            'no se puede eliminar ni corregir.');
        $e->codigo = self::PAGO_EN_REVISION;

        return $e;
    }

    /** SIREB tiene un pago de este trámite: la acción se frena por eso y no por un error. */
    public function frenaPorPago(): bool
    {
        return in_array($this->codigo, [self::PAGADA, self::PAGO_EN_REVISION], true);
    }

    /** Lo que ve ventanilla cuando SIREB frena una acción: sin jerga ni códigos. */
    public function paraVentanilla(): string
    {
        return match ($this->codigo) {
            null => 'Recaudaciones no responde en este momento. No se cambió nada; espere unos minutos y vuelva a intentarlo.',
            self::PAGADA, self::PAGO_EN_REVISION => $this->getMessage(),
            default => 'Recaudaciones no aceptó la operación (puede tener un pago registrado allá). No se cambió nada; '.
                'consulte con el encargado del sistema.',
        };
    }
}
