<?php

namespace App\Traits;

use App\Models\DocumentoEmitido;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * El PDF guardado del papel. Lo usan autorización, carnet, faena, guía y recibo.
 * Ver App\Services\DocumentoEmitidoService.
 */
trait ConDocumentoEmitido
{
    /** El vigente: los regenerados quedan dados de baja, como historial. */
    public function documentoEmitido(): MorphOne
    {
        return $this->morphOne(DocumentoEmitido::class, 'emitible');
    }
}
