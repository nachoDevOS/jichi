<?php

namespace App\Enums;

/**
 * En qué situación está una credencial.
 *
 * ----------------------------------------------------------------------------
 *  ESTA COLUMNA PUEDE MENTIR, Y ESTÁ BIEN QUE PUEDA
 * ----------------------------------------------------------------------------
 *
 * `Vencido` no se escribe solo el día que corresponde: lo pone un comando
 * programado que corre una vez por día. Entre corrida y corrida, un carnet que
 * venció ayer sigue diciendo «activo» en la base.
 *
 * Por eso NINGUNA decisión se toma leyendo esta columna sola: para saber si un
 * carnet vale HOY se mira además `fecha_vencimiento`, que no puede quedar
 * desfasada. Ver Carnet::estaVigente().
 *
 * ¿Y entonces para qué está la columna? Para dos cosas que la fecha no puede
 * dar: distinguir «venció» de «se revocó» —un carnet revocado en marzo tiene la
 * fecha de vencimiento de diciembre y la fecha no lo delata— y filtrar o
 * agrupar en los listados sin calcular una comparación por fila.
 */
enum EstadoCarnet: string
{
    /** Vale. Es como nace toda credencial. */
    case Activo = 'activo';

    /**
     * Dado de baja por decisión de la unidad, antes de su vencimiento.
     *
     * El documento sigue existiendo y su historial queda legible —un inspector
     * necesita saber que la persona estuvo autorizada hasta tal fecha— pero hoy
     * no habilita a trabajar ni a emitir faenas o guías.
     */
    case Revocado = 'revocado';

    /** Se le pasó la fecha. Lo que corresponde es emitir el de la gestión nueva. */
    case Vencido = 'vencido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Revocado => 'Revocado',
            self::Vencido => 'Vencido',
        };
    }

    /**
     * Nombre del color del badge. Devuelve el NOMBRE y no las clases armadas
     * con texto: Tailwind solo incluye en el CSS final las que puede leer
     * literalmente. Un color nuevo acá va también al mapa de
     * resources/js/components/ui/badge.tsx.
     */
    public function color(): string
    {
        return match ($this) {
            self::Activo => 'emerald',
            self::Revocado => 'rose',
            self::Vencido => 'slate',
        };
    }

    /**
     * ¿Esta credencial autoriza a trabajar HOY, según su estado?
     *
     * Solo mira el estado; la fecha la agrega `Carnet::estaVigente()`, por lo
     * dicho arriba sobre el desfase de esta columna.
     */
    public function habilita(): bool
    {
        return $this === self::Activo;
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
