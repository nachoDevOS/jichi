<?php

namespace Database\Factories;

use App\Models\Solicitante;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitante>
 */
class SolicitanteFactory extends Factory
{
    protected $model = Solicitante::class;

    public function definition(): array
    {
        return [
            'ci_nit' => (string) $this->faker->unique()->numberBetween(1_000_000, 9_999_999),
            'complemento' => $this->faker->optional(0.2)->bothify('#?'),
            'expedido' => 'BN',

            // El segundo nombre y el apellido materno se dejan opcionales a
            // propósito: en el padrón real hay mucha gente que no los tiene, y
            // si la factory siempre los completara, las pruebas nunca pasarían
            // por el camino en que faltan.
            'primerNombre' => $this->faker->firstName(),
            'segundoNombre' => $this->faker->optional(0.6)->firstName(),
            'apellidoPaterno' => $this->faker->lastName(),
            'apellidoMaterno' => $this->faker->optional(0.8)->lastName(),
            'apellidoCasada' => null,

            'fechaNacimiento' => $this->faker->dateTimeBetween('-65 years', '-18 years'),
            'genero' => $this->faker->randomElement(['masculino', 'femenino']),
            'nacionalidad' => 'Boliviana',

            'direccion' => $this->faker->streetAddress(),
            'ciudad' => 'Trinidad',
            'provincia' => $this->faker->randomElement(config('jichi.provincias')),
            'telefono' => '7'.$this->faker->numerify('#######'),
            'email' => $this->faker->optional(0.4)->safeEmail(),

            /*
             * La ficha viene CON fotografía porque es lo normal en el padrón:
             * el solicitante se registra en ventanilla y ahí mismo le sacan la
             * foto para la credencial. La ruta apunta a un archivo que no
             * existe, y da igual: nada la abre, solo se comprueba que esté
             * cargada. Para el caso contrario está el estado sinFoto().
             */
            'foto' => 'solicitantes/fotos/prueba.jpg',
        ];
    }

    /**
     * Ficha sin fotografía cargada.
     *
     * La credencial se imprime con la cara del titular, así que sin foto no se
     * puede emitir. Este estado existe para probar esa compuerta: ver
     * `TipoTramite::habilitacionPara()` y `Habilitacion::sinFotografia()`.
     */
    public function sinFoto(): static
    {
        return $this->state(fn (): array => ['foto' => null]);
    }

    /**
     * Mujer casada que usa el apellido del esposo.
     *
     * Es el único caso donde `nombreCompleto` agrega el «de», así que conviene
     * poder generarlo para probar cómo queda impreso en la credencial.
     */
    public function casada(): static
    {
        return $this->state(fn (): array => [
            'genero' => 'femenino',
            // Los nombres se vuelven a sortear en femenino: si no, la factory
            // deja fichas como «Samuel Medina de Lomeli», que en una pantalla
            // de demostración se lee como un error del sistema.
            'primerNombre' => $this->faker->firstNameFemale(),
            'segundoNombre' => $this->faker->optional(0.6)->firstNameFemale(),
            'apellidoCasada' => $this->faker->lastName(),
        ]);
    }
}
