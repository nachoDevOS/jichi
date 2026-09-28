<?php

namespace Tests\Feature\Caja;

use App\Enums\EstadoValidacionPago;
use App\Enums\TipoActor;
use App\Exceptions\CobroInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Pago;
use App\Models\Recibo;
use App\Models\User;
use App\Services\CobrarService;
use App\Services\ControlarPagoService;
use App\Services\OtorgarCupoService;
use App\Services\RevisarCupoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 6 — el cobro: Caja, el recibo con su correlativo, y el control de cada
 * boleta (validar, observar, corregir). Ver docs/REGLAS-NEGOCIO.md y PAGOS.md.
 */
class CobroYControlTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->sembrarYEntrar();
    }

    //  Caja

    #[Test]
    public function un_recibo_cubre_la_autorizacion_y_el_carnet_de_la_misma_persona(): void
    {
        $persona = Beneficiario::factory()->create();
        $cupo = app(OtorgarCupoService::class)->otorgar($persona, $this->escala(2), 'Canoa');
        $carnet = $this->emitirCarnet($persona, TipoActor::Pescador, $cupo);

        $recibo = $this->cobrar([
            ['tipo' => 'cupo', 'id' => $cupo->id, 'monto' => $cupo->montoACobrar()],
            ['tipo' => 'carnet', 'id' => $carnet->id, 'monto' => $carnet->montoACobrar()],
        ]);

        $this->assertSame('000001', $recibo->numero_recibo);
        $this->assertSame($cupo->montoACobrar() + $carnet->montoACobrar(), (float) $recibo->monto_total);
        $this->assertSame($persona->id, $recibo->beneficiario_id);
        $this->assertSame(2, $recibo->pagos()->count());
        $this->assertTrue($cupo->fresh()->estaPagado());
        $this->assertTrue($carnet->fresh()->estaPagado());
        $this->assertNotNull($recibo->codigo_legible, 'el recibo también se verifica');
    }

    #[Test]
    public function el_numero_de_recibo_es_continuo(): void
    {
        $a = $this->cobrar([$this->lineaDeCupoNuevo()]);
        $b = $this->cobrar([$this->lineaDeCupoNuevo()]);

        $this->assertSame('000001', $a->numero_recibo);
        $this->assertSame('000002', $b->numero_recibo);
    }

    #[Test]
    public function no_se_cobra_de_mas_ni_lo_ya_pagado(): void
    {
        $linea = $this->lineaDeCupoNuevo();

        try {
            $this->cobrar([[...$linea, 'monto' => $linea['monto'] + 1]]);
            $this->fail('Se cobró de más');
        } catch (CobroInvalidoException) {
            $this->assertSame(0, Recibo::query()->count());
        }

        $this->cobrar([$linea]);

        $this->expectException(CobroInvalidoException::class);
        $this->cobrar([$linea]);
    }

    #[Test]
    public function un_recibo_no_ampara_tramites_de_dos_personas(): void
    {
        $this->expectException(CobroInvalidoException::class);
        $this->cobrar([$this->lineaDeCupoNuevo(), $this->lineaDeCupoNuevo()]);
    }

    #[Test]
    public function el_numero_de_boleta_no_se_repite(): void
    {
        $this->cobrar([$this->lineaDeCupoNuevo()], 'BOLETA-UNICA');

        try {
            $this->cobrar([$this->lineaDeCupoNuevo()], 'BOLETA-UNICA');
            $this->fail('Se usó dos veces la misma boleta');
        } catch (\Throwable) {
            $this->assertSame(1, Pago::query()->where('nro_transaccion', 'BOLETA-UNICA')->count());
        }
    }

    #[Test]
    public function un_cobro_parcial_deja_saldo(): void
    {
        $linea = $this->lineaDeCupoNuevo();
        $this->cobrar([[...$linea, 'monto' => 10]]);

        $cupo = AprovechamientoPesq::query()->findOrFail($linea['id']);
        $this->assertSame(10.0, $cupo->montoPagado());
        $this->assertSame($linea['monto'] - 10, $cupo->saldoPendiente());
    }

    //  Control de boletas

    #[Test]
    public function solo_se_controla_con_el_tramite_en_revision(): void
    {
        $persona = Beneficiario::factory()->create();
        $cupo = app(OtorgarCupoService::class)->otorgar($persona, $this->escala(1), 'Canoa');
        $this->pagar($cupo);

        $this->expectException(CobroInvalidoException::class);
        app(ControlarPagoService::class)->validar($cupo->pagos()->firstOrFail());
    }

    #[Test]
    public function validar_deja_quien_y_cuando(): void
    {
        $pago = $this->pagoEnRevision();

        $validado = app(ControlarPagoService::class)->validar($pago);

        $this->assertSame(EstadoValidacionPago::Validado, $validado->estado_validacion);
        $this->assertSame($this->admin->id, $validado->validado_por);
        $this->assertNotNull($validado->validado_en);
    }

    #[Test]
    public function un_observado_no_se_valida_se_corrige_y_vuelve_a_pendiente(): void
    {
        $pago = $this->pagoEnRevision();

        $observado = app(ControlarPagoService::class)->observar($pago, 'El número no figura en el extracto');
        $this->assertSame(EstadoValidacionPago::Observado, $observado->estado_validacion);
        $this->assertSame('El número no figura en el extracto', $observado->observacion);

        try {
            app(ControlarPagoService::class)->validar($observado);
            $this->fail('Se validó un observado sin corregirlo');
        } catch (CobroInvalidoException) {
            $this->assertSame(EstadoValidacionPago::Observado, $observado->fresh()->estado_validacion);
        }

        $corregido = app(ControlarPagoService::class)->corregir($observado, ['nro_transaccion' => 'CORREGIDO-99']);
        $this->assertSame(EstadoValidacionPago::Pendiente, $corregido->estado_validacion);
        $this->assertNull($corregido->validado_por);
        $this->assertNull($corregido->observacion);
        $this->assertSame('CORREGIDO-99', $corregido->nro_transaccion);

        $this->assertSame(EstadoValidacionPago::Validado, app(ControlarPagoService::class)->validar($corregido)->estado_validacion);
    }

    #[Test]
    public function un_observado_sigue_sumando(): void
    {
        $pago = $this->pagoEnRevision();
        app(ControlarPagoService::class)->observar($pago, 'Monto dudoso');

        $this->assertTrue($pago->pagable->fresh()->estaPagado());
    }

    #[Test]
    public function el_control_por_http_valida_y_observa(): void
    {
        $pago = $this->pagoEnRevision();

        $this->patch(route('pagos.observar', $pago), ['motivo' => 'No figura en el extracto'])->assertRedirect();
        $this->assertSame(EstadoValidacionPago::Observado, $pago->fresh()->estado_validacion);

        // El formulario exige solo dígitos en el número de boleta.
        $this->post(route('pagos.corregir', $pago), ['nro_transaccion' => '99887766', 'monto_parcial' => $pago->monto_parcial, 'fecha_deposito' => now()->toDateString()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->patch(route('pagos.validar', $pago))->assertRedirect();

        $this->assertSame(EstadoValidacionPago::Validado, $pago->fresh()->estado_validacion);
    }

    #[Test]
    public function las_pantallas_de_caja_y_recibos_y_el_pdf_abren(): void
    {
        $recibo = $this->cobrar([$this->lineaDeCupoNuevo()]);

        $this->get(route('caja.index'))->assertOk();
        $this->get(route('caja.create'))->assertOk();
        $this->get(route('recibos.index'))->assertOk();
        $this->get(route('recibos.show', $recibo))->assertOk();

        $pdf = $this->get(route('recibos.imprimir', $recibo));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    //  Auxiliares

    /** @param  array<int, array{tipo: string, id: int, monto: float}>  $lineas */
    private function cobrar(array $lineas, ?string $boleta = null): Recibo
    {
        return app(CobrarService::class)->cobrar(
            $lineas,
            $boleta ?? 'CAJA-'.uniqid(),
            now()->toDateString(),
            'comprobantes/prueba.pdf',
        );
    }

    /** @return array{tipo: string, id: int, monto: float} */
    private function lineaDeCupoNuevo(): array
    {
        $cupo = app(OtorgarCupoService::class)->otorgar(Beneficiario::factory()->create(), $this->escala(1), 'Canoa');

        return ['tipo' => 'cupo', 'id' => $cupo->id, 'monto' => $cupo->montoACobrar()];
    }

    private function pagoEnRevision(): Pago
    {
        $cupo = app(OtorgarCupoService::class)->otorgar(Beneficiario::factory()->create(), $this->escala(1), 'Canoa');
        $this->pagar($cupo);
        app(RevisarCupoService::class)->enviar($cupo->fresh());

        return $cupo->pagos()->firstOrFail();
    }
}
