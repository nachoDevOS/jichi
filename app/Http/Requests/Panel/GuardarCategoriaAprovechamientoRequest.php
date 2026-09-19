<?php

namespace App\Http\Requests\Panel;

use App\Enums\ModalidadAprovechamiento;
use App\Models\CategoriaAprovechamiento;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar un tramo de la ESCALA OFICIAL.
 *
 * ============================================================================
 *  LA VALIDACIÓN QUE IMPORTA NO ES LA DE LOS CAMPOS: ES LA DE LOS TRAMOS
 * ============================================================================
 *
 * Cada campo por separado puede estar perfecto y la escala quedar rota igual.
 * Dos formas de romperla, y las dos son silenciosas:
 *
 *   - SOLAPE. Si el tramo 3 llega a 500 y el 4 arranca en 400, un cupo de 450
 *     cae en los dos. `CategoriaAprovechamiento::paraVolumen()` devuelve el de
 *     `nro_escala` más bajo, así que el sistema cobraría siempre el más barato
 *     —y nadie lo notaría hasta un arqueo—.
 *
 *   - HUECO. Si el 3 llega a 500 y el 4 arranca en 502, un cupo de 501 no cae
 *     en ninguno: `paraVolumen()` devuelve null y el formulario no ofrece
 *     ninguna escala, sin ningún error que lo explique.
 *
 * El SOLAPE se RECHAZA acá: es plata mal cobrada y no tiene lectura válida.
 *
 * El HUECO no se rechaza, y es deliberado: cargar la escala de a un tramo por
 * vez deja huecos TRANSITORIOS —al guardar el tramo 1 todavía no existe el 2—
 * y bloquearlos haría imposible cargarla. Se avisa desde la pantalla, que ve
 * la escala entera de una vez. Ver el índice de la escala.
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
             *
             * El número de tramos lo fija una resolución y puede cambiar; un
             * tope escrito en código convertiría ese cambio en un despliegue,
             * que es exactamente lo que esta tabla vino a evitar.
             */
            'nro_escala' => [
                'required', 'integer', 'min:1', 'max:99',
                Rule::unique('categorias_aprovechamiento', 'nro_escala')->ignore($this->idActual()),
            ],

            /*
             * El TEXTO OFICIAL, y no se deduce de los kilos.
             *
             * El tramo más alto dice «1001 kg Hasta 2000 Kg PAICHE», y ese
             * «PAICHE» no está en ningún número. El documento impreso tiene que
             * decir lo que dice la resolución.
             */
            /*
             * LA MODALIDAD LA FIJA LA RESOLUCIÓN AL DEFINIR EL TRAMO, no el
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

            'valor_bs' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],

            'estado' => ['required', 'boolean'],
        ];
    }

    /**
     * El control que mira la escala ENTERA, no el tramo suelto.
     *
     * Corre después de las reglas de campo —si los kilos ni siquiera son
     * números, no tiene sentido buscar solapes— y por eso va en `after()` y no
     * dentro de `rules()`.
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
                 * DOS TRAMOS SE SOLAPAN cuando uno empieza antes de que el otro
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
                        'un cupo que cayera en los dos se cobraría con el más barato.',
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
            'valor_bs.required' => 'Indique cuánto se cobra por este tramo.',
            'valor_bs.decimal' => 'El valor lleva como máximo dos decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'descripcion_kg' => trim((string) $this->input('descripcion_kg')),
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
