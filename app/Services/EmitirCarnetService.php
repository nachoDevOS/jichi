<?php

namespace App\Services;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Models\AprovechamientoPesq;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\TipoCarnet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  PASO 3 DEL FLUJO — emitir la CREDENCIAL
 * ============================================================================
 *
 *     beneficiario + asociación + tipo (+ cupo si es pescador) ──▶ carnet
 *
 * El carnet es la llave ANUAL. Con él solo no se sale a trabajar: de él cuelgan
 * los permisos operativos —faenas y guías— que autorizan cada día.
 *
 * ----------------------------------------------------------------------------
 *  LAS TRES REGLAS QUE VIVEN ACÁ
 * ----------------------------------------------------------------------------
 *
 *   1. Una credencial vigente POR ACTIVIDAD y por persona. Quien pesca y
 *      además comercializa tiene dos; lo que no puede tener son dos iguales.
 *
 *   2. Un carnet de PESCADOR exige una bolsa madre vigente. El plástico
 *      imprime el cupo, y sin cupo tampoco se pueden emitir faenas — que es
 *      para lo único que sirve ese carnet.
 *
 *   3. Un carnet de COMERCIALIZADOR no lleva cupo, y la columna queda en NULL.
 *      No es que «no se cargó»: la comercialización no se autoriza por volumen.
 *
 * Ninguna la puede garantizar la base: la primera depende de la fecha de hoy y
 * las otras dos son condicionales. Por eso van acá, con la fila del
 * beneficiario bloqueada.
 */
class EmitirCarnetService
{
    /**
     * El alfabeto del código impreso.
     *
     * ------------------------------------------------------------------------
     *  FALTAN 0, O, 1, I, L, 5 Y S A PROPÓSITO
     * ------------------------------------------------------------------------
     *
     * El código se lee de un plástico gastado, a veces se dicta por teléfono y
     * se tipea a mano en la verificación pública. Esos siete caracteres son los
     * que se confunden entre sí en cualquier tipografía, y una sola letra mal
     * leída devuelve «no existe» — que en el muelle se lee como «carnet falso».
     *
     * Sacarlos cuesta poco: quedan 29 símbolos, y con siete posiciones al azar
     * son 17 billones de combinaciones.
     */
    private const ALFABETO = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';

    /** Cuántas posiciones al azar lleva el código, después del prefijo. */
    private const LARGO_ALEATORIO = 7;

    /**
     * Emite la credencial.
     *
     * ------------------------------------------------------------------------
     *  SE BLOQUEA AL BENEFICIARIO, IGUAL QUE AL OTORGAR EL CUPO
     * ------------------------------------------------------------------------
     *
     * Sin el candado, dos ventanillas atendiendo a la misma persona pasan las
     * dos comprobaciones —ninguna ve el carnet de la otra, que todavía no está
     * escrito— y la persona se va con dos plásticos de la misma actividad, cada
     * uno con su código válido. Después no hay forma de saber cuál vale.
     */
    public function emitir(
        Beneficiario $beneficiario,
        Asociacion $asociacion,
        TipoCarnet $tipo,
        TipoActor $actor,
        ?Carbon $emision = null,
    ): Carnet {
        $emision ??= now();

        return DB::transaction(function () use ($beneficiario, $asociacion, $tipo, $actor, $emision): Carnet {
            // Releer con lockForUpdate() devuelve OTRA instancia: acá solo sirve
            // para tomar el candado, no se escribe sobre ella.
            Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            $this->comprobarCatalogos($asociacion, $tipo);

            $vigente = $this->carnetVigenteDe($beneficiario, $actor);

            if ($vigente !== null) {
                throw CarnetInvalidoException::yaTieneCarnetVigente(
                    $beneficiario->nombreCompleto,
                    $actor,
                    $vigente->codigo_legible,
                    $vigente->fecha_vencimiento->format('d/m/Y'),
                );
            }

            /*
             * EL CUPO SOLO SE BUSCA SI EL ACTOR LO LLEVA, y quién lo lleva lo
             * dice el enum — NUNCA el nombre del tipo de carnet, que es un
             * catálogo que la unidad edita y donde el mismo documento figura
             * como «Carnet de Pescador» o «Pescador Artesanal».
             */
            $cupo = null;

            if ($actor->requiereAprovechamiento()) {
                $cupo = $this->cupoVigenteDe($beneficiario);

                if ($cupo === null) {
                    throw CarnetInvalidoException::pescadorSinCupo($beneficiario->nombreCompleto);
                }
            }

            return Carnet::create([
                'beneficiario_id' => $beneficiario->id,
                'asociacion_id' => $asociacion->id,
                'tipo_carnet_id' => $tipo->id,
                // NULL en un comercializador, y es la regla: la comercialización
                // no se autoriza por volumen.
                'aprovechamiento_id' => $cupo?->id,
                'tipo_actor' => $actor,
                'codigo_carnet' => $this->codigoUnico($actor, $emision),
                'estado' => EstadoCarnet::Activo,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);
        });
    }

    /**
     * Da de baja una credencial, con motivo.
     *
     * ------------------------------------------------------------------------
     *  NO SE BORRA NI SE REVIERTE
     * ------------------------------------------------------------------------
     *
     * El plástico está en la calle. Borrar la fila liberaría un código que el
     * índice único volvería a aceptar, así que dos credenciales distintas
     * podrían terminar diciendo ser la misma — y la verificación pública
     * respondería por la nueva mostrando el nombre de otra persona.
     *
     * Tampoco se «desrevoca»: si la persona vuelve a estar en regla, lo que
     * corresponde es emitirle una nueva, con su propio código. La vieja pudo
     * haber quedado en manos de cualquiera.
     */
    public function revocar(Carnet $carnet, string $motivo): Carnet
    {
        if (trim($motivo) === '') {
            throw CarnetInvalidoException::motivoObligatorio();
        }

        if ($carnet->estado === EstadoCarnet::Revocado) {
            throw CarnetInvalidoException::yaRevocado();
        }

        return DB::transaction(function () use ($carnet, $motivo): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            // El motivo se deja ANTES de guardar: el trait Auditable lo lee en el
            // evento `updated`. Sin él la auditoría diría QUÉ cambió pero no POR
            // QUÉ, que en una sanción es lo único que sirve después.
            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->update(['estado' => EstadoCarnet::Revocado]);

            // Se devuelve la instancia ORIGINAL refrescada: quien llamó tiene esa
            // en la mano, y darle la copia bloqueada lo deja con el estado viejo.
            return $carnet->refresh();
        });
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Los catálogos se releen DENTRO de la transacción.
     *
     * No es redundancia con el Request: entre que el operador abrió el
     * formulario y apretó guardar pueden pasar minutos, y en el medio alguien
     * pudo desactivar la asociación o el tipo desde el catálogo. El Request
     * mira el momento del envío; esto, el del guardado.
     */
    private function comprobarCatalogos(Asociacion $asociacion, TipoCarnet $tipo): void
    {
        if (! Asociacion::query()->whereKey($asociacion->id)->firstOrFail()->estaActiva()) {
            throw CarnetInvalidoException::catalogoInactivo('La asociación', $asociacion->nombre);
        }

        if (! TipoCarnet::query()->whereKey($tipo->id)->firstOrFail()->estado) {
            throw CarnetInvalidoException::catalogoInactivo('El tipo de carnet', $tipo->nombre);
        }
    }

    /**
     * Su credencial vigente de ESTA actividad, o null.
     *
     * Se consulta siempre contra la base y no se reutiliza ninguna relación
     * cargada: corre dentro del candado, y todo el punto es ver lo último
     * escrito, incluido lo que otra ventanilla acaba de crear.
     */
    private function carnetVigenteDe(Beneficiario $beneficiario, TipoActor $actor): ?Carnet
    {
        return Carnet::query()
            ->where('beneficiario_id', $beneficiario->id)
            ->deTipo($actor)
            ->vigentes()
            ->latest('fecha_emision')
            ->first();
    }

    /**
     * Su bolsa madre de esta gestión, o null.
     *
     * ------------------------------------------------------------------------
     *  ACEPTA UN CUPO PENDIENTE DE PAGO, Y TIENE QUE ACEPTARLO
     * ------------------------------------------------------------------------
     *
     * Lo único que el carnet necesita del cupo es el VOLUMEN que va impreso en
     * el plástico, y eso ya está decidido desde que se otorgó. El carnet también
     * nace sin pagar, y los dos se cobran juntos en el mismo recibo — así llega
     * la persona al mostrador—.
     *
     * Con `vigentes()`, que exige el cupo ya cobrado, el circuito quedaba
     * trabado: no se podía emitir la credencial hasta cobrar el cupo, y entonces
     * la caja nunca podía cobrar las dos cosas de una.
     */
    private function cupoVigenteDe(Beneficiario $beneficiario): ?AprovechamientoPesq
    {
        return AprovechamientoPesq::query()
            ->deBeneficiario($beneficiario->id)
            ->enCurso()
            ->latest('fecha_emision')
            ->first();
    }

    /**
     * ========================================================================
     *  EL CÓDIGO IMPRESO EN EL PLÁSTICO
     * ========================================================================
     *
     * Forma: PES + 26 + siete al azar  ->  «PES26K7RJ2M», que se muestra como
     * «PES2 6K7R J2M».
     *
     * ------------------------------------------------------------------------
     *  LLEVA UNA PARTE AL AZAR, Y NO ES ADORNO
     * ------------------------------------------------------------------------
     *
     * El código es la llave de la verificación pública, que es una pantalla SIN
     * SESIÓN. Un código correlativo —PES26-0001, 0002…— se recorre entero
     * probando de 1 en adelante, y cualquiera podría listar el padrón de
     * pescadores del año con un script.
     *
     * El prefijo y el año SÍ son predecibles, y está bien: sirven para que una
     * persona sepa de un vistazo qué credencial tiene en la mano. Lo que
     * protege son las siete posiciones al azar.
     *
     * ------------------------------------------------------------------------
     *  EL REINTENTO NO SOBRA
     * ------------------------------------------------------------------------
     *
     * La probabilidad de repetir es ínfima, pero «ínfima» no es «cero», y la
     * columna tiene un índice único: sin reintentar, esa colisión sería un error
     * de base de datos en la cara del operador, con alguien esperando el carnet.
     * Se prueba varias veces y recién ahí se rinde.
     */
    private function codigoUnico(TipoActor $actor, Carbon $emision): string
    {
        $prefijo = ($actor === TipoActor::Pescador ? 'PES' : 'COM').$emision->format('y');

        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = $prefijo.$this->azar(self::LARGO_ALEATORIO);

            if (! Carnet::query()->where('codigo_carnet', $codigo)->exists()) {
                return $codigo;
            }
        }

        // Diez colisiones seguidas no es mala suerte: es que algo está mal en el
        // generador. Falla ruidosamente en vez de entregar un código repetido.
        throw new \RuntimeException('No se pudo generar un código de carnet único después de 10 intentos.');
    }

    /** Una tira al azar del alfabeto sin caracteres confundibles. */
    private function azar(int $largo): string
    {
        $tira = '';

        for ($i = 0; $i < $largo; $i++) {
            // random_int y no rand(): es el generador criptográfico, y acá el
            // azar es lo único que impide recorrer el padrón entero.
            $tira .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $tira;
    }
}
