<?php

namespace Tests\Feature\Comercializador;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoGuia;
use App\Enums\EstadoValidacionPago;
use App\Enums\TipoActor;
use App\Exceptions\PermisoOperativoException;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Models\ProductoHidrobiologico;
use App\Models\Recibo;
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
 * El resto del circuito de la guía: rechazo y reenvío, boletas observadas,
 * vencimiento, corrección, baja, numeración, formulario y verificación pública.
 */
class GuiaCircuitoTest extends TestCase
{
    use RefreshDatabase;

    private Carnet $carnet;

    private ProductoHidrobiologico $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, ConfiguracionSeeder::class, UsuarioSeeder::class, CatalogoSeeder::class]);
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());

        $this->carnet = Carnet::query()->create([
            'beneficiario_id' => Beneficiario::factory()->create()->id,
            'asociacion_id' => Asociacion::query()->firstOrFail()->id,
            'tipo_carnet_id' => TipoCarnet::query()->where('tipo_actor', TipoActor::Comercializador)->firstOrFail()->id,
            'tipo_actor' => TipoActor::Comercializador,
            'nro_registro' => 1,
            'estado' => EstadoCarnet::Aprobado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
        $this->producto = ProductoHidrobiologico::query()->create(['nombre' => 'Pacú de prueba', 'precio_kg' => 10]);
    }

    #[Test]
    public function rechazar_devuelve_a_pendiente_y_el_reenvio_no_emite_otro_recibo(): void
    {
        $guia = $this->enviada();
        $recibo = $guia->pagos()->firstOrFail()->recibo_id;

        $rechazada = app(RevisarGuiaService::class)->rechazar($guia, 'Falta la placa del vehículo');
        $this->assertSame(EstadoGuia::Pendiente, $rechazada->estado);

        $reenviada = app(RevisarGuiaService::class)->enviar($rechazada->fresh());
        $this->assertSame(EstadoGuia::EnRevision, $reenviada->estado);
        $this->assertSame($recibo, $reenviada->pagos()->firstOrFail()->recibo_id);
        $this->assertSame(1, Recibo::query()->count());
    }

    #[Test]
    public function una_boleta_observada_frena_la_aprobacion_hasta_corregirla_y_validarla(): void
    {
        $guia = $this->enviada();
        $pago = $guia->pagos()->firstOrFail();

        app(ControlarPagoService::class)->observar($pago, 'El número no figura en el extracto');

        try {
            app(RevisarGuiaService::class)->aprobar($guia->fresh());
            $this->fail('Se aprobó con una boleta observada');
        } catch (PermisoOperativoException) {
            $this->assertSame(EstadoGuia::EnRevision, $guia->fresh()->estado);
        }

        $corregido = app(ControlarPagoService::class)->corregir($pago->fresh(), ['nro_transaccion' => 'CORREGIDA-1']);
        $this->assertSame(EstadoValidacionPago::Pendiente, $corregido->estado_validacion);

        app(ControlarPagoService::class)->validar($corregido);
        $this->assertSame(EstadoGuia::Aprobada, app(RevisarGuiaService::class)->aprobar($guia->fresh())->estado);
    }

    #[Test]
    public function a_los_cinco_dias_deja_de_amparar_el_traslado(): void
    {
        $guia = $this->aprobada();
        $this->assertTrue($guia->estaVigente());

        $this->travel(5)->days();
        $this->travel(1)->minutes();

        $this->assertFalse($guia->fresh()->estaVigente());
        $this->assertTrue($guia->fresh()->estaCaducada());
    }

    #[Test]
    public function corregir_el_borrador_reemplaza_el_detalle_entero(): void
    {
        $otro = ProductoHidrobiologico::query()->create(['nombre' => 'Sábalo de prueba', 'precio_kg' => 4]);
        $guia = app(EmitirGuiaService::class)->emitir($this->carnet, $this->datos(), [
            $this->renglon($this->producto->id, 10),
            $this->renglon($otro->id, 20),
        ]);
        $this->assertSame(30.0, (float) $guia->peso_total_kg);

        $corregida = app(EmitirGuiaService::class)->editar($guia, $this->datos(), [$this->renglon($otro->id, 7)]);

        $this->assertSame(1, $corregida->detalles()->count());
        $this->assertSame(7.0, (float) $corregida->peso_total_kg);
        $this->assertSame(28.0, (float) $corregida->detalles()->firstOrFail()->importe_total);
    }

    #[Test]
    public function eliminar_el_borrador_da_de_baja_la_guia_y_su_detalle(): void
    {
        $guia = $this->pendiente();

        app(EmitirGuiaService::class)->eliminar($guia, 'Cargada por error en ventanilla');

        $this->assertSoftDeleted($guia);
        $this->assertSame(0, GuiaDetalle::query()->where('guia_movimiento_id', $guia->id)->count());
        $this->assertSame(1, GuiaDetalle::withTrashed()->where('guia_movimiento_id', $guia->id)->count());
    }

    #[Test]
    public function una_guia_sin_detalle_no_se_emite(): void
    {
        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->emitir($this->carnet, $this->datos(), []);
    }

    #[Test]
    public function el_numero_es_correlativo_y_cada_guia_lleva_su_codigo(): void
    {
        $a = $this->pendiente();
        $b = $this->pendiente();

        $this->assertSame($a->numero_guia + 1, $b->numero_guia);
        $this->assertSame(16, strlen(str_replace('-', '', (string) $a->codigo_legible)));
        $this->assertNotSame($a->codigo_legible, $b->codigo_legible);
    }

    #[Test]
    public function cerrar_solo_vale_sobre_una_guia_aprobada(): void
    {
        $this->expectException(PermisoOperativoException::class);
        app(EmitirGuiaService::class)->cerrar($this->pendiente());
    }

    //  Por HTTP, como lo usa ventanilla

    #[Test]
    public function el_formulario_registra_la_guia_con_el_producto_del_catalogo(): void
    {
        $this->post(route('guias.store'), [
            'carnet_id' => $this->carnet->id,
            ...$this->datos(),
            'detalles' => [
                ['producto_id' => $this->producto->id, 'condicion' => 'congelado_entero', 'cantidad_kg' => '12.5'],
                // Un renglón vacío del papel se descarta, no se valida.
                ['producto_id' => '', 'condicion' => '', 'cantidad_kg' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $d = GuiaDetalle::query()->firstOrFail();
        $this->assertSame('Pacú de prueba', $d->especie);
        $this->assertSame(125.0, (float) $d->importe_total);
    }

    #[Test]
    public function el_formulario_pide_elegir_un_producto(): void
    {
        $this->post(route('guias.store'), [
            'carnet_id' => $this->carnet->id,
            ...$this->datos(),
            'detalles' => [['producto_id' => 999999, 'condicion' => 'seco', 'cantidad_kg' => '5']],
        ])->assertSessionHasErrors('detalles.0.producto_id');

        $this->assertSame(0, GuiaMovimiento::query()->count());
    }

    #[Test]
    public function la_ficha_y_la_impresion_abren(): void
    {
        $guia = $this->aprobada();

        $this->get(route('guias.show', $guia))->assertOk();
        $this->get(route('guias.edit', $this->pendiente()))->assertOk();
        $this->get(route('guias.index'))->assertOk();

        $pdf = $this->get(route('guias.imprimir', $guia));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    #[Test]
    public function la_verificacion_publica_muestra_la_guia_vigente_con_la_cedula_enmascarada(): void
    {
        $guia = $this->aprobada();
        auth()->logout();

        $this->get(route('verificar.show', ['codigo' => $guia->codigo_legible]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('encontrado', true)
                ->where('documento.tipo', 'guia')
                ->where('documento.vigente', true)
                ->where('documento.vigencia.hasta_etiqueta', 'Vence')
                ->where('documento.documento_titular', fn ($ci) => ! str_contains((string) $ci, (string) $this->carnet->beneficiario->ci)));
    }

    //  Auxiliares

    private function pendiente(): GuiaMovimiento
    {
        return app(EmitirGuiaService::class)->emitir($this->carnet, $this->datos(), [$this->renglon($this->producto->id, 10)]);
    }

    private function enviada(): GuiaMovimiento
    {
        $guia = $this->pendiente();

        app(CobrarService::class)->registrarDepositos($guia, [[
            'monto' => $guia->montoACobrar(),
            'nro_transaccion' => 'GC-'.$guia->id,
            'fecha_deposito' => now()->toDateString(),
            'comprobante' => 'comprobantes/prueba.pdf',
        ]]);

        return app(RevisarGuiaService::class)->enviar($guia->fresh());
    }

    private function aprobada(): GuiaMovimiento
    {
        $guia = $this->enviada();
        app(ControlarPagoService::class)->validar($guia->pagos()->firstOrFail());

        return app(RevisarGuiaService::class)->aprobar($guia->fresh());
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return ['origen' => 'Trinidad', 'destino' => 'Santa Cruz', 'es_piscicultura' => false, 'fecha_solicitud' => now()->toDateString()];
    }

    /** @return array<string, mixed> */
    private function renglon(int $productoId, float $kilos): array
    {
        return ['producto_id' => $productoId, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos];
    }
}
