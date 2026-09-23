<?php

namespace App\Http\Requests\Panel;

use App\Models\PermisoFaena;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para emitir un permiso de faena.
 */
class EmitirFaenaRequest extends FormRequest
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
            // Que exista. Que HABILITE lo decide el servicio: son tres condiciones
            // que dependen de la fecha de hoy y del saldo.
            'carnet_id' => ['required', 'integer', Rule::exists('carnets', 'id')],

            // El número NO viene del formulario: lo genera el correlativo.

            // `gt:0`: una faena de cero kilos no autoriza nada y gastaría una hoja.
            'kilos_extraidos' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],

            // Futura no: daría un permiso que empieza a valer antes de que el papel
            // exista. Pasada sí, para poner al día lo emitido en papel.
            // Ni tan vieja que nazca vencido. El tope sale de la constante del
            // modelo, no escrito acá.
            'fecha_salida' => [
                'required', 'date', 'before_or_equal:today',
                'after:'.now()->subDays(PermisoFaena::DIAS_VIGENCIA)->toDateString(),
            ],

            /*
             * LA VENTANA DE ESTA SALIDA. No puede cerrar antes de abrir, ni
             * pasarse del techo de la resolución: la faena vence a los
             * DIAS_VIGENCIA de la salida y un desembarque posterior prometería
             * un permiso que ya caducó.
             */
            'fecha_desembarque' => ['required', 'date', 'after_or_equal:fecha_salida'],

            /*
             * LOS RENGLONES DEL PAPEL. Nullable porque el formulario se llena a
             * mano y llega incompleto: la obligatoriedad es del trámite en
             * ventanilla, no de la tabla.
             */
            'embarcacion' => ['nullable', 'string', 'max:150'],
            'propietario' => ['nullable', 'string', 'max:150'],
            'comandante_barco' => ['nullable', 'string', 'max:150'],
            'matricula_naval' => ['nullable', 'string', 'max:50'],
            'nro_kardex' => ['nullable', 'string', 'max:50'],
            'region_desde' => ['nullable', 'string', 'max:150'],
            'region_hasta' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'carnet_id.required' => 'Elija el carnet del pescador.',
            'carnet_id.exists' => 'Ese carnet no existe.',
            'kilos_extraidos.required' => 'Indique cuántos kilos autoriza la faena.',
            'kilos_extraidos.gt' => 'La faena tiene que autorizar kilos: escriba un número mayor que cero.',
            'kilos_extraidos.decimal' => 'Los kilos llevan como máximo dos decimales.',
            'fecha_salida.required' => 'Indique la fecha de salida.',
            'fecha_salida.before_or_equal' => 'La fecha de salida no puede ser futura.',
            'fecha_salida.after' => 'La faena vence a los '.PermisoFaena::DIAS_VIGENCIA
                .' días de la salida: con esa fecha nacería vencida.',
            'fecha_desembarque.required' => 'Indique la fecha de desembarque.',
            'fecha_desembarque.after_or_equal' => 'El desembarque no puede ser anterior a la salida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_salida' => $this->input('fecha_salida') ?: now()->toDateString(),
        ]);
    }

    /**
     * El techo de la ventana se valida acá y no en `rules()`: depende de
     * `fecha_salida`, que recién existe cuando la petición ya llegó.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $salida = $this->date('fecha_salida');
            $desembarque = $this->date('fecha_desembarque');

            if ($salida === null || $desembarque === null) {
                return;
            }

            $techo = PermisoFaena::limiteDesde($salida);

            if ($desembarque->gt($techo)) {
                $v->errors()->add('fecha_desembarque', sprintf(
                    'El permiso vale %d días desde la salida: el desembarque no puede pasar del %s.',
                    PermisoFaena::DIAS_VIGENCIA,
                    $techo->format('d/m/Y'),
                ));
            }
        });
    }
}
