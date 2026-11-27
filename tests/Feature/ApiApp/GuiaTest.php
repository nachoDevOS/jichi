<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoGuia;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\User;
use App\Services\ApiApp\PedirGuiaService;
use App\Services\CuentaPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * La guía de transporte pedida desde la app (19/11/2026, REGLAS-NEGOCIO Paso 8): las
 * reglas del panel, el carnet es el suyo, el pescador va por el código de su
 * autorización y los kilos se descuentan al registrar (Paso 5).
 */
class GuiaTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private Carnet $carnet;

    private AprovechamientoPesq $autJuan;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->cuenta = $this->beneficiarioConCuenta();
        $this->carnet = $this->carnetDe($this->cuenta->beneficiario, 'comercializador');
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;

        $juan = Beneficiario::create([
            'ci' => '5544332', 'primerNombre' => 'Juan', 'segundoNombre' => 'Carlos',
            'apellidoPaterno' => 'Pérez', 'apellidoMaterno' => 'Rivero', 'fechaNacimiento' => '1980-01-01',
        ]);
        $this->autJuan = $this->autorizacionDe($juan);
        $this->autJuan->asignarCodigo();
    }

    //  Antes del formulario

    public function test_nueva_trae_carnet_productos_y_opciones_del_panel(): void
    {
        $this->api('get', '/guias/nueva')
            ->assertOk()
            ->assertJsonPath('guia.puede', true)
            ->assertJsonPath('guia.carnet.numero', $this->carnet->registro_legible)
            ->assertJsonPath('guia.productos.0.id', self::TARIFA_SURUBI)
            ->assertJsonPath('guia.productos.0.nombre', 'Surubí')
            ->assertJsonPath('guia.productos.0.precio_kg', 0.5)
            ->assertJsonCount(10, 'guia.condiciones')
            ->assertJsonCount(3, 'guia.medios')
            ->assertJsonCount(3, 'guia.tipos_transporte')
            ->assertJsonStructure(['guia' => ['vigencia']]);
    }

    public function test_sin_carnet_de_comercializador_no_puede(): void
    {
        $this->carnet->delete();

        $this->api('get', '/guias/nueva')
            ->assertJsonPath('guia.puede', false)
            ->assertJsonPath('guia.motivo', PedirGuiaService::SIN_CARNET);
    }

    public function test_busca_al_pescador_por_el_codigo_y_muestra_lo_justo(): void
    {
        $codigo = $this->autJuan->codigo_legible;

        foreach ([$codigo, mb_strtolower(str_replace('-', '', $codigo))] as $tipeado) {
            $this->api('get', '/autorizaciones/'.urlencode($tipeado))
                ->assertOk()
                ->assertJsonPath('autorizacion.numero', $this->autJuan->numeroLegible())
                ->assertJsonPath('autorizacion.pescador', 'Juan Carlos P. R.')
                ->assertJsonPath('autorizacion.kilos_disponibles', 1000)
                ->assertJsonPath('autorizacion.vendible', true)
                ->assertJsonMissingPath('autorizacion.id');
        }
    }

    //  La foto del pescador (27/11/2026)

    public function test_la_foto_del_pescador_va_por_el_codigo_y_el_token(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('beneficiarios/juan.png', $png);
        $this->autJuan->beneficiario->forceFill(['foto' => 'beneficiarios/juan.png'])->save();
        $codigo = $this->autJuan->codigo_legible;

        $this->api('get', '/autorizaciones/'.$codigo)
            ->assertJsonPath('autorizacion.tiene_foto', true)
            ->assertJsonMissingPath('autorizacion.foto_url');
        $this->withToken($this->token)
            ->get('/api/v1/portal/autorizaciones/'.$codigo.'/foto', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_sin_foto_o_con_un_codigo_que_no_existe_da_404(): void
    {
        $this->api('get', '/autorizaciones/'.$this->autJuan->codigo_legible)
            ->assertJsonPath('autorizacion.tiene_foto', false);
        $this->api('get', '/autorizaciones/'.$this->autJuan->codigo_legible.'/foto')->assertNotFound();
        $this->api('get', '/autorizaciones/AAAABBBBCCCCDDDD/foto')->assertNotFound();
    }

    public function test_la_foto_solo_con_carnet_de_comercializador_y_token(): void
    {
        $this->getJson('/api/v1/portal/autorizaciones/'.$this->autJuan->codigo_legible.'/foto')
            ->assertUnauthorized();

        $this->carnet->delete();

        $this->api('get', '/autorizaciones/'.$this->autJuan->codigo_legible.'/foto')
            ->assertForbidden()
            ->assertJsonPath('message', PedirGuiaService::SIN_CARNET);
    }

    public function test_un_codigo_que_no_existe_da_404(): void
    {
        $this->api('get', '/autorizaciones/AAAABBBBCCCCDDDD')
            ->assertNotFound()
            ->assertJsonPath('message', PedirGuiaService::SIN_AUTORIZACION);
    }

    public function test_una_autorizacion_sin_pagar_se_ve_pero_no_es_vendible(): void
    {
        $pendiente = $this->autorizacionDe(Beneficiario::factory()->create(), estado: 'pendiente');
        $pendiente->asignarCodigo();

        $this->api('get', '/autorizaciones/'.$pendiente->codigo_legible)
            ->assertOk()
            ->assertJsonPath('autorizacion.vendible', false)
            ->assertJsonPath('autorizacion.motivo', fn (?string $m) => $m !== null && $m !== '');
    }

    //  Pedirla

    public function test_pedirla_la_registra_pendiente_y_descuenta_los_kilos_del_pescador(): void
    {
        $respuesta = $this->api('post', '/guias', $this->guia(300))
            ->assertCreated()
            ->assertJsonPath('guia.clase', 'guia')
            ->assertJsonPath('guia.en_tramite', true)
            ->assertJsonPath('guia.pago', 'sin_pago')
            ->assertJsonPath('guia.debe', 150)
            ->assertJsonPath('guia.kilos', 300)
            ->assertJsonPath('guia.autorizacion_numero', $this->autJuan->numeroLegible());

        $guia = GuiaMovimiento::query()->sole();
        $this->assertSame($this->carnet->id, $guia->carnet_id);
        $this->assertSame($this->autJuan->id, $guia->aprovechamiento_id);
        $this->assertSame(EstadoGuia::Pendiente, $guia->estado);
        $this->assertFalse((bool) $guia->es_piscicultura);
        $this->assertNotNull($guia->sireb_liquidacion_id, 'la liquidación se registró en SIREB');
        $respuesta->assertJsonPath('guia.codigo_pago', $guia->sireb_codigo_publico);
        // Paso 5: se descuenta al registrar, aunque todavía no esté pagada.
        $this->assertSame(700.0, (float) $this->autJuan->refresh()->kilos_disponibles);
    }

    public function test_ignora_carnet_autorizacion_y_piscicultura_que_vengan_de_afuera(): void
    {
        $otroCarnet = $this->carnetDe(Beneficiario::factory()->create(), 'comercializador');
        $otraAut = $this->autorizacionDe(Beneficiario::factory()->create());

        $this->api('post', '/guias', [
            ...$this->guia(100),
            'carnet_id' => $otroCarnet->id,
            'aprovechamiento_id' => $otraAut->id,
            'es_piscicultura' => true,
        ])->assertCreated();

        $guia = GuiaMovimiento::query()->sole();
        $this->assertSame($this->carnet->id, $guia->carnet_id);
        $this->assertSame($this->autJuan->id, $guia->aprovechamiento_id);
        $this->assertFalse((bool) $guia->es_piscicultura);
        $this->assertSame(1000.0, (float) $otraAut->refresh()->kilos_disponibles);
    }

    public function test_exige_ruta_transporte_detalle_y_codigo_con_los_mensajes_de_ventanilla(): void
    {
        $this->api('post', '/guias', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'origen', 'origen_departamento', 'destino', 'destino_distrito',
                'medio_transporte', 'tipo_transporte', 'transporte_placa', 'transporte_capacidad_kg',
                'detalles', 'autorizacion_codigo',
            ])
            ->assertJsonPath('errors.origen.0', 'Indique desde dónde sale la carga.')
            ->assertJsonMissingValidationErrors(['es_piscicultura', 'aprovechamiento_id', 'carnet_id']);
    }

    public function test_un_codigo_equivocado_no_registra_ni_descuenta(): void
    {
        $this->api('post', '/guias', [...$this->guia(100), 'autorizacion_codigo' => 'AAAABBBBCCCCDDDD'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.guia.0', PedirGuiaService::SIN_AUTORIZACION);

        $this->assertSame(0, GuiaMovimiento::query()->count());
        $this->assertSame(1000.0, (float) $this->autJuan->refresh()->kilos_disponibles);
    }

    public function test_si_no_alcanzan_los_kilos_del_pescador_se_frena(): void
    {
        $this->api('post', '/guias', $this->guia(1200))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guia.0', fn (string $m) => str_contains($m, 'disponibles'));

        $this->assertSame(0, GuiaMovimiento::query()->count());
        $this->assertSame(1000.0, (float) $this->autJuan->refresh()->kilos_disponibles);
    }

    /** @return array<string, array{0: string}> */
    public static function autorizacionesNoVendibles(): array
    {
        return ['pendiente de pago' => ['pendiente'], 'revocada' => ['revocado']];
    }

    #[DataProvider('autorizacionesNoVendibles')]
    public function test_las_reglas_del_panel_frenan_igual(string $estado): void
    {
        $this->autJuan->update(['estado' => $estado]);

        $this->api('post', '/guias', $this->guia(100))->assertUnprocessable();

        $this->assertSame(0, GuiaMovimiento::query()->count());
    }

    public function test_despues_aparece_en_curso_listo_para_pagar(): void
    {
        $this->api('post', '/guias', $this->guia(100))->assertCreated();

        $this->api('get', '/en-curso')->assertJsonPath('tramites.0.clase', 'guia')
            ->assertJsonPath('tramites.0.debe', 50)
            ->assertJsonPath('tramites.0.puede_pagar_qr', true);
    }

    public function test_con_la_clave_temporal_no_se_pide(): void
    {
        app(CuentaPortalService::class)->resetear($this->cuenta->beneficiario);
        $this->token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;

        $this->api('get', '/guias/nueva')->assertForbidden();
        $this->api('get', '/autorizaciones/'.$this->autJuan->codigo_legible)->assertForbidden();
        $this->api('post', '/guias', $this->guia(100))->assertForbidden();
        $this->assertSame(1000.0, (float) $this->autJuan->refresh()->kilos_disponibles);
    }

    /** @return array<string, mixed> */
    public function test_repetida_con_la_misma_clave_descuenta_los_kilos_una_sola_vez(): void
    {
        $clave = ['Idempotency-Key' => (string) Str::uuid()];

        foreach ([1, 2] as $_) {
            $this->app['auth']->forgetGuards();
            $this->withToken($this->token)->postJson('/api/v1/portal/guias', $this->guia(100), $clave)->assertCreated();
        }

        $this->assertSame(1, GuiaMovimiento::query()->count());
        $this->assertSame(900.0, (float) $this->autJuan->refresh()->kilos_disponibles, 'al pescador le quedan 900, no 800');
    }

    private function guia(float $kilos): array
    {
        return [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->autJuan->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
        ];
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
