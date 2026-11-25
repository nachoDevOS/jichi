<?php

namespace App\Http\Requests\ApiApp;

use App\Http\Requests\Panel\ActualizarGuiaRequest;
use Illuminate\Support\Arr;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * La guía pedida por el propio comercializador (portal web y app): ruta, transporte
 * y cuadro D con las MISMAS reglas y mensajes que ventanilla (ActualizarGuiaRequest).
 * El carnet es el suyo, de la sesión; el pescador llega por el CÓDIGO de su
 * autorización, no por un id; y la piscicultura sigue oculta, como en el panel.
 */
class PedirGuiaRequest extends ActualizarGuiaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['es_piscicultura', 'aprovechamiento_id']),
            'autorizacion_codigo' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'autorizacion_codigo.required' => 'Indique el código de la Autorización de Pesca del pescador que le vendió.',
        ];
    }
}
