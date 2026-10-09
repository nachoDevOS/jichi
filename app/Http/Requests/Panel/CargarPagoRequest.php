<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El pago que se carga en SIREB: el N°, el banco y, si lo hay, el comprobante
 * (se sube acá y SIREB recibe su URL). Los largos son los de su API.
 */
class CargarPagoRequest extends FormRequest
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
            'numero_transaccion' => ['required', 'string', 'max:50'],
            'banco' => ['required', 'string', 'max:100'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('jichi.archivos.max_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_transaccion.required' => 'Indique el N° de transacción del depósito.',
            'numero_transaccion.max' => 'El N° de transacción no puede pasar de 50 caracteres.',
            'banco.required' => 'Indique el banco donde se pagó.',
            'banco.max' => 'El banco no puede pasar de 100 caracteres.',
            'comprobante.mimes' => 'El comprobante tiene que ser una imagen (JPG, PNG, WEBP) o un PDF.',
            'comprobante.max' => 'El comprobante no puede pasar de 3 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_transaccion' => trim((string) $this->input('numero_transaccion')),
            'banco' => trim((string) $this->input('banco')),
        ]);
    }
}
