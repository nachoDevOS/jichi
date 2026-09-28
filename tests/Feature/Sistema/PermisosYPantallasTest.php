<?php

namespace Tests\Feature\Sistema;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * La seguridad real está en las rutas (regla 5 de CLAUDE.md): sin sesión no se
 * entra al panel, y sin el permiso la ruta responde 403 aunque el botón no se vea.
 */
class PermisosYPantallasTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function listados(): array
    {
        return [
            'panel' => ['dashboard'],
            'beneficiarios' => ['beneficiarios.index'],
            'autorizaciones' => ['aprovechamientos.index'],
            'carnets' => ['carnets.index'],
            'faenas' => ['faenas.index'],
            'guías' => ['guias.index'],
            'caja' => ['caja.index'],
            'recibos' => ['recibos.index'],
            'asociaciones' => ['asociaciones.index'],
            'escala' => ['categorias-aprovechamiento.index'],
            'tipos de carnet' => ['tipos-carnet.index'],
            'productos' => ['productos.index'],
        ];
    }

    #[Test]
    #[DataProvider('listados')]
    public function el_administrador_abre_cada_pantalla(string $ruta): void
    {
        $this->sembrarYEntrar();

        $this->get(route($ruta))->assertOk();
    }

    #[Test]
    #[DataProvider('listados')]
    public function sin_sesion_se_va_al_login(string $ruta): void
    {
        $this->get(route($ruta))->assertRedirect(route('login'));
    }

    #[Test]
    #[DataProvider('listados')]
    public function un_usuario_sin_permisos_recibe_403(string $ruta): void
    {
        $this->sembrarYEntrar();
        $this->actingAs($this->usuarioSinRol());

        $this->get(route($ruta))->assertForbidden();
    }

    #[Test]
    public function sin_permiso_tampoco_se_escribe_aunque_se_arme_la_peticion_a_mano(): void
    {
        $this->sembrarYEntrar();
        $this->actingAs($this->usuarioSinRol());

        $this->post(route('beneficiarios.store'), ['ci' => '1234567'])->assertForbidden();
        $this->post(route('productos.store'), ['nombre' => 'Pirata', 'precio_kg' => 1])->assertForbidden();
        $this->post(route('caja.store'), [])->assertForbidden();
    }

    #[Test]
    public function los_tipos_de_carnet_no_tienen_alta_ni_por_ruta(): void
    {
        $this->sembrarYEntrar();

        $this->post('/panel/catalogos/tipos-carnet', ['nombre' => 'Otro'])->assertStatus(405);
    }

    #[Test]
    public function la_portada_publica_abre_sin_sesion(): void
    {
        $this->sembrarYEntrar();
        auth()->logout();

        $this->get(route('inicio'))->assertOk();
        $this->get(route('verificar.show'))->assertOk();
    }

    private function usuarioSinRol(): User
    {
        return User::query()->create([
            'name' => 'Sin permisos',
            'email' => 'sinpermisos@prueba.bo',
            'password' => bcrypt('secreto'),
            'activo' => true,
        ]);
    }
}
