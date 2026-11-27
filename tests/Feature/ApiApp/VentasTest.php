<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoGuia;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
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
 * «Mis ventas» de la autorización en la app (27/11/2026): lo mismo que la ficha del
 * panel. Vendido, reservado (guías pendientes) y libre, y a quién le vendió.
 */
class VentasTest extends TestCase
{
    use PescadorDePrueba, RefreshDatabase, SirebFalso;

    private User $pescador;

    private AprovechamientoPesq $cupo;

    private Carnet $carnetPescador;

    private string $tokenPescador;

    private string $tokenComprador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);

        $this->pescador = $this->beneficiarioConCuenta();
        $this->carnetPescador = $this->pescadorListo($this->pescador->beneficiario);
        $this->carnetPescador->asignarCodigo();
        $this->cupo = $this->carnetPescador->aprovechamiento;
        $this->cupo->asignarCodigo();
        $this->tokenPescador = $this->pescador->createToken('prueba')->plainTextToken;

        $comprador = $this->beneficiarioConCuenta('5544332', 'Juan');
        $this->carnetDe($comprador->beneficiario, 'comercializador');
        $this->tokenComprador = $comprador->createToken('prueba')->plainTextToken;
    }

    public function test_muestra_vendido_reservado_libre_y_a_quien_como_el_panel(): void
    {
        $this->comprar(200);
        $this->comprar(100);
        // La primera, pagada: ya se vendió. La segunda sigue reservando sus kilos.
        GuiaMovimiento::query()->oldest('id')->firstOrFail()->update(['estado' => EstadoGuia::Aprobada]);

        $comprador = Beneficiario::query()->where('ci', '5544332')->sole()->nombreCompleto;

        $this->ventas()
            ->assertOk()
            ->assertJsonPath('ventas.numero', $this->cupo->numeroLegible())
            ->assertJsonPath('ventas.total_kg', 1000)
            ->assertJsonPath('ventas.vendidos_kg', 200)
            ->assertJsonPath('ventas.reservados_kg', 100)
            ->assertJsonPath('ventas.disponibles_kg', 700)
            ->assertJsonCount(2, 'ventas.guias')
            // La más nueva primero, como el panel.
            ->assertJsonPath('ventas.guias.0.kilos', 100)
            ->assertJsonPath('ventas.guias.0.por_confirmar', true)
            ->assertJsonPath('ventas.guias.1.kilos', 200)
            ->assertJsonPath('ventas.guias.1.por_confirmar', false)
            ->assertJsonPath('ventas.guias.1.comprador', $comprador)
            ->assertJsonPath('ventas.guias.1.productos.0.kilos', 200)
            ->assertJsonPath('ventas.guias.1.productos.0.nombre', fn (?string $n) => filled($n))
            ->assertJsonPath('ventas.guias.1.productos.0.condicion', fn (?string $c) => filled($c))
            ->assertJsonMissingPath('ventas.guias.0.id')
            // El estado de la guía (revocada, sin efecto) no le compete al pescador.
            ->assertJsonMissingPath('ventas.guias.1.estado_etiqueta')
            // Cómo fue bajando, como el gráfico del panel: otorgada, cada guía y hoy.
            ->assertJsonCount(4, 'ventas.evolucion')
            ->assertJsonPath('ventas.evolucion.0.etiqueta', 'Otorgada')
            ->assertJsonPath('ventas.evolucion.0.disponible', 1000)
            ->assertJsonPath('ventas.evolucion.1.disponible', 800)
            ->assertJsonPath('ventas.evolucion.1.kilos', 200)
            ->assertJsonPath('ventas.evolucion.1.comprador', $comprador)
            ->assertJsonPath('ventas.evolucion.1.por_confirmar', false)
            ->assertJsonPath('ventas.evolucion.2.disponible', 700)
            ->assertJsonPath('ventas.evolucion.2.por_confirmar', true)
            ->assertJsonPath('ventas.evolucion.3.etiqueta', 'Hoy')
            ->assertJsonPath('ventas.evolucion.3.disponible', 700)
            ->assertJsonMissingPath('ventas.evolucion.1.estado_etiqueta');
    }

    public function test_sin_ventas_viene_vacia(): void
    {
        $this->ventas()
            ->assertOk()
            ->assertJsonPath('ventas.vendidos_kg', 0)
            ->assertJsonPath('ventas.disponibles_kg', 1000)
            ->assertJsonPath('ventas.guias', []);
    }

    public function test_solo_su_autorizacion(): void
    {
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Pescador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $ajena = $this->autorizacionDe($otro);
        $ajena->asignarCodigo();

        $this->pedir($this->tokenPescador, "/papeles/{$ajena->codigo_legible}/ventas")->assertNotFound();
        // Otro papel suyo (el carnet) tampoco: solo la autorización tiene ventas.
        $this->pedir($this->tokenPescador, "/papeles/{$this->carnetPescador->codigo_legible}/ventas")->assertNotFound();
        $this->pedir($this->tokenPescador, '/papeles/ZZZZ-ZZZZ-ZZZZ-ZZZZ/ventas')->assertNotFound();
    }

    public function test_sin_token_no_entra(): void
    {
        $this->getJson("/api/v1/portal/papeles/{$this->cupo->codigo_legible}/ventas")->assertUnauthorized();
    }

    private function comprar(float $kilos): void
    {
        $this->pedir($this->tokenComprador, '/guias', 'post', [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->cupo->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
        ])->assertCreated();
    }

    private function ventas(): TestResponse
    {
        return $this->pedir($this->tokenPescador, "/papeles/{$this->cupo->codigo_legible}/ventas");
    }

    /** @param  array<string, mixed>  $datos */
    private function pedir(string $token, string $ruta, string $metodo = 'get', array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $encabezados = $metodo === 'post' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($token)->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
