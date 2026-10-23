<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El PDF de un papel entregado, guardado tal como se emitió. Lo arma y lo sirve
 * App\Services\DocumentoEmitidoService; se escribe con StorageController::pdf().
 */
#[Fillable(['emitible_type', 'emitible_id', 'disco', 'ruta', 'sha256', 'bytes', 'motivo', 'generado_en'])]
class DocumentoEmitido extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'documentos_emitidos';

    protected function casts(): array
    {
        return [
            'generado_en' => 'datetime',
            'bytes' => 'integer',
        ];
    }

    /** El papel: con `withTrashed()`, el PDF de algo dado de baja sigue sabiendo de qué es. */
    public function emitible(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
