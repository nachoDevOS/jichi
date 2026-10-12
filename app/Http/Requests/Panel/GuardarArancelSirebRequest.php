<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para elegir el servicio de SIREB de un arancel. El concepto no se
 * edita: lo fija el seeder. La tarifa no se guarda: se elige al emitir.
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
        ];
    }

    protected function prepareForValidation(): void
    {
        // SIREB manda los ids en minúscula: así se comparan tal cual.
        $this->merge([
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
        ]);
    }
}
