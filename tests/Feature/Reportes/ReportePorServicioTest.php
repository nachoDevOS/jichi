<?php

namespace Tests\Feature\Reportes;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoCarnet;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\Recibo;
use App\Models\User;
use App\Services\ReportePorServicioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/** El reporte por tipo de servicio y rango de fechas (24/11/2026). Ver docs/modulos/REPORTES.md. */
class ReportePorServicioTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private User $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Carbon::setTestNow('2026-11-24 10:00:00');
        $this->funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);

        $autorizacion = AprovechamientoPesq::query()->create([
            'beneficiario_id' => Beneficiario::factory()->create()->id, 'nro' => 1, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => '2026-11-05', 'fecha_emision' => '2026-11-05', 'fecha_vencimiento' => '2026-12-31',
        ]);
        $this->recibo($autorizacion, '000001', 660);

        $asociacion = Asociacion::query()->create(['nombre' => 'Pescadores del Mamoré', 'sigla' => 'PDM']);
        $pagado = $this->carnet($asociacion, EstadoCarnet::Aprobado, '2026-11-12', 1);
        $this->recibo($pagado, '000002', 15);
        $this->carnet($asociacion, EstadoCarnet::Pendiente, '2026-11-03', 2);
        // Fuera del rango de noviembre.
        $this->carnet($asociacion, EstadoCarnet::Pendiente, '2026-10-20', 3);
    }

    public function test_cada_servicio_trae_solo_lo_suyo_en_el_rango(): void
    {
        $reportes = app(ReportePorServicioService::class);
        [$desde, $hasta] = $reportes->periodo('2026-11-01', '2026-11-30');

        $carnets = $reportes->totales(ConceptoArancel::Carnet, $reportes->consulta(ConceptoArancel::Carnet, $desde, $hasta));
        $this->assertSame(['cantidad' => 2, 'monto' => 30.0, 'cobrado' => 15.0, 'por_cobrar' => 15.0], $carnets);

        $autorizaciones = $reportes->totales(ConceptoArancel::Autorizacion, $reportes->consulta(ConceptoArancel::Autorizacion, $desde, $hasta));
        $this->assertSame(1, $autorizaciones['cantidad']);
        $this->assertSame(660.0, $autorizaciones['cobrado']);

        $this->assertSame(0, $reportes->totales(ConceptoArancel::Faena, $reportes->consulta(ConceptoArancel::Faena, $desde, $hasta))['cantidad']);
    }

    public function test_la_pantalla_filtra_por_servicio_fechas_y_estado(): void
    {
        $this->withoutVite();
        $pedir = fn (array $q) => $this->actingAs($this->funcionario)->get(route('reportes.index', $q))->assertOk();

        $pedir(['servicio' => 'carnet', 'desde' => '2026-11-01', 'hasta' => '2026-11-30'])
            ->assertInertia(fn (AssertableInertia $p) => $p->component('panel/reportes/index')
                ->where('totales.cantidad', 2)
                ->has('filas.data', 2)
                // Del más viejo al más nuevo.
                ->where('filas.data.0.fecha', '2026-11-03')
                ->where('filas.data.1.recibo', '000002')
                ->has('estados', 3));

        $pedir(['servicio' => 'carnet', 'desde' => '2026-10-01', 'hasta' => '2026-11-30', 'estado' => 'pendiente'])
            ->assertInertia(fn (AssertableInertia $p) => $p->where('totales.cantidad', 2)->where('filtros.estado', 'pendiente'));

        // Un estado que no es de ese servicio se ignora.
        $pedir(['servicio' => 'carnet', 'desde' => '2026-11-01', 'hasta' => '2026-11-30', 'estado' => "x' OR 1=1"])
            ->assertInertia(fn (AssertableInertia $p) => $p->where('totales.cantidad', 2)->where('filtros.estado', null));

        foreach (ConceptoArancel::cases() as $servicio) {
            $pedir(['servicio' => $servicio->value])->assertInertia(fn (AssertableInertia $p) => $p->where('filtros.servicio', $servicio->value));
        }
    }

    public function test_el_excel_trae_las_filas_del_servicio(): void
    {
        $csv = $this->actingAs($this->funcionario)
            ->get(route('reportes.excel', ['servicio' => 'carnet', 'desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()
            ->assertDownload('reporte-carnet_2026-11-01_2026-11-30.csv')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFFecha;N°;Titular;C.I.;Actividad;Asociación;\"Monto (Bs)\";Estado;\"N° recibo\"", $csv);
        $this->assertStringContainsString('15,00;Aprobado;000002', $csv);
        $this->assertSame(3, substr_count(trim($csv), "\n") + 1, 'encabezado y dos carnets: el de octubre no entra');
    }

    public function test_el_pdf_sale(): void
    {
        $respuesta = $this->actingAs($this->funcionario)
            ->get(route('reportes.pdf', ['servicio' => 'autorizacion', 'desde' => '2026-11-01', 'hasta' => '2026-11-30']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_una_cuenta_del_portal_no_entra(): void
    {
        $titular = User::create(['name' => 'T', 'email' => 't@jichi.test', 'password' => 'x', 'activo' => true,
            'beneficiario_id' => Beneficiario::query()->first()->id]);

        $this->actingAs($titular)->get(route('reportes.index'))->assertRedirect(route('portal.inicio'));
        $this->actingAs($titular)->get(route('reportes.pdf'))->assertRedirect(route('portal.inicio'));
    }

    private function carnet(Asociacion $asociacion, EstadoCarnet $estado, string $fecha, int $nro): Carnet
    {
        return Carnet::query()->create([
            'beneficiario_id' => Beneficiario::factory()->create()->id, 'asociacion_id' => $asociacion->id,
            'tipo_actor' => 'comercializador', 'monto' => 15, 'estado' => $estado, 'nro' => $nro,
            'fecha_solicitud' => $fecha, 'fecha_emision' => $estado === EstadoCarnet::Aprobado ? $fecha : null,
            'fecha_vencimiento' => '2026-12-31',
        ]);
    }

    private function recibo($documento, string $numero, float $monto): void
    {
        Recibo::query()->create(['beneficiario_id' => $documento->titularSireb()->id, 'recibible_type' => $documento->getMorphClass(),
            'recibible_id' => $documento->id, 'numero_recibo' => $numero, 'monto_total' => $monto, 'concepto' => 'Prueba']);
    }
}
