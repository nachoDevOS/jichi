<?php

namespace App\Support\ApiApp;

use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\Codigo;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
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

    /**
     * Un recibo suyo por su código público (26/11/2026: lo comparten «Descargar» del
     * portal web y la descarga de la app). Lo ajeno o borrado, null, como lo inexistente.
     */
    public static function recibo(string $codigo, int $beneficiarioId): ?Recibo
    {
        $recibo = Codigo::query()
            ->where('codigo', Carnet::normalizarCodigo($codigo))
            ->where('codigable_type', (new Recibo)->getMorphClass())
            ->first()
            ?->codigable;

        return $recibo instanceof Recibo && ! $recibo->trashed() && $recibo->beneficiario_id === $beneficiarioId
            ? $recibo
            : null;
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
