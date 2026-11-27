<?php

namespace App\Services\ApiApp;

use App\Enums\EstadoFaena;
use App\Enums\OrigenTramite;
use App\Enums\TipoActor;
use App\Enums\TramiteConVigencia;
use App\Exceptions\PermisoOperativoException;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Services\EmitirFaenaService;
use App\Support\Vigencia;
use InvalidArgumentException;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * El permiso de faena pedido por el propio pescador, desde el portal web o la app
 * (decisión del 19/11/2026, REGLAS-NEGOCIO Paso 8). Las reglas son las del panel:
 * emite EmitirFaenaService con su carnet vigente; acá solo se elige ese carnet y se
 * dice, en palabras del titular, por qué hoy no puede.
 */
class PedirFaenaService
{
    /** Sin precio no se registra; el detalle (catálogos de SIREB) es para ventanilla. */
    public const SIN_PRECIO = 'Por ahora no se puede pedir en línea: Recaudaciones no responde. '
        .'Intente en unos minutos o pídalo en ventanilla.';

    public const SIN_CARNET = 'Necesita un carnet de pescador vigente. Se saca en ventanilla de la Unidad de Pesca.';

    public function __construct(private readonly EmitirFaenaService $faenas) {}

    /**
     * Lo que va a pedir (carnet, kilos, arancel, vigencia) o por qué hoy no puede.
     *
     * @return array{puede: bool, motivo: string|null, carnet: array<string, mixed>|null, kilos: float|null, arancel: array{etiqueta: string, monto: float}|null, vigencia: string, propietario_sugerido: string}
     */
    public function datos(Beneficiario $beneficiario): array
    {
        $carnet = $this->carnet($beneficiario);
        $motivo = $carnet === null ? self::SIN_CARNET : $carnet->motivoSinPermisos();

        // En línea, una faena a la vez (26/11/2026, pedido del responsable): con una
        // sin pagar, primero esa. Ventanilla (el panel) no tiene este tope.
        if ($motivo === null && ($sinPagar = $this->faenaSinPagar($beneficiario)) !== null) {
            $motivo = "Tiene el permiso de faena N° {$sinPagar->numero_legible} sin pagar. "
                .'Páguelo primero; después puede pedir otro.';
        }
        $arancel = $motivo === null ? $this->faenas->arancelDeReferencia()['arancel'] : null;

        if ($motivo === null && $arancel === null) {
            $motivo = self::SIN_PRECIO;
        }

        return [
            'puede' => $motivo === null,
            'motivo' => $motivo,
            'carnet' => $carnet === null ? null : [
                'numero' => $carnet->registro_legible,
                'asociacion' => $carnet->asociacion?->nombre,
                'vence' => $carnet->fecha_vencimiento?->toDateString(),
            ],
            // Fijos: los libres de su autorización ese día (la faena no descuenta).
            'kilos' => $carnet?->aprovechamiento === null ? null : (float) $carnet->aprovechamiento->kilos_disponibles,
            'arancel' => $arancel,
            'vigencia' => Vigencia::texto(TramiteConVigencia::Faena),
            // Como el panel: si la embarcación es suya, el propietario es él.
            'propietario_sugerido' => $beneficiario->nombreCompleto,
        ];
    }

    /**
     * Registra la faena con los renglones del talonario. Queda PENDIENTE con su
     * liquidación en SIREB y se aprueba sola al validarse el pago.
     *
     * @param  array<string, string|null>  $papel
     *
     * @throws PermisoOperativoException con un mensaje para el titular
     */
    public function pedir(Beneficiario $beneficiario, array $papel, OrigenTramite $origen): PermisoFaena
    {
        // Lo comparten el portal web y la app: el controlador de cada uno dice cuál fue.
        if (! $origen->esEnLinea()) {
            throw new InvalidArgumentException('Un pedido en línea no puede registrarse como de ventanilla.');
        }

        $datos = $this->datos($beneficiario);

        if (! $datos['puede']) {
            throw new PermisoOperativoException($datos['motivo']);
        }

        return $this->faenas->emitir($this->carnet($beneficiario), $origen, $papel);
    }

    /** La faena suya que todavía espera el pago (la más vieja), o null. */
    private function faenaSinPagar(Beneficiario $beneficiario): ?PermisoFaena
    {
        return $beneficiario->faenas()
            ->where('permisos_faena.estado', EstadoFaena::Pendiente)
            ->orderBy('permisos_faena.nro')
            ->first();
    }

    /** Su carnet de pescador vigente; si no tiene, el último (para decir por qué no sirve). */
    private function carnet(Beneficiario $beneficiario): ?Carnet
    {
        $carnet = $beneficiario->carnetVigenteDe(TipoActor::Pescador)
            ?? $beneficiario->carnets()->where('tipo_actor', TipoActor::Pescador)->latest('id')->first();

        return $carnet?->loadMissing(['aprovechamiento', 'asociacion']);
    }
}
