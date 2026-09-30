<?php

namespace App\Http\Requests\Panel;

use App\Enums\ModalidadAprovechamiento;
use App\Models\CategoriaAprovechamiento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar un tramo de la ESCALA OFICIAL.
 */
class GuardarCategoriaAprovechamientoRequest extends FormRequest
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
            // Sin `nro_escala`: lo asigna el sistema al dar de alta y no se edita.

            /*
             * El TEXTO OFICIAL, y no se deduce de los kilos.
             */
            /*
             * La modalidad la fija la resolución al definir el tramo, no el
             * operador al otorgar: por eso se declara acá, en el catálogo, y no
             * en el formulario de otorgamiento. Puesta allá, dos cupos del mismo
             * tramo podrían terminar con reglas distintas.
             */
            'modalidad' => ['required', Rule::enum(ModalidadAprovechamiento::class)],

            'descripcion_kg' => ['required', 'string', 'max:160'],

            // Van en decimal porque la balanza pesa con decimales: con enteros,
            // un cupo de 100,5 kg caería fuera del primer tramo por redondeo.
            'kilos_min' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'kilos_max' => ['required', 'numeric', 'gt:kilos_min', 'max:9999999999', 'decimal:0,2'],

            // Los tramos comparten servicio; lo que no se repite es la tarifa,
            // o dos tramos cobrarían lo mismo.
            'servicio_sireb' => ['required', 'uuid'],
            'tarifa_sireb' => [
                'required', 'uuid',
                Rule::unique('categorias_aprovechamiento', 'tarifa_sireb')
                    ->whereNull('deleted_at')
                    ->ignore($this->idActual()),
            ],

            'estado' => ['required', 'boolean'],
        ];
    }

    /**
     * El control que mira la escala ENTERA, no el tramo suelto.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $min = (float) $this->input('kilos_min');
                $max = (float) $this->input('kilos_max');

                /*
                 * Dos tramos se solapan cuando uno empieza antes de que el otro
                 * termine Y termina después de que el otro empiece. Es la forma
                 * estándar de comparar intervalos, y es más corta que enumerar
                 * los cuatro casos de «contiene», «contenido», «pisa por la
                 * izquierda» y «pisa por la derecha».
                 */
                $choque = CategoriaAprovechamiento::query()
                    ->when($this->idActual(), fn ($q, $id) => $q->whereKeyNot($id))
                    ->where('kilos_min', '<=', $max)
                    ->where('kilos_max', '>=', $min)
                    ->orderBy('nro_escala')
                    ->first();

                if ($choque) {
                    $validator->errors()->add('kilos_min', sprintf(
                        'Este rango se pisa con la escala %d (%s a %s kg). Los tramos no pueden solaparse: '.
                        'un cupo que cayera en los dos no sabría con qué servicio cobrarse.',
                        $choque->nro_escala,
                        number_format((float) $choque->kilos_min, 2, ',', '.'),
                        number_format((float) $choque->kilos_max, 2, ',', '.'),
                    ));
                }

            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modalidad.required' => 'Indique bajo qué régimen se autoriza este tramo.',
            'descripcion_kg.required' => 'Escriba el texto tal como figura en la resolución.',
            'kilos_min.required' => 'Indique el piso del rango, en kilos.',
            'kilos_max.required' => 'Indique el techo del rango, en kilos.',
            'kilos_max.gt' => 'El techo del rango tiene que ser mayor que el piso.',
            'servicio_sireb.required' => 'Indique el id del servicio en Recaudaciones (SIREB).',
            'servicio_sireb.uuid' => 'El id del servicio no tiene el formato de SIREB: cópielo completo.',
            'tarifa_sireb.required' => 'Indique el id de la tarifa del tramo en SIREB.',
            'tarifa_sireb.uuid' => 'El id de la tarifa no tiene el formato de SIREB: cópielo completo.',
            'tarifa_sireb.unique' => 'Esa tarifa ya la usa otro tramo de la escala.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descripcion_kg' => trim((string) $this->input('descripcion_kg')),
            // SIREB manda los ids en minúscula: así se comparan tal cual.
            'servicio_sireb' => mb_strtolower(trim((string) $this->input('servicio_sireb'))),
            'tarifa_sireb' => mb_strtolower(trim((string) $this->input('tarifa_sireb'))),
            'estado' => $this->boolean('estado', true),
            // Casi todos los tramos son escala general: es el valor que evita
            // preguntar lo obvio en el caso frecuente.
            'modalidad' => $this->input('modalidad') ?: ModalidadAprovechamiento::EscalaGeneral->value,
        ]);
    }

    /** El id del tramo que se está editando, o null si es un alta. */
    private function idActual(): ?int
    {
        $categoria = $this->route('categoria');

        return $categoria instanceof CategoriaAprovechamiento ? $categoria->id : null;
    }
}
