<?php

namespace Tests\Feature\Faenas;

use App\Enums\EstadoFaena;
use App\Enums\TipoActor;
use App\Exceptions\PermisoOperativoException;
use App\Models\Beneficiario;
use App\Models\PermisoFaena;
use App\Services\EmitirFaenaService;
use App\Services\OtorgarCupoService;
use App\Services\RevisarFaenaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 4 — el permiso de faena: quién lo emite, cuánto sale, su número, las
 * fechas que pone la firma y su impresión. La reserva de kilos está en
 * ReservaFaenaTest. Ver docs/REGLAS-NEGOCIO.md.
 */
class FaenaCircuitoTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarYEntrar();
        config(['jichi.aprovechamiento.estricto' => true]);
    }

    #[Test]
    public function nace_pendiente_con_arancel_de_15_y_los_renglones_del_talonario(): void
    {
        $carnet = $this->pescadorHabilitado();

        $faena = app(EmitirFaenaService::class)->emitir($carnet, 120, [
            'embarcacion' => 'La Doña Olga', 'comandante_barco' => 'Pedro Suárez', 'region_desde' => 'Río Mamoré',
        ]);

        $this->assertSame(EstadoFaena::Pendiente, $faena->estado);
        $this->assertSame(15.0, $faena->montoACobrar());
        $this->assertSame('La Doña Olga', $faena->embarcacion);
        $this->assertNull($faena->fecha_salida, 'las fechas las pone la firma');
        $this->assertNotNull($faena->codigo_legible);
    }

    #[Test]
    public function el_numero_de_faena_es_correlativo_y_continuo(): void
    {
        $carnet = $this->pescadorHabilitado();
        $a = app(EmitirFaenaService::class)->emitir($carnet, 10);
        $b = app(EmitirFaenaService::class)->emitir($carnet, 10);

        $this->assertSame($a->numero_faena + 1, $b->numero_faena);
        $this->assertSame(6, strlen($a->numero_legible));
    }

    #[Test]
    public function la_firma_fija_la_salida_hoy_y_el_desembarque_a_los_treinta_dias(): void
    {
        $faena = $this->faenaAprobada($this->pescadorHabilitado(), 50);

        $this->assertSame(EstadoFaena::Aprobado, $faena->estado);
        $this->assertSame(now()->toDateString(), $faena->fecha_salida->toDateString());
        $this->assertSame(now()->addDays(30)->toDateString(), $faena->fecha_desembarque->toDateString());
        $this->assertTrue($faena->estaVigente());
        $this->assertTrue($faena->consumeCupo());
    }

    #[Test]
    public function a_los_treinta_dias_deja_de_valer(): void
    {
        $faena = $this->faenaAprobada($this->pescadorHabilitado(), 50);

        $this->travel(31)->days();

        $this->assertFalse($faena->fresh()->estaVigente());
        $this->assertTrue($faena->fresh()->estaCaducada());
    }

    #[Test]
    public function un_comercializador_no_emite_faenas(): void
    {
        $persona = Beneficiario::factory()->create();
        $carnet = $this->aprobarCarnet($this->emitirCarnet($persona, TipoActor::Comercializador));

        $this->expectException(PermisoOperativoException::class);
        app(EmitirFaenaService::class)->emitir($carnet, 10);
    }

    #[Test]
    public function con_la_autorizacion_sin_firmar_no_se_emite(): void
    {
        $persona = Beneficiario::factory()->create();
        $cupo = app(OtorgarCupoService::class)->otorgar($persona, $this->escala(3), 'Canoa');
        // Un carnet aprobado sobre una autorización todavía pendiente.
        $carnet = $this->aprobarCarnet($this->emitirCarnet($persona, TipoActor::Pescador, $cupo));

        $this->expectException(PermisoOperativoException::class);
        app(EmitirFaenaService::class)->emitir($carnet, 10);
    }

    #[Test]
    public function sin_cobrar_no_se_envia_y_sin_validar_no_se_aprueba(): void
    {
        $faena = app(EmitirFaenaService::class)->emitir($this->pescadorHabilitado(), 30);

        try {
            app(RevisarFaenaService::class)->enviar($faena);
            $this->fail('Se envió sin cobrar');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoFaena::Pendiente, $faena->fresh()->estado);
        }

        $this->pagar($faena);
        $enviada = app(RevisarFaenaService::class)->enviar($faena->fresh());

        $this->expectException(PermisoOperativoException::class);
        app(RevisarFaenaService::class)->aprobar($enviada);
    }

    #[Test]
    public function rechazar_devuelve_a_pendiente_sin_perder_los_pagos(): void
    {
        $faena = app(EmitirFaenaService::class)->emitir($this->pescadorHabilitado(), 30);
        $this->pagar($faena);
        $enviada = app(RevisarFaenaService::class)->enviar($faena->fresh());

        $rechazada = app(RevisarFaenaService::class)->rechazar($enviada, 'La matrícula no coincide');

        $this->assertSame(EstadoFaena::Pendiente, $rechazada->estado);
        $this->assertSame(15.0, $rechazada->montoPagado());
    }

    #[Test]
    public function el_borrador_se_corrige_y_se_elimina_solo_sin_pagos(): void
    {
        $carnet = $this->pescadorHabilitado();
        $faena = app(EmitirFaenaService::class)->emitir($carnet, 30);

        $this->assertSame(45.0, (float) app(EmitirFaenaService::class)->editar($faena, 45)->kilos_extraidos);

        $otra = app(EmitirFaenaService::class)->emitir($carnet, 10);
        app(EmitirFaenaService::class)->eliminar($otra, 'Cargada por error en ventanilla');
        $this->assertSoftDeleted($otra);

        $this->pagar($faena);
        $this->expectException(PermisoOperativoException::class);
        app(EmitirFaenaService::class)->eliminar($faena->fresh(), 'Ya tiene un pago');
    }

    #[Test]
    public function solo_la_aprobada_se_imprime_y_las_pantallas_abren(): void
    {
        $carnet = $this->pescadorHabilitado();
        $pendiente = app(EmitirFaenaService::class)->emitir($carnet, 10);
        $aprobada = $this->faenaAprobada($carnet, 20);

        $this->assertFalse($pendiente->puedeImprimirse());
        $this->get(route('faenas.imprimir', $pendiente))->assertRedirect();

        $pdf = $this->get(route('faenas.imprimir', $aprobada));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->get(route('faenas.index'))->assertOk();
        $this->get(route('faenas.create', ['carnet' => $carnet->id]))->assertOk();
        $this->get(route('faenas.show', $aprobada))->assertOk();
        $this->get(route('faenas.edit', $pendiente))->assertOk();
    }

    #[Test]
    public function el_formulario_registra_la_faena_por_http(): void
    {
        $carnet = $this->pescadorHabilitado();

        $this->post(route('faenas.store'), [
            'carnet_id' => $carnet->id,
            'kilos_extraidos' => '25',
            'embarcacion' => 'La Esperanza',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(25.0, (float) PermisoFaena::query()->firstOrFail()->kilos_extraidos);
    }
}
