<?php

namespace Tests\Feature\ApiApp;

use App\Http\Middleware\ApiApp\SoloBeneficiarioApp;
use App\Jobs\VerificarPagoJob;
use App\Models\PermisoFaena;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * La seguridad obligatoria de la API (docs/api/seguridad.md § 3, 25/11/2026):
 * Idempotency-Key en lo que cobra, tokens que vencen y tope general por cuenta.
 */
class SeguridadTest extends TestCase
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

    //  Idempotency-Key

    public function test_sin_idempotency_key_no_se_registra(): void
    {
        $this->pedirFaena(null)
            ->assertStatus(400)
            ->assertJsonPath('codigo', 'idempotency_key_requerida');

        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_la_misma_clave_dos_veces_registra_una_sola_faena(): void
    {
        $clave = (string) Str::uuid();

        $primera = $this->pedirFaena($clave)->assertCreated();
        $segunda = $this->pedirFaena($clave)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($primera->json('faena.codigo'), $segunda->json('faena.codigo'));
        $this->assertSame(1, PermisoFaena::query()->count(), 'un solo trámite y un solo cobro en SIREB');
        $this->assertCount(1, $this->liquidaciones);
    }

    public function test_la_misma_clave_con_otros_datos_se_rechaza(): void
    {
        $clave = (string) Str::uuid();
        $this->pedirFaena($clave)->assertCreated();

        $this->pedirFaena($clave, ['embarcacion' => 'Otra canoa'])
            ->assertUnprocessable()
            ->assertJsonPath('codigo', 'idempotency_key_reusada');
        $this->assertSame(1, PermisoFaena::query()->count());
    }

    public function test_un_error_no_se_guarda_y_se_reintenta_con_la_misma_clave(): void
    {
        $clave = (string) Str::uuid();

        $this->api('post', '/faenas', [], ['Idempotency-Key' => $clave])->assertUnprocessable();
        $this->pedirFaena($clave)->assertCreated();

        $this->assertSame(1, PermisoFaena::query()->count());
    }

    public function test_la_clave_de_otra_cuenta_es_otro_pedido(): void
    {
        $clave = (string) Str::uuid();
        $this->pedirFaena($clave)->assertCreated();

        $otra = $this->beneficiarioConCuenta('1111111', 'Otro');
        $this->pescadorListo($otra->beneficiario);
        $this->token = $otra->createToken('prueba')->plainTextToken;
        $this->pedirFaena($clave)->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, PermisoFaena::query()->count());
    }

    //  Tokens que vencen

    public function test_el_token_del_ingreso_vence_a_los_90_dias(): void
    {
        $this->postJson('/api/v1/portal/sesion', ['ci' => '7633685', 'password' => 'pesca2026'])->assertCreated();

        $token = PersonalAccessToken::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(now()->addDays(SoloBeneficiarioApp::DIAS_TOKEN)->timestamp, $token->expires_at->timestamp, 60);
    }

    public function test_un_token_vencido_no_entra(): void
    {
        $this->tokenActual()->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->api('get', '/yo')->assertUnauthorized();
    }

    public function test_usar_la_app_renueva_el_vencimiento(): void
    {
        $this->tokenActual()->forceFill(['expires_at' => now()->addDays(10)])->save();

        $this->api('get', '/yo')->assertOk();

        $this->assertEqualsWithDelta(now()->addDays(SoloBeneficiarioApp::DIAS_TOKEN)->timestamp, $this->tokenActual()->expires_at->timestamp, 60);
    }

    public function test_un_token_viejo_sin_vencimiento_recibe_uno(): void
    {
        $this->assertNull($this->tokenActual()->expires_at);

        $this->api('get', '/yo')->assertOk();

        $this->assertNotNull($this->tokenActual()->expires_at);
    }

    //  Tope general por cuenta

    public function test_mas_de_60_pedidos_por_minuto_da_429(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->api('get', '/yo')->assertOk();
        }

        $this->api('get', '/yo')->assertTooManyRequests();
    }

    /** @param  array<string, string>  $cambios */
    private function pedirFaena(?string $clave, array $cambios = []): TestResponse
    {
        return $this->api('post', '/faenas', [...self::TALONARIO, ...$cambios], $clave === null ? [] : ['Idempotency-Key' => $clave]);
    }

    private function tokenActual(): PersonalAccessToken
    {
        return PersonalAccessToken::findToken($this->token);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $encabezados
     */
    private function api(string $metodo, string $ruta, array $datos = [], array $encabezados = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
