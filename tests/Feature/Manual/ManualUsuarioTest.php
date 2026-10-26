<?php

namespace Tests\Feature\Manual;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Manual de usuario: cada video se ofrece y se sirve solo a quien tiene el
 * permiso del trámite que muestra.
 */
class ManualUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_el_permiso_ve_el_tutorial_y_el_video_responde_por_rangos(): void
    {
        $usuario = $this->usuario('beneficiarios.crear');

        $this->actingAs($usuario)->getJson(route('manual.index'))
            ->assertOk()
            ->assertJsonPath('0.clave', 'registrar-beneficiario');

        // El reproductor salta a un capítulo pidiendo un rango: tiene que volver 206.
        $this->actingAs($usuario)
            ->get(route('manual.archivo', ['registrar-beneficiario', 'mp4']), ['Range' => 'bytes=0-99'])
            ->assertStatus(206);
    }

    public function test_cada_tramite_se_ofrece_a_quien_lo_puede_hacer(): void
    {
        $usuario = $this->usuario('aprovechamientos.crear');

        $this->actingAs($usuario)->getJson(route('manual.index'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.clave', 'autorizacion-pesca')
            // El carnet sigue en el recorrido, pero este rol no lo puede ver: no se ofrece.
            ->assertJsonPath('0.siguiente', null);
        $this->actingAs($usuario)
            ->get(route('manual.archivo', ['autorizacion-pesca', 'mp4']), ['Range' => 'bytes=0-99'])
            ->assertStatus(206);
    }

    public function test_la_vista_previa_de_la_tarjeta_se_sirve_con_el_mismo_permiso(): void
    {
        $usuario = $this->usuario('guias.crear');

        $previa = $this->actingAs($usuario)->getJson(route('manual.index'))
            ->assertOk()
            ->assertJsonPath('0.clave', 'guia-transporte')
            ->json('0.previa');

        $this->actingAs($usuario)->get($previa)->assertOk();
        $this->actingAs($usuario)->get(route('manual.archivo', ['autorizacion-pesca', 'previa.mp4']))->assertForbidden();
    }

    public function test_la_pantalla_final_ofrece_el_siguiente_tramite_que_el_rol_puede_ver(): void
    {
        Permission::findOrCreate('carnets.crear', 'web');
        $usuario = $this->usuario('aprovechamientos.crear');
        $usuario->givePermissionTo('carnets.crear');

        $this->actingAs($usuario)->getJson(route('manual.index'))
            ->assertOk()
            ->assertJsonPath('0.siguiente', ['clave' => 'carnet-pescador', 'titulo' => 'Carnet de pescador']);
    }

    public function test_sin_el_permiso_no_lo_ve_ni_lo_puede_bajar(): void
    {
        $usuario = $this->usuario('beneficiarios.ver');

        $this->actingAs($usuario)->getJson(route('manual.index'))->assertOk()->assertExactJson([]);
        $this->actingAs($usuario)->get(route('manual.archivo', ['registrar-beneficiario', 'mp4']))->assertForbidden();
    }

    public function test_un_tutorial_que_no_existe_da_404(): void
    {
        $this->actingAs($this->usuario('beneficiarios.crear'))
            ->get('/panel/manual/no-existe.mp4')
            ->assertNotFound();
    }

    private function usuario(string $permiso): User
    {
        Permission::findOrCreate($permiso, 'web');
        $usuario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $usuario->givePermissionTo($permiso);

        return $usuario;
    }
}
