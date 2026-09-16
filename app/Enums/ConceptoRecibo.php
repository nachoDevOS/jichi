<?php

namespace App\Enums;

use App\Models\Tramite;

/**
 * ============================================================================
 *  LAS CASILLAS DE «DESCRIPCIÓN» DEL RECIBO OFICIAL
 * ============================================================================
 *
 * El talonario del SEDAG trae seis renglones con un paréntesis adelante, y el
 * cajero marca el que corresponde:
 *
 *     ( ) Permiso por Faena
 *     ( ) Solicitud de Importe de Alevines
 *     ( ) Autorización de Pesca para Aprovechamiento Pesquero
 *     ( ) Guía única de Transporte
 *     ( ) Cédulas
 *     ( ) Otros
 *
 * ----------------------------------------------------------------------------
 *  SE IMPRIMEN LOS SEIS, SIEMPRE
 * ----------------------------------------------------------------------------
 *
 * Aunque el sistema solo sepa cobrar dos de ellos. El recibo tiene que salir
 * IGUAL al papel: quien lo recibe está acostumbrado a esa lista, y una versión
 * recortada se lee como si fuera otro documento. Lo que cambia es cuál queda
 * marcado.
 *
 * ----------------------------------------------------------------------------
 *  LA CASILLA DICE QUÉ SE COBRÓ, NO QUÉ ACTIVIDAD HABILITA
 * ----------------------------------------------------------------------------
 *
 * Y esta distinción costó un error. Las seis casillas son los SERVICIOS que
 * cobra el SEDAG por ventanilla, cada uno con su propio trámite:
 *
 *     Permiso por Faena         un permiso puntual de pesca
 *     Importe de Alevines       la solicitud de alevines
 *     Autorización de Pesca     el aprovechamiento pesquero
 *     Guía única de Transporte  la guía que acompaña un cargamento
 *     Cédulas                   ← LA CREDENCIAL. Esto es lo que emite Jichi
 *     Otros
 *
 * Este sistema emite **carnets**, que en el mostrador se llaman «cédula de
 * pescador». Cobre lo que cobre —emisión inicial o adición de rubro, Pescador o
 * Comercializador— lo que el pescador se lleva es su cédula, y esa es la
 * casilla que corresponde.
 *
 * EL RUBRO NO ENTRA EN ESTA DECISIÓN. «Pescador» y «Comercializador» son las
 * actividades que el carnet HABILITA, no servicios distintos del talonario.
 * Marcar «Guía única de Transporte» porque el rubro se llama Comercializador
 * era confundir la actividad autorizada con el papel que se está cobrando: el
 * pescador pagaba su carnet y el recibo decía que había pagado una guía.
 *
 * Qué rubro se habilitó sí se lee en el recibo, pero donde corresponde: en el
 * renglón «Concepto», que dice «Comercializador — Carnet gestión 2026».
 */
enum ConceptoRecibo: string
{
    case PermisoFaena = 'permiso_faena';
    case ImporteAlevines = 'importe_alevines';
    case AprovechamientoPesquero = 'aprovechamiento_pesquero';
    case GuiaTransporte = 'guia_transporte';
    case Cedulas = 'cedulas';
    case Otros = 'otros';

    /**
     * El texto tal cual está impreso en el talonario. No se toca.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PermisoFaena => 'Permiso por Faena',
            self::ImporteAlevines => 'Solicitud de Importe de Alevines',
            self::AprovechamientoPesquero => 'Autorización de Pesca para Aprovechamiento Pesquero',
            self::GuiaTransporte => 'Guía única de Transporte',
            self::Cedulas => 'Cédulas',
            self::Otros => 'Otros',
        };
    }

    /**
     * ========================================================================
     *  QUÉ CASILLA MARCA UN TRÁMITE DE ESTE SISTEMA
     * ========================================================================
     *
     * **Cédulas**, siempre. Jichi emite carnets —la cédula de pescador— y eso es
     * lo que se está cobrando, sea una emisión inicial o una adición de rubro,
     * sea Pescador o Comercializador.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ ES FIJO Y NO UN `match` SOBRE EL RUBRO
     * ------------------------------------------------------------------------
     *
     * Porque no hay nada que decidir. Las otras cinco casillas son servicios
     * que el SEDAG cobra por OTROS trámites —un permiso de faena, una guía de
     * transporte, una solicitud de alevines—, y este sistema no los maneja. El
     * día que maneje alguno, ese módulo tendrá su propio tipo de trámite y este
     * método recibirá algo con qué distinguirlos.
     *
     * Mientras tanto, un `match` que mira el rubro daría la ilusión de estar
     * decidiendo algo cuando en realidad marcaría mal: fue el error original
     * —«Comercializador» marcaba «Guía única de Transporte»— y hacía que el
     * pescador pagara su carnet y el recibo dijera que había pagado una guía.
     *
     * Recibe el trámite igual, y no ningún parámetro, para que el día que haya
     * más de un servicio la firma ya esté lista y solo haya que cambiar el
     * cuerpo.
     */
    public static function desdeTramite(?Tramite $tramite): self
    {
        return self::Cedulas;
    }
}
