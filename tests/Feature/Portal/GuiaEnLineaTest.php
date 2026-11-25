<?php

namespace Tests\Feature\Portal;

use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\GuiaMovimiento;
use App\Models\User;
use App\Services\ApiApp\PedirGuiaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Pedir guía de transporte» en el portal web /mi-cuenta (19/11/2026), con las reglas
 * del panel. Y que el panel siga ofreciendo sus productos tras moverlos al servicio.
 */
class GuiaEnLineaTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $cuenta;

    private AprovechamientoPesq $autJuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);
        // Con `composer run dev` corriendo, Inertia intentaría el SSR contra Vite.
        config(['inertia.ssr.enabled' => false]);
        $this->withoutVite();

        $this->cuenta = $this->beneficiarioConCuenta();
        $this->carnetDe($this->cuenta->beneficiario, 'comercializador');
        $this->autJuan = $this->autorizacionDe(Beneficiario::factory()->create());
        $this->autJuan->asignarCodigo();
    }

    public function test_el_formulario_trae_productos_y_opciones(): void
    {
        $this->actingAs($this->cuenta)->get(route('portal.guias.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('portal/guia')
                ->where('puede', true)
                ->where('productos.0.nombre', 'Surubí')
                ->has('condiciones', 10)
                ->has('medios', 3)
                ->has('tipos_transporte', 3));
    }

    public function test_busca_al_pescador_por_codigo_desde_la_pagina(): void
    {
        $this->actingAs($this->cuenta)
            ->getJson(route('portal.guias.autorizacion', ['codigo' => $this->autJuan->codigo_legible]))
            ->assertOk()
            ->assertJsonPath('autorizacion.vendible', true)
            ->assertJsonPath('autorizacion.kilos_disponibles', 1000);

        $this->actingAs($this->cuenta)
            ->getJson(route('portal.guias.autorizacion', ['codigo' => 'AAAABBBBCCCCDDDD']))
            ->assertNotFound();
    }

    public function test_pedirla_la_registra_descuenta_y_lleva_a_en_curso(): void
    {
        $this->actingAs($this->cuenta)->post(route('portal.guias.store'), $this->guia(250))
            ->assertRedirect(route('portal.en-curso'))
            ->assertSessionHas('exito');

        $this->assertSame(1, GuiaMovimiento::query()->count());
        $this->assertSame(750.0, (float) $this->autJuan->refresh()->kilos_disponibles);
    }

    public function test_sin_los_datos_vuelve_con_los_errores_de_ventanilla(): void
    {
        $this->actingAs($this->cuenta)->post(route('portal.guias.store'), [])
            ->assertSessionHasErrors(['origen' => 'Indique desde dónde sale la carga.', 'detalles', 'autorizacion_codigo']);
        $this->assertSame(0, GuiaMovimiento::query()->count());
    }

    public function test_con_un_codigo_equivocado_vuelve_con_el_motivo(): void
    {
        $this->actingAs($this->cuenta)
            ->post(route('portal.guias.store'), [...$this->guia(100), 'autorizacion_codigo' => 'AAAABBBBCCCCDDDD'])
            ->assertSessionHasErrors(['guia' => PedirGuiaService::SIN_AUTORIZACION]);
    }

    public function test_el_nombre_del_pescador_va_abreviado(): void
    {
        $b = Beneficiario::create([
            'ci' => '1', 'primerNombre' => 'Pedro', 'apellidoPaterno' => 'Nuñez', 'apellidoMaterno' => 'Cuéllar', 'fechaNacimiento' => '1980-01-01',
        ]);

        $this->assertSame('Pedro N. C.', PedirGuiaService::nombreAbreviado($b));
    }

    public function test_el_panel_sigue_ofreciendo_los_productos_de_la_guia(): void
    {
        Permission::findOrCreate('guias.crear', 'web');
        $funcionario = User::create(['name' => 'Ventanilla', 'email' => 'v@jichi.test', 'password' => 'x', 'activo' => true]);
        $funcionario->givePermissionTo('guias.crear');

        $this->actingAs($funcionario)->get(route('guias.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('panel/guias/crear')
                ->where('productos.0.id', self::TARIFA_SURUBI)
                ->where('productos.0.precio_kg', 0.5)
                ->where('sirebDisponible', true));
    }

    /** @return array<string, mixed> */
    private function guia(float $kilos): array
    {
        return [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Riberalta', 'destino_departamento' => 'Beni', 'destino_provincia' => 'Vaca Díez', 'destino_distrito' => 'Riberalta',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camión', 'transporte_placa' => '999-XYZ', 'transporte_capacidad_kg' => 3000,
            'autorizacion_codigo' => $this->autJuan->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'congelado_entero', 'cantidad_kg' => $kilos]],
        ];
    }
}
