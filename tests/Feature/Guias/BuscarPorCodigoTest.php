<?php

namespace Tests\Feature\Guias;

use App\Http\Controllers\Panel\BeneficiarioController;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * «Procedencia del pescado»: el pescador se busca también por el código de
 * verificación de su autorización. `codigos` guarda los de cinco documentos.
 */
class BuscarPorCodigoTest extends TestCase
{
    use RefreshDatabase;

    private Beneficiario $juan;

    private AprovechamientoPesq $vigente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->juan = Beneficiario::factory()->create();
        $this->vigente = $this->autorizacion($this->juan, now()->endOfYear()->toDateString(), 1);
    }

    public function test_encuentra_al_pescador_y_su_autorizacion_en_todas_las_formas_del_codigo(): void
    {
        $codigo = $this->vigente->codigo_legible;
        $plano = str_replace('-', '', $codigo);

        foreach ([$codigo, $plano, mb_strtolower($codigo), " {$codigo} ", "https://jichi.beni.gob.bo/verificar/{$plano}"] as $termino) {
            $r = $this->buscar($termino);

            $this->assertCount(1, $r, "con «{$termino}»");
            $this->assertSame($this->juan->id, $r[0]['id']);
            $this->assertSame($this->vigente->id, $r[0]['autorizacion_buscada_id']);
            $this->assertSame([$this->vigente->id], array_column($r[0]['autorizaciones_vendibles'], 'id'));
        }
    }

    public function test_una_autorizacion_vencida_trae_al_pescador_sin_nada_que_vender(): void
    {
        $ana = Beneficiario::factory()->create();
        $vencida = $this->autorizacion($ana, now()->subDay()->toDateString(), 2);

        $r = $this->buscar($vencida->codigo_legible);

        $this->assertSame($ana->id, $r[0]['id']);
        $this->assertSame([], $r[0]['autorizaciones_vendibles']);
    }

    public function test_el_codigo_de_otro_documento_no_encuentra_nada(): void
    {
        $carnet = Carnet::query()->create([
            'beneficiario_id' => $this->juan->id,
            'asociacion_id' => Asociacion::query()->create(['nombre' => 'Asociación de prueba', 'sigla' => 'ADP'])->id,
            'tipo_actor' => 'comercializador', 'monto' => 30, 'estado' => 'aprobado', 'nro' => 1,
            'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
        $carnet->asignarCodigo();

        $this->assertSame([], $this->buscar($carnet->refresh()->codigo_legible));
    }

    public function test_un_codigo_incompleto_no_encuentra_nada(): void
    {
        $this->assertSame([], $this->buscar(substr(str_replace('-', '', $this->vigente->codigo_legible), 0, 10)));
    }

    public function test_los_otros_buscadores_no_buscan_por_codigo(): void
    {
        $this->assertSame([], $this->buscar($this->vigente->codigo_legible, porCodigo: false));
    }

    public function test_por_nombre_sigue_funcionando(): void
    {
        $r = $this->buscar($this->juan->apellidoPaterno);

        $this->assertContains($this->juan->id, array_column($r, 'id'));
        $this->assertNull(collect($r)->firstWhere('id', $this->juan->id)['autorizacion_buscada_id']);
    }

    /** @return array<int, array<string, mixed>> */
    private function buscar(string $termino, bool $porCodigo = true): array
    {
        $consulta = ['q' => $termino] + ($porCodigo ? ['autorizacion' => 1] : []);

        return app(BeneficiarioController::class)->buscar(Request::create('/panel/beneficiarios/buscar', 'GET', $consulta));
    }

    private function autorizacion(Beneficiario $b, string $vence, int $nro): AprovechamientoPesq
    {
        $a = AprovechamientoPesq::query()->create([
            'beneficiario_id' => $b->id, 'nro' => $nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => $vence,
        ]);
        $a->asignarCodigo();

        return $a->refresh();
    }
}
