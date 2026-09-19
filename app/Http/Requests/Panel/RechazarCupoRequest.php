<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para RECHAZAR un aprovechamiento presentado a revisión.
 *
 * ============================================================================
 *  EL MOTIVO ES LO ÚNICO QUE EXPLICA LA DEVOLUCIÓN
 * ============================================================================
 *
 * Rechazar es devolverle el expediente a ventanilla, y lo que sigue es que lo
 * corrijan. Sin el texto escrito, quien lo recibe no sabe QUÉ corregir y el
 * expediente rebota: se vuelve a presentar igual y se vuelve a rechazar.
 *
 * El estado no recuerda el rechazo —vuelve a PENDIENTE, a secas— así que la
 * línea de `auditorias` es todo el rastro que queda.
 *
 * El mínimo de 10 caracteres coincide con el de la ventana de confirmación del
 * panel: si acá fuera menor, el botón se habilitaría antes de que el servidor
 * acepte el texto.
 */
class RechazarCupoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escriba por qué se rechaza, para que ventanilla sepa qué corregir.',
            'motivo.min' => 'El motivo tiene que explicar algo: escriba al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
