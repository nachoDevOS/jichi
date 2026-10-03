<?php

namespace App\Enums;

use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;

/**
 *  Las casillas de «descripción» del recibo oficial
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
     *  Qué casilla va marcada, según el documento pagado
     */
    public static function desdeDocumento(?object $documento): self
    {
        return match (true) {
            $documento instanceof AprovechamientoPesq => self::AprovechamientoPesquero,
            $documento instanceof GuiaMovimiento => self::GuiaTransporte,
            $documento instanceof PermisoFaena => self::PermisoFaena,
            $documento instanceof Carnet => self::Cedulas,
            default => self::Otros,
        };
    }
}
