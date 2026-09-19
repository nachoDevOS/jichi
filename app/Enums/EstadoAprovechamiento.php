<?php

namespace App\Enums;

/**
 * En qué situación está la BOLSA MADRE de un pescador.
 *
 * ----------------------------------------------------------------------------
 *  NACE PENDIENTE, Y ESO ES LO QUE LO HACE CORREGIBLE
 * ----------------------------------------------------------------------------
 *
 *     PENDIENTE ──[se cobra entero]──▶ ACTIVO ──▶ AGOTADO | VENCIDO
 *     (borrador)
 *
 * Un cupo recién otorgado es un BORRADOR: el operador lo acaba de cargar contra
 * el talonario y el pescador todavía está enfrente. Mientras nadie pagó nada,
 * equivocarse de tramo o de embarcación se arregla corrigiendo la fila, y un
 * cupo cargado por error se ELIMINA con el motivo escrito.
 *
 * En cuanto entra el primer boliviano eso deja de valer: hay un recibo numerado
 * con el detalle impreso, y cambiar lo que dice ese papel por detrás no es una
 * corrección sino otra cosa. Por eso editar y eliminar viven SOLO en pendiente,
 * y de ahí en adelante lo único que mueve el cupo es AMPLIARLO.
 *
 * Y por eso un cupo pendiente TAMPOCO emite faenas: lo que autoriza a pescar es
 * la concesión pagada, no el papel a medio llenar.
 *
 * ----------------------------------------------------------------------------
 *  TIENE DOS FORMAS DE MORIR, Y HAY QUE PODER DISTINGUIRLAS
 * ----------------------------------------------------------------------------
 *
 * Un aprovechamiento es un cupo de kilos con fecha. Deja de servir por dos
 * motivos distintos y la diferencia importa en ventanilla:
 *
 *   - `Vencido`  — se le acabó el TIEMPO. Puede quedarle volumen sin usar, y
 *                  ese volumen se pierde: no se arrastra a la gestión siguiente.
 *   - `Agotado`  — se le acabaron los KILOS. La fecha todavía no llegó, pero
 *                  las faenas emitidas ya consumieron el volumen otorgado.
 *
 * Al pescador se le dice cosas distintas en cada caso: en uno renueva, en el
 * otro pide ampliación de cupo. Un solo estado «inactivo» obligaría a deducirlo
 * comparando fechas y sumando faenas cada vez.
 *
 * ----------------------------------------------------------------------------
 *  IGUAL QUE EN CARNETS, ESTA COLUMNA PUEDE ESTAR DESFASADA
 * ----------------------------------------------------------------------------
 *
 * `Agotado` lo escribe quien emite la última faena y `Vencido` un comando
 * diario. Para decidir si hoy se puede emitir una faena se usa
 * `AprovechamientoPesq::puedeEmitirFaena()`, que mira además la fecha y el
 * saldo real en kilos.
 */
enum EstadoAprovechamiento: string
{
    /** Otorgado y todavía sin cobrar. Es el borrador: se edita y se elimina. */
    case Pendiente = 'pendiente';

    case Activo = 'activo';
    case Vencido = 'vencido';
    case Agotado = 'agotado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de pago',
            self::Activo => 'Activo',
            self::Vencido => 'Vencido',
            self::Agotado => 'Agotado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'sky',
            self::Activo => 'emerald',
            self::Vencido => 'slate',
            self::Agotado => 'amber',
        };
    }

    /**
     * ¿Según el estado, todavía se le pueden colgar faenas?
     *
     * PENDIENTE no habilita, y no es un descuido: lo que autoriza a pescar es
     * la concesión PAGADA. Un cupo cargado y sin cobrar es un papel a medio
     * llenar, y emitir faenas contra él dejaría al pescador trabajando sobre
     * una autorización que la unidad todavía no entregó.
     */
    public function habilita(): bool
    {
        return $this === self::Activo;
    }

    /**
     * ¿Se pueden corregir sus datos?
     *
     * Solo el borrador. Con un pago encima existe un recibo numerado que dice
     * qué se cobró y por qué: cambiar el tramo por detrás haría que el papel
     * entregado dejara de coincidir con la fila, sin que nada lo delate.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * ¿Se puede borrar la fila entera?
     *
     * Mismo criterio que la edición, y con más razón: un cupo con plata encima
     * no se elimina —se arregla por caja—, porque borrarlo dejaría pagos
     * colgando de algo que ya no existe.
     */
    public function permiteEliminacion(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $e): array => [
            'value' => $e->value,
            'label' => $e->etiqueta(),
            'color' => $e->color(),
        ], self::cases());
    }
}
