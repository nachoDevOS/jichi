<?php

namespace App\Services\ApiApp;

use App\Enums\CondicionProducto;
use App\Enums\MedioTransporte;
use App\Enums\OrigenTramite;
use App\Enums\TipoActor;
use App\Enums\TipoTransporte;
use App\Enums\TramiteConVigencia;
use App\Exceptions\PermisoOperativoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Services\EmitirGuiaService;
use App\Support\Archivos;
use App\Support\Vigencia;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * La guía de transporte pedida por el propio comercializador, desde el portal web o
 * la app (decisión del 19/11/2026, REGLAS-NEGOCIO Paso 8). Las reglas son las del
 * panel: emite EmitirGuiaService con su carnet vigente, que descuenta los kilos del
 * pescador al registrar (Paso 5). Acá solo se elige ese carnet, se busca la
 * autorización por su código y se dice, en palabras del titular, por qué no puede.
 */
class PedirGuiaService
{
    public const SIN_PRECIO = 'Por ahora no se puede pedir en línea: Recaudaciones no responde. '
        .'Intente en unos minutos o pídala en ventanilla.';

    public const SIN_CARNET = 'Necesita un carnet de comercializador vigente. Se saca en ventanilla de la Unidad de Pesca.';

    public const SIN_AUTORIZACION = 'No encontramos una Autorización de Pesca con ese código. '
        .'Revise los 16 caracteres que están debajo del QR del papel del pescador.';

    public function __construct(private readonly EmitirGuiaService $guias) {}

    /**
     * Lo que necesita el formulario (carnet, productos, opciones) o por qué hoy no puede.
     *
     * @return array<string, mixed>
     */
    public function datos(Beneficiario $beneficiario): array
    {
        $carnet = $this->carnet($beneficiario);
        $motivo = $carnet === null ? self::SIN_CARNET : $carnet->motivoSinPermisos();
        // Solo los productos que hoy se pueden cobrar: uno inactivo no se elige en una guía nueva.
        $productos = $motivo === null ? $this->guias->productos() : null;
        $activos = array_values(array_filter($productos ?? [], fn (array $p): bool => $p['estado']));

        if ($motivo === null && $activos === []) {
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
            'productos' => array_map(fn (array $p): array => Arr::only($p, ['id', 'nombre', 'precio_kg']), $activos),
            'condiciones' => CondicionProducto::opciones(),
            'medios' => MedioTransporte::opciones(),
            'tipos_transporte' => TipoTransporte::opciones(),
            'vigencia' => Vigencia::texto(TramiteConVigencia::Guia),
        ];
    }

    /**
     * La autorización del pescador que le vendió, por su código. Solo lo que hace
     * falta para elegirla: nombre abreviado, número y kilos libres, y si tiene foto
     * (27/11/2026: la app la pide aparte, ver fotoDelPescador). Null si no existe.
     *
     * @return array{numero: string, pescador: string, kilos_disponibles: float, vence: string|null, vendible: bool, motivo: string|null, tiene_foto: bool}|null
     */
    public function autorizacion(string $codigo): ?array
    {
        $a = AprovechamientoPesq::porCodigo($codigo)?->loadMissing('beneficiario');

        if ($a === null) {
            return null;
        }

        $motivo = $a->motivoSinGuia();

        return [
            'numero' => $a->numeroLegible(),
            'pescador' => $a->beneficiario === null ? '' : self::nombreAbreviado($a->beneficiario),
            'kilos_disponibles' => (float) $a->kilos_disponibles,
            'vence' => $a->fecha_vencimiento?->toDateString(),
            'vendible' => $motivo === null,
            'motivo' => $motivo,
            'tiene_foto' => self::tieneFoto($a->beneficiario?->foto),
        ];
    }

    /**
     * La foto del carnet del pescador de esa autorización (27/11/2026, decisión del
     * responsable): el comercializador reconoce a quien le vende. Solo con el código
     * de la autorización, el que está en el papel del pescador. Null si el código no
     * existe o no tiene foto.
     */
    public function fotoDelPescador(string $codigo): ?string
    {
        return Archivos::contenido(AprovechamientoPesq::porCodigo($codigo)?->loadMissing('beneficiario')->beneficiario?->foto);
    }

    /** Como Archivos::contenido: una URL de afuera no se sirve. */
    private static function tieneFoto(?string $foto): bool
    {
        return filled($foto) && ! str_starts_with($foto, 'http://') && ! str_starts_with($foto, 'https://');
    }

    /**
     * Registra la guía. Queda PENDIENTE con su liquidación en SIREB, los kilos ya
     * descontados del pescador, y se aprueba sola al validarse el pago.
     *
     * @param  array<string, mixed>  $datos  Los del formulario: ruta, transporte,
     *                                       `autorizacion_codigo`, `detalles` y `observaciones`.
     *
     * @throws PermisoOperativoException con un mensaje para el titular
     */
    public function pedir(Beneficiario $beneficiario, array $datos, OrigenTramite $origen): GuiaMovimiento
    {
        // Lo comparten el portal web y la app: el controlador de cada uno dice cuál fue.
        if (! $origen->esEnLinea()) {
            throw new InvalidArgumentException('Un pedido en línea no puede registrarse como de ventanilla.');
        }

        $resumen = $this->datos($beneficiario);

        if (! $resumen['puede']) {
            throw new PermisoOperativoException($resumen['motivo']);
        }

        $autorizacion = AprovechamientoPesq::porCodigo((string) ($datos['autorizacion_codigo'] ?? ''))
            ?? throw new PermisoOperativoException(self::SIN_AUTORIZACION);

        return $this->guias->emitir(
            $this->carnet($beneficiario),
            [
                ...Arr::except($datos, ['autorizacion_codigo', 'detalles']),
                // Como el panel hoy: la casilla de piscicultura está oculta, toda guía lleva pescador.
                'es_piscicultura' => false,
                'aprovechamiento_id' => $autorizacion->id,
            ],
            $datos['detalles'],
            $origen,
        );
    }

    /**
     * «Juan Carlos Pérez Rivero» → «Juan Carlos P. R.»: lo justo para reconocer a quién
     * le compró, sin entregar el nombre completo a quien tenga un código.
     */
    public static function nombreAbreviado(Beneficiario $b): string
    {
        $inicial = fn (?string $apellido): ?string => blank($apellido) ? null : mb_strtoupper(mb_substr(trim($apellido), 0, 1)).'.';

        return implode(' ', array_filter([
            trim($b->primerNombre.' '.$b->segundoNombre),
            $inicial($b->apellidoPaterno),
            $inicial($b->apellidoMaterno),
        ]));
    }

    /** Su carnet de comercializador vigente; si no tiene, el último (para decir por qué no sirve). */
    private function carnet(Beneficiario $beneficiario): ?Carnet
    {
        $carnet = $beneficiario->carnetVigenteDe(TipoActor::Comercializador)
            ?? $beneficiario->carnets()->where('tipo_actor', TipoActor::Comercializador)->latest('id')->first();

        return $carnet?->loadMissing('asociacion');
    }
}
