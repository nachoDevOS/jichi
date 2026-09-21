<?php

namespace App\Http\Requests\Panel;

use App\Enums\TipoActor;
use App\Models\TipoCarnet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar un tipo de carnet.
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
             * PARA QUÉ ACTIVIDAD SIRVE. Es lo que después obliga a que el tipo
             * elegido y el `tipo_actor` del carnet coincidan.
             */
            'tipo_actor' => ['required', Rule::enum(TipoActor::class)],

            'precio_bs' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],

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
            'precio_bs.required' => 'Indique el precio en bolivianos.',
            'precio_bs.decimal' => 'El precio lleva como máximo dos decimales.',
            'precio_bs.min' => 'El precio no puede ser negativo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            // Un tipo nuevo nace vigente; el checkbox llega ausente cuando está
            // destildado, y sin este boolean() la regla lo vería como null.
            'estado' => $this->boolean('estado', true),
        ]);
    }
}
