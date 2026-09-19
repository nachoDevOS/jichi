<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para cerrar una guía.
 *
 * El peso es OPCIONAL: lo declarado al salir es lo que dijo la balanza del
 * origen, y al llegar se vuelve a pesar. Casi nunca coincide al kilo, así que
 * el campo permite corregirlo — y a diferencia de la faena, acá no hay tope:
 * no existe ningún cupo que exceder.
 */
class CerrarGuiaRequest extends FormRequest
{
    /** El permiso ya lo revisa el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'peso_total_kg' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'peso_total_kg.gt' => 'El peso descargado tiene que ser mayor que cero.',
            'peso_total_kg.decimal' => 'El peso lleva como máximo dos decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // La cadena vacía llega cuando el operador no tocó el campo: se convierte
        // en null para que `nullable` la acepte y el servicio entienda «dejar el
        // peso como estaba».
        $this->merge([
            'peso_total_kg' => $this->input('peso_total_kg') === '' ? null : $this->input('peso_total_kg'),
        ]);
    }
}
