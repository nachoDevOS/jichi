<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoGuia;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\GuiaMovimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Kilos por mes» del inicio de la app (27/11/2026): un gráfico para los dos rubros.
 * Vendido (pescador, por sus autorizaciones) y transportado (comercializador, por sus
 * guías), por mes, separando lo confirmado de lo por confirmar.
 */
class KilosPorMesTest extends TestCase
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

    public function test_el_pescador_ve_lo_vendido_por_mes(): void
    {
        $this->comprar(200);
        $this->comprar(100);
        GuiaMovimiento::query()->oldest('id')->firstOrFail()->update(['estado' => EstadoGuia::Aprobada]);
        $mes = now()->month - 1;

        $this->kilos($this->pescador)
            ->assertOk()
            ->assertJsonPath('kilos.gestion', now()->year)
            ->assertJsonPath('kilos.mes_actual', now()->month)
            ->assertJsonCount(1, 'kilos.rubros')
            ->assertJsonPath('kilos.rubros.0.clave', 'vendido')
            ->assertJsonPath('kilos.rubros.0.etiqueta', 'Vendido')
            ->assertJsonPath('kilos.rubros.0.total_kg', 300)
            ->assertJsonPath('kilos.rubros.0.por_confirmar_kg', 100)
            ->assertJsonPath('kilos.rubros.0.guias', 2)
            ->assertJsonCount(12, 'kilos.rubros.0.meses')
            ->assertJsonPath("kilos.rubros.0.meses.{$mes}.confirmado", 200)
            ->assertJsonPath("kilos.rubros.0.meses.{$mes}.por_confirmar", 100);
    }

    public function test_el_comercializador_ve_lo_transportado(): void
    {
        $this->comprar(150);
        $mes = now()->month - 1;

        $this->kilos($this->comprador)
            ->assertJsonCount(1, 'kilos.rubros')
            ->assertJsonPath('kilos.rubros.0.clave', 'transportado')
            ->assertJsonPath('kilos.rubros.0.etiqueta', 'Transportado')
            ->assertJsonPath('kilos.rubros.0.total_kg', 150)
            ->assertJsonPath("kilos.rubros.0.meses.{$mes}.por_confirmar", 150);
    }

    public function test_con_los_dos_carnets_ve_los_dos_y_primero_el_de_mas_movimiento(): void
    {
        $this->carnetDe($this->pescador->beneficiario, 'comercializador');
        $this->comprar(50);

        $this->kilos($this->pescador)
            ->assertJsonCount(2, 'kilos.rubros')
            // Vendió 50 y todavía no transportó nada: arranca en «Vendido».
            ->assertJsonPath('kilos.rubros.0.clave', 'vendido')
            ->assertJsonPath('kilos.rubros.1.clave', 'transportado')
            ->assertJsonPath('kilos.rubros.1.total_kg', 0);
    }

    public function test_cada_guia_va_en_su_mes_y_su_gestion(): void
    {
        $this->comprar(80);
        GuiaMovimiento::query()->sole()->update(['fecha_solicitud' => now()->startOfYear()->toDateString()]);

        $this->kilos($this->pescador)->assertJsonPath('kilos.rubros.0.meses.0.por_confirmar', 80);
        $this->kilos($this->pescador, now()->year - 1)
            ->assertJsonPath('kilos.gestion', now()->year - 1)
            ->assertJsonPath('kilos.mes_actual', 12)
            ->assertJsonPath('kilos.rubros.0.total_kg', 0);
    }

    public function test_sin_autorizacion_ni_carnet_de_comercializador_no_hay_rubros(): void
    {
        $nadie = $this->beneficiarioConCuenta('1111111', 'Ana');

        $this->kilos($nadie)->assertOk()->assertJsonPath('kilos.rubros', []);
    }

    public function test_sin_token_no_entra(): void
    {
        $this->getJson('/api/v1/portal/kilos-por-mes')->assertUnauthorized();
    }

    private function comprar(float $kilos): void
    {
        $this->pedir($this->comprador, '/guias', 'post', [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->cupo->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
        ])->assertCreated();
    }

    private function kilos(User $quien, ?int $gestion = null): TestResponse
    {
        return $this->pedir($quien, '/kilos-por-mes'.($gestion === null ? '' : "?gestion={$gestion}"));
    }

    /** @param  array<string, mixed>  $datos */
    private function pedir(User $quien, string $ruta, string $metodo = 'get', array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $encabezados = $metodo === 'post' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($quien->createToken('prueba')->plainTextToken)
            ->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
