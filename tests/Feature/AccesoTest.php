<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Models\Acceso;
use App\Models\Documento;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolPermisoSeeder::class,
            ConfiguracionSeeder::class,
            AreaSeeder::class,
            UsuarioSeeder::class,
            DemoSeeder::class,
        ]);
    }

    public function test_la_raiz_redirige_al_login_para_visitantes(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_login_se_renderiza(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_un_usuario_activo_inicia_sesion_y_queda_registrado_el_acceso(): void
    {
        $usuario = User::where('email', 'admin@admin.com')->firstOrFail();

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertDatabaseHas('accesos', ['user_id' => $usuario->id, 'evento' => 'login']);
        $this->assertNotNull($usuario->fresh()->ultimo_acceso_at);
    }

    public function test_un_usuario_dado_de_baja_no_puede_iniciar_sesion(): void
    {
        $usuario = User::where('email', 'consulta@beni.gob.bo')->firstOrFail();
        $usuario->update(['activo' => false]);

        $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('accesos', ['email' => $usuario->email, 'evento' => 'fallido']);
    }

    public function test_el_dashboard_carga_para_un_usuario_autenticado(): void
    {
        $usuario = User::role(RolSistema::Administrador->value)->firstOrFail();

        $this->actingAs($usuario)->get(route('dashboard'))->assertOk();
    }

    public function test_el_dashboard_exige_autenticacion(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_la_verificacion_publica_no_exige_login(): void
    {
        $documento = Documento::firstOrFail();

        $this->get('/verificar/'.$documento->codigo_verificacion)->assertOk();

        $this->assertSame(1, $documento->fresh()->veces_verificado);
    }

    public function test_un_codigo_inexistente_no_rompe_la_verificacion(): void
    {
        $this->get('/verificar/CODIGOFALSO123')->assertOk();
    }

    public function test_cerrar_sesion_registra_el_evento(): void
    {
        $usuario = User::where('email', 'ventanilla1@beni.gob.bo')->firstOrFail();

        $this->actingAs($usuario)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('accesos', ['user_id' => $usuario->id, 'evento' => 'logout']);
    }

    public function test_los_roles_sembrados_tienen_sus_permisos(): void
    {
        $operador = User::role(RolSistema::Operador->value)->firstOrFail();

        $this->assertTrue($operador->can('pagos.registrar'));
        $this->assertFalse($operador->can('tramites.aprobar'));

        $this->assertSame(0, Acceso::count());
    }
}
