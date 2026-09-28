<?php

namespace Tests\Concerns;

use App\Enums\TipoActor;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CategoriaAprovechamiento;
use App\Models\PermisoFaena;
use App\Models\TipoCarnet;
use App\Models\User;
use App\Services\CobrarService;
use App\Services\ControlarPagoService;
use App\Services\EmitirCarnetService;
use App\Services\EmitirFaenaService;
use App\Services\OtorgarCupoService;
use App\Services\RevisarCarnetService;
use App\Services\RevisarCupoService;
use App\Services\RevisarFaenaService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * El camino de ventanilla armado con los SERVICIOS reales —otorgar, cobrar,
 * enviar, validar, aprobar—, para que cada prueba se ocupe solo de su regla.
 */
trait ArmaEscenarios
{
    protected function sembrarYEntrar(): User
    {
        $this->seed([RolPermisoSeeder::class, ConfiguracionSeeder::class, UsuarioSeeder::class, CatalogoSeeder::class]);

        $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();
        $this->actingAs($admin);

        return $admin;
    }

    protected function escala(int $numero = 5): CategoriaAprovechamiento
    {
        return CategoriaAprovechamiento::query()->where('nro_escala', $numero)->firstOrFail();
    }

    /** Carga un depósito por lo que falta, sin emitir recibo. */
    protected function pagar(Model $tramite, ?float $monto = null): void
    {
        static $boleta = 0;

        app(CobrarService::class)->registrarDepositos($tramite, [[
            'monto' => $monto ?? $tramite->saldoPendiente(),
            'nro_transaccion' => 'ESC-'.(++$boleta).'-'.uniqid(),
            'fecha_deposito' => now()->toDateString(),
            'comprobante' => 'comprobantes/prueba.pdf',
        ]]);
    }

    /** Valida todas las boletas del trámite (tiene que estar en revisión). */
    protected function validarBoletas(Model $tramite): void
    {
        foreach ($tramite->pagos()->get() as $pago) {
            app(ControlarPagoService::class)->validar($pago);
        }
    }

    protected function autorizacionAprobada(Beneficiario $persona, int $escala = 5): AprovechamientoPesq
    {
        $cupo = app(OtorgarCupoService::class)->otorgar($persona, $this->escala($escala), 'Canoa');
        $this->pagar($cupo);
        $cupo = app(RevisarCupoService::class)->enviar($cupo->fresh());
        $this->validarBoletas($cupo);

        return app(RevisarCupoService::class)->aprobar($cupo->fresh());
    }

    protected function emitirCarnet(Beneficiario $persona, TipoActor $actor, ?AprovechamientoPesq $cupo = null): Carnet
    {
        return app(EmitirCarnetService::class)->emitir(
            $persona,
            Asociacion::query()->firstOrFail(),
            TipoCarnet::query()->where('tipo_actor', $actor)->firstOrFail(),
            $actor,
            cupoElegido: $cupo,
        );
    }

    protected function aprobarCarnet(Carnet $carnet): Carnet
    {
        $this->pagar($carnet);
        $carnet = app(RevisarCarnetService::class)->enviar($carnet->fresh());
        $this->validarBoletas($carnet);

        return app(RevisarCarnetService::class)->aprobar($carnet->fresh());
    }

    protected function faenaAprobada(Carnet $carnet, float $kilos): PermisoFaena
    {
        $faena = app(EmitirFaenaService::class)->emitir($carnet->fresh(), $kilos);
        $this->pagar($faena);
        $faena = app(RevisarFaenaService::class)->enviar($faena->fresh());
        $this->validarBoletas($faena);

        return app(RevisarFaenaService::class)->aprobar($faena->fresh());
    }

    /** Beneficiario + autorización aprobada + carnet de pescador aprobado. */
    protected function pescadorHabilitado(int $escala = 5): Carnet
    {
        $persona = Beneficiario::factory()->create();
        $cupo = $this->autorizacionAprobada($persona, $escala);

        return $this->aprobarCarnet($this->emitirCarnet($persona, TipoActor::Pescador, $cupo));
    }
}
