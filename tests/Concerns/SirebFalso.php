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

    /** false: el entorno no cobra por QR (hoy, Paititi): `503 PAGO_QR_NO_DISPONIBLE`. */
    protected bool $qrDisponible = true;

    /** true: el QR es de prueba y se puede simular su pago (test.sireb). */
    protected bool $qrSimulado = true;

    /** Cuántas veces se pidió pagar un QR de prueba. */
    protected int $simulacionesDePago = 0;

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
                $monto = collect($request->data()['items'] ?? [])->sum(fn ($i) => ($tarifas[$i['tarifa_id']]['monto'] ?? 0) * $i['cantidad']);
                $this->liquidaciones[$id] = ['id' => $id, 'codigo_publico' => 'PAGO-'.count($this->liquidaciones),
                    'monto' => number_format($monto, 2, '.', ''), 'estado' => 'pendiente', 'pago' => null];

                return Http::response(['data' => $this->liquidaciones[$id]], 201);
            }

            if (preg_match('#/liquidaciones/([^/]+)/qr$#', $ruta, $m)) {
                return $this->qrFalso($m[1]);
            }

            if (preg_match('#/liquidaciones/([^/]+)/qr/simular-pago$#', $ruta, $m)) {
                return $this->simularPagoFalso($m[1], (string) ($request->data()['transaccion_id'] ?? ''));
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

    /** `POST /liquidaciones/{id}/qr` como lo contesta SIREB: el mismo QR mientras siga vigente. */
    private function qrFalso(string $id)
    {
        if (! $this->qrDisponible) {
            return Http::response(['codigo' => 'PAGO_QR_NO_DISPONIBLE', 'mensaje' => 'El pago por QR todavía no está disponible.'], 503);
        }

        $liquidacion = $this->liquidaciones[$id] ?? null;

        if ($liquidacion === null) {
            return Http::response(['codigo' => 'LIQUIDACION_NO_ENCONTRADO'], 404);
        }

        if ($liquidacion['estado'] !== 'pendiente') {
            return Http::response(['codigo' => 'LIQUIDACION_NO_PAGABLE'], 422);
        }

        if ($liquidacion['pago'] !== null) {
            return Http::response(['codigo' => 'PAGO_YA_EXISTE'], 422);
        }

        $nuevo = ! isset($liquidacion['qr']);
        $this->liquidaciones[$id]['qr'] ??= [
            'transaccion_id' => 'SIM-'.Str::upper(Str::random(12)),
            'codigo_publico' => $liquidacion['codigo_publico'],
            'monto' => $liquidacion['monto'],
            'imagen' => base64_encode('png-de-prueba'),
            'contenido' => $this->qrSimulado ? 'SIMULACION-NO-PAGAR' : 'qr-real',
            'vence_en' => now()->addMinutes(30)->toIso8601String(),
            'simulado' => $this->qrSimulado,
        ];

        return Http::response(['data' => $this->liquidaciones[$id]['qr']], $nuevo ? 201 : 200);
    }

    /** `POST /liquidaciones/{id}/qr/simular-pago`: deja la liquidación pagada con un pago QR de prueba. */
    private function simularPagoFalso(string $id, string $transaccionId)
    {
        $this->simulacionesDePago++;

        if (! $this->qrSimulado) {
            return Http::response(['codigo' => 'QR_SIMULADO_DESHABILITADO'], 404);
        }

        $liquidacion = $this->liquidaciones[$id] ?? null;

        if (($liquidacion['qr']['transaccion_id'] ?? null) !== $transaccionId) {
            return Http::response(['codigo' => 'QR_INVALIDO'], 422);
        }

        if ($liquidacion['estado'] !== 'pendiente') {
            return Http::response(['codigo' => 'LIQUIDACION_NO_PAGABLE'], 422);
        }

        $this->liquidaciones[$id]['estado'] = 'pagada';
        $this->liquidaciones[$id]['pago'] = [
            'tipo_pago' => 'electronico', 'monto_pagado' => $liquidacion['monto'], 'estado' => 'confirmado',
            'fecha_pago' => now()->toIso8601String(), 'numero_boleta' => null, 'entidad_bancaria' => null,
            'fecha_deposito' => null, 'fecha_validacion' => now()->toIso8601String(),
            'medio' => 'QR', 'transaccion_id' => $transaccionId, 'simulado' => true,
        ];

        return Http::response(['data' => $this->liquidaciones[$id]], 201);
    }
}
