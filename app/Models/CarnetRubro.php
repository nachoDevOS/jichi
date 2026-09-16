<?php

namespace App\Models;

use App\Enums\EstadoHabilitacion;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * La habilitación de un rubro dentro de un carnet.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ES UN MODELO Y NO UN PIVOTE ANÓNIMO
 * ----------------------------------------------------------------------------
 *
 * Una tabla intermedia que solo une dos ids no necesita modelo: alcanza con
 * attach() y detach(). Esta guarda además cuándo se habilitó y si sigue
 * habilitada, y esos datos se consultan, se filtran y se AUDITAN.
 *
 * Lo de auditar es lo decisivo. Suspender un rubro es una medida sancionatoria
 * y tiene que quedar registrado quién la tomó y cuándo. El trait Auditable se
 * engancha a los eventos del modelo (`created`, `updated`), y attach() sobre un
 * pivote anónimo NO dispara eventos de modelo: la suspensión pasaría sin dejar
 * rastro. Con un Pivot propio, sí.
 *
 * Extiende Pivot y no Model para que `->using(CarnetRubro::class)` funcione en
 * las relaciones belongsToMany de Carnet y Rubro.
 */
#[Fillable(['carnet_id', 'rubro_id', 'fecha_habilitacion', 'capacidad_kg', 'estado'])]
class CarnetRubro extends Pivot
{
    use Auditable;

    protected $table = 'carnet_rubro';

    /**
     * Pivot los desactiva por defecto —una tabla intermedia clásica no tiene
     * id—, pero esta sí lo tiene y hace falta: la auditoría necesita una clave
     * con la que referirse a la fila, y sin `incrementing` el trait guardaría
     * NULL como `auditable_id`.
     */
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'estado' => EstadoHabilitacion::class,
            'fecha_habilitacion' => 'date',
            // Mismo motivo que en Tramite: el cupo se compara contra kilos
            // declarados, y en punto flotante esas sumas no cierran exactas.
            'capacidad_kg' => 'decimal:2',
        ];
    }

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    public function estaHabilitado(): bool
    {
        return $this->estado === EstadoHabilitacion::Habilitado;
    }
}
