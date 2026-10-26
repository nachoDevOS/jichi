<?php

namespace Tests\Feature\Beneficiarios;

use App\Models\Beneficiario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * La nacionalidad vacía en el formulario llegaba como null y rompía el NOT NULL
 * de la columna con un 500: el null explícito pisa el default de la base.
 */
class NacionalidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_nacionalidad_se_registra_como_boliviana(): void
    {
        Permission::findOrCreate('beneficiarios.crear', 'web');
        Permission::findOrCreate('beneficiarios.ver', 'web');
        $usuario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $usuario->givePermissionTo(['beneficiarios.crear', 'beneficiarios.ver']);

        $this->actingAs($usuario)->post(route('beneficiarios.store'), [
            'ci' => '7633685',
            'primerNombre' => 'Ignacio',
            'apellidoPaterno' => 'Molina',
            'fechaNacimiento' => '1997-03-08',
            'nacionalidad' => '',
        ])->assertRedirect();

        $this->assertSame('Boliviana', Beneficiario::where('ci', '7633685')->value('nacionalidad'));
    }

    public function test_un_texto_mas_largo_que_la_columna_vuelve_como_error_del_formulario(): void
    {
        Permission::findOrCreate('beneficiarios.crear', 'web');
        $usuario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $usuario->givePermissionTo('beneficiarios.crear');

        $this->actingAs($usuario)->post(route('beneficiarios.store'), [
            'ci' => '7633685',
            'primerNombre' => 'Ignacio',
            'apellidoPaterno' => 'Molina',
            'fechaNacimiento' => '1997-03-08',
            'ciudad' => str_repeat('a', 81),
        ])->assertSessionHasErrors('ciudad');

        $this->assertSame(0, Beneficiario::count());
    }
}
