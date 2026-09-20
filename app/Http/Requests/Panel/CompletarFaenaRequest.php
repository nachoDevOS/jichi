<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para cerrar un permiso de faena.
 */
class CompletarFaenaRequest extends FormRequest
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
            'kilos_extraidos' => ['nullable', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kilos_extraidos.gt' => 'Los kilos descargados tienen que ser mayores que cero.',
            'kilos_extraidos.decimal' => 'Los kilos llevan como máximo dos decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // La cadena vacía llega cuando el operador no tocó el campo: se convierte
        // en null para que `nullable` la acepte y el servicio entienda «dejar los
        // kilos como estaban».
        $this->merge([
            'kilos_extraidos' => $this->input('kilos_extraidos') === '' ? null : $this->input('kilos_extraidos'),
        ]);
    }
}
