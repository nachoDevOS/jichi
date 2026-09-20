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
     * El alfabeto del código impreso.
     */
    private const ALFABETO = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';

    /** Cuántas posiciones al azar lleva el código, después del prefijo. */
    private const LARGO_ALEATORIO = 7;

    /**
     * Emite la credencial.
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
            ->latest('fecha_emision')
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
            ->latest('fecha_emision')
            ->first();
    }

    /**
     *  EL CÓDIGO IMPRESO EN EL PLÁSTICO
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
