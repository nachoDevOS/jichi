<?php

namespace App\Support;

use App\Models\Beneficiario;
use App\Models\Carnet;

/**
 * ============================================================================
 *  QUÉ TIENE ESTA PERSONA EN ESTA GESTIÓN
 * ============================================================================
 *
 * Responde, de una sola vez, las preguntas que el operador necesita antes de
 * cargar un trámite:
 *
 *   1. ¿qué actividades ya tiene cubiertas este año?
 *   2. ¿cuáles de esas siguen vigentes y cuáles están cortadas?
 *   3. ¿qué rubros puede pedir sin chocar con nada?
 *
 * ----------------------------------------------------------------------------
 *  YA NO HAY «UN» CARNET, HAY UNA LISTA
 * ----------------------------------------------------------------------------
 *
 * Esta clase devolvía antes `tiene_carnet` y un objeto `carnet`, porque el
 * modelo garantizaba uno solo por persona y gestión. Hoy hay uno POR ACTIVIDAD,
 * así que devuelve `carnets` —una lista, posiblemente vacía— y la pantalla
 * decide cómo mostrarla.
 *
 * El cambio importa más de lo que parece: la pregunta del formulario pasó de
 * «¿es emisión inicial o adición?» —una sola respuesta para toda la persona— a
 * «¿qué es PARA ESTE RUBRO?», que tiene una respuesta distinta en cada fila del
 * selector. Por eso `rubros_ocupados` sigue existiendo pero significa otra cosa:
 * antes eran los rubros ya colgados del único carnet; ahora son los rubros que
 * ya tienen su propio carnet.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTÁ ACÁ Y NO EN UN CONTROLADOR
 * ----------------------------------------------------------------------------
 *
 * Porque lo arman DOS lugares distintos con la misma forma: el autocompletado
 * del buscador (`BeneficiarioController::buscar`, que devuelve diez personas) y
 * el formulario de trámite cuando llega con un beneficiario ya elegido
 * (`TramiteController::create`). Escrito dos veces, alcanza con agregar un campo
 * en uno para que la pantalla muestre datos distintos según de dónde se entró.
 *
 * ----------------------------------------------------------------------------
 *  ESTO ES PARA LA PANTALLA, NO ES LA REGLA
 * ----------------------------------------------------------------------------
 *
 * Lo que devuelve sirve para que el formulario no ofrezca lo que no se puede
 * pedir. La decisión REAL la toma SolicitudCarnetService al registrar, dentro de
 * una transacción y con la fila del beneficiario bloqueada: entre que el
 * operador ve esta pantalla y aprieta guardar pueden pasar minutos, y en el
 * medio otra ventanilla pudo haberle emitido el mismo carnet.
 *
 * Por eso el servidor vuelve a comprobar todo. Esto solo evita que el operador
 * cargue un expediente entero para que se lo rechacen al final.
 */
class SituacionCarnet
{
    /**
     * @return array<string, mixed>
     */
    public static function para(Beneficiario $beneficiario, ?int $gestion = null): array
    {
        $gestion ??= (int) now()->format('Y');

        /*
         * loadMissing y no load: el buscador arma esto para diez personas de una
         * vez y ya trajo `carnets.rubro` con eager loading. `load()` a secas
         * volvería a consultar igual —una vez por persona— que es exactamente el
         * N+1 que el `with()` del controlador viene a evitar.
         */
        $beneficiario->loadMissing('carnets.rubro:id,nombre');

        $carnets = $beneficiario->carnetsDeGestion($gestion);

        return [
            'gestion' => $gestion,

            // Se conserva el nombre por comodidad de la pantalla, pero ahora
            // significa «tiene AL MENOS UNO», no «tiene EL carnet».
            'tiene_carnet' => $carnets->isNotEmpty(),

            'carnets' => $carnets->map(self::resumir(...))->values()->all(),

            /*
             * LOS RUBROS QUE YA NO SE PUEDEN PEDIR ESTE AÑO.
             *
             * Incluye los de carnets SUSPENDIDOS y ANULADOS, y eso sorprende
             * hasta que se piensa:
             *
             *   - suspendido: el carnet existe y ya se pagó. Lo que corresponde
             *     es que un supervisor levante la suspensión, no cobrar otro
             *     trámite por lo mismo;
             *   - anulado: sigue ocupando su lugar en el índice único
             *     (beneficiario, rubro, gestión), así que emitir otro del mismo
             *     rubro ese año es IMPOSIBLE — la base no lo deja. Ofrecerlo en
             *     el selector sería dejar cargar un expediente entero para que
             *     reviente al guardar.
             *
             * Es la misma regla que aplica el servicio del lado del servidor;
             * acá se adelanta para que el selector no ofrezca la opción.
             */
            'rubros_ocupados' => $carnets->pluck('rubro_id')->all(),
        ];
    }

    /**
     * Un carnet, con lo que la pantalla necesita y NADA MÁS.
     *
     * NO viaja `firma_validacion`. Es la llave de la verificación pública y no
     * tiene nada que hacer en un autocompletado que devuelve diez personas.
     *
     * @return array<string, mixed>
     */
    private static function resumir(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,

            // El número que va IMPRESO en el plástico. Ver Carnet::registro().
            'registro' => $carnet->registro(),

            'rubro_id' => $carnet->rubro_id,
            'rubro' => $carnet->rubro?->nombre,

            'gestion' => $carnet->gestion,

            // Se imprime en la tarjeta, debajo del nombre.
            'asociacion' => $carnet->asociacion,

            // El cupo autorizado, ya escrito como va en el plástico: «600 KG».
            'capacidad' => $carnet->capacidadLegible(),

            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),

            /*
             * `vigente` NO es lo mismo que estado === 'vigente'.
             *
             * Carnet::estaVigente() mira además la fecha, porque el estado
             * `vencido` lo escribe un comando programado que corre una vez al
             * día. Acá importa de verdad: un carnet caído no habilita, y
             * mostrarlo como bueno mandaría a trabajar a alguien sin permiso.
             */
            'vigente' => $carnet->estaVigente(),

            // Si admite que se le presente un trámite de actualización.
            'admite_tramites' => $carnet->admiteTramites(),

            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),
        ];
    }

    /**
     * Las actividades que la persona tiene VIGENTES este año, en texto corto.
     *
     * Es lo que se muestra en la fila del autocompletado, donde no entra una
     * lista con estados: «Pescador, Comercializador».
     *
     * Deja afuera los suspendidos, los anulados y los vencidos: la fila tiene
     * que responder «¿qué puede hacer hoy esta persona?», y un carnet cortado no
     * la habilita a nada. Los cortados igual se ven completos en la ficha.
     *
     * @param  iterable<int, Carnet>  $carnets
     */
    public static function rubrosVigentes(iterable $carnets): string
    {
        $nombres = [];

        foreach ($carnets as $carnet) {
            if ($carnet->estaVigente() && filled($carnet->rubro?->nombre)) {
                $nombres[] = $carnet->rubro->nombre;
            }
        }

        return implode(', ', $nombres);
    }
}
