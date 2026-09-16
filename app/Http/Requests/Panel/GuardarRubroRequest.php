<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoRubro;
use App\Models\Rubro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para el catálogo de rubros.
 *
 * Es el formulario que toca el administrador cuando cambia una ordenanza. Se
 * usa igual para crear y para editar; la única diferencia es que al editar hay
 * que excluir al propio registro de la regla de unicidad del nombre.
 */
class GuardarRubroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rubro = $this->route('rubro');
        $idActual = $rubro instanceof Rubro ? $rubro->id : null;

        return [
            // El nombre es único porque es lo que el operador ve en el selector
            // de solicitud: dos rubros llamados igual lo obligarían a elegir a
            // ciegas.
            'nombre' => ['required', 'string', 'max:50', Rule::unique('rubros', 'nombre')->ignore($idActual)],

            'descripcion' => ['nullable', 'string', 'max:1000'],

            /*
             * La tarifa vigente, en bolivianos.
             *
             * Se permite 0 —a diferencia del monto de un pago— porque hay
             * rubros exentos de cobro por ordenanza, y un trámite de costo cero
             * se aprueba sin ningún depósito.
             *
             * OJO: cambiar este valor NO afecta a los trámites ya registrados.
             * El costo se copia a `tramites.monto_requerido` al presentar la
             * solicitud, justamente para que subir una tarifa no deje impagos de
             * golpe expedientes que ya estaban cubiertos.
             */
            'costo' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            'estado' => ['required', Rule::enum(EstadoRubro::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del rubro es obligatorio.',
            'nombre.unique' => 'Ya existe un rubro con ese nombre.',
            'costo.required' => 'Indique el costo del rubro. Escriba 0 si es gratuito.',
            'costo.min' => 'El costo no puede ser negativo.',
            'estado.required' => 'Indique si el rubro está activo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
        ]);
    }
}
