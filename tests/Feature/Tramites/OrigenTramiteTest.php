<?php

namespace Tests\Feature\Tramites;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoFaena;
use App\Enums\OrigenTramite;
use App\Enums\RolSistema;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\ArancelSireb;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\ApiApp\PedirFaenaService;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * Desde dónde se registró cada trámite (26/11/2026): ventanilla, portal web o app. Cada
 * prueba entra por la puerta de verdad (HTTP), porque quien decide el origen es el
 * controlador de cada entrada. Ver docs/MER.md § Origen del trámite.
 */
class OrigenTramiteTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private User $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);
        config(['inertia.ssr.enabled' => false]);
        $this->withoutVite();

        $this->cuenta = $this->beneficiarioConCuenta();

        $this->seed(RolPermisoSeeder::class);
        $this->funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $this->funcionario->assignRole(RolSistema::Administrador->value);
    }

    //  Permiso de faena: los tres caminos

    public function test_la_faena_de_ventanilla_queda_ventanilla(): void
    {
        $carnet = $this->pescadorListo($this->cuenta->beneficiario);

        $this->actingAs($this->funcionario)
            ->post(route('faenas.store'), ['carnet_id' => $carnet->id, ...self::TALONARIO])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrigenTramite::Ventanilla, PermisoFaena::query()->sole()->origen_tramite);
    }

    public function test_la_faena_del_portal_web_queda_portal_web(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), self::TALONARIO)->assertSessionHasNoErrors();

        $this->assertSame(OrigenTramite::PortalWeb, PermisoFaena::query()->sole()->origen_tramite);
    }

    public function test_la_faena_de_la_app_queda_app_y_la_app_lo_recibe(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->desdeLaApp('post', '/faenas', self::TALONARIO)
            ->assertCreated()
            ->assertJsonPath('faena.origen_tramite', 'app')
            ->assertJsonPath('faena.origen_tramite_etiqueta', 'App BeniDigital');

        $this->assertSame(OrigenTramite::App, PermisoFaena::query()->sole()->origen_tramite);
    }

    //  Guía de transporte: los tres caminos

    public function test_la_guia_de_ventanilla_queda_ventanilla(): void
    {
        $carnet = $this->carnetDe($this->cuenta->beneficiario, 'comercializador');
        $pescador = $this->autorizacionDe(Beneficiario::factory()->create());

        $this->actingAs($this->funcionario)
            ->post(route('guias.store'), [
                ...$this->guia(),
                'carnet_id' => $carnet->id,
                'es_piscicultura' => false,
                'aprovechamiento_id' => $pescador->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrigenTramite::Ventanilla, GuiaMovimiento::query()->sole()->origen_tramite);
    }

    public function test_la_guia_del_portal_web_queda_portal_web(): void
    {
        $this->carnetDe($this->cuenta->beneficiario, 'comercializador');

        $this->actingAs($this->cuenta)
            ->post(route('portal.guias.store'), [...$this->guia(), 'autorizacion_codigo' => $this->pescadorConCodigo()])
            ->assertSessionHasNoErrors();

        $guia = GuiaMovimiento::query()->sole();
        $this->assertSame(OrigenTramite::PortalWeb, $guia->origen_tramite);
        // No se pisó con el lugar de partida, que también se llama `origen`.
        $this->assertSame('Puerto Almacén', $guia->origen);
    }

    public function test_la_guia_de_la_app_queda_app(): void
    {
        $this->carnetDe($this->cuenta->beneficiario, 'comercializador');

        $this->desdeLaApp('post', '/guias', [...$this->guia(), 'autorizacion_codigo' => $this->pescadorConCodigo()])
            ->assertCreated()
            ->assertJsonPath('guia.origen_tramite', 'app');

        $this->assertSame(OrigenTramite::App, GuiaMovimiento::query()->sole()->origen_tramite);
    }

    public function test_lo_que_mande_el_formulario_no_cambia_el_origen(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->desdeLaApp('post', '/faenas', [...self::TALONARIO, 'origen_tramite' => 'ventanilla'])->assertCreated();

        $this->assertSame(OrigenTramite::App, PermisoFaena::query()->sole()->origen_tramite);
    }

    //  Autorización y carnet: hoy solo ventanilla

    public function test_la_autorizacion_de_ventanilla_queda_ventanilla(): void
    {
        ArancelSireb::query()->create(['nombre' => 'Autorización', 'concepto' => ConceptoArancel::Autorizacion->value, 'servicio_sireb' => self::SERVICIO_AUTORIZACION]);
        $persona = Beneficiario::factory()->create();

        $this->actingAs($this->funcionario)
            ->post(route('aprovechamientos.store'), ['beneficiario_id' => $persona->id, 'tarifa_sireb' => self::TARIFA_ESCALA_1000, 'tipo_embarcacion' => 'Canoa'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrigenTramite::Ventanilla, AprovechamientoPesq::query()->sole()->origen_tramite);
    }

    public function test_el_carnet_de_ventanilla_queda_ventanilla(): void
    {
        Storage::fake('public');
        $persona = Beneficiario::factory()->create();
        $autorizacion = $this->autorizacionDe($persona);

        $this->actingAs($this->funcionario)
            ->post(route('carnets.store'), [
                'beneficiario_id' => $persona->id,
                'asociacion_id' => Asociacion::query()->create(['nombre' => 'Pescadores del Mamoré', 'sigla' => 'PDM'])->id,
                'tipo_actor' => 'pescador',
                'aprovechamiento_id' => $autorizacion->id,
                'archivo_ci' => UploadedFile::fake()->image('ci.jpg'),
                'archivo_asociacion' => UploadedFile::fake()->image('aval.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrigenTramite::Ventanilla, Carnet::query()->where('beneficiario_id', $persona->id)->sole()->origen_tramite);
    }

    //  Un pedido en línea nunca se registra como de ventanilla

    public function test_un_pedido_en_linea_no_acepta_el_origen_ventanilla(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->expectException(InvalidArgumentException::class);
        app(PedirFaenaService::class)->pedir($this->cuenta->beneficiario, self::TALONARIO, OrigenTramite::Ventanilla);
    }

    //  Dónde se ve

    public function test_la_ficha_y_el_listado_del_panel_muestran_el_origen(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);
        $this->desdeLaApp('post', '/faenas', self::TALONARIO)->assertCreated();
        $faena = PermisoFaena::query()->sole();

        $this->actingAs($this->funcionario)->get(route('faenas.show', $faena))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('faena.origen_tramite', 'app')
                ->where('faena.origen_tramite_etiqueta', 'App BeniDigital'));

        $this->actingAs($this->funcionario)->get(route('faenas.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('faenas.data.0.origen_tramite_etiqueta', 'App BeniDigital'));
    }

    public function test_el_portal_web_muestra_el_origen_en_curso(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);
        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), self::TALONARIO);

        $this->actingAs($this->cuenta)->get(route('portal.en-curso'))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('tramites.0.origen_tramite_etiqueta', 'Portal web'));
    }

    public function test_el_reporte_filtra_por_origen(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);
        $this->desdeLaApp('post', '/faenas', self::TALONARIO)->assertCreated();
        // En línea, una faena a la vez (26/11/2026): la primera, ya pagada.
        PermisoFaena::query()->sole()->update(['estado' => EstadoFaena::Aprobado]);
        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), self::TALONARIO);

        $reporte = fn (array $q) => $this->actingAs($this->funcionario)
            ->get(route('reportes.index', ['servicio' => 'faena', ...$q]))->assertOk();

        $reporte([])->assertInertia(fn (AssertableInertia $p) => $p->where('totales.cantidad', 2)->has('origenes', 3));
        $reporte(['origen' => 'app'])->assertInertia(fn (AssertableInertia $p) => $p
            ->where('totales.cantidad', 1)
            ->where('filtros.origen', 'app')
            ->where('filas.data.0.origen', 'App BeniDigital'));
        $reporte(['origen' => 'ventanilla'])->assertInertia(fn (AssertableInertia $p) => $p->where('totales.cantidad', 0));
    }

    //  Auxiliares

    /** @param  array<string, mixed>  $datos */
    private function desdeLaApp(string $metodo, string $ruta, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $token = $this->cuenta->createToken('prueba')->plainTextToken;

        return $this->withToken($token)->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, ['Idempotency-Key' => (string) Str::uuid()]);
    }

    /** El código de la autorización de un pescador, el que el comercializador declara. */
    private function pescadorConCodigo(): string
    {
        $autorizacion = $this->autorizacionDe(Beneficiario::factory()->create());
        $autorizacion->asignarCodigo();

        return $autorizacion->codigo_legible;
    }

    /** @return array<string, mixed> */
    private function guia(): array
    {
        return [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Riberalta', 'destino_departamento' => 'Beni', 'destino_provincia' => 'Vaca Díez', 'destino_distrito' => 'Riberalta',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camión', 'transporte_placa' => '999-XYZ', 'transporte_capacidad_kg' => 3000,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'congelado_entero', 'cantidad_kg' => 100]],
        ];
    }
}
