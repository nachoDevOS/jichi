<?php

namespace App\Services;

use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * El cobro por QR: SIREB lo genera y Jichi lo muestra tal cual, venga de la pasarela
 * real o sea de prueba (`simulado`). Sin pasarela, se paga por depósito. Ver docs/modulos/PAGO-QR.md.
 */
class CobroQrService
{
    /** Cada cuántos segundos, como mucho, la ventana abierta hace preguntar a SIREB por el pago. */
    private const SEGUNDOS_ENTRE_CONSULTAS = 15;

    public function __construct(
        private readonly SirebService $sireb,
        private readonly ConfirmarPagoService $pagos,
    ) {}

    /**
     * El QR del trámite, si se puede cobrar: liquidación `pendiente` y sin pago cargado.
     *
     * @return array{puede: bool, disponible: bool, mensaje: ?string, qr?: array}
     */
    public function qr(Model $documento, bool $desdeElPanel = false): array
    {
        $consulta = $this->pagos->puedePagarPorQr($documento, $desdeElPanel);

        if (! $consulta['puede']) {
            return [...$consulta, 'disponible' => true];
        }

        try {
            $qr = $this->sireb->qrDePago($documento->sireb_liquidacion_id);
        } catch (SirebException $e) {
            return ['puede' => false, 'disponible' => true, 'mensaje' => $this->motivo($e, $desdeElPanel)];
        }

        if ($qr === null) {
            return [
                'puede' => false,
                'disponible' => false,
                'mensaje' => 'El pago por QR todavía no está habilitado en Recaudaciones. Se puede pagar por depósito '
                    .'bancario con el código de pago '.$documento->sireb_codigo_publico.'.',
            ];
        }

        if (empty($qr['imagen'])) {
            return ['puede' => false, 'disponible' => true, 'mensaje' => 'Recaudaciones no devolvió la imagen del QR. Intente de nuevo o pague por depósito.'];
        }

        if (! $this->anotar($documento, $qr)) {
            return ['puede' => false, 'disponible' => true, 'mensaje' => 'El cobro de este trámite acaba de cambiar. Cierre y vuelva a abrir el QR.'];
        }

        $venceEn = isset($qr['vence_en']) ? Carbon::parse($qr['vence_en']) : null;

        return [
            'puede' => true,
            'disponible' => true,
            'mensaje' => null,
            'qr' => [
                'imagen' => (string) $qr['imagen'],
                'transaccion_id' => $qr['transaccion_id'] ?? null,
                'monto' => (float) ($qr['monto'] ?? $documento->porPagar()),
                'vence_en' => $venceEn?->toIso8601String(),
                // Lo cuenta el servidor: un teléfono con la hora mal daría el QR por vencido o vigente de más.
                'segundos_restantes' => $venceEn ? max(0, (int) now()->diffInSeconds($venceEn, false)) : null,
                'simulado' => (bool) ($qr['simulado'] ?? false),
            ],
        ];
    }

    /**
     * Para la ventana abierta: ¿ya se pagó? Pregunta a SIREB como mucho cada 15 s por
     * trámite (varias pestañas comparten la espera) y, si está pagado, lo aprueba.
     *
     * @return array{pagado: bool, mensaje: ?string}
     */
    public function estado(Model $documento): array
    {
        if (! $documento->estado->estaAbierto()) {
            // Ya no está pendiente: se pagó si tiene recibo (sale solo al aprobarse por el pago).
            return ['pagado' => $documento->recibo()->exists(), 'mensaje' => null];
        }

        // Como VerificarPagoJob: sin liquidación viva no se consulta. Si no, verificar() prepararía
        // y registraría otra en SIREB, y el portal se la estaría pidiendo al titular cada 10 s.
        if (! $documento->registradoEnSireb() || $documento->liquidacionCaida() !== null) {
            return ['pagado' => false, 'mensaje' => null];
        }

        $clave = 'consulta-qr:'.$documento::class.':'.$documento->getKey();

        if (SirebService::sinRespuestaReciente() || ! Cache::add($clave, true, now()->addSeconds(self::SEGUNDOS_ENTRE_CONSULTAS))) {
            return ['pagado' => false, 'mensaje' => null];
        }

        $resultado = $this->pagos->verificar($documento);

        return ['pagado' => $resultado['aprobado'], 'mensaje' => $resultado['aprobado'] ? $resultado['mensaje'] : null];
    }

    /**
     * Paga el QR de PRUEBA y aprueba el trámite: para probar el circuito en test. Solo con un QR
     * que SIREB dio por `simulado`; en producción SIREB responde `QR_SIMULADO_DESHABILITADO`.
     *
     * @return array{aprobado: bool, mensaje: string}
     */
    public function simularPago(Model $documento): array
    {
        $qr = $documento->sireb_envio['qr'] ?? null;

        if (! $documento->estado->estaAbierto() || ($qr['simulado'] ?? false) !== true || empty($qr['transaccion_id'])) {
            return ['aprobado' => false, 'mensaje' => 'Solo se puede simular el pago de un QR de prueba abierto. Vuelva a abrir el QR.'];
        }

        try {
            $this->sireb->simularPagoQr($documento->sireb_liquidacion_id, $qr['transaccion_id']);
        } catch (SirebException $e) {
            // Ya pagada o con pago: el primer intento llegó; se verifica igual.
            if (! in_array($e->codigo, ['LIQUIDACION_NO_PAGABLE', 'PAGO_YA_EXISTE'], true)) {
                return ['aprobado' => false, 'mensaje' => match ($e->codigo) {
                    'QR_VENCIDO', 'QR_INVALIDO' => 'El QR de prueba venció. Cierre la ventana y vuelva a abrirla para generar otro.',
                    'QR_SIMULADO_DESHABILITADO' => 'Este Recaudaciones no simula pagos: solo se puede en el entorno de prueba.',
                    null => 'Recaudaciones no responde en este momento. Intente de nuevo en unos minutos.',
                    default => 'Recaudaciones no aceptó el pago de prueba ('.$e->codigo.').',
                }];
            }
        }

        return $this->pagos->verificar($documento);
    }

    /**
     * El QR vigente en `sireb_envio.qr`, sin la imagen: para el pago de prueba. Solo si cambió,
     * y solo sobre la misma liquidación (ver `anotarEnvioSireb()`). False si cambió de liquidación.
     */
    private function anotar(Model $documento, array $qr): bool
    {
        $nuevo = [
            'transaccion_id' => $qr['transaccion_id'] ?? null,
            'vence_en' => $qr['vence_en'] ?? null,
            'simulado' => (bool) ($qr['simulado'] ?? false),
        ];

        if (($documento->sireb_envio['qr'] ?? null) === $nuevo) {
            return true;
        }

        return $documento->anotarEnvioSireb($documento->sireb_liquidacion_id, ['qr' => $nuevo]);
    }

    private function motivo(SirebException $e, bool $desdeElPanel): string
    {
        return match ($e->codigo) {
            null => 'Recaudaciones no responde en este momento. Intente de nuevo en unos minutos.',
            'PAGO_YA_EXISTE' => 'Ya tiene un pago registrado en Recaudaciones. En unos minutos queda aprobado.',
            'LIQUIDACION_NO_PAGABLE' => $desdeElPanel
                ? 'La liquidación ya no se puede cobrar (pagada, vencida o anulada). Use «Verificar pago».'
                : 'Este código de pago ya no se puede pagar por QR. Actualice la página.',
            default => 'Recaudaciones no pudo generar el QR. Intente de nuevo o pague por depósito.',
        };
    }
}
