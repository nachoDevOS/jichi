<?php

namespace App\Enums;

/**
 * En qué situación está un carnet.
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
 * ¿Y entonces para qué está la columna? Para dos cosas que la fecha no puede
 * dar: distinguir «venció» de «se anuló» —un carnet anulado en marzo tiene la
 * fecha de vencimiento de diciembre y la fecha no lo delata—, y para filtrar y
 * agrupar en los listados sin calcular una comparación de fechas por fila.
 */
enum EstadoCarnet: string
{
    case Vigente = 'vigente';
    case Vencido = 'vencido';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::Vencido => 'Vencido',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vigente => 'emerald',
            self::Vencido => 'amber',
            self::Anulado => 'rose',
        };
    }

    /**
     * ¿Se le pueden seguir agregando rubros?
     *
     * Un carnet anulado no admite adiciones aunque la fecha no haya llegado: se
     * anuló por algo. Un carnet vencido tampoco —lo que corresponde es emitir el
     * de la gestión nueva—, pero esa comprobación se hace además contra la
     * fecha, por lo dicho arriba sobre el desfase de esta columna.
     */
    public function admiteAdiciones(): bool
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
