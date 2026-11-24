<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoLiquidacionSireb;
use App\Enums\TipoActor;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Services\CobroQrService;
use App\Services\ConfirmarPagoService;
use App\Services\EmitirCarnetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * El cobro por QR (23/11/2026): SIREB genera el QR —real o de prueba— y Jichi lo muestra,
 * se entera del pago y aprueba. Y el webhook firmado de SIREB. Ver docs/modulos/PAGO-QR.md.
 */
class PagoQrTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private const SECRETO = 'secreto-del-webhook';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('s3');
        $this->simularSireb();
        config(['jichi.sireb.webhook_secreto' => self::SECRETO]);
    }

    public function test_muestra_el_qr_que_genera_sireb_y_es_el_mismo_mientras_vale(): void
    {
        $carnet = $this->carnetPendiente();

        $primero = app(CobroQrService::class)->qr($carnet);
        $segundo = app(CobroQrService::class)->qr($carnet->refresh());

        $this->assertTrue($primero['puede']);
        $this->assertSame(base64_encode('png-de-prueba'), $primero['qr']['imagen']);
        $this->assertTrue($primero['qr']['simulado']);
        $this->assertSame(15.0, $primero['qr']['monto']);
        $this->assertSame($primero['qr']['transaccion_id'], $segundo['qr']['transaccion_id']);
        // Lo guarda sin la imagen: hace falta para simular el pago.
        $this->assertSame($primero['qr']['transaccion_id'], $carnet->refresh()->sireb_envio['qr']['transaccion_id']);
        $this->assertArrayNotHasKey('imagen', $carnet->sireb_envio['qr']);
    }

    public function test_sin_pasarela_en_sireb_ofrece_el_deposito(): void
    {
        $this->qrDisponible = false;
        $carnet = $this->carnetPendiente();

        $respuesta = app(CobroQrService::class)->qr($carnet);

        $this->assertFalse($respuesta['puede']);
        $this->assertFalse($respuesta['disponible']);
        $this->assertStringContainsString('depósito', $respuesta['mensaje']);
        $this->assertSame(EstadoCarnet::Pendiente, $carnet->refresh()->estado);
    }

    public function test_un_qr_real_no_se_puede_simular(): void
    {
        $this->qrSimulado = false;
        $carnet = $this->carnetPendiente();

        $this->assertFalse(app(CobroQrService::class)->qr($carnet)['qr']['simulado']);
        $resultado = app(CobroQrService::class)->simularPago($carnet->refresh());

        $this->assertFalse($resultado['aprobado']);
        $this->assertSame(0, $this->simulacionesDePago, 'pidió simular el pago de un QR real');
    }

    public function test_simular_el_pago_aprueba_y_el_recibo_lleva_la_transaccion(): void
    {
        $carnet = $this->carnetPendiente();
        $qr = app(CobroQrService::class)->qr($carnet)['qr'];

        $resultado = app(CobroQrService::class)->simularPago($carnet->refresh());

        $this->assertTrue($resultado['aprobado'], $resultado['mensaje']);
        $carnet->refresh();
        $this->assertSame(EstadoCarnet::Aprobado, $carnet->estado);
        $this->assertSame($qr['transaccion_id'], $carnet->recibo->numero_boleta);
        $this->assertSame('Pago por QR de prueba', $carnet->recibo->entidad_bancaria);
        $this->assertEquals(15, $carnet->recibo->monto_total);
    }

    public function test_la_ventana_abierta_se_entera_del_pago(): void
    {
        $carnet = $this->carnetPendiente();
        app(CobroQrService::class)->qr($carnet);
        $this->pagarEnSireb($carnet);

        $estado = app(CobroQrService::class)->estado($carnet->refresh());

        $this->assertTrue($estado['pagado']);
        $this->assertSame(EstadoCarnet::Aprobado, $carnet->refresh()->estado);
    }

    public function test_el_aviso_firmado_de_sireb_aprueba_el_tramite(): void
    {
        $carnet = $this->carnetPendiente();
        $this->pagarEnSireb($carnet);

        $this->avisar($carnet, 'pago_confirmado', 'entrega-1')->assertOk()->assertJson(['recibido' => true]);

        $this->assertSame(EstadoCarnet::Aprobado, $carnet->refresh()->estado);
    }

    public function test_un_aviso_con_firma_mala_o_viejo_se_rechaza(): void
    {
        $carnet = $this->carnetPendiente();
        $this->pagarEnSireb($carnet);

        $this->avisar($carnet, 'pago_confirmado', 'entrega-2', secreto: 'otro')->assertUnauthorized();
        $this->avisar($carnet, 'pago_confirmado', 'entrega-3', momento: time() - 301)->assertUnauthorized();

        config(['jichi.sireb.webhook_secreto' => null]);
        $this->avisar($carnet, 'pago_confirmado', 'entrega-4', secreto: '')->assertUnauthorized();

        $this->assertSame(EstadoCarnet::Pendiente, $carnet->refresh()->estado);
    }

    public function test_un_aviso_repetido_no_se_procesa_dos_veces(): void
    {
        $carnet = $this->carnetPendiente();

        $this->avisar($carnet, 'pago_confirmado', 'entrega-5')->assertOk()->assertJsonMissing(['repetido' => true]);
        $this->avisar($carnet, 'pago_confirmado', 'entrega-5')->assertOk()->assertJson(['repetido' => true]);
    }

    public function test_el_aviso_no_se_cree_sin_consultar_a_sireb(): void
    {
        // El aviso dice «pagada», pero SIREB todavía la tiene pendiente: no se aprueba.
        $carnet = $this->carnetPendiente();

        $this->avisar($carnet, 'pago_confirmado', 'entrega-6')->assertOk();

        $this->assertSame(EstadoCarnet::Pendiente, $carnet->refresh()->estado);
    }

    public function test_el_qr_trae_los_segundos_contados_por_el_servidor(): void
    {
        $qr = app(CobroQrService::class)->qr($this->carnetPendiente())['qr'];

        $this->assertEqualsWithDelta(1800, $qr['segundos_restantes'], 5);
    }

    public function test_la_ventana_no_hace_registrar_otra_liquidacion_si_la_suya_cayo(): void
    {
        // Anulada o vencida: verificar() prepararía y registraría otra. Desde el portal, cada 10 s.
        foreach (['anulada' => ['sireb_estado' => EstadoLiquidacionSireb::Anulada], 'vencida' => ['sireb_envio' => ['consulta' => ['estado' => 'vencida']]]] as $caso => $cambio) {
            $carnet = $this->carnetPendiente();
            $carnet->update($cambio);
            $antes = count($this->liquidaciones);

            $estado = app(CobroQrService::class)->estado($carnet->refresh());

            $this->assertFalse($estado['pagado'], $caso);
            $this->assertCount($antes, $this->liquidaciones, "registró otra liquidación en SIREB ({$caso})");
        }
    }

    public function test_una_consulta_vieja_no_pisa_la_liquidacion_nueva(): void
    {
        // El webhook de «anulada» llega mientras una corrección registra otra liquidación.
        $carnet = $this->carnetPendiente();
        $viejo = Carnet::query()->find($carnet->id);
        $vieja = $carnet->sireb_liquidacion_id;
        $nueva = (string) Str::uuid();
        $carnet->update(['sireb_liquidacion_id' => $nueva, 'sireb_envio' => ['respuesta' => ['id' => $nueva]]]);

        $anotado = $viejo->anotarEnvioSireb($vieja, ['consulta' => ['estado' => 'anulada', 'en' => now()->toIso8601String()]]);

        $this->assertFalse($anotado);
        $this->assertSame(['respuesta' => ['id' => $nueva]], $carnet->refresh()->sireb_envio);
        $this->assertNull($carnet->liquidacionCaida());
    }

    public function test_un_aviso_con_un_id_que_no_es_uuid_no_rompe(): void
    {
        $carnet = $this->carnetPendiente();
        $cuerpo = json_encode(['id' => 'e-7', 'evento' => 'pago_confirmado', 'ocurrido_en' => now()->toIso8601String(), 'liquidacion' => ['id' => 'no-es-uuid']]);
        $momento = time();

        $this->call('POST', '/api/sireb/aviso', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAITITI_ENTREGA' => 'e-7', 'HTTP_X_PAITITI_TIMESTAMP' => (string) $momento,
            'HTTP_X_PAITITI_FIRMA' => 'sha256='.hash_hmac('sha256', $momento.'.'.$cuerpo, self::SECRETO),
        ], $cuerpo)->assertOk();

        $this->assertSame(EstadoCarnet::Pendiente, $carnet->refresh()->estado);
    }

    public function test_el_recibo_lleva_el_dia_de_la_boleta_y_no_el_de_la_carga(): void
    {
        $this->assertSame('2026-11-20', ConfirmarPagoService::diaDelPago(['fecha_deposito' => '2026-11-20', 'fecha_pago' => '2026-11-22T10:00:00-04:00']));
        $this->assertSame('2026-11-22', ConfirmarPagoService::diaDelPago(['fecha_deposito' => null, 'fecha_pago' => '2026-11-22T10:00:00-04:00']));
        $this->assertSame('Banco Unión', ConfirmarPagoService::medio(['medio' => null, 'entidad_bancaria' => 'Banco Unión']));
        $this->assertSame('Pago por QR', ConfirmarPagoService::medio(['medio' => 'QR', 'simulado' => false]));
    }

    private function avisar(Carnet $carnet, string $evento, string $entrega, ?string $secreto = null, ?int $momento = null)
    {
        $liquidacion = $this->liquidaciones[$carnet->sireb_liquidacion_id];
        $cuerpo = json_encode(['id' => $entrega, 'evento' => $evento, 'ocurrido_en' => now()->toIso8601String(),
            'liquidacion' => [...$liquidacion, 'estado' => 'pagada', 'referencia_externa' => $carnet->codigo_legible]]);
        $momento ??= time();
        $firma = 'sha256='.hash_hmac('sha256', $momento.'.'.$cuerpo, $secreto ?? self::SECRETO);

        return $this->call('POST', '/api/sireb/aviso', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PAITITI_EVENTO' => $evento,
            'HTTP_X_PAITITI_ENTREGA' => $entrega,
            'HTTP_X_PAITITI_TIMESTAMP' => (string) $momento,
            'HTTP_X_PAITITI_FIRMA' => $firma,
        ], $cuerpo);
    }

    /** Lo que hace la pasarela cuando el ciudadano paga el QR. */
    private function pagarEnSireb(Carnet $carnet): void
    {
        $id = $carnet->sireb_liquidacion_id;
        $this->liquidaciones[$id]['estado'] = 'pagada';
        $this->liquidaciones[$id]['pago'] = ['tipo_pago' => 'electronico', 'monto_pagado' => '15.00', 'estado' => 'confirmado',
            'fecha_pago' => now()->toIso8601String(), 'medio' => 'QR', 'transaccion_id' => 'QR-REAL-1', 'simulado' => false];
    }

    private function carnetPendiente(): Carnet
    {
        $persona = Beneficiario::factory()->create();
        $asociacion = Asociacion::query()->create(['nombre' => 'Asociación '.Str::random(6), 'sigla' => Str::upper(Str::random(4))]);
        AprovechamientoPesq::query()->create([
            'beneficiario_id' => $persona->id, 'nro' => (int) AprovechamientoPesq::query()->max('nro') + 1, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        $carnet = app(EmitirCarnetService::class)->emitir($persona, $asociacion, TipoActor::Pescador);
        $this->assertNotNull($carnet->refresh()->sireb_liquidacion_id, 'el carnet no registró su liquidación en SIREB');

        return $carnet;
    }
}
