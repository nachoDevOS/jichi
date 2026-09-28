<?php

namespace Tests\Feature\Aprovechamientos;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\ModalidadAprovechamiento;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Exceptions\CupoInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CategoriaAprovechamiento;
use App\Models\PermisoFaena;
use App\Models\TipoCarnet;
use App\Models\User;
use App\Services\EmitirCarnetService;
use App\Services\EmitirFaenaService;
use App\Services\OtorgarCupoService;
use App\Services\RevisarCarnetService;
use App\Services\RevisarCupoService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las reglas de la Autorización de Pesca para Aprovechamiento Pesquero:
 * una vigente por persona, y la revocación con lo que arrastra.
 * La especificación está en docs/REGLAS-NEGOCIO.md.
 */
class RevocacionTest extends TestCase
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

    //  Una autorización vigente por persona

    #[Test]
    public function no_se_otorga_otra_con_una_aprobada_y_vigente(): void
    {
        $this->cupo(EstadoAprovechamiento::Aprobado);

        $this->expectException(CupoInvalidoException::class);
        $this->otorgar();
    }

    #[Test]
    public function no_se_otorga_otra_con_una_pendiente(): void
    {
        $this->cupo(EstadoAprovechamiento::Pendiente);

        $this->expectException(CupoInvalidoException::class);
        $this->otorgar();
    }

    #[Test]
    public function se_otorga_otra_si_la_anterior_aprobada_ya_no_esta_en_fecha(): void
    {
        // Dice «aprobado» en la columna, pero su fecha pasó: ya no está vigente.
        $this->cupo(EstadoAprovechamiento::Aprobado, vence: now()->subDay()->toDateString());

        $this->assertSame(EstadoAprovechamiento::Pendiente, $this->otorgar()->estado);
    }

    #[Test]
    public function se_otorga_otra_si_la_anterior_esta_vencida_agotada_o_revocada(): void
    {
        foreach ([EstadoAprovechamiento::Vencido, EstadoAprovechamiento::Agotado, EstadoAprovechamiento::Revocado] as $estado) {
            $persona = Beneficiario::factory()->create();
            $this->cupo($estado, persona: $persona);

            $this->assertSame(EstadoAprovechamiento::Pendiente, $this->otorgar($persona)->estado, "Con la anterior {$estado->value}");
        }
    }

    //  Revocar

    #[Test]
    public function revocar_exige_motivo(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);

        $this->expectException(CupoInvalidoException::class);
        app(RevisarCupoService::class)->revocar($cupo, '   ');
    }

    #[Test]
    public function solo_se_revoca_una_aprobada_o_agotada(): void
    {
        foreach ([EstadoAprovechamiento::Pendiente, EstadoAprovechamiento::EnRevision, EstadoAprovechamiento::Vencido] as $estado) {
            $cupo = $this->cupo($estado, persona: Beneficiario::factory()->create());

            try {
                app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');
                $this->fail("Se revocó una autorización {$estado->value}");
            } catch (CupoInvalidoException) {
                $this->assertSame($estado, $cupo->fresh()->estado);
            }
        }

        $agotada = $this->cupo(EstadoAprovechamiento::Agotado);
        $this->assertSame(EstadoAprovechamiento::Revocado, app(RevisarCupoService::class)->revocar($agotada, 'Motivo de prueba suficiente')->estado);
    }

    #[Test]
    public function no_se_revoca_dos_veces(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        app(RevisarCupoService::class)->revocar($cupo, 'Primera revocación de prueba');

        $this->expectException(CupoInvalidoException::class);
        app(RevisarCupoService::class)->revocar($cupo->fresh(), 'Segunda revocación de prueba');
    }

    #[Test]
    public function revocar_no_reescribe_carnets_ni_faenas_pero_los_deja_sin_efecto(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $carnet = $this->carnet($cupo, EstadoCarnet::Aprobado);
        $faena = $this->faena($carnet, EstadoFaena::Aprobado, desembarque: now()->addDays(10)->toDateString());
        $this->assertTrue($carnet->estaVigente());
        $this->assertTrue($faena->fresh()->estaVigente());

        app(RevisarCupoService::class)->revocar($cupo, 'Resolución de prueba: pesca en veda');

        $this->assertSame(EstadoAprovechamiento::Revocado, $cupo->fresh()->estado);

        // El estado guardado NO cambia: la vigencia se calcula mirando al padre.
        $carnet = $carnet->fresh();
        $faena = $faena->fresh();
        $this->assertSame(EstadoCarnet::Aprobado, $carnet->estado);
        $this->assertSame(EstadoFaena::Aprobado, $faena->estado);

        $this->assertFalse($carnet->estaVigente());
        $this->assertFalse($faena->estaVigente());
        $this->assertTrue($carnet->sinEfecto());
        $this->assertTrue($faena->sinEfecto());
        $this->assertSame('Sin efecto', $carnet->etiquetaEstado());
        $this->assertSame('Sin efecto', $faena->etiquetaEstado());
        $this->assertStringContainsString('fue revocada', (string) $carnet->motivoSinPermisos());
        $this->assertStringContainsString('fue revocada', (string) $faena->motivoSinAutorizar());

        // Tampoco cuentan como vigentes en las consultas.
        $this->assertFalse(Carnet::query()->vigentes()->whereKey($carnet->id)->exists());
        $this->assertFalse(PermisoFaena::query()->vigentes()->whereKey($faena->id)->exists());

        // Nada se escribió en ellos: la auditoría solo tiene la revocación de la autorización.
        foreach ([$carnet, $faena] as $doc) {
            $this->assertSame(0, DB::table('auditorias')
                ->where('auditable_type', $doc->getMorphClass())
                ->where('auditable_id', $doc->id)
                ->where('evento', '!=', 'creado')
                ->count(), class_basename($doc).' fue reescrito');
        }
    }

    #[Test]
    public function revocar_no_toca_lo_vencido_ni_lo_que_no_esta_firmado(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $vigente = $this->carnet($cupo, EstadoCarnet::Aprobado);
        $faenaTerminada = $this->faena($vigente, EstadoFaena::Aprobado, desembarque: now()->subDay()->toDateString());
        $faenaPendiente = $this->faena($vigente, EstadoFaena::Pendiente, desembarque: null);
        // Aprobado en la columna pero con la fecha pasada: el comando diario todavía no lo marcó.
        $carnetVencido = $this->carnet($cupo, EstadoCarnet::Aprobado, vence: now()->subDay()->toDateString());
        $carnetPendiente = $this->carnet($cupo, EstadoCarnet::Pendiente);

        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');

        $this->assertSame(EstadoFaena::Aprobado, $faenaTerminada->fresh()->estado);
        $this->assertSame(EstadoFaena::Pendiente, $faenaPendiente->fresh()->estado);
        $this->assertSame(EstadoCarnet::Aprobado, $carnetVencido->fresh()->estado);
        $this->assertSame(EstadoCarnet::Pendiente, $carnetPendiente->fresh()->estado);
    }

    #[Test]
    public function la_faena_de_un_carnet_repuesto_antes_tambien_queda_sin_efecto(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $repuesto = $this->carnet($cupo, EstadoCarnet::Revocado);
        $faena = $this->faena($repuesto, EstadoFaena::Aprobado, desembarque: now()->addDays(5)->toDateString());
        // El carnet que lo reemplazó, con la misma autorización: ampara la faena.
        $this->carnet($cupo, EstadoCarnet::Aprobado);
        $this->assertTrue($faena->fresh()->estaVigente(), 'con el carnet nuevo, la faena del repuesto vale');

        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');

        $this->assertSame(EstadoFaena::Aprobado, $faena->fresh()->estado);
        $this->assertFalse($faena->fresh()->estaVigente());
    }

    #[Test]
    public function el_qr_de_un_carnet_o_una_faena_sin_efecto_dice_que_no_esta_vigente(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $carnet = $this->carnet($cupo, EstadoCarnet::Aprobado);
        $faena = $this->faena($carnet, EstadoFaena::Aprobado, desembarque: now()->addDays(5)->toDateString());
        $carnet->asignarCodigo();
        $faena->asignarCodigo();

        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');
        auth()->logout();

        foreach ([$carnet, $faena] as $doc) {
            $this->get(route('verificar.show', ['codigo' => $doc->fresh()->codigo_legible]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('documento.vigente', false)
                    ->where('documento.estado_etiqueta', 'Sin efecto')
                    ->where('documento.mensaje', fn ($m) => str_contains($m, 'fue revocada')));
        }
    }

    #[Test]
    public function la_faena_de_un_carnet_perdido_vale_solo_cuando_se_aprueba_el_carnet_nuevo(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $perdido = $this->carnet($cupo, EstadoCarnet::Aprobado);
        $faena = $this->faena($perdido, EstadoFaena::Aprobado, desembarque: now()->addDays(10)->toDateString());
        $this->assertTrue($faena->fresh()->estaVigente());

        // Se revoca el carnet perdido y todavía no hay otro.
        app(EmitirCarnetService::class)->revocar($perdido, 'Reposición: carnet extraviado');
        $faena = $faena->fresh();
        $this->assertSame(EstadoFaena::Aprobado, $faena->estado, 'no se reescribe');
        $this->assertFalse($faena->estaVigente());
        $this->assertTrue($faena->sinEfecto());
        $this->assertStringContainsString('carnet de pescador vigente', (string) $faena->motivoSinEfecto());
        $this->assertFalse(PermisoFaena::query()->vigentes()->whereKey($faena->id)->exists());

        // El nuevo registrado pero sin aprobar todavía no ampara.
        $nuevo = $this->carnet($cupo, EstadoCarnet::Pendiente);
        $this->assertFalse($faena->fresh()->estaVigente());

        // Aprobado el nuevo, la faena vuelve a valer sola.
        $nuevo->update(['estado' => EstadoCarnet::Aprobado]);
        $this->assertTrue($faena->fresh()->estaVigente());
        $this->assertTrue(PermisoFaena::query()->vigentes()->whereKey($faena->id)->exists());
    }

    //  Lo que ya no se puede hacer con una revocada

    #[Test]
    public function con_una_revocada_no_se_emite_carnet(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Revocado);

        $this->expectException(CarnetInvalidoException::class);
        app(EmitirCarnetService::class)->emitir(
            $this->persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail(),
            TipoActor::Pescador,
            null,
            $cupo,
        );
    }

    #[Test]
    public function no_se_aprueba_un_carnet_cuya_autorizacion_revocaron_mientras_esperaba(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $carnet = $this->carnet($cupo, EstadoCarnet::EnRevision);
        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');

        $this->expectException(CarnetInvalidoException::class);
        $this->expectExceptionMessage('fue revocada');
        app(RevisarCarnetService::class)->aprobar($carnet);
    }

    #[Test]
    public function con_una_revocada_no_se_emite_faena_aunque_el_carnet_siga_aprobado(): void
    {
        // Carnet aprobado sobre una revocada: no debería existir, pero si existe no emite.
        $cupo = $this->cupo(EstadoAprovechamiento::Revocado);
        $carnet = $this->carnet($cupo, EstadoCarnet::Aprobado);

        $this->assertFalse($carnet->puedeEmitirFaenas());
        $this->assertStringContainsString('fue revocada', (string) $carnet->motivoSinPermisos());

        $this->expectException(PermisoOperativoException::class);
        $this->expectExceptionMessage('fue revocada');
        app(EmitirFaenaService::class)->emitir($carnet, 10);
    }

    #[Test]
    public function en_modo_flexible_tampoco_emite_faenas(): void
    {
        config(['jichi.aprovechamiento.estricto' => false]);
        $cupo = $this->cupo(EstadoAprovechamiento::Revocado);

        $this->assertFalse($cupo->puedeEmitirFaena());
        $this->assertSame('No autoriza faenas: la autorización fue revocada.', $cupo->motivoSinFaena());
    }

    #[Test]
    public function recalcular_el_saldo_no_revive_una_revocada(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Revocado);

        $cupo->sincronizarEstadoPorSaldo();

        $this->assertSame(EstadoAprovechamiento::Revocado, $cupo->fresh()->estado);
    }

    #[Test]
    public function lo_revocado_no_se_imprime(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $faena = $this->faena($this->carnet($cupo, EstadoCarnet::Aprobado), EstadoFaena::Aprobado, desembarque: now()->addDays(3)->toDateString());
        $this->assertTrue($cupo->puedeImprimirse());
        $this->assertTrue($faena->puedeImprimirse());

        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');

        $this->assertFalse($cupo->fresh()->puedeImprimirse());
        $this->assertFalse($faena->fresh()->puedeImprimirse());
        $this->get(route('aprovechamientos.autorizacion', $cupo))->assertRedirect();
        $this->get(route('faenas.imprimir', $faena))->assertRedirect();
    }

    #[Test]
    public function despues_de_revocar_se_vuelve_a_empezar_con_autorizacion_y_carnet_nuevos(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $this->carnet($cupo, EstadoCarnet::Aprobado);
        app(RevisarCupoService::class)->revocar($cupo, 'Motivo de prueba suficiente');

        $nuevo = $this->otorgar();
        $carnet = app(EmitirCarnetService::class)->emitir(
            $this->persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail(),
            TipoActor::Pescador,
            null,
            $nuevo,
        );

        $this->assertSame($nuevo->id, $carnet->aprovechamiento_id);
    }

    //  La ruta

    #[Test]
    public function la_ruta_revoca_y_valida_el_motivo(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);

        $this->patch(route('aprovechamientos.revocar', $cupo), ['motivo' => 'corto'])
            ->assertSessionHasErrors('motivo');
        $this->assertSame(EstadoAprovechamiento::Aprobado, $cupo->fresh()->estado);

        $this->patch(route('aprovechamientos.revocar', $cupo), ['motivo' => 'Resolución SEDAG de prueba'])
            ->assertRedirect(route('aprovechamientos.show', $cupo));
        $this->assertSame(EstadoAprovechamiento::Revocado, $cupo->fresh()->estado);
    }

    #[Test]
    public function sin_el_permiso_no_se_revoca(): void
    {
        $cupo = $this->cupo(EstadoAprovechamiento::Aprobado);
        $sinRol = User::query()->create([
            'name' => 'Sin rol',
            'email' => 'sinrol@prueba.test',
            'password' => bcrypt('secreto-de-prueba'),
        ]);

        $this->actingAs($sinRol)
            ->patch(route('aprovechamientos.revocar', $cupo), ['motivo' => 'Intento sin permiso'])
            ->assertForbidden();
        $this->assertSame(EstadoAprovechamiento::Aprobado, $cupo->fresh()->estado);
    }

    //  Datos de prueba

    private function otorgar(?Beneficiario $persona = null): AprovechamientoPesq
    {
        return app(OtorgarCupoService::class)->otorgar(
            $persona ?? $this->persona,
            CategoriaAprovechamiento::query()->firstOrFail(),
            'Canoa',
        );
    }

    private function cupo(EstadoAprovechamiento $estado, ?string $vence = null, ?Beneficiario $persona = null): AprovechamientoPesq
    {
        $categoria = CategoriaAprovechamiento::query()->firstOrFail();

        return AprovechamientoPesq::query()->create([
            'beneficiario_id' => ($persona ?? $this->persona)->id,
            'categoria_aprov_id' => $categoria->id,
            'modalidad' => ModalidadAprovechamiento::EscalaGeneral,
            'volumen_total_kg' => $categoria->kilos_max,
            'tipo_embarcacion' => 'Canoa',
            'estado' => $estado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => $vence ?? now()->endOfYear()->toDateString(),
        ]);
    }

    private function carnet(AprovechamientoPesq $cupo, EstadoCarnet $estado, ?string $vence = null): Carnet
    {
        static $registro = 0;

        return Carnet::query()->create([
            'beneficiario_id' => $cupo->beneficiario_id,
            'asociacion_id' => Asociacion::query()->firstOrFail()->id,
            'tipo_carnet_id' => TipoCarnet::query()->where('tipo_actor', TipoActor::Pescador)->firstOrFail()->id,
            'aprovechamiento_id' => $cupo->id,
            'tipo_actor' => TipoActor::Pescador,
            'nro_registro' => ++$registro,
            'estado' => $estado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_emision' => now()->toDateString(),
            'fecha_vencimiento' => $vence ?? now()->endOfYear()->toDateString(),
        ]);
    }

    private function faena(Carnet $carnet, EstadoFaena $estado, ?string $desembarque): PermisoFaena
    {
        static $numero = 0;

        return PermisoFaena::query()->create([
            'carnet_id' => $carnet->id,
            'numero_faena' => ++$numero,
            'monto' => 15,
            'kilos_extraidos' => 10,
            'estado' => $estado,
            'fecha_solicitud' => now()->toDateString(),
            'fecha_salida' => $desembarque ? now()->toDateString() : null,
            'fecha_desembarque' => $desembarque,
        ]);
    }
}
