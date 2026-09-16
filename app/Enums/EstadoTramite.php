<?php

namespace App\Enums;

/**
 * ============================================================================
 *  EL CICLO DE VIDA DE UN TRÁMITE
 * ============================================================================
 *
 *     PENDIENTE ──[enviar]──▶ EN REVISIÓN ──┬──▶ APROBADO
 *     (borrador)                              └──▶ RECHAZADO
 *
 * PENDIENTE es un BORRADOR. Se arma, se corrige y se borra si sobra; no se
 * rechaza, porque todavía nadie lo presentó. EN REVISIÓN ya está presentado: no
 * se toca más, solo se resuelve.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ SIGNIFICA CADA UNO
 * ----------------------------------------------------------------------------
 *
 *   PENDIENTE    EL BORRADOR. Ventanilla arma el expediente: carga los
 *                papeles, registra los depósitos, corrige lo que salió
 *                ilegible, y lo borra si se cargó por error. Todavía no lo
 *                presentó a nadie, así que tampoco hay nada que rechazar.
 *
 *   EN REVISIÓN  PRESENTADO. Ventanilla lo envió y desde acá el expediente ya
 *                no se arma: no se edita ni se elimina, solo se aprueba o se
 *                rechaza. Es el único estado desde el que se puede aprobar.
 *
 *   APROBADO     Se validó todo. Acá —y solo acá— nace la fila en
 *                el carnet, y el beneficiario queda oficialmente
 *                habilitado.
 *
 *   RECHAZADO    Papeles ilegibles, pago que no corresponde, falta un
 *                requisito. Lleva SIEMPRE un motivo escrito.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SON CUATRO Y NO SEIS
 * ----------------------------------------------------------------------------
 *
 * La tentación es agregar «generado» y «entregado», porque el carnet se imprime
 * y después se entrega en ventanilla. Esos dos NO son estados del trámite: son
 * hechos con fecha, y viven en `tramites.fecha_generacion` y
 * `tramites.fecha_entrega`.
 *
 * La diferencia importa. Un estado obliga a mantener sincronizadas dos cosas
 * que pueden discrepar —un trámite «entregado» sin fecha de entrega, o al
 * revés—, y eso es un error que la base no puede impedir. Una fecha en NULL
 * dice «todavía no pasó» sin posibilidad de contradicción.
 *
 * ----------------------------------------------------------------------------
 *  ESTE ENUM ES LA ÚNICA FUENTE DE VERDAD DE LAS TRANSICIONES
 * ----------------------------------------------------------------------------
 *
 * Qué salto vale desde dónde se decide acá, no en el controlador ni en React.
 * La ficha lo usa para decidir qué botones dibuja, y el servidor lo usa para
 * decidir si acepta la petición. Escrito en los dos lados, algún día dirían
 * cosas distintas y el operador vería un botón que el servidor rechaza.
 */
enum EstadoTramite: string
{
    case Pendiente = 'pendiente';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En Revisión',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }

    /**
     * Qué le toca hacer al operador cuando ve un trámite en este estado.
     *
     * Es para el cartel de la ficha. Un estado dice dónde está el expediente;
     * esto dice qué se espera de quien lo está mirando, que es la pregunta que
     * el operador tiene de verdad.
     */
    public function queSigue(): string
    {
        return match ($this) {
            self::Pendiente => 'El expediente se está armando. Cuando los papeles estén completos, envíelo a revisión.',
            self::EnRevision => 'Verifique los adjuntos y los pagos, y después apruebe o rechace.',
            self::Aprobado => 'El rubro quedó habilitado en el carnet. Falta imprimirlo y entregarlo.',
            self::Rechazado => 'El expediente se cerró. El beneficiario puede volver a presentar con los papeles corregidos.',
        };
    }

    /**
     * Nombre del color del badge en las tablas de React.
     *
     * Se devuelve el nombre suelto y no las clases completas porque Tailwind
     * solo incluye en el CSS final lo que puede leer literalmente en el código.
     * Si se agrega un color acá, hay que agregarlo también al mapa de
     * `components/ui/badge.tsx`.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'slate',
            self::EnRevision => 'amber',
            self::Aprobado => 'emerald',
            self::Rechazado => 'rose',
        };
    }

    /**
     * Estados a los que se puede pasar desde el actual.
     *
     * ------------------------------------------------------------------------
     *  NO SE APRUEBA DIRECTO DESDE PENDIENTE — HAY QUE ENVIARLO PRIMERO
     * ------------------------------------------------------------------------
     *
     * El circuito tiene un paso obligatorio en el medio:
     *
     *     PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO
     *
     * Hubo una versión que permitía `PENDIENTE ──▶ APROBADO` para ahorrarle un
     * clic a quien recibía un expediente perfecto. Se sacó, y el motivo es de
     * control y no de comodidad: con ese atajo, la MISMA persona que carga la
     * solicitud en ventanilla podía aprobarla sin que nadie más la tocara.
     *
     * Con el paso obligatorio, cargar y aprobar quedan separados en dos
     * momentos: ventanilla arma el expediente y lo ENVÍA; recién ahí queda a la
     * vista de quien revisa y firma. Es la base sobre la que se apoya la
     * separación de funciones el día que exista el rol de ventanilla —ver el
     * comentario de App\Enums\RolSistema—.
     *
     * ------------------------------------------------------------------------
     *  DESDE PENDIENTE TAMPOCO SE RECHAZA
     * ------------------------------------------------------------------------
     *
     * Y esa es la otra mitad de la idea: PENDIENTE es un BORRADOR. Rechazar es
     * la respuesta a algo que alguien PRESENTÓ, y en borrador no se presentó
     * nada todavía — el expediente lo está armando la misma ventanilla.
     *
     * Si un borrador no sirve —se cargó dos veces, con la persona equivocada—
     * lo que corresponde es ELIMINARLO, que además pide su motivo. Rechazarlo
     * dejaría un expediente cerrado con un «motivo de rechazo» que en realidad
     * nadie le respondió a nadie.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ NO SE VUELVE DE «EN REVISIÓN» A «PENDIENTE»
     * ------------------------------------------------------------------------
     *
     * Porque no hay nada que deshacer: el expediente sigue abierto igual. Si
     * quien lo recibió no lo va a resolver, lo deja como está y otro lo aprueba
     * o lo rechaza. Un botón de «devolver» solo abriría la puerta a que un
     * expediente rebote entre estados sin avanzar nunca.
     *
     * Y hay una razón más concreta: al enviarlo se emite el RECIBO OFICIAL que
     * se le entrega al pescador. Volver atrás dejaría un papel numerado en la
     * calle por un expediente que figura sin presentar.
     *
     * Aprobado y Rechazado son FINALES. Un trámite aprobado por error no se
     * «des-aprueba»: el cupo ya se consolidó en el carnet y
     * posiblemente se imprimió el carnet. Lo que corresponde es suspender el
     * rubro, que deja el rastro de por qué.
     *
     * @return array<int, self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Pendiente => [self::EnRevision],
            self::EnRevision => [self::Aprobado, self::Rechazado],
            self::Aprobado, self::Rechazado => [],
        };
    }

    /**
     * La guarda que usan todos los métodos del servicio antes de escribir.
     */
    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }

    /**
     * ¿El expediente sigue abierto, esperando que alguien lo resuelva?
     *
     * Agrupa PENDIENTE y EN REVISIÓN. Se usa en dos lugares donde confundirlos
     * sería un error:
     *
     *   - el contador de trabajo del tablero: un expediente que alguien tomó
     *     para revisar sigue siendo trabajo sin terminar;
     *   - la comprobación de solicitud duplicada: si un rubro ya tiene un
     *     trámite en revisión, no se puede presentar otro para lo mismo, o el
     *     beneficiario pagaría dos veces por una sola habilitación.
     *
     * OJO: «abierto» NO quiere decir «editable». Un trámite en revisión sigue
     * abierto —falta resolverlo— pero ya no se toca. Para eso están
     * permiteEdicion() y permiteEliminacion(), que hoy responden solo por
     * PENDIENTE. Usar estaAbierto() como permiso de escritura fue justamente el
     * error que se corrigió.
     */
    public function estaAbierto(): bool
    {
        return $this === self::Pendiente || $this === self::EnRevision;
    }

    /**
     * ¿Se puede todavía EDITAR el expediente?
     *
     * Solo en PENDIENTE, porque PENDIENTE es el borrador: mientras se arma, se
     * corrige lo que salió ilegible, se agrega el certificado que faltaba y se
     * cargan los depósitos.
     *
     * ------------------------------------------------------------------------
     *  EN REVISIÓN YA NO — Y ANTES SÍ SE PODÍA
     * ------------------------------------------------------------------------
     *
     * Hubo una versión donde también se editaba en revisión, pensando en el
     * revisor que ve un escaneo ilegible y pide que lo vuelvan a cargar. Se
     * sacó: enviar es PRESENTAR, y lo presentado tiene que quedar igual que
     * cuando se lo miró. Si no, dos cosas se rompen:
     *
     *   - el RECIBO OFICIAL sale al enviar, con el monto congelado. Editar
     *     después deja el papel que tiene el pescador en la mano describiendo
     *     un expediente que ya no es ese;
     *   - quien aprueba firma sobre los papeles que vio. Si se pueden cambiar
     *     mientras tanto, la firma deja de decir sobre qué se firmó.
     *
     * El escaneo ilegible ahora se RECHAZA, con su motivo escrito, y se
     * presenta de nuevo. Es un clic más y deja el rastro de qué pasó.
     *
     * APROBADO y RECHAZADO tampoco, por lo mismo y con más razón: ya hay una
     * decisión tomada mirando esos papeles.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * ¿Se puede BORRAR el expediente?
     *
     * ------------------------------------------------------------------------
     *  SOLO PENDIENTE — SOLO EL BORRADOR
     * ------------------------------------------------------------------------
     *
     * Un borrador que nunca debió existir —cargado dos veces, con la persona
     * equivocada, con el rubro equivocado— no tiene por qué quedar ensuciando
     * la pila. Nadie lo vio todavía, así que borrarlo no contradice nada.
     *
     * EN REVISIÓN ya no, aunque siga abierto: al enviarlo se emitió el RECIBO
     * OFICIAL, con su número de talonario, y el pescador se fue con ese papel.
     * Borrar el expediente dejaría un recibo numerado en la calle sin nada
     * detrás. Un expediente presentado que no corresponde se RECHAZA, que deja
     * el motivo escrito y le da al pescador una respuesta.
     *
     * APROBADO tampoco: el carnet ya habilita, la persona quedó
     * habilitada y posiblemente se imprimió el plástico. Lo que corresponde ahí
     * es suspender el rubro o anular el carnet, que dejan el rastro de por qué.
     *
     * RECHAZADO tampoco: el rechazo ES el registro de que se presentó algo y no
     * se aceptó, con su motivo escrito. Borrarlo dejaría al beneficiario
     * preguntando por qué le devolvieron los papeles sin que el sistema pueda
     * responder.
     *
     * Dicho de otra forma: se borra lo que NADIE llegó a ver. Desde que se
     * presenta, todo expediente termina con una respuesta escrita.
     */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * ¿Se le pueden seguir cargando pagos?
     *
     * Sí mientras esté abierto —el beneficiario está juntando el monto— y
     * también una vez aprobado, porque un trámite puede aprobarse y terminarse
     * de cobrar después. Rechazado no: ese expediente ya no cobra nada.
     */
    public function permitePagos(): bool
    {
        return $this !== self::Rechazado;
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }

    /**
     * Los estados en los que un expediente todavía espera resolución.
     *
     * Lo usan los scopes del modelo. Devuelve los VALORES para poder pasarlos
     * directo a un whereIn().
     *
     * @return array<int, string>
     */
    public static function abiertos(): array
    {
        return array_values(array_map(
            fn (self $estado): string => $estado->value,
            array_filter(self::cases(), fn (self $estado): bool => $estado->estaAbierto()),
        ));
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $estado): array => [
            'value' => $estado->value,
            'label' => $estado->etiqueta(),
            'color' => $estado->color(),
        ], self::cases());
    }
}
