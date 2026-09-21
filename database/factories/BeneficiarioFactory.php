<?php

namespace Database\Factories;

use App\Models\Beneficiario;
use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiario>
 */
class BeneficiarioFactory extends Factory
{
    protected $model = Beneficiario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genero = fake()->randomElement(['masculino', 'femenino']);

        return [
            /*
             * La cédula se arma con unique() y no con un número al azar.
             */
            'ci' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            // En mayúscula, igual que lo guarda el formulario: el complemento se
            // normaliza en el Request, y la factory no pasa por ahí. Sin el
            // strtoupper, los datos sembrados muestran «8112684-8z» donde el
            // sistema real muestra «8112684-8Z».
            'complemento' => fake()->boolean(15) ? strtoupper(fake()->bothify('#?')) : null,
            // De la TABLA: `departamentos` es la fuente y la FK lo exige.
            'departamento_id' => Departamento::query()->inRandomOrder()->value('id'),

            'primerNombre' => fake()->firstName($genero === 'masculino' ? 'male' : 'female'),
            // Mucha gente no tiene segundo nombre: se refleja en los datos de
            // prueba para que las pantallas se vean con y sin él.
            'segundoNombre' => fake()->boolean(60) ? fake()->firstName() : null,
            'apellidoPaterno' => fake()->lastName(),
            'apellidoMaterno' => fake()->boolean(80) ? fake()->lastName() : null,
            'apellidoCasado' => null,

            'fechaNacimiento' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'genero' => $genero,
            'nacionalidad' => 'Boliviana',

            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->randomElement(['Trinidad', 'Riberalta', 'Guayaramerín', 'San Borja', 'Rurrenabaque']),
            'provincia' => fake()->randomElement(config('jichi.provincias')),
            'telefono' => fake()->numerify('7#######'),
            'email' => fake()->boolean(40) ? fake()->safeEmail() : null,
            'foto' => null,
        ];
    }

    /**
     * Una mujer casada, con el apellido del esposo.
     */
    public function casada(): static
    {
        return $this->state(fn (): array => [
            'genero' => 'femenino',
            'primerNombre' => fake()->firstName('female'),
            // Se guarda SIN el «de»: lo agrega el modelo al armar el nombre. Ver
            // Beneficiario::nombreCompleto().
            'apellidoCasado' => fake()->lastName(),
        ]);
    }

    /**
     * Sin segundo nombre ni apellido materno: el caso mínimo.
     */
    public function nombreCorto(): static
    {
        return $this->state(fn (): array => [
            'segundoNombre' => null,
            'apellidoMaterno' => null,
        ]);
    }
}
