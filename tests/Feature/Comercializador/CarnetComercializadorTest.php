<?php

namespace Tests\Feature\Comercializador;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoGuia;
use App\Enums\ModalidadAprovechamiento;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CategoriaAprovechamiento;
use App\Models\GuiaMovimiento;
use App\Models\ProductoHidrobiologico;
use App\Models\TipoCarnet;
use App\Models\User;
use App\Services\CobrarService;
use App\Services\ControlarPagoService;
use App\Services\EmitirCarnetService;
use App\Services\EmitirGuiaService;
use App\Services\RevisarCarnetService;
use App\Services\RevisarGuiaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El carnet de comercializador: sin autorización de pesca, uno vigente por
 * actividad, y recién aprobado emite guías. Ver docs/REGLAS-NEGOCIO.md, paso 3.
 */
class CarnetComercializadorTest extends TestCase
{
    use RefreshDatabase;

    private Beneficiario $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, ConfiguracionSeeder::class, UsuarioSeeder::class, CatalogoSeeder::class]);
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());
        $this->persona = Beneficiario::factory()->create();
    }

    #[Test]
    public function se_emite_sin_autorizacion_de_pesca_y_nace_pendiente(): void
    {
        $carnet = $this->emitir();

        $this->assertSame(TipoActor::Comercializador, $carnet->tipo_actor);
        $this->assertNull($carnet->aprovechamiento_id);
        $this->assertSame(EstadoCarnet::Pendiente, $carnet->estado);
        $this->assertNotNull($carnet->codigo_legible, 'lleva código de verificación');
    }

    #[Test]
    public function el_comercializador_nunca_lleva_autorizacion_de_pesca(): void
    {
        $cupo = AprovechamientoPesq::query()->create([
            'beneficiario_id' => $this->persona->id,
            'categoria_aprov_id' => CategoriaAprovechamiento::query()->firstOrFail()->id,
            'modalidad' => ModalidadAprovechamiento::EscalaGeneral,
            'volumen_total_kg' => 100,
            'tipo_embarcacion' => 'Canoa',
            'estado' => EstadoAprovechamiento::Aprobado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        $this->expectException(CarnetInvalidoException::class);
        $this->emitir(cupo: $cupo);
    }

    #[Test]
    public function un_tipo_de_carnet_de_pescador_no_sirve_para_comercializador(): void
    {
        $this->expectException(CarnetInvalidoException::class);
        app(EmitirCarnetService::class)->emitir(
            $this->persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail(),
            TipoActor::Comercializador,
        );
    }

    #[Test]
    public function no_se_emite_un_segundo_carnet_vigente_de_la_misma_actividad(): void
    {
        $this->aprobar($this->emitir());

        $this->expectException(CarnetInvalidoException::class);
        $this->emitir();
    }

    #[Test]
    public function el_circuito_cobrar_enviar_validar_aprobar_y_recien_ahi_emite_guias(): void
    {
        $carnet = $this->emitir();
        $producto = ProductoHidrobiologico::query()->firstOrFail();
        $guias = app(EmitirGuiaService::class);
        $renglon = [['producto_id' => $producto->id, 'condicion' => 'fresco_entero', 'cantidad_kg' => 10]];
        $datos = ['origen' => 'Trinidad', 'destino' => 'Riberalta'];

        // Pendiente todavía no emite.
        try {
            $guias->emitir($carnet, $datos, $renglon);
            $this->fail('Emitió una guía con el carnet pendiente');
        } catch (PermisoOperativoException) {
            $this->addToAssertionCount(1);
        }

        // Sin cobrar no se presenta.
        try {
            app(RevisarCarnetService::class)->enviar($carnet);
            $this->fail('Se envió el carnet sin cobrar');
        } catch (CarnetInvalidoException) {
            $this->assertSame(EstadoCarnet::Pendiente, $carnet->fresh()->estado);
        }

        $aprobado = $this->aprobar($carnet);
        $this->assertSame(EstadoCarnet::Aprobado, $aprobado->estado);
        $this->assertTrue($aprobado->estaVigente());
        $this->assertTrue($aprobado->puedeEmitirGuias());
        $this->assertFalse($aprobado->puedeEmitirFaenas());

        $this->assertSame(EstadoGuia::Pendiente, $guias->emitir($aprobado, $datos, $renglon)->estado);
    }

    #[Test]
    public function la_guia_de_un_carnet_revocado_vale_solo_con_un_carnet_nuevo_aprobado(): void
    {
        $carnet = $this->aprobar($this->emitir());
        $producto = ProductoHidrobiologico::query()->firstOrFail();

        $guia = app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'Riberalta'],
            [['producto_id' => $producto->id, 'condicion' => 'fresco_entero', 'cantidad_kg' => 10]],
        );
        $this->pagar($guia);
        $guia = app(RevisarGuiaService::class)->enviar($guia->fresh());
        app(ControlarPagoService::class)->validar($guia->pagos()->firstOrFail());
        $guia = app(RevisarGuiaService::class)->aprobar($guia->fresh());

        app(EmitirCarnetService::class)->revocar($carnet->fresh(), 'Reposición: carnet dañado');

        // Caso 3: revocado y sin carnet nuevo. La guía no se reescribe, pero no vale.
        $guia = $guia->fresh();
        $this->assertSame(EstadoCarnet::Revocado, $carnet->fresh()->estado);
        $this->assertSame(EstadoGuia::Aprobada, $guia->estado, 'no se reescribe');
        $this->assertFalse($guia->estaVigente());
        $this->assertSame('Sin efecto', $guia->etiquetaEstado());
        $this->assertStringContainsString('carnet de comercializador vigente', (string) $guia->motivoSinEfecto());
        $this->assertFalse(GuiaMovimiento::query()->vigentes()->whereKey($guia->id)->exists());

        // El QR lo dice.
        auth()->logout();
        $this->get(route('verificar.show', ['codigo' => $guia->codigo_legible]))
            ->assertInertia(fn ($page) => $page
                ->where('documento.vigente', false)
                ->where('documento.estado_etiqueta', 'Sin efecto')
                ->where('documento.mensaje', fn ($m) => str_contains($m, 'carnet de comercializador vigente')));
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());

        // Caso 2: registrado el nuevo, todavía pendiente: no ampara.
        $nuevo = $this->emitir();
        $this->assertFalse($guia->fresh()->estaVigente());

        // Aprobado el nuevo, la guía vuelve a valer sola, hasta su propia fecha.
        $this->aprobar($nuevo);
        $this->assertTrue($guia->fresh()->estaVigente());
        $this->assertSame('Aprobada', $guia->fresh()->etiquetaEstado());
        $this->assertTrue(GuiaMovimiento::query()->vigentes()->whereKey($guia->id)->exists());
    }

    #[Test]
    public function anular_la_guia_no_toca_el_carnet(): void
    {
        $carnet = $this->aprobar($this->emitir());
        $producto = ProductoHidrobiologico::query()->firstOrFail();

        $guia = app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'Riberalta'],
            [['producto_id' => $producto->id, 'condicion' => 'fresco_entero', 'cantidad_kg' => 10]],
        );
        $this->pagar($guia);
        $guia = app(RevisarGuiaService::class)->enviar($guia->fresh());
        app(ControlarPagoService::class)->validar($guia->pagos()->firstOrFail());
        $guia = app(RevisarGuiaService::class)->aprobar($guia->fresh());

        app(EmitirGuiaService::class)->anular($guia, 'Carga decomisada en el control');

        $this->assertFalse($guia->fresh()->estaVigente());
        $this->assertTrue($carnet->fresh()->estaVigente());
        $this->assertTrue($carnet->fresh()->puedeEmitirGuias());
    }

    #[Test]
    public function en_el_cambio_de_anio_la_guia_espera_el_carnet_de_la_gestion_nueva(): void
    {
        // Firmada el 30/12 a las 10:00: vence el 04/01, pero el carnet vence el 31/12.
        $this->travelTo(now()->setDate((int) now()->format('Y'), 12, 30)->setTime(10, 0));
        $carnet = $this->aprobar($this->emitir());
        $producto = ProductoHidrobiologico::query()->firstOrFail();

        $guia = app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'Riberalta'],
            [['producto_id' => $producto->id, 'condicion' => 'fresco_entero', 'cantidad_kg' => 10]],
        );
        $this->pagar($guia);
        $guia = app(RevisarGuiaService::class)->enviar($guia->fresh());
        app(ControlarPagoService::class)->validar($guia->pagos()->firstOrFail());
        $guia = app(RevisarGuiaService::class)->aprobar($guia->fresh());
        $this->assertTrue($guia->estaVigente());

        // 2 de enero: la guía sigue en fecha, pero ya no hay carnet vigente.
        $this->travel(3)->days();
        $this->assertTrue($guia->fresh()->estaEnFecha());
        $this->assertFalse($guia->fresh()->estaVigente());

        // Con el carnet de la gestión nueva aprobado, vuelve a valer.
        $this->aprobar($this->emitir());
        $this->assertTrue($guia->fresh()->estaVigente());
    }

    #[Test]
    public function la_ficha_del_carnet_lista_sus_guias_y_el_boton_abre_el_formulario_con_el_carnet_elegido(): void
    {
        $carnet = $this->aprobar($this->emitir());
        $producto = ProductoHidrobiologico::query()->firstOrFail();

        $guia = app(EmitirGuiaService::class)->emitir(
            $carnet,
            ['origen' => 'Trinidad', 'destino' => 'Riberalta'],
            [['producto_id' => $producto->id, 'condicion' => 'fresco_entero', 'cantidad_kg' => 42]],
        );

        $this->get(route('carnets.show', $carnet))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('carnet.puede_emitir_guias', true)
                ->has('guias', 1)
                ->where('guias.0.id', $guia->id)
                ->where('guias.0.origen', 'Trinidad')
                ->where('guias.0.peso_total_kg', 42));

        $this->get(route('guias.create', ['carnet' => $carnet->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('carnetElegido', $carnet->id)
                ->where('beneficiario.id', $this->persona->id)
                ->where('beneficiario.carnets_vigentes.0.id', $carnet->id));
    }

    //  Auxiliares

    private function emitir(?AprovechamientoPesq $cupo = null): Carnet
    {
        return app(EmitirCarnetService::class)->emitir(
            $this->persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Comercializador)->firstOrFail(),
            TipoActor::Comercializador,
            cupoElegido: $cupo,
        );
    }

    private function aprobar(Carnet $carnet): Carnet
    {
        $this->pagar($carnet);
        $enviado = app(RevisarCarnetService::class)->enviar($carnet->fresh());
        app(ControlarPagoService::class)->validar($enviado->pagos()->firstOrFail());

        return app(RevisarCarnetService::class)->aprobar($enviado->fresh());
    }

    private function pagar(Model $tramite): void
    {
        static $boleta = 0;

        app(CobrarService::class)->registrarDepositos($tramite, [[
            'monto' => $tramite->montoACobrar(),
            'nro_transaccion' => 'CC-'.(++$boleta),
            'fecha_deposito' => now()->toDateString(),
            'comprobante' => 'comprobantes/prueba.pdf',
        ]]);
    }
}
