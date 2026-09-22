<?php

namespace App\Http\Requests\Panel;

/**
 * Reglas para CORREGIR un permiso de faena en borrador.
 *
 * Hereda las de la emisión y le saca el carnet: al corregir, el titular no se
 * toca. Ver EmitirFaenaService::editar().
 */
class ActualizarFaenaRequest extends EmitirFaenaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();

        unset($reglas['carnet_id']);

        return $reglas;
    }
}
