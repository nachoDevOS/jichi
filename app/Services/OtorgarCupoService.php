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
 * ============================================================================
 *  PASO 2 DEL FLUJO DEL PESCADOR — otorgar la BOLSA MADRE
 * ============================================================================
 *
 *     beneficiario + escala  ──▶  aprovechamiento (volumen en kg, con fecha)
 *
 * De acá sale todo lo demás del módulo de pesca: el cupo que se imprime en el
 * carnet y los kilos que descuentan las faenas.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTO ES UN SERVICIO Y NO CÓDIGO DEL CONTROLADOR
 * ----------------------------------------------------------------------------
 *
 * Porque el mismo caso de uso lo necesitan el formulario del panel, un comando
 * de consola —una carga masiva cuando sale la resolución— y cualquier prueba
 * que se escriba. Puesto en el controlador, los otros dos lo copian, y las
 * copias se quedan viejas.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE SE COPIA, SE CONGELA
 * ----------------------------------------------------------------------------
 *
 * `volumen_total_kg` se COPIA de la escala al otorgar. La escala cambia por
 * resolución, y un cupo otorgado en marzo bajo un tramo de 500 kg no puede
 * pasar a valer 800 en agosto porque alguien editó el catálogo. La
 * `categoria_aprov_id` queda solo como referencia de bajo qué tramo se otorgó.
 *
 * El VALOR en bolivianos NO se copia, y es a propósito: lo que se debe se
 * calcula contra la escala actual (`AprovechamientoPesq::montoACobrar()`), y lo
 * que ya se pagó vive en `pagos`, que no se recalcula nunca.
 */
class OtorgarCupoService
{
    /**
     * Otorga la bolsa madre a una persona.
     *
     * ------------------------------------------------------------------------
     *  LA FILA DEL BENEFICIARIO SE BLOQUEA, Y NO ES PARANOIA
     * ------------------------------------------------------------------------
     *
     * La regla «una bolsa vigente por persona» no la puede garantizar ningún
     * índice de la base: «vigente» depende de la fecha de hoy, y un índice
     * único no sabe de fechas. Así que la comprueba este método.
     *
     * Sin el bloqueo, dos ventanillas atendiendo a la misma persona al mismo
     * tiempo pasan las dos comprobaciones —ninguna ve el cupo de la otra, que
     * todavía no está escrito— y la persona termina con el doble de kilos.
     * Pasa poco, y cuando pasa no deja ningún rastro que lo explique.
     *
     * Se bloquea al BENEFICIARIO y no a los aprovechamientos porque es la fila
     * que existe seguro: no se puede bloquear una fila que todavía no se creó.
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
                 *
                 * La escala dice «201 kg Hasta 500 Kg»: lo que se autoriza es el
                 * máximo del rango, no un número que el operador elija adentro.
                 * Dejarlo elegir convertiría la escala en una sugerencia y
                 * abriría la puerta a cobrar el tramo 3 otorgando el volumen del 5.
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
                 *
                 * Uno otorgado bajo escala general sigue siéndolo aunque su
                 * tramo pase después a especie especial.
                 */
                'modalidad' => $tramo->modalidad,

                /*
                 * NACE PENDIENTE, y de ahí sale solo al cobrarse.
                 *
                 * Es lo que lo hace corregible: mientras no entró plata, el cupo
                 * es un borrador que el operador puede arreglar o borrar con el
                 * pescador todavía enfrente. Lo activa `CobrarService`.
                 */
                'estado' => EstadoAprovechamiento::Pendiente,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $this->vencimientoDe($emision),
            ]);
        });
    }

    /**
     * ========================================================================
     *  CORREGIR UN CUPO QUE TODAVÍA ES BORRADOR
     * ========================================================================
     *
     * Acá no se le está dando más volumen a nadie: se está arreglando una carga
     * equivocada antes de que exista ningún papel. El volumen y el monto se
     * vuelven a copiar del tramo nuevo, igual que al otorgar.
     *
     * Solo corre en PENDIENTE y sin pagos. Con un abono encima hay un recibo
     * numerado que dice qué se cobró: cambiar el tramo por detrás haría que el
     * papel entregado dejara de coincidir con la fila, y nadie lo notaría.
     *
     * NO se toca `fecha_vencimiento` recalculándola desde cero por las dudas:
     * se recalcula solo si cambió la fecha de emisión, que es de donde sale.
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
             *
             * Entre que el operador abrió el formulario y apretó guardar, otra
             * ventanilla pudo cobrar este mismo cupo. Preguntándole al modelo en
             * memoria, la edición pasaría sobre un cupo ya pagado.
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
     * ========================================================================
     *  ELIMINAR UN CUPO CARGADO POR ERROR
     * ========================================================================
     *
     * ES UNA BAJA LÓGICA: la fila queda con `deleted_at` y desaparece de todas
     * las consultas por el scope global de SoftDeletes. Eso incluye la regla de
     * «una bolsa vigente por persona», que por lo tanto NO va a bloquear a nadie
     * por un cupo dado de baja — que es lo único que había que cuidar acá.
     *
     * Se conserva y no se borra de verdad porque el cupo lleva el nombre de una
     * persona y un volumen autorizado: aunque no haya llegado a cobrarse, que
     * alguien haya cargado 2000 kg a nombre de Fulano y lo haya dado de baja
     * cinco minutos después es exactamente el tipo de cosa que después hay que
     * poder mirar.
     *
     * EL MOTIVO va en `motivoAuditoria`, que el trait Auditable lee dentro del
     * evento `deleted` — el mismo que dispara la baja lógica.
     *
     * Las tres condiciones se comprueban con la fila bloqueada por lo mismo que
     * en `editar()`: entre el clic y el borrado, otra ventanilla pudo cobrar el
     * cupo o emitirle una faena.
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
             *
             * El trait Auditable ya engancha el evento `deleted` y escribe la
             * fila con los valores que tenía la fila. Registrándola además acá
             * salían DOS auditorías del mismo borrado —la mía con el motivo y la
             * automática sin él—, y quien leyera el historial vería el hecho
             * duplicado, una de las dos veces sin explicación.
             *
             * `$motivoAuditoria` es justamente el canal para esto: el trait lo
             * lee dentro del evento.
             */
            $bloqueado->motivoAuditoria = $motivo;

            $bloqueado->delete();
        });
    }

    /**
     * Su bolsa madre utilizable hoy, o null.
     *
     * Se consulta SIEMPRE contra la base y no se reutiliza la relación cargada:
     * este método corre dentro del candado, y todo el punto es ver lo último que
     * hay escrito, incluido lo que otra ventanilla acaba de crear.
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
     *
     * ------------------------------------------------------------------------
     *  VENCE CON LA GESTIÓN, NO AL AÑO DE OTORGADO
     * ------------------------------------------------------------------------
     *
     * Un cupo otorgado en octubre vence el 31 de diciembre, no el octubre
     * siguiente. Es lo que hace que el volumen sin usar SE PIERDA al cerrar el
     * año en vez de arrastrarse, y lo que permite que la unidad cuente cuántos
     * kilos autorizó en una gestión sin tener que prorratear.
     *
     * Se guarda la fecha calculada en la fila en vez de derivarla al leer: si
     * mañana una resolución cambia el criterio, los cupos ya otorgados tienen
     * que seguir venciendo cuando dice el papel que la persona tiene en la mano.
     */
    private function vencimientoDe(Carbon $emision): string
    {
        return $emision->copy()->endOfYear()->toDateString();
    }
}
