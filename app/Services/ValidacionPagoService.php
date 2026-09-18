<?php

namespace App\Services;

use App\Enums\EstadoValidacionPago;
use App\Exceptions\SolicitudInvalidaException;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EL CONTROL DE CADA DEPÓSITO
 * ============================================================================
 *
 * Alguien abre la boleta escaneada y la compara contra el extracto del banco:
 * el número de transacción, el monto y la fecha. Si cuadra, la VALIDA; si no,
 * la OBSERVA y escribe por qué.
 *
 * Es la única forma de saber que el dinero entró de verdad. La fila la tipea
 * una persona y el archivo adjunto puede ser cualquier cosa; hasta que alguien
 * distinto lo mire, lo que hay es una declaración, no un cobro.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ UN SERVICIO PROPIO Y NO UN MÉTODO EN PagoTramiteService
 * ----------------------------------------------------------------------------
 *
 * Porque son dos actos de personas distintas. `PagoTramiteService` es de
 * VENTANILLA —carga la boleta, sube el archivo, suma el saldo—; esto es de
 * SUPERVISIÓN. Juntos en una clase, el día que exista el rol de ventanilla
 * habría que separarlos igual, y mientras tanto la clase mezclaría dos permisos
 * distintos.
 *
 * Además `PagoTramiteService` solo sabe de trámites, y esto vale para los tres:
 * `pagos` es polimórfica y una faena o una guía se controlan igual.
 */
class ValidacionPagoService
{
    /**
     * ========================================================================
     *  DAR POR BUENO UN DEPÓSITO
     * ========================================================================
     *
     * Dos comprobaciones, y la segunda es la que importa.
     */
    public function validar(Pago $pago, User $usuario): Pago
    {
        $this->comprobarQuePuede($pago, $usuario);

        /*
         * UN OBSERVADO NO SE VALIDA: PRIMERO SE CORRIGE.
         *
         * Antes sí se podía, y era la única salida que había —no existía la
         * corrección—. Hoy es al revés: validar un depósito observado sin que
         * nadie haya tocado el dato es dar por bueno exactamente lo que se
         * marcó como malo, y el problema señalado se pierde sin que quede si se
         * arregló o no.
         *
         * El camino es OBSERVADO ──[corregir]──▶ SIN CONTROLAR ──[validar]──▶
         * VALIDADO. `PagoTramiteService::corregir()` es quien devuelve el
         * depósito a sin controlar, y también cubre el caso de la observación
         * equivocada: se guarda sin cambiar nada.
         *
         * Va DESPUÉS de comprobarQuePuede(): si además no es el momento o no es
         * la persona, ese mensaje es el que importa primero.
         */
        if ($pago->estaObservado()) {
            throw SolicitudInvalidaException::pagoObservadoSeCorrigePrimero();
        }

        return DB::transaction(function () use ($pago, $usuario): Pago {
            $pago->update([
                'estado_validacion' => EstadoValidacionPago::Validado,
                'validado_por' => $usuario->id,
                'validado_at' => now(),
                /*
                 * Se limpia el motivo por las dudas, aunque desde que un
                 * observado no se puede validar directo no debería quedar
                 * ninguno: el depósito llega acá siempre desde «sin controlar»,
                 * y `corregir()` ya lo borró. Cuesta una línea y evita que un
                 * texto viejo haga creer que el problema sigue.
                 */
                'motivo_observacion' => null,
            ]);

            return $pago->refresh();
        });
    }

    /**
     * ========================================================================
     *  MARCAR QUE LA BOLETA NO CUADRA
     * ========================================================================
     *
     * EL MOTIVO ES OBLIGATORIO, y no es formalidad: es lo único que le dice a
     * ventanilla qué tiene que ir a corregir. «Observado» a secas manda a la
     * persona a adivinar si el problema es el monto, la fecha o el archivo.
     *
     * NO ES DEFINITIVO. Ventanilla corrige el dato o vuelve a subir la boleta, y
     * quien revisa lo valida. Se distingue de rechazar el trámite en que acá el
     * problema es de UN depósito y se arregla sin voltear el expediente entero.
     */
    public function observar(Pago $pago, User $usuario, ?string $motivo): Pago
    {
        $this->comprobarQuePuede($pago, $usuario);

        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            throw SolicitudInvalidaException::motivoObservacionObligatorio();
        }

        return DB::transaction(function () use ($pago, $usuario, $motivo): Pago {
            $pago->update([
                'estado_validacion' => EstadoValidacionPago::Observado,
                // Se guarda igual QUIÉN y CUÁNDO: observar también es haber
                // revisado, y el dato sirve para saber hace cuánto está trabado.
                'validado_por' => $usuario->id,
                'validado_at' => now(),
                'motivo_observacion' => $motivo,
            ]);

            return $pago->refresh();
        });
    }

    /**
     * QUIEN CARGÓ EL DEPÓSITO NO PUEDE VALIDARLO.
     *
     * Es el control clásico de separación de funciones: la misma persona que
     * dice «entraron 150 Bs» no puede además declarar que lo comprobó, porque
     * entonces no lo comprobó nadie.
     *
     * Hoy, con un solo rol, el caso se da todo el tiempo —el administrador carga
     * y revisa— así que esto VA A MOLESTAR en una oficina de una sola persona.
     * Se dejó igual porque es la regla que el responsable definió, y porque
     * quitarla después es una línea mientras que agregarla más tarde obliga a
     * revisar todo lo ya validado.
     */
    private function comprobarQuePuede(Pago $pago, User $usuario): void
    {
        /*
         * EL CONTROL ES PARTE DE LA REVISIÓN.
         *
         * Se comprueba ANTES que la persona, a propósito: si el expediente ya
         * está aprobado, el mensaje útil es «el control quedó cerrado» y no
         * «tiene que revisarlo otra persona», que mandaría a buscar un
         * compañero para hacer algo que ya no corresponde.
         *
         * Ver Pago::admiteControl().
         */
        if (! $pago->admiteControl()) {
            throw SolicitudInvalidaException::fueraDeMomentoParaControlar(
                $pago->motivoSinControl($usuario) ?? '',
            );
        }

        if (! $pago->puedeValidarlo($usuario)) {
            throw SolicitudInvalidaException::noValidaSuPropioPago();
        }
    }
}
