<?php

namespace Tests\Feature\Catalogos;

use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\CategoriaAprovechamiento;
use App\Models\TipoCarnet;
use App\Services\EmitirCarnetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Los catálogos que edita la unidad: asociaciones, escala de aprovechamiento y
 * tipos de carnet. (Productos hidrobiológicos: ver Comercializador/GuiaTest.)
 */
class CatalogosTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarYEntrar();
    }

    #[Test]
    public function una_asociacion_se_registra_y_su_nombre_no_se_repite(): void
    {
        $this->post(route('asociaciones.store'), ['nombre' => 'Asociación de Prueba del Mamoré', 'sigla' => 'ASOPRU'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post(route('asociaciones.store'), ['nombre' => 'Asociación de Prueba del Mamoré'])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(1, Asociacion::query()->where('nombre', 'Asociación de Prueba del Mamoré')->count());
    }

    #[Test]
    public function una_asociacion_inactiva_no_avala_carnets_nuevos(): void
    {
        $asociacion = Asociacion::query()->firstOrFail();
        $this->put(route('asociaciones.update', $asociacion), ['nombre' => $asociacion->nombre, 'estado' => 'inactivo'])
            ->assertSessionHasNoErrors();

        $this->expectException(CarnetInvalidoException::class);
        app(EmitirCarnetService::class)->emitir(
            Beneficiario::factory()->create(),
            $asociacion->fresh(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Comercializador)->firstOrFail(),
            TipoActor::Comercializador,
        );
    }

    #[Test]
    public function la_escala_no_admite_tramos_que_se_pisen(): void
    {
        $this->post(route('categorias-aprovechamiento.store'), [
            'nro_escala' => 8, 'descripcion_kg' => '50 Kg Hasta 150 Kg', 'kilos_min' => 50, 'kilos_max' => 150,
            'valor_bs' => 10, 'modalidad' => 'escala_general', 'estado' => '1',
        ])->assertSessionHasErrors();

        $this->post(route('categorias-aprovechamiento.store'), [
            'nro_escala' => 8, 'descripcion_kg' => '2001 Kg Hasta 3000 Kg', 'kilos_min' => 2001, 'kilos_max' => 3000,
            'valor_bs' => 1500, 'modalidad' => 'escala_general', 'estado' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(CategoriaAprovechamiento::query()->where('nro_escala', 8)->exists());
    }

    #[Test]
    public function el_maximo_de_la_escala_tiene_que_superar_al_minimo(): void
    {
        $this->post(route('categorias-aprovechamiento.store'), [
            'nro_escala' => 9, 'descripcion_kg' => 'al revés', 'kilos_min' => 500, 'kilos_max' => 100,
            'valor_bs' => 10, 'modalidad' => 'escala_general', 'estado' => '1',
        ])->assertSessionHasErrors('kilos_max');
    }

    #[Test]
    public function un_tipo_de_carnet_se_corrige_y_su_precio_cambia_lo_que_se_cobra(): void
    {
        $tipo = TipoCarnet::query()->where('tipo_actor', TipoActor::Comercializador)->firstOrFail();

        $this->put(route('tipos-carnet.update', $tipo), [
            'nombre' => $tipo->nombre, 'tipo_actor' => 'comercializador', 'precio_bs' => '120.50', 'estado' => '1',
        ])->assertSessionHasNoErrors();

        $carnet = $this->emitirCarnet(Beneficiario::factory()->create(), TipoActor::Comercializador);
        $this->assertSame(120.5, $carnet->montoACobrar());
    }
}
