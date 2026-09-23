<?php

namespace App\Services;

use App\Enums\CondicionProducto;
use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paso 4 del flujo, rama comercializador. El circuito de revisión vive en
 * `RevisarGuiaService`.
 */
class EmitirGuiaService
{
    /** Los renglones del papel que el formulario manda tal cual. */
    private const RENGLONES = [
        'origen', 'origen_departamento', 'origen_provincia', 'origen_distrito',
        'destino', 'destino_departamento', 'destino_provincia', 'destino_distrito',
        'medio_transporte', 'tipo_transporte', 'transporte_nombre', 'transporte_placa',
        'observaciones',
    ];

    public function __construct(private readonly CorrelativoService $correlativos) {}

    /**
     * Nace PENDIENTE: no ampara nada hasta que se cobre y alguien la firme.
     *
     * @param  array<string, mixed>  $datos  Los renglones del talonario.
     * @param  array<int, array<string, mixed>>  $detalles  El cuadro D.
     */
    public function emitir(
        Carnet $carnet,
        array $datos,
        array $detalles,
        ?Carbon $solicitud = null,
    ): GuiaMovimiento {
        $solicitud ??= now();

        // El carnet se comprueba ANTES de abrir la transacción: no se revoca
        // en el medio, y abrirla para nada sostiene una conexión.
        if (! $carnet->tipo_actor->emiteGuias()) {
            throw PermisoOperativoException::actorNoEmite('guías de movimiento', $carnet->tipo_actor);
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente($carnet->estado);
        }

        $renglones = $this->normalizarDetalle($detalles);

        if ($renglones === []) {
            throw PermisoOperativoException::guiaSinDetalle();
        }

        return DB::transaction(function () use ($carnet, $datos, $renglones, $solicitud): GuiaMovimiento {
            $guia = GuiaMovimiento::create([
                // La guía cuelga del CARNET: la persona se lee de él.
                'carnet_id' => $carnet->id,

                /*
                 * LA ASOCIACIÓN SE COPIA DEL CARNET, no se pregunta de nuevo.
                 */
                'asociacion_id' => $carnet->asociacion_id,

                // EL NÚMERO LO PONE EL SISTEMA, no el operador: correlativo
                // global y continuo, como el talonario de papel.
                'numero_guia' => $this->correlativos->siguienteContinuo(GuiaMovimiento::SERIE),

                ...$this->renglonesDelPapel($datos),

                'transporte_capacidad_kg' => $this->numeroONull($datos['transporte_capacidad_kg'] ?? null),
                'es_piscicultura' => (bool) ($datos['es_piscicultura'] ?? false),
                'peso_total_kg' => $this->kilosDe($renglones),

                // PENDIENTE, como el carnet y el cupo: la emisión y el
                // vencimiento los escribe la aprobación.
                'estado' => EstadoGuia::Pendiente,
                'fecha_solicitud' => $solicitud->toDateString(),
            ]);

            // Después del create(): el factor de piscicultura lo calcula el
            // modelo leyendo su columna, que recién existe con la fila escrita.
            $guia->update(['monto' => $guia->arancelCalculado(GuiaMovimiento::tarifaVigente())]);

            $this->guardarDetalle($guia, $renglones);

            // Su llave pública, en la misma transacción: sin código no se
            // puede verificar.
            $guia->asignarCodigo();

            return $guia->refresh();
        });
    }

    /**
     * Corrige el borrador. El CARNET no se toca: cambiar de titular no es
     * corregir un traslado, es emitir otro.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public function editar(GuiaMovimiento $guia, array $datos, array $detalles): GuiaMovimiento
    {
        $renglones = $this->normalizarDetalle($detalles);

        if ($renglones === []) {
            throw PermisoOperativoException::guiaSinDetalle();
        }

        return DB::transaction(function () use ($guia, $datos, $renglones): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            // Se comprueba con la copia bloqueada, no con la que llegó: entre
            // que la pantalla se dibujó y llegó el submit, otra ventanilla
            // pudo enviarla a revisión.
            if (! $bloqueada->estado->permiteEdicion()) {
                throw PermisoOperativoException::guiaNoSePuedeEditar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            if (($pagos = $bloqueada->pagos()->count()) > 0) {
                throw PermisoOperativoException::guiaTienePagos($pagos);
            }

            $bloqueada->update([
                ...$this->renglonesDelPapel($datos),
                'transporte_capacidad_kg' => $this->numeroONull($datos['transporte_capacidad_kg'] ?? null),
                'es_piscicultura' => (bool) ($datos['es_piscicultura'] ?? false),
                'peso_total_kg' => $this->kilosDe($renglones),
            ]);

            // El arancel se vuelve a copiar: la piscicultura pudo cambiar, y
            // con ella la mitad del monto.
            $bloqueada->refresh();
            $bloqueada->update([
                'monto' => $bloqueada->arancelCalculado(GuiaMovimiento::tarifaVigente()),
            ]);

            // El detalle se reemplaza ENTERO: casar fila por fila sin un id
            // estable del papel inventa una identidad que el talonario no tiene.
            $bloqueada->detalles()->delete();
            $this->guardarDetalle($bloqueada, $renglones);

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $guia->refresh();
        });
    }

    /**
     *  ELIMINAR UNA GUÍA CARGADA POR ERROR
     */
    public function eliminar(GuiaMovimiento $guia, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        DB::transaction(function () use ($guia, $motivo): void {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEliminacion()) {
                throw PermisoOperativoException::guiaNoSePuedeEliminar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            if (($pagos = $bloqueada->pagos()->count()) > 0) {
                throw PermisoOperativoException::guiaTienePagos($pagos);
            }

            // A MANO: la FK es CASCADE, pero eso es del MOTOR y `delete()` con
            // SoftDeletes es un UPDATE. Sin esto quedan renglones huérfanos.
            $bloqueada->detalles()->delete();

            // El motivo se deja y se borra: `Auditable` ya engancha el `deleted`,
            // y registrarlo a mano además dejaría el hecho dos veces.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->delete();

            // El número NO se reusa: la serie queda con un hueco, que es lo que
            // el motivo en la auditoría explica.
        });
    }

    /**
     * Registra que la carga llegó a destino.
     */
    public function cerrar(GuiaMovimiento $guia, ?float $pesoReal = null): GuiaMovimiento
    {
        if ($guia->estado !== EstadoGuia::Activa) {
            throw PermisoOperativoException::noSePuedeCerrar(mb_strtolower($guia->estado->etiqueta()));
        }

        return DB::transaction(function () use ($guia, $pesoReal): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            $cambios = ['estado' => EstadoGuia::Cerrada];

            if ($pesoReal !== null && abs($pesoReal - (float) $bloqueada->peso_total_kg) > 0.001) {
                $cambios['peso_total_kg'] = $pesoReal;
            }

            $bloqueada->update($cambios);

            // Se devuelve la instancia ORIGINAL refrescada: quien llamó tiene
            // esa en la mano, y darle la copia bloqueada lo deja con el estado
            // viejo en memoria.
            return $guia->refresh();
        });
    }

    /**
     * Da de baja una guía, con motivo.
     */
    public function anular(GuiaMovimiento $guia, string $motivo): GuiaMovimiento
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        if ($guia->estado === EstadoGuia::Anulada) {
            throw PermisoOperativoException::guiaYaAnulada();
        }

        if ($guia->estado === EstadoGuia::Cerrada) {
            throw PermisoOperativoException::cerradaNoSeAnula();
        }

        return DB::transaction(function () use ($guia, $motivo): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            // El motivo se deja ANTES de guardar: el trait Auditable lo lee en el
            // evento `updated`. Sin él la auditoría diría QUÉ cambió pero no POR
            // QUÉ, que en un papel anulado es lo único que sirve después.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoGuia::Anulada]);

            return $guia->refresh();
        });
    }

    //  Auxiliares

    /**
     * Los renglones del talonario, normalizados: '' entra como null.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, string|null>
     */
    private function renglonesDelPapel(array $datos): array
    {
        return collect(self::RENGLONES)
            ->mapWithKeys(fn (string $c): array => [$c => trim((string) ($datos[$c] ?? '')) ?: null])
            ->all();
    }

    /**
     * El cuadro D, limpio: se descartan los renglones sin especie, que son los
     * que el operador no llenó de las cinco filas fijas del papel.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array<string, mixed>>
     */
    private function normalizarDetalle(array $detalles): array
    {
        return collect($detalles)
            ->filter(fn (array $d): bool => trim((string) ($d['especie'] ?? '')) !== '')
            ->map(function (array $d): array {
                $cantidad = round((float) ($d['cantidad_kg'] ?? 0), 2);
                $precio = round((float) ($d['precio_kg'] ?? 0), 2);

                return [
                    'especie' => trim((string) $d['especie']),
                    'condicion' => $d['condicion'] instanceof CondicionProducto
                        ? $d['condicion']
                        : CondicionProducto::from((string) $d['condicion']),
                    'cantidad_kg' => $cantidad,
                    'precio_kg' => $precio,
                    // Se acepta el importe declarado si vino; si no, se
                    // multiplica. Ver GuiaDetalle::importeDe().
                    'importe_total' => isset($d['importe_total']) && $d['importe_total'] !== ''
                        ? round((float) $d['importe_total'], 2)
                        : GuiaDetalle::importeDe($cantidad, $precio),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $renglones
     */
    private function guardarDetalle(GuiaMovimiento $guia, array $renglones): void
    {
        foreach ($renglones as $renglon) {
            // `create()` y no `insert()`: el trait Auditable engancha eventos
            // de Eloquent, y una inserción masiva no dispara ninguno.
            $guia->detalles()->create($renglon);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $renglones
     */
    private function kilosDe(array $renglones): float
    {
        return round(array_sum(array_column($renglones, 'cantidad_kg')), 2);
    }

    private function numeroONull(mixed $valor): ?float
    {
        return $valor === null || $valor === '' ? null : round((float) $valor, 2);
    }
}
