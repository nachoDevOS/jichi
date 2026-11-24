<?php

namespace App\Http\Controllers\Sireb;

use App\Http\Controllers\Controller;
use App\Jobs\VerificarPagoJob;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * El webhook de SIREB/Paititi: avisa que una liquidación se pagó, venció o se anuló.
 * No se cree en el aviso: se verifica la firma y se vuelve a CONSULTAR a SIREB
 * (VerificarPagoJob), que es quien aprueba. Ver docs/modulos/PAGO-QR.md.
 */
class AvisoSirebController extends Controller
{
    /** SIREB rechaza la entrega vieja a los 5 minutos; reintenta hasta 24 h: se recuerda 3 días. */
    private const MAX_ANTIGUEDAD = 300;

    private const DIAS_RECORDADO = 3;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->firmaValida($request)) {
            Log::channel('sireb')->warning('AVISO RECHAZADO: firma inválida, vieja o sin secreto configurado', [
                'entrega' => $request->header('X-Paititi-Entrega'),
                'evento' => $request->header('X-Paititi-Evento'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['codigo' => 'FIRMA_INVALIDA'], 401);
        }

        $entrega = (string) $request->header('X-Paititi-Entrega');
        $evento = (string) $request->input('evento');
        $liquidacionId = $request->input('liquidacion.id');

        // Un aviso puede llegar más de una vez: el repetido se contesta 200 y no se procesa.
        if ($entrega === '' || Cache::has('aviso-sireb:'.$entrega)) {
            return response()->json(['recibido' => true, 'repetido' => true]);
        }

        // Validado antes de consultar: en PostgreSQL un uuid mal formado es un 500, y SIREB reintentaría 24 h.
        $documento = is_string($liquidacionId) && Str::isUuid($liquidacionId) ? $this->documento($liquidacionId) : null;

        Log::channel('sireb')->info("AVISO {$evento} de la liquidación ".($liquidacionId ?? '—'), [
            'entrega' => $entrega,
            'referencia' => $request->input('liquidacion.referencia_externa'),
            'estado' => $request->input('liquidacion.estado'),
            'tramite' => $documento ? class_basename($documento).' #'.$documento->getKey() : 'no es de Jichi o no existe',
        ]);

        // Después de responder: SIREB espera 2xx en menos de 10 s.
        if ($documento !== null && $documento->estado->estaAbierto()) {
            VerificarPagoJob::dispatchAfterResponse($documento::class, $documento->getKey());
        }

        // Recién ahora se recuerda: si algo de arriba falló, el reintento de SIREB se procesa.
        Cache::put('aviso-sireb:'.$entrega, true, now()->addDays(self::DIAS_RECORDADO));

        return response()->json(['recibido' => true]);
    }

    /** HMAC-SHA256(secreto, timestamp + "." + cuerpo CRUDO), en tiempo constante, y de menos de 5 minutos. */
    private function firmaValida(Request $request): bool
    {
        $secreto = (string) config('jichi.sireb.webhook_secreto');
        $momento = (string) $request->header('X-Paititi-Timestamp');

        if ($secreto === '' || ! ctype_digit($momento) || abs(time() - (int) $momento) > self::MAX_ANTIGUEDAD) {
            return false;
        }

        $esperada = 'sha256='.hash_hmac('sha256', $momento.'.'.$request->getContent(), $secreto);

        return hash_equals($esperada, (string) $request->header('X-Paititi-Firma'));
    }

    /** El trámite de esa liquidación: los cuatro documentos guardan su id de SIREB. */
    private function documento(string $liquidacionId): ?Model
    {
        $id = mb_strtolower(trim($liquidacionId));

        foreach ([AprovechamientoPesq::class, Carnet::class, PermisoFaena::class, GuiaMovimiento::class] as $clase) {
            if ($documento = $clase::query()->where('sireb_liquidacion_id', $id)->first()) {
                return $documento;
            }
        }

        return null;
    }
}
