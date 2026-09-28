<?php

namespace Tests\Feature\Comercializador;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoGuia;
use App\Enums\TipoActor;
use App\Exceptions\PermisoOperativoException;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\ProductoHidrobiologico;
use App\Models\TipoCarnet;
use App\Models\User;
use App\Services\CobrarService;
use App\Services\ControlarPagoService;
use App\Services\EmitirGuiaService;
use App\Services\RevisarGuiaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El módulo del comercializador: carnet → guía única de transporte, con el
 * cuadro D tomado del catálogo de productos hidrobiológicos.
 * La especificación está en docs/REGLAS-NEGOCIO.md, paso 5.
 */
class GuiaTest extends TestCase
{
    use RefreshDatabase;

    private Carnet $carnet;

    private ProductoHidrobiologico $surubi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, ConfiguracionSeeder::class, UsuarioSeeder::class, CatalogoSeeder::class]);
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());

        $this->carnet = $this->carnet(TipoActor::Comercializador, EstadoCarnet::Aprobado);
        $this->surubi = ProductoHidrobiologico::query()->create(['nombre' => 'Surubí de prueba', 'precio_kg' => 12.50]);
    }

    //  El cuadro D sale del catálogo

    #[Test]
    public function el_detalle_copia_nombre_y_precio_del_catalogo(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 200]]);
        $d = $guia->detalles()->firstOrFail();

        $this->assertSame($this->surubi->id, $d->producto_id);
        $this->assertSame('Surubí de prueba', $d->especie);
        $this->assertSame(12.5, (float) $d->precio_kg);
        $this->assertSame(2500.0, (float) $d->importe_total);
        $this->assertSame(200.0, (float) $guia->peso_total_kg);
    }

    #[Test]
    public function el_precio_que_llega_del_formulario_se_ignora(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10, 'precio_kg' => 1, 'importe_total' => 1]]);

        $this->assertSame(125.0, (float) $guia->detalles()->firstOrFail()->importe_total);
    }

    #[Test]
    public function cambiar_el_catalogo_no_mueve_una_guia_emitida(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10]]);

        $this->surubi->update(['nombre' => 'Surubí renombrado', 'precio_kg' => 99]);

        $d = $guia->detalles()->firstOrFail();
        $this->assertSame('Surubí de prueba', $d->especie);
        $this->assertSame(12.5, (float) $d->precio_kg);
    }

    #[Test]
    public function un_producto_inactivo_no_se_elige_en_una_guia_nueva(): void
    {
        $this->surubi->update(['estado' => false]);

        $this->expectException(PermisoOperativoException::class);
        $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10]]);
    }

    #[Test]
    public function al_corregir_se_conserva_un_producto_que_la_guia_ya_tenia_aunque_este_inactivo(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10]]);
        $this->surubi->update(['estado' => false]);

        $corregida = app(EmitirGuiaService::class)->editar($guia, $this->datos(), [$this->renglon($this->surubi->id, 30)]);
        $this->assertSame(30.0, (float) $corregida->peso_total_kg);

        // Pero no se suma uno inactivo que la guía no tenía.
        $otro = ProductoHidrobiologico::query()->create(['nombre' => 'Pacú de prueba', 'precio_kg' => 8, 'estado' => false]);

        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->editar($guia, $this->datos(), [$this->renglon($otro->id, 5)]);
    }

    //  Quién emite

    #[Test]
    public function se_cobra_el_total_del_cuadro_d_y_la_piscicultura_paga_la_mitad(): void
    {
        $pacu = ProductoHidrobiologico::query()->create(['nombre' => 'Pacú cobrado', 'precio_kg' => 2]);

        // 200 kg de surubí a 12,50 + 100 kg de pacú a 2 = 2500 + 200.
        $normal = $this->emitir([
            ['producto_id' => $this->surubi->id, 'cantidad_kg' => 200],
            ['producto_id' => $pacu->id, 'cantidad_kg' => 100],
        ]);
        $this->assertSame(2700.0, (float) $normal->monto);
        $this->assertSame(2700.0, $normal->montoACobrar());

        $criadero = app(EmitirGuiaService::class)->emitir(
            $this->carnet,
            [...$this->datos(), 'es_piscicultura' => true],
            [$this->renglon($this->surubi->id, 200), $this->renglon($pacu->id, 100)],
        );
        $this->assertSame(1350.0, (float) $criadero->monto);

        // Corregir la carga recalcula lo que se cobra.
        $corregida = app(EmitirGuiaService::class)->editar($normal, $this->datos(), [$this->renglon($pacu->id, 10)]);
        $this->assertSame(20.0, (float) $corregida->monto);

        // Y subir el precio del catálogo después no mueve lo ya emitido.
        $pacu->update(['precio_kg' => 50]);
        $this->assertSame(20.0, $corregida->fresh()->montoACobrar());
    }

    #[Test]
    public function un_carnet_de_pescador_no_emite_guias(): void
    {
        $pescador = $this->carnet(TipoActor::Pescador, EstadoCarnet::Aprobado);

        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->emitir($pescador, $this->datos(), [$this->renglon($this->surubi->id, 10)]);
    }

    #[Test]
    public function un_carnet_sin_aprobar_o_revocado_no_emite_guias(): void
    {
        foreach ([EstadoCarnet::Pendiente, EstadoCarnet::EnRevision, EstadoCarnet::Revocado] as $estado) {
            $carnet = $this->carnet(TipoActor::Comercializador, $estado);

            try {
                app(EmitirGuiaService::class)->emitir($carnet, $this->datos(), [$this->renglon($this->surubi->id, 10)]);
                $this->fail("Emitió con el carnet {$estado->value}");
            } catch (PermisoOperativoException) {
                $this->assertSame(0, GuiaMovimiento::query()->where('carnet_id', $carnet->id)->count());
            }
        }
    }

    //  El circuito

    #[Test]
    public function el_circuito_completo_cobrar_enviar_validar_aprobar_y_cerrar(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 100]]);
        $revisar = app(RevisarGuiaService::class);

        // Sin el arancel cubierto no se presenta.
        try {
            $revisar->enviar($guia);
            $this->fail('Se envió sin cobrar');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoGuia::Pendiente, $guia->fresh()->estado);
        }

        $this->pagar($guia);
        $enviada = $revisar->enviar($guia->fresh());
        $this->assertSame(EstadoGuia::EnRevision, $enviada->estado);
        $this->assertNotNull($enviada->pagos()->firstOrFail()->recibo_id, 'Al enviar sale el recibo');

        // Con la boleta sin controlar no se aprueba.
        try {
            $revisar->aprobar($enviada);
            $this->fail('Se aprobó con la boleta sin validar');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoGuia::EnRevision, $enviada->fresh()->estado);
        }

        app(ControlarPagoService::class)->validar($enviada->pagos()->firstOrFail());
        $aprobada = $revisar->aprobar($enviada->fresh());

        $this->assertSame(EstadoGuia::Aprobada, $aprobada->estado);
        $this->assertNotNull($aprobada->fecha_emision);
        $this->assertSame(5, (int) round($aprobada->fecha_emision->diffInDays($aprobada->fecha_vencimiento)));

        $cerrada = app(EmitirGuiaService::class)->cerrar($aprobada, 95.5);
        $this->assertSame(EstadoGuia::Cerrada, $cerrada->estado);
        $this->assertSame(95.5, (float) $cerrada->peso_total_kg);

        // Cerrada ya cumplió: no se anula.
        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->anular($cerrada, 'Motivo de prueba suficiente');
    }

    #[Test]
    public function solo_se_anula_una_guia_aprobada(): void
    {
        $pendiente = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10]]);

        try {
            app(EmitirGuiaService::class)->anular($pendiente, 'Motivo de prueba suficiente');
            $this->fail('Se anuló un borrador');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoGuia::Pendiente, $pendiente->fresh()->estado);
        }

        $this->pagar($pendiente);
        $enRevision = app(RevisarGuiaService::class)->enviar($pendiente->fresh());

        try {
            app(EmitirGuiaService::class)->anular($enRevision, 'Motivo de prueba suficiente');
            $this->fail('Se anuló una guía en revisión');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoGuia::EnRevision, $enRevision->fresh()->estado);
        }

        app(ControlarPagoService::class)->validar($enRevision->pagos()->firstOrFail());
        $aprobada = app(RevisarGuiaService::class)->aprobar($enRevision->fresh());

        $this->assertSame(EstadoGuia::Anulada, app(EmitirGuiaService::class)->anular($aprobada, 'Motivo de prueba suficiente')->estado);
    }

    #[Test]
    public function un_borrador_con_depositos_no_se_corrige_ni_se_elimina(): void
    {
        $guia = $this->emitir([['producto_id' => $this->surubi->id, 'cantidad_kg' => 10]]);
        $this->pagar($guia);

        try {
            app(EmitirGuiaService::class)->editar($guia->fresh(), $this->datos(), [$this->renglon($this->surubi->id, 20)]);
            $this->fail('Se corrigió con un depósito cargado');
        } catch (PermisoOperativoException) {
            $this->assertSame(10.0, (float) $guia->fresh()->peso_total_kg);
        }

        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->eliminar($guia->fresh(), 'Motivo de prueba suficiente');
    }

    //  El catálogo desde el panel

    #[Test]
    public function el_catalogo_da_de_alta_y_corrige_productos(): void
    {
        $this->post(route('productos.store'), ['nombre' => 'Tambaqui nuevo', 'precio_kg' => '7.25', 'estado' => '1'])
            ->assertRedirect(route('productos.index'));

        $p = ProductoHidrobiologico::query()->where('nombre', 'Tambaqui nuevo')->firstOrFail();
        $this->assertSame(7.25, (float) $p->precio_kg);

        // Nombre repetido: rechazado.
        $this->post(route('productos.store'), ['nombre' => 'Tambaqui nuevo', 'precio_kg' => '1'])
            ->assertSessionHasErrors('nombre');

        // El precio es la tasa que se cobra: de 0,20 Bs en adelante.
        $this->post(route('productos.store'), ['nombre' => 'Sin precio', 'precio_kg' => '0.10'])
            ->assertSessionHasErrors('precio_kg');
        $this->post(route('productos.store'), ['nombre' => 'Precio mínimo', 'precio_kg' => '0.20'])
            ->assertSessionHasNoErrors();

        $this->put(route('productos.update', $p), ['nombre' => 'Tambaqui nuevo', 'precio_kg' => '8', 'estado' => '0'])
            ->assertRedirect(route('productos.index'));

        $this->assertFalse($p->fresh()->estado);
        $this->get(route('productos.index'))->assertOk();
    }

    #[Test]
    public function el_formulario_de_guia_recibe_solo_los_productos_activos(): void
    {
        $this->surubi->update(['estado' => false]);

        $this->get(route('guias.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('panel/guias/crear')
                ->where('productos', fn ($lista) => collect($lista)->pluck('id')->doesntContain($this->surubi->id)));
    }

    //  Auxiliares

    /** @param  array<int, array<string, mixed>>  $filas */
    private function emitir(array $filas): GuiaMovimiento
    {
        return app(EmitirGuiaService::class)->emitir(
            $this->carnet,
            $this->datos(),
            array_map(fn (array $f): array => ['condicion' => 'fresco_entero', ...$f], $filas),
        );
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return ['origen' => 'Trinidad', 'destino' => 'Santa Cruz', 'es_piscicultura' => false];
    }

    /** @return array<string, mixed> */
    private function renglon(int $productoId, float $kilos): array
    {
        return ['producto_id' => $productoId, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos];
    }

    private function pagar(GuiaMovimiento $guia): void
    {
        static $boleta = 0;

        app(CobrarService::class)->registrarDepositos($guia, [[
            'monto' => $guia->montoACobrar(),
            'nro_transaccion' => 'BOLETA-'.(++$boleta),
            'fecha_deposito' => now()->toDateString(),
            'comprobante' => 'comprobantes/prueba.pdf',
        ]]);
    }

    private function carnet(TipoActor $actor, EstadoCarnet $estado): Carnet
    {
        static $registro = 0;

        return Carnet::query()->create([
            'beneficiario_id' => Beneficiario::factory()->create()->id,
            'asociacion_id' => Asociacion::query()->firstOrFail()->id,
            'tipo_carnet_id' => TipoCarnet::query()->where('tipo_actor', $actor)->firstOrFail()->id,
            'tipo_actor' => $actor,
            'nro_registro' => ++$registro,
            'estado' => $estado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }
}
