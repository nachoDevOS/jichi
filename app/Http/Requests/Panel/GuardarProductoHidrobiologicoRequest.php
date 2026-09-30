<?php

namespace App\Http\Requests\Panel;

use App\Models\ProductoHidrobiologico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para dar de alta o corregir un producto hidrobiológico.
 */
class GuardarProductoHidrobiologicoRequest extends FormRequest
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
        $producto = $this->route('producto');
        $idActual = $producto instanceof ProductoHidrobiologico ? $producto->id : null;

        return [
            // Dos iguales serían indistinguibles en el desplegable de la guía.
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('productos_hidrobiologicos', 'nombre')->ignore($idActual)->whereNull('deleted_at'),
            ],

            // Varios productos pueden compartir tarifa: SIREB puede cobrar igual el kilo de dos especies.
            'servicio_sireb' => ['required', 'uuid'],
            'tarifa_sireb' => ['required', 'uuid'],

            'estado' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.unique' => 'Ya existe un producto con ese nombre.',
            'servicio_sireb.required' => 'Elija la tarifa de SIREB del producto.',
            'servicio_sireb.uuid' => 'El id del servicio no tiene el formato de SIREB.',
            'tarifa_sireb.required' => 'Elija la tarifa de SIREB del producto.',
            'tarifa_sireb.uuid' => 'El id de la tarifa no tiene el formato de SIREB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            // SIREB manda los ids en minúscula: así se comparan tal cual.
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
            'tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb'))),
            // El checkbox llega ausente cuando está destildado.
            'estado' => $this->boolean('estado', true),
        ]);
    }
}
