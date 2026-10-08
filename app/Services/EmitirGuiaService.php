<?php

namespace App\Services;

use App\Enums\CondicionProducto;
use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\GuiaDetalle;
use App\Models\GuiaMovimiento;
use App\Models\ProductoHidrobiologico;
use App\Sireb\PrecioSireb;
use App\Sireb\SinPrecioException;
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

    public function __construct(
        private readonly CorrelativoService $correlativos,
        private readonly PrecioSireb $precios,
        private readonly LiquidarSirebService $liquidaciones,
    ) {}

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

        $guia = DB::transaction(function () use ($carnet, $datos, $renglones, $solicitud): GuiaMovimiento {
            $guia = GuiaMovimiento::create([
                // La guía cuelga del CARNET: la persona se lee de él.
                'carnet_id' => $carnet->id,

                /*
                 * La asociación se copia del carnet, no se pregunta de nuevo.
                 */
                'asociacion_id' => $carnet->asociacion_id,

                // El número lo pone el sistema, no el operador: correlativo
                // global y continuo, como el talonario de papel.
                'nro' => $this->correlativos->siguienteContinuo(GuiaMovimiento::SERIE),

                ...$this->renglonesDelPapel($datos),

                'transporte_capacidad_kg' => $this->numeroONull($datos['transporte_capacidad_kg'] ?? null),
                'es_piscicultura' => (bool) ($datos['es_piscicultura'] ?? false),
                'peso_total_kg' => $this->kilosDe($renglones),

                // PENDIENTE, como el carnet y el cupo: la emisión y el
                // vencimiento los escribe la aprobación.
                'estado' => EstadoGuia::Pendiente,
                'fecha_solicitud' => $solicitud->toDateString(),
            ]);

            // Lo que se cobra es el total del cuadro D, con el descuento de
            // piscicultura. Después del create(): el factor lee la columna.
            $guia->update(['monto' => $guia->arancelCalculado($this->importeDe($renglones))]);

            $this->guardarDetalle($guia, $renglones);

            // Su llave pública, en la misma transacción: sin código no se
            // puede verificar.
            $guia->asignarCodigo();
            $this->liquidaciones->preparar($guia);

            return $guia->refresh();
        });

        $this->liquidaciones->enviarSinFrenar($guia);

        return $guia;
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
        // Un producto que la guía ya tenía se conserva aunque hoy esté fuera de uso.
        $renglones = $this->normalizarDetalle($detalles, array_map('intval', $guia->detalles()->pluck('producto_id')->all()));

        if ($renglones === []) {
            throw PermisoOperativoException::guiaSinDetalle();
        }

        if (! $guia->puedeEditarse()) {
            throw PermisoOperativoException::guiaNoSePuedeEditar(mb_strtolower($guia->estado->etiqueta()));
        }

        // Otra carga es otro cobro: la liquidación vieja se anula antes de tocar nada.
        $otraLiquidacion = $this->liquidaciones->anularSiCambia(
            $guia,
            array_map(fn (array $r): array => ['tarifa_id' => $r['sireb_tarifa_id'], 'cantidad' => (float) $r['cantidad_kg']], $renglones),
            $guia->arancelCalculado($this->importeDe($renglones)),
        );

        $guia = DB::transaction(function () use ($guia, $datos, $renglones, $otraLiquidacion): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            // Con la copia bloqueada: entre que la pantalla se dibujó y llegó el
            // submit, el pago pudo aprobarla.
            if (! $bloqueada->estado->permiteEdicion()) {
                throw PermisoOperativoException::guiaNoSePuedeEditar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
            }

            $bloqueada->update([
                ...$this->renglonesDelPapel($datos),
                'transporte_capacidad_kg' => $this->numeroONull($datos['transporte_capacidad_kg'] ?? null),
                'es_piscicultura' => (bool) ($datos['es_piscicultura'] ?? false),
                'peso_total_kg' => $this->kilosDe($renglones),
            ]);

            // El monto se vuelve a calcular: cambiaron la carga o la piscicultura.
            $bloqueada->refresh();
            $bloqueada->update([
                'monto' => $bloqueada->arancelCalculado($this->importeDe($renglones)),
            ]);

            // El detalle se reemplaza ENTERO: casar fila por fila sin un id
            // estable del papel inventa una identidad que el talonario no tiene.
            $bloqueada->detalles()->delete();
            $this->guardarDetalle($bloqueada, $renglones);

            if ($otraLiquidacion) {
                $this->liquidaciones->preparar($bloqueada);
            }

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $guia->refresh();
        });

        $this->liquidaciones->enviarSinFrenar($guia);

        return $guia;
    }

    /**
     *  Eliminar una guía cargada por error
     */
    public function eliminar(GuiaMovimiento $guia, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        if (! $guia->puedeEliminarse()) {
            throw PermisoOperativoException::guiaNoSePuedeEliminar(mb_strtolower($guia->estado->etiqueta()));
        }

        // Primero SIREB: si no anula la liquidación, no se elimina.
        $this->liquidaciones->anular($guia, 'Eliminado en Jichi: '.$motivo);

        DB::transaction(function () use ($guia, $motivo): void {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueada->estado->permiteEliminacion()) {
                throw PermisoOperativoException::guiaNoSePuedeEliminar(
                    mb_strtolower($bloqueada->estado->etiqueta()),
                );
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
     * Da de baja una guía, con motivo.
     */
    public function revocar(GuiaMovimiento $guia, string $motivo): GuiaMovimiento
    {
        if (trim($motivo) === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        if ($guia->estado === EstadoGuia::Revocada) {
            throw PermisoOperativoException::guiaYaRevocada();
        }

        if (! $guia->estado->permiteRevocacion()) {
            throw PermisoOperativoException::guiaNoSeRevoca(mb_strtolower($guia->estado->etiqueta()));
        }

        return DB::transaction(function () use ($guia, $motivo): GuiaMovimiento {
            $bloqueada = GuiaMovimiento::query()->whereKey($guia->id)->lockForUpdate()->firstOrFail();

            // Otra ventanilla pudo revocarla mientras tanto.
            if (! $bloqueada->estado->permiteRevocacion()) {
                throw PermisoOperativoException::guiaNoSeRevoca(mb_strtolower($bloqueada->estado->etiqueta()));
            }

            // El motivo se deja ANTES de guardar: el trait Auditable lo lee en el
            // evento `updated`. Sin él la auditoría diría QUÉ cambió pero no POR
            // QUÉ, que en un papel revocado es lo único que sirve después.
            $bloqueada->motivoAuditoria = $motivo;
            $bloqueada->update(['estado' => EstadoGuia::Revocada]);

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
     * El cuadro D con el nombre del CATÁLOGO y el precio de SIREB, no del
     * formulario: el operador elige el producto y el sistema copia lo de hoy.
     * Corre fuera de la transacción: la llamada a SIREB no debe tener filas
     * bloqueadas. Se descartan los renglones sin producto.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @param  array<int, int>  $yaUsados  Productos que la guía ya tenía (al corregir)
     * @return array<int, array<string, mixed>>
     */
    private function normalizarDetalle(array $detalles, array $yaUsados = []): array
    {
        $filas = collect($detalles)->filter(fn (array $d): bool => ! empty($d['producto_id']));

        $productos = ProductoHidrobiologico::query()
            ->whereKey($filas->pluck('producto_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return $filas
            ->map(function (array $d) use ($productos, $yaUsados): array {
                $producto = $productos->get((int) $d['producto_id']);

                if ($producto === null || (! $producto->estado && ! in_array($producto->id, $yaUsados, true))) {
                    throw PermisoOperativoException::productoNoDisponible($producto?->nombre ?? '#'.$d['producto_id']);
                }

                $sireb = $this->precioDelProducto($producto);
                $cantidad = round((float) ($d['cantidad_kg'] ?? 0), 2);
                $precio = round($sireb['monto'], 2);

                return [
                    'producto_id' => $producto->id,
                    'especie' => $producto->nombre,
                    'condicion' => $d['condicion'] instanceof CondicionProducto
                        ? $d['condicion']
                        : CondicionProducto::from((string) $d['condicion']),
                    'cantidad_kg' => $cantidad,
                    'precio_kg' => $precio,
                    'sireb_tarifa_id' => $sireb['tarifa_id'],
                    'importe_total' => GuiaDetalle::importeDe($cantidad, $precio),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * El precio por kilo del producto según SIREB, el de ahora.
     *
     * @return array{monto: float, tarifa_id: string}
     */
    public function precioDelProducto(ProductoHidrobiologico $producto): array
    {
        try {
            return $this->precios->de($producto->servicio_sireb, $producto->tarifa_sireb);
        } catch (SinPrecioException $e) {
            throw PermisoOperativoException::productoSinPrecio($producto->nombre, $e->getMessage());
        }
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
     * El total del cuadro D: es lo que se cobra por la guía.
     *
     * @param  array<int, array<string, mixed>>  $renglones
     */
    private function importeDe(array $renglones): float
    {
        return round(array_sum(array_column($renglones, 'importe_total')), 2);
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
