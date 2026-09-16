<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoRubro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Reglas del formulario de alta de solicitud.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE ESTA CLASE NO VALIDA, Y ES IMPORTANTE
 * ----------------------------------------------------------------------------
 *
 * Acá solo se comprueba la FORMA de lo que llegó: que el rubro exista, que los
 * archivos estén y pesen lo permitido, que el monto sea un número.
 *
 * Las reglas del NEGOCIO —si la persona ya tiene carnet de esta gestión, si ese
 * rubro ya está habilitado, si hay otra solicitud en curso— viven en
 * SolicitudCarnetService y NO se duplican acá. El motivo es concreto: esas
 * comprobaciones tienen que correr dentro de la transacción y con la fila del
 * beneficiario bloqueada, porque entre el momento de validar y el de escribir
 * otra ventanilla puede haber cambiado la situación. Un FormRequest corre antes
 * de todo eso, así que lo que compruebe acá puede ser mentira medio segundo
 * después.
 *
 * Tampoco se pide el `tipo_tramite`: lo decide el sistema mirando si hay carnet
 * de la gestión. Ver App\Enums\TipoTramite.
 */
class RegistrarSolicitudRequest extends FormRequest
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
        $maxKb = config('jichi.archivos.max_kb');
        $extensiones = implode(',', config('jichi.archivos.extensiones'));

        return [
            'beneficiario_id' => [
                'required',
                // Sin el whereNull, una ficha dada de baja pasaría la validación
                // y recién fallaría adentro del servicio. Mejor que el mensaje
                // aparezca bajo el campo del formulario.
                Rule::exists('beneficiarios', 'id')->whereNull('deleted_at'),
            ],

            'rubro_id' => [
                'required',
                // Solo rubros activos: los dados de baja no se ofrecen en el
                // selector, pero el id llega por el cuerpo de la petición y
                // cualquiera puede escribir otro.
                Rule::exists('rubros', 'id')->where('estado', EstadoRubro::Activo->value),
            ],

            /*
             * LOS DOS RESPALDOS SON OBLIGATORIOS — Regla B.
             *
             * Se aceptan PDF además de imagen porque el certificado de la
             * asociación suele llegar escaneado desde una fotocopiadora, y esas
             * máquinas devuelven PDF.
             *
             * Nótese que en la BASE las columnas son nullable: la obligatoriedad
             * es del formulario, no del almacenamiento. Un expediente migrado
             * del papel puede no tener los adjuntos, y una NOT NULL haría
             * imposible cargarlo.
             */
            'ciFile' => ['required', 'file', 'mimes:'.$extensiones, 'max:'.$maxKb],
            'certAsociacionFile' => ['required', 'file', 'mimes:'.$extensiones, 'max:'.$maxKb],

            /*
             * A QUÉ ASOCIACIÓN PERTENECE.
             *
             * Obligatoria y junto al certificado, porque es lo que ese papel
             * respalda: sin el nombre escrito, el archivo adjunto es un PDF que
             * nadie puede buscar ni cruzar con nada.
             *
             * Texto libre y no un id de una tabla: hay decenas de asociaciones,
             * nacen y se disuelven, y ninguna oficina mantiene ese padrón. Una
             * lista cerrada obligaría a dar de alta una asociación antes de poder
             * atender a alguien en ventanilla.
             */
            'asociacion' => ['required', 'string', 'min:3', 'max:150'],

            /*
             * CUPO AUTORIZADO EN KILOS — OBLIGATORIO.
             *
             * Sin cupo la habilitación no dice cuánto autoriza, y una guía de
             * transporte presentada después no tiene contra qué contrastarse.
             * Dejarlo entrar vacío significaría descubrir el faltante recién en
             * el control, cuando ya no hay a quién preguntarle.
             *
             * En la BASE la columna es nullable, y eso NO es una contradicción:
             * es la misma decisión que con `asociacion` y los dos adjuntos. La
             * obligatoriedad es de ESTE formulario, no del almacenamiento —un
             * expediente migrado del padrón en papel puede no traer el dato, y
             * una NOT NULL haría imposible cargarlo—.
             *
             * `min:0.01` y no `min:0`: un cupo de cero no autoriza nada, así que
             * no es un cupo sino una habilitación que no sirve. Quien escriba 0
             * casi seguro quiso decir otra cosa.
             *
             * El tope de 999999.99 lo impone la columna `decimal(10,2)`; sin
             * esta regla el número se guardaría truncado o reventaría en la base
             * con un error que el operador no puede entender.
             */
            'capacidad_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],

            'observaciones' => ['nullable', 'string', 'max:1000'],

            /*
             * PAGOS DEL MISMO DÍA — Regla C, parte opcional.
             *
             * El pescador puede llegar con la boleta en la mano, con dos, o sin
             * ninguna. Por eso el arreglo entero es `nullable` pero cada fila que
             * venga tiene que estar completa: un pago sin comprobante no se
             * puede auditar después.
             *
             * `pagos.*.monto` con `min:0.01` y no `min:0`: un pago de cero no es
             * un pago, es una fila que ensucia el historial y hace creer que se
             * cobró algo.
             */
            'pagos' => ['nullable', 'array', 'max:10'],
            'pagos.*.nro_transaccion' => ['required', 'string', 'max:50', 'distinct', 'unique:pagos,nro_transaccion'],
            'pagos.*.monto' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'pagos.*.comprobante' => ['required', 'file', 'mimes:'.$extensiones, 'max:'.$maxKb],
            'pagos.*.fecha_pago' => ['nullable', 'date', 'before_or_equal:today'],
            'pagos.*.observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = round(config('jichi.archivos.max_kb') / 1024, 1);

        return [
            'beneficiario_id.required' => 'Seleccione al beneficiario que presenta la solicitud.',
            'beneficiario_id.exists' => 'El beneficiario seleccionado no existe o está dado de baja.',
            'rubro_id.required' => 'Seleccione el rubro que se solicita.',
            'rubro_id.exists' => 'El rubro seleccionado no está disponible.',

            'ciFile.required' => 'Adjunte la fotocopia del carnet de identidad.',
            'ciFile.mimes' => 'La fotocopia del carnet debe ser PDF o imagen.',
            'ciFile.max' => "La fotocopia del carnet no puede pesar más de {$mb} MB.",

            'certAsociacionFile.required' => 'Adjunte el certificado de la asociación.',
            'certAsociacionFile.mimes' => 'El certificado debe ser PDF o imagen.',
            'certAsociacionFile.max' => "El certificado no puede pesar más de {$mb} MB.",

            'asociacion.required' => 'Escriba a qué asociación pertenece el beneficiario.',
            'asociacion.min' => 'El nombre de la asociación es demasiado corto.',

            'capacidad_kg.required' => 'Indique la capacidad autorizada en kilos.',
            'capacidad_kg.numeric' => 'La capacidad autorizada tiene que ser un número en kilos.',
            'capacidad_kg.min' => 'La capacidad autorizada debe ser mayor a cero.',
            'capacidad_kg.max' => 'La capacidad autorizada es demasiado alta. Revise el número.',

            'pagos.*.nro_transaccion.required' => 'Escriba el número de transacción del depósito.',
            'pagos.*.nro_transaccion.unique' => 'Ese número de transacción ya fue registrado en otro pago.',
            'pagos.*.nro_transaccion.distinct' => 'Hay dos pagos con el mismo número de transacción.',
            'pagos.*.monto.required' => 'Indique el monto del depósito.',
            'pagos.*.monto.min' => 'El monto del depósito debe ser mayor a cero.',
            'pagos.*.comprobante.required' => 'Adjunte la boleta del depósito.',
            'pagos.*.fecha_pago.before_or_equal' => 'La fecha del depósito no puede ser futura.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'beneficiario_id' => 'beneficiario',
            'rubro_id' => 'rubro',
            'ciFile' => 'fotocopia de carnet de identidad',
            'certAsociacionFile' => 'certificado de la asociación',
            'asociacion' => 'asociación',
            'capacidad_kg' => 'capacidad autorizada',
            'observaciones' => 'observaciones',
        ];
    }

    /**
     * Los pagos que llegaron, listos para PagoTramiteService.
     *
     * Se arma acá y no en el controlador porque es una transformación de la
     * ENTRADA —el formulario manda `pagos[0][comprobante]` y el servicio espera
     * un arreglo de arreglos con el UploadedFile adentro—, y el controlador no
     * tiene por qué conocer la forma del formulario.
     *
     * @return array<int, array{nro_transaccion: string, monto: float, comprobante: UploadedFile, fecha_pago: string|null, observaciones: string|null}>
     */
    public function pagosIniciales(): array
    {
        $pagos = [];

        foreach (array_keys($this->input('pagos', []) ?? []) as $indice) {
            $pagos[] = [
                'nro_transaccion' => trim((string) $this->input("pagos.$indice.nro_transaccion")),
                'monto' => (float) $this->input("pagos.$indice.monto"),
                // El archivo NO está en input(): los adjuntos viajan aparte y se
                // leen con file(). Buscarlo en validated() devolvería null y el
                // pago se guardaría sin comprobante.
                'comprobante' => $this->file("pagos.$indice.comprobante"),
                'fecha_pago' => $this->input("pagos.$indice.fecha_pago"),
                'observaciones' => $this->input("pagos.$indice.observaciones"),
            ];
        }

        return $pagos;
    }
}
