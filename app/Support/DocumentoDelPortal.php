<?php

namespace App\Support;

use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\Codigo;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use Illuminate\Database\Eloquent\Model;

/**
 * El documento del portal que se pide por su código público: solo uno de los
 * cuatro que se cobran y se imprimen, y solo si es del beneficiario con sesión.
 * Lo ajeno devuelve null igual que lo inexistente: no confirma que el código exista.
 */
class DocumentoDelPortal
{
    public static function buscar(string $codigo, int $beneficiarioId): ?Model
    {
        $documento = Codigo::query()
            ->where('codigo', Carnet::normalizarCodigo($codigo))
            ->first()
            ?->codigable;

        if ($documento === null || $documento->trashed()) {
            return null;
        }

        return self::titular($documento) === $beneficiarioId ? $documento : null;
    }

    /** La faena y la guía llegan a la persona por su carnet. Un recibo no entra. */
    private static function titular(Model $documento): ?int
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq, $documento instanceof Carnet => $documento->beneficiario_id,
            $documento instanceof PermisoFaena, $documento instanceof GuiaMovimiento => $documento->carnet?->beneficiario_id,
            default => null,
        };
    }
}
