<?php

namespace Tests\Feature\Publico;

use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Support\QrVerificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * La verificación pública: el QR lleva `/verificar?code=CODIGO` (25/11/2026); lo ya impreso,
 * `/verificar/CODIGO`. Las dos tienen que encontrar el documento.
 */
class VerificacionTest extends TestCase
{
    use RefreshDatabase;

    private AprovechamientoPesq $autorizacion;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false, 'app.url' => 'https://jichi.beni.gob.bo']);
        $this->withoutVite();

        $persona = Beneficiario::factory()->create();
        $this->autorizacion = AprovechamientoPesq::query()->create([
            'beneficiario_id' => $persona->id, 'nro' => 1, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => 'aprobado', 'fecha_solicitud' => now()->toDateString(), 'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
        $this->autorizacion->asignarCodigo();
    }

    public function test_el_qr_lleva_el_codigo_como_parametro_get_code(): void
    {
        $codigo = $this->autorizacion->refresh()->codigo->codigo;

        $this->assertSame("https://jichi.beni.gob.bo/verificar?code={$codigo}", QrVerificacion::de($this->autorizacion)['url']);
    }

    public function test_encuentra_el_documento_con_la_url_nueva_y_con_la_de_lo_ya_impreso(): void
    {
        $plano = $this->autorizacion->refresh()->codigo->codigo;
        $legible = $this->autorizacion->codigo_legible;

        foreach (["/verificar?code={$plano}", "/verificar?code={$legible}", "/verificar/{$plano}", "/verificar/{$legible}"] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
                ->where('encontrado', true)
                ->where('codigo', $plano));
        }
    }

    public function test_sin_codigo_es_el_formulario_y_uno_inventado_no_se_encuentra(): void
    {
        $this->get('/verificar')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('encontrado', null));
        $this->get('/verificar?code=AAAABBBBCCCCDDDD')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('encontrado', false));
    }

    public function test_el_formulario_acepta_la_url_pegada_y_lleva_a_la_forma_nueva(): void
    {
        $plano = $this->autorizacion->refresh()->codigo->codigo;

        $this->post('/verificar', ['codigo' => "https://jichi.beni.gob.bo/verificar?code={$plano}"])
            ->assertRedirect("/verificar?code={$plano}");
    }
}
