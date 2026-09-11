<?php

namespace App\Services;

use App\Models\Tramite;

/**
 * ============================================================================
 *  EL CÓDIGO DE REGISTRO QUE VA IMPRESO EN LA CREDENCIAL
 * ============================================================================
 *
 * El renglón «REGISTRO» de la cédula de pescador se escribía a mano en
 * ventanilla, copiándolo del padrón del SEDAG. Eso trae dos problemas que en
 * una tarjeta plastificada no se arreglan editando un registro:
 *
 *   - se repite, porque nadie tiene a la vista los que ya se usaron
 *   - se tipea mal, y la credencial sale con un número que no es de nadie
 *
 * Ahora lo asigna el sistema, al guardar, con este formato:
 *
 *     78T3-8K9T-789P
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NO ES UN CORRELATIVO
 * ----------------------------------------------------------------------------
 *
 * El número de trámite sí es correlativo —TRA-PESCA-2026-0038— porque es interno
 * y su orden importa para el archivo. Este código, en cambio, va impreso en una
 * tarjeta que anda por la calle: si fuera 0001, 0002, 0003, cualquiera con dos
 * credenciales a la vista deduce la serie entera y se fabrica una que «existe».
 * Sorteado no se puede adivinar, y sigue siendo único.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTOS CARACTERES Y NO OTROS
 * ----------------------------------------------------------------------------
 *
 * El mismo alfabeto que el código de verificación del documento, y por la misma
 * razón: sin I, O, 0 ni 1. El código se lee de una tarjeta gastada y se dicta
 * por teléfono, y ahí un cero y una O son la misma letra. Los guiones cada
 * cuatro caracteres son solo para leerlo: se guardan, porque es como está
 * impreso, pero nada del sistema depende de ellos.
 *
 * Ver App\Services\EmisionDocumentoService, que usa el mismo alfabeto.
 */
class RegistroPescadorService
{
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /*
     * Tres grupos y no cuatro.
     *
     * El código entra en un renglón de una tarjeta CR80 —85,6 mm de ancho— y
     * comparte esa línea con el cupo autorizado. Con dieciséis caracteres más
     * los guiones, el renglón quedaba al límite y en pantallas angostas el
     * final se cortaba con puntos suspensivos. Con doce entra holgado.
     *
     * No se pierde nada por el lado de la unicidad: doce caracteres de este
     * alfabeto siguen siendo más combinaciones que credenciales va a emitir el
     * Beni en varias vidas.
     */
    private const GRUPOS = 3;

    private const LARGO_GRUPO = 4;

    /**
     * Un código que ninguna credencial esté usando.
     *
     * Con 32^12 combinaciones el primer intento acierta prácticamente siempre.
     * La comprobación igual se hace: «prácticamente siempre» no es lo mismo que
     * «jamás», y dos pescadores con el mismo número de registro es exactamente
     * el problema que este servicio vino a resolver.
     *
     * El tope de 10 intentos está para que un error de configuración no deje el
     * alta girando para siempre con el operador esperando.
     */
    public function siguiente(): string
    {
        for ($intento = 0; $intento < 10; $intento++) {
            $codigo = $this->sortear();

            if (! $this->estaUsado($codigo)) {
                return $codigo;
            }
        }

        throw new \RuntimeException('No se pudo generar un código de registro libre.');
    }

    /**
     * ¿Alguna credencial ya tiene este código?
     *
     * El registro vive en la columna jsonb `datos_adicionales`, y el operador
     * `->` de Laravel se traduce solo al SQL de cada motor —`->>` en PostgreSQL,
     * `json_extract` en SQLite—, así que esta consulta no necesita pasar por
     * App\Support\Sql.
     *
     * withTrashed() importa: un trámite dado de baja igual pudo haber impreso su
     * credencial, y esa tarjeta sigue existiendo en el bolsillo de alguien.
     */
    private function estaUsado(string $codigo): bool
    {
        return Tramite::withTrashed()
            ->where('datos_adicionales->registro', $codigo)
            ->exists();
    }

    private function sortear(): string
    {
        $grupos = [];

        for ($g = 0; $g < self::GRUPOS; $g++) {
            $grupo = '';

            for ($i = 0; $i < self::LARGO_GRUPO; $i++) {
                // random_int y no rand(): el código va impreso en un documento
                // oficial, y rand() produce series predecibles.
                $grupo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }

            $grupos[] = $grupo;
        }

        return implode('-', $grupos);
    }
}
