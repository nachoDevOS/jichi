<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| El detalle de importes del recibo
|--------------------------------------------------------------------------
|
| La cuadrícula «IMPORTE A PAGAR Bs.» del talonario no es un solo número: es un
| cuadro con un renglón por cobro y el TOTAL al pie. Un pescador que pagó en dos
| depósitos tiene que ver los dos escritos, igual que en el papel.
|
| ----------------------------------------------------------------------------
|  POR QUÉ SE COPIAN ACÁ Y NO SE LEEN DE `pagos`
| ----------------------------------------------------------------------------
|
| Por lo mismo que todo el resto de esta tabla: el recibo es un papel numerado
| que ya se entregó, y tiene que seguir diciendo lo mismo para siempre.
|
| Y acá el caso es concreto, no teórico: un trámite SIGUE ACEPTANDO PAGOS
| después de pasar a revisión —`EstadoTramite::permitePagos()` lo permite hasta
| que se rechaza—. Si la plantilla leyera `pagos` al imprimir, una boleta cargada
| la semana siguiente aparecería en la reimpresión de un recibo que se entregó
| antes de que ese depósito existiera. El papel del pescador y el del sistema
| dirían cosas distintas.
|
| Copiado, el recibo declara exactamente lo que se había recibido el día que se
| emitió, que es lo que un comprobante tiene que declarar.
|
| ----------------------------------------------------------------------------
|  ES JSON Y NO UNA TABLA APARTE
| ----------------------------------------------------------------------------
|
| Son dos o tres renglones que solo existen para imprimirse: nadie los va a
| consultar sueltos, filtrar ni cruzar. Una tabla `recibo_lineas` agregaría una
| relación, un modelo y un join a cada impresión para guardar lo mismo.
|
| `nullable` por las filas anteriores a esta migración: el modelo arma el
| renglón único a partir de `monto` cuando no hay detalle. Ver Recibo::lineas().
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->json('detalle')->nullable()->after('concepto')
                ->comment('Renglones del cuadro de importes: [{descripcion, monto}]');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropColumn('detalle');
        });
    }
};
