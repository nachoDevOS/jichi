<?php

namespace App\Enums;

/**
 * En qué situación está un carnet.
 *
 * ----------------------------------------------------------------------------
 *  UN CARNET ES UNA ACTIVIDAD, ASÍ QUE SU ESTADO ES EL DE ESA ACTIVIDAD
 * ----------------------------------------------------------------------------
 *
 * Antes existía un enum aparte, `EstadoHabilitacion`, para decir si un rubro
 * estaba habilitado o suspendido DENTRO de un carnet que agrupaba varios. Ese
 * enum ya no existe, y sus dos valores se resolvieron acá: con un carnet por
 * rubro, suspender la actividad es suspender el carnet, y los demás carnets de
 * la persona siguen intactos.
 *
 * A un pescador se le puede cortar el transporte de producto sin quitarle la
 * pesca artesanal: son dos carnets distintos y se suspende uno.
 *
 * ----------------------------------------------------------------------------
 *  ESTA COLUMNA PUEDE MENTIR, Y ESTÁ BIEN QUE PUEDA
 * ----------------------------------------------------------------------------
 *
 * `Vencido` no se escribe solo el 1 de enero: lo pone un comando programado que
 * corre una vez por día. Entre corrida y corrida, un carnet que venció ayer
 * sigue diciendo «vigente» en la base.
 *
 * Por eso NINGUNA decisión se toma leyendo esta columna sola. Para saber si un
 * carnet vale hoy se mira `fecha_vencimiento`, que no puede quedar desfasada.
 * Ver Carnet::estaVigente().
 *
 * ¿Y entonces para qué está la columna? Para tres cosas que la fecha no puede
 * dar: distinguir «venció» de «se anuló» —un carnet anulado en marzo tiene la
 * fecha de vencimiento de diciembre y la fecha no lo delata—, marcar una
 * suspensión, que no tiene ninguna fecha asociada, y filtrar y agrupar en los
 * listados sin calcular una comparación por fila.
 */
enum EstadoCarnet: string
{
    case Vigente = 'vigente';

    /**
     * Cortado temporalmente. El documento sigue existiendo y su historial
     * queda legible —un inspector necesita saber que estuvo autorizado hasta
     * tal fecha—, pero hoy no habilita a trabajar.
     *
     * Se distingue de `Anulado` porque se puede volver atrás: lo levanta un
     * supervisor. Anular es definitivo.
     */
    case Suspendido = 'suspendido';

    case Vencido = 'vencido';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Suspendido => 'Suspendido',
            self::Vencido => 'Vencido',
            self::Anulado => 'Anulado',
        };
    }

    /**
     * Nombre del color del badge.
     *
     * Devuelve el NOMBRE y no las clases armadas con texto, porque Tailwind
     * solo incluye en el CSS final las clases que puede leer literalmente en el
     * código. Si se agrega un color acá hay que agregarlo también al mapa de
     * `resources/js/components/ui/badge.tsx`, o el badge sale sin fondo y sin
     * ningún error que lo explique.
     */
    public function color(): string
    {
        return match ($this) {
            self::Vigente => 'emerald',
            self::Suspendido => 'amber',
            self::Vencido => 'slate',
            self::Anulado => 'rose',
        };
    }

    /**
     * ¿Este carnet autoriza a trabajar HOY, según su estado?
     *
     * Solo mira el estado; la fecha la agrega `Carnet::estaVigente()`, por lo
     * dicho arriba sobre el desfase de esta columna. Un carnet suspendido
     * existe y es consultable, pero no habilita.
     */
    public function habilita(): bool
    {
        return $this === self::Vigente;
    }

    /**
     * ¿Se puede volver a levantar?
     *
     * Solo una suspensión. Vencido se resuelve emitiendo el carnet de la
     * gestión siguiente, y anulado es definitivo.
     */
    public function esReversible(): bool
    {
        return $this === self::Suspendido;
    }

    /**
     * ¿Admite que se le presente un trámite de actualización?
     *
     * Un carnet anulado no, aunque la fecha no haya llegado: se anuló por algo.
     * Uno vencido tampoco —lo que corresponde es emitir el de la gestión
     * nueva—, pero esa comprobación se hace además contra la fecha. Uno
     * suspendido tampoco: lo que corresponde es que un supervisor lo levante,
     * no cobrar otro trámite por lo mismo.
     */
    public function admiteTramites(): bool
    {
        return $this === self::Vigente;
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
