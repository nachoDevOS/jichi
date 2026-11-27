<?php

namespace Tests\Feature\ApiApp;

use App\Enums\EstadoFaena;
use App\Enums\OrigenTramite;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\User;
use App\Services\ApiApp\AnularPedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\PescadorDePrueba;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Anular pedido» desde la app (27/11/2026): el titular anula su faena o guía pendiente
 * pedida en línea, como «Eliminar» del panel. Primero se anula el cobro en SIREB; la
 * guía devuelve sus kilos al pescador.
 */
class AnularTramiteTest extends TestCase
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

    public function test_anula_su_faena_pendiente_y_puede_pedir_otra(): void
    {
        $faena = $this->pedirFaena();
        $this->assertTrue($this->enCurso($this->pescador)->json('tramites.0.puede_anular'));
        // Con la pendiente, no puede pedir otra.
        $this->pedir($this->pescador, '/faenas/nueva')->assertJsonPath('faena.puede', false);

        $this->anular($this->pescador, $faena)
            ->assertOk()
            ->assertJsonPath('mensaje', "Anulaste el permiso de faena N° {$faena->numero_legible}. No se cobra. Ya podés pedir otro.");

        $this->assertSoftDeleted($faena);
        // El cobro en SIREB, anulado: nadie paga un QR de algo que ya no existe.
        $this->assertSame('anulada', $this->liquidaciones[$faena->sireb_liquidacion_id]['estado']);
        $this->enCurso($this->pescador)->assertJsonPath('tramites', []);
        $this->pedir($this->pescador, '/faenas/nueva')->assertJsonPath('faena.puede', true);
    }

    public function test_anula_su_guia_pendiente_y_los_kilos_vuelven_al_pescador(): void
    {
        $guia = $this->pedirGuia(300);
        $this->assertSame(700.0, (float) $this->cupo->refresh()->kilos_disponibles);

        $this->anular($this->comprador, $guia)
            ->assertOk()
            ->assertJsonPath('mensaje', "Anulaste la guía de transporte N° {$guia->numero_legible}. No se cobra y los kilos volvieron al pescador. Ya podés pedir otro.");

        $this->assertSoftDeleted($guia);
        $this->assertSame(1000.0, (float) $this->cupo->refresh()->kilos_disponibles);
    }

    public function test_el_pescador_no_anula_la_guia_que_le_compraron(): void
    {
        $guia = $this->pedirGuia(100);

        // La guía es del comercializador: para el pescador, ajena.
        $this->anular($this->pescador, $guia)->assertNotFound();
        $this->assertNotSoftDeleted($guia);
    }

    public function test_lo_registrado_en_ventanilla_se_anula_en_ventanilla(): void
    {
        $faena = $this->pedirFaena();
        $faena->update(['origen_tramite' => OrigenTramite::Ventanilla]);

        $this->assertFalse($this->enCurso($this->pescador)->json('tramites.0.puede_anular'));
        $this->anular($this->pescador, $faena)
            ->assertUnprocessable()
            ->assertJsonPath('errors.tramite.0', fn (string $m) => str_contains($m, 'ventanilla'));
        $this->assertNotSoftDeleted($faena);
    }

    public function test_aprobada_ya_no_se_anula(): void
    {
        $faena = $this->pedirFaena();
        $faena->update([
            'estado' => EstadoFaena::Aprobado,
            'fecha_salida' => now()->toDateString(),
            'fecha_desembarque' => now()->addDays(20)->toDateString(),
        ]);

        $this->anular($this->pescador, $faena)
            ->assertUnprocessable()
            ->assertJsonPath('message', "Ya no se puede anular el permiso de faena N° {$faena->numero_legible}: está aprobado.");
    }

    public function test_si_ya_pago_en_sireb_no_se_anula_y_queda_aprobada(): void
    {
        $faena = $this->pedirFaena();
        $this->pagarEnSireb($faena->sireb_liquidacion_id);

        $this->anular($this->pescador, $faena)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'ya_pagado')
            ->assertJsonPath('aprobado', true);

        $this->assertNotSoftDeleted($faena);
        $this->assertSame(EstadoFaena::Aprobado, $faena->refresh()->estado);
    }

    public function test_lo_ajeno_o_inexistente_da_404_y_sin_token_401(): void
    {
        $faena = $this->pedirFaena();

        $this->anular($this->comprador, $faena)->assertNotFound();
        $this->pedir($this->pescador, '/tramites/ZZZZ-ZZZZ-ZZZZ-ZZZZ', 'delete')->assertNotFound();
        $this->assertNotSoftDeleted($faena);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->deleteJson("/api/v1/portal/tramites/{$faena->codigo_legible}")->assertUnauthorized();
    }

    public function test_queda_en_la_auditoria_con_el_motivo(): void
    {
        $faena = $this->pedirFaena();

        $this->anular($this->pescador, $faena)->assertOk();

        $this->assertDatabaseHas('auditorias', [
            'auditable_type' => $faena->getMorphClass(),
            'auditable_id' => $faena->id,
            'evento' => 'eliminado',
            'descripcion' => AnularPedidoService::MOTIVO,
            // Quién: la cuenta del titular.
            'user_id' => $this->pescador->id,
        ]);
    }

    private function pedirFaena(): PermisoFaena
    {
        $this->pedir($this->pescador, '/faenas', 'post', self::TALONARIO)->assertCreated();

        return PermisoFaena::query()->latest('id')->firstOrFail()->load('codigo');
    }

    private function pedirGuia(float $kilos): GuiaMovimiento
    {
        $this->pedir($this->comprador, '/guias', 'post', [
            'origen' => 'Puerto Almacén', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Cercado', 'origen_distrito' => 'Trinidad',
            'destino' => 'Santa Cruz de la Sierra', 'destino_departamento' => 'Santa Cruz', 'destino_provincia' => 'Andrés Ibáñez', 'destino_distrito' => 'Santa Cruz',
            'medio_transporte' => 'terrestre', 'tipo_transporte' => 'automotriz',
            'transporte_nombre' => 'Camioneta Toyota Hilux', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
            'autorizacion_codigo' => $this->cupo->codigo_legible,
            'detalles' => [['tarifa_sireb' => self::TARIFA_SURUBI, 'condicion' => 'fresco_entero', 'cantidad_kg' => $kilos]],
        ])->assertCreated();

        return GuiaMovimiento::query()->latest('id')->firstOrFail()->load('codigo');
    }

    private function anular(User $quien, PermisoFaena|GuiaMovimiento $tramite): TestResponse
    {
        return $this->pedir($quien, "/tramites/{$tramite->codigo_legible}", 'delete');
    }

    private function enCurso(User $quien): TestResponse
    {
        return $this->pedir($quien, '/en-curso');
    }

    /** Lo que hace el titular con la app de su banco: SIREB deja la liquidación pagada. */
    private function pagarEnSireb(string $liquidacionId): void
    {
        $this->liquidaciones[$liquidacionId]['estado'] = 'pagada';
        $this->liquidaciones[$liquidacionId]['pago'] = [
            'tipo_pago' => 'electronico', 'monto_pagado' => $this->liquidaciones[$liquidacionId]['monto'],
            'estado' => 'confirmado', 'fecha_pago' => now()->toIso8601String(), 'numero_boleta' => null,
            'entidad_bancaria' => null, 'fecha_deposito' => null, 'fecha_validacion' => now()->toIso8601String(),
            'medio' => 'QR', 'transaccion_id' => 'QR-'.Str::upper(Str::random(10)), 'simulado' => false,
        ];
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
