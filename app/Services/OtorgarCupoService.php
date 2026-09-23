<?php

namespace App\Services;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\CupoInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\CategoriaAprovechamiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 *  PASO 2 DEL FLUJO DEL PESCADOR — otorgar la BOLSA MADRE
 */
class OtorgarCupoService
{
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

            $cupo = AprovechamientoPesq::create([
                'beneficiario_id' => $beneficiario->id,
                'categoria_aprov_id' => $tramo->id,

                /*
                 * EL VOLUMEN SALE DEL TECHO DEL TRAMO.
                 */
                'volumen_total_kg' => $tramo->kilos_max,

                // Obligatorio: va impreso en la autorización. Texto libre porque no
                // hay padrón de embarcaciones.
                'tipo_embarcacion' => $tipoEmbarcacion,

                // Se copia, por lo mismo que el volumen: reclasificar el tramo en el
                // catálogo no puede cambiarle el régimen a lo ya otorgado.
                'modalidad' => $tramo->modalidad,

                /*
                 * NACE PENDIENTE, y de ahí sale solo al cobrarse.
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
     *  CORREGIR UN CUPO QUE TODAVÍA ES BORRADOR
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
             * SE COMPRUEBA CON LA COPIA BLOQUEADA, no con la que llegó.
             */
            if (! $bloqueado->puedeEditarse()) {
                throw CupoInvalidoException::noSePuedeEditar($bloqueado->estado->etiqueta());
            }

            $tramo = CategoriaAprovechamiento::query()->whereKey($categoria->id)->firstOrFail();

            if (! $tramo->estado) {
                throw CupoInvalidoException::escalaDerogada($tramo->nro_escala);
            }

            $bloqueado->update([
                'categoria_aprov_id' => $tramo->id,
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
     *  ELIMINAR UN CUPO CARGADO POR ERROR
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
             * EL MOTIVO SE DEJA EN EL MODELO Y SE BORRA: no se llama a
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
}
