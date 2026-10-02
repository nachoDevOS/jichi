<?php

namespace App\Sireb;

use RuntimeException;

/**
 * SIREB no dio un precio cobrable. El mensaje dice por qué, y quien emite lo
 * envuelve en su propia excepción con el nombre del documento.
 */
class SinPrecioException extends RuntimeException
{
    /** SIREB no contestó: se reintenta más tarde. Si es false, contestó que no se cobra. */
    public bool $sinRespuesta = false;
}
