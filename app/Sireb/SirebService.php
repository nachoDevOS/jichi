<?php

namespace App\Sireb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del gateway de Recaudaciones: el catálogo del SEDAG con sus precios.
 * SIREB filtra por la dependencia del token, así que solo llegan servicios del
 * SEDAG. Ver docs/modulos/SIREB.md.
 */
class SirebService
{
    private const CACHE_TOKEN = 'sireb.token';

    private const CACHE_CATALOGO = 'sireb.catalogo';

    /**
     * El catálogo del SEDAG, indexado por código de servicio.
     *
     * `monto` y `tarifa_id` quedan en null si el servicio no tiene exactamente
     * una tarifa general (sin sucursal ni categoría); `tarifas` dice cuántas hay.
     *
     * @return array<string, array{codigo: string, nombre: string, monto: float|null, tarifa_id: string|null, tarifas: int}>
     *
     * @throws SirebException
     */
    public function catalogo(bool $refrescar = false): array
    {
        if ($refrescar) {
            Cache::forget(self::CACHE_CATALOGO);
        }

        return Cache::remember(
            self::CACHE_CATALOGO,
            now()->addMinutes(config('jichi.sireb.cache_minutos')),
            fn (): array => $this->descargarCatalogo(),
        );
    }

    /**
     * Para pantallas que solo MUESTRAN precios: sin SIREB, la pantalla abre igual.
     *
     * @return array<string, array{codigo: string, nombre: string, monto: float|null, tarifa_id: string|null, tarifas: int}>|null
     */
    public function catalogoSiResponde(): ?array
    {
        try {
            return $this->catalogo();
        } catch (SirebException) {
            return null;
        }
    }

    /**
     * El precio vigente de un servicio, para congelarlo en el documento.
     *
     * @return array{codigo: string, nombre: string, monto: float, tarifa_id: string}
     *
     * @throws SirebException
     */
    public function precioDe(string $codigo): array
    {
        $servicio = $this->catalogo()[$codigo] ?? null;

        // Un código que no está puede ser un alta reciente: se descarga de nuevo una vez.
        if ($servicio === null) {
            $servicio = $this->catalogo(refrescar: true)[$codigo] ?? null;
        }

        if ($servicio === null) {
            throw SirebException::servicioInexistente($codigo);
        }

        if ($servicio['monto'] === null || $servicio['tarifa_id'] === null) {
            throw SirebException::sinTarifaUnica($codigo, $servicio['tarifas']);
        }

        return [
            'codigo' => $servicio['codigo'],
            'nombre' => $servicio['nombre'],
            'monto' => $servicio['monto'],
            'tarifa_id' => $servicio['tarifa_id'],
        ];
    }

    /** @return array<string, array{codigo: string, nombre: string, monto: float|null, tarifa_id: string|null, tarifas: int}> */
    private function descargarCatalogo(): array
    {
        $catalogo = [];
        $pagina = 1;

        do {
            $cuerpo = $this->get('/api/v1/catalogo/servicios', ['pagina' => $pagina, 'por_pagina' => 100]);

            foreach ($cuerpo['data'] ?? [] as $servicio) {
                $catalogo[$servicio['codigo']] = $this->resumir($servicio);
            }

            $total = (int) ($cuerpo['meta']['total_paginas'] ?? 1);
            $pagina++;
        } while ($pagina <= $total);

        return $catalogo;
    }

    /**
     * Solo cuenta la tarifa GENERAL: una con sucursal o categoría es una variante
     * que Jichi no sabría elegir.
     *
     * @param  array<string, mixed>  $servicio
     * @return array{codigo: string, nombre: string, monto: float|null, tarifa_id: string|null, tarifas: int}
     */
    private function resumir(array $servicio): array
    {
        $generales = collect($servicio['tarifas'] ?? [])
            ->filter(fn (array $t): bool => ($t['sucursal_id'] ?? null) === null && empty($t['categorias']))
            ->values();

        $unica = $generales->count() === 1 ? $generales->first() : null;

        return [
            'codigo' => (string) $servicio['codigo'],
            'nombre' => (string) $servicio['nombre'],
            // SIREB manda el monto como texto para no perder decimales.
            'monto' => $unica ? round((float) $unica['monto'], 2) : null,
            'tarifa_id' => $unica['id'] ?? null,
            'tarifas' => $generales->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $consulta
     * @return array<string, mixed>
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

    /** Token de máquina (client_credentials) emitido por Ibare. */
    private function token(bool $refrescar = false): string
    {
        if (! $refrescar && is_string($token = Cache::get(self::CACHE_TOKEN))) {
            return $token;
        }

        try {
            $respuesta = Http::asForm()->acceptJson()
                ->timeout(config('jichi.ibare.timeout'))
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
}
