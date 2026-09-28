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

            // Es la tasa que se cobra por kilo: en cero, la guía saldría gratis.
            'precio_kg' => ['required', 'numeric', 'min:0.20', 'max:99999999', 'decimal:0,2'],

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
            'precio_kg.required' => 'Indique el precio por kilo en bolivianos.',
            'precio_kg.decimal' => 'El precio lleva como máximo dos decimales.',
            'precio_kg.min' => 'El precio por kilo es de 0,20 Bs en adelante.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            // El checkbox llega ausente cuando está destildado.
            'estado' => $this->boolean('estado', true),
        ]);
    }
}
