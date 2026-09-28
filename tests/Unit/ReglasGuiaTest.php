<?php

namespace Tests\Unit;

use App\Enums\CondicionProducto;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Las reglas puras del módulo del comercializador: qué permite cada estado de
 * la guía, cómo se calcula el arancel y el importe. Sin base de datos.
 */
class ReglasGuiaTest extends TestCase
{
    /**
     * La tabla del circuito, estado por estado. Si alguien agrega un estado o
     * cambia un permiso, esta prueba lo obliga a decidir de qué lado cae.
     *
     * @return array<string, array{EstadoGuia, array<string, bool>}>
     */
    public static function circuito(): array
    {
        $nada = [
            'permiteEdicion' => false, 'permiteEliminacion' => false, 'admitePagos' => false,
            'permiteEnvio' => false, 'permiteRevision' => false, 'permiteCierre' => false,
            'permiteAnulacion' => false, 'habilita' => false,
        ];

        return [
            'pendiente' => [EstadoGuia::Pendiente, [...$nada, 'permiteEdicion' => true, 'permiteEliminacion' => true, 'admitePagos' => true, 'permiteEnvio' => true]],
            'en revisión' => [EstadoGuia::EnRevision, [...$nada, 'permiteRevision' => true]],
            'aprobada' => [EstadoGuia::Aprobada, [...$nada, 'permiteCierre' => true, 'permiteAnulacion' => true, 'habilita' => true]],
            'cerrada' => [EstadoGuia::Cerrada, $nada],
            'anulada' => [EstadoGuia::Anulada, $nada],
        ];
    }

    /** @param  array<string, bool>  $esperado */
    #[Test]
    #[DataProvider('circuito')]
    public function cada_estado_permite_exactamente_lo_suyo(EstadoGuia $estado, array $esperado): void
    {
        foreach ($esperado as $metodo => $valor) {
            $this->assertSame($valor, $estado->{$metodo}(), "{$estado->value}::{$metodo}()");
        }
    }

    #[Test]
    public function la_tabla_cubre_todos_los_estados(): void
    {
        $this->assertCount(count(EstadoGuia::cases()), self::circuito());
    }

    #[Test]
    public function la_piscicultura_paga_la_mitad_del_arancel(): void
    {
        $normal = new GuiaMovimiento(['es_piscicultura' => false]);
        $criadero = new GuiaMovimiento(['es_piscicultura' => true]);

        $this->assertSame(50.0, $normal->arancelCalculado(50));
        $this->assertSame(25.0, $criadero->arancelCalculado(50));
        $this->assertSame(16.67, $criadero->arancelCalculado(33.33), 'redondea a dos decimales');
    }

    #[Test]
    public function el_importe_es_kilos_por_precio_redondeado(): void
    {
        $this->assertSame(2500.0, GuiaDetalle::importeDe(200, 12.5));
        $this->assertSame(0.0, GuiaDetalle::importeDe(150, 0));
        $this->assertSame(3.33, GuiaDetalle::importeDe(1.11, 3));
    }

    #[Test]
    public function la_guia_vale_cinco_dias_contados_con_hora(): void
    {
        $vence = GuiaMovimiento::vencimientoDesde('2026-09-28 18:00:00');

        $this->assertSame('2026-10-03 18:00:00', $vence->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function las_condiciones_se_agrupan_como_en_el_papel(): void
    {
        $grupos = array_column(CondicionProducto::opciones(), 'grupo');

        // Primero los dos grupos, contiguos y en el orden del cuadro D; después las sueltas.
        $this->assertSame(['Fresco o Refrigerado', 'Fresco o Refrigerado', 'Congelado', 'Congelado', 'Congelado'], array_slice($grupos, 0, 5));
        $this->assertSame(['', '', '', '', ''], array_slice($grupos, 5));

        // Adentro del grupo va el nombre corto; en las sueltas, el completo.
        $this->assertSame('Fileteado', CondicionProducto::CongeladoFileteado->subetiqueta());
        $this->assertSame('Eviscerado', CondicionProducto::FrescoEviscerado->subetiqueta());
        $this->assertSame('Sal preso', CondicionProducto::SalPreso->subetiqueta());
    }

    /**
     * El mensaje de «carnet no vigente» tiene que existir para TODOS los
     * estados: faltaban pendiente y en revisión y ventanilla recibía un error
     * 500 en vez de la explicación (27/09/2026).
     */
    #[Test]
    public function hay_mensaje_de_carnet_no_vigente_para_cada_estado(): void
    {
        foreach (EstadoCarnet::cases() as $estado) {
            $mensaje = PermisoOperativoException::carnetNoVigente($estado)->getMessage();

            $this->assertStringStartsWith('El carnet no está vigente.', $mensaje, $estado->value);
        }
    }
}
