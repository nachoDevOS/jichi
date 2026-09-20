<?php

namespace App\Enums;

use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;

/**
 *  LAS CASILLAS DE «DESCRIPCIÓN» DEL RECIBO OFICIAL
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
     *  QUÉ CASILLA VA MARCADA, SEGÚN LO QUE SE COBRÓ
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
