<?php

use App\Enums\EstadoGuia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Guías de movimiento — el amparo de UN traslado de producto.
|
| Lo que la faena es para el pescador, la guía es para el comercializador: el
| carnet habilita el año, la guía habilita el viaje. Vale como máximo 5 días.
|
| `es_piscicultura` NO es descriptivo: es plata. Marcado, el arancel se cobra al
| 50% —el pescado de criadero no sale del río—. El descuento se aplica en UN
| solo lugar: GuiaMovimiento::factorArancel().
|
| Ver docs/MER.md.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias_movimiento', function (Blueprint $table) {
            $table->id();

            // Se llama `_com_` porque acá la persona entra en un papel concreto:
            // es QUIEN COMERCIALIZA, no el pescador que extrajo la carga.
            $table->foreignId('beneficiario_com_id')->constrained('beneficiarios')->restrictOnDelete();
            $table->foreignId('asociacion_id')->constrained('asociaciones')->restrictOnDelete();

            // Global y NO parcial: igual que el carnet, el papel ya se entregó y
            // su número no vuelve a usarse aunque la fila se dé de baja.
            $table->string('codigo_guia', 40)->unique();

            $table->string('origen', 160);
            $table->string('destino', 160);

            $table->decimal('peso_total_kg', 12, 2)->default(0);

            $table->boolean('es_piscicultura')->default(false)
                ->comment('true = producto de criadero: el arancel se cobra al 50%');

            $table->string('estado', 20)->default(EstadoGuia::Activa->value);

            // `dateTime` y no `date`: los cinco días se cuentan desde la HORA de
            // emisión. Una guía de las 18:00 del lunes vence a las 18:00 del
            // sábado, no a la medianoche del viernes. Por eso a React van con
            // toIso8601String() —son MOMENTOS— y no con toDateString().
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_vencimiento')->comment('Máximo 5 días desde fecha_emision');

            $table->index(['beneficiario_com_id', 'estado']);
            $table->index('asociacion_id');

            // Para el comando diario que cierra las que se pasaron de fecha.
            $table->index(['estado', 'fecha_vencimiento']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias_movimiento');
    }
};
