<?php

namespace Tests\Feature\Carnets;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\TipoCarnet;
use App\Services\EmitirCarnetService;
use App\Services\RevisarCarnetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 3 — la credencial del pescador: exige autorización, uno vigente por
 * actividad, su circuito, la reposición y el plástico. Ver REGLAS-NEGOCIO.
 */
class CarnetPescadorTest extends TestCase
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
    public function el_pescador_no_saca_carnet_sin_autorizacion(): void
    {
        $this->expectException(CarnetInvalidoException::class);
        $this->emitirCarnet($this->persona, TipoActor::Pescador);
    }

    #[Test]
    public function con_autorizacion_nace_pendiente_con_codigo_y_su_arancel_es_el_del_tipo(): void
    {
        $cupo = $this->autorizacionAprobada($this->persona);
        $carnet = $this->emitirCarnet($this->persona, TipoActor::Pescador, $cupo);

        $this->assertSame(EstadoCarnet::Pendiente, $carnet->estado);
        $this->assertSame($cupo->id, $carnet->aprovechamiento_id);
        $this->assertSame(16, strlen(str_replace('-', '', (string) $carnet->codigo_legible)));
        $this->assertSame((float) $carnet->tipoCarnet->precio_bs, $carnet->montoACobrar());
        $this->assertFalse($carnet->estaVigente());
        $this->assertFalse($carnet->puedeImprimirse());
    }

    #[Test]
    public function la_firma_da_numero_de_registro_y_vigencia_hasta_fin_de_anio(): void
    {
        $carnet = $this->pescadorHabilitado();

        $this->assertSame(EstadoCarnet::Aprobado, $carnet->estado);
        $this->assertNotNull($carnet->nro_registro);
        $this->assertSame(now()->endOfYear()->toDateString(), $carnet->fecha_vencimiento->toDateString());
        $this->assertTrue($carnet->estaVigente());
        $this->assertTrue($carnet->puedeEmitirFaenas());
        $this->assertFalse($carnet->puedeEmitirGuias(), 'el pescador no emite guías');
    }

    #[Test]
    public function los_numeros_de_registro_son_consecutivos(): void
    {
        $a = $this->pescadorHabilitado();
        $b = $this->pescadorHabilitado();

        $this->assertSame($a->nro_registro + 1, $b->nro_registro);
    }

    #[Test]
    public function sin_cobrar_no_se_envia_y_sin_validar_no_se_aprueba(): void
    {
        $cupo = $this->autorizacionAprobada($this->persona);
        $carnet = $this->emitirCarnet($this->persona, TipoActor::Pescador, $cupo);

        try {
            app(RevisarCarnetService::class)->enviar($carnet);
            $this->fail('Se envió sin cobrar');
        } catch (CarnetInvalidoException) {
            $this->assertSame(EstadoCarnet::Pendiente, $carnet->fresh()->estado);
        }

        $this->pagar($carnet);
        $enviado = app(RevisarCarnetService::class)->enviar($carnet->fresh());

        $this->expectException(CarnetInvalidoException::class);
        app(RevisarCarnetService::class)->aprobar($enviado);
    }

    #[Test]
    public function uno_vigente_por_actividad(): void
    {
        $carnet = $this->pescadorHabilitado();

        $this->expectException(CarnetInvalidoException::class);
        $this->emitirCarnet($carnet->beneficiario, TipoActor::Pescador, $carnet->aprovechamiento);
    }

    #[Test]
    public function la_reposicion_revoca_con_motivo_y_libera_el_lugar_con_la_misma_autorizacion(): void
    {
        $viejo = $this->pescadorHabilitado();

        $revocado = app(EmitirCarnetService::class)->reponer($viejo, 'Carnet extraviado en la faena');
        $this->assertSame(EstadoCarnet::Revocado, $revocado->estado);
        $this->assertFalse($revocado->puedeImprimirse());

        $nuevo = $this->aprobarCarnet($this->emitirCarnet($viejo->beneficiario, TipoActor::Pescador, $viejo->aprovechamiento));
        $this->assertSame($viejo->aprovechamiento_id, $nuevo->aprovechamiento_id);
        $this->assertNotSame($viejo->codigo_legible, $nuevo->codigo_legible);
        $this->assertTrue($nuevo->estaVigente());
    }

    #[Test]
    public function revocar_exige_motivo_y_solo_vale_sobre_un_aprobado(): void
    {
        $carnet = $this->pescadorHabilitado();

        try {
            app(EmitirCarnetService::class)->revocar($carnet, '  ');
            $this->fail('Se revocó sin motivo');
        } catch (CarnetInvalidoException) {
            $this->assertSame(EstadoCarnet::Aprobado, $carnet->fresh()->estado);
        }

        app(EmitirCarnetService::class)->revocar($carnet, 'Resolución de prueba suficiente');

        $this->expectException(CarnetInvalidoException::class);
        app(EmitirCarnetService::class)->revocar($carnet->fresh(), 'Otra vez');
    }

    #[Test]
    public function el_borrador_se_elimina_y_el_presentado_se_rechaza(): void
    {
        $cupo = $this->autorizacionAprobada($this->persona);
        $borrador = $this->emitirCarnet($this->persona, TipoActor::Pescador, $cupo);

        app(EmitirCarnetService::class)->eliminar($borrador, 'Cargado por error en ventanilla');
        $this->assertSoftDeleted($borrador);

        $carnet = $this->emitirCarnet($this->persona, TipoActor::Pescador, $cupo);
        $this->pagar($carnet);
        $enviado = app(RevisarCarnetService::class)->enviar($carnet->fresh());

        $rechazado = app(RevisarCarnetService::class)->rechazar($enviado, 'La foto no se ve');
        $this->assertSame(EstadoCarnet::Pendiente, $rechazado->estado);
    }

    #[Test]
    public function un_tipo_inactivo_no_se_elige(): void
    {
        $cupo = $this->autorizacionAprobada($this->persona);
        TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->update(['estado' => false]);

        $this->expectException(CarnetInvalidoException::class);
        app(EmitirCarnetService::class)->emitir(
            $this->persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail(),
            TipoActor::Pescador,
            cupoElegido: $cupo,
        );
    }

    #[Test]
    public function revocar_la_reposicion_no_toca_la_faena_si_hay_carnet_nuevo(): void
    {
        $viejo = $this->pescadorHabilitado();
        $faena = $this->faenaAprobada($viejo, 50);

        app(EmitirCarnetService::class)->reponer($viejo, 'Carnet dañado');
        $this->aprobarCarnet($this->emitirCarnet($viejo->beneficiario, TipoActor::Pescador, $viejo->aprovechamiento));

        $this->assertSame(EstadoFaena::Aprobado, $faena->fresh()->estado);
        $this->assertTrue($faena->fresh()->estaVigente());
    }

    #[Test]
    public function las_pantallas_y_el_plastico_abren(): void
    {
        $carnet = $this->pescadorHabilitado();

        $this->get(route('carnets.index'))->assertOk();
        $this->get(route('carnets.create'))->assertOk();
        $this->get(route('carnets.show', $carnet))->assertOk();

        $pdf = $this->get(route('carnets.imprimir', $carnet));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
}
