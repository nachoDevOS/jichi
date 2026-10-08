<?php

namespace App\Services;

use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Database\Eloquent\Model;

/**
 * Carga desde Jichi el pago de un trámite en su liquidación de SIREB. Solo CARGA:
 * la validación la hace un encargado de Recaudaciones y la aprobación sigue siendo
 * de ConfirmarPagoService. Ver docs/modulos/SIREB.md.
 */
class CargarPagoService
{
    public function __construct(
        private readonly SirebService $sireb,
        private readonly ConfirmarPagoService $pagos,
    ) {}

    /**
     * @return array{cargado: bool, mensaje: string}
     */
    public function cargar(Model $documento, string $numeroTransaccion, string $banco): array
    {
        if (! $documento->estado->estaAbierto() || ! $documento->registradoEnSireb()) {
            return $this->no('el trámite ya no está pendiente de pago, o su cobro todavía no llegó a Recaudaciones.');
        }

        try {
            $liquidacion = $this->sireb->liquidacion($documento->sireb_liquidacion_id);
        } catch (SirebException) {
            return $this->no('Recaudaciones no responde en este momento. Espere unos minutos y vuelva a intentarlo.');
        }

        // Se carga solo sobre una liquidación pendiente —ni vencida, ni pagada, ni anulada— y sin ningún pago.
        $estado = $liquidacion['estado'] ?? null;
        $pago = $liquidacion['pago'] ?? null;

        if ($estado !== 'pendiente' || $pago !== null) {
            // Se pone al día la ficha: muestra el pago, aprueba si está pagada, ofrece generar otra si venció.
            $this->pagos->verificar($documento);

            return $this->no(match (true) {
                $pago !== null => 'ya tiene un pago cargado en Recaudaciones (N° de transacción '.($pago['numero_boleta'] ?? '—').').',
                $estado === 'vencida' => 'venció el plazo de pago. Genere una nueva liquidación para cobrarlo.',
                $estado === 'anulada' => 'Recaudaciones anuló el cobro. Genere una nueva liquidación para cobrarlo.',
                $estado === 'pagada' => 'ya está pagado en Recaudaciones.',
                default => 'Recaudaciones no encuentra el cobro de este trámite.',
            });
        }

        try {
            $registrado = $this->sireb->registrarPagoManual($documento->sireb_liquidacion_id, $numeroTransaccion, $banco);
        } catch (SirebException $e) {
            // Pudo quedar cargado igual (timeout) o lo cargaron en el medio: se consulta y la ficha muestra lo que haya.
            $this->pagos->verificar($documento);

            return $this->no($e->codigo === null
                ? 'Recaudaciones no respondió. Revise el pago en la ficha antes de volver a cargarlo.'
                : 'Recaudaciones no lo aceptó: el cobro ya no admite pagos o ya tiene uno cargado.');
        }

        $documento->update(['sireb_envio' => [...($documento->sireb_envio ?? []), 'pago' => $registrado]]);

        return [
            'cargado' => true,
            'mensaje' => 'Pago cargado en Recaudaciones. Queda por validar allá; cuando lo validen, el trámite se aprueba solo.',
        ];
    }

    /** @return array{cargado: false, mensaje: string} */
    private function no(string $motivo): array
    {
        return ['cargado' => false, 'mensaje' => 'No se cargó el pago: '.$motivo];
    }
}
