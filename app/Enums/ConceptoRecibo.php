<?php

namespace App\Enums;

use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;

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
 * pescador». Cobre lo que cobre —emisión inicial o actualización, Pescador o
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
     *  QUÉ CASILLA VA MARCADA, SEGÚN LO QUE SE COBRÓ
     * ========================================================================
     *
     * El `match` va sobre la CLASE del pagable y no sobre un texto: es el mismo
     * dato, pero así el analizador avisa el día que se agregue un cobrable y
     * este método se olvide.
     *
     * UN RECIBO PUEDE CUBRIR VARIAS COSAS —el carnet y el cupo en un mismo
     * depósito— y el papel tiene UNA sola casilla. Se marca la del PRIMER pago,
     * que es el concepto principal; el detalle completo va igual en el cuadro de
     * importes, renglón por renglón. Marcar dos casillas sería inventar un papel
     * que el talonario no tiene.
     */
    public static function desdePagable(?object $pagable): self
    {
        return match (true) {
            $pagable instanceof AprovechamientoPesq => self::AprovechamientoPesquero,
            $pagable instanceof GuiaMovimiento => self::GuiaTransporte,
            $pagable instanceof PermisoFaena => self::PermisoFaena,
            $pagable instanceof Carnet => self::Cedulas,
            default => self::Otros,
        };
    }
}
