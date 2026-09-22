<?php

namespace App\Http\Requests\Panel;

/**
 * Reglas para CORREGIR el borrador de una guía.
 *
 * Hereda las del papel y deja fuera el carnet: cambiar de titular no es
 * corregir un traslado, es emitir otro.
 */
class ActualizarGuiaRequest extends EmitirGuiaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reglasDelPapel();
    }
}
