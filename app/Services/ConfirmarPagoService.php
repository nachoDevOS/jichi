<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Enums\EstadoLiquidacionSireb;
use App\Exceptions\CarnetInvalidoException;
use App\Exceptions\CupoInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El pago se hace y se valida en SIREB; Jichi pregunta. Si la liquidación está
 * `pagada`, aprueba el documento y emite su recibo con la boleta de SIREB, en
 * una transacción. Lo usan el botón de la ficha y `jichi:verificar-pagos`.
 */
class ConfirmarPagoService
{
    /** La CLAVE del contador del recibo, no lo que se imprime: sale «000001». */
    private const SERIE_RECIBO = 'REC';

    public function __construct(
        private readonly SirebService $sireb,
        private readonly LiquidarSirebService $liquidaciones,
        private readonly CorrelativoService $correlativos,
        private readonly RevisarCupoService $cupos,
        private readonly RevisarCarnetService $carnets,
        private readonly RevisarFaenaService $faenas,
        private readonly RevisarGuiaService $guias,
    ) {}

    /**
     * @return array{aprobado: bool, mensaje: string}
     */
    public function verificar(Model $documento): array
    {
        if (! $documento->estado->estaAbierto()) {
            return $this->no('ya no está pendiente.');
        }

        // Sin liquidación viva (una corrección que anuló y no llegó a guardar): se prepara otra.
        if (in_array($documento->sireb_estado, [null, EstadoLiquidacionSireb::Anulada], true)) {
            $this->liquidaciones->preparar($documento);
        }

        try {
            // Si quedó «por enviar», se manda primero con su clave.
            $this->liquidaciones->enviar($documento);
            $liquidacion = $this->sireb->liquidacion($documento->sireb_liquidacion_id);
        } catch (SirebException $e) {
            return $this->no($e->codigo === null
                ? 'Recaudaciones no responde en este momento. Espere unos minutos y vuelva a intentarlo.'
                : 'Recaudaciones no aceptó el cobro. Corrija el trámite o consulte con el encargado del sistema.');
        }

        $pago = $liquidacion['pago'] ?? null;

        // La ficha muestra la boleta mientras se valida. Solo si cambió: el comando corre cada 10 min y audita.
        if (($documento->sireb_envio['pago'] ?? null) !== $pago) {
            $documento->update(['sireb_envio' => [...($documento->sireb_envio ?? []), 'pago' => $pago]]);
        }

        // Venció el plazo de pago sin ningún pago: el trámite no sigue su curso. No es su vigencia.
        if (($liquidacion['estado'] ?? null) === 'vencida' && $pago === null) {
            $this->marcarNoPagado($documento);

            return $this->no('venció el plazo de pago en Recaudaciones sin ningún pago. El trámite quedó «No pagado» y ya no sigue su curso.');
        }

        if (($liquidacion['estado'] ?? null) !== 'pagada' || ($pago['estado'] ?? null) !== 'confirmado') {
            return $this->no(match (true) {
                ($liquidacion['estado'] ?? null) === 'vencida' => 'venció el plazo de pago con un pago todavía en revisión. '
                    .'Consulte con Recaudaciones.',
                ($liquidacion['estado'] ?? null) === 'anulada' => 'su cobro fue anulado en Recaudaciones. '
                    .'Corrija el trámite para generar uno nuevo, o elimínelo.',
                $pago !== null => 'el pago está cargado en Recaudaciones y falta que lo validen allá.',
                default => 'todavía no se registró ningún pago en Recaudaciones (código '.$documento->sireb_codigo_publico.').',
            });
        }

        try {
            DB::transaction(function () use ($documento, $liquidacion, $pago): void {
                match (true) {
                    $documento instanceof AprovechamientoPesq => $this->cupos->aprobar($documento),
                    $documento instanceof Carnet => $this->carnets->aprobar($documento),
                    $documento instanceof PermisoFaena => $this->faenas->aprobar($documento),
                    $documento instanceof GuiaMovimiento => $this->guias->aprobar($documento),
                };

                $this->emitirRecibo($documento, $liquidacion, $pago);
            });
        } catch (CupoInvalidoException|CarnetInvalidoException|PermisoOperativoException $e) {
            // Pagado, pero una regla de Jichi lo frena (p. ej. la autorización fue revocada).
            return $this->no('está pagado en Recaudaciones, pero no se pudo aprobar: '.$e->getMessage());
        }

        return ['aprobado' => true, 'mensaje' => 'Pago confirmado en Recaudaciones: quedó aprobado y se emitió '.$this->documentoEmitido($documento).'.'];
    }

    /**
     * Se intentó eliminar y SIREB tiene un pago: no se borra. Se verifica igual —la ficha
     * muestra el pago y, si ya está validado, el trámite se aprueba—.
     *
     * @return array{aprobado: bool, mensaje: string}
     */
    public function alNoPoderEliminar(Model $documento): array
    {
        $resultado = $this->verificar($documento);

        return [
            'aprobado' => $resultado['aprobado'],
            'mensaje' => match (true) {
                $resultado['aprobado'] => 'No se eliminó: el pago ya fue validado en Recaudaciones. El trámite quedó aprobado y se emitió '
                    .$this->documentoEmitido($documento).'.',
                ($documento->sireb_envio['pago']['estado'] ?? null) === 'pendiente' => 'No se eliminó: tiene un pago cargado en Recaudaciones '
                    .'que todavía está en revisión. Cuando lo validen, quedará aprobado.',
                default => 'No se eliminó. '.$resultado['mensaje'],
            },
        ];
    }

    /** El recibo con la boleta tal como la validó SIREB, congelada. */
    private function emitirRecibo(Model $documento, array $liquidacion, array $pago): void
    {
        $recibo = Recibo::create([
            'beneficiario_id' => $documento->titularSireb()->id,
            'recibible_type' => $documento->getMorphClass(),
            'recibible_id' => $documento->getKey(),
            'numero_recibo' => CorrelativoService::rellenar($this->correlativos->siguienteContinuo(self::SERIE_RECIBO)),
            'monto_total' => (float) ($pago['monto_pagado'] ?? $liquidacion['monto']),
            'concepto' => $this->concepto($documento),
            'numero_boleta' => $pago['numero_boleta'] ?? null,
            'entidad_bancaria' => $pago['entidad_bancaria'] ?? null,
            'fecha_pago' => isset($pago['fecha_pago']) ? Carbon::parse($pago['fecha_pago'])->timezone(config('app.timezone'))->toDateString() : null,
        ]);

        $recibo->asignarCodigo();
    }

    /** Cómo se nombra el documento en el recibo: «Cédula de Pescador - 300 Kg», «Permiso de Faena N° 0003 - 120 kg». */
    private function concepto(Model $documento): string
    {
        $kilos = fn ($valor): string => rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');

        return match (true) {
            $documento instanceof Carnet => 'Cédula de '.$documento->tipo_actor->etiqueta()
                .($documento->aprovechamiento ? ' - '.$kilos($documento->aprovechamiento->volumen_total_kg).' Kg' : ''),
            $documento instanceof AprovechamientoPesq => 'Autorización de Pesca para Aprovechamiento Pesquero'
                .($documento->categoria?->descripcion_kg ? ' - '.$documento->categoria->descripcion_kg : ''),
            $documento instanceof PermisoFaena => $documento->etiqueta.' - '.$kilos($documento->kilos_extraidos).' kg',
            $documento instanceof GuiaMovimiento => $documento->etiqueta,
        };
    }

    /** Pendiente → no pagado, con la fila bloqueada. Libera lo que ocupaba: el lugar de la persona, los kilos reservados. */
    private function marcarNoPagado(Model $documento): void
    {
        DB::transaction(function () use ($documento): void {
            $bloqueado = $documento::query()->whereKey($documento->getKey())->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->estaAbierto()) {
                return;
            }

            $bloqueado->motivoAuditoria = 'Venció el plazo de pago en SIREB sin ningún pago.';
            $bloqueado->update(['estado' => match (true) {
                $bloqueado instanceof AprovechamientoPesq => EstadoAprovechamiento::NoPagado,
                $bloqueado instanceof Carnet => EstadoCarnet::NoPagado,
                $bloqueado instanceof PermisoFaena => EstadoFaena::NoPagado,
                $bloqueado instanceof GuiaMovimiento => EstadoGuia::NoPagada,
            }]);
        });

        // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
        $documento->refresh();
    }

    /** Lo que recibe la persona al aprobarse, para el aviso: «la autorización», «el carnet»… */
    private function documentoEmitido(Model $documento): string
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq => 'la autorización',
            $documento instanceof Carnet => 'el carnet',
            $documento instanceof PermisoFaena => 'el permiso de faena',
            $documento instanceof GuiaMovimiento => 'la guía',
        };
    }

    /** @return array{aprobado: false, mensaje: string} */
    private function no(string $motivo): array
    {
        return ['aprobado' => false, 'mensaje' => 'No se aprobó: '.$motivo];
    }
}
