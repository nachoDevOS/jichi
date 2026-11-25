<?php

namespace App\Http\Middleware\ApiApp;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * `Idempotency-Key` en lo que cobra (pedir faena y guía): un doble toque o un reintento
 * tras un corte de red devuelve lo ya registrado, sin otro cobro en SIREB ni otro
 * descuento de kilos. Ver docs/api/seguridad.md § 3, F.
 */
class Idempotente
{
    /** Cuánto se recuerda una respuesta: de sobra para cualquier reintento. */
    private const HORAS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) $request->header('Idempotency-Key', '');

        if (preg_match('/^[A-Za-z0-9-]{16,100}$/', $clave) !== 1) {
            return response()->json([
                'message' => 'Falta el encabezado Idempotency-Key: un UUID nuevo por cada pedido.',
                'codigo' => 'idempotency_key_requerida',
            ], 400);
        }

        // Por cuenta y por ruta: la misma clave de otra persona u otro trámite es otro pedido.
        $llave = 'idempotencia:'.$request->user()->id.':'.$request->route()->getName().':'.hash('sha256', $clave);
        $huella = hash('sha256', (string) json_encode($request->all()));

        if (($guardada = Cache::get($llave)) !== null) {
            return $this->repetir($guardada, $huella);
        }

        // Dos pedidos con la misma clave a la vez: el segundo espera su turno afuera.
        $candado = Cache::lock($llave.':candado', 30);

        if (! $candado->get()) {
            return response()->json([
                'message' => 'Ese pedido ya se está registrando. Espere unos segundos.',
                'codigo' => 'pedido_en_curso',
            ], 409);
        }

        try {
            // Otro pudo terminar entre la primera lectura y el candado.
            if (($guardada = Cache::get($llave)) !== null) {
                return $this->repetir($guardada, $huella);
            }

            $respuesta = $next($request);

            // Solo lo registrado: con un 422 el titular corrige y reintenta con la misma clave.
            if ($respuesta->isSuccessful()) {
                Cache::put($llave, [
                    'huella' => $huella,
                    'estado' => $respuesta->getStatusCode(),
                    'cuerpo' => $respuesta->getContent(),
                ], now()->addHours(self::HORAS));
            }

            return $respuesta;
        } finally {
            $candado->release();
        }
    }

    /** @param array{huella: string, estado: int, cuerpo: string} $guardada */
    private function repetir(array $guardada, string $huella): Response
    {
        if (! hash_equals($guardada['huella'], $huella)) {
            return response()->json([
                'message' => 'Esa Idempotency-Key ya se usó con otros datos. Use una nueva para un pedido nuevo.',
                'codigo' => 'idempotency_key_reusada',
            ], 422);
        }

        return response($guardada['cuerpo'], $guardada['estado'], [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
