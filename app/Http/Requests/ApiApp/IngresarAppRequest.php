<?php

namespace App\Http\Requests\ApiApp;

use App\Http\Middleware\ApiApp\SoloBeneficiarioApp;
use App\Http\Requests\Portal\IngresarRequest;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * Ingreso desde la app BeniDigital: la misma C.I., clave, mensaje y tope de
 * intentos que el portal web, pero devuelve un token en vez de abrir sesión.
 */
class IngresarAppRequest extends IngresarRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Nombre del token: en qué celular entró («Android · Pixel 7»).
            'dispositivo' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{0: User, 1: NewAccessToken} */
    public function emitirToken(): array
    {
        $cuenta = $this->cuentaValida();
        // Vence si el celular no se usa en DIAS_TOKEN días; con uso se renueva solo.
        $token = $cuenta->createToken(
            $this->input('dispositivo') ?: 'app',
            ['portal'],
            now()->addDays(SoloBeneficiarioApp::DIAS_TOKEN),
        );
        $this->registrarAcceso('login', $cuenta->id);

        return [$cuenta, $token];
    }
}
