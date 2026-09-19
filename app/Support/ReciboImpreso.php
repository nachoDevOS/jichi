<?php

namespace App\Support;

use App\Enums\ConceptoRecibo;
use App\Models\Recibo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * ============================================================================
 *  EL RECIBO, COMO LO NECESITA LA PLANTILLA IMPRESA
 * ============================================================================
 *
 * Es un ADAPTADOR entre el modelo `Recibo` y `views/documentos/recibo-oficial`.
 * La plantilla es una maqueta de coordenadas fijas, medida contra el talonario
 * verde del SEDAG y afinada durante días —los contornos, el sello atenuado, los
 * casilleros de la fecha—. Tocarla para que lea los nombres nuevos de las
 * columnas sería rehacer ese trabajo para no ganar nada.
 *
 * Así que el modelo se adapta a la plantilla, y no al revés: esta clase expone
 * exactamente lo que el Blade pide —`numeroImpreso()`, `montoEnLetras()`,
 * `marca()`, `beneficiario_nombre`…— leyendo del modelo de hoy.
 *
 * ----------------------------------------------------------------------------
 *  A DIFERENCIA DEL ANTERIOR, ACÁ EL NÚMERO SÍ ESTÁ GUARDADO
 * ----------------------------------------------------------------------------
 *
 * El `ReciboArmado` del modelo viejo tenía que usar el id del trámite como
 * número, porque no existía la tabla `recibos` y un correlativo es justamente un
 * dato que no se puede derivar. Hoy la tabla existe y `numero_recibo` es un
 * correlativo de verdad —`REC-2026-0016`, reservado con la fila del contador
 * bloqueada—, así que la serie NO tiene huecos y Contabilidad la puede auditar.
 */
readonly class ReciboImpreso
{
    public function __construct(
        public Recibo $recibo,
        public string $lugar,
        public ConceptoRecibo $descripcion,
        /** @var array<int, array{descripcion: string, monto: float}> */
        public array $detalle,
    ) {}

    /**
     * ========================================================================
     *  ARMA EL RECIBO IMPRESO A PARTIR DEL MODELO
     * ========================================================================
     *
     * Los pagos tienen que venir cargados con su `pagable`, y con `morphWith`:
     * una relación polimórfica NO se precarga con `with('pagable.beneficiario')`
     * —eso se ignora en silencio— y acá cada renglón necesita saber qué trámite
     * pagó.
     */
    public static function desde(Recibo $recibo, string $lugar): self
    {
        $pagos = $recibo->pagos;

        return new self(
            recibo: $recibo,
            lugar: $lugar,
            // La casilla del PRIMER pago: el papel tiene una sola, y el detalle
            // completo va igual renglón por renglón. Ver ConceptoRecibo.
            descripcion: ConceptoRecibo::desdePagable($pagos->first()?->pagable),
            detalle: $pagos
                ->map(fn ($p): array => [
                    'descripcion' => $p->concepto_detalle,
                    'monto' => (float) $p->monto_parcial,
                ])
                ->values()
                ->all(),
        );
    }

    // ------------------------------------------------------------------
    //  Lo que la plantilla lee como si fueran columnas
    // ------------------------------------------------------------------

    public function __get(string $nombre): mixed
    {
        return match ($nombre) {
            'beneficiario_nombre' => $this->recibo->nombre_factura,
            'beneficiario_ci' => $this->recibo->nit_ci_factura,
            'concepto' => $this->recibo->concepto,

            /*
             * EL NÚMERO DE LA BOLETA DEL BANCO, no el del recibo.
             *
             * Va en el renglón «N° de depósito» del papel. Sale del primer pago:
             * cuando un mismo depósito cubre varias líneas, las siguientes
             * llevan el número con sufijo y el que corresponde escribir es el
             * original.
             */
            'nro_deposito' => $this->recibo->pagos->first()?->nro_transaccion,

            default => null,
        };
    }

    /** El monto total, congelado al emitir. */
    public function monto(): float
    {
        return (float) $this->recibo->monto_total;
    }

    /**
     * El número que va en el recuadro N° del papel: 0016.
     *
     * Se imprime SOLO LA PARTE NUMÉRICA de `REC-2026-0016`, porque el recuadro
     * del talonario es angosto y porque el prefijo y el año ya están impresos
     * alrededor. El número completo sigue en la base y en la pantalla.
     */
    public function numeroImpreso(): string
    {
        preg_match('/(\d+)\D*$/', (string) $this->recibo->numero_recibo, $m);

        return $m[1] ?? (string) $this->recibo->numero_recibo;
    }

    /**
     * ========================================================================
     *  EL MONTO EN LETRAS — el renglón «La suma de:»
     * ========================================================================
     *
     * Sale así: «OCHENTA 00/100 BOLIVIANOS».
     *
     * El papel trae preimpreso un «-00/100» al final del renglón, que es la
     * forma clásica de cerrar un importe escrito a mano para que nadie pueda
     * agregarle centavos después. Se reproduce igual, con los centavos reales.
     *
     * `Number::spell()` usa la extensión intl, que ya es requisito del proyecto.
     * Escribir a mano un conversor de número a palabras en castellano son
     * doscientas líneas de casos especiales —«veintiuno», «quinientos», «un
     * millón»— que ya están resueltas y probadas ahí.
     */
    public function montoEnLetras(): string
    {
        $monto = $this->monto();
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        return mb_strtoupper(sprintf(
            '%s %02d/100 BOLIVIANOS',
            Number::spell($entero, locale: 'es'),
            $centavos,
        ));
    }

    /**
     * Los tres recuadros DIA | MES | AÑO del encabezado.
     *
     * Salen de `created_at` —cuándo se emitió el recibo— y no de hoy: una
     * reimpresión de marzo tiene que seguir diciendo marzo.
     *
     * @return array{dia: string, mes: string, anio: string}
     */
    public function fechaEnCasilleros(): array
    {
        $fecha = $this->recibo->created_at ?? Carbon::now();

        return [
            'dia' => $fecha->format('d'),
            'mes' => $fecha->format('m'),
            'anio' => $fecha->format('Y'),
        ];
    }

    /**
     * ¿Va marcada esta casilla de DESCRIPCIÓN?
     *
     * La plantilla dibuja las seis siempre —el recibo tiene que salir igual al
     * papel— y pregunta acá cuál lleva la cruz.
     */
    public function marca(ConceptoRecibo $casilla): bool
    {
        return $this->descripcion === $casilla;
    }

    /**
     * Los renglones del cuadro «IMPORTE A PAGAR Bs.».
     *
     * Uno por cobro, en el orden en que entraron. Un pescador que pagó en dos
     * depósitos ve los dos escritos, igual que en el talonario de papel.
     *
     * @return array<int, array{descripcion: string, monto: float}>
     */
    public function lineas(): array
    {
        return $this->detalle;
    }
}
