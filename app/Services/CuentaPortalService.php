<?php

namespace App\Services;

use App\Exceptions\CuentaPortalException;
use App\Models\Beneficiario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Las cuentas del portal /mi-cuenta: una fila de `users` con `beneficiario_id`.
 *
 * La clave se entrega en ventanilla y es TEMPORAL: el portal obliga a cambiarla
 * al primer ingreso. Esta cuenta nunca lleva roles: no es personal del SEDAG.
 */
class CuentaPortalService
{
    /** Sin 0/O ni 1/I/L: se dicta y se copia a mano desde un papel. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Crea la cuenta y devuelve la clave temporal, que no se guarda legible en ningún lado.
     */
    public function darAcceso(Beneficiario $beneficiario): string
    {
        return DB::transaction(function () use ($beneficiario): string {
            // La fila de la persona bloqueada: dos ventanillas no le crean dos cuentas.
            $persona = Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            if ($persona->cuenta()->exists()) {
                throw CuentaPortalException::yaTieneCuenta($persona->nombreCompleto);
            }

            $clave = $this->claveTemporal();

            User::create([
                'beneficiario_id' => $persona->id,
                'name' => $persona->nombreCompleto,
                'ci' => $persona->ci,
                'email' => null,
                'password' => $clave,
                'activo' => true,
                'debe_cambiar_password' => true,
            ]);

            return $clave;
        });
    }

    /**
     * Nueva clave temporal. También reactiva una cuenta desactivada.
     */
    public function resetear(Beneficiario $beneficiario): string
    {
        $cuenta = $beneficiario->cuenta()->first()
            ?? throw CuentaPortalException::sinCuenta($beneficiario->nombreCompleto);

        $clave = $this->claveTemporal();

        $cuenta->update([
            'password' => $clave,
            'activo' => true,
            'debe_cambiar_password' => true,
        ]);

        return $clave;
    }

    /** Corta el acceso sin borrar la cuenta: resetear la vuelve a abrir. */
    public function desactivar(Beneficiario $beneficiario): void
    {
        $cuenta = $beneficiario->cuenta()->first()
            ?? throw CuentaPortalException::sinCuenta($beneficiario->nombreCompleto);

        $cuenta->update(['activo' => false]);
    }

    /** La que elige el propio beneficiario al entrar: deja de ser temporal. */
    public function cambiarClave(User $cuenta, string $nueva): void
    {
        $cuenta->update([
            'password' => $nueva,
            'debe_cambiar_password' => false,
        ]);
    }

    private function claveTemporal(): string
    {
        $clave = '';

        for ($i = 0; $i < 8; $i++) {
            $clave .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $clave;
    }
}
