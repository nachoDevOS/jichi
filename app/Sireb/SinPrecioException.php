<?php

namespace App\Sireb;

use RuntimeException;

/**
 * SIREB no dio un precio cobrable. El mensaje dice por qué, y quien emite lo
 * envuelve en su propia excepción con el nombre del documento.
 */
class SinPrecioException extends RuntimeException {}
