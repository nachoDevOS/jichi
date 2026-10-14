<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un permiso de faena.
 */
class EmitirFaenaRequest extends FormRequest
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
            // Que exista. Que HABILITE lo decide el servicio: depende de la fecha de hoy.
            // Los kilos NO vienen del formulario: son los de la autorización.
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],

            // El número NO viene del formulario: lo genera el correlativo.

            // Las fechas NO vienen del formulario: las escribe la aprobación.

            /*
             * Los renglones del papel. Nullable porque el formulario se llena a
             * mano y llega incompleto: la obligatoriedad es del trámite en
             * ventanilla, no de la tabla.
             */
            'embarcacion' => ['nullable', 'string', 'max:150'],
            'propietario' => ['nullable', 'string', 'max:150'],
            'comandante_barco' => ['nullable', 'string', 'max:150'],
            'matricula_naval' => ['nullable', 'string', 'max:50'],
            'nro_kardex' => ['nullable', 'string', 'max:50'],
            'region_desde' => ['nullable', 'string', 'max:150'],
            'region_hasta' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet del pescador.',
            'carnet_id.exists' => 'Ese carnet no existe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // SIREB manda los ids en minúscula: así se comparan tal cual.
        if ($this->has('tarifa_sireb')) {
            $this->merge(['tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb')))]);
        }
    }
}
