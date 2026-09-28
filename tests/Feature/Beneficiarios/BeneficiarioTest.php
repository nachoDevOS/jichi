<?php

namespace Tests\Feature\Beneficiarios;

use App\Models\Beneficiario;
use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 1 — el registro base. Ver docs/REGLAS-NEGOCIO.md.
 */
class BeneficiarioTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarYEntrar();
    }

    #[Test]
    public function se_registra_una_persona_con_sus_datos(): void
    {
        $this->post(route('beneficiarios.store'), $this->datos(['ci' => '7162902']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $persona = Beneficiario::query()->where('ci', '7162902')->firstOrFail();
        $this->assertSame('Olga', $persona->primerNombre);
        $this->assertStringContainsString('Acevedo', $persona->nombreCompleto);
    }

    #[Test]
    public function la_cedula_no_se_repite_entre_los_vigentes(): void
    {
        $this->post(route('beneficiarios.store'), $this->datos(['ci' => '5555555']))->assertSessionHasNoErrors();

        $this->post(route('beneficiarios.store'), $this->datos(['ci' => '5555555']))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, Beneficiario::query()->where('ci', '5555555')->count());
    }

    #[Test]
    public function la_baja_es_logica_y_libera_la_cedula(): void
    {
        $persona = Beneficiario::factory()->create(['ci' => '4444444']);

        $this->delete(route('beneficiarios.destroy', $persona))->assertRedirect(route('beneficiarios.index'));

        $this->assertSoftDeleted($persona);
        $this->post(route('beneficiarios.store'), $this->datos(['ci' => '4444444']))->assertSessionHasNoErrors();
        $this->assertSame(1, Beneficiario::query()->where('ci', '4444444')->count());
        $this->assertSame(2, Beneficiario::withTrashed()->where('ci', '4444444')->count());
    }

    #[Test]
    public function faltan_los_datos_obligatorios(): void
    {
        $this->post(route('beneficiarios.store'), [])
            ->assertSessionHasErrors(['ci', 'primerNombre', 'apellidoPaterno', 'fechaNacimiento']);
    }

    #[Test]
    public function la_fecha_de_nacimiento_no_puede_ser_futura(): void
    {
        $this->post(route('beneficiarios.store'), $this->datos(['fechaNacimiento' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('fechaNacimiento');
    }

    #[Test]
    public function se_corrigen_los_datos(): void
    {
        $persona = Beneficiario::factory()->create();

        $this->put(route('beneficiarios.update', $persona), $this->datos(['ci' => $persona->ci, 'primerNombre' => 'Corregido']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Corregido', $persona->fresh()->primerNombre);
    }

    #[Test]
    public function el_buscador_pide_tres_letras_y_encuentra_por_cedula(): void
    {
        $persona = Beneficiario::factory()->create(['ci' => '9876543']);

        $this->getJson(route('beneficiarios.buscar', ['q' => '98']))->assertOk()->assertExactJson([]);
        $this->getJson(route('beneficiarios.buscar', ['q' => '98765']))
            ->assertOk()
            ->assertJsonFragment(['id' => $persona->id]);
    }

    #[Test]
    public function las_pantallas_abren(): void
    {
        $persona = Beneficiario::factory()->create();

        $this->get(route('beneficiarios.index'))->assertOk();
        $this->get(route('beneficiarios.create'))->assertOk();
        $this->get(route('beneficiarios.show', $persona))->assertOk();
        $this->get(route('beneficiarios.edit', $persona))->assertOk();
    }

    /** @return array<string, mixed> */
    private function datos(array $cambios = []): array
    {
        return [
            'ci' => '1234567',
            'departamento_id' => Departamento::query()->value('id'),
            'primerNombre' => 'Olga',
            'apellidoPaterno' => 'Acevedo',
            'apellidoMaterno' => 'Rodrigo',
            'fechaNacimiento' => '1985-05-10',
            'genero' => 'femenino',
            ...$cambios,
        ];
    }
}
