<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para ELIMINAR un aprovechamiento cargado por error.
 *
 * ============================================================================
 *  EL MOTIVO ES OBLIGATORIO, Y NO ES BUROCRACIA
 * ============================================================================
 *
 * La fila se borra: después de esto no queda nada que mirar salvo la línea de
 * `auditorias`. Si ahí no dice POR QUÉ, dentro de seis meses la única respuesta
 * posible a «¿y el cupo de Fulano?» es «alguien lo borró».
 *
 * El mínimo de 10 caracteres es lo que separa una explicación de un «error».
 * Coincide con el `minimo` que usa la ventana de confirmación del panel, y los
 * dos tienen que moverse juntos o el botón se habilitaría antes de que el
 * servidor acepte el texto.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE NO SE VALIDA ACÁ
 * ----------------------------------------------------------------------------
 *
 * Que el cupo esté pendiente, sin pagos y sin faenas. Esas tres corren DENTRO
 * de la transacción y con la fila bloqueada: entre que el operador abre la
 * ventana y confirma, otra ventanilla puede cobrarlo o emitirle una faena. Ver
 * OtorgarCupoService::eliminar().
 */
class EliminarCupoRequest extends FormRequest
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
            'motivo.required' => 'Escriba por qué se elimina este aprovechamiento.',
            'motivo.min' => 'El motivo tiene que explicar algo: escriba al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede pasar de 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
    }
}
