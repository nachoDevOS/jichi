<?php

namespace Tests\Feature\Aprovechamientos;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\ModalidadAprovechamiento;
use App\Enums\TipoActor;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CategoriaAprovechamiento;
use App\Models\PermisoFaena;
use App\Models\TipoCarnet;
use App\Models\User;
use App\Services\EmitirFaenaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La faena pendiente o en revisión RESERVA sus kilos sin descontarlos.
 * Autorización de 750 kg con 600 aprobados: el ejemplo de docs/REGLAS-NEGOCIO.md.
 */
class ReservaFaenaTest extends TestCase
{
    use RefreshDatabase;

    private AprovechamientoPesq $cupo;

    private Carnet $carnet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolPermisoSeeder::class, ConfiguracionSeeder::class, UsuarioSeeder::class, CatalogoSeeder::class]);
        $this->actingAs(User::query()->where('email', 'admin@admin.com')->firstOrFail());
        config(['jichi.aprovechamiento.estricto' => true]);

        $persona = Beneficiario::factory()->create();

        $this->cupo = AprovechamientoPesq::query()->create([
            'beneficiario_id' => $persona->id,
            'categoria_aprov_id' => CategoriaAprovechamiento::query()->firstOrFail()->id,
            'modalidad' => ModalidadAprovechamiento::EscalaGeneral,
            'volumen_total_kg' => 750,
            'tipo_embarcacion' => 'Canoa',
            'estado' => EstadoAprovechamiento::Aprobado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        $this->carnet = Carnet::query()->create([
            'beneficiario_id' => $persona->id,
            'asociacion_id' => Asociacion::query()->firstOrFail()->id,
            'tipo_carnet_id' => TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail()->id,
            'aprovechamiento_id' => $this->cupo->id,
            'tipo_actor' => TipoActor::Pescador,
            'nro_registro' => 1,
            'estado' => EstadoCarnet::Aprobado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => now()->endOfYear()->toDateString(),
        ]);

        // Las dos salidas ya firmadas: 600 kg consumidos, quedan 150.
        foreach ([300, 300] as $i => $kilos) {
            PermisoFaena::query()->create([
                'carnet_id' => $this->carnet->id,
                'numero_faena' => 900 + $i,
                'monto' => 15,
                'kilos_extraidos' => $kilos,
                'estado' => EstadoFaena::Aprobado,
                'fecha_solicitud' => now()->toDateString(),
                'fecha_salida' => now()->toDateString(),
                'fecha_desembarque' => now()->addDays(30)->toDateString(),
            ]);
        }
    }

    #[Test]
    public function la_pendiente_reserva_sin_descontar(): void
    {
        $this->emitir(150);
        $cupo = $this->cupo->fresh();

        $this->assertSame(600.0, $cupo->kilosConsumidos());
        $this->assertSame(150.0, $cupo->saldoKg());
        $this->assertSame(150.0, $cupo->kilosReservados());
        $this->assertSame(0.0, $cupo->libreKg());
        // Reservar no agota: la reserva todavía se puede eliminar.
        $this->assertSame(EstadoAprovechamiento::Aprobado, $cupo->estado);
    }

    #[Test]
    public function con_todo_reservado_no_se_registra_otra(): void
    {
        $this->emitir(150);

        try {
            $this->emitir(100);
            $this->fail('Se registró una faena sobre kilos reservados');
        } catch (PermisoOperativoException $e) {
            $this->assertStringContainsString('reservados', $e->getMessage());
        }

        $this->assertFalse($this->cupo->fresh()->puedeEmitirFaena());
        $this->assertStringContainsString('reservados', $this->cupo->fresh()->motivoSinFaena());
    }

    #[Test]
    public function la_en_revision_tambien_reserva(): void
    {
        $this->emitir(150)->update(['estado' => EstadoFaena::EnRevision]);

        $this->expectException(PermisoOperativoException::class);
        $this->emitir(50);
    }

    #[Test]
    public function eliminarla_libera_los_kilos_y_lo_libre_se_achica_con_la_nueva(): void
    {
        $a = $this->emitir(150);
        app(EmitirFaenaService::class)->eliminar($a, 'Cargada por error en ventanilla');

        $this->emitir(100);
        $this->assertSame(50.0, $this->cupo->fresh()->libreKg());

        $this->emitir(50);

        $this->expectException(PermisoOperativoException::class);
        $this->emitir(1);
    }

    #[Test]
    public function al_editar_cuenta_lo_que_ella_misma_reservaba(): void
    {
        $b = $this->emitir(100);
        $this->emitir(50);

        // Libre 0, pero sus propios 100 vuelven: puede quedarse en 100, no subir.
        app(EmitirFaenaService::class)->editar($b, 100);
        $this->assertSame(100.0, (float) $b->fresh()->kilos_extraidos);

        $this->expectException(PermisoOperativoException::class);
        app(EmitirFaenaService::class)->editar($b, 101);
    }

    #[Test]
    public function en_modo_flexible_la_reserva_no_frena(): void
    {
        config(['jichi.aprovechamiento.estricto' => false]);

        $this->emitir(150);
        $this->emitir(100);

        $this->assertSame(250.0, $this->cupo->fresh()->kilosReservados());
    }

    private function emitir(float $kilos): PermisoFaena
    {
        return app(EmitirFaenaService::class)->emitir($this->carnet->fresh(), $kilos);
    }
}
