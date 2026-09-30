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
            /*
             * NO lleva `max:7` aunque hoy la escala tenga siete tramos.
             */
            'nro_escala' => [
                'required', 'integer', 'min:1', 'max:99',
                Rule::unique('categorias_aprovechamiento', 'nro_escala')->ignore($this->idActual()),
            ],

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

            // Un código por tramo: dos tramos con el mismo servicio cobrarían lo mismo.
            'servicio_sireb' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('categorias_aprovechamiento', 'servicio_sireb')
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
            'nro_escala.required' => 'Indique el número de escala.',
            'nro_escala.unique' => 'Ya existe un tramo con ese número de escala.',
            'modalidad.required' => 'Indique bajo qué régimen se autoriza este tramo.',
            'descripcion_kg.required' => 'Escriba el texto tal como figura en la resolución.',
            'kilos_min.required' => 'Indique el piso del rango, en kilos.',
            'kilos_max.required' => 'Indique el techo del rango, en kilos.',
            'kilos_max.gt' => 'El techo del rango tiene que ser mayor que el piso.',
            'servicio_sireb.required' => 'Indique el código del servicio en Recaudaciones (SIREB).',
            'servicio_sireb.regex' => 'El código va en mayúsculas, sin espacios: SEDAG-001.',
            'servicio_sireb.unique' => 'Ese código ya lo usa otro tramo de la escala.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descripcion_kg' => trim((string) $this->input('descripcion_kg')),
            'servicio_sireb' => mb_strtoupper(trim((string) $this->input('servicio_sireb'))),
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
