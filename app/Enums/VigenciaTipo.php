<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * ============================================================================
 *  CÓMO SE CALCULA HASTA CUÁNDO VALE UN DOCUMENTO
 * ============================================================================
 *
 * No todos los documentos vencen de la misma manera, y esa fue la sorpresa al
 * relevar el trámite real:
 *
 *   - El PERMISO POR FAENA vale un mes desde que se emite. Cuenta días.
 *
 *   - La CÉDULA DE PESCADOR vale LA GESTIÓN. Vence el 31 de diciembre del año
 *     en que se emitió, sin importar el mes. Sacada en enero dura casi doce
 *     meses; sacada en diciembre dura unas semanas. Las dos vencen el mismo
 *     día.
 *
 * Esa segunda regla es la que obligó a crear este enum. Antes el vencimiento
 * se calculaba siempre como `emisión + vigencia_dias`, y con esa cuenta una
 * credencial sacada en diciembre habría durado hasta diciembre del año
 * siguiente: casi un año de más habilitando a pescar.
 *
 * ----------------------------------------------------------------------------
 *  DÓNDE SE CAMBIA SI LA REGLA DE LA GESTIÓN NO ES ESTA
 * ----------------------------------------------------------------------------
 *
 * En un solo lugar: el `case Gestion` del match de vencimiento, más abajo. Hoy
 * devuelve el 31 de diciembre del año de emisión. Si la Gobernación maneja una
 * gestión que no coincide con el año calendario, se toca ahí y nada más.
 */
enum VigenciaTipo: string
{
    /** Vence a los `vigencia_dias` de emitido. */
    case Dias = 'dias';

    /** Vence al terminar la gestión en que se emitió. */
    case Gestion = 'gestion';

    /** No vence nunca. */
    case SinVencimiento = 'sin_vencimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Dias => 'Por días desde la emisión',
            self::Gestion => 'Hasta el cierre de la gestión',
            self::SinVencimiento => 'Sin vencimiento',
        };
    }

    /**
     * Cómo se le explica la vigencia al ciudadano en ventanilla.
     */
    public function descripcion(?int $dias): string
    {
        return match ($this) {
            self::Dias => match (true) {
                $dias === null => 'Sin vencimiento',
                $dias === 30 => 'Un mes desde la emisión',
                $dias === 365 => 'Un año desde la emisión',
                default => "{$dias} días desde la emisión",
            },
            // Se dice así y no "un año" a propósito: quien lo saca en octubre
            // tiene que entender en ventanilla que no le dura hasta octubre.
            self::Gestion => 'Hasta el 31 de diciembre de la gestión',
            self::SinVencimiento => 'Sin vencimiento',
        };
    }

    /**
     * La fecha en que el documento deja de valer. NULL = no vence.
     *
     * @param  int|null  $dias  Solo lo usa el tipo Dias; los otros lo ignoran.
     */
    public function calcularVencimiento(CarbonInterface $emision, ?int $dias = null): ?Carbon
    {
        $desde = Carbon::instance($emision->toDateTime())->startOfDay();

        return match ($this) {
            self::Dias => $dias === null ? null : $desde->copy()->addDays($dias),

            // ACÁ vive la regla de la gestión. Ver el comentario de arriba.
            self::Gestion => $desde->copy()->endOfYear()->startOfDay(),

            self::SinVencimiento => null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $v) => [
            'value' => $v->value,
            'label' => $v->etiqueta(),
        ], self::cases());
    }
}
