<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para un arancel: nombre (sin repetir) y servicio de SIREB. El concepto
 * no viene del formulario: lo pone el seeder. La tarifa no se guarda.
 */
class GuardarArancelSirebRequest extends FormRequest
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
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('aranceles_sireb', 'nombre')->ignore($this->route('arancel'))->whereNull('deleted_at'),
            ],
            'servicio_sireb' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'servicio_sireb.required' => 'Elija el servicio de SIREB.',
            'servicio_sireb.uuid' => 'El id del servicio no tiene el formato de SIREB.',
            'nombre.required' => 'Escriba el nombre del arancel.',
            'nombre.max' => 'El nombre no puede pasar de 120 caracteres.',
            'nombre.unique' => 'Ya hay un arancel con ese nombre.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // SIREB manda los ids en minúscula: así se comparan tal cual.
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
        ]);
    }
}
