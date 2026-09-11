<?php

namespace Tests\Feature;

use App\Enums\RolSistema;
use App\Http\Requests\Panel\GuardarUsuarioRequest;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pruebas de GuardarUsuarioRequest, las reglas del alta y edición de
 * funcionarios.
 *
 * OJO: el módulo Usuarios todavía no tiene rutas ni controlador, así que estas
 * pruebas NO hacen peticiones HTTP como SolicitanteTest. Arman el Form Request
 * a mano y lo validan, que es exactamente lo que hará Laravel el día que exista
 * la pantalla. Cuando el módulo se construya, estas pruebas se pueden reescribir
 * como $this->post('/panel/usuarios', ...) sin cambiar ninguna expectativa.
 */
class GuardarUsuarioRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolPermisoSeeder::class,
            UsuarioSeeder::class,
        ]);
    }

    /**
     * Corre el Form Request tal como lo correría Laravel: normaliza, valida y
     * devuelve los datos limpios. Si algo falla, lanza ValidationException.
     *
     * @param  array<string, mixed>  $datos
     * @param  User|null  $editado  Funcionario que se edita (null = alta).
     * @param  User|null  $autenticado  Quién está guardando.
     * @return array<string, mixed>
     */
    private function validar(array $datos, ?User $editado = null, ?User $autenticado = null): array
    {
        $request = GuardarUsuarioRequest::create('/panel/usuarios', $editado ? 'PUT' : 'POST', $datos);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));

        if ($autenticado !== null) {
            $request->setUserResolver(fn () => $autenticado);
        }

        // Se simula el enlace del modelo por la ruta: es de donde rules() saca
        // el id que hay que excluir de las reglas de unicidad.
        $ruta = new Route([$editado ? 'PUT' : 'POST'], '/panel/usuarios/{usuario}', []);
        $ruta->bind($request);

        if ($editado !== null) {
            $ruta->setParameter('usuario', $editado);
        }

        $request->setRouteResolver(fn () => $ruta);
        $request->validateResolved();

        return $request->validated();
    }

    /** @param array<string, mixed> $datos */
    private function erroresDe(array $datos, ?User $editado = null, ?User $autenticado = null): array
    {
        try {
            $this->validar($datos, $editado, $autenticado);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function altaValida(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Carlos Ruiz',
            'email' => 'carlos.ruiz@beni.gob.bo',
            'ci' => '4567890',
            'cargo' => 'Operador de Ventanilla',
            'telefono' => '71234567',
            'activo' => true,
            'rol' => RolSistema::Operador->value,
            'password' => 'ventanilla2026',
            'password_confirmation' => 'ventanilla2026',
        ], $cambios);
    }

    public function test_el_alta_normaliza_el_correo_y_recorta_los_espacios(): void
    {
        $datos = $this->validar($this->altaValida([
            'name' => '  Carlos Ruiz  ',
            'email' => '  Carlos.Ruiz@Beni.Gob.Bo ',
        ]));

        $this->assertSame('Carlos Ruiz', $datos['name']);
        $this->assertSame('carlos.ruiz@beni.gob.bo', $datos['email']);
    }

    public function test_el_alta_exige_contrasena(): void
    {
        $errores = $this->erroresDe($this->altaValida([
            'password' => null,
            'password_confirmation' => null,
        ]));

        $this->assertArrayHasKey('password', $errores);
    }

    public function test_la_contrasena_debe_venir_confirmada(): void
    {
        $errores = $this->erroresDe($this->altaValida([
            'password_confirmation' => 'otra-cosa-2026',
        ]));

        $this->assertArrayHasKey('password', $errores);
    }

    public function test_el_rol_tiene_que_existir_en_el_enum(): void
    {
        $errores = $this->erroresDe($this->altaValida(['rol' => 'jefazo']));

        $this->assertArrayHasKey('rol', $errores);
    }

    public function test_al_editar_la_contrasena_en_blanco_significa_dejarla_como_esta(): void
    {
        $supervisor = User::role(RolSistema::Supervisor->value)->firstOrFail();

        $datos = $this->validar([
            'name' => $supervisor->name,
            'email' => $supervisor->email,
            'cargo' => 'Jefe de Unidad de Recaudaciones',
            'activo' => true,
            'rol' => RolSistema::Supervisor->value,
            'password' => '',
        ], $supervisor);

        $this->assertNull($datos['password']);
    }

    public function test_el_correo_de_otra_cuenta_no_se_puede_reutilizar(): void
    {
        $errores = $this->erroresDe($this->altaValida([
            'email' => 'supervisor@beni.gob.bo',
        ]));

        $this->assertArrayHasKey('email', $errores);
    }

    /**
     * Documenta a propósito el límite que explica el comentario del Request:
     * `users.email` tiene índice único COMPLETO, así que un funcionario dado de
     * baja sigue ocupando su correo. Si algún día se cambia por un índice
     * parcial, esta prueba es la que hay que dar vuelta.
     */
    public function test_el_correo_de_un_funcionario_dado_de_baja_sigue_ocupado(): void
    {
        $consulta = User::role(RolSistema::SoloLectura->value)->firstOrFail();
        $consulta->delete();

        $errores = $this->erroresDe($this->altaValida(['email' => 'consulta@beni.gob.bo']));

        $this->assertArrayHasKey('email', $errores);
    }

    /** La cédula, en cambio, sí se libera al dar de baja al funcionario. */
    public function test_la_cedula_de_un_funcionario_dado_de_baja_se_puede_reutilizar(): void
    {
        $operador = User::role(RolSistema::Operador->value)->firstOrFail();
        $operador->update(['ci' => '4567890']);
        $operador->delete();

        $datos = $this->validar($this->altaValida(['ci' => '4567890']));

        $this->assertSame('4567890', $datos['ci']);
    }

    public function test_un_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = User::role(RolSistema::Administrador->value)->firstOrFail();

        $errores = $this->erroresDe([
            'name' => $admin->name,
            'email' => $admin->email,
            'activo' => false,
            'rol' => RolSistema::Administrador->value,
        ], $admin, $admin);

        $this->assertArrayHasKey('activo', $errores);
    }

    public function test_no_se_puede_dejar_al_sistema_sin_administrador(): void
    {
        $admin = User::role(RolSistema::Administrador->value)->firstOrFail();
        $supervisor = User::role(RolSistema::Supervisor->value)->firstOrFail();

        // El supervisor degrada al único administrador que queda.
        $errores = $this->erroresDe([
            'name' => $admin->name,
            'email' => $admin->email,
            'activo' => true,
            'rol' => RolSistema::Operador->value,
        ], $admin, $supervisor);

        $this->assertArrayHasKey('rol', $errores);
    }

    public function test_con_otro_administrador_en_pie_si_se_puede_degradar(): void
    {
        $admin = User::role(RolSistema::Administrador->value)->firstOrFail();

        $segundo = User::create([
            'name' => 'Segundo Administrador',
            'email' => 'admin2@beni.gob.bo',
            'password' => 'contrasena2026',
            'activo' => true,
        ]);
        $segundo->syncRoles([RolSistema::Administrador->value]);

        $datos = $this->validar([
            'name' => $admin->name,
            'email' => $admin->email,
            'activo' => true,
            'rol' => RolSistema::Supervisor->value,
        ], $admin, $segundo);

        $this->assertSame(RolSistema::Supervisor->value, $datos['rol']);
    }

    public function test_si_hay_dominio_institucional_configurado_se_exige(): void
    {
        config(['jichi.dominio_institucional' => 'beni.gob.bo']);

        $this->assertArrayHasKey('email', $this->erroresDe($this->altaValida([
            'email' => 'carlos.ruiz@gmail.com',
        ])));

        // Y el correo institucional sigue pasando.
        $datos = $this->validar($this->altaValida());
        $this->assertSame('carlos.ruiz@beni.gob.bo', $datos['email']);
    }
}
