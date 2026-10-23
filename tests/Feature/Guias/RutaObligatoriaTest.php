<?php

namespace Tests\Feature\Guias;

use App\Enums\MedioTransporte;
use App\Enums\TipoTransporte;
use App\Http\Requests\Panel\ActualizarGuiaRequest;
use App\Http\Requests\Panel\EmitirGuiaRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** La ruta y el transporte de la guía son obligatorios (23/10/2026), al registrar y al corregir. */
class RutaObligatoriaTest extends TestCase
{
    private static function completa(): array
    {
        return [
            'origen' => 'Camiaco', 'origen_departamento' => 'Beni', 'origen_provincia' => 'Iténez', 'origen_distrito' => 'Mamoré',
            'destino' => 'Trinidad', 'destino_departamento' => 'Beni', 'destino_provincia' => 'Cercado', 'destino_distrito' => 'Ibare',
            'medio_transporte' => MedioTransporte::cases()[0]->value,
            'tipo_transporte' => TipoTransporte::cases()[0]->value,
            'transporte_nombre' => 'Camioneta', 'transporte_placa' => '1234-ABC', 'transporte_capacidad_kg' => 1500,
        ];
    }

    public static function campos(): array
    {
        $casos = [];
        foreach ([EmitirGuiaRequest::class, ActualizarGuiaRequest::class] as $request) {
            foreach (array_keys(self::completa()) as $campo) {
                $casos[class_basename($request).' sin '.$campo] = [$request, $campo];
            }
        }

        return $casos;
    }

    #[DataProvider('campos')]
    public function test_sin_un_dato_de_la_ruta_no_valida(string $request, string $campo): void
    {
        $datos = self::completa();
        $datos[$campo] = '';

        $this->assertSame([$campo], $this->errores($request, $datos));
    }

    public function test_completa_valida_y_la_capacidad_tiene_que_ser_mayor_que_cero(): void
    {
        $this->assertSame([], $this->errores(EmitirGuiaRequest::class, self::completa()));
        $this->assertSame(['transporte_capacidad_kg'], $this->errores(EmitirGuiaRequest::class, ['transporte_capacidad_kg' => 0] + self::completa()));
    }

    /** Solo las reglas de la ruta: el resto (carnet, detalle) tiene sus propias pruebas. */
    private function errores(string $request, array $datos): array
    {
        $reglas = array_intersect_key((new $request)->rules(), self::completa());

        return array_keys(Validator::make($datos, $reglas)->errors()->messages());
    }
}
