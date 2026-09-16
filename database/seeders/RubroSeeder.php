<?php

namespace Database\Seeders;

use App\Enums\EstadoRubro;
use App\Models\Rubro;
use Illuminate\Database\Seeder;

/**
 * ============================================================================
 *  EL CATÁLOGO DE ACTIVIDADES — dos rubros, no más
 * ============================================================================
 *
 * El sector pesquero del Beni maneja dos servicios, y cada uno es un rubro:
 *
 *   PESCADOR         habilita a faenar. Es el permiso por faena de siempre.
 *   COMERCIALIZADOR  habilita a trasladar y vender el producto. Es la guía
 *                    única de transporte.
 *
 * Una misma persona puede tener los dos en el mismo carnet: pesca y además
 * lleva su producto al mercado. Por eso son rubros del carnet y no dos
 * documentos distintos —que es como se manejaba en papel, con el problema de
 * que el pescador tenía que cargar dos talonarios—.
 *
 * ----------------------------------------------------------------------------
 *  LOS MONTOS SON REFERENCIALES
 * ----------------------------------------------------------------------------
 *
 * Están en bolivianos y salen de las tarifas que se venían cobrando. La unidad
 * de recaudación los ajusta desde la pantalla de Rubros cuando cambia la
 * ordenanza, sin tocar código.
 *
 * Cambiarlos ahí NO afecta a los trámites ya registrados: el costo se copia a
 * `tramites.monto_requerido` el día que se presenta la solicitud, justamente
 * para que subir una tarifa no deje impagos de golpe expedientes que ya estaban
 * cubiertos.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ updateOrCreate Y NO create
 * ----------------------------------------------------------------------------
 *
 * Para que volver a correr el seeder sobre una base ya sembrada no falle contra
 * el índice único del nombre. Es lo que permite reejecutar `db:seed` sin tener
 * que hacer `migrate:fresh` cada vez.
 */
class RubroSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rubros() as $datos) {
            Rubro::updateOrCreate(['nombre' => $datos['nombre']], $datos);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rubros(): array
    {
        return [
            [
                'nombre' => 'Pescador',
                'descripcion' => 'Permiso por faena. Habilita la captura de productos hidrobiológicos '
                    .'en el río Mamoré, Ibare y afluentes, con artes de pesca artesanal.',
                'costo' => 80.00,
                'estado' => EstadoRubro::Activo,
            ],
            [
                'nombre' => 'Comercializador',
                'descripcion' => 'Guía única de transporte. Habilita el traslado y la venta de '
                    .'pescado fresco o congelado dentro del departamento del Beni.',
                'costo' => 120.00,
                'estado' => EstadoRubro::Activo,
            ],
        ];
    }
}
