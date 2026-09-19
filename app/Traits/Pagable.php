<?php

namespace App\Traits;

use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Lo que sabe hacer un trámite que se cobra: carnet, aprovechamiento o guía.
 *
 * ============================================================================
 *  POR QUÉ ES UN TRAIT Y NO TRES COPIAS DEL MISMO CÓDIGO
 * ============================================================================
 *
 * Las tres cosas que se cobran se pagan exactamente igual: en abonos, contra
 * un recibo, y el saldo es el precio menos lo entregado. Escrita tres veces,
 * esa resta se desincroniza sola —alguien corrige el corte en cero de un lado
 * y los otros dos siguen devolviendo saldos negativos—.
 *
 * Lo único que cambia entre los tres es DE DÓNDE SALE EL PRECIO, y eso es
 * justamente lo que el trait deja abierto en `montoACobrar()`.
 *
 * ============================================================================
 *  EL SALDO NO SE GUARDA EN NINGUNA COLUMNA, A PROPÓSITO
 * ============================================================================
 *
 * Una columna `saldo` hay que actualizarla en cada alta, cada baja y cada
 * corrección de un abono. Se olvida una y el número queda mintiendo para
 * siempre, sin ningún error que lo delate. Calculado al leer no puede
 * desfasarse: es siempre la resta de lo que hay hoy.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Pago> $pagos
 * @property-read string|null $pagos_sum_monto_parcial columna virtual que agrega withSum()
 */
trait Pagable
{
    /**
     * Cuánto cuesta este trámite. Lo define cada modelo:
     *
     *   - Carnet              → el precio de su tipo de carnet
     *   - AprovechamientoPesq → el valor de la escala con la que se otorgó
     *   - GuiaMovimiento      → el arancel, con el 50% de descuento si es piscicultura
     */
    abstract public function montoACobrar(): float;

    /** Los abonos cargados contra este trámite, del más nuevo al más viejo. */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable')->latest();
    }

    /**
     * Lo entregado hasta hoy.
     *
     * ------------------------------------------------------------------------
     *  LA PRIMERA RAMA ES LO QUE EVITA UNA CONSULTA POR FILA
     * ------------------------------------------------------------------------
     *
     * `$this->pagos()->sum(...)` consulta SIEMPRE, aunque quien llamó haya
     * hecho `withSum('pagos', 'monto_parcial')` justamente para evitarlo. En un
     * listado de treinta carnets eso son treinta consultas agregadas, con el
     * `withSum` escrito, viéndose correcto y sin ningún error.
     *
     * Cuando el atributo agregado vino en la consulta se usa ese; cuando la
     * relación ya está cargada se suma en memoria; recién si no hay ninguno de
     * los dos se consulta. Quien llama no tiene que saber en cuál de los tres
     * casos está.
     *
     * OJO CON LA COMPROBACIÓN: se pregunta si la CLAVE EXISTE, no si el valor
     * es distinto de null. `withSum` NO devuelve 0 cuando no hay filas: devuelve
     * NULL, porque eso es lo que contesta `sum()` en SQL sobre un conjunto
     * vacío. Comparando contra null, justamente el trámite SIN abonos —el que
     * más aparece en un listado— se caía a la consulta suelta, y el withSum
     * quedaba escrito, viéndose correcto, sin ahorrar nada.
     */
    public function montoPagado(): float
    {
        if (array_key_exists('pagos_sum_monto_parcial', $this->getAttributes())) {
            return (float) $this->pagos_sum_monto_parcial;
        }

        if ($this->relationLoaded('pagos')) {
            return (float) $this->pagos->sum('monto_parcial');
        }

        return (float) $this->pagos()->sum('monto_parcial');
    }

    /**
     * Cuánto falta para cubrirlo.
     *
     * Se corta en cero: pagar de más NO genera saldo a favor. Si entró dinero
     * de más, no es un abono de este trámite y se resuelve por caja — dejarlo
     * en negativo lo mostraría como un crédito que el sistema no sabe aplicar.
     */
    public function saldoPendiente(): float
    {
        return max(0.0, round($this->montoACobrar() - $this->montoPagado(), 2));
    }

    /** ¿Está cubierto? Es la condición que habilita a emitir el documento. */
    public function estaPagado(): bool
    {
        return $this->saldoPendiente() <= 0.0;
    }

    /** ¿Se entregó algo pero no todo? Lo que el listado muestra como «parcial». */
    public function tienePagoParcial(): bool
    {
        return $this->montoPagado() > 0.0 && ! $this->estaPagado();
    }

    /**
     * Los recibos bajo los que se cobró este trámite.
     *
     * Son VARIOS y no uno: pagar en dos cuotas son dos papeles distintos, cada
     * uno con su número de caja. Por eso la relación no puede ser un belongsTo
     * colgado del trámite.
     *
     * @return Collection<int, Recibo>
     */
    public function recibos()
    {
        return Recibo::query()
            ->whereIn('id', $this->pagos()->select('recibo_id'))
            ->orderBy('numero_recibo')
            ->get();
    }
}
