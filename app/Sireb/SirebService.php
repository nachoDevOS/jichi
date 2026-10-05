<?php

namespace App\Sireb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente del gateway de Recaudaciones con el token de Ibare: lee el catálogo
 * del SEDAG y registra liquidaciones. Ver docs/modulos/SIREB.md.
 */
class SirebService
{
    private const CACHE_TOKEN = 'sireb.token';

    private const CACHE_SERVICIOS = 'sireb.servicios';

    private const RUTA_SERVICIOS = '/api/v1/catalogo/servicios';

    private const RUTA_LIQUIDACIONES = '/api/v1/liquidaciones';

    /** Reintentos de una escritura ante red, timeout o 5xx, siempre con la misma clave. */
    private const REINTENTOS = 2;

    /**
     * Token de máquina (client_credentials) emitido por Ibare. Sin refresh_token:
     * vencido, se pide otro con las mismas credenciales.
     */
    public function token(bool $refrescar = false): string
    {
        if (! $refrescar && is_string($token = Cache::get(self::CACHE_TOKEN))) {
            return $token;
        }

        try {
            $respuesta = Http::asForm()->acceptJson()
                ->timeout(config('jichi.sireb.timeout'))
                ->post(config('jichi.sireb.ibare_url').'/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('jichi.sireb.client_id'),
                    'client_secret' => config('jichi.sireb.client_secret'),
                ]);
        } catch (ConnectionException) {
            throw SirebException::noResponde();
        }

        $token = $respuesta->json('access_token');

        if (! $respuesta->successful() || ! is_string($token)) {
            Log::warning('Ibare no emitió el token para SIREB', ['status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::rechazado($respuesta->status());
        }

        // Un minuto antes de que venza, para no usarlo en el borde.
        $vida = max(60, (int) $respuesta->json('expires_in', 600) - 60);
        Cache::put(self::CACHE_TOKEN, $token, now()->addSeconds($vida));

        return $token;
    }

    /**
     * Una página del catálogo TAL COMO LA MANDA SIREB, sin resumir ni cachear.
     * SIREB filtra por la dependencia del token: solo llegan servicios del SEDAG.
     * Con `tarifas=todas`: cada tarifa trae `estado`, `tarifario_estado` y `liquidable`.
     */
    public function catalogoCrudo(int $pagina = 1): array
    {
        return $this->get(self::RUTA_SERVICIOS, ['pagina' => $pagina, 'por_pagina' => 100, 'tarifas' => 'todas']) ?? [];
    }

    /**
     * Todos los servicios del SEDAG con sus tarifas, juntando las páginas.
     * En caché 10 minutos: un precio recién cambiado en SIREB tarda eso en llegar.
     */
    public function servicios(bool $refrescar = false): array
    {
        if ($refrescar) {
            Cache::forget(self::CACHE_SERVICIOS);
        }

        return Cache::remember(self::CACHE_SERVICIOS, now()->addMinutes(config('jichi.sireb.cache_minutos')), function (): array {
            $servicios = [];
            $pagina = 1;

            do {
                $cuerpo = $this->catalogoCrudo($pagina);
                array_push($servicios, ...($cuerpo['data'] ?? []));
                $pagina++;
            } while ($pagina <= (int) ($cuerpo['meta']['total_paginas'] ?? 1));

            return $servicios;
        });
    }

    /**
     * Igual que servicios(), pero null si SIREB no responde: para las pantallas
     * que solo MUESTRAN precios y tienen que abrir igual.
     */
    public function serviciosSiResponde(): ?array
    {
        try {
            return $this->servicios();
        } catch (SirebException) {
            return null;
        }
    }

    /**
     * Un servicio con sus tarifas, pedido directo a SIREB y sin caché: el precio
     * que devuelve es el de ahora. Null si SIREB no tiene ese id.
     */
    public function servicio(string $servicioId): ?array
    {
        return $this->get(self::RUTA_SERVICIOS.'/'.$this->id($servicioId))['data'] ?? null;
    }

    /**
     * Una tarifa con su servicio adentro (`servicio`), sin caché: el estado y el
     * precio son los de ahora. Null si SIREB no la tiene bajo ese servicio.
     */
    public function tarifa(string $servicioId, string $tarifaId): ?array
    {
        return $this->get(self::RUTA_SERVICIOS.'/'.$this->id($servicioId).'/tarifas/'.$this->id($tarifaId))['data'] ?? null;
    }

    /**
     * Registra la deuda en SIREB y devuelve la liquidación (`id`, `codigo_publico`…).
     * `$cuerpo`: `cliente` {ci_nit, nombre_completo}, `referencia_externa` e `items`;
     * SIREB crea al cliente si su C.I. no existe. Con la misma `$clave` devuelve la
     * original en vez de crear otra, así que se puede reintentar con ella.
     */
    public function registrarLiquidacion(array $cuerpo, string $clave): array
    {
        $respuesta = $this->enviar('post', self::RUTA_LIQUIDACIONES, $cuerpo, ['Idempotency-Key' => $clave], self::REINTENTOS);

        return $this->liquidacionDe($respuesta, $cuerpo['referencia_externa'] ?? null);
    }

    /**
     * El detalle de una liquidación: su `estado` (pendiente / pagada / anulada /
     * vencida) y su `pago` (boleta, banco, fecha, `confirmado`). Null si no existe.
     *
     * @throws SirebException
     */
    public function liquidacion(string $liquidacionId): ?array
    {
        return $this->get(self::RUTA_LIQUIDACIONES.'/'.$this->id($liquidacionId))['data'] ?? null;
    }

    /**
     * Carga la boleta con que se pagó. Queda `pendiente` hasta que la valida un encargado
     * de SIREB. Sin Idempotency-Key, pero reintentar es seguro: un segundo pago da `PAGO_YA_EXISTE`.
     *
     * @throws SirebException
     */
    public function registrarPagoManual(string $liquidacionId, string $numeroBoleta, string $entidadBancaria): array
    {
        $ruta = self::RUTA_LIQUIDACIONES.'/'.$this->id($liquidacionId).'/pago-manual';
        $respuesta = $this->enviar('post', $ruta, [
            'numero_boleta' => mb_substr($numeroBoleta, 0, 50),
            'entidad_bancaria' => mb_substr($entidadBancaria, 0, 100),
        ], reintentos: self::REINTENTOS);

        if (in_array($respuesta->status(), [404, 422], true)) {
            throw SirebException::liquidacionRechazada((string) $respuesta->json('codigo'));
        }

        return $this->cuerpo($respuesta, $ruta)['data'] ?? [];
    }

    /**
     * Anula en SIREB una liquidación pendiente y sin pago. Anular dos veces no
     * falla: si SIREB dice que ya está anulada, se da por hecho.
     */
    public function anularLiquidacion(string $liquidacionId, string $motivo): void
    {
        $ruta = self::RUTA_LIQUIDACIONES.'/'.$this->id($liquidacionId).'/anular';
        $respuesta = $this->enviar('patch', $ruta, ['motivo' => mb_substr($motivo, 0, 500)], reintentos: self::REINTENTOS);

        if ($respuesta->status() === 422) {
            if (($this->liquidacion($liquidacionId)['estado'] ?? null) === 'anulada') {
                return;
            }
        }

        $this->liquidacionDe($respuesta, $liquidacionId);
    }

    /** La liquidación de una respuesta; un 404 o 422 es un «no» de SIREB con su código. */
    private function liquidacionDe(Response $respuesta, ?string $referencia): array
    {
        if (in_array($respuesta->status(), [404, 422], true)) {
            Log::warning('SIREB rechazó la liquidación', ['referencia' => $referencia, 'cuerpo' => $respuesta->body()]);

            throw SirebException::liquidacionRechazada((string) $respuesta->json('codigo'));
        }

        $liquidacion = $this->cuerpo($respuesta, self::RUTA_LIQUIDACIONES)['data'] ?? null;

        if (! is_array($liquidacion) || ! isset($liquidacion['id'])) {
            throw SirebException::noResponde();
        }

        return $liquidacion;
    }

    /** La respuesta de SIREB, o null si el recurso no existe (404). */
    private function get(string $ruta, array $consulta = []): ?array
    {
        $respuesta = $this->enviar('get', $ruta, $consulta);

        return $respuesta->status() === 404 ? null : $this->cuerpo($respuesta, $ruta);
    }

    /** Pide con el token en caché; si SIREB dice 401 (pudo vencer en el medio), pide otro y reintenta una vez. */
    private function enviar(string $metodo, string $ruta, array $datos = [], array $cabeceras = [], int $reintentos = 0): Response
    {
        $respuesta = $this->pedir($metodo, $ruta, $datos, $cabeceras, $reintentos, $this->token());

        if ($respuesta->status() === 401) {
            $respuesta = $this->pedir($metodo, $ruta, $datos, $cabeceras, $reintentos, $this->token(refrescar: true));
        }

        if (in_array($respuesta->status(), [401, 403], true)) {
            Log::warning('SIREB rechazó la consulta', ['ruta' => $ruta, 'status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::rechazado($respuesta->status());
        }

        return $respuesta;
    }

    /** `$reintentos`: veces de más ante red, timeout o 5xx, con 1 s de pausa. Un 4xx no cambia por insistir. */
    private function pedir(string $metodo, string $ruta, array $datos, array $cabeceras, int $reintentos, string $token): Response
    {
        try {
            return Http::acceptJson()
                ->withToken($token)
                ->withHeaders($cabeceras)
                ->timeout(config('jichi.sireb.timeout'))
                ->retry(
                    $reintentos + 1,
                    1000,
                    fn (Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->{$metodo}(config('jichi.sireb.url').$ruta, $datos);
        } catch (ConnectionException) {
            throw SirebException::noResponde();
        }
    }

    /** El JSON de una respuesta exitosa; cualquier otra cosa es «no responde». */
    private function cuerpo(Response $respuesta, string $ruta): array
    {
        if (! $respuesta->successful() || ! is_array($respuesta->json())) {
            Log::warning('SIREB respondió con error', ['ruta' => $ruta, 'status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::noResponde();
        }

        return $respuesta->json();
    }

    /** SIREB manda los ids en minúscula. */
    private function id(string $valor): string
    {
        return rawurlencode(mb_strtolower(trim($valor)));
    }
}
