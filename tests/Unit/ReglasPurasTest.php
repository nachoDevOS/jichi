<?php

namespace Tests\Unit;

use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Services\CodigoService;
use App\Services\CorrelativoService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Reglas que no tocan la base: el alfabeto del código de verificación, cómo se
 * limpia lo tipeado, el relleno de los correlativos y el plazo de la faena.
 */
class ReglasPurasTest extends TestCase
{
    #[Test]
    public function el_alfabeto_del_codigo_no_tiene_caracteres_confundibles(): void
    {
        foreach (str_split('ILOS015') as $confundible) {
            $this->assertStringNotContainsString($confundible, CodigoService::ALFABETO, "«{$confundible}» se confunde al dictarlo");
        }

        $this->assertSame(29, strlen(CodigoService::ALFABETO));
        $this->assertSame(16, CodigoService::LARGO);
        $this->assertSame(strlen(CodigoService::ALFABETO), count(array_unique(str_split(CodigoService::ALFABETO))), 'sin repetidos');
    }

    #[Test]
    public function lo_tipeado_se_limpia_antes_de_buscar(): void
    {
        $this->assertSame('EFGT96R4CJ42AHYJ', Carnet::normalizarCodigo('efgt-96r4 cj42.ahyj'));
        $this->assertSame('ABCD', Carnet::normalizarCodigo('  a b-c_d  '));
        $this->assertSame('', Carnet::normalizarCodigo('---'));
    }

    #[Test]
    public function los_correlativos_del_talonario_van_con_seis_digitos(): void
    {
        $this->assertSame('000001', CorrelativoService::rellenar(1));
        $this->assertSame('002190', CorrelativoService::rellenar('2190'));
        $this->assertSame('1234567', CorrelativoService::rellenar(1234567), 'no corta lo que ya es más largo');
    }

    #[Test]
    public function la_faena_desembarca_treinta_dias_despues_de_la_salida(): void
    {
        $this->assertSame('2026-10-27', PermisoFaena::desembarqueDesde('2026-09-27 15:30')->toDateString());
        $this->assertSame('00:00:00', PermisoFaena::desembarqueDesde('2026-09-27 15:30')->format('H:i:s'), 'es un día, no un instante');
        $this->assertSame('2027-01-29', PermisoFaena::desembarqueDesde('2026-12-30')->toDateString(), 'cruza de año');
    }
}
