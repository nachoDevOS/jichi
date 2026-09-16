<?php

namespace App\Enums;

/**
 * Cómo entró el dinero del trámite, tal como lo pregunta el talonario.
 *
 * ----------------------------------------------------------------------------
 *  SON LAS DOS CASILLAS DEL PAPEL, NI UNA MÁS
 * ----------------------------------------------------------------------------
 *
 * El RECIBO OFICIAL del SEDAG trae dos recuadros —«Depósito Bancario» y
 * «Efectivo»— y el operador marca uno. Este enum existe para que esa marca no
 * se escriba como texto suelto en la vista: si mañana el talonario agrega una
 * casilla de transferencia, se agrega un `case` acá y la plantilla ya la sabe
 * dibujar.
 *
 * ----------------------------------------------------------------------------
 *  HOY EL SISTEMA SOLO REGISTRA DEPÓSITOS
 * ----------------------------------------------------------------------------
 *
 * La tabla `pagos` pide número de transacción y boleta escaneada, o sea que
 * todo lo que entra por el formulario es un depósito bancario. `Efectivo` se
 * marca cuando el expediente llega a revisión sin ningún pago cargado: es plata
 * que el pescador puso en el mostrador y que todavía no tiene boleta, y el
 * recibo es justamente el papel que se la respalda.
 */
enum FormaPago: string
{
    case Deposito = 'deposito';
    case Efectivo = 'efectivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Deposito => 'Depósito Bancario',
            self::Efectivo => 'Efectivo',
        };
    }

    /**
     * ¿Esta forma de pago lleva número de comprobante en el recibo?
     *
     * El papel tiene un campo «N°» al lado de las dos casillas. Con depósito ahí
     * va el número de transacción del banco; con efectivo queda vacío, porque no
     * hay ningún número que anotar.
     */
    public function llevaNumero(): bool
    {
        return $this === self::Deposito;
    }
}
