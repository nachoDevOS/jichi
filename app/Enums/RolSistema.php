<?php

namespace App\Enums;

/**
 * Los roles del sistema y qué puede hacer cada uno.
 *
 * ----------------------------------------------------------------------------
 *  POR AHORA HAY UN SOLO ROL, Y ES A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * El sistema arranca con `administrador` haciendo todo. Supervisor, operador de
 * ventanilla y solo-lectura se agregarán cuando la unidad defina quién firma
 * qué; mientras tanto, inventar roles que nadie usa solo obliga a mantenerlos.
 *
 * LO QUE SÍ QUEDA ARMADO es la lista de permisos, y las rutas los exigen uno por
 * uno (ver el middleware `permiso:` en routes/panel.php). Esa parte no se saca
 * aunque hoy el único rol los tenga todos: el día que aparezca el segundo rol,
 * se agrega un `case` acá con su lista y las rutas ya están protegidas. Si en
 * cambio se quitara el middleware «porque total el admin puede todo», habría que
 * volver a repartir permisos ruta por ruta, que es justamente donde se olvida
 * uno y queda un agujero.
 *
 * Este enum es la ÚNICA fuente de verdad. De acá los lee RolPermisoSeeder para
 * crearlos en la base, y contra estos nombres comprueba el middleware.
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
     * ------------------------------------------------------------------------
     *  LOS BLOQUES SIGUEN EL FLUJO DE TRABAJO, NO EL ABECEDARIO
     * ------------------------------------------------------------------------
     *
     * `lectura` es mirar. `operacion` es lo que hace ventanilla todos los días.
     * `supervision` es lo que ROMPE algo ya emitido —anular, revocar— y por eso
     * no puede estar en las mismas manos que emitir. `administracion` es el
     * catálogo y las cuentas, que se tocan una vez cada tanto.
     *
     * Están separados aunque hoy el único rol se los lleve todos. Es lo que
     * permite que agregar un rol mañana sea escribir una línea
     * —`self::Operador => [...$lectura, ...$operacion]`— en vez de volver a
     * clasificar veinte permisos sueltos.
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
             * cupo definido no se sabe qué imprimir en el plástico. Lo que NO
             * es de ventanilla es AMPLIARLO después, que está abajo.
             */
            'aprovechamientos.crear',

            /*
             * CORREGIR EL BORRADOR TAMBIÉN ES DE VENTANILLA.
             *
             * Solo corre mientras el cupo está PENDIENTE DE PAGO: es arreglar
             * una carga equivocada con el pescador todavía enfrente, no cambiar
             * una autorización entregada. En cuanto entra plata el permiso deja
             * de alcanzar, porque el estado ya no lo permite.
             */
            'aprovechamientos.editar',

            'carnets.crear',
            // Imprimir el plástico es un hecho con fecha propia
            // (`fecha_generacion` en el modelo anterior): por eso es su propio
            // permiso y no viene incluido en `carnets.crear`.
            'carnets.imprimir',

            /*
             * EMITIR FAENAS Y GUÍAS ES DE VENTANILLA, no de supervisión.
             *
             * Son papeles del talonario que se llenan en el mostrador y se
             * entregan en el acto: no hay nada que firmar después. Pedir un
             * permiso de supervisión los frenaría todos los días por algo que
             * ya está autorizado — el carnet vigente ES la autorización.
             *
             * ANULARLOS sí es de supervisión: ver el bloque de abajo.
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

            // Cobrar y entregar el comprobante numerado: el mostrador entero.
            'caja.cobrar',
            'recibos.imprimir',
        ];

        $supervision = [
            /*
             * AMPLIAR UN CUPO YA OTORGADO es distinto de otorgarlo.
             *
             * Otorgar es aplicar la escala que corresponde. Ampliar es darle a
             * alguien más kilos de los que su escala le daba, y eso es
             * justamente lo que el cupo viene a limitar.
             */
            'aprovechamientos.ampliar',

            /*
             * REVOCAR un carnet es una medida sancionatoria, y no se revierte:
             * el día que exista el rol de ventanilla, no la tendrá.
             */
            'carnets.revocar',

            /*
             * ANULAR UNA GUÍA quema un número del talonario para siempre —no se
             * desanula— y deja un hueco que hay que poder explicar. Por eso no
             * lo tiene quien emite.
             *
             * NO HAY `faenas.anular`, y no es un olvido: `EstadoFaena` no tiene
             * un estado anulado. Una faena emitida de más no se borra ni se
             * anula — se deja VENCER, y al vencer libera su volumen sola. El
             * número del talonario queda ocupado igual, que es lo que
             * corresponde: la hoja se gastó.
             */
            'guias.anular',

            /*
             * ANULAR UN COBRO no es corregirlo.
             *
             * Corregir deja la fila y su historial: se ve qué decía antes y
             * quién lo cambió. Anular hace desaparecer dinero declarado de un
             * recibo ya entregado, y lo único que queda es la línea de
             * `auditorias`. Quien atiende el mostrador corrige lo que tipeó;
             * esto es otra cosa.
             */
            'caja.anular',

            /*
             * ELIMINAR UN CUPO ES DE SUPERVISIÓN, aunque solo se pueda sobre un
             * borrador sin pagos ni faenas.
             *
             * Corregir deja la fila y su historial; borrar la hace desaparecer y
             * lo único que queda es la línea de `auditorias`. Mismo criterio que
             * `caja.anular`: quien atiende el mostrador arregla lo que tipeó,
             * hacer desaparecer un registro es otra cosa.
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
     * Sale del rol administrador porque es el que los tiene todos: escribir la
     * lista otra vez acá sería una segunda copia que puede quedar corta.
     *
     * @return array<int, string>
     */
    public static function todosLosPermisos(): array
    {
        return array_values(array_unique(self::Administrador->permisos()));
    }
}
