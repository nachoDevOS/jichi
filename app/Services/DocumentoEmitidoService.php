<?php

namespace App\Services;

use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\GuiaImpresionController;
use App\Http\Controllers\Panel\PermisoFaenaImpresionController;
use App\Http\Controllers\Panel\ReciboController;
use App\Http\Controllers\StorageController;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\DocumentoEmitido;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * El PDF de cada papel entregado se guarda al emitirse y desde ahí se reimprime
 * tal como salió, sin volver a armarlo. Ver docs/modulos/DOCUMENTOS-EMITIDOS.md.
 */
class DocumentoEmitidoService
{
    /** Carpeta del disco por tipo de papel. */
    private const CARPETAS = [
        AprovechamientoPesq::class => 'documentos/autorizaciones',
        Carnet::class => 'documentos/carnets',
        PermisoFaena::class => 'documentos/faenas',
        GuiaMovimiento::class => 'documentos/guias',
        Recibo::class => 'documentos/recibos',
    ];

    public function __construct(private readonly StorageController $almacen) {}

    /** Cuánto vale el enlace firmado al imprimir por la ruta de Jichi: lo justo para abrirlo. */
    private const MINUTOS_ENLACE = 5;

    /**
     * El PDF guardado. En s3 redirige a un enlace firmado y temporal (el archivo es
     * privado); en el disco local lo entrega Jichi. `$descarga`: lo baja en vez de abrirlo.
     */
    public function respuesta(Model $documento, bool $descarga = false): Response|RedirectResponse
    {
        $emitido = $this->vigenteConArchivo($documento);
        $disposicion = ($descarga ? 'attachment' : 'inline').'; filename="'.self::nombre($documento).'"';

        if ($emitido->disco === 's3') {
            return redirect()->away(Storage::disk('s3')->temporaryUrl($emitido->ruta, now()->addMinutes(self::MINUTOS_ENLACE), [
                'ResponseContentType' => 'application/pdf',
                'ResponseContentDisposition' => $disposicion,
            ]));
        }

        return response(Storage::disk($emitido->disco)->get($emitido->ruta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion,
        ]);
    }

    /** El PDF vigente con su archivo en el disco; si falta, se arma y se guarda. Sin bajarlo: basta preguntar si existe. */
    private function vigenteConArchivo(Model $documento): DocumentoEmitido
    {
        $emitido = $documento->documentoEmitido()->first();

        if ($emitido === null) {
            return $this->guardar($documento);
        }

        if (! Storage::disk($emitido->disco)->exists($emitido->ruta)) {
            Log::warning('PDF emitido ausente: se vuelve a generar.', ['id' => $emitido->id, 'ruta' => $emitido->ruta]);

            return $this->guardar($documento, 'El archivo guardado faltaba en el disco.');
        }

        return $emitido;
    }

    /** Los bytes del PDF guardado. Si falta el archivo o no coincide su huella, se vuelve a guardar. */
    public function contenido(Model $documento): string
    {
        $emitido = $documento->documentoEmitido()->first();

        if ($emitido !== null) {
            $disco = Storage::disk($emitido->disco);
            $bytes = $disco->exists($emitido->ruta) ? $disco->get($emitido->ruta) : null;

            if ($bytes !== null && hash('sha256', $bytes) === $emitido->sha256) {
                return $bytes;
            }

            Log::warning('PDF emitido ausente o alterado: se vuelve a generar.', ['id' => $emitido->id, 'ruta' => $emitido->ruta]);
            $emitido = $this->guardar($documento, 'El archivo guardado faltaba o no coincidía con su huella.');
        } else {
            $emitido = $this->guardar($documento);
        }

        return Storage::disk($emitido->disco)->get($emitido->ruta);
    }

    /**
     * Arma el PDF, lo sube y lo anota. Con `$motivo` reemplaza al vigente, que queda
     * dado de baja como historial (el archivo viejo no se borra).
     */
    public function guardar(Model $documento, ?string $motivo = null): DocumentoEmitido
    {
        $bytes = $this->armar($documento);
        // El archivo se sube ANTES de la transacción: un rollback no borra el disco.
        ['disco' => $disco, 'ruta' => $ruta] = $this->almacen->pdf($bytes, self::CARPETAS[$documento::class]);

        try {
            return DB::transaction(function () use ($documento, $motivo, $bytes, $disco, $ruta): DocumentoEmitido {
                $anterior = $documento->documentoEmitido()->lockForUpdate()->first();

                if ($anterior !== null) {
                    $anterior->motivoAuditoria = $motivo ?? 'Reemplazado por una nueva emisión.';
                    $anterior->delete();
                }

                return $documento->documentoEmitido()->create([
                    'disco' => $disco,
                    'ruta' => $ruta,
                    'sha256' => hash('sha256', $bytes),
                    'bytes' => strlen($bytes),
                    'motivo' => $motivo,
                    'generado_en' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Otra impresión lo guardó al mismo tiempo: vale el suyo, el nuestro sobra.
            Storage::disk($disco)->delete($ruta);

            return $documento->documentoEmitido()->firstOrFail();
        } catch (Throwable $e) {
            Storage::disk($disco)->delete($ruta);

            throw $e;
        }
    }

    /**
     * Al aprobarse: el documento y su recibo. Un fallo no tumba la aprobación;
     * el PDF se arma en la primera impresión.
     */
    public function emitirAlAprobar(Model $documento): void
    {
        foreach (array_filter([$documento, $documento->recibo()->first()]) as $papel) {
            try {
                if (! $papel->documentoEmitido()->exists()) {
                    $this->guardar($papel);
                }
            } catch (Throwable $e) {
                Log::error('No se pudo guardar el PDF emitido.', ['tipo' => $papel::class, 'id' => $papel->getKey(), 'error' => $e->getMessage()]);
            }
        }
    }

    /** Cuánto vale el enlace que lleva la ficha: puede quedar abierta un rato; vencido, el botón usa la ruta de Jichi. */
    private const MINUTOS_ENLACE_FICHA = 30;

    /** El nombre con que se abre o se baja el archivo. */
    public static function nombre(Model $documento): string
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq => "autorizacion-{$documento->numeroLegible()}.pdf",
            $documento instanceof Carnet => 'carnet-'.($documento->registro_legible ?: $documento->codigo_legible).'.pdf',
            $documento instanceof PermisoFaena => "permiso-faena-{$documento->numero_legible}.pdf",
            $documento instanceof GuiaMovimiento => "guia-transporte-{$documento->numero_legible}.pdf",
            $documento instanceof Recibo => "recibo-{$documento->numero_recibo}.pdf",
        };
    }

    /**
     * Lo que muestra la ficha: cuándo se guardó, el comienzo de su huella y, en s3,
     * el enlace firmado para abrirlo directo sin pasar por Jichi. Null si todavía no hay PDF.
     *
     * @return array{generado_en: string, huella: string, enlace: array{url: string, vence: string}|null}|null
     */
    public static function resumen(Model $documento): ?array
    {
        $emitido = self::emitido($documento);

        if ($emitido === null) {
            return null;
        }

        return [
            'generado_en' => $emitido->generado_en->toIso8601String(),
            'huella' => substr($emitido->sha256, 0, 12),
            'enlace' => self::enlace($documento),
        ];
    }

    /**
     * El enlace firmado de S3 para que el botón abra el archivo directo, o null si
     * no hay PDF o no está en s3. Los listados precargan `documentoEmitido`.
     *
     * @return array{url: string, vence: string}|null
     */
    public static function enlace(?Model $documento): ?array
    {
        $emitido = $documento ? self::emitido($documento) : null;

        if ($emitido?->disco !== 's3') {
            return null;
        }

        $vence = now()->addMinutes(self::MINUTOS_ENLACE_FICHA);

        // Firmar es local (no consulta a S3): no demora la pantalla.
        return [
            'url' => Storage::disk('s3')->temporaryUrl($emitido->ruta, $vence, [
                'ResponseContentType' => 'application/pdf',
                'ResponseContentDisposition' => 'inline; filename="'.self::nombre($documento).'"',
            ]),
            'vence' => $vence->toIso8601String(),
        ];
    }

    /** El PDF vigente; con la relación precargada no consulta (un listado no hace N+1). */
    private static function emitido(Model $documento): ?DocumentoEmitido
    {
        return $documento->relationLoaded('documentoEmitido')
            ? $documento->getRelation('documentoEmitido')
            : $documento->documentoEmitido()->first();
    }

    /** El PDF recién armado, con los generadores de siempre. */
    private function armar(Model $documento): string
    {
        $pdf = match (true) {
            $documento instanceof AprovechamientoPesq => app(AutorizacionPescaController::class)->documento($documento),
            $documento instanceof Carnet => app(CarnetImpresionController::class)->documento($documento),
            $documento instanceof PermisoFaena => app(PermisoFaenaImpresionController::class)->documento($documento),
            $documento instanceof GuiaMovimiento => app(GuiaImpresionController::class)->documento($documento),
            $documento instanceof Recibo => app(ReciboController::class)->documento($documento),
        };

        return $pdf->output();
    }
}
