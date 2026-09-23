<?php

namespace App\Traits;

use App\Models\Pago;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Lo que sabe hacer un trámite que se cobra: carnet, aprovechamiento o guía.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Pago> $pagos
 * @property-read string|null $pagos_sum_monto_parcial columna virtual que agrega withSum()
 */
trait Pagable
{
    /**
     * Cuánto cuesta este trámite. Lo define cada modelo:
     */
    abstract public function montoACobrar(): float;

    /** Los abonos cargados contra este trámite, del más nuevo al más viejo. */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable')->latest();
    }

    /**
     * Lo entregado hasta hoy.
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

    /**
     * ¿Se pueden CONTROLAR sus depósitos? Solo donde hay circuito de revisión;
     * hoy el aprovechamiento, que lo sobreescribe.
     */
    public function admiteControlDePagos(): bool
    {
        return false;
    }

    /**
     * ¿Se pueden CORREGIR? Se habilita en más momentos que controlar: es lo
     * único que levanta una observación, y observar pasa EN REVISIÓN.
     */
    public function admiteCorreccionDePagos(): bool
    {
        return false;
    }

    /**
     * Los recibos bajo los que se cobró este trámite.
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
