<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoCarnet;
use App\Enums\OrigenTramite;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\User;
use App\Services\CuentaPortalService;
use App\Services\EmitirGuiaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «En curso» desde la app: lo pendiente de pago, con el código para pedir su QR. Son
 * los mismos datos que el portal web (PantallaPortal), sin las URLs que piden sesión.
 */
class EnCursoTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private Beneficiario $ignacio;

    private User $cuenta;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->ignacio = Beneficiario::create([
            'ci' => '7633685',
            'primerNombre' => 'Ignacio',
            'apellidoPaterno' => 'Molina',
            'apellidoMaterno' => 'Guzman',
            'fechaNacimiento' => '1997-03-08',
        ]);
        $servicio = app(CuentaPortalService::class);
        $servicio->darAcceso($this->ignacio);
        $this->cuenta = $this->ignacio->cuenta()->firstOrFail();
        $servicio->cambiarClave($this->cuenta, 'pesca2026');
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_en_curso_trae_lo_pendiente_con_su_codigo_de_pago(): void
    {
        $guia = $this->guiaPendiente($this->carnetComercializador(), 40);

        $this->pedir('/en-curso')
            ->assertOk()
            ->assertJsonCount(1, 'tramites')
            ->assertJsonPath('tramites.0.clase', 'guia')
            ->assertJsonPath('tramites.0.codigo_pago', $guia->refresh()->sireb_codigo_publico)
            ->assertJsonPath('tramites.0.siguiente_paso', fn (string $paso) => str_starts_with($paso, 'Falta pagar'))
            ->assertJsonPath('tramites.0.codigo', $guia->codigo_legible);
    }

    public function test_no_viaja_lo_que_pide_sesion_del_navegador_y_consulta_sus_pagos(): void
    {
        $this->guiaPendiente($this->carnetComercializador(), 40);

        $respuesta = $this->pedir('/en-curso')->assertOk();

        foreach (['verificar', 'vista_previa', 'descargar'] as $soloWeb) {
            $respuesta->assertJsonMissingPath("tramites.0.{$soloWeb}");
        }
        // Como la web: al entrar se consultan sus pagos en SIREB.
        Bus::assertDispatchedAfterResponse(VerificarPagoJob::class);
    }

    public function test_con_la_clave_temporal_no_se_ve_nada(): void
    {
        app(CuentaPortalService::class)->resetear($this->ignacio);
        $token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;

        foreach (['/en-curso', '/faenas/nueva', '/guias/nueva'] as $ruta) {
            $this->withToken($token)->getJson('/api/v1/portal'.$ruta)
                ->assertForbidden()
                ->assertJsonPath('codigo', 'clave_temporal');
        }
    }

    public function test_sin_token_no_hay_nada(): void
    {
        foreach (['/yo', '/en-curso', '/faenas/nueva', '/guias/nueva'] as $ruta) {
            $this->getJson('/api/v1/portal'.$ruta)->assertUnauthorized();
        }
    }

    public function test_solo_ve_lo_suyo(): void
    {
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Comercializador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $this->guiaPendiente($this->carnetComercializador($otro), 100);

        $this->pedir('/en-curso')->assertOk()->assertJsonPath('tramites', []);
    }

    public function test_fuera_del_alcance_de_la_app_no_hay_ruta(): void
    {
        // Alcance del 25/11/2026: inicio, pedir faena y guía, pagar por QR, «Mi perfil» y «Mis pagos».
        // «Mis papeles» sigue solo en la web.
        foreach (['/papeles'] as $ruta) {
            $this->pedir($ruta)->assertNotFound();
        }
    }

    private function pedir(string $ruta): TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/portal'.$ruta);
    }

    private function carnetComercializador(?Beneficiario $de = null): Carnet
    {
        static $nro = 0;
        $asociacion = Asociacion::query()->firstOrCreate(['nombre' => 'Asociación de prueba'], ['sigla' => 'ADP']);

        return Carnet::query()->create([
            'origen_tramite' => OrigenTramite::Ventanilla,
            'beneficiario_id' => ($de ?? $this->ignacio)->id, 'asociacion_id' => $asociacion->id,
            'tipo_actor' => 'comercializador', 'monto' => 15, 'estado' => EstadoCarnet::Aprobado, 'nro' => ++$nro,
            'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** Una guía registrada como en ventanilla: queda pendiente con su liquidación en SIREB. */
    private function guiaPendiente(Carnet $carnet, float $kilos): GuiaMovimiento
    {
        static $nro = 0;
        $pescador = Beneficiario::factory()->create();
        $autorizacion = AprovechamientoPesq::query()->create([
            'origen_tramite' => OrigenTramite::Ventanilla,
            'beneficiario_id' => $pescador->id, 'nro' => ++$nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        return app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'La Paz', 'es_piscicultura' => false, 'aprovechamiento_id' => $autorizacion->id],
            [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
            OrigenTramite::Ventanilla,
        );
    }
}
