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
        ?Carbon $emision = null,
        ?string $tipoEmbarcacion = null,
    ): AprovechamientoPesq {
        $emision ??= now();

        return DB::transaction(function () use ($beneficiario, $categoria, $emision, $tipoEmbarcacion): AprovechamientoPesq {
            /*
             * Releer con lockForUpdate() devuelve OTRA instancia del mismo
             * registro. No se escribe sobre ella ni se la devuelve: acá solo
             * sirve para tomar el candado, y todo lo demás se lee del modelo que
             * llegó por parámetro.
             */
            Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            $vigente = $this->cupoVigenteDe($beneficiario);

            if ($vigente !== null) {
                throw CupoInvalidoException::yaTieneCupoVigente(
                    $beneficiario->nombreCompleto,
                    $vigente->saldoKg(),
                    $vigente->fecha_vencimiento->format('d/m/Y'),
                );
            }

            /*
             * Se vuelve a leer el tramo DESDE LA BASE en vez de confiar en el
             * modelo que llegó: entre que el operador abrió el formulario y
             * apretó guardar pueden pasar minutos, y en el medio alguien pudo
             * derogar la escala desde el catálogo.
             */
            $tramo = CategoriaAprovechamiento::query()->whereKey($categoria->id)->firstOrFail();

            if (! $tramo->estado) {
                throw CupoInvalidoException::escalaDerogada($tramo->nro_escala);
            }

            return AprovechamientoPesq::create([
                'beneficiario_id' => $beneficiario->id,
                'categoria_aprov_id' => $tramo->id,

                /*
                 * EL VOLUMEN SALE DEL TECHO DEL TRAMO.
                 */
                'volumen_total_kg' => $tramo->kilos_max,

                /*
                 * LO QUE EL PESCADOR DECLARA QUE NAVEGA, tal como lo pide el
                 * renglón del talonario. Es texto libre porque no hay padrón de
                 * embarcaciones: se escribe «canoa», «peque-peque» o «bote»
                 * según con qué llegue, y un catálogo cerrado obligaría a dar de
                 * alta un tipo nuevo con la persona esperando en la ventanilla.
                 */
                'tipo_embarcacion' => $tipoEmbarcacion,

                /*
                 * LA MODALIDAD TAMBIÉN SE COPIA, y por el mismo motivo que el
                 * volumen: la fija la resolución al definir el tramo, y si
                 * alguien reclasifica ese tramo en el catálogo, los cupos ya
                 * otorgados no pueden cambiar de régimen retroactivamente.
                 */
                'modalidad' => $tramo->modalidad,

                /*
                 * NACE PENDIENTE, y de ahí sale solo al cobrarse.
                 */
                'estado' => EstadoAprovechamiento::Pendiente,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $this->vencimientoDe($emision),
            ]);
        });
    }

    /**
     *  CORREGIR UN CUPO QUE TODAVÍA ES BORRADOR
     */
    public function editar(
        AprovechamientoPesq $cupo,
        CategoriaAprovechamiento $categoria,
        Carbon $emision,
        ?string $tipoEmbarcacion = null,
    ): AprovechamientoPesq {
        return DB::transaction(function () use ($cupo, $categoria, $emision, $tipoEmbarcacion): AprovechamientoPesq {
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
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $this->vencimientoDe($emision),
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
            ->latest('fecha_emision')
            ->first();
    }

    /**
     * Hasta cuándo vale un cupo otorgado en esta fecha.
     */
    private function vencimientoDe(Carbon $emision): string
    {
        return $emision->copy()->endOfYear()->toDateString();
    }
}
