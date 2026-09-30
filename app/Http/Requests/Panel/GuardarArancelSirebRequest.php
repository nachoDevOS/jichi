<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para elegir la tarifa de SIREB de un arancel. El concepto no se edita:
 * lo fija el seeder.
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
            'servicio_sireb' => ['required', 'uuid'],
            'tarifa_sireb' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'servicio_sireb.required' => 'Elija la tarifa de SIREB del arancel.',
            'servicio_sireb.uuid' => 'El id del servicio no tiene el formato de SIREB.',
            'tarifa_sireb.required' => 'Elija la tarifa de SIREB del arancel.',
            'tarifa_sireb.uuid' => 'El id de la tarifa no tiene el formato de SIREB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // SIREB manda los ids en minúscula: así se comparan tal cual.
        $this->merge([
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
            'tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb'))),
        ]);
    }
}
