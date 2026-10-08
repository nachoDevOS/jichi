<?php

namespace App\Services;

use App\Exceptions\CarnetInvalidoException;
use App\Exceptions\CupoInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * «Generar nueva liquidación»: la vencida o anulada en SIREB pasa a `sireb_historial` y se pide
 * otra con la tarifa que el CATÁLOGO tiene hoy, no la congelada en el trámite (pudo
 * darse de baja). El estado del trámite no cambia. Ver docs/modulos/SIREB.md.
 */
class RenovarLiquidacionService
{
    public function __construct(
        private readonly SirebService $sireb,
        private readonly LiquidarSirebService $liquidaciones,
        private readonly ConfirmarPagoService $pagos,
        private readonly OtorgarCupoService $cupos,
        private readonly EmitirCarnetService $carnets,
        private readonly EmitirFaenaService $faenas,
        private readonly EmitirGuiaService $guias,
    ) {}

    /**
     * @return array{renovada: bool, mensaje: string}
     */
    public function renovar(Model $documento): array
    {
        if (! $documento->estado->estaAbierto()) {
            return $this->no('el trámite ya no está pendiente.');
        }

        if (! $documento->registradoEnSireb()) {
            return $this->no('no tiene una liquidación registrada en Recaudaciones. Use «Verificar pago».');
        }

        // Se vuelve a preguntar: entre la consulta de la ficha y el clic pudieron pagarla.
        try {
            $liquidacion = $this->sireb->liquidacion($documento->sireb_liquidacion_id);
        } catch (SirebException) {
            return $this->no('Recaudaciones no responde en este momento. Espere unos minutos y vuelva a intentarlo.');
        }

        $pago = $liquidacion['pago'] ?? null;
        $estado = $liquidacion['estado'] ?? null;

        if ($estado === 'pagada') {
            // Lo guardado aún dice «vencida» y verificar() cortaría sin preguntar: va lo que SIREB dice ahora.
            $documento->update(['sireb_envio' => [...($documento->sireb_envio ?? []), 'pago' => $pago,
                'consulta' => ['estado' => $liquidacion['estado'] ?? null, 'en' => now()->toIso8601String()]]]);
            $verificacion = $this->pagos->verificar($documento);

            return ['renovada' => false, 'mensaje' => 'No se generó otra: la liquidación ya está pagada. '.$verificacion['mensaje']];
        }

        // Decide solo el estado de la liquidación (08/10/2026).
        if (! in_array($estado, ['vencida', 'anulada'], true)) {
            return $this->no('la liquidación '.$documento->sireb_codigo_publico.' todavía no venció: se sigue pagando con ese código.');
        }

        // Fuera de la transacción: la llamada a SIREB no debe tener filas bloqueadas.
        try {
            $precio = $this->precioVigente($documento);
        } catch (CarnetInvalidoException|CupoInvalidoException|PermisoOperativoException $e) {
            return $this->no($this->motivoSinPrecio($documento, $e));
        }

        $codigoAnterior = $documento->sireb_codigo_publico;
        $montoAnterior = (float) $documento->monto;
        $motivo = ($estado === 'anulada' ? 'Recaudaciones anuló el cobro.' : 'Venció el plazo de pago.')
            .' Se generó una nueva liquidación.';

        $renovada = DB::transaction(function () use ($documento, $precio, $estado, $motivo): bool {
            $bloqueado = $documento::query()->whereKey($documento->getKey())->lockForUpdate()->firstOrFail();

            // Otro clic llegó primero: ya no es la misma liquidación.
            if (! $bloqueado->estado->estaAbierto() || $bloqueado->sireb_liquidacion_id !== $documento->sireb_liquidacion_id) {
                return false;
            }

            $bloqueado->motivoAuditoria = $motivo;
            $this->liquidaciones->cerrarEnHistorial($bloqueado, $estado, $motivo);
            $this->aplicarPrecio($bloqueado, $precio);
            $this->liquidaciones->preparar($bloqueado);

            return true;
        });

        // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
        $documento->refresh();

        if (! $renovada) {
            return $this->no('la liquidación ya se renovó o el trámite cambió. Recargue la ficha.');
        }

        try {
            $this->liquidaciones->enviar($documento);
        } catch (SirebException) {
            return ['renovada' => true, 'mensaje' => 'La liquidación '.$codigoAnterior.' pasó al historial, pero Recaudaciones no '
                .'respondió al registrar la nueva. Use «Verificar pago» para reintentarlo.'];
        }

        $monto = (float) $documento->monto;
        $cambio = abs($monto - $montoAnterior) >= 0.005
            ? ' (la tarifa cambió de '.$this->bs($montoAnterior).' a '.$this->bs($monto).')'
            : '';

        return ['renovada' => true, 'mensaje' => 'Se generó una nueva liquidación: código '.$documento->sireb_codigo_publico
            .', '.$this->bs($monto).$cambio.'. La anterior ('.$codigoAnterior.') quedó en el historial.'];
    }

    /**
     * Lo que cobraría la nueva liquidación, para mostrarlo ANTES de confirmar. Mismo
     * cálculo que renovar(): lo que se ve es lo que se cobra.
     *
     * @return array{monto: ?float, anterior: float, motivo: ?string}
     */
    public function cotizar(Model $documento): array
    {
        $anterior = (float) $documento->monto;

        if (! $documento->puedeRenovarLiquidacion()) {
            return ['monto' => null, 'anterior' => $anterior, 'motivo' => 'Ya no corresponde generar una nueva liquidación. Recargue la ficha.'];
        }

        try {
            return ['monto' => $this->precioVigente($documento)['monto'], 'anterior' => $anterior, 'motivo' => null];
        } catch (CarnetInvalidoException|CupoInvalidoException|PermisoOperativoException $e) {
            return ['monto' => null, 'anterior' => $anterior, 'motivo' => ucfirst($this->motivoSinPrecio($documento, $e))];
        }
    }

    /**
     * La tarifa que el catálogo tiene HOY, con su precio de SIREB. Cada servicio la
     * busca como al emitir; si está de baja, su excepción dice dónde corregirla.
     *
     * @return array{monto: float, tarifa_id?: string, renglones?: array<int, array{monto: float, tarifa_id: string}>}
     */
    private function precioVigente(Model $documento): array
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq => $this->cupos->precioDe($documento->categoria()->withTrashed()->firstOrFail()),
            $documento instanceof Carnet => $this->carnets->precioDe($documento->tipoCarnet()->withTrashed()->firstOrFail()),
            $documento instanceof PermisoFaena => $this->faenas->precioDeLaFaena(),
            $documento instanceof GuiaMovimiento => $this->precioDeLaGuia($documento),
        };
    }

    /** Cada renglón con su tarifa de hoy, y el total como lo cobra la guía (con su descuento). */
    private function precioDeLaGuia(GuiaMovimiento $guia): array
    {
        $renglones = [];
        $importe = 0.0;

        foreach ($guia->detalles()->with(['producto' => fn ($q) => $q->withTrashed()])->get() as $detalle) {
            $renglones[$detalle->id] = $this->guias->precioDelProducto($detalle->producto);
            $importe += GuiaDetalle::importeDe((float) $detalle->cantidad_kg, round($renglones[$detalle->id]['monto'], 2));
        }

        return ['monto' => $guia->arancelCalculado(round($importe, 2)), 'renglones' => $renglones];
    }

    /** Por qué no hay precio, para el aviso. */
    private function motivoSinPrecio(Model $documento, \Throwable $e): string
    {
        // El aviso de otorgar dice «elija otra escala», y el tramo de una autorización no se cambia nunca.
        if ($e instanceof CupoInvalidoException && ! str_contains($e->getMessage(), 'no responde')) {
            return 'la tarifa de SIREB de la escala «'.$documento->categoria?->descripcion_kg.'» está dada de baja o no se '
                .'puede cobrar hoy. Actualícela en Catálogos › Escala y vuelva a intentarlo.';
        }

        return $e->getMessage();
    }

    /** Escribe el precio nuevo sobre la copia bloqueada. En la guía, renglón por renglón. */
    private function aplicarPrecio(Model $bloqueado, array $precio): void
    {
        if (! $bloqueado instanceof GuiaMovimiento) {
            $bloqueado->update(['monto' => $precio['monto'], 'sireb_tarifa_id' => $precio['tarifa_id']]);

            return;
        }

        $importe = 0.0;

        foreach ($bloqueado->detalles as $detalle) {
            $nuevo = $precio['renglones'][$detalle->id];
            $precioKg = round($nuevo['monto'], 2);
            $total = GuiaDetalle::importeDe((float) $detalle->cantidad_kg, $precioKg);
            $detalle->update(['precio_kg' => $precioKg, 'sireb_tarifa_id' => $nuevo['tarifa_id'], 'importe_total' => $total]);
            $importe += $total;
        }

        $bloqueado->update(['monto' => $bloqueado->arancelCalculado(round($importe, 2))]);
    }

    private function bs(float $monto): string
    {
        return 'Bs '.number_format($monto, 2, ',', '.');
    }

    /** @return array{renovada: false, mensaje: string} */
    private function no(string $motivo): array
    {
        return ['renovada' => false, 'mensaje' => 'No se generó una nueva liquidación: '.$motivo];
    }
}
