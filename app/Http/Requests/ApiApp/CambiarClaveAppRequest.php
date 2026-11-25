<?php

namespace App\Http\Requests\ApiApp;

use App\Http\Requests\Portal\CambiarClaveRequest;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * Las mismas reglas de clave que el portal web; solo cambia de dónde sale la
 * cuenta para comprobar la actual: el token, no la sesión.
 */
class CambiarClaveAppRequest extends CambiarClaveRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'actual' => ['required', 'string', 'current_password:sanctum'],
        ];
    }
}
