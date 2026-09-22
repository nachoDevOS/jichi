<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configuraciones = [
            // Institución
            ['municipio.nombre', 'Gobierno Autónomo Departamental del Beni', 'string', 'municipio', 'Nombre de la institución', true],
            ['municipio.sigla', 'GAD-BENI', 'string', 'municipio', 'Sigla institucional', true],
            ['municipio.departamento', 'Beni', 'string', 'municipio', 'Departamento', true],
            ['municipio.direccion', 'Plaza José Ballivián s/n, Trinidad - Beni', 'string', 'municipio', 'Dirección', true],
            ['municipio.telefono', '(591-3) 462-0000', 'string', 'municipio', 'Teléfono', true],
            ['municipio.email', 'contacto@beni.gob.bo', 'string', 'municipio', 'Correo institucional', true],
            ['municipio.logo_path', 'institucional/logo-gadbeni.png', 'archivo', 'municipio', 'Logo de la institución', true],
            ['municipio.escudo_path', 'institucional/escudo-beni.png', 'archivo', 'municipio', 'Escudo departamental', true],

            // Sistema
            ['sistema.nombre', 'Jichi', 'string', 'general', 'Nombre del sistema', true],
            ['sistema.descripcion', 'Sistema de gestión de carnets, rubros y trámites del sector pesquero', 'string', 'general', 'Descripción', true],
            ['sistema.logo_path', 'institucional/logo-jichi.png', 'archivo', 'general', 'Logo de Jichi', true],

            // Documentos
            ['documentos.firmante_nombre', 'Lic. Responsable de Recaudaciones', 'string', 'documentos', 'Nombre del firmante', false],
            ['documentos.firmante_cargo', 'Jefe de Unidad de Recaudaciones', 'string', 'documentos', 'Cargo del firmante', false],
            ['documentos.pie_legal', 'Carnet emitido electrónicamente. Verifique su autenticidad escaneando el código QR.', 'string', 'documentos', 'Pie de página legal', true],
            // El «Lugar y Fecha» del RECIBO OFICIAL. Sale de acá y no escrito en
            // la plantilla porque la misma unidad puede atender desde otra
            // oficina, y entonces se cambia desde el panel.
            ['documentos.lugar_emision', 'Trinidad - Beni', 'string', 'documentos', 'Lugar de emisión de recibos', false],
            ['documentos.dias_alerta_vencimiento', '30', 'number', 'documentos', 'Días de anticipación para alertar el cierre de gestión', false],

            /*
             * QUIÉN FIRMA EL DORSO DEL CARNET. No es el firmante del recibo:
             * ahí firma Recaudaciones y acá el Gobernador. El nombre va VACÍO a
             * propósito —hay que cargar el de la gestión en curso—; sin él, el
             * plástico imprime el recuadro de firma con el cargo y sin nombre.
             */
            ['carnet.firmante_nombre', '', 'string', 'documentos', 'Nombre de quien firma el dorso del carnet', false],
            ['carnet.firmante_cargo', 'GOBERNADOR DEL DEPARTAMENTO DEL BENI', 'string', 'documentos', 'Cargo de quien firma el dorso del carnet', false],

            /*
             * ¿QUIEN CARGA UN DEPÓSITO PUEDE VALIDARLO ÉL MISMO?
             */
            ['pagos.revisor_distinto', '0', 'boolean', 'general', 'Exigir que el depósito lo valide otra persona', false],

            // Moneda. Vivía en el grupo 'caja'; cuando el arqueo se sacó del
            // sistema se mudó acá, porque la moneda es de todo el sistema y no
            // de un módulo. La lee HandleInertiaRequests para escribir «Bs» en
            // los montos del panel.
            ['general.moneda', 'BOB', 'string', 'general', 'Moneda de cobro', false],
            ['general.simbolo_moneda', 'Bs', 'string', 'general', 'Símbolo de la moneda', true],
        ];

        foreach ($configuraciones as [$clave, $valor, $tipo, $grupo, $etiqueta, $publico]) {
            Configuracion::updateOrCreate(
                ['clave' => $clave],
                compact('valor', 'tipo', 'grupo', 'etiqueta', 'publico'),
            );
        }
    }
}
