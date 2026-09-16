<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Pagos — uno o varios depósitos por trámite
|--------------------------------------------------------------------------
|
| La relación con `tramites` es de 1 a N a propósito: el pescador puede
| depositar todo junto o en cuotas, y cada depósito llega con su propia boleta
| del banco. Guardar un solo `monto_pagado` en el trámite obligaría a sumarlos
| a mano antes de escribir y perdería la traza de cada comprobante.
|
| NO HAY COLUMNA `saldo`. Lo que se debe es una resta —monto_requerido menos la
| suma de los pagos— y se calcula al leer. Guardado, quedaría desfasado el día
| que alguien inserte o corrija un pago sin acordarse de recalcularlo. Ver
| Tramite::montoPagado() y Tramite::saldoPendiente().
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();

            /*
             * EL NÚMERO DE TRANSACCIÓN ES ÚNICO EN TODO EL SISTEMA.
             *
             * El script SQL de referencia no lo pedía. Se agrega porque la misma
             * boleta cargada dos veces —el operador duda si guardó y vuelve a
             * dar clic— haría figurar el trámite como pagado con la mitad del
             * dinero. Es la única defensa real contra eso: la comprobación en
             * PHP no resiste dos peticiones simultáneas, el índice sí.
             *
             * Es único global y no por trámite porque un mismo depósito tampoco
             * puede usarse para pagar dos expedientes distintos.
             */
            $table->string('nro_transaccion', 50)->unique();

            $table->decimal('monto', 10, 2);

            // Ruta de la boleta escaneada. Igual que los adjuntos del trámite,
            // puede ser ruta local o dirección completa según el disco: se lee
            // siempre a través de App\Support\Archivos::url().
            $table->string('urlFile')->comment('Comprobante escaneado del deposito');

            // La fecha del depósito en el banco, que no es la de carga en el
            // sistema: una boleta del viernes se registra el lunes.
            $table->timestamp('fecha_pago')->useCurrent();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            // «Los pagos de este trámite, del más viejo al más nuevo» — la
            // consulta de la ficha y la que suma para saber el saldo.
            $table->index(['tramite_id', 'fecha_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
