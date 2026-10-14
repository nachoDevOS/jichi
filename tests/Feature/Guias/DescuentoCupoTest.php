<?php

namespace Tests\Feature\Guias;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Services\EmitirFaenaService;
use App\Services\EmitirGuiaService;
use App\Services\RevisarCupoService;
use App\Services\RevisarGuiaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * La guía descuenta el cupo del pescador que vendió (13/10/2026).
 * Ver docs/modulos/GUIA-DESCUENTA-CUPO.md.
 */
class DescuentoCupoTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private EmitirGuiaService $guias;

    private Carnet $carnetNacho;

    private AprovechamientoPesq $autJuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();

        $this->guias = app(EmitirGuiaService::class);
        $this->autJuan = $this->autorizacion(Beneficiario::factory()->create());
        $this->carnetNacho = $this->carnet(Beneficiario::factory()->create(), 'comercializador');
    }

    //  Al registrar

    public function test_registrar_una_guia_descuenta_sus_kilos_del_pescador(): void
    {
        $guia = $this->registrar($this->autJuan, 300);

        $this->assertSame(700.0, $this->disponibles($this->autJuan));
        $this->assertSame($this->autJuan->id, $guia->aprovechamiento_id);
        $this->assertSame(EstadoGuia::Pendiente, $guia->estado);
        $this->assertNotNull($guia->sireb_liquidacion_id, 'la liquidación se registró en SIREB');
    }

    public function test_dos_guias_acumulan_el_descuento(): void
    {
        $this->registrar($this->autJuan, 300);
        $this->registrar($this->autJuan, 450);

        $this->assertSame(250.0, $this->disponibles($this->autJuan));
    }

    public function test_se_puede_usar_justo_todo_el_saldo(): void
    {
        $this->registrar($this->autJuan, 1000);

        $this->assertSame(0.0, $this->disponibles($this->autJuan));
    }

    public function test_si_se_pasa_del_saldo_se_frena_sin_crear_la_guia_ni_tocar_el_saldo(): void
    {
        $this->registrar($this->autJuan, 700);

        try {
            $this->registrar($this->autJuan, 300.01);
            $this->fail('Tenía que frenar por saldo.');
        } catch (PermisoOperativoException $e) {
            $this->assertStringContainsString('solo tiene 300 kg disponibles', $e->getMessage());
        }

        $this->assertSame(300.0, $this->disponibles($this->autJuan));
        $this->assertSame(1, GuiaMovimiento::query()->count(), 'la guía rechazada no quedó guardada');
    }

    public function test_sin_pescador_no_se_registra(): void
    {
        $this->expectException(PermisoOperativoException::class);
        $this->expectExceptionMessage('a qué pescador le compró');

        $this->guias->emitir($this->carnetNacho, $this->papel(null), $this->detalle(100));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function autorizacionesNoVendibles(): array
    {
        return [
            'pendiente de pago' => [EstadoAprovechamiento::Pendiente->value, '+1 month', 'todavía no se pagó'],
            'aprobada pero vencida' => [EstadoAprovechamiento::Aprobado->value, '-1 day', 'venció'],
            'revocada' => [EstadoAprovechamiento::Revocado->value, '+1 month', 'fue revocada'],
        ];
    }

    #[DataProvider('autorizacionesNoVendibles')]
    public function test_solo_se_descuenta_de_una_autorizacion_aprobada_y_en_fecha(string $estado, string $vence, string $motivo): void
    {
        $otra = $this->autorizacion(Beneficiario::factory()->create(), estado: $estado, vence: now()->modify($vence)->toDateString());

        try {
            $this->registrar($otra, 10);
            $this->fail('Tenía que rechazar la autorización.');
        } catch (PermisoOperativoException $e) {
            $this->assertStringContainsString($motivo, $e->getMessage());
        }

        $this->assertSame(1000.0, $this->disponibles($otra));
        $this->assertSame(0, GuiaMovimiento::query()->count());
    }

    public function test_piscicultura_no_lleva_pescador_ni_descuenta(): void
    {
        $guia = $this->guias->emitir(
            $this->carnetNacho,
            [...$this->papel(null), 'es_piscicultura' => true],
            $this->detalle(200),
        );

        $this->assertNull($guia->aprovechamiento_id);
        $this->assertSame(1000.0, $this->disponibles($this->autJuan));
    }

    //  Después de registrar

    public function test_eliminar_una_guia_pendiente_devuelve_los_kilos_y_anula_la_liquidacion(): void
    {
        $guia = $this->registrar($this->autJuan, 300);

        $this->guias->eliminar($guia, 'Cargada por error en la prueba.');

        $this->assertSame(1000.0, $this->disponibles($this->autJuan));
        $this->assertSoftDeleted($guia);
        $this->assertSame('anulada', $this->liquidaciones[$guia->sireb_liquidacion_id]['estado']);
    }

    public function test_corregir_devuelve_los_kilos_viejos_y_descuenta_los_nuevos(): void
    {
        $guia = $this->registrar($this->autJuan, 300);

        $this->guias->editar($guia, $this->papel($this->autJuan->id), $this->detalle(250));
        $this->assertSame(750.0, $this->disponibles($this->autJuan));

        $this->guias->editar($guia->refresh(), $this->papel($this->autJuan->id), $this->detalle(900));
        $this->assertSame(100.0, $this->disponibles($this->autJuan));
    }

    public function test_corregir_pasandose_del_saldo_se_frena_y_no_cambia_nada(): void
    {
        $guia = $this->registrar($this->autJuan, 300);
        $this->registrar($this->autJuan, 600);

        try {
            // Le quedan 100 + los 300 de esta guía = 400: 450 no entra.
            $this->guias->editar($guia, $this->papel($this->autJuan->id), $this->detalle(450));
            $this->fail('Tenía que frenar por saldo.');
        } catch (PermisoOperativoException) {
        }

        $this->assertSame(100.0, $this->disponibles($this->autJuan));
        $this->assertSame(300.0, (float) $guia->refresh()->peso_total_kg);
    }

    public function test_corregir_cambiando_de_pescador_mueve_los_kilos(): void
    {
        $autAna = $this->autorizacion(Beneficiario::factory()->create());
        $guia = $this->registrar($this->autJuan, 300);

        $this->guias->editar($guia, $this->papel($autAna->id), $this->detalle(300));

        $this->assertSame(1000.0, $this->disponibles($this->autJuan));
        $this->assertSame(700.0, $this->disponibles($autAna));
        $this->assertSame($autAna->id, $guia->refresh()->aprovechamiento_id);
    }

    public function test_aprobar_y_revocar_no_devuelve_los_kilos(): void
    {
        $guia = $this->registrar($this->autJuan, 200);

        app(RevisarGuiaService::class)->aprobar($guia);
        $this->guias->revocar($guia->refresh(), 'Revocada en la prueba.');

        $this->assertSame(EstadoGuia::Revocada, $guia->refresh()->estado);
        $this->assertSame(800.0, $this->disponibles($this->autJuan));
    }

    public function test_si_revocan_la_autorizacion_la_guia_pendiente_no_se_aprueba(): void
    {
        $guia = $this->registrar($this->autJuan, 100);
        app(RevisarCupoService::class)->revocar($this->autJuan, 'Revocada en la prueba por la unidad.');

        try {
            app(RevisarGuiaService::class)->aprobar($guia);
            $this->fail('No tenía que aprobarse.');
        } catch (PermisoOperativoException $e) {
            $this->assertStringContainsString('fue revocada', $e->getMessage());
        }

        $this->assertSame(EstadoGuia::Pendiente, $guia->refresh()->estado);
    }

    //  El saldo y la faena

    public function test_devolver_no_pasa_de_lo_autorizado(): void
    {
        $this->autJuan->devolver(500);

        $this->assertSame(1000.0, $this->disponibles($this->autJuan));
    }

    public function test_la_faena_no_descuenta_y_sale_con_lo_disponible_de_ese_dia(): void
    {
        $this->registrar($this->autJuan, 300);
        $carnetJuan = $this->carnet($this->autJuan->beneficiario, 'pescador', $this->autJuan);

        $faena = app(EmitirFaenaService::class)->emitir($carnetJuan, []);

        $this->assertSame(700.0, (float) $faena->kilos_extraidos);
        $this->assertSame(700.0, $this->disponibles($this->autJuan), 'la faena no descontó');
    }

    //  Auxiliares

    private function registrar(AprovechamientoPesq $autorizacion, float $kilos): GuiaMovimiento
    {
        return $this->guias->emitir($this->carnetNacho, $this->papel($autorizacion->id), $this->detalle($kilos));
    }

    /** @return array<string, mixed> */
    private function papel(?int $autorizacionId): array
    {
        return ['origen' => 'Trinidad', 'destino' => 'La Paz', 'es_piscicultura' => false, 'aprovechamiento_id' => $autorizacionId];
    }

    /** @return array<int, array<string, mixed>> */
    private function detalle(float $kilos): array
    {
        return [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]];
    }

    private function disponibles(AprovechamientoPesq $autorizacion): float
    {
        return (float) $autorizacion->refresh()->kilos_disponibles;
    }

    private function autorizacion(Beneficiario $b, string $estado = 'aprobado', ?string $vence = null, float $kilos = 1000): AprovechamientoPesq
    {
        static $nro = 0;

        return AprovechamientoPesq::query()->create([
            'beneficiario_id' => $b->id, 'nro' => ++$nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => $kilos, 'kilos_disponibles' => $kilos, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => $estado, 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => $vence ?? now()->endOfYear()->toDateString(),
        ]);
    }

    private function carnet(Beneficiario $b, string $actor, ?AprovechamientoPesq $cupo = null): Carnet
    {
        static $nro = 0;
        $asociacion = Asociacion::query()->firstOrCreate(['nombre' => 'Asociación de prueba'], ['sigla' => 'ADP']);

        return Carnet::query()->create([
            'beneficiario_id' => $b->id, 'asociacion_id' => $asociacion->id, 'aprovechamiento_id' => $cupo?->id,
            'tipo_actor' => $actor, 'monto' => 15, 'estado' => EstadoCarnet::Aprobado, 'nro' => ++$nro,
            'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }
}
