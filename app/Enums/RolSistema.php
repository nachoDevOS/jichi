<?php

namespace App\Enums;

/**
 * Los roles del sistema y qué puede hacer cada uno.
 */
enum RolSistema: string
{
    case Administrador = 'administrador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Control total del sistema: beneficiarios, aprovechamientos, '.
                'carnets, faenas, guías, caja y configuración.',
        };
    }

    /**
     * Permisos asignados al rol durante el seeding.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        $lectura = [
            'dashboard.ver',
            'beneficiarios.ver',
            'aprovechamientos.ver',
            'carnets.ver',
            'faenas.ver',
            'guias.ver',
            'caja.ver',
            'catalogos.ver',
            'reportes.ver',
        ];

        $operacion = [
            'beneficiarios.crear',
            'beneficiarios.editar',

            /*
             * OTORGAR LA BOLSA MADRE ES DE VENTANILLA.
             *
             * Es el paso 2 del flujo del pescador y va antes del carnet: sin
             * cupo definido no se sabe qué imprimir en el plástico.
             */
            'aprovechamientos.crear',

            /*
             * CORREGIR EL BORRADOR TAMBIÉN ES DE VENTANILLA.
             */
            'aprovechamientos.editar',

            /*
             * ENVIAR A REVISIÓN es de ventanilla: quien cargó los depósitos
             * declara que el expediente está completo. No es aprobarlo — eso
             * está en supervisión, y son dos personas distintas a propósito.
             */
            'aprovechamientos.enviar',

            // Entregar la autorización de pesca en papel. Permiso propio, como
            // `carnets.imprimir`: es un acto distinto de consultar la ficha.
            'aprovechamientos.imprimir',

            'carnets.crear',
            // Corregir el BORRADOR es de ventanilla: es el mismo mostrador el
            // que lo está armando.
            'carnets.editar',
            // Presentar el carnet a revisión: mismo circuito que el cupo, y el
            // mismo reparto —presentar es de ventanilla, firmar no—.
            'carnets.enviar',
            // Imprimir el plástico es un hecho con fecha propia
            // (`fecha_generacion` en el modelo anterior): por eso es su propio
            // permiso y no viene incluido en `carnets.crear`.
            'carnets.imprimir',

            /*
             * EMITIR FAENAS Y GUÍAS ES DE VENTANILLA, no de supervisión.
             */
            'faenas.crear',
            'guias.crear',

            /*
             * CERRAR un permiso también es de ventanilla, y es el otro medio
             * circuito: la faena se completa cuando el pescador vuelve y
             * descarga, y la guía cuando la carga llega a destino. Son hechos
             * que se registran en el mostrador, no decisiones que alguien firme.
             *
             * Recién ahí los kilos de la faena quedan firmes contra el cupo.
             */
            'faenas.completar',
            'guias.cerrar',

            // Presentar la faena a revisión: mismo reparto que en el carnet y
            // el cupo —presentar es de ventanilla, firmar no—.
            'faenas.enviar',

            // Cobrar y entregar el comprobante numerado: el mostrador entero.
            'caja.cobrar',
            'recibos.imprimir',

            /*
             * CORREGIR UN DEPÓSITO ES DE VENTANILLA, y tiene que serlo.
             */
            'pagos.corregir',
        ];

        $supervision = [
            /*
             * REVOCAR un carnet es una medida sancionatoria, y no se revierte:
             * el día que exista el rol de ventanilla, no la tendrá.
             */
            'carnets.revocar',

            /*
             * ANULAR UNA GUÍA quema un número del talonario para siempre —no se
             * desanula— y deja un hueco que hay que poder explicar. Por eso no
             * lo tiene quien emite.
             */
            'guias.anular',

            /*
             * ANULAR UN COBRO no es corregirlo.
             */
            'caja.anular',

            /*
             * APROBAR Y RECHAZAR un aprovechamiento presentado.
             */
            'aprovechamientos.aprobar',

            /*
             * APROBAR Y RECHAZAR un carnet presentado. Va con el del cupo: es
             * la misma firma sobre el mismo expediente.
             */
            'carnets.aprobar',

            /*
             * Y LA FIRMA DE LA FAENA, que es la misma decisión sobre el mismo
             * tipo de expediente: se miran las boletas y se habilita la salida.
             */
            'faenas.aprobar',

            /*
             * ELIMINAR un carnet cargado por error. De supervisión, igual que
             * en el cupo: borrar la fila la hace desaparecer de los listados y
             * lo único que queda es la línea de `auditorias` con el motivo.
             */
            'carnets.eliminar',

            /*
             * CONTROLAR LAS BOLETAS —validar u observar— es del mismo lado que
             * aprobar, y por el mismo motivo.
             */
            'pagos.controlar',

            /*
             * ELIMINAR UN CUPO ES DE SUPERVISIÓN, aunque solo se pueda sobre un
             * borrador sin pagos ni faenas.
             */
            'aprovechamientos.eliminar',

            'reportes.exportar',
            'auditoria.ver',
        ];

        $administracion = [
            'beneficiarios.eliminar',

            /*
             * LOS TRES CATÁLOGOS VAN CON UN SOLO PERMISO —asociaciones, escala
             * de aprovechamiento y tipos de carnet— porque los tres cambian por
             * la MISMA vía: una resolución. Quien puede tocar la tarifa del
             * carnet puede tocar la de la escala; separarlos daría tres
             * permisos que en la práctica se otorgan siempre juntos.
             */
            'catalogos.gestionar',

            'usuarios.gestionar',
            'roles.gestionar',
            'configuracion.gestionar',
        ];

        return match ($this) {
            self::Administrador => [...$lectura, ...$operacion, ...$supervision, ...$administracion],
        };
    }

    /**
     * Catálogo completo de permisos del sistema.
     *
     * @return array<int, string>
     */
    public static function todosLosPermisos(): array
    {
        return array_values(array_unique(self::Administrador->permisos()));
    }
}
