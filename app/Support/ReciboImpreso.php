<?php

namespace App\Support;

use App\Enums\ConceptoRecibo;
use App\Models\Recibo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 *  El recibo, como lo necesita la plantilla impresa
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
     *  Arma el recibo impreso a partir del modelo
     */
    public static function desde(Recibo $recibo, string $lugar): self
    {
        return new self(
            recibo: $recibo,
            lugar: $lugar,
            descripcion: ConceptoRecibo::desdeDocumento($recibo->recibible),
            // Un renglón: un recibo es de un documento.
            detalle: [['descripcion' => $recibo->concepto, 'monto' => (float) $recibo->monto_total]],
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

            // La boleta del banco, no el número del recibo: va en el renglón «N°» del papel.
            'nro_deposito' => trim(($this->recibo->numero_boleta ?? '').' '.($this->recibo->entidad_bancaria ? '('.$this->recibo->entidad_bancaria.')' : '')),

            default => null,
        };
    }

    /** El monto total, congelado al emitir. */
    public function monto(): float
    {
        return (float) $this->recibo->monto_total;
    }

    /**
     * El número que va en el recuadro N° del papel: «000016».
     *
     * Es la columna tal cual. Recortaba con una regex el sufijo de
     * `REC-2026-0016`; desde que el número es continuo y sin prefijo, lo
     * guardado ES lo que va impreso.
     */
    public function numeroImpreso(): string
    {
        return (string) $this->recibo->numero_recibo;
    }

    /**
     *  El monto en letras — el renglón «La suma de:»
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
