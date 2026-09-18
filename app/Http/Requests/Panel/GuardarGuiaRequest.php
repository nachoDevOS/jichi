<?php

namespace App\Http\Requests\Panel;

use App\Enums\CondicionProducto;
use App\Enums\TipoTransporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas del formulario de emisión de una guía de transporte.
 *
 * ----------------------------------------------------------------------------
 *  VALIDA DOS COSAS A LA VEZ: LA CABECERA Y LA GRILLA
 * ----------------------------------------------------------------------------
 *
 * `detalles` llega como una lista de filas, y las reglas con `detalles.*.campo`
 * se aplican a cada una. Sin eso, una fila con la especie cargada y los kilos en
 * blanco pasaría la validación y se guardaría como una línea que no dice nada.
 *
 * Lo que NO se valida acá: que el carnet emita guías y esté vigente, y que la
 * carga entre en el vehículo. Las tres son reglas de negocio y viven en
 * `GuiaService` — la última porque cruza la cabecera con la grilla, que es
 * justamente lo que un `rules()` no puede expresar de forma legible.
 */
class GuardarGuiaRequest extends FormRequest
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

            // El número del talonario. Ver la nota de GuardarFaenaRequest.
            'nro_guia' => ['required', 'string', 'max:50', 'unique:guias,nro_guia'],
            'nro_recibo' => ['nullable', 'string', 'max:50'],

            /*
             * ORIGEN Y DESTINO: solo el LUGAR es obligatorio.
             *
             * Es lo que hace falta para que la guía diga algo en un control.
             * Departamento, provincia y distrito se piden porque están en el
             * formulario de papel, pero exigirlos frenaría la ventanilla cuando
             * el pescador no sabe a qué distrito pertenece el paraje del que
             * salió.
             */
            'origen_lugar' => ['required', 'string', 'max:150'],
            'origen_depto' => ['nullable', 'string', 'max:100'],
            'origen_provincia' => ['nullable', 'string', 'max:100'],
            'origen_distrito' => ['nullable', 'string', 'max:100'],

            'destino_lugar' => ['required', 'string', 'max:150'],
            'destino_depto' => ['nullable', 'string', 'max:100'],
            'destino_provincia' => ['nullable', 'string', 'max:100'],
            'destino_distrito' => ['nullable', 'string', 'max:100'],

            // El valor se compara contra el enum y no contra una lista escrita
            // acá: con dos copias, alguna termina desactualizada.
            'tipo_transporte' => ['required', Rule::enum(TipoTransporte::class)],

            'transporte_nombre' => ['required', 'string', 'max:150'],
            'transporte_placa' => ['nullable', 'string', 'max:50'],

            // Nullable: de una canoa nadie conoce la capacidad. Cuando está,
            // GuiaService la contrasta contra la carga declarada.
            'capacidad_maxima' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            'observaciones' => ['nullable', 'string', 'max:500'],

            /*
             * LA GRILLA DE CARGA.
             *
             * `min:1` porque una guía sin carga no ampara ningún traslado. El
             * servicio lo vuelve a comprobar DESPUÉS de descartar las filas
             * vacías, que es el caso real: la grilla arranca con una fila en
             * blanco y el operador puede enviarla sin llenar.
             */
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.especie' => ['required', 'string', 'max:100'],
            'detalles.*.condicion' => ['required', Rule::enum(CondicionProducto::class)],
            'detalles.*.cantidad_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'detalles.*.precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],

            /*
             * `imponible` se acepta tal como lo escribe el operador y NO se
             * recalcula contra cantidad × precio: es la base de cálculo que
             * figura en el papel, y con una rebaja o un redondeo no coincide.
             * Validar la multiplicación haría rebotar guías correctas.
             */
            'detalles.*.imponible' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet de comercializador al que se le emite la guía.',
            'carnet_id.exists' => 'El carnet elegido no existe.',
            'nro_guia.required' => 'Escriba el número que trae el formulario del talonario.',
            'nro_guia.unique' => 'Ese número ya está registrado en otra guía.',
            'origen_lugar.required' => 'Indique desde qué lugar sale la carga.',
            'destino_lugar.required' => 'Indique a qué lugar va la carga.',
            'tipo_transporte.required' => 'Indique si el traslado es fluvial, aéreo o terrestre.',
            'transporte_nombre.required' => 'Indique la empresa o el nombre del transportista.',
            'detalles.required' => 'Cargue al menos una línea de producto.',
            'detalles.min' => 'Cargue al menos una línea de producto.',
            'detalles.*.especie.required' => 'Cada línea necesita la especie.',
            'detalles.*.condicion.required' => 'Indique en qué condición viaja cada especie.',
            'detalles.*.cantidad_kg.required' => 'Indique los kilos de cada línea.',
            'detalles.*.cantidad_kg.min' => 'Los kilos de cada línea tienen que ser mayores a cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'carnet_id' => 'carnet',
            'nro_guia' => 'número de guía',
            'nro_recibo' => 'número de recibo',
            'origen_lugar' => 'lugar de origen',
            'destino_lugar' => 'lugar de destino',
            'tipo_transporte' => 'tipo de transporte',
            'transporte_nombre' => 'transportista',
            'transporte_placa' => 'placa o matrícula',
            'capacidad_maxima' => 'capacidad del transporte',
        ];
    }
}
