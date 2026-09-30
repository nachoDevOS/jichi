<?php

namespace App\Sireb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del gateway de Recaudaciones. Hoy solo baja el catálogo del SEDAG tal
 * cual llega; conectarlo con la escala y los demás catálogos es la etapa
 * siguiente. Ver docs/modulos/SIREB.md.
 */
class SirebService
{
    private const CACHE_TOKEN = 'sireb.token';

    /**
     * Una página del catálogo TAL COMO LA MANDA SIREB, sin resumir ni cachear.
     * SIREB filtra por la dependencia del token: solo llegan servicios del SEDAG.
     *
     * @return array<string, mixed>
     *
     * @throws SirebException
     */
    public function catalogoCrudo(int $pagina = 1): array
    {
        return $this->get('/api/v1/catalogo/servicios', ['pagina' => $pagina, 'por_pagina' => 100]);
    }

    /**
     * Token de máquina (client_credentials) emitido por Ibare. Sin refresh_token:
     * vencido, se pide otro con las mismas credenciales.
     *
     * @throws SirebException
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
     * @param  array<string, mixed>  $consulta
     * @return array<string, mixed>
     *
     * @throws SirebException
     */
    private function get(string $ruta, array $consulta): array
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

        if (! $respuesta->successful() || ! is_array($respuesta->json())) {
            Log::warning('SIREB respondió con error', ['ruta' => $ruta, 'status' => $respuesta->status(), 'cuerpo' => $respuesta->body()]);

            throw SirebException::noResponde();
        }

        return $respuesta->json();
    }

    /** @param  array<string, mixed>  $consulta */
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
}
