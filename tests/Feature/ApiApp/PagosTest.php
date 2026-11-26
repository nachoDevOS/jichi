<?php

namespace Tests\Feature\ApiApp;

use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Services\CuentaPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SirebFalso;
use Tests\TestCase;

/**
 * «Mis pagos» en la app (25/11/2026): sus recibos de una gestión, lo mismo que la web
 * (PantallaPortal::pagos), con el código y el QR de verificación de cada recibo.
 */
class PagosTest extends TestCase
{
    use RefreshDatabase, SirebFalso;

    private Beneficiario $ignacio;

    private string $token;

    private int $siguiente = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simularSireb();
        Bus::fake([VerificarPagoJob::class]);
        config(['app.url' => 'https://pescador.beni.gob.bo']);

        $this->ignacio = Beneficiario::create([
            'ci' => '7633685', 'primerNombre' => 'Ignacio', 'apellidoPaterno' => 'Molina', 'fechaNacimiento' => '1997-03-08',
        ]);
        $servicio = app(CuentaPortalService::class);
        $servicio->darAcceso($this->ignacio);
        $cuenta = $this->ignacio->cuenta()->firstOrFail();
        $servicio->cambiarClave($cuenta, 'pesca2026');
        $this->token = $cuenta->createToken('prueba')->plainTextToken;
    }

    public function test_sus_recibos_de_la_gestion_con_codigo_y_verificacion(): void
    {
        $autorizacion = $this->recibo($this->ignacio, AprovechamientoPesq::class, 500, 'Autorización de Pesca - 2000 kg', '2026-11-25');
        $this->recibo($this->ignacio, PermisoFaena::class, 30, 'Certificado de Faena', '2026-11-26');

        $codigo = $autorizacion->codigo->codigo;

        $this->pedir('/pagos')
            ->assertOk()
            ->assertJsonPath('pagos.gestion', (int) now()->format('Y'))
            ->assertJsonPath('pagos.total_pagado', 530)
            ->assertJsonCount(2, 'pagos.recibos')
            // El más nuevo primero, como en la web.
            ->assertJsonPath('pagos.recibos.0.clase', 'faena')
            ->assertJsonPath('pagos.recibos.1.clase', 'aprovechamiento')
            ->assertJsonPath('pagos.recibos.1.concepto', 'Autorización de Pesca - 2000 kg')
            ->assertJsonPath('pagos.recibos.1.monto_total', 500)
            ->assertJsonPath('pagos.recibos.1.numero_boleta', 'SIM-0001')
            ->assertJsonPath('pagos.recibos.1.entidad_bancaria', 'Pago por QR de prueba')
            ->assertJsonPath('pagos.recibos.1.fecha_pago', '2026-11-25')
            ->assertJsonPath('pagos.recibos.1.codigo', $autorizacion->codigo_legible)
            // La misma dirección que va en el QR del PDF (QrVerificacion).
            ->assertJsonPath('pagos.recibos.1.verificar', "https://pescador.beni.gob.bo/verificar?code={$codigo}")
            // La descarga pide sesión de navegador: no viaja a la app.
            ->assertJsonMissingPath('pagos.recibos.0.descargar');
    }

    public function test_solo_ve_sus_recibos(): void
    {
        $otro = Beneficiario::create([
            'ci' => '1111111', 'primerNombre' => 'Otro', 'apellidoPaterno' => 'Pescador', 'fechaNacimiento' => '1990-01-01',
        ]);
        $this->recibo($otro, AprovechamientoPesq::class, 999, 'Ajeno', '2026-11-25');

        $this->pedir('/pagos')
            ->assertOk()
            ->assertJsonPath('pagos.total_pagado', 0)
            ->assertJsonPath('pagos.recibos', []);
    }

    public function test_elige_la_gestion_y_una_que_no_existe_da_la_actual(): void
    {
        $actual = (int) now()->format('Y');
        $this->recibo($this->ignacio, AprovechamientoPesq::class, 200, 'Del año pasado', ($actual - 1).'-06-10', creado: now()->subYear());
        $this->recibo($this->ignacio, PermisoFaena::class, 30, 'De este año', $actual.'-11-25');

        $this->pedir('/pagos?gestion='.($actual - 1))
            ->assertOk()
            ->assertJsonPath('pagos.gestion', $actual - 1)
            ->assertJsonPath('pagos.gestiones', [$actual, $actual - 1])
            ->assertJsonPath('pagos.total_pagado', 200)
            ->assertJsonPath('pagos.recibos.0.concepto', 'Del año pasado');

        foreach (['1990', 'abc', ''] as $gestion) {
            $this->pedir('/pagos?gestion='.$gestion)
                ->assertOk()
                ->assertJsonPath('pagos.gestion', $actual)
                ->assertJsonPath('pagos.recibos.0.concepto', 'De este año');
        }
    }

    public function test_el_inicio_trae_lo_pagado_en_la_gestion(): void
    {
        $this->recibo($this->ignacio, AprovechamientoPesq::class, 500, 'Autorización', '2026-11-25');
        $this->recibo($this->ignacio, PermisoFaena::class, 30, 'Faena', '2026-11-25');

        $this->pedir('/inicio')
            ->assertOk()
            ->assertJsonPath('inicio.pagado.gestion', (int) now()->format('Y'))
            ->assertJsonPath('inicio.pagado.total', 530)
            ->assertJsonPath('inicio.pagado.recibos', 2);
    }

    public function test_sin_token_no_entra(): void
    {
        $this->getJson('/api/v1/portal/pagos')->assertUnauthorized();
    }

    private function recibo(Beneficiario $de, string $clase, float $monto, string $concepto, string $fechaPago, ?Carbon $creado = null): Recibo
    {
        $this->siguiente++;
        $recibo = Recibo::query()->create([
            'beneficiario_id' => $de->id,
            // El trámite no hace falta: «Mis pagos» lee el recibo, que congela lo cobrado.
            'recibible_type' => (new $clase)->getMorphClass(),
            'recibible_id' => $this->siguiente,
            'numero_recibo' => str_pad((string) $this->siguiente, 6, '0', STR_PAD_LEFT),
            'monto_total' => $monto,
            'concepto' => $concepto,
            'numero_boleta' => 'SIM-'.str_pad((string) $this->siguiente, 4, '0', STR_PAD_LEFT),
            'entidad_bancaria' => 'Pago por QR de prueba',
            'fecha_pago' => $fechaPago,
        ]);
        if ($creado !== null) {
            $recibo->forceFill(['created_at' => $creado])->saveQuietly();
        }
        $recibo->asignarCodigo();

        return $recibo->load('codigo');
    }

    private function pedir(string $ruta): TestResponse
    {
        return $this->withToken($this->token)->getJson('/api/v1/portal'.$ruta);
    }
}
