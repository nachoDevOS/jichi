<?php

namespace Tests\Feature\Carnets;

use App\Enums\EstadoAsociacion;
use App\Http\Requests\Panel\EditarCarnetRequest;
use App\Http\Requests\Panel\EmitirCarnetRequest;
use App\Models\Asociacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** El carnet exige una asociación, y solo una activa. */
class AsociacionObligatoriaTest extends TestCase
{
    use RefreshDatabase;

    public static function requests(): array
    {
        return ['emitir' => [EmitirCarnetRequest::class], 'corregir' => [EditarCarnetRequest::class]];
    }

    #[DataProvider('requests')]
    public function test_sin_asociacion_no_valida(string $request): void
    {
        $this->assertSame('Elija la asociación que la certifica.', $this->error($request, null));
    }

    #[DataProvider('requests')]
    public function test_solo_acepta_una_asociacion_activa(string $request): void
    {
        $activa = Asociacion::create(['nombre' => 'Activa', 'estado' => EstadoAsociacion::Activo->value]);
        $inactiva = Asociacion::create(['nombre' => 'Inactiva', 'estado' => EstadoAsociacion::Inactivo->value]);

        $this->assertNull($this->error($request, $activa->id));
        $this->assertSame('Esa asociación no existe o está inactiva.', $this->error($request, $inactiva->id));
    }

    private function error(string $request, ?int $asociacion): ?string
    {
        $reglas = (new $request)->rules();
        $validador = Validator::make(['asociacion_id' => $asociacion], ['asociacion_id' => $reglas['asociacion_id']], (new $request)->messages());

        return $validador->errors()->first('asociacion_id') ?: null;
    }
}
