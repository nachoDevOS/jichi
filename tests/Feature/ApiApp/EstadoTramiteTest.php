<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoFaena;
use App\Enums\OrigenTramite;
use App\Jobs\VerificarPagoJob;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\CuentaPortalService;
use App\Services\EmitirFaenaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * El estado de un trámite después de pagarlo (opción B, 25/11/2026): la app paga el QR
 * directo en SIREB y pregunta acá. Se aprueba solo si SIREB dice «pagada».
 */
class EstadoTramiteTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->cuenta = $this->beneficiarioConCuenta();
        $this->pescadorListo($this->cuenta->beneficiario);
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_sin_pagar_sigue_pendiente(): void
    {
        $codigo = $this->pedirFaena();

        $this->estado($codigo)
            ->assertOk()
            ->assertJsonPath('pagado', false)
            ->assertJsonPath('tramite.clase', 'faena')
            ->assertJsonPath('tramite.en_tramite', true)
            ->assertJsonPath('tramite.pago', 'sin_pago');
        $this->assertSame(EstadoFaena::Pendiente, PermisoFaena::query()->sole()->estado);
    }

    public function test_pagada_en_sireb_queda_aprobada_con_su_recibo(): void
    {
        $codigo = $this->pedirFaena();
        $this->pagarEnSireb(PermisoFaena::query()->sole()->sireb_liquidacion_id);

        $this->estado($codigo)
            ->assertOk()
            ->assertJsonPath('pagado', true)
            ->assertJsonPath('tramite.en_tramite', false)
            ->assertJsonPath('tramite.vigente', true);

        $faena = PermisoFaena::query()->sole();
        $this->assertSame(EstadoFaena::Aprobado, $faena->estado);
        $this->assertTrue($faena->recibo()->exists(), 'el recibo sale al aprobarse');
    }

    public function test_ya_aprobada_dice_pagado_sin_volver_a_consultar(): void
    {
        $codigo = $this->pedirFaena();
        $this->pagarEnSireb(PermisoFaena::query()->sole()->sireb_liquidacion_id);
        $this->estado($codigo)->assertJsonPath('pagado', true);

        $this->estado($codigo)->assertOk()->assertJsonPath('pagado', true);
    }

    public function test_lo_ajeno_y_lo_inexistente_dan_el_mismo_404(): void
    {
        $otra = $this->beneficiarioConCuenta('1111111', 'Otro');
        $faenaAjena = app(EmitirFaenaService::class)->emitir($this->pescadorListo($otra->beneficiario), OrigenTramite::App, self::TALONARIO);

        $ajeno = $this->estado($faenaAjena->codigo_legible)->assertNotFound();
        $inexistente = $this->estado('ZZZZ-ZZZZ-ZZZZ-ZZZZ')->assertNotFound();

        $this->assertSame($inexistente->json(), $ajeno->json());
    }

    public function test_sin_token_o_con_clave_temporal_no_se_consulta(): void
    {
        $codigo = $this->pedirFaena();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson("/api/v1/portal/tramites/{$codigo}/estado")->assertUnauthorized();

        app(CuentaPortalService::class)->resetear($this->cuenta->beneficiario);
        $this->token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;
        $this->estado($codigo)->assertForbidden()->assertJsonPath('codigo', 'clave_temporal');
    }

    /** Pide la faena como la app y devuelve su código de verificación. */
    private function pedirFaena(): string
    {
        return $this->withToken($this->token)
            ->postJson('/api/v1/portal/faenas', self::TALONARIO, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()
            ->json('faena.codigo');
    }

    private function estado(string $codigo): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->getJson("/api/v1/portal/tramites/{$codigo}/estado");
    }

    /** Lo que hace el titular con la app de su banco: SIREB deja la liquidación pagada. */
    private function pagarEnSireb(string $liquidacionId): void
    {
        $this->liquidaciones[$liquidacionId]['estado'] = 'pagada';
        $this->liquidaciones[$liquidacionId]['pago'] = [
            'tipo_pago' => 'electronico', 'monto_pagado' => $this->liquidaciones[$liquidacionId]['monto'],
            'estado' => 'confirmado', 'fecha_pago' => now()->toIso8601String(), 'numero_boleta' => null,
            'entidad_bancaria' => null, 'fecha_deposito' => null, 'fecha_validacion' => now()->toIso8601String(),
            'medio' => 'QR', 'transaccion_id' => 'QR-'.Str::upper(Str::random(10)), 'simulado' => false,
        ];
    }
}
