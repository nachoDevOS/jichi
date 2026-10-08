<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una regla de los roles del personal dijo que no.
 */
class RolInvalidoException extends RuntimeException
{
    public static function delSistema(string $rol): self
    {
        return new self("«{$rol}» es un rol del sistema: no se modifica ni se elimina desde el panel.");
    }

    public static function conUsuarios(string $rol, int $usuarios): self
    {
        return new self("«{$rol}» lo tienen {$usuarios} usuario(s). Asígneles otro rol antes de eliminarlo.");
    }
}
