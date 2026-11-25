<?php

namespace Tests\Feature\ApiApp;

use App\Models\Beneficiario;
use App\Models\User;
use App\Services\CuentaPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\PescadorDePrueba;
use Tests\TestCase;

/**
 * «Mi perfil» desde la app (vuelve el 25/11/2026): lo mismo que «Mis datos» del
 * portal web, y la foto por el token (la URL de APP_URL no la alcanza el celular).
 */
class PerfilTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase;

    private Beneficiario $ignacio;

    private User $cuenta;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ignacio = Beneficiario::create([
            'ci' => '7633685',
            'primerNombre' => 'Ignacio',
            'apellidoPaterno' => 'Molina',
            'apellidoMaterno' => 'Guzman',
            'fechaNacimiento' => '1997-03-08',
            'telefono' => '67285914',
            'ciudad' => 'Trinidad',
        ]);
        $servicio = app(CuentaPortalService::class);
        $servicio->darAcceso($this->ignacio);
        $this->cuenta = $this->ignacio->cuenta()->firstOrFail();
        $servicio->cambiarClave($this->cuenta, 'pesca2026');
        $this->token = $this->cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_devuelve_sus_datos_sin_la_url_de_la_foto(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/portal/perfil')
            ->assertOk()
            ->assertJsonPath('perfil.datos.nombre', 'Ignacio Molina Guzman')
            ->assertJsonPath('perfil.datos.telefono', '67285914')
            ->assertJsonPath('perfil.datos.fecha_nacimiento', '1997-03-08')
            ->assertJsonPath('perfil.datos.tiene_foto', false)
            ->assertJsonMissingPath('perfil.datos.foto_url')
            ->assertJsonPath('perfil.actividades', [])
            ->assertJsonStructure(['perfil' => ['cuenta' => ['creada', 'ultimo_acceso']]]);
    }

    public function test_sus_actividades_son_sus_carnets_vigentes(): void
    {
        $this->pescadorListo($this->ignacio);

        $this->withToken($this->token)->getJson('/api/v1/portal/perfil')
            ->assertOk()
            ->assertJsonCount(1, 'perfil.actividades')
            ->assertJsonPath('perfil.actividades.0.actividad', 'Pescador')
            ->assertJsonPath('perfil.actividades.0.asociacion', 'Asociación de prueba');
    }

    public function test_solo_ve_lo_suyo(): void
    {
        $otra = $this->beneficiarioConCuenta('1111111', 'Otra');

        $this->withToken($otra->createToken('prueba')->plainTextToken)->getJson('/api/v1/portal/perfil')
            ->assertOk()
            ->assertJsonPath('perfil.datos.nombres', 'Otra')
            ->assertJsonMissing(['telefono' => '67285914']);
    }

    public function test_con_la_clave_temporal_no_se_ve_el_perfil(): void
    {
        app(CuentaPortalService::class)->resetear($this->ignacio);
        $token = $this->cuenta->refresh()->createToken('prueba')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/portal/perfil')
            ->assertForbidden()
            ->assertJsonPath('codigo', 'clave_temporal');
    }

    public function test_la_foto_va_por_el_token(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put('beneficiarios/foto.png', $png);
        $this->ignacio->forceFill(['foto' => 'beneficiarios/foto.png'])->save();

        $this->withToken($this->token)->get('/api/v1/portal/perfil/foto', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->withToken($this->token)->getJson('/api/v1/portal/perfil')
            ->assertJsonPath('perfil.datos.tiene_foto', true);
    }

    public function test_sin_foto_da_404(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/portal/perfil/foto')->assertNotFound();
    }

    public function test_sin_token_no_hay_perfil(): void
    {
        $this->getJson('/api/v1/portal/perfil')->assertUnauthorized();
        $this->getJson('/api/v1/portal/perfil/foto')->assertUnauthorized();
    }

    public function test_el_portal_web_sigue_mostrando_mis_datos(): void
    {
        // Con `composer run dev` corriendo, Inertia intentaría el SSR contra Vite.
        config(['inertia.ssr.enabled' => false]);
        $this->withoutVite();

        $this->actingAs($this->cuenta)->get(route('portal.perfil'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('portal/perfil')
                ->where('datos.nombre', 'Ignacio Molina Guzman')
                ->has('datos.foto_url')
                ->has('actividades')
                ->has('cuenta.ultimo_acceso'));
    }
}
