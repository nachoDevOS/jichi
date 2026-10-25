<?php

namespace Tests\Feature\Carnets;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Services\EmitirCarnetService;
use App\Services\RevisarCarnetService;
use App\Support\TramitesDisponibles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/** El carnet de pescador pide la autorización APROBADA: pendiente de pago no alcanza (26/10/2026). */
class AutorizacionAprobadaTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private Asociacion $asociacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        $this->asociacion = Asociacion::query()->create(['nombre' => 'Asociación de prueba', 'sigla' => 'ADP']);
    }

    public function test_con_la_autorizacion_pendiente_no_se_registra_el_carnet(): void
    {
        $persona = Beneficiario::factory()->create();
        $pendiente = $this->autorizacion($persona, 'pendiente');

        foreach ([null, $pendiente] as $elegida) {
            try {
                app(EmitirCarnetService::class)->emitir($persona, $this->asociacion, TipoActor::Pescador, cupoElegido: $elegida);
                $this->fail('registró un carnet sobre una autorización sin pagar');
            } catch (CarnetInvalidoException $e) {
                $this->assertStringContainsString('aprobada', $e->getMessage());
            }
        }

        $this->assertSame(0, Carnet::query()->count());
    }

    public function test_con_la_autorizacion_aprobada_si_se_registra(): void
    {
        $persona = Beneficiario::factory()->create();
        $aprobada = $this->autorizacion($persona, 'aprobado');

        $carnet = app(EmitirCarnetService::class)->emitir($persona, $this->asociacion, TipoActor::Pescador);

        $this->assertSame($aprobada->id, $carnet->aprovechamiento_id);
    }

    public function test_un_carnet_viejo_sobre_una_pendiente_no_se_aprueba(): void
    {
        $persona = Beneficiario::factory()->create();
        $carnet = Carnet::query()->create([
            'beneficiario_id' => $persona->id, 'asociacion_id' => $this->asociacion->id,
            'aprovechamiento_id' => $this->autorizacion($persona, 'pendiente')->id,
            'tipo_actor' => 'pescador', 'monto' => 15, 'estado' => EstadoCarnet::Pendiente,
            'fecha_solicitud' => now()->toDateString(), 'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        $this->expectException(CarnetInvalidoException::class);
        app(RevisarCarnetService::class)->aprobar($carnet);
    }

    public function test_el_portal_no_ofrece_el_carnet_hasta_que_se_apruebe_la_autorizacion(): void
    {
        $persona = Beneficiario::factory()->create();
        $autorizacion = $this->autorizacion($persona, 'pendiente');

        $carnet = $this->carnetDePescadorEnPortal($persona);
        $this->assertFalse($carnet['disponible']);
        $this->assertStringContainsString('aprobarse', $carnet['detalle']);

        $autorizacion->update(['estado' => 'aprobado']);
        $this->assertTrue($this->carnetDePescadorEnPortal($persona)['disponible']);
    }

    private function carnetDePescadorEnPortal(Beneficiario $persona): array
    {
        return collect(TramitesDisponibles::para($persona))->firstOrFail(fn ($t) => $t['titulo'] === 'Carnet de pescador');
    }

    private function autorizacion(Beneficiario $b, string $estado): AprovechamientoPesq
    {
        static $nro = 0;

        return AprovechamientoPesq::query()->create([
            'beneficiario_id' => $b->id, 'nro' => ++$nro, 'escala' => '801 kg hasta 1000 kg',
            'volumen_total_kg' => 1000, 'kilos_disponibles' => 1000, 'monto' => 660, 'tipo_embarcacion' => 'Canoa',
            'estado' => $estado, 'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => $estado === 'aprobado' ? now()->toDateString() : null,
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);
    }
}
