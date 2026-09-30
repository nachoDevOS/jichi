<?php

namespace App\Sireb;

use RuntimeException;

/**
 * No se pudo obtener un precio de Recaudaciones. El mensaje es el que ve el funcionario.
 */
class SirebException extends RuntimeException
{
    public static function noResponde(): self
    {
        return new self('Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos o avise a la Unidad de Sistemas.');
    }

    // 401/403: credencial vencida, rotada o el sistema `sedag` dado de baja en SIREB.
    public static function rechazado(int $estado): self
    {
        return new self("Recaudaciones (SIREB) rechazó la consulta (HTTP {$estado}). Avise a la Unidad de Sistemas: la credencial de Jichi no está habilitada.");
    }

    public static function servicioInexistente(string $codigo): self
    {
        return new self("El servicio {$codigo} no está en el catálogo del SEDAG en Recaudaciones, o no tiene un tarifario vigente.");
    }

    // Jichi no elige entre variantes: cada código tiene que tener un solo precio.
    public static function sinTarifaUnica(string $codigo, int $cantidad): self
    {
        return new self(sprintf(
            'El servicio %s tiene %d tarifas vigentes en Recaudaciones y Jichi necesita exactamente una. '.
            'Pida a Recaudaciones que lo deje con un solo precio.',
            $codigo,
            $cantidad,
        ));
    }
}
