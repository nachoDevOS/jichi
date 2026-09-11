<?php

namespace Database\Seeders;

use App\Enums\CategoriaDocumento;
use App\Enums\EstadoDocumento;
use App\Enums\EstadoTramite;
use App\Enums\FormaPago;
use App\Models\Documento;
use App\Models\Pago;
use App\Models\Solicitante;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use App\Services\CorrelativoService;
use App\Services\EmisionDocumentoService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de prueba para desarrollo: solicitantes, trámites en todos los estados,
 * pagos y documentos emitidos. No debe ejecutarse en producción.
 */
class DemoSeeder extends Seeder
{
    public function __construct(private readonly CorrelativoService $correlativos) {}

    public function run(): void
    {
        $operador = User::where('email', 'ventanilla1@beni.gob.bo')->firstOrFail();
        $supervisor = User::where('email', 'supervisor@beni.gob.bo')->firstOrFail();

        $solicitantes = Solicitante::factory(40)->create()
            ->merge(Solicitante::factory(8)->casada()->create());

        $tipos = TipoTramite::with('area')->get();

        foreach (range(1, 60) as $i) {
            $tipo = $tipos->random();
            $solicitante = $solicitantes->random();
            $monto = (float) $tipo->monto ?: 100.0;
            $creado = now()->subDays(random_int(0, 45))->setTime(random_int(8, 17), random_int(0, 59));

            $estado = $this->estadoPonderado();

            $tramite = Tramite::create([
                'solicitante_id' => $solicitante->id,
                'tipo_tramite_id' => $tipo->id,
                'user_id' => $operador->id,
                'estado' => $estado,
                'monto_total' => $monto,
                'fecha_recepcion' => $creado,
                'created_at' => $creado,
                'updated_at' => $creado,
            ]);

            if ($estado === EstadoTramite::Rechazado) {
                $tramite->update([
                    'motivo_rechazo' => 'Documentación incompleta: falta certificado de afiliación.',
                    'revisado_por' => $supervisor->id,
                    'fecha_revision' => $creado->copy()->addHours(2),
                ]);

                continue;
            }

            // Todo trámite que pasó de la revisión ya fue cobrado en ventanilla.
            if ($estado !== EstadoTramite::EnRevision) {
                $forma = collect(FormaPago::cases())->random();

                Pago::create([
                    'nro_comprobante' => $this->correlativos->siguiente('PAG', (int) $creado->format('Y')),
                    'tramite_id' => $tramite->id,
                    'user_id' => $operador->id,
                    'monto_bruto' => $monto,
                    'descuento' => 0,
                    'monto' => $monto,
                    'forma_pago' => $forma,
                    'referencia' => $forma->requiereReferencia() ? strtoupper(Str::random(10)) : null,
                    'fecha_pago' => $creado->copy()->addMinutes(15),
                    'estado' => 'pagado',
                    'created_at' => $creado,
                    'updated_at' => $creado,
                ]);

                $tramite->recalcularPagado();
            }

            /*
             * El documento existe desde que se aprueba: emitirlo dejó de ser
             * un estado del trámite y pasó a ser una fila en `documentos`.
             */
            if (in_array($estado, [EstadoTramite::Aprobado, EstadoTramite::Entregado], true)) {
                $emision = $creado->copy()->addDay();
                $vencimiento = $tipo->vigencia_dias
                    ? $emision->copy()->addDays($tipo->vigencia_dias)
                    : null;

                Documento::create([
                    'codigo_verificacion' => $this->codigoDePrueba(),
                    'tramite_id' => $tramite->id,
                    'tipo' => $tipo->categoria_documento ?? CategoriaDocumento::Permiso,
                    'fecha_emision' => $emision->toDateString(),
                    'fecha_vencimiento' => $vencimiento?->toDateString(),
                    'estado' => $vencimiento && $vencimiento->isPast()
                        ? EstadoDocumento::Vencido
                        : EstadoDocumento::Vigente,
                    'emitido_por' => $supervisor->id,
                    'created_at' => $emision,
                    'updated_at' => $emision,
                ]);

                $tramite->update([
                    'aprobado_por' => $supervisor->id,
                    'fecha_aprobacion' => $creado->copy()->addHours(6),
                    'fecha_emision' => $emision,
                    'fecha_entrega' => $estado === EstadoTramite::Entregado ? $emision->copy()->addDay() : null,
                    'entregado_por' => $estado === EstadoTramite::Entregado ? $operador->id : null,
                    'modo_entrega' => $estado === EstadoTramite::Entregado ? 'fisica' : null,
                ]);
            }
        }

    }

    /**
     * Un código de verificación con la misma forma que los de verdad.
     *
     * Sale del alfabeto y el largo que usa EmisionDocumentoService, y no de
     * `Str::random()`, por dos motivos: los códigos reales no llevan I, O, 0
     * ni 1 —se dictan por teléfono—, y el formulario público exige el largo
     * exacto. Con códigos de prueba de otra forma, probar la pantalla pública
     * con datos sembrados daría «el código no tiene 16 caracteres».
     */
    private function codigoDePrueba(): string
    {
        $alfabeto = EmisionDocumentoService::ALFABETO;
        $codigo = '';

        for ($i = 0; $i < EmisionDocumentoService::LARGO_CODIGO; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $codigo;
    }

    /**
     * Distribución realista: la mayoría de los trámites llegan a entregado.
     */
    private function estadoPonderado(): EstadoTramite
    {
        return match (random_int(1, 100)) {
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 => EstadoTramite::EnRevision,
            16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30 => EstadoTramite::Aprobado,
            31, 32, 33, 34, 35 => EstadoTramite::Rechazado,
            default => EstadoTramite::Entregado,
        };
    }
}
