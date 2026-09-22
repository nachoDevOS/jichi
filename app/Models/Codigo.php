<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La llave pública de un documento. Ver App\Traits\Codificable.
 */
#[Fillable(['codigo', 'codigable_type', 'codigable_id'])]
class Codigo extends Model
{
    use SoftDeletes;

    protected $table = 'codigos';

    /**
     * El documento al que pertenece.
     *
     * VA CON `withTrashed()`: si se anula un carnet y alguien escanea su QR, la
     * respuesta correcta es «este documento fue anulado», no «no existe».
     * Sin esto, anular vuelve invisible en vez de inválido.
     */
    public function codigable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
