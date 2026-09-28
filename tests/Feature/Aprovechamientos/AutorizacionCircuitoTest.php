<?php

namespace Tests\Feature\Aprovechamientos;

use App\Enums\EstadoAprovechamiento;
use App\Enums\TipoActor;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Recibo;
use App\Services\OtorgarCupoService;
use App\Services\RevisarCupoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 2 — la Autorización de Pesca para Aprovechamiento Pesquero, del
 * borrador a la firma, y cuándo se agota. Ver docs/REGLAS-NEGOCIO.md.
 */
class AutorizacionCircuitoTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    private Beneficiario $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarYEntrar();
        $this->persona = Beneficiario::factory()->create();
    }

    #[Test]
    public function nace_pendiente_y_hereda_volumen_y_valor_de_la_escala(): void
    {
        $escala = $this->escala(5);
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $escala, 'Canoa');

        $this->assertSame(EstadoAprovechamiento::Pendiente, $cupo->estado);
        $this->assertSame((float) $escala->kilos_max, (float) $cupo->volumen_total_kg);
        $this->assertSame((float) $escala->valor_bs, $cupo->montoACobrar());
        $this->assertNull($cupo->fecha_emision, 'la emisión la escribe la firma');
        $this->assertNotNull($cupo->codigo_legible);
        $this->assertFalse($cupo->puedeEmitirFaena());
    }

    #[Test]
    public function el_borrador_se_corrige_y_se_elimina_mientras_no_tenga_pagos(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(5), 'Canoa');

        $corregido = app(OtorgarCupoService::class)->editar($cupo, $this->escala(3), 'Peque-peque', now());
        $this->assertSame((float) $this->escala(3)->kilos_max, (float) $corregido->volumen_total_kg);
        $this->assertSame('Peque-peque', $corregido->tipo_embarcacion);

        $this->pagar($corregido);
        $this->assertFalse($corregido->fresh()->puedeEditarse());
        $this->assertFalse($corregido->fresh()->puedeEliminarse());

        $this->expectException(CupoInvalidoException::class);
        app(OtorgarCupoService::class)->eliminar($corregido->fresh(), 'Cargado por error en ventanilla');
    }

    #[Test]
    public function eliminar_el_borrador_sin_pagos_es_una_baja_logica(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(1), 'Canoa');

        app(OtorgarCupoService::class)->eliminar($cupo, 'Cargado por error en ventanilla');

        $this->assertSoftDeleted($cupo);
        // Y libera el lugar: se puede otorgar otra.
        $this->assertSame(EstadoAprovechamiento::Pendiente, app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(1), 'Canoa')->estado);
    }

    #[Test]
    public function sin_cobrar_no_se_envia_y_al_enviar_sale_un_recibo(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(2), 'Canoa');

        try {
            app(RevisarCupoService::class)->enviar($cupo);
            $this->fail('Se envió sin cubrir el monto');
        } catch (CupoInvalidoException) {
            $this->assertSame(EstadoAprovechamiento::Pendiente, $cupo->fresh()->estado);
        }

        $this->pagar($cupo);
        $enviado = app(RevisarCupoService::class)->enviar($cupo->fresh());

        $this->assertSame(EstadoAprovechamiento::EnRevision, $enviado->estado);
        $this->assertSame(1, Recibo::query()->count());
        $this->assertSame((float) $this->escala(2)->valor_bs, (float) Recibo::query()->firstOrFail()->monto_total);
    }

    #[Test]
    public function la_firma_exige_boletas_validadas_y_fija_la_vigencia_al_fin_de_anio(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(2), 'Canoa');
        $this->pagar($cupo);
        $cupo = app(RevisarCupoService::class)->enviar($cupo->fresh());

        try {
            app(RevisarCupoService::class)->aprobar($cupo);
            $this->fail('Se aprobó con la boleta sin validar');
        } catch (CupoInvalidoException) {
            $this->assertSame(EstadoAprovechamiento::EnRevision, $cupo->fresh()->estado);
        }

        $this->validarBoletas($cupo);
        $aprobado = app(RevisarCupoService::class)->aprobar($cupo->fresh());

        $this->assertSame(EstadoAprovechamiento::Aprobado, $aprobado->estado);
        $this->assertSame(now()->toDateString(), $aprobado->fecha_emision->toDateString());
        $this->assertSame(now()->endOfYear()->toDateString(), $aprobado->fecha_vencimiento->toDateString());
        $this->assertTrue($aprobado->estaVigente());
        $this->assertTrue($aprobado->puedeImprimirse());
    }

    #[Test]
    public function rechazar_devuelve_a_pendiente(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(2), 'Canoa');
        $this->pagar($cupo);
        $cupo = app(RevisarCupoService::class)->enviar($cupo->fresh());

        $rechazado = app(RevisarCupoService::class)->rechazar($cupo, 'Falta el documento de la embarcación');

        $this->assertSame(EstadoAprovechamiento::Pendiente, $rechazado->estado);
    }

    #[Test]
    public function se_agota_cuando_las_faenas_aprobadas_consumen_todo(): void
    {
        $carnet = $this->pescadorHabilitado(1); // escala 1: hasta 100 kg
        $cupo = $carnet->aprovechamiento;

        $this->faenaAprobada($carnet, 60);
        $this->assertSame(EstadoAprovechamiento::Aprobado, $cupo->fresh()->estado);

        $this->faenaAprobada($carnet, 40);
        $cupo = $cupo->fresh();

        $this->assertSame(EstadoAprovechamiento::Agotado, $cupo->estado);
        $this->assertSame(0.0, $cupo->saldoKg());
        $this->assertFalse($cupo->puedeEmitirFaena());
        $this->assertStringContainsString('sin kilos', (string) $cupo->motivoSinFaena());
    }

    #[Test]
    public function la_ficha_el_listado_y_el_papel_abren(): void
    {
        $carnet = $this->pescadorHabilitado();
        $cupo = $carnet->aprovechamiento;

        $this->get(route('aprovechamientos.index'))->assertOk();
        $this->get(route('aprovechamientos.create'))->assertOk();
        $this->get(route('aprovechamientos.show', $cupo))->assertOk();

        $pdf = $this->get(route('aprovechamientos.autorizacion', $cupo));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    #[Test]
    public function un_borrador_no_se_imprime(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(1), 'Canoa');

        $this->assertFalse($cupo->puedeImprimirse());
        $this->get(route('aprovechamientos.autorizacion', $cupo))->assertRedirect();
    }

    #[Test]
    public function el_carnet_se_emite_aunque_la_autorizacion_este_en_tramite_pero_la_faena_no(): void
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($this->persona, $this->escala(1), 'Canoa');
        $carnet = $this->emitirCarnet($this->persona, TipoActor::Pescador, $cupo);

        $this->assertSame($cupo->id, $carnet->aprovechamiento_id);
        $this->assertFalse(AprovechamientoPesq::query()->find($cupo->id)->puedeEmitirFaena());
    }
}
