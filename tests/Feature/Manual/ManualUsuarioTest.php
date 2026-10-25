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
