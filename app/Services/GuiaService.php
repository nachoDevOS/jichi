<?php

namespace App\Services;

use App\Enums\EstadoPermiso;
use App\Exceptions\PermisoOperativoException;
use App\Models\Carnet;
use App\Models\Guia;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EMISIÓN Y BAJA DE GUÍAS ÚNICAS DE TRANSPORTE
 * ============================================================================
 *
 * Igual que `FaenaService` pero con una diferencia que ordena todo el archivo:
 * LA GUÍA NO ES UNA FILA, SON DOS TABLAS. La cabecera dice quién traslada, desde
 * dónde y en qué; el detalle dice qué lleva, especie por especie.
 *
 * Por eso las dos se escriben SIEMPRE dentro de la misma transacción: una guía a
 * la que le falten las líneas no ampara nada y no se puede distinguir de una
 * cargada a medias.
 */
class GuiaService
{
    /**
     * ========================================================================
     *  EMITIR UNA GUÍA CON SU CARGA
     * ========================================================================
     *
     * @param  array<string, mixed>  $datos  La cabecera
     * @param  array<int, array<string, mixed>>  $detalles  Las líneas de carga
     */
    public function emitir(Carnet $carnet, array $datos, array $detalles): Guia
    {
        $this->comprobarCarnet($carnet);

        $numero = trim((string) ($datos['nro_guia'] ?? ''));

        // Antes de insertar, no después: en PostgreSQL un INSERT fallido aborta
        // la transacción entera. Ver la nota de FaenaService::emitir().
        if (Guia::query()->where('nro_guia', $numero)->exists()) {
            throw PermisoOperativoException::numeroRepetido('guía', $numero);
        }

        $detalles = $this->limpiarDetalles($detalles);

        if ($detalles === []) {
            throw PermisoOperativoException::guiaSinDetalle();
        }

        $this->comprobarCapacidad($detalles, $datos['capacidad_maxima'] ?? null);

        return DB::transaction(function () use ($carnet, $datos, $numero, $detalles): Guia {
            $guia = $carnet->guias()->create([
                'nro_guia' => $numero,
                'nro_recibo' => $datos['nro_recibo'] ?? null,
                'origen_lugar' => $datos['origen_lugar'] ?? null,
                'origen_depto' => $datos['origen_depto'] ?? null,
                'origen_provincia' => $datos['origen_provincia'] ?? null,
                'origen_distrito' => $datos['origen_distrito'] ?? null,
                'destino_lugar' => $datos['destino_lugar'] ?? null,
                'destino_depto' => $datos['destino_depto'] ?? null,
                'destino_provincia' => $datos['destino_provincia'] ?? null,
                'destino_distrito' => $datos['destino_distrito'] ?? null,
                'tipo_transporte' => $datos['tipo_transporte'],
                'transporte_nombre' => $datos['transporte_nombre'] ?? null,
                'transporte_placa' => $datos['transporte_placa'] ?? null,
                'capacidad_maxima' => $datos['capacidad_maxima'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            // createMany y no un create por vuelta: es un solo INSERT con varias
            // filas, y sobre todo es una sola cosa que puede fallar.
            $guia->detalles()->createMany($detalles);

            return $guia->load('detalles');
        });
    }

    /**
     * ========================================================================
     *  REEMPLAZAR LA CARGA DE UNA GUÍA
     * ========================================================================
     *
     * SE BORRA TODO Y SE VUELVE A INSERTAR, en vez de comparar línea por línea
     * para decidir qué agregar, qué cambiar y qué sacar.
     *
     * Parece bruto y es lo correcto acá: la grilla del formulario manda la lista
     * completa, no un diff, así que reconstruirla es exactamente lo que el
     * operador pidió. Comparar exigiría mandar los ids de cada línea desde el
     * navegador —y confiar en ellos—, y el único beneficio sería conservar unos
     * ids que no significan nada para nadie.
     *
     * Es además la razón por la que `guia_detalles` es la única tabla del módulo
     * que se borra en cascada: el borrado es parte de su uso normal.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     */
    public function reemplazarDetalle(Guia $guia, array $detalles): Guia
    {
        if ($guia->estado === EstadoPermiso::Anulado) {
            throw PermisoOperativoException::yaAnulado('guía');
        }

        $detalles = $this->limpiarDetalles($detalles);

        if ($detalles === []) {
            throw PermisoOperativoException::guiaSinDetalle();
        }

        $this->comprobarCapacidad($detalles, $guia->capacidad_maxima);

        return DB::transaction(function () use ($guia, $detalles): Guia {
            $guia->detalles()->delete();
            $guia->detalles()->createMany($detalles);

            return $guia->load('detalles');
        });
    }

    /**
     * ========================================================================
     *  ANULAR UNA GUÍA
     * ========================================================================
     *
     * No se borra, por lo mismo que la faena: el número del talonario ya se
     * gastó y el papel puede estar viajando con la carga. El motivo es
     * obligatorio porque es lo único que explica después por qué no vale.
     *
     * EL DETALLE NO SE TOCA. Podría borrarse —total la guía ya no ampara nada—
     * pero es justo lo que hay que poder mirar cuando alguien reclama: qué
     * decía llevar ese papel el día que se anuló.
     */
    public function anular(Guia $guia, ?string $motivo): Guia
    {
        if ($guia->estado === EstadoPermiso::Anulado) {
            throw PermisoOperativoException::yaAnulado('guía');
        }

        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            throw PermisoOperativoException::motivoObligatorio();
        }

        return DB::transaction(function () use ($guia, $motivo): Guia {
            $guia->update([
                'estado' => EstadoPermiso::Anulado,
                'observaciones' => trim("ANULADA: {$motivo}\n".(string) $guia->observaciones),
            ]);

            return $guia->refresh();
        });
    }

    /**
     * Descarta las líneas vacías y normaliza el resto.
     *
     * La grilla del formulario arranca con una fila en blanco y el operador
     * puede dejar otra a medio llenar al final. Sin este filtro se guardarían
     * como líneas con especie vacía y cero kilos, que en el papel impreso salen
     * como renglones fantasma.
     *
     * Una línea cuenta como cargada si tiene ESPECIE: es el único dato sin el
     * cual la fila no dice nada.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array<string, mixed>>
     */
    private function limpiarDetalles(array $detalles): array
    {
        $limpias = [];

        foreach ($detalles as $fila) {
            $especie = trim((string) ($fila['especie'] ?? ''));

            if ($especie === '') {
                continue;
            }

            $limpias[] = [
                'especie' => $especie,
                'condicion' => $fila['condicion'],
                'cantidad_kg' => (float) ($fila['cantidad_kg'] ?? 0),
                // Los dos van a NULL cuando vienen vacíos y no a cero: cero
                // significaría «vale nada», y vacío significa «no se declaró».
                'precio_unitario' => $this->numeroONulo($fila['precio_unitario'] ?? null),
                'imponible' => $this->numeroONulo($fila['imponible'] ?? null),
            ];
        }

        return $limpias;
    }

    private function numeroONulo(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Que la carga declarada entre en el vehículo.
     *
     * NO es una restricción de la base, y no podría serlo: cruza dos tablas. Y
     * solo se comprueba cuando la capacidad está cargada — de una canoa nadie la
     * conoce, y exigirla frenaría la mitad de las guías del departamento.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     */
    private function comprobarCapacidad(array $detalles, mixed $capacidad): void
    {
        if ($capacidad === null || $capacidad === '') {
            return;
        }

        $declarado = array_sum(array_column($detalles, 'cantidad_kg'));

        if ($declarado > (float) $capacidad) {
            throw PermisoOperativoException::excedeCapacidad($declarado, (float) $capacidad);
        }
    }

    /** Las dos condiciones del carnet. Ver FaenaService::comprobarCarnet(). */
    private function comprobarCarnet(Carnet $carnet): void
    {
        $carnet->loadMissing('rubro');

        if (! ($carnet->rubro?->emiteGuias() ?? false)) {
            throw PermisoOperativoException::rubroNoEmite('guías', $carnet->rubro?->nombre ?? 'sin rubro');
        }

        if (! $carnet->estaVigente()) {
            throw PermisoOperativoException::carnetNoVigente(
                $carnet->rubro?->nombre ?? 'sin rubro',
                (int) $carnet->gestion,
                $carnet->estado,
            );
        }
    }
}
