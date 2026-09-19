<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para ampliar una bolsa madre ya otorgada.
 *
 * ----------------------------------------------------------------------------
 *  EL MOTIVO ES OBLIGATORIO Y TIENE LARGO MÍNIMO
 * ----------------------------------------------------------------------------
 *
 * Ampliar es dar MÁS kilos de los que la escala otorgaba, que es justamente lo
 * que el cupo viene a limitar. Sin un motivo escrito, dentro de seis meses
 * nadie puede explicar por qué esa persona tuvo 800 kg cuando su tramo daba
 * 500 — y la fila de `auditorias` diría QUÉ cambió pero no POR QUÉ.
 *
 * El mínimo de 10 caracteres está para que no se resuelva con «ok» o «sí». No
 * garantiza que el motivo sirva, pero sí que alguien haya tenido que escribir
 * una frase.
 */
class AmpliarCupoRequest extends FormRequest
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
            /*
             * Los kilos que se SUMAN, no el total nuevo.
             *
             * Pedir el total obligaría al operador a hacer la cuenta y a
             * acertarla, y un error ahí no se nota: 500 + 300 escrito como 300
             * RECORTARÍA el cupo en vez de ampliarlo, sin que nada avise.
             * Pidiendo lo que se suma, el peor error posible es sumar de menos.
             */
            'kilos_adicionales' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],

            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kilos_adicionales.required' => 'Indique cuántos kilos se suman al cupo.',
            'kilos_adicionales.gt' => 'La ampliación tiene que sumar kilos: escriba un número mayor que cero.',
            'kilos_adicionales.decimal' => 'Los kilos llevan como máximo dos decimales.',
            'motivo.required' => 'Escriba el motivo de la ampliación.',
            'motivo.min' => 'El motivo tiene que explicar la decisión: escriba al menos 10 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => trim((string) $this->input('motivo')),
        ]);
    }
}
