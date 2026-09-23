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
 *  PASO 3 DEL FLUJO — emitir la CREDENCIAL
 */
class EmitirCarnetService
{
    /**
     * Emite la credencial.
     */
    public function emitir(
        Beneficiario $beneficiario,
        Asociacion $asociacion,
        TipoCarnet $tipo,
        TipoActor $actor,
        ?Carbon $emision = null,
        ?AprovechamientoPesq $cupoElegido = null,
        // Llegan como RUTA, ya escritos ANTES de la transacción: un rollback
        // borra filas pero no deshace lo escrito en disco.
        ?string $archivoCi = null,
        ?string $archivoAsociacion = null,
    ): Carnet {
        $emision ??= now();

        return DB::transaction(function () use ($beneficiario, $asociacion, $tipo, $actor, $emision, $cupoElegido, $archivoCi, $archivoAsociacion): Carnet {
            // Releer con lockForUpdate() devuelve OTRA instancia: acá solo sirve
            // para tomar el candado, no se escribe sobre ella.
            Beneficiario::query()->whereKey($beneficiario->id)->lockForUpdate()->firstOrFail();

            $this->comprobarCatalogos($asociacion, $tipo);

            // Contra `tipos_carnet.tipo_actor` y NUNCA contra el nombre, que la
            // unidad edita: así se emitía un «Carnet Comercializador» marcado
            // como pescador.
            if ($tipo->tipo_actor !== $actor) {
                throw CarnetInvalidoException::tipoNoCorresponde($tipo->nombre, $tipo->tipo_actor, $actor);
            }

            // Va ANTES del control de duplicados: es un error de forma de la
            // petición, y decirlo primero evita mandar al operador a revocar un
            // carnet cuando lo que sobra es el cupo que eligió.
            if (! $actor->requiereAprovechamiento() && $cupoElegido !== null) {
                throw CarnetInvalidoException::comercializadorConCupo();
            }

            $vigente = $this->carnetVigenteDe($beneficiario, $actor);

            if ($vigente !== null) {
                throw CarnetInvalidoException::yaTieneCarnetVigente(
                    $beneficiario->nombreCompleto,
                    $actor,
                    $vigente->codigo_legible,
                    $vigente->fecha_vencimiento->format('d/m/Y'),
                );
            }

            // Quién lleva cupo lo dice el ENUM, nunca el nombre del tipo: el
            // mismo documento figura como «Carnet de Pescador» o «Pescador Artesanal».
            $cupo = null;

            if ($actor->requiereAprovechamiento()) {
                // Puede venir elegido o resolverse solo. Si vino, se comprueba que
                // sea SUYO: un id tipeado en el navegador colgaría el carnet de
                // una bolsa ajena.
                $cupo = $cupoElegido !== null
                    ? $this->cupoUtilizable($beneficiario, $cupoElegido)
                    : $this->cupoVigenteDe($beneficiario);

                if ($cupo === null) {
                    throw CarnetInvalidoException::pescadorSinCupo($beneficiario->nombreCompleto);
                }
            }

            $carnet = Carnet::create([
                'beneficiario_id' => $beneficiario->id,
                'asociacion_id' => $asociacion->id,
                'tipo_carnet_id' => $tipo->id,
                // NULL en un comercializador, y es la regla: la comercialización
                // no se autoriza por volumen.
                'aprovechamiento_id' => $cupo?->id,
                'tipo_actor' => $actor,

                // Los respaldos de la emisión.
                'archivo_ci' => $archivoCi,
                'archivo_asociacion' => $archivoAsociacion,

                // Nace PENDIENTE: el plástico no se entrega hasta que se cobre y
                // alguien firme.
                'estado' => EstadoCarnet::Pendiente,
                // La fecha en que se PIDIÓ. La de emisión la escribe la aprobación.
                'fecha_solicitud' => $emision->toDateString(),
                'fecha_emision' => null,
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),
            ]);

            // Su llave pública, en la misma transacción: sin código, el documento
            // no se puede verificar.
            $carnet->asignarCodigo();

            return $carnet;
        });
    }

    /**
     *  CORREGIR UN CARNET QUE TODAVÍA ES BORRADOR
     *
     * El titular NO se toca: si se equivocaron de persona, eso no es una
     * corrección —es otro carnet— y el código ya emitido quedaría a nombre de
     * alguien que nunca lo pidió. Lo mismo con la actividad, que viene atada
     * al código: «PES26…» no puede pasar a ser de un comercializador.
     */
    public function editar(
        Carnet $carnet,
        Asociacion $asociacion,
        TipoCarnet $tipo,
        Carbon $emision,
        ?AprovechamientoPesq $cupoElegido = null,
        ?string $archivoCi = null,
        ?string $archivoAsociacion = null,
    ): Carnet {
        return DB::transaction(function () use ($carnet, $asociacion, $tipo, $emision, $cupoElegido, $archivoCi, $archivoAsociacion): Carnet {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            // Con la copia BLOQUEADA: entre abrir el formulario y guardar, otra
            // ventanilla pudo cobrarlo. Las dos condiciones van separadas porque
            // `puedeEditarse()` a secas daba un mensaje que no explicaba nada.
            if (! $bloqueado->estado->permiteEdicion()) {
                throw CarnetInvalidoException::noSePuedeEditar($bloqueado->estado->etiqueta());
            }

            if ($bloqueado->montoPagado() > 0.0) {
                throw CarnetInvalidoException::tienePagos($bloqueado->pagos()->count());
            }

            $this->comprobarCatalogos($asociacion, $tipo);

            // El tipo tiene que seguir siendo de la MISMA actividad: el código
            // impreso lleva su prefijo, y cambiarla lo dejaría mintiendo.
            if ($tipo->tipo_actor !== $bloqueado->tipo_actor) {
                throw CarnetInvalidoException::tipoNoCorresponde(
                    $tipo->nombre,
                    $tipo->tipo_actor,
                    $bloqueado->tipo_actor,
                );
            }

            $cupo = null;

            if ($bloqueado->tipo_actor->requiereAprovechamiento()) {
                $cupo = $cupoElegido !== null
                    ? $this->cupoUtilizable($bloqueado->beneficiario, $cupoElegido)
                    : $this->cupoVigenteDe($bloqueado->beneficiario);

                if ($cupo === null) {
                    throw CarnetInvalidoException::pescadorSinCupo(
                        $bloqueado->beneficiario?->nombreCompleto ?? 'La persona',
                    );
                }
            } elseif ($cupoElegido !== null) {
                throw CarnetInvalidoException::comercializadorConCupo();
            }

            $bloqueado->update([
                'asociacion_id' => $asociacion->id,
                'tipo_carnet_id' => $tipo->id,
                'aprovechamiento_id' => $cupo?->id,
                'fecha_solicitud' => $emision->toDateString(),
                'fecha_vencimiento' => $emision->copy()->endOfYear()->toDateString(),

                // Los adjuntos solo se pisan si vinieron nuevos: el formulario
                // de corrección no obliga a volver a subir lo que ya está.
                ...($archivoCi !== null ? ['archivo_ci' => $archivoCi] : []),
                ...($archivoAsociacion !== null ? ['archivo_asociacion' => $archivoAsociacion] : []),
            ]);

            // La original refrescada, no la copia bloqueada. Ver CLAUDE.md.
            return $carnet->refresh();
        });
    }

    /**
     *  ELIMINAR UN CARNET CARGADO POR ERROR
     *
     * Baja LÓGICA: la fila queda con `deleted_at` y el motivo en `auditorias`.
     * El código NO se libera —el índice único es global— porque un carnet
     * eliminado pudo alcanzar a imprimirse.
     */
    public function eliminar(Carnet $carnet, string $motivo): void
    {
        if (trim($motivo) === '') {
            throw CarnetInvalidoException::motivoObligatorio();
        }

        DB::transaction(function () use ($carnet, $motivo): void {
            $bloqueado = Carnet::query()->whereKey($carnet->id)->lockForUpdate()->firstOrFail();

            if (! $bloqueado->estado->permiteEliminacion()) {
                throw CarnetInvalidoException::noSePuedeEliminar($bloqueado->estado->etiqueta());
            }

            // Los tres mensajes son distintos a propósito: la salida no es la
            // misma. Lo cobrado se resuelve por caja; un permiso ya emitido no
            // se resuelve de ninguna manera, el papel está afuera.
            if (($pagos = $bloqueado->pagos()->count()) > 0) {
                throw CarnetInvalidoException::tienePagos($pagos);
            }

            if (($faenas = $bloqueado->faenas()->count()) > 0) {
                throw CarnetInvalidoException::tienePermisos($faenas, 'faena(s)');
            }

            if (($guias = $bloqueado->guias()->count()) > 0) {
                throw CarnetInvalidoException::tienePermisos($guias, 'guía(s)');
            }

            // El motivo se deja y se borra: `Auditable` ya engancha el `deleted`,
            // y registrarlo a mano además dejaría el hecho dos veces.
            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->delete();
        });
    }

    /**
     * Da de baja una credencial, con motivo.
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

    //  Auxiliares

    /**
     * Los catálogos se releen DENTRO de la transacción.
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
     */
    private function carnetVigenteDe(Beneficiario $beneficiario, TipoActor $actor): ?Carnet
    {
        return Carnet::query()
            ->where('beneficiario_id', $beneficiario->id)
            ->deTipo($actor)
            ->vigentes()
            ->latest('fecha_solicitud')
            ->first();
    }

    /**
     * El cupo ELEGIDO, si de verdad es de esta persona y sirve hoy.
     */
    private function cupoUtilizable(Beneficiario $beneficiario, AprovechamientoPesq $cupo): ?AprovechamientoPesq
    {
        return AprovechamientoPesq::query()
            ->whereKey($cupo->id)
            ->deBeneficiario($beneficiario->id)
            ->enCurso()
            ->first();
    }

    /**
     * Su bolsa madre de esta gestión, o null.
     */
    private function cupoVigenteDe(Beneficiario $beneficiario): ?AprovechamientoPesq
    {
        return AprovechamientoPesq::query()
            ->deBeneficiario($beneficiario->id)
            ->enCurso()
            // Por la SOLICITUD: un cupo en curso puede estar sin firmar, y
            // entonces su `fecha_emision` es NULL.
            ->latest('fecha_solicitud')
            ->first();
    }
}
