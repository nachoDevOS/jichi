<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas del formulario de emisión de una faena.
 *
 * ----------------------------------------------------------------------------
 *  LA PANTALLA EXIGE MÁS QUE LA TABLA, Y ES A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * En la base casi todo es nullable porque un permiso viejo cargado desde el
 * archivo puede llegar incompleto. Acá, en cambio, se pide lo que el formulario
 * de papel pide: sin embarcación ni comandante, el permiso no identifica a nadie
 * en un control del río.
 *
 * Es la misma decisión que en `RegistrarSolicitudRequest`: la obligatoriedad es
 * de la PANTALLA, no del almacenamiento.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE NO SE VALIDA ACÁ
 * ----------------------------------------------------------------------------
 *
 * Que el carnet esté vigente y que su rubro emita faenas. Eso lo decide
 * `FaenaService`, porque son reglas de NEGOCIO —cambian con el catálogo— y
 * porque el mensaje tiene que poder explicar cuál de las dos falló.
 */
class GuardarFaenaRequest extends FormRequest
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
        return [
            'carnet_id' => ['required', 'integer', 'exists:carnets,id'],

            /*
             * El número del talonario. `unique` da el mensaje legible; quien
             * garantiza de verdad es el índice único de la tabla, porque entre
             * validar y escribir otra ventanilla puede usar el mismo número.
             *
             * No se exige que sean solo dígitos: el talonario trae prefijos y
             * series según el lote impreso.
             */
            'nro_permiso' => ['required', 'string', 'max:50', 'unique:faenas,nro_permiso'],

            'nro_recibo' => ['nullable', 'string', 'max:50'],

            // Puede venir vacío: la base pone la tarifa vigente por defecto.
            'monto' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            'embarcacion' => ['required', 'string', 'max:150'],
            'propietario' => ['nullable', 'string', 'max:150'],
            'comandante_barco' => ['required', 'string', 'max:150'],
            'matricula_naval' => ['nullable', 'string', 'max:50'],
            'nro_kardex' => ['nullable', 'string', 'max:50'],
            'region_desde' => ['required', 'string', 'max:150'],
            'region_hasta' => ['nullable', 'string', 'max:150'],

            /*
             * LAS DOS FECHAS DEFINEN LA VENTANA DEL PERMISO.
             *
             * `after_or_equal` y no `after`: una salida que va y vuelve en el
             * día es lo más común en el río, y con `after` no se podría cargar.
             *
             * La base NO comprueba esto —un CHECK no puede dar un mensaje
             * entendible— así que esta regla es la única que lo impide.
             */
            'fecha_salida' => ['required', 'date'],
            'fecha_desembarque' => ['required', 'date', 'after_or_equal:fecha_salida'],

            // min:0.01 y no min:0: una faena autorizada a cero kilos no autoriza
            // nada, y es casi siempre un campo que quedó sin llenar.
            'cantidad_autorizada_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],

            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet de pescador al que se le emite la faena.',
            'carnet_id.exists' => 'El carnet elegido no existe.',
            'nro_permiso.required' => 'Escriba el número que trae el formulario del talonario.',
            'nro_permiso.unique' => 'Ese número ya está registrado en otra faena.',
            'embarcacion.required' => 'Indique la embarcación: sin ella el permiso no identifica la salida.',
            'comandante_barco.required' => 'Indique quién comanda la embarcación.',
            'region_desde.required' => 'Indique desde qué región sale.',
            'fecha_salida.required' => 'Indique la fecha de salida.',
            'fecha_desembarque.required' => 'Indique la fecha de desembarque.',
            'fecha_desembarque.after_or_equal' => 'El desembarque no puede ser anterior a la salida.',
            'cantidad_autorizada_kg.required' => 'Indique cuántos kilos autoriza esta salida.',
            'cantidad_autorizada_kg.min' => 'La cantidad autorizada tiene que ser mayor a cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'carnet_id' => 'carnet',
            'nro_permiso' => 'número de permiso',
            'nro_recibo' => 'número de recibo',
            'comandante_barco' => 'comandante',
            'matricula_naval' => 'matrícula naval',
            'nro_kardex' => 'número de kardex',
            'region_desde' => 'región de salida',
            'region_hasta' => 'región de destino',
            'cantidad_autorizada_kg' => 'cantidad autorizada',
        ];
    }
}
