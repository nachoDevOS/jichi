<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\CategoriaAprovechamiento;
use App\Sireb\PrecioSireb;
use App\Sireb\SinPrecioException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 2 DEL FLUJO DEL PESCADOR — otorgar la BOLSA MADRE
 */
class OtorgarCupoService
{
    public function __construct(private readonly PrecioSireb $precios) {}

    /**
     * Otorga la bolsa madre a una persona.
     */
    public function otorgar(
        Beneficiario $beneficiario,
        CategoriaAprovechamiento $categoria,
        string $tipoEmbarcacion,
        ?Carbon $solicitud = null,
    ): AprovechamientoPesq {
        $solicitud ??= now();

        return DB::transaction(function () use ($beneficiario, $categoria, $solicitud, $tipoEmbarcacion): AprovechamientoPesq {
            // `lockForUpdate()` devuelve OTRA instancia: acá solo toma el candado,
            // todo lo demás se lee del modelo que llegó.
            Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            $vigente = $this->cupoVigenteDe($beneficiario);

            if ($vigente !== null) {
                throw CupoInvalidoException::yaTieneCupoVigente(
                    $beneficiario->nombreCompleto,
                    $vigente->saldoKg(),
                    $vigente->fecha_vencimiento->format('d/m/Y'),
                );
            }

            // El tramo se relee DESDE LA BASE: entre abrir el formulario y guardar
            // pueden pasar minutos, y alguien pudo derogar la escala.
            $tramo = CategoriaAprovechamiento::query()->whereKey($categoria->id)->firstOrFail();

            if (! $tramo->estado) {
                throw CupoInvalidoException::escalaDerogada($tramo->nro_escala);
            }

            $precio = $this->precioDe($tramo);

            $cupo = AprovechamientoPesq::create([
                'beneficiario_id' => $beneficiario->id,
                'categoria_aprov_id' => $tramo->id,
                'monto' => $precio['monto'],
                'sireb_tarifa_id' => $precio['tarifa_id'],

                /*
                 * El volumen sale del techo del tramo.
                 */
                'volumen_total_kg' => $tramo->kilos_max,

                // Obligatorio: va impreso en la autorización. Texto libre porque no
                // hay padrón de embarcaciones.
                'tipo_embarcacion' => $tipoEmbarcacion,

                // Se copia, por lo mismo que el volumen: reclasificar el tramo en el
                // catálogo no puede cambiarle el régimen a lo ya otorgado.
                'modalidad' => $tramo->modalidad,

                /*
                 * Nace pendiente, y de ahí sale solo al cobrarse.
                 */
                'estado' => EstadoAprovechamiento::Pendiente,

                // La fecha en que se PIDIÓ. La de otorgamiento la escribe la
                // aprobación: en borrador no hay nada otorgado.
                'fecha_solicitud' => $solicitud->toDateString(),
                'fecha_emision' => null,
                'fecha_vencimiento' => $this->vencimientoDe($solicitud),
            ]);

            // Su llave pública, en la misma transacción: sin código, el documento
            // no se puede verificar.
            $cupo->asignarCodigo();

            return $cupo;
        });
    }

    /**
     *  Corregir un cupo que todavía es borrador
     */
    public function editar(
        AprovechamientoPesq $cupo,
        CategoriaAprovechamiento $categoria,
        string $tipoEmbarcacion,
        Carbon $solicitud,
    ): AprovechamientoPesq {
        return DB::transaction(function () use ($cupo, $categoria, $solicitud, $tipoEmbarcacion): AprovechamientoPesq {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            /*
             * Se comprueba con la copia bloqueada, no con la que llegó.
             */
            if (! $bloqueado->puedeEditarse()) {
                throw CupoInvalidoException::noSePuedeEditar($bloqueado->estado->etiqueta());
            }

            $tramo = CategoriaAprovechamiento::query()->whereKey($categoria->id)->firstOrFail();

            if (! $tramo->estado) {
                throw CupoInvalidoException::escalaDerogada($tramo->nro_escala);
            }

            // Corregir el borrador vuelve a pedir el precio, como la guía.
            $precio = $this->precioDe($tramo);

            $bloqueado->update([
                'categoria_aprov_id' => $tramo->id,
                'monto' => $precio['monto'],
                'sireb_tarifa_id' => $precio['tarifa_id'],
                'modalidad' => $tramo->modalidad,
                'volumen_total_kg' => $tramo->kilos_max,
                'tipo_embarcacion' => $tipoEmbarcacion,
                'fecha_solicitud' => $solicitud->toDateString(),
                'fecha_vencimiento' => $this->vencimientoDe($solicitud),
            ]);

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $cupo->refresh();
        });
    }

    /**
     *  Eliminar un cupo cargado por error
     */
    public function eliminar(AprovechamientoPesq $cupo, string $motivo): void
    {
        DB::transaction(function () use ($cupo, $motivo): void {
            $bloqueado = AprovechamientoPesq::query()->whereKey($cupo->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEliminacion()) {
                throw CupoInvalidoException::noSePuedeEliminar($bloqueado->estado->etiqueta());
            }

            // Los dos mensajes son distintos a propósito: la salida no es la
            // misma. Lo cobrado se resuelve por caja; las faenas ya emitidas no
            // se resuelven de ninguna manera, el papel está afuera.
            if (($pagos = $bloqueado->pagos()->count()) > 0) {
                throw CupoInvalidoException::tienePagos($pagos);
            }

            if (($faenas = $bloqueado->faenas()->count()) > 0) {
                throw CupoInvalidoException::tieneFaenas($faenas);
            }

            /*
             * El motivo se deja en el modelo y se borra: no se llama a
             * `registrarAuditoria()` a mano.
             */
            $bloqueado->motivoAuditoria = $motivo;

            $bloqueado->delete();
        });
    }

    /**
     * Su bolsa madre utilizable hoy, o null.
     */
    private function cupoVigenteDe(Beneficiario $beneficiario): ?AprovechamientoPesq
    {
        return AprovechamientoPesq::query()
            ->deBeneficiario($beneficiario->id)
            /*
             * `enCurso()` y no `vigentes()`: un cupo PENDIENTE DE PAGO ocupa el
             * lugar igual. Con `vigentes()` —que solo mira los activos— alguien
             * podría otorgar cinco cupos seguidos sin pagar ninguno y quedarse
             * con el que más le convenga, que es exactamente lo que la regla de
             * una bolsa por persona viene a impedir.
             */
            ->enCurso()
            ->withSum('faenasQueConsumen', 'kilos_extraidos')
            ->withSum('faenasQueReservan', 'kilos_extraidos')
            ->latest('fecha_solicitud')
            ->first();
    }

    /**
     * Hasta cuándo vale un cupo de esta fecha: el cupo es de la GESTIÓN.
     */
    private function vencimientoDe(Carbon $fecha): string
    {
        return $fecha->copy()->endOfYear()->toDateString();
    }

    /**
     * El precio del tramo según SIREB, el de ahora. Sin él no se otorga:
     * ninguna tarifa se escribe a mano.
     *
     * @return array{monto: float, tarifa_id: string}
     */
    private function precioDe(CategoriaAprovechamiento $tramo): array
    {
        try {
            return $this->precios->de($tramo->servicio_sireb, $tramo->tarifa_sireb);
        } catch (SinPrecioException $e) {
            throw CupoInvalidoException::sinPrecio($tramo->descripcion_kg, $e->getMessage());
        }
    }
}
