<?php

namespace App\Enums;

/**
 * Los roles del sistema y qué puede hacer cada uno.
 *
 * ----------------------------------------------------------------------------
 *  POR AHORA HAY UN SOLO ROL, Y ES A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * El módulo de carnets arranca con `administrador` haciendo todo. Supervisor,
 * operador de ventanilla y solo-lectura se agregarán cuando la unidad defina
 * quién firma qué; mientras tanto, inventar roles que nadie usa solo obliga a
 * mantenerlos.
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
            self::Administrador => 'Control total del sistema: beneficiarios, carnets, rubros, trámites, faenas, guías, pagos y configuración.',
        };
    }

    /**
     * Permisos asignados al rol durante el seeding.
     *
     * Los bloques están separados por área aunque hoy el único rol se los lleve
     * todos. Es lo que permite que agregar un rol mañana sea escribir una línea
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
            'carnets.ver',
            'rubros.ver',
            'tramites.ver',
            'faenas.ver',
            'guias.ver',
            'pagos.ver',
            'reportes.ver',
        ];

        $operacion = [
            'beneficiarios.crear',
            'beneficiarios.editar',
            'tramites.crear',
            'tramites.editar',
            /*
             * EMITIR FAENAS Y GUÍAS ES DE VENTANILLA, no de supervisión.
             *
             * Son papeles del talonario que se llenan en el mostrador y se
             * entregan en el acto: no hay nada que firmar después. Pedir un
             * permiso de supervisión los frenaría todos los días por algo que
             * ya está autorizado —el carnet vigente es la autorización—.
             *
             * ANULARLOS sí es de supervisión: ver el bloque de abajo.
             */
            'faenas.crear',
            'guias.crear',
            'pagos.registrar',
            // Imprimir el carnet y entregarlo en mano son dos hechos distintos y
            // quedan registrados con su propia fecha, por eso son dos permisos.
            'carnets.generar',
            'carnets.entregar',
            // El RECIBO OFICIAL que se le entrega al pescador en el mostrador.
            // Es de ventanilla y no de supervisión: lo imprime quien atiende, no
            // quien aprueba. Sigue el mismo criterio que `carnets.generar`.
            'recibos.imprimir',
        ];

        $supervision = [
            /*
             * NO HAY `tramites.revisar`, y se quitó a propósito.
             *
             * Existía para el paso PENDIENTE ──▶ EN REVISIÓN cuando ese paso
             * significaba «un supervisor toma el expediente para mirarlo». Hoy
             * significa lo contrario: es ventanilla la que ENVÍA el expediente
             * cuando terminó de armarlo, así que esa ruta pide `tramites.editar`
             * —el permiso de quien lo arma— y no uno de supervisión.
             *
             * Lo que sí es de supervisión es lo que viene después: aprobar y
             * rechazar. Y ahí está el punto de la separación de funciones —
             * quien arma no firma.
             */
            'tramites.aprobar',
            'tramites.rechazar',
            // Suspender y anular un carnet son medidas sancionatorias: el día
            // que exista el rol de ventanilla, no las tendrá.
            //
            // `carnets.suspender` reemplazó a `habilitaciones.suspender`: con un
            // carnet por rubro, cortar una actividad es suspender su carnet.
            /*
             * CONTROLAR LOS DEPÓSITOS es de supervisión, no de ventanilla.
             *
             * Quien carga la boleta no puede darla por buena —eso lo impide
             * además `Pago::puedeValidarlo()`— así que el permiso tiene que
             * estar en otras manos, o el control no existe.
             */
            'pagos.validar',
            'carnets.suspender',
            'carnets.anular',
            /*
             * Anular una faena o una guía quema un número del talonario para
             * siempre —no se desanula— y deja un hueco que hay que poder
             * explicar. Por eso no lo tiene quien emite.
             */
            'faenas.anular',
            'guias.anular',
            'reportes.exportar',
            'auditoria.ver',
        ];

        $administracion = [
            'beneficiarios.eliminar',
            'tramites.eliminar',
            /*
             * QUITAR UN DEPÓSITO NO ES CORREGIRLO, y por eso no va con
             * `pagos.registrar`.
             *
             * Corregir deja la fila y su historial: se ve qué decía antes y
             * quién lo cambió. Quitar la hace desaparecer —del expediente y de
             * la suma cobrada— y lo único que queda es la línea de
             * `auditorias`. Es el mismo criterio que separa `tramites.editar`
             * de `tramites.eliminar`: quien atiende el mostrador corrige lo que
             * tipeó; hacer desaparecer dinero declarado es otra cosa.
             */
            'pagos.eliminar',
            'usuarios.gestionar',
            'roles.gestionar',
            // El catálogo de rubros y sus tarifas cambia por ordenanza.
            'rubros.gestionar',
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
