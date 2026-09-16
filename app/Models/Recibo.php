<?php

namespace App\Models;

use App\Enums\ConceptoRecibo;
use App\Enums\FormaPago;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

/**
 * El RECIBO OFICIAL que se le entrega al pescador.
 *
 * Todo lo que hay acá es una COPIA CONGELADA del momento en que se entregó el
 * papel. El porqué está en la migración: un recibo numerado que ya salió del
 * mostrador no puede cambiar porque alguien corrija una ficha después.
 *
 * Por eso este modelo casi no tiene lógica de negocio —esa vive en
 * ReciboTramiteService— y sí varios métodos de PRESENTACIÓN: son las formas en
 * que el talonario de papel muestra cada dato, y están acá porque las necesitan
 * tanto la plantilla del PDF como la pantalla del panel.
 */
#[Fillable([
    'tramite_id',
    'numero',
    'gestion',
    'beneficiario_nombre',
    'beneficiario_ci',
    'concepto',
    'detalle',
    'descripcion',
    'monto',
    'forma_pago',
    'nro_deposito',
    'lugar',
    'fecha_emision',
])]
class Recibo extends Model
{
    use Auditable;

    /** Cuántos dígitos tiene el número impreso: 0016. */
    public const DIGITOS = 4;

    protected function casts(): array
    {
        return [
            'descripcion' => ConceptoRecibo::class,
            'forma_pago' => FormaPago::class,
            'detalle' => 'array',
            'monto' => 'decimal:2',
            'fecha_emision' => 'date',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    /**
     * El expediente que lo originó. Puede ser NULL.
     *
     * El recibo le SOBREVIVE al trámite: si la fila del expediente se va, esta
     * queda con `tramite_id` en null —ver la migración—. Por eso todo lo que la
     * plantilla necesita está copiado en esta tabla y nada se lee a través de
     * esta relación.
     *
     * Con las reglas de hoy un trámite que ya tiene recibo no se puede borrar
     * —el recibo nace al enviar a revisión, y desde ahí solo se aprueba o se
     * rechaza—, pero el papel numerado está afuera, en manos del pescador, y no
     * se lo puede dejar dependiendo de que esa regla no cambie nunca.
     */
    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    // ------------------------------------------------------------------
    //  Presentación — cómo lo muestra el papel
    // ------------------------------------------------------------------

    /**
     * El número tal como va impreso arriba a la derecha: 0016.
     *
     * Los ceros a la izquierda no son decoración: el talonario de papel viene
     * numerado con ancho fijo, y el recibo digital tiene que poder archivarse
     * junto a los viejos sin que salte a la vista cuál es cuál.
     */
    public function numeroImpreso(): string
    {
        return str_pad((string) $this->numero, self::DIGITOS, '0', STR_PAD_LEFT);
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
     * Number::spell() usa la extensión intl, que ya es requisito del proyecto.
     * Escribir a mano un conversor de número a palabras en castellano son
     * doscientas líneas de casos especiales —«veintiuno», «quinientos»,
     * «un millón»— que ya están resueltas y probadas ahí.
     */
    public function montoEnLetras(): string
    {
        $entero = (int) floor((float) $this->monto);
        $centavos = (int) round(((float) $this->monto - $entero) * 100);

        $letras = Number::spell($entero, locale: 'es');

        return mb_strtoupper(sprintf(
            '%s %02d/100 BOLIVIANOS',
            $letras,
            $centavos,
        ));
    }

    /**
     * ========================================================================
     *  LOS RENGLONES DEL CUADRO «IMPORTE A PAGAR Bs.»
     * ========================================================================
     *
     * Uno por cobro, en el orden en que entraron, y el TOTAL al pie lo pone la
     * plantilla sumándolos. Un pescador que pagó en dos depósitos ve los dos
     * escritos, igual que en el talonario de papel.
     *
     * Salen de la copia congelada —ver la migración— y NUNCA de `pagos`: un
     * trámite sigue aceptando depósitos después de pasar a revisión, y leerlos
     * al imprimir haría que una reimpresión mostrara plata que entró DESPUÉS de
     * haber entregado el papel.
     *
     * FILAS ANTERIORES A LA COLUMNA `detalle`: se arma un renglón único con el
     * monto total, que es exactamente lo que esos recibos decían. Así una
     * reimpresión vieja sale igual que el original.
     *
     * @return array<int, array{descripcion: string, monto: float}>
     */
    public function lineas(): array
    {
        $detalle = $this->detalle ?? [];

        if ($detalle === []) {
            return [[
                'descripcion' => $this->concepto,
                'monto' => (float) $this->monto,
            ]];
        }

        return array_map(fn (array $linea): array => [
            'descripcion' => (string) ($linea['descripcion'] ?? ''),
            'monto' => (float) ($linea['monto'] ?? 0),
        ], $detalle);
    }

    /**
     * Los tres recuadros DIA | MES | AÑO del encabezado.
     *
     * El papel los trae separados, cada uno en su casillero, y así se
     * reproducen. El año va con cuatro dígitos porque el casillero del talonario
     * es ancho y porque un recibo archivado sin siglo se vuelve ambiguo.
     *
     * @return array{dia: string, mes: string, anio: string}
     */
    public function fechaEnCasilleros(): array
    {
        return [
            'dia' => $this->fecha_emision?->format('d') ?? '',
            'mes' => $this->fecha_emision?->format('m') ?? '',
            'anio' => $this->fecha_emision?->format('Y') ?? '',
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

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    public function scopeDeGestion(Builder $query, ?int $gestion = null): Builder
    {
        return $query->where($query->qualifyColumn('gestion'), $gestion ?? (int) now()->format('Y'));
    }
}
