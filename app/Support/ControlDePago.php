<?php

namespace App\Support;

use App\Models\Pago;
use App\Models\User;

/**
 * El estado del CONTROL de un depósito, ya resuelto para la pantalla.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTÁ ACÁ Y NO EN UN CONTROLADOR
 * ----------------------------------------------------------------------------
 *
 * Porque lo pintan DOS pantallas: el libro de caja, que los lista todos, y la
 * ficha del trámite, que es donde quien revisa hace el trabajo. Escrito dos
 * veces, alcanzaría con tocar uno para que una diga «Validado» y la otra
 * muestre el botón de validar sobre el mismo depósito.
 *
 * Es PRESENTACIÓN, no negocio: arma los rótulos y los colores. Quién puede
 * validar qué lo decide `Pago::puedeValidarlo()`, y quién IMPIDE es
 * `ValidacionPagoService`.
 */
class ControlDePago
{
    /**
     * @return array<string, mixed>
     */
    public static function resumen(Pago $pago, ?User $usuario): array
    {
        return [
            'validacion' => $pago->estado_validacion->value,
            'validacion_etiqueta' => $pago->estado_validacion->etiqueta(),
            'validacion_color' => $pago->estado_validacion->color(),

            // Los nombres van resueltos, no los ids: la pantalla muestra «María
            // Pérez», y mandar el id obligaría a un catálogo de usuarios en el
            // frontend para traducirlo.
            'registrado_por' => $pago->registradoPor?->name,
            'validado_por' => $pago->validadoPor?->name,
            'validado_at' => $pago->validado_at?->toIso8601String(),
            'motivo_observacion' => $pago->motivo_observacion,

            /*
             * Si ESTE usuario puede controlar ESTE depósito.
             *
             * Lo contesta el modelo y no la pantalla, por lo mismo que los
             * `puede_*` del trámite: incluye la regla de que quien cargó la
             * boleta no puede darla por buena, y reescrita en React esa regla
             * terminaría diciendo otra cosa.
             *
             * OJO: es solo la mitad. La otra es el permiso `pagos.validar`, que
             * React consulta con usePermisos() y el middleware aplica de verdad.
             */
            'puede_validarse' => $pago->puedeValidarlo($usuario),

            /*
             * Si ESTE es el momento de controlarlo. Es otra pregunta que la de
             * arriba: una mira el ESTADO de lo que se paga —el control es parte
             * de la revisión— y la otra mira QUIÉN.
             *
             * `motivo_sin_control` trae el texto ya escrito para mostrar, porque
             * cambia según el caso y reescribirlo en React sería tener dos
             * versiones del mismo mensaje.
             */
            'puede_controlarse' => $pago->admiteControl(),
            'motivo_sin_control' => $pago->motivoSinControl($usuario),
        ];
    }
}
