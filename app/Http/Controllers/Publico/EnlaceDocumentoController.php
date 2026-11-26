<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\DocumentoEmitido;
use App\Services\DocumentoEmitidoService;
use Illuminate\Http\Response;

/**
 * El PDF guardado en el disco LOCAL, por el enlace firmado de 5 minutos que arma
 * DocumentoEmitidoService::enlaceDescarga() (26/11/2026, descarga de la app). La firma la
 * valida el middleware `signed`; en s3 no se usa: ahí el enlace es el del bucket.
 */
class EnlaceDocumentoController extends Controller
{
    public function __invoke(DocumentoEmitido $emitido, DocumentoEmitidoService $documentos): Response
    {
        return $documentos->archivoLocal($emitido);
    }
}
