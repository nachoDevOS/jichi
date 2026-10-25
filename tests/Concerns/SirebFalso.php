<?php

namespace Tests\Concerns;

use App\Enums\ConceptoArancel;
use App\Models\ArancelSireb;
use App\Models\ArancelTarifa;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SIREB e Ibare en memoria: catálogo de la guía y de la faena, liquidaciones que
 * se registran, se consultan y se anulan. Nada sale a la red.
 */
trait SirebFalso
{
    protected const SERVICIO_GUIA = '01a0f4e4-120d-7095-bea1-17b44b338d62';

    protected const SERVICIO_FAENA = '01a0f40b-baf6-7191-adaa-d72a61f9caf6';

    protected const TARIFA_SURUBI = '01a0f4e4-0000-7000-8000-000000000001';

    protected const TARIFA_FAENA = '01a0f4b2-28a9-70c8-9912-a1fc6109d441';

    protected const SERVICIO_CARNET = '01a0f40b-0000-7000-8000-00000000ca01';

    protected const TARIFA_CARNET_PESCADOR = '01a0f40b-0000-7000-8000-00000000ca02';

    /** @var array<string, array<string, mixed>> Las liquidaciones de SIREB, por id. */
    protected array $liquidaciones = [];

    protected function simularSireb(): void
    {
        ArancelSireb::query()->create(['nombre' => 'Guía de transporte', 'concepto' => ConceptoArancel::Guia->value, 'servicio_sireb' => self::SERVICIO_GUIA]);
        ArancelSireb::query()->create(['nombre' => 'Permiso de faena', 'concepto' => ConceptoArancel::Faena->value, 'servicio_sireb' => self::SERVICIO_FAENA]);
        $carnet = ArancelSireb::query()->create(['nombre' => 'Carnet', 'concepto' => ConceptoArancel::Carnet->value, 'servicio_sireb' => self::SERVICIO_CARNET]);
        ArancelTarifa::query()->create(['arancel_id' => $carnet->id, 'etiqueta' => 'Carnet de pescador', 'clave' => 'pescador', 'sireb_tarifa_id' => self::TARIFA_CARNET_PESCADOR]);

        $tarifas = [
            self::TARIFA_SURUBI => ['id' => self::TARIFA_SURUBI, 'etiqueta' => 'Surubí', 'monto' => 0.5, 'estado' => 'activo'],
            self::TARIFA_FAENA => ['id' => self::TARIFA_FAENA, 'etiqueta' => 'Permiso de faena', 'monto' => 15, 'estado' => 'activo'],
            self::TARIFA_CARNET_PESCADOR => ['id' => self::TARIFA_CARNET_PESCADOR, 'etiqueta' => 'Carnet de pescador', 'monto' => 15, 'estado' => 'activo'],
        ];
        $servicios = [
            ['id' => self::SERVICIO_GUIA, 'codigo' => 'g', 'nombre' => 'Productos Guia', 'estado' => 'activo', 'tarifas' => [$tarifas[self::TARIFA_SURUBI]]],
            ['id' => self::SERVICIO_FAENA, 'codigo' => 'a', 'nombre' => 'Aranceles', 'estado' => 'activo', 'tarifas' => [$tarifas[self::TARIFA_FAENA]]],
            ['id' => self::SERVICIO_CARNET, 'codigo' => 'c', 'nombre' => 'Carnets', 'estado' => 'activo', 'tarifas' => [$tarifas[self::TARIFA_CARNET_PESCADOR]]],
        ];

        Http::fake(function (Request $request) use ($tarifas, $servicios) {
            $url = $request->url();
            $ruta = parse_url($url, PHP_URL_PATH);

            if (str_ends_with($ruta, '/oauth/token')) {
                return Http::response(['access_token' => 'token-de-prueba', 'expires_in' => 600]);
            }

            if (preg_match('#/catalogo/servicios/[^/]+/tarifas/([^/]+)$#', $ruta, $m)) {
                return isset($tarifas[$m[1]]) ? Http::response(['data' => $tarifas[$m[1]]]) : Http::response([], 404);
            }

            if (str_ends_with($ruta, '/catalogo/servicios')) {
                return Http::response(['data' => $servicios, 'meta' => ['total_paginas' => 1]]);
            }

            if ($request->method() === 'POST' && str_ends_with($ruta, '/liquidaciones')) {
                $id = (string) Str::uuid();
                $this->liquidaciones[$id] = ['id' => $id, 'codigo_publico' => 'PAGO-'.count($this->liquidaciones), 'estado' => 'pendiente', 'pago' => null];

                return Http::response(['data' => $this->liquidaciones[$id]], 201);
            }

            if (preg_match('#/liquidaciones/([^/]+)/anular$#', $ruta, $m)) {
                $this->liquidaciones[$m[1]]['estado'] = 'anulada';

                return Http::response(['data' => $this->liquidaciones[$m[1]]]);
            }

            if (preg_match('#/liquidaciones/([^/]+)$#', $ruta, $m)) {
                return isset($this->liquidaciones[$m[1]]) ? Http::response(['data' => $this->liquidaciones[$m[1]]]) : Http::response([], 404);
            }

            return Http::response(['error' => "Ruta no simulada: {$request->method()} {$url}"], 500);
        });
    }
}
