<?php

namespace Tests\Feature\Portal;

use App\Jobs\VerificarPagoJob;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\ApiApp\PedirFaenaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Pedir permiso de faena» en el portal web /mi-cuenta (19/11/2026), con las reglas
 * del panel. Y que el panel siga mostrando su arancel tras moverlo al servicio.
 */
class FaenaEnLineaTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);
        // Con `composer run dev` corriendo, Inertia intentaría el SSR contra Vite.
        config(['inertia.ssr.enabled' => false]);
        $this->withoutVite();

        $this->cuenta = $this->beneficiarioConCuenta();
    }

    public function test_el_formulario_muestra_lo_que_va_a_pedir(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario, kilos: 750);

        $this->actingAs($this->cuenta)->get(route('portal.faenas.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('portal/faena')
                ->where('puede', true)
                ->where('kilos', 750)
                ->where('arancel.monto', 15)
                ->where('propietario_sugerido', 'Ignacio Molina Guzman'));
    }

    public function test_pedirla_la_registra_y_lleva_a_en_curso(): void
    {
        $carnet = $this->pescadorListo($this->cuenta->beneficiario);

        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), self::TALONARIO)
            ->assertRedirect(route('portal.en-curso'))
            ->assertSessionHas('exito');

        $this->assertSame($carnet->id, PermisoFaena::query()->sole()->carnet_id);
    }

    public function test_sin_los_renglones_vuelve_con_los_errores(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), [])
            ->assertSessionHasErrors(array_keys(self::TALONARIO));
        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_sin_carnet_de_pescador_vuelve_con_el_motivo(): void
    {
        $this->actingAs($this->cuenta)->post(route('portal.faenas.store'), self::TALONARIO)
            ->assertSessionHasErrors(['faena' => PedirFaenaService::SIN_CARNET]);
        $this->assertSame(0, PermisoFaena::query()->count());
    }

    public function test_el_inicio_web_marca_la_faena_en_linea(): void
    {
        $this->pescadorListo($this->cuenta->beneficiario);

        $this->actingAs($this->cuenta)->get(route('portal.inicio'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('tramites_disponibles.0.clase', 'faena')
                ->where('tramites_disponibles.0.canal', 'en_linea'));
    }

    public function test_un_funcionario_no_entra_al_formulario_del_portal(): void
    {
        $funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);

        $this->actingAs($funcionario)->get(route('portal.faenas.create'))->assertRedirect();
    }

    public function test_el_panel_sigue_mostrando_el_arancel_de_la_faena(): void
    {
        Permission::findOrCreate('faenas.crear', 'web');
        $funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $funcionario->givePermissionTo('faenas.crear');

        $this->actingAs($funcionario)->get(route('faenas.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('panel/faenas/crear')
                ->where('arancel.monto', 15)
                ->where('sinArancel', null));
    }
}
