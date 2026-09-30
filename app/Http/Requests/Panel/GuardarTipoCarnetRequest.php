<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoActor;
use App\Models\TipoCarnet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para corregir un tipo de carnet. No hay alta: ver routes/panel.php.
 */
class GuardarTipoCarnetRequest extends FormRequest
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
        $tipo = $this->route('tipo_carnet');
        $idActual = $tipo instanceof TipoCarnet ? $tipo->id : null;

        return [
            /*
             * Dos tipos no pueden llamarse igual: en un desplegable serían
             * indistinguibles y el operador elegiría cualquiera de los dos.
             */
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('tipos_carnet', 'nombre')->ignore($idActual),
            ],

            /*
             * Para qué actividad sirve. Es lo que después obliga a que el tipo
             * elegido y el `tipo_actor` del carnet coincidan.
             */
            'tipo_actor' => ['required', Rule::enum(TipoActor::class)],

            // Una tarifa por tipo: dos tipos con la misma cobrarían lo mismo.
            'servicio_sireb' => ['required', 'uuid'],
            'tarifa_sireb' => [
                'required', 'uuid',
                Rule::unique('tipos_carnet', 'tarifa_sireb')->whereNull('deleted_at')->ignore($idActual),
            ],

            'estado' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del tipo de carnet es obligatorio.',
            'nombre.unique' => 'Ya existe un tipo de carnet con ese nombre.',
            'tipo_actor.required' => 'Indique si el tipo es de pescador o de comercializador.',
            'servicio_sireb.required' => 'Elija la tarifa de SIREB del tipo de carnet.',
            'servicio_sireb.uuid' => 'El id del servicio no tiene el formato de SIREB.',
            'tarifa_sireb.required' => 'Elija la tarifa de SIREB del tipo de carnet.',
            'tarifa_sireb.uuid' => 'El id de la tarifa no tiene el formato de SIREB.',
            'tarifa_sireb.unique' => 'Esa tarifa ya la usa otro tipo de carnet.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            // SIREB manda los ids en minúscula: así se comparan tal cual.
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
            'tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb'))),
            // El checkbox llega ausente cuando está destildado, y sin este
            // boolean() la regla lo vería como null.
            'estado' => $this->boolean('estado', true),
        ]);
    }
}
