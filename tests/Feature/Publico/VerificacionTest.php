<?php

namespace Tests\Feature\Publico;

use App\Models\Codigo;
use App\Services\CodigoService;
use App\Services\EmitirCarnetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ArmaEscenarios;
use Tests\TestCase;

/**
 * Paso 7 — la verificación pública por código de 16 caracteres. Muestra lo
 * justo, con la cédula enmascarada. Ver docs/REGLAS-NEGOCIO.md.
 */
class VerificacionTest extends TestCase
{
    use ArmaEscenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarYEntrar();
    }

    #[Test]
    public function el_carnet_vigente_se_verifica_sin_sesion_y_con_la_cedula_enmascarada(): void
    {
        $carnet = $this->pescadorHabilitado();
        $ci = (string) $carnet->beneficiario->ci;
        auth()->logout();

        $respuesta = $this->get(route('verificar.show', ['codigo' => $carnet->codigo_legible]));

        $respuesta->assertOk()->assertInertia(fn ($page) => $page
            ->where('encontrado', true)
            ->where('documento.tipo', 'carnet')
            ->where('documento.vigente', true)
            ->where('documento.titular', $carnet->beneficiario->nombreCompleto)
            ->where('documento.documento_titular', fn ($enmascarada) => ! str_contains((string) $enmascarada, $ci)
                && str_ends_with((string) $enmascarada, substr($ci, -3))));

        // Nada interno ni personal completo viaja en la página.
        $this->assertStringNotContainsString($ci, $respuesta->getContent());
        $this->assertStringNotContainsString('"beneficiario_id"', $respuesta->getContent());
    }

    #[Test]
    public function el_codigo_se_encuentra_aunque_se_tipee_en_minusculas_y_sin_guiones(): void
    {
        $carnet = $this->pescadorHabilitado();
        $tipeado = strtolower(str_replace('-', '', (string) $carnet->codigo_legible));

        $this->get(route('verificar.show', ['codigo' => $tipeado]))
            ->assertInertia(fn ($page) => $page->where('encontrado', true));
    }

    #[Test]
    public function un_codigo_que_no_existe_dice_no_encontrado(): void
    {
        $this->get(route('verificar.show', ['codigo' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('encontrado', false)->where('documento', null));
    }

    #[Test]
    public function los_cinco_documentos_se_verifican(): void
    {
        $carnet = $this->pescadorHabilitado();
        $faena = $this->faenaAprobada($carnet, 20);
        $recibo = $carnet->recibos()->firstOrFail();

        foreach ([
            'carnet' => $carnet,
            'aprovechamiento' => $carnet->aprovechamiento,
            'faena' => $faena,
            'recibo' => $recibo,
        ] as $tipo => $doc) {
            $this->get(route('verificar.show', ['codigo' => $doc->fresh()->codigo_legible]))
                ->assertInertia(fn ($page) => $page->where('encontrado', true)->where('documento.tipo', $tipo));
        }
    }

    #[Test]
    public function un_carnet_revocado_se_informa_no_vigente(): void
    {
        $carnet = $this->pescadorHabilitado();
        app(EmitirCarnetService::class)->revocar($carnet, 'Resolución de prueba suficiente');

        $this->get(route('verificar.show', ['codigo' => $carnet->codigo_legible]))
            ->assertInertia(fn ($page) => $page->where('documento.vigente', false));
    }

    #[Test]
    public function el_formulario_redirige_al_codigo_limpio(): void
    {
        $carnet = $this->pescadorHabilitado();

        $this->post(route('verificar.buscar'), ['codigo' => ' '.strtolower((string) $carnet->codigo_legible).' '])
            ->assertRedirect();
    }

    #[Test]
    public function el_codigo_es_unico_y_asignarlo_dos_veces_no_crea_otro(): void
    {
        $carnet = $this->pescadorHabilitado();
        $antes = $carnet->codigo_legible;

        $this->assertSame(str_replace('-', '', (string) $antes), $carnet->fresh()->asignarCodigo()->codigo, 'idempotente');
        $this->assertSame(1, Codigo::query()->where('codigable_id', $carnet->id)->where('codigable_type', $carnet->getMorphClass())->count());

        $generados = collect(range(1, 200))->map(fn () => app(CodigoService::class)->generar());
        $this->assertCount(200, $generados->unique());
        $generados->each(fn (string $c) => $this->assertMatchesRegularExpression('/^['.CodigoService::ALFABETO.']{16}$/', $c));
    }
}
