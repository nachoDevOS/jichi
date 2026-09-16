<?php

namespace App\Support;

use App\Enums\EstadoHabilitacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\CarnetRubro;

/**
 * ============================================================================
 *  QUÉ TIENE ESTA PERSONA EN ESTA GESTIÓN
 * ============================================================================
 *
 * Responde, de una sola vez, las tres preguntas que el operador necesita antes
 * de cargar un trámite:
 *
 *   1. ¿tiene carnet de este año?  ->  decide si es EMISIÓN INICIAL o ADICIÓN
 *   2. ¿qué rubros ya tiene?       ->  esos no se pueden volver a pedir
 *   3. ¿el carnet sigue vigente?   ->  uno anulado o vencido no admite adiciones
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
 * medio otra ventanilla pudo haberle habilitado el mismo rubro.
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

        $carnet = $beneficiario->carnetDeGestion($gestion);

        if ($carnet === null) {
            return [
                'gestion' => $gestion,
                'tiene_carnet' => false,
                'carnet' => null,
                // Sin carnet no hay nada habilitado: TODOS los rubros activos se
                // pueden pedir. El arreglo vacío es lo que la pantalla usa para
                // no deshabilitar ninguna opción del selector.
                'rubros_ocupados' => [],
            ];
        }

        // with() para no caer en N+1: el buscador arma esto para diez personas a
        // la vez, y sin eager loading serían veinte consultas por tecleada.
        $carnet->loadMissing('habilitaciones.rubro:id,nombre');

        return [
            'gestion' => $gestion,
            'tiene_carnet' => true,

            'carnet' => [
                'id' => $carnet->id,
                // El número que va IMPRESO en el plástico. La firma no viaja
                // acá: solo entra en el QR. Ver Carnet::registro().
                'registro' => $carnet->registro(),
                'gestion' => $carnet->gestion,
                // Se imprime en la tarjeta, debajo del nombre.
                'asociacion' => $carnet->asociacion,
                'estado' => $carnet->estado->value,
                'estado_etiqueta' => $carnet->estado->etiqueta(),
                'estado_color' => $carnet->estado->color(),
                /*
                 * `vigente` NO es lo mismo que estado === 'vigente'.
                 *
                 * Carnet::estaVigente() mira además la fecha, porque el estado
                 * `vencido` lo escribe un comando programado que corre una vez
                 * al día. Acá importa de verdad: un carnet caído no admite
                 * adiciones, y ofrecerlas sería dejar cargar un trámite que el
                 * servidor va a rechazar.
                 */
                'vigente' => $carnet->estaVigente(),
                'admite_adiciones' => $carnet->admiteAdiciones(),
                'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),

                // NO viaja `firma_validacion`. Es la llave de la verificación
                // pública y no tiene nada que hacer en un autocompletado.
                'rubros' => $carnet->habilitaciones
                    ->map(fn (CarnetRubro $h): array => [
                        'id' => $h->rubro_id,
                        'nombre' => $h->rubro?->nombre,
                        'estado' => $h->estado->value,
                        'estado_etiqueta' => $h->estado->etiqueta(),
                        'estado_color' => $h->estado->color(),
                        'fecha_habilitacion' => $h->fecha_habilitacion?->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ],

            /*
             * LOS RUBROS QUE YA NO SE PUEDEN PEDIR.
             *
             * Incluye los SUSPENDIDOS, y eso sorprende hasta que se piensa: la
             * habilitación existe igual, solo que cortada. Lo que corresponde
             * con un rubro suspendido es que un supervisor la levante, no cobrar
             * otro trámite por lo mismo.
             *
             * Es la misma regla que aplica Carnet::tieneRubro() del lado del
             * servidor; acá se adelanta para que el selector no ofrezca la
             * opción.
             */
            'rubros_ocupados' => $carnet->habilitaciones->pluck('rubro_id')->all(),
        ];
    }

    /**
     * Los rubros habilitados de verdad —sin los suspendidos— en texto corto.
     *
     * Es lo que se muestra en la fila del autocompletado, donde no entra una
     * lista con estados: «Pescador, Comercializador».
     */
    public static function rubrosHabilitados(?Carnet $carnet): string
    {
        if ($carnet === null) {
            return '';
        }

        return $carnet->habilitaciones
            ->where('estado', EstadoHabilitacion::Habilitado)
            ->map(fn (CarnetRubro $h): ?string => $h->rubro?->nombre)
            ->filter()
            ->implode(', ');
    }
}
