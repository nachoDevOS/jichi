<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoCarnet;
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
 * El inicio de El Pescador en la app (vuelve el 25/11/2026): lo mismo que el inicio del
 * portal web (PantallaPortal::inicio), sin las URLs que piden sesión de navegador.
 */
class InicioTest extends TestCase
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

    public function test_sin_papeles_ofrece_los_tramites_de_ventanilla(): void
    {
        $this->pedir('/inicio')
            ->assertOk()
            ->assertJsonPath('inicio.nombre', 'Ignacio Molina Guzman')
            ->assertJsonPath('inicio.deuda', 0)
            ->assertJsonPath('inicio.vigentes', [])
            ->assertJsonPath('inicio.abiertos', [])
            ->assertJsonFragment(['clase' => 'aprovechamiento', 'canal' => 'ventanilla'])
            ->assertJsonFragment(['clase' => 'carnet', 'titulo' => 'Carnet de comercializador', 'canal' => 'ventanilla']);
    }

    public function test_muestra_lo_vigente_lo_abierto_y_la_deuda(): void
    {
        $carnet = $this->carnetComercializador();
        $guia = $this->guiaPendiente($carnet, 100);

        $respuesta = $this->pedir('/inicio')->assertOk();

        $respuesta
            ->assertJsonPath('inicio.deuda', 50)
            ->assertJsonPath('inicio.vigentes.0.clase', 'carnet')
            ->assertJsonPath('inicio.vigentes.0.vigente', true)
            ->assertJsonPath('inicio.abiertos.0.clase', 'guia')
            ->assertJsonPath('inicio.abiertos.0.numero', $guia->numero_legible)
            ->assertJsonPath('inicio.abiertos.0.debe', 50)
            ->assertJsonPath('inicio.abiertos.0.pago', 'sin_pago')
            ->assertJsonPath('inicio.abiertos.0.codigo_pago', $guia->refresh()->sireb_codigo_publico)
            ->assertJsonFragment(['clase' => 'guia', 'canal' => 'en_linea']);
        // Lo que pide la sesión del navegador no viaja a la app.
        foreach (['verificar', 'vista_previa', 'descargar'] as $soloWeb) {
            $respuesta->assertJsonMissingPath("inicio.abiertos.0.{$soloWeb}");
            $respuesta->assertJsonMissingPath("inicio.vigentes.0.{$soloWeb}");
        }
        // Como la web: al entrar se consultan sus pagos en SIREB.
        Bus::assertDispatchedAfterResponse(VerificarPagoJob::class);
    }

    public function test_la_autorizacion_vigente_trae_sus_kilos(): void
    {
        $autorizacion = AprovechamientoPesq::query()->create([
            'beneficiario_id' => $this->ignacio->id, 'nro' => 77, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 700, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        $this->pedir('/inicio')
            ->assertOk()
            ->assertJsonPath('inicio.vigentes.0.clase', 'aprovechamiento')
            ->assertJsonPath('inicio.vigentes.0.kilos_disponibles', 700)
            ->assertJsonPath('inicio.vigentes.0.volumen_total_kg', 1000)
            ->assertJsonPath('inicio.vigentes.0.vence_el', $autorizacion->fecha_vencimiento->toDateString());
    }

    public function test_con_la_clave_temporal_no_se_ve(): void
    {
        app(CuentaPortalService::class)->resetear($this->ignacio);
        $token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/portal/inicio')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'clave_temporal');
    }

    public function test_sin_token_no_hay_inicio(): void
    {
        $this->getJson('/api/v1/portal/inicio')->assertUnauthorized();
    }

    public function test_solo_ve_lo_suyo(): void
    {
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Comercializador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $this->guiaPendiente($this->carnetComercializador($otro), 100);

        $this->pedir('/inicio')
            ->assertOk()
            ->assertJsonPath('inicio.deuda', 0)
            ->assertJsonPath('inicio.abiertos', []);
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
            'beneficiario_id' => $pescador->id, 'nro' => ++$nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        return app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'La Paz', 'es_piscicultura' => false, 'aprovechamiento_id' => $autorizacion->id],
            [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
        );
    }
}
