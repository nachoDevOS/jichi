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
}
