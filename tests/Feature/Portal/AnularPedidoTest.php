<?php

namespace Tests\Feature\Portal;

use App\Enums\OrigenTramite;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Anular pedido» en el portal web (27/11/2026): la MISMA regla que la app
 * (AnularPedidoService). Lo que importa es que lo pidió el beneficiario, no dónde:
 *
 * | Lo pidió desde | Anular desde la app | Anular desde el portal web |
 * | App            | ✅                  | ✅                         |
 * | Portal web     | ✅                  | ✅                         |
 * | Ventanilla     | ❌ (el panel)       | ❌ (el panel)              |
 */
class AnularPedidoTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $pescador;

    private User $comprador;

    private AprovechamientoPesq $cupo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->pescador = $this->beneficiarioConCuenta();
        $this->cupo = $this->pescadorListo($this->pescador->beneficiario)->aprovechamiento;
        $this->cupo->asignarCodigo();

        $this->comprador = $this->beneficiarioConCuenta('5544332', 'Juan');
        $this->carnetDe($this->comprador->beneficiario, 'comercializador');
    }

    //  El cuadro: pedida en la web o en la app, se anula en la web o en la app

    public function test_pedida_en_la_web_se_anula_en_la_web(): void
    {
        $faena = $this->faenaDesdeLaWeb();

        // «En curso» ofrece el botón.
        $this->actingAs($this->pescador)->get(route('portal.en-curso'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('portal/en-curso')
                ->where('tramites.0.puede_anular', true));

        $this->anularEnLaWeb($faena)
            ->assertRedirect(route('portal.en-curso'))
            ->assertSessionHas('exito', "Anuló el permiso de faena N° {$faena->numero_legible}. No se cobra. Ya puede pedir otro.");

        $this->assertSoftDeleted($faena);
        $this->assertSame('anulada', $this->liquidaciones[$faena->sireb_liquidacion_id]['estado']);
    }

    public function test_pedida_en_la_app_se_anula_en_la_web(): void
    {
        $faena = $this->faenaDesdeLaApp();

        $this->anularEnLaWeb($faena)->assertSessionHas('exito');
        $this->assertSoftDeleted($faena);
    }

    public function test_pedida_en_la_web_se_anula_en_la_app(): void
    {
        $faena = $this->faenaDesdeLaWeb();

        $this->api($this->pescador, "/tramites/{$faena->codigo_legible}", 'delete')
            ->assertOk()
            ->assertJsonPath('mensaje', "Anulaste el permiso de faena N° {$faena->numero_legible}. No se cobra. Ya podés pedir otro.");
        $this->assertSoftDeleted($faena);
    }

    public function test_lo_de_ventanilla_no_se_anula_ni_en_la_web(): void
    {
        $faena = $this->faenaDesdeLaWeb();
        $faena->update(['origen_tramite' => OrigenTramite::Ventanilla]);

        $this->actingAs($this->pescador)->get(route('portal.en-curso'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('tramites.0.puede_anular', false));

        $this->anularEnLaWeb($faena)
            ->assertRedirect(route('portal.en-curso'))
            ->assertSessionHas('aviso', fn (string $m) => str_contains($m, 'ventanilla'));
        $this->assertNotSoftDeleted($faena);
    }

    public function test_la_guia_se_anula_en_la_web_y_los_kilos_vuelven(): void
    {
        $this->api($this->comprador, '/guias', 'post', [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->cupo->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => 300]],
        ])->assertCreated();
        $guia = GuiaMovimiento::query()->sole()->load('codigo');

        $this->actingAs($this->comprador)
            ->delete(route('portal.tramites.anular', ['codigo' => $guia->codigo_legible]))
            ->assertSessionHas('exito', fn (string $m) => str_contains($m, 'los kilos volvieron al pescador'));

        $this->assertSoftDeleted($guia);
        $this->assertSame(1000.0, (float) $this->cupo->refresh()->kilos_disponibles);
    }

    public function test_con_un_pago_cargado_no_se_anula_y_rechazado_si(): void
    {
        $faena = $this->faenaDesdeLaWeb();
        $id = $faena->sireb_liquidacion_id;
        $this->liquidaciones[$id]['pago'] = [
            'tipo_pago' => 'manual', 'monto_pagado' => $this->liquidaciones[$id]['monto'], 'estado' => 'pendiente',
            'fecha_pago' => now()->toIso8601String(), 'numero_boleta' => '778899', 'entidad_bancaria' => 'Banco Unión',
            'fecha_deposito' => now()->toDateString(), 'fecha_validacion' => null, 'medio' => 'deposito',
            'transaccion_id' => null, 'simulado' => false,
        ];

        $this->anularEnLaWeb($faena)->assertSessionHas('aviso', fn (string $m) => str_contains($m, 'pago cargado'));
        $this->assertNotSoftDeleted($faena);

        // Recaudaciones lo rechaza: SIREB ya no lo devuelve. Pendiente sin pago.
        $this->liquidaciones[$id]['pago'] = null;
        $this->anularEnLaWeb($faena)->assertSessionHas('exito');
        $this->assertSoftDeleted($faena);
    }

    public function test_lo_ajeno_da_404(): void
    {
        $faena = $this->faenaDesdeLaWeb();

        $this->actingAs($this->comprador)
            ->delete(route('portal.tramites.anular', ['codigo' => $faena->codigo_legible]))
            ->assertNotFound();
        $this->assertNotSoftDeleted($faena);
    }

    private function faenaDesdeLaWeb(): PermisoFaena
    {
        $this->actingAs($this->pescador)->post(route('portal.faenas.store'), self::TALONARIO)
            ->assertRedirect(route('portal.en-curso'));

        $faena = PermisoFaena::query()->latest('id')->firstOrFail()->load('codigo');
        $this->assertSame(OrigenTramite::PortalWeb, $faena->origen_tramite);

        return $faena;
    }

    private function faenaDesdeLaApp(): PermisoFaena
    {
        $this->api($this->pescador, '/faenas', 'post', self::TALONARIO)->assertCreated();

        $faena = PermisoFaena::query()->latest('id')->firstOrFail()->load('codigo');
        $this->assertSame(OrigenTramite::App, $faena->origen_tramite);

        return $faena;
    }

    private function anularEnLaWeb(PermisoFaena $faena): TestResponse
    {
        return $this->actingAs($this->pescador)
            ->delete(route('portal.tramites.anular', ['codigo' => $faena->codigo_legible]));
    }

    /** @param  array<string, mixed>  $datos */
    private function api(User $quien, string $ruta, string $metodo = 'get', array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $encabezados = $metodo === 'post' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($quien->createToken('prueba')->plainTextToken)
            ->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
