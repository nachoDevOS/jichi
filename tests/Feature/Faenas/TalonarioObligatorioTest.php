<?php

namespace Tests\Feature\Faenas;

use App\Http\Requests\Panel\ActualizarFaenaRequest;
use App\Http\Requests\Panel\EmitirFaenaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Los renglones del talonario de la faena son obligatorios (23/10/2026).
 * Ver docs/REGLAS-NEGOCIO.md, paso 4.
 */
class TalonarioObligatorioTest extends TestCase
{
    use RefreshDatabase;

    private const RENGLONES = [
        'embarcacion' => 'Canoa San José',
        'propietario' => 'Juan Pérez',
        'comandante_barco' => 'Juan Pérez',
        'matricula_naval' => 'MN-4585',
        'nro_kardex' => '456',
        'region_desde' => 'Loma Suárez',
        'region_hasta' => 'Boca del Mamoré',
    ];

    public static function renglones(): array
    {
        return array_map(fn (string $campo) => [$campo], array_combine(array_keys(self::RENGLONES), array_keys(self::RENGLONES)));
    }

    #[DataProvider('renglones')]
    public function test_corregir_sin_un_renglon_no_valida(string $campo): void
    {
        $datos = self::RENGLONES;
        $datos[$campo] = '';

        $validador = Validator::make($datos, (new ActualizarFaenaRequest)->rules(), (new ActualizarFaenaRequest)->messages());

        $this->assertTrue($validador->fails());
        $this->assertSame([$campo], array_keys($validador->errors()->messages()));
    }

    public function test_corregir_con_todos_los_renglones_valida(): void
    {
        $validador = Validator::make(self::RENGLONES, (new ActualizarFaenaRequest)->rules());

        $this->assertFalse($validador->fails());
    }

    public function test_emitir_exige_los_siete_renglones_con_su_mensaje(): void
    {
        $validador = Validator::make([], (new EmitirFaenaRequest)->rules(), (new EmitirFaenaRequest)->messages());
        $errores = $validador->errors();

        foreach (array_keys(self::RENGLONES) as $campo) {
            $this->assertTrue($errores->has($campo), "falta exigir {$campo}");
        }
        $this->assertSame('Escriba la embarcación.', $errores->first('embarcacion'));
    }
}
