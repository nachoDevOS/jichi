<?php

namespace Tests\Feature\Pagos;

use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\EmitirFaenaService;
use App\Services\EmitirGuiaService;
use App\Sireb\SirebException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * La MISMA regla al eliminar en el panel y al anular en la app y el portal web
 * (27/11/2026, REGLAS-NEGOCIO Paso 6): solo un trámite pendiente **sin ningún pago
 * cargado**. Un pago manual en revisión frena; si Recaudaciones lo **rechaza**
 * (`observado`), SIREB ya no lo devuelve en la liquidación y el trámite vuelve a
 * estar pendiente sin pago: se puede eliminar. Pagado, se aprueba en vez de borrarse.
 */
class EliminarConPagoTest extends TestCase
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

    /** @return array<string, array{string}> */
    public static function tramites(): array
    {
        return ['faena' => ['faena'], 'guía' => ['guia']];
    }

    //  Panel (el servicio de «Eliminar»)

    #[DataProvider('tramites')]
    public function test_en_el_panel_un_pago_en_revision_frena_y_rechazado_ya_no(string $cual): void
    {
        $tramite = $this->pedir($cual);

        // Ventanilla carga un pago manual: queda en revisión en Recaudaciones.
        $this->pagoManualEnRevision($tramite);
        try {
            $this->eliminarEnElPanel($tramite);
            $this->fail('Con un pago en revisión no debía eliminarse.');
        } catch (SirebException $e) {
            $this->assertSame(SirebException::PAGO_EN_REVISION, $e->codigo);
        }
        $this->assertNotSoftDeleted($tramite);

        // Recaudaciones lo rechaza (observado): SIREB ya no lo devuelve. Pendiente sin pago.
        $this->pagoRechazado($tramite);
        $this->eliminarEnElPanel($tramite->refresh());

        $this->assertSoftDeleted($tramite);
        $this->assertSame('anulada', $this->liquidaciones[$tramite->sireb_liquidacion_id]['estado']);
    }

    #[DataProvider('tramites')]
    public function test_en_el_panel_pagado_no_se_elimina(string $cual): void
    {
        $tramite = $this->pedir($cual);
        $this->liquidaciones[$tramite->sireb_liquidacion_id]['estado'] = 'pagada';

        try {
            $this->eliminarEnElPanel($tramite);
            $this->fail('Pagado no debía eliminarse.');
        } catch (SirebException $e) {
            $this->assertSame(SirebException::PAGADA, $e->codigo);
        }
        $this->assertNotSoftDeleted($tramite);
    }

    //  App y portal web («Anular pedido»): la misma regla

    #[DataProvider('tramites')]
    public function test_en_la_app_un_pago_en_revision_frena_y_rechazado_ya_no(string $cual): void
    {
        $tramite = $this->pedir($cual);
        $titular = $cual === 'faena' ? $this->pescador : $this->comprador;

        $this->pagoManualEnRevision($tramite);
        $this->llamar($titular, "/tramites/{$tramite->codigo_legible}", 'delete')
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'ya_pagado')
            ->assertJsonPath('aprobado', false);
        $this->assertNotSoftDeleted($tramite);

        $this->pagoRechazado($tramite);
        $this->llamar($titular, "/tramites/{$tramite->codigo_legible}", 'delete')->assertOk();
        $this->assertSoftDeleted($tramite);
    }

    private function pedir(string $cual): PermisoFaena|GuiaMovimiento
    {
        if ($cual === 'faena') {
            $this->llamar($this->pescador, '/faenas', 'post', self::TALONARIO)->assertCreated();

            return PermisoFaena::query()->latest('id')->firstOrFail()->load('codigo');
        }

        $this->llamar($this->comprador, '/guias', 'post', [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->cupo->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => 100]],
        ])->assertCreated();

        return GuiaMovimiento::query()->latest('id')->firstOrFail()->load('codigo');
    }

    private function eliminarEnElPanel(PermisoFaena|GuiaMovimiento $tramite): void
    {
        $tramite instanceof PermisoFaena
            ? app(EmitirFaenaService::class)->eliminar($tramite, 'Cargada por error.')
            : app(EmitirGuiaService::class)->eliminar($tramite, 'Cargada por error.');
    }

    /** Como SIREB con un pago manual cargado y todavía sin validar. */
    private function pagoManualEnRevision(PermisoFaena|GuiaMovimiento $tramite): void
    {
        $this->liquidaciones[$tramite->sireb_liquidacion_id]['pago'] = [
            'tipo_pago' => 'manual', 'monto_pagado' => $this->liquidaciones[$tramite->sireb_liquidacion_id]['monto'],
            'estado' => 'pendiente', 'fecha_pago' => now()->toIso8601String(), 'numero_boleta' => '778899',
            'entidad_bancaria' => 'Banco Unión', 'fecha_deposito' => now()->toDateString(), 'fecha_validacion' => null,
            'medio' => 'deposito', 'transaccion_id' => null, 'simulado' => false,
        ];
    }

    /** Como SIREB tras rechazarlo (`observado`): `GET /liquidaciones/{id}` ya no lo trae. */
    private function pagoRechazado(PermisoFaena|GuiaMovimiento $tramite): void
    {
        $this->liquidaciones[$tramite->sireb_liquidacion_id]['pago'] = null;
    }

    /** @param  array<string, mixed>  $datos */
    private function llamar(User $quien, string $ruta, string $metodo = 'get', array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $encabezados = $metodo === 'post' ? ['Idempotency-Key' => (string) Str::uuid()] : [];

        return $this->withToken($quien->createToken('prueba')->plainTextToken)
            ->json(strtoupper($metodo), '/api/v1/portal'.$ruta, $datos, $encabezados);
    }
}
