<?php

namespace Tests\Feature\ApiApp;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoFaena;
use App\Jobs\VerificarPagoJob;
use App\Models\ArancelSireb;
use App\Models\Beneficiario;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\ApiApp\PedirFaenaService;
use App\Services\CuentaPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * El permiso de faena pedido desde la app (19/11/2026, REGLAS-NEGOCIO Paso 8): las
 * mismas reglas que ventanilla, el carnet es el suyo y queda pendiente de pago.
 */
class FaenaTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private Beneficiario $ignacio;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->cuenta = $this->beneficiarioConCuenta();
        $this->ignacio = $this->cuenta->beneficiario;
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    //  Antes del formulario

    public function test_nueva_dice_lo_que_va_a_pedir(): void
    {
        $carnet = $this->pescadorListo($this->ignacio, kilos: 750);

        $this->api('get', '/faenas/nueva')
            ->assertOk()
            ->assertJsonPath('faena.puede', true)
            ->assertJsonPath('faena.motivo', null)
            ->assertJsonPath('faena.kilos', 750)
            ->assertJsonPath('faena.arancel.monto', 15)
            ->assertJsonPath('faena.carnet.numero', $carnet->registro_legible)
            ->assertJsonPath('faena.propietario_sugerido', 'Ignacio Molina Guzman')
            ->assertJsonStructure(['faena' => ['vigencia']]);
    }

    public function test_sin_carnet_de_pescador_no_puede(): void
    {
        $this->carnetDe($this->ignacio, 'comercializador');

        $this->api('get', '/faenas/nueva')
            ->assertOk()
            ->assertJsonPath('faena.puede', false)
            ->assertJsonPath('faena.motivo', PedirFaenaService::SIN_CARNET);
    }

    public function test_sin_precio_de_sireb_no_puede_y_no_muestra_detalles_internos(): void
    {
        $this->pescadorListo($this->ignacio);
        ArancelSireb::query()->where('concepto', ConceptoArancel::Faena->value)->delete();

        $this->api('get', '/faenas/nueva')
            ->assertJsonPath('faena.puede', false)
            ->assertJsonPath('faena.motivo', PedirFaenaService::SIN_PRECIO);
    }

    //  Pedirla

    public function test_pedirla_la_registra_pendiente_con_su_codigo_de_pago(): void
    {
        $carnet = $this->pescadorListo($this->ignacio);

        $respuesta = $this->api('post', '/faenas', self::TALONARIO)
            ->assertCreated()
            ->assertJsonPath('faena.clase', 'faena')
            ->assertJsonPath('faena.en_tramite', true)
            ->assertJsonPath('faena.pago', 'sin_pago')
            ->assertJsonPath('faena.debe', 15)
            ->assertJsonPath('faena.embarcacion', 'Canoa San José')
            ->assertJsonMissingPath('faena.vista_previa');

        $faena = PermisoFaena::query()->sole();
        $this->assertSame($carnet->id, $faena->carnet_id);
        $this->assertSame(EstadoFaena::Pendiente, $faena->estado);
        $this->assertSame(1000.0, (float) $faena->kilos_extraidos);
        $this->assertSame('MN-4585', $faena->matricula_naval);
        $this->assertNotNull($faena->sireb_liquidacion_id, 'la liquidación se registró en SIREB');
        $respuesta->assertJsonPath('faena.codigo_pago', $faena->sireb_codigo_publico);
        // La faena no descuenta kilos (Paso 4).
        $this->assertSame(1000.0, (float) $carnet->aprovechamiento->refresh()->kilos_disponibles);
    }

    public function test_despues_aparece_en_curso_listo_para_pagar(): void
    {
        $this->pescadorListo($this->ignacio);
        $this->api('post', '/faenas', self::TALONARIO)->assertCreated();

        $this->api('get', '/en-curso')->assertJsonCount(1, 'tramites')->assertJsonPath('tramites.0.clase', 'faena')
            ->assertJsonPath('tramites.0.debe', 15)
            ->assertJsonPath('tramites.0.puede_pagar_qr', true);
    }

    public function test_con_una_faena_sin_pagar_no_pide_otra_hasta_pagarla(): void
    {
        $this->pescadorListo($this->ignacio);
        $this->api('post', '/faenas', self::TALONARIO)->assertCreated();
        $primera = PermisoFaena::query()->sole();

        // La tarjeta de la app ya lo dice, con el número de la que falta pagar.
        $this->api('get', '/faenas/nueva')
            ->assertOk()
            ->assertJsonPath('faena.puede', false)
            ->assertJsonPath('faena.motivo', "Tiene el permiso de faena N° {$primera->numero_legible} sin pagar. Páguelo primero; después puede pedir otro.");

        // Y si igual la piden, no se registra otra.
        $respuesta = $this->api('post', '/faenas', [...self::TALONARIO, 'embarcacion' => 'Otra canoa'])->assertUnprocessable();
        $this->assertStringContainsString('sin pagar', $respuesta->json('errors.faena.0'));
        $this->assertSame(1, PermisoFaena::query()->count());

        // Pagada (aprobada), vuelve a poder.
        $primera->update(['estado' => EstadoFaena::Aprobado]);
        $this->api('get', '/faenas/nueva')->assertJsonPath('faena.puede', true);
    }

    public function test_exige_los_siete_renglones_con_los_mensajes_de_ventanilla(): void
    {
        $this->pescadorListo($this->ignacio);

        $this->api('post', '/faenas', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys(self::TALONARIO))
            ->assertJsonPath('errors.embarcacion.0', 'Escriba la embarcación.');
        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_el_carnet_es_siempre_el_suyo_aunque_manden_otro(): void
    {
        $mio = $this->pescadorListo($this->ignacio);
        $ajeno = $this->pescadorListo(Beneficiario::factory()->create());

        $this->api('post', '/faenas', [...self::TALONARIO, 'carnet_id' => $ajeno->id])->assertCreated();

        $this->assertSame($mio->id, PermisoFaena::query()->sole()->carnet_id);
    }

    /** @return array<string, array{0: string}> */
    public static function autorizacionesQueNoHabilitan(): array
    {
        return [
            'pendiente de pago' => ['pendiente'],
            'revocada' => ['revocado'],
        ];
    }

    #[DataProvider('autorizacionesQueNoHabilitan')]
    public function test_las_reglas_del_panel_frenan_igual(string $estado): void
    {
        $this->carnetDe($this->ignacio, 'pescador', $this->autorizacionDe($this->ignacio, estado: $estado));

        $respuesta = $this->api('post', '/faenas', self::TALONARIO)->assertUnprocessable();

        $this->assertNotEmpty($respuesta->json('errors.faena.0'));
        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_un_comercializador_no_pide_faenas(): void
    {
        $this->carnetDe($this->ignacio, 'comercializador');

        $this->api('post', '/faenas', self::TALONARIO)
            ->assertUnprocessable()
            ->assertJsonPath('errors.faena.0', PedirFaenaService::SIN_CARNET);
    }

    public function test_con_la_clave_temporal_no_se_pide(): void
    {
        $this->pescadorListo($this->ignacio);
        app(CuentaPortalService::class)->resetear($this->ignacio);
        $this->token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;

        $this->api('get', '/faenas/nueva')->assertForbidden()->assertJsonPath('codigo', 'clave_temporal');
        $this->api('post', '/faenas', self::TALONARIO)->assertForbidden();
        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_sin_token_no_se_pide(): void
    {
        $this->postJson('/api/v1/portal/faenas', self::TALONARIO)->assertUnauthorized();
    }

    /** @param  array<string, mixed>  $datos */
    private function api(string $metodo, string $ruta, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        // Como la app: cada pedido que cobra lleva su propia Idempotency-Key.
        $encabezados = strtoupper($metodo) === 'POST' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($this->token)->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
