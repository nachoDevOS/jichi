<?php

namespace App\Services;

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
use Illuminate\Support\Facades\Cache;
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
     * Con un candado por trámite: el botón, el comando y el portal no lo consultan a la vez.
     *
     * @return array{aprobado: bool, mensaje: string}
     */
    public function verificar(Model $documento): array
    {
        $candado = Cache::lock('candado-pago:'.$documento::class.':'.$documento->getKey(), 120);

        if (! $candado->get()) {
            return $this->no('ya se está consultando en este momento. Espere unos segundos.');
        }

        try {
            return $this->consultar($documento);
        } finally {
            $candado->release();
        }
    }

    /**
     * Reserva la consulta automática de un trámite por unos minutos: si el portal o el
     * comando ya lo preguntaron hace poco, el otro no lo vuelve a preguntar.
     */
    public static function reservarConsulta(Model $documento, int $minutos = 2): bool
    {
        return Cache::add('consulta-pago:'.$documento::class.':'.$documento->getKey(), true, now()->addMinutes($minutos));
    }

    /**
     * @return array{aprobado: bool, mensaje: string}
     */
    private function consultar(Model $documento): array
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
        $estado = $liquidacion['estado'] ?? null;

        // La ficha muestra la boleta y si venció. Solo si cambió: el comando corre cada 10 min y audita.
        if (($documento->sireb_envio['pago'] ?? null) !== $pago || ($documento->sireb_envio['consulta']['estado'] ?? null) !== $estado) {
            $documento->update(['sireb_envio' => [
                ...($documento->sireb_envio ?? []),
                'pago' => $pago,
                'consulta' => ['estado' => $estado, 'en' => now()->toIso8601String()],
            ]]);
        }

        // Decide SOLO el estado de la liquidación: `pagada` aprueba; vencida, anulada y pendiente
        // no tocan el estado del trámite (08/10/2026).
        if ($estado !== 'pagada') {
            return $this->no(match (true) {
                in_array($estado, ['vencida', 'anulada'], true) => $this->motivoCaida($estado),
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
                $this->liquidaciones->cerrarEnHistorial($documento, 'pagada');
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
    private function emitirRecibo(Model $documento, array $liquidacion, ?array $pago): void
    {
        // Aprueba `pagada` aunque SIREB no mande el detalle del pago: el recibo sale con el monto de la liquidación.
        $pago ??= [];

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

    /** El aviso de una liquidación que ya no se puede pagar. */
    private function motivoCaida(?string $estado): string
    {
        return ($estado === 'anulada' ? 'Recaudaciones anuló el cobro.' : 'venció el plazo de pago.')
            .' El trámite sigue pendiente: genere una nueva liquidación para cobrarlo.';
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
