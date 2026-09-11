<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\TipoTramite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AreaSeeder extends Seeder
{
    /**
     * Áreas departamentales con sus tipos de trámite y tarifas iniciales.
     * Los montos están en bolivianos y son referenciales: la unidad de
     * recaudación los ajusta desde Configuración cuando cambia la ordenanza.
     */
    public function run(): void
    {
        foreach ($this->areas() as $orden => $datos) {
            $tipos = $datos['tipos'];
            unset($datos['tipos']);

            $area = Area::updateOrCreate(
                ['codigo' => $datos['codigo']],
                [...$datos, 'slug' => Str::slug($datos['nombre']), 'orden' => $orden, 'activo' => true],
            );

            foreach ($tipos as $tipo) {
                TipoTramite::updateOrCreate(
                    ['area_id' => $area->id, 'codigo' => $tipo['codigo']],
                    [...$tipo, 'slug' => Str::slug($tipo['nombre']), 'activo' => true],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function areas(): array
    {
        return [
            [
                'nombre' => 'Pesca Artesanal',
                'codigo' => 'PESCA',
                'icono' => '🐟',
                'color' => '#1e3a5f',
                'descripcion' => 'Habilitación de pescadores artesanales del río Mamoré e Ibare.',
                'tipos' => [
                    /*
                     * LA CÉDULA DE PESCADOR ES LA LLAVE DEL SISTEMA.
                     *
                     * Sin ella vigente no se puede emitir ni un permiso por
                     * faena ni una guía de transporte.
                     *
                     * Vence al CERRAR LA GESTIÓN, no a los N días: sacada en
                     * enero dura casi doce meses, sacada en diciembre dura
                     * unas semanas, y las dos vencen el 31 de diciembre.
                     * Figuraba como 730 días —dos años— y estaba mal.
                     */
                    [
                        'nombre' => 'Cédula de Pescador',
                        'codigo' => 'CAP',
                        'categoria_documento' => 'credencial',
                        'requiere_foto' => true,
                        'vigencia_tipo' => 'gestion',
                        'vigencia_dias' => null,
                        'requiere_aprobacion' => true,
                        // Los tres primeros se adjuntan como archivo y son
                        // bloqueantes: sin ellos el formulario no deja
                        // registrar. Ver TramiteController::store().
                        'requisitos' => [
                            'Certificación emitida por su asociación',
                            'Fotocopia de carnet simple',
                            'Comprobante de pago de la cédula',
                            'Fotografía tipo carnet, fondo claro',
                        ],
                        'texto_plantilla' => 'Credencial que acredita a {solicitante} como pescador artesanal registrado del Departamento del Beni, válida hasta {fecha_vencimiento}.',
                        // La reposición por pérdida costaba Bs 50 y era una
                        // segunda tarifa del mismo servicio. Al quedar un
                        // precio por servicio, se cobra la emisión: si la
                        // reposición vuelve, entra como un tipo aparte.
                        'monto' => 80.00,
                        'ordenanza' => 'L.D. 023/2024',
                    ],

                    /*
                     * PERMISO POR FAENA — el servicio del talonario del SEDAG.
                     *
                     * Vale un mes desde que se emite Y ampara UNA sola salida
                     * de pesca: si se usa a los tres días se terminó igual.
                     * Exige cédula de pescador vigente.
                     */
                    [
                        'nombre' => 'Permiso por Faena',
                        'codigo' => 'PPF',
                        'categoria_documento' => 'permiso',
                        'requiere_foto' => false,
                        'vigencia_tipo' => 'dias',
                        'vigencia_dias' => 30,
                        'uso_unico' => true,
                        'requiere_credencial' => true,
                        'requiere_aprobacion' => true,
                        'requisitos' => [
                            'Cédula de Pescador vigente',
                            'Matrícula naval vigente de la embarcación',
                            'Comprobante de pago de la tasa',
                        ],
                        'texto_plantilla' => 'El Área de Fiscalización y Control de la Actividad Pesquera autoriza a {solicitante} a realizar una faena de pesca. La presente autorización tiene validez para una sola faena. Sin este documento no se otorgará el derecho de Zarpe por la Capitanía del Puerto.',
                        'monto' => 15.00,
                        'ordenanza' => 'L.D. 023/2024',
                    ],
                ],
            ],
            [
                'nombre' => 'Comercialización de Pescado',
                'codigo' => 'COMER',
                'icono' => '🏪',
                'color' => '#c9a84c',
                'descripcion' => 'Venta mayorista y minorista de productos hidrobiológicos.',
                'tipos' => [
                    [
                        'nombre' => 'Permiso de Comercialización',
                        'codigo' => 'PCO',
                        'categoria_documento' => 'permiso',
                        'requiere_foto' => false,
                        'vigencia_dias' => 365,
                        'requiere_aprobacion' => true,
                        'requisitos' => [
                            'Cédula de Identidad o NIT',
                            'Croquis de ubicación del punto de venta',
                            'Certificado sanitario vigente',
                        ],
                        'texto_plantilla' => 'Se autoriza a {solicitante}, con {documento}, la comercialización de productos hidrobiológicos en jurisdicción del Departamento del Beni, válido hasta {fecha_vencimiento}.',
                        // El permiso mensual costaba Bs 40 y era la segunda
                        // tarifa de este servicio. Queda el anual, que es el
                        // que se emite en ventanilla.
                        'monto' => 350.00,
                        'ordenanza' => 'L.D. 023/2024',
                    ],
                    /*
                     * GUÍA ÚNICA DE TRANSPORTE.
                     *
                     * Mismo flujo que el permiso por faena: exige cédula de
                     * pescador vigente. Lo que todavía NO está definido es su
                     * vigencia —queda pendiente de confirmar con el SEDAG—, así
                     * que por ahora se carga como un solo traslado con 30 días
                     * de tope, igual que la faena. Ver docs/PENDIENTES.md.
                     */
                    [
                        'nombre' => 'Guía Única de Transporte de Productos Ictícolas',
                        'codigo' => 'GUT',
                        'categoria_documento' => 'permiso',
                        'requiere_foto' => false,
                        'vigencia_tipo' => 'dias',
                        'vigencia_dias' => 30,
                        'uso_unico' => true,
                        'requiere_credencial' => true,
                        'requiere_aprobacion' => true,
                        'requisitos' => [
                            'Cédula de Pescador vigente',
                            'Datos del medio de transporte (placa o matrícula)',
                            'Detalle de especies, kilos y precio en lugar de origen',
                        ],
                        'texto_plantilla' => 'Se ampara el traslado de los productos hidrobiológicos declarados por {solicitante} desde el lugar de origen hasta su destino. Los firmantes dan fe de los datos de su competencia y se responsabilizan de los mismos.',
                        'monto' => 30.00,
                        'ordenanza' => 'L.D. 023/2024',
                    ],
                ],
            ],
        ];
    }
}
