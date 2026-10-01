<?php

namespace App\Sireb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del gateway de Recaudaciones: servicios del SEDAG y sus tarifas, con
 * el token de Ibare. Ver docs/modulos/SIREB.md.
 */
class SirebService
{
    private const CACHE_TOKEN = 'sireb.token';

    private const CACHE_SERVICIOS = 'sireb.servicios';

    private const RUTA_SERVICIOS = '/api/v1/catalogo/servicios';

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

    /** La respuesta de SIREB, o null si el recurso no existe (404). */
    private function get(string $ruta, array $consulta = []): ?array
    {
        $respuesta = $this->pedir($ruta, $consulta, $this->token());

        // El token pudo vencer entre la caché y la llamada: se pide otro una vez.
        if ($respuesta->status() === 401) {
            $respuesta = $this->pedir($ruta, $consulta, $this->token(refrescar: true));
        }

        if (in_array($respuesta->status(), [401, 403], true)) {
            Log::warning('SIREB rechazó la consulta', ['ruta' => $ruta, 'status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::rechazado($respuesta->status());
        }

        if ($respuesta->status() === 404) {
            return null;
        }

        if (! $respuesta->successful() || ! is_array($respuesta->json())) {
            Log::warning('SIREB respondió con error', ['ruta' => $ruta, 'status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::noResponde();
        }

        return $respuesta->json();
    }

    private function pedir(string $ruta, array $consulta, string $token): Response
    {
        try {
            return Http::acceptJson()
                ->withToken($token)
                ->timeout(config('jichi.sireb.timeout'))
                ->get(config('jichi.sireb.url').$ruta, $consulta);
        } catch (ConnectionException) {
            throw SirebException::noResponde();
        }
    }

    // Desde aka se tiene que implementar las nuevas funciones de SIREB, que son las que usan el token de Ibare. Por ahora solo se implementa el catálogo de servicios.

    /**
     * Todos los servicios del SEDAG con sus tarifas, juntando las páginas.
     * En caché 10 minutos: un precio recién cambiado en SIREB tarda eso en llegar.
     */
    public function servicios(bool $refrescar = false): array
    {
        if ($refrescar) {
            Cache::forget(self::CACHE_SERVICIOS);
        }

        return Cache::remember(self::CACHE_SERVICIOS, now()->addMinutes(10), function (): array {
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
        $cuerpo = $this->get(self::RUTA_SERVICIOS.'/'.rawurlencode(mb_strtolower(trim($servicioId))));

        return $cuerpo['data'] ?? null;
    }
}
