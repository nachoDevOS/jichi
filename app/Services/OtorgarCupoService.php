<?php

namespace App\Services;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Sireb\SinPrecioException;
use App\Sireb\TarifarioSireb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 *  PASO 2 DEL FLUJO DEL PESCADOR — otorgar la BOLSA MADRE
 */
class OtorgarCupoService
{
    public function __construct(
        private readonly TarifarioSireb $tarifario,
        private readonly LiquidarSirebService $liquidaciones,
        private readonly CorrelativoService $correlativos,
    ) {}

    /**
     * Otorga la bolsa madre a una persona. `$tarifaId` es el tramo (una tarifa
     * del servicio de la escala en SIREB) y `$kilos`, lo que escribe ventanilla.
     */
    public function otorgar(
        Beneficiario $beneficiario,
        string $tarifaId,
        float $kilos,
        string $tipoEmbarcacion,
        ?Carbon $solicitud = null,
    ): AprovechamientoPesq {
        $solicitud ??= now();

        $cupo = DB::transaction(function () use ($beneficiario, $tarifaId, $kilos, $solicitud, $tipoEmbarcacion): AprovechamientoPesq {
            // `lockForUpdate()` devuelve OTRA instancia: acá solo toma el candado,
            // todo lo demás se lee del modelo que llegó.
            Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            $vigente = $this->cupoVigenteDe($beneficiario);

            if ($vigente !== null) {
                throw CupoInvalidoException::yaTieneCupoVigente(
                    $beneficiario->nombreCompleto,
                    $vigente->saldoKg(),
                    $vigente->fecha_vencimiento->format('d/m/Y'),
                );
            }

            $tramo = $this->verificarTramo($tarifaId);

            $cupo = AprovechamientoPesq::create([
                'beneficiario_id' => $beneficiario->id,
                // Correlativo propio, no el id: el talonario no puede tener huecos de filas ajenas.
                'nro' => $this->correlativos->siguienteContinuo(AprovechamientoPesq::SERIE),
                'monto' => $tramo['monto'],
                'sireb_tarifa_id' => $tramo['tarifa_id'],
                // Copia: Recaudaciones puede renombrar o dar de baja la tarifa después.
                'tramo' => $tramo['etiqueta'],
                // Los escribe ventanilla: la tarifa de SIREB solo trae el precio.
                'volumen_total_kg' => $kilos,

                // Obligatorio: va impreso en la autorización. Texto libre porque no
                // hay padrón de embarcaciones.
                'tipo_embarcacion' => $tipoEmbarcacion,

                /*
                 * Nace pendiente, y de ahí sale solo al cobrarse.
                 */
                'estado' => EstadoAprovechamiento::Pendiente,

                // La fecha en que se PIDIÓ. La de otorgamiento la escribe la
                // aprobación: en borrador no hay nada otorgado.
                'fecha_solicitud' => $solicitud->toDateString(),
                'fecha_emision' => null,
                'fecha_vencimiento' => $this->vencimientoDe($solicitud),
            ]);

            // Su llave pública, en la misma transacción: sin código, el documento
            // no se puede verificar.
            $cupo->asignarCodigo();

            // La liquidación y su clave se guardan ACÁ, antes de llamar a SIREB: un reintento usa la misma.
            $this->liquidaciones->preparar($cupo);

            return $cupo;
        });

        $this->liquidaciones->enviarSinFrenar($cupo);

        return $cupo;
    }

    /**
     * Corregir el borrador: solo la embarcación. El tramo no cambia nunca —ni los
     * kilos ni el monto—, así que la liquidación de SIREB queda como está.
     */
    public function editar(AprovechamientoPesq $cupo, string $tipoEmbarcacion): AprovechamientoPesq
    {
        if (! $cupo->puedeEditarse()) {
            throw CupoInvalidoException::noSePuedeEditar($cupo->estado->etiqueta());
        }

        return DB::transaction(function () use ($cupo, $tipoEmbarcacion): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            // Se comprueba con la copia bloqueada, no con la que llegó.
            if (! $bloqueado->puedeEditarse()) {
                throw CupoInvalidoException::noSePuedeEditar($bloqueado->estado->etiqueta());
            }

            $bloqueado->update(['tipo_embarcacion' => $tipoEmbarcacion]);

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $cupo->refresh();
        });
    }

    /**
     *  Eliminar un cupo cargado por error
     */
    public function eliminar(AprovechamientoPesq $cupo, string $motivo): void
    {
        if (! $cupo->puedeEliminarse()) {
            throw CupoInvalidoException::noSePuedeEliminar($cupo->estado->etiqueta());
        }

        // Primero SIREB: si no anula la liquidación, no se elimina, o quedaría una deuda sin trámite.
        $this->liquidaciones->anular($cupo, 'Eliminado en Jichi: '.$motivo);

        DB::transaction(function () use ($cupo, $motivo): void {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEliminacion()) {
                throw CupoInvalidoException::noSePuedeEliminar($bloqueado->estado->etiqueta());
            }

            // Las faenas ya emitidas no se resuelven de ninguna manera: el papel está afuera.
            if (($faenas = $bloqueado->faenas()->count()) > 0) {
                throw CupoInvalidoException::tieneFaenas($faenas);
            }

            /*
             * El motivo se deja en el modelo y se borra: no se llama a
             * `registrarAuditoria()` a mano.
             */
            $bloqueado->motivoAuditoria = $motivo;

            $bloqueado->delete();
        });
    }

    /**
     * Su bolsa madre utilizable hoy, o null.
     */
    private function cupoVigenteDe(Beneficiario $beneficiario): ?AprovechamientoPesq
    {
        return AprovechamientoPesq::query()
            ->deBeneficiario($beneficiario->id)
            /*
             * `enCurso()` y no `vigentes()`: un cupo PENDIENTE DE PAGO ocupa el
             * lugar igual. Con `vigentes()` —que solo mira los activos— alguien
             * podría otorgar cinco cupos seguidos sin pagar ninguno y quedarse
             * con el que más le convenga, que es exactamente lo que la regla de
             * una bolsa por persona viene a impedir.
             */
            ->enCurso()
            ->withSum('faenasQueConsumen', 'kilos_extraidos')
            ->withSum('faenasQueReservan', 'kilos_extraidos')
            ->latest('fecha_solicitud')
            ->first();
    }

    /**
     * Hasta cuándo vale un cupo de esta fecha: el cupo es de la GESTIÓN.
     */
    private function vencimientoDe(Carbon $fecha): string
    {
        return $fecha->copy()->endOfYear()->toDateString();
    }

    /**
     * Lo que otorgar() revisa del tramo antes de escribir: la tarifa activa en
     * SIREB. No escribe nada, así que sirve también para probar.
     *
     * @return array{monto: float, tarifa_id: string, etiqueta: string}
     *
     * @throws CupoInvalidoException
     */
    public function verificarTramo(string $tarifaId): array
    {
        $precio = $this->precioDe($tarifaId);

        return ['monto' => $precio['monto'], 'tarifa_id' => $precio['tarifa_id'], 'etiqueta' => $precio['tarifa']['etiqueta'] ?? ''];
    }

    /**
     * El precio de un tramo según SIREB, el de ahora, con la tarifa activa. Sin
     * eso no se otorga; ninguna tarifa se escribe a mano.
     *
     * @return array{monto: float, tarifa_id: string, tarifa: array}
     */
    public function precioDe(string $tarifaId, ?string $tramo = null): array
    {
        if ($this->tarifario->servicioId(ConceptoArancel::Autorizacion) === null) {
            throw CupoInvalidoException::sinServicio();
        }

        try {
            return $this->tarifario->precio(ConceptoArancel::Autorizacion, $tarifaId);
        } catch (SinPrecioException $e) {
            // El motivo exacto (tarifa de baja, servicio de baja…) es para quien administra SIREB.
            Log::channel('sireb')->warning('SIN PRECIO para la tarifa de la escala '.$tarifaId, [
                'tarifa' => $tarifaId, 'motivo' => $e->getMessage(),
            ]);

            throw CupoInvalidoException::sireb($tramo, $e->sinRespuesta);
        }
    }
}
