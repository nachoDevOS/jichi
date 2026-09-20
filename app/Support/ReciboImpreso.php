<?php

namespace App\Support;

use App\Enums\ConceptoRecibo;
use App\Models\Recibo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 *  EL RECIBO, COMO LO NECESITA LA PLANTILLA IMPRESA
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
     *  ARMA EL RECIBO IMPRESO A PARTIR DEL MODELO
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

    //  Lo que la plantilla lee como si fueran columnas

    public function __get(string $nombre): mixed
    {
        return match ($nombre) {
            // Del beneficiario y no copiados en el recibo: uno solo es el
            // lugar donde se corrige un apellido mal tipeado.
            'beneficiario_nombre' => $this->recibo->beneficiario?->nombreCompleto ?? 'Sin nombre',
            'beneficiario_ci' => $this->recibo->beneficiario?->documento_identidad ?: 'S/N',
            'concepto' => $this->recibo->concepto,

            /*
             * LAS BOLETAS DEL BANCO —TODAS—, no el número del recibo.
             *
             * Va en el renglón «N°» del papel. Ver `boletas()`.
             */
            'nro_deposito' => $this->boletas(),

            default => null,
        };
    }

    /**
     * Todas las boletas del recibo. El papel es UNO por trámite, así que tiene
     * que nombrarlas todas: es con lo que Contabilidad lo cruza contra el banco.
     */
    public function boletas(int $tope = 6): string
    {
        $numeros = $this->recibo->pagos
            ->pluck('nro_transaccion')
            ->filter()
            ->values();

        if ($numeros->count() <= $tope) {
            return $numeros->implode(' · ');
        }

        return $numeros->take($tope)->implode(' · ').' y '.($numeros->count() - $tope).' más';
    }

    /** El monto total, congelado al emitir. */
    public function monto(): float
    {
        return (float) $this->recibo->monto_total;
    }

    /**
     * El número que va en el recuadro N° del papel: 0016.
     */
    public function numeroImpreso(): string
    {
        preg_match('/(\d+)\D*$/', (string) $this->recibo->numero_recibo, $m);

        return $m[1] ?? (string) $this->recibo->numero_recibo;
    }

    /**
     *  EL MONTO EN LETRAS — el renglón «La suma de:»
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
     * @return array<int, array{descripcion: string, monto: float}>
     */
    public function lineas(): array
    {
        return $this->detalle;
    }
}
