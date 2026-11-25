<?php

namespace Tests\Feature\ApiApp;

use App\Models\Beneficiario;
use App\Models\User;
use App\Services\CuentaPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La app BeniDigital entra con la MISMA cuenta que da ventanilla para /mi-cuenta
 * («Dar acceso al portal»): C.I. + clave temporal, token en vez de sesión.
 */
class SesionTest extends TestCase
{
    use RefreshDatabase;

    private const ERROR = 'La cédula o la contraseña no son correctas, o su acceso no está habilitado.';

    private Beneficiario $ignacio;

    private string $claveTemporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ignacio = Beneficiario::create([
            'ci' => '7633685',
            'primerNombre' => 'Ignacio',
            'apellidoPaterno' => 'Molina',
            'apellidoMaterno' => 'Guzman',
            'fechaNacimiento' => '1997-03-08',
        ]);
        $this->claveTemporal = app(CuentaPortalService::class)->darAcceso($this->ignacio);
    }

    public function test_entra_con_la_clave_temporal_de_ventanilla_y_recibe_un_token(): void
    {
        $respuesta = $this->ingresar($this->claveTemporal)
            ->assertCreated()
            ->assertJsonPath('cuenta.nombre', 'Ignacio Molina Guzman')
            ->assertJsonPath('cuenta.debe_cambiar_password', true);

        $this->assertIsString($respuesta->json('token'));
        $cuenta = $this->cuenta();
        $this->assertSame(1, $cuenta->tokens()->count());
        $this->assertSame('Android · Pixel 7', $cuenta->tokens()->value('name'));
        // El panel lo muestra en «Último ingreso», igual que si entrara por la web.
        $this->assertNotNull($cuenta->ultimo_acceso_at);
        $this->assertDatabaseHas('accesos', ['user_id' => $cuenta->id, 'evento' => 'login']);
    }

    public function test_acepta_la_ci_con_puntos_o_con_expedido_como_la_web(): void
    {
        $this->ingresar($this->claveTemporal, '7.633.685 BN')->assertCreated();
    }

    public function test_clave_erronea_y_cedula_inexistente_dan_el_mismo_mensaje(): void
    {
        $this->ingresar('OTRACLAVE')->assertUnprocessable()->assertJsonPath('errors.ci.0', self::ERROR);
        $this->ingresar('OTRACLAVE', '9999999')->assertUnprocessable()->assertJsonPath('errors.ci.0', self::ERROR);

        $this->assertDatabaseHas('accesos', ['evento' => 'fallido']);
    }

    public function test_al_sexto_intento_fallido_bloquea_aunque_la_clave_sea_buena(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->ingresar('OTRACLAVE')->assertUnprocessable();
        }

        $this->ingresar($this->claveTemporal)
            ->assertUnprocessable()
            ->assertJsonPath('errors.ci.0', fn (string $m) => str_starts_with($m, 'Demasiados intentos'));
    }

    public function test_con_la_clave_temporal_solo_puede_verse_cambiarla_o_salir(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'beneficiario.app'])
            ->get('/api/v1/portal/prueba', fn () => 'ok')->name('api.portal.prueba');
        $token = $this->ingresar($this->claveTemporal)->json('token');

        $this->withToken($token)->getJson('/api/v1/portal/yo')->assertOk();
        $this->withToken($token)->getJson('/api/v1/portal/prueba')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'clave_temporal');
    }

    public function test_cambia_la_clave_y_los_otros_celulares_salen(): void
    {
        $otroCelular = $this->ingresar($this->claveTemporal)->json('token');
        $token = $this->ingresar($this->claveTemporal)->json('token');

        $this->withToken($token)->putJson('/api/v1/portal/clave', [
            'actual' => $this->claveTemporal,
            'password' => 'pesca2026',
            'password_confirmation' => 'pesca2026',
        ])->assertOk()->assertJsonPath('cuenta.debe_cambiar_password', false);

        $this->olvidarSesion();
        $this->withToken($token)->getJson('/api/v1/portal/yo')->assertOk();
        $this->olvidarSesion();
        $this->withToken($otroCelular)->getJson('/api/v1/portal/yo')->assertUnauthorized();

        // La nueva vale también en la web: es la misma cuenta.
        $this->ingresar($this->claveTemporal)->assertUnprocessable();
        $this->ingresar('pesca2026')->assertCreated();
    }

    public function test_la_clave_nueva_sigue_las_reglas_de_la_web(): void
    {
        $token = $this->ingresar($this->claveTemporal)->json('token');

        $this->withToken($token)->putJson('/api/v1/portal/clave', [
            'actual' => 'NOESLAACTUAL1',
            'password' => 'solo-letras',
            'password_confirmation' => 'solo-letras',
        ])->assertUnprocessable()->assertJsonValidationErrors(['actual', 'password']);

        $this->assertTrue($this->cuenta()->debe_cambiar_password);
    }

    public function test_salir_borra_el_token_de_ese_celular(): void
    {
        $token = $this->ingresar($this->claveTemporal)->json('token');

        $this->withToken($token)->deleteJson('/api/v1/portal/sesion')->assertNoContent();

        $this->assertSame(0, $this->cuenta()->tokens()->count());
        $this->olvidarSesion();
        $this->withToken($token)->getJson('/api/v1/portal/yo')->assertUnauthorized();
    }

    public function test_resetear_en_ventanilla_saca_a_la_app(): void
    {
        $token = $this->ingresar($this->claveTemporal)->json('token');

        $nueva = app(CuentaPortalService::class)->resetear($this->ignacio);

        $this->withToken($token)->getJson('/api/v1/portal/yo')->assertUnauthorized();
        $this->ingresar($nueva)->assertCreated()->assertJsonPath('cuenta.debe_cambiar_password', true);
    }

    public function test_desactivar_en_ventanilla_saca_a_la_app_y_no_deja_entrar(): void
    {
        $token = $this->ingresar($this->claveTemporal)->json('token');

        app(CuentaPortalService::class)->desactivar($this->ignacio);

        $this->withToken($token)->getJson('/api/v1/portal/yo')->assertUnauthorized();
        $this->ingresar($this->claveTemporal)->assertUnprocessable()->assertJsonPath('errors.ci.0', self::ERROR);
    }

    public function test_un_funcionario_no_pasa_aunque_tenga_token(): void
    {
        $funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $token = $funcionario->createToken('prueba')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/portal/yo')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'no_beneficiario');
    }

    public function test_el_portal_web_sigue_entrando_con_sesion(): void
    {
        $this->post(route('portal.ingresar.store'), ['ci' => '7633685', 'password' => $this->claveTemporal])
            ->assertRedirect(route('portal.inicio'));

        $this->assertAuthenticatedAs($this->cuenta());
        $this->assertSame(0, $this->cuenta()->tokens()->count());
    }

    private function ingresar(string $clave, string $ci = '7633685'): TestResponse
    {
        $this->olvidarSesion();

        return $this->postJson('/api/v1/portal/sesion', [
            'ci' => $ci,
            'password' => $clave,
            'dispositivo' => 'Android · Pixel 7',
        ]);
    }

    /** Cada pedido del celular llega solo con su token: sin el usuario del pedido anterior en memoria. */
    private function olvidarSesion(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function cuenta(): User
    {
        return $this->ignacio->cuenta()->firstOrFail();
    }
}
