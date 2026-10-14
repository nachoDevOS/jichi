<?php

namespace App\Http\Requests\Panel;

use App\Enums\CondicionProducto;
use App\Enums\MedioTransporte;
use App\Enums\TipoTransporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir una guía de movimiento — los bloques A a D del papel.
 */
class EmitirGuiaRequest extends FormRequest
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
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],
            ...$this->reglasDelPapel(),
        ];
    }

    /**
     * Todo menos el carnet: lo comparte con la corrección del borrador, que no
     * deja cambiar de titular.
     *
     * @return array<string, mixed>
     */
    protected function reglasDelPapel(): array
    {
        return [
            //  BLOQUE B — la ubicación. Solo el lugar es obligatorio: el resto
            //  del papel se llena a mano y llega incompleto.
            'origen' => ['required', 'string', 'max:160'],
            'origen_departamento' => ['nullable', 'string', 'max:100'],
            'origen_provincia' => ['nullable', 'string', 'max:100'],
            'origen_distrito' => ['nullable', 'string', 'max:100'],

            'destino' => ['required', 'string', 'max:160'],
            'destino_departamento' => ['nullable', 'string', 'max:100'],
            'destino_provincia' => ['nullable', 'string', 'max:100'],
            'destino_distrito' => ['nullable', 'string', 'max:100'],

            //  BLOQUE C — el medio y el vehículo
            'medio_transporte' => ['nullable', Rule::enum(MedioTransporte::class)],
            'tipo_transporte' => ['nullable', Rule::enum(TipoTransporte::class)],
            'transporte_nombre' => ['nullable', 'string', 'max:150'],
            'transporte_placa' => ['nullable', 'string', 'max:50'],
            'transporte_capacidad_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],

            /*
             * La marca que vale plata: con ella el arancel se cobra al 50%.
             */
            'es_piscicultura' => ['required', 'boolean'],

            // De qué autorización sale el pescado. Si está aprobada, en fecha y le
            // alcanzan los kilos lo decide el servicio, con la fila bloqueada.
            'aprovechamiento_id' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('es_piscicultura')),
                Rule::prohibitedIf(fn (): bool => $this->boolean('es_piscicultura')),
                'nullable', 'integer',
                Rule::exists('aprovechamientos_pesq', 'id')->whereNull('deleted_at'),
            ],

            'observaciones' => ['nullable', 'string', 'max:1000'],

            /*
             *  El cuadro D. Al menos un renglón: una guía sin especies no
             *  ampara nada y solo gastaría una hoja del talonario.
             */
            'detalles' => ['required', 'array', 'min:1', 'max:20'],
            // Nombre y precio NO vienen del formulario: los copia el servicio del
            // catálogo. Que esté activo lo decide EmitirGuiaService.
            // El producto es una tarifa de SIREB: si se puede cobrar lo decide el servicio.
            'detalles.*.tarifa_sireb' => ['required', 'uuid'],
            'detalles.*.condicion' => ['required', Rule::enum(CondicionProducto::class)],
            'detalles.*.cantidad_kg' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet del comercializador.',
            'carnet_id.exists' => 'Ese carnet no existe.',
            'origen.required' => 'Indique desde dónde sale la carga.',
            'destino.required' => 'Indique a dónde va la carga.',
            'es_piscicultura.required' => 'Indique si el producto es de piscicultura.',
            'aprovechamiento_id.required' => 'Indique a qué pescador le compró el pescado.',
            'aprovechamiento_id.prohibited' => 'El producto de criadero no lleva pescador.',
            'aprovechamiento_id.exists' => 'Esa autorización no existe o fue eliminada.',
            'detalles.required' => 'Cargue al menos una especie en el detalle.',
            'detalles.min' => 'Cargue al menos una especie en el detalle.',
            'detalles.*.tarifa_sireb.required' => 'Elija el producto de este renglón.',
            'detalles.*.tarifa_sireb.uuid' => 'Ese producto no es una tarifa de SIREB.',
            'detalles.*.condicion.required' => 'Indique cómo viaja esta especie.',
            'detalles.*.cantidad_kg.required' => 'Indique los kilos de este renglón.',
            'detalles.*.cantidad_kg.gt' => 'Los kilos tienen que ser mayores que cero.',
            'detalles.*.cantidad_kg.decimal' => 'Los kilos llevan como máximo dos decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        /*
         * Las filas vacías se descartan acá. El formulario manda los cinco
         * renglones del papel y el operador llena los que usa; sin esto, las
         * vacías se validarían y el submit fallaría pidiendo un producto que
         * nadie quiso elegir.
         */
        $detalles = collect((array) $this->input('detalles', []))
            ->filter(fn ($fila): bool => is_array($fila) && ! empty($fila['tarifa_sireb']))
            ->values()
            ->all();

        $this->merge([
            'origen' => trim((string) $this->input('origen')),
            'destino' => trim((string) $this->input('destino')),
            'es_piscicultura' => $this->boolean('es_piscicultura'),
            'detalles' => $detalles,
        ]);
    }
}
