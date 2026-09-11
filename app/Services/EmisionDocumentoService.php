<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 *  EMITIR EL DOCUMENTO DE UN TRÁMITE
 * ============================================================================
 *
 * Emitir es el momento en que el trámite deja de ser un expediente y pasa a ser
 * un documento con valor: el que el pescador lleva encima, el que el inspector
 * verifica en la orilla del río.
 *
 * Por eso no vive en el controlador. Son cuatro cosas que tienen que pasar
 * juntas o no pasar:
 *
 *   1. reservar el número correlativo de la serie (DOC-PESCA-CAP-2026-0001)
 *   2. sortear un código de verificación que no exista
 *   3. calcular hasta cuándo vale, según la vigencia del tipo
 *   4. congelar una copia de lo que se imprime
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ SE GUARDA UNA COPIA DE LOS DATOS IMPRESOS
 * ----------------------------------------------------------------------------
 *
 * `datos_snapshot` duplica cosas que ya están en `solicitantes` y en
 * `tramites`, y eso normalmente sería un error. Acá es a propósito.
 *
 * El documento es una tarjeta plastificada que sale del sistema y se queda en
 * el bolsillo de alguien. Si mañana el pescador se muda y corrige su dirección
 * en la ficha, la credencial que tiene en la mano SIGUE diciendo la dirección
 * vieja. La página pública de verificación tiene que mostrar lo que dice el
 * papel, no lo que dice la base hoy: si mostrara lo de hoy, un inspector
 * compararía la tarjeta contra la pantalla, vería dos direcciones distintas y
 * concluiría que el documento es falso.
 */
class EmisionDocumentoService
{
    /**
     * Alfabeto del código de verificación.
     *
     * Sin I, O, 0 ni 1: el código se dicta por teléfono y se tipea a mano
     * cuando el QR está rayado o el celular no lo lee. Un cero y una O son la
     * misma letra para quien lo escribe, y el documento «no aparece».
     */
    public const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Dieciséis caracteres de ese alfabeto: 32^16 combinaciones, que son unas
     * 1,2 × 10^24.
     *
     * Antes eran doce. El largo importa porque el código es lo ÚNICO que
     * separa a un documento real de uno inventado: quien quisiera hacer pasar
     * una credencial falsa solo tiene que acertar un código que exista, y
     * probar códigos al azar contra la página pública es gratis. Cuatro
     * caracteres más multiplican por un millón el trabajo de acertar.
     *
     * Son públicos porque los usa también la validación del formulario
     * público, que exige exactamente este largo, y los datos de prueba.
     */
    public const LARGO_CODIGO = 16;

    /**
     * Emite el documento de un trámite aprobado.
     *
     * EL DOCUMENTO NO LLEVA NÚMERO PROPIO. Se identifica por su código de
     * verificación y nada más. Antes había además un correlativo legible
     * —DOC-COMER-GUT-2026-0013—, y eran dos identificadores para la misma
     * fila: el que el ciudadano usa de verdad es el código, que es el que
     * viaja en el QR y el que se tipea en la pantalla pública.
     *
     * La transacción se conserva igual: acá adentro se sortea el código y se
     * congela la copia de los datos, y las dos cosas tienen que entrar o no
     * entrar juntas con el documento.
     */
    public function emitir(Tramite $tramite, User $usuario): Documento
    {
        return DB::transaction(function () use ($tramite, $usuario): Documento {
            $tipo = $tramite->tipoTramite;
            $emision = now();

            return Documento::create([
                'codigo_verificacion' => $this->codigoLibre(),
                'tramite_id' => $tramite->id,
                'tipo' => $tipo->categoria_documento,
                'fecha_emision' => $emision,

                // La cuenta la hace el enum VigenciaTipo: no es lo mismo «un
                // mes desde la emisión» que «hasta el cierre de la gestión».
                'fecha_vencimiento' => $tipo->calcularVencimiento($emision),

                'datos_snapshot' => $this->congelar($tramite),
                'emitido_por' => $usuario->id,
            ]);
        });
    }

    /**
     * Un código que todavía no esté usado.
     *
     * El bucle no es paranoia gratuita: la columna tiene índice único, así que
     * un choque no rompería datos, pero sí reventaría el alta con un error de
     * base que el operador no puede entender ni resolver. Reintentar sale más
     * barato que explicarlo.
     *
     * Con 32^16 combinaciones el primer intento acierta prácticamente siempre;
     * el tope de 10 está para que un error de configuración no deje el proceso
     * girando para siempre.
     */
    private function codigoLibre(): string
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = $this->sortearCodigo();

            if (! Documento::where('codigo_verificacion', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new \RuntimeException('No se pudo generar un código de verificación libre.');
    }

    private function sortearCodigo(): string
    {
        $codigo = '';

        for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
            // random_int y no rand(): el código es lo único que separa a un
            // documento real de uno inventado, y rand() es predecible.
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $codigo;
    }

    /**
     * La copia inmutable de lo que queda impreso en el documento.
     *
     * @return array<string, mixed>
     */
    private function congelar(Tramite $tramite): array
    {
        $solicitante = $tramite->solicitante;

        return [
            'solicitante' => [
                'nombre' => $solicitante->nombreCompleto,
                'documento_identidad' => $solicitante->documento_identidad,
                'ci_nit' => $solicitante->ci_nit,
                'expedido' => $solicitante->expedido,
                'ciudad' => $solicitante->ciudad,
                'provincia' => $solicitante->provincia,
                'direccion' => $solicitante->direccion,
                // La ruta y no la URL: el dominio puede cambiar y la foto
                // seguiría estando en el mismo lugar del disco.
                'foto' => $solicitante->foto,
            ],
            'tramite' => [
                'numero' => $tramite->id,
                'tipo' => $tramite->tipoTramite->nombre,
                'tipo_codigo' => $tramite->tipoTramite->codigo,
                'area' => $tramite->tipoTramite->area->nombre,
                'monto_total' => (float) $tramite->monto_total,
            ],

            // Los campos propios del servicio: asociación y cupo en la cédula,
            // embarcación y especies en los otros. Se copian tal cual.
            'datos' => $tramite->datos_adicionales ?? [],
        ];
    }
}
