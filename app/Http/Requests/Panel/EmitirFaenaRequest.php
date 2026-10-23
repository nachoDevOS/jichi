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

            // Los renglones del talonario: obligatorios desde el 23/10/2026 (decisión del responsable).
            'embarcacion' => ['required', 'string', 'max:150'],
            'propietario' => ['required', 'string', 'max:150'],
            'comandante_barco' => ['required', 'string', 'max:150'],
            'matricula_naval' => ['required', 'string', 'max:50'],
            'nro_kardex' => ['required', 'string', 'max:50'],
            'region_desde' => ['required', 'string', 'max:150'],
            'region_hasta' => ['required', 'string', 'max:150'],
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
            'embarcacion.required' => 'Escriba la embarcación.',
            'propietario.required' => 'Escriba de quién es la embarcación.',
            'comandante_barco.required' => 'Escriba el comandante de barco.',
            'matricula_naval.required' => 'Escriba la matrícula naval.',
            'nro_kardex.required' => 'Escriba el N° de Kardex.',
            'region_desde.required' => 'Escriba desde dónde va a pescar.',
            'region_hasta.required' => 'Escriba hasta dónde va a pescar.',
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
