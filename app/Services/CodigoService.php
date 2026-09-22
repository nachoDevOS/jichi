<?php

namespace App\Services;

use App\Models\Codigo;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 *  EL CÓDIGO DE 16 DE CUALQUIER DOCUMENTO
 *
 *  Vivía adentro de EmitirCarnetService; se sacó acá el 22/09/2026 porque lo
 *  necesitan cinco documentos. Ver docs/MER.md, tabla `codigos`.
 */
class CodigoService
{
    /**
     * SIN `I L O S 0 1 5`: son los que se confunden al dictar por teléfono o
     * al tipear de un plástico gastado.
     */
    public const ALFABETO = 'ABCDEFGHJKMNPQRTUVWXYZ2346789';

    public const LARGO = 16;

    /**
     * Cuántas veces se reintenta ante una colisión.
     */
    private const INTENTOS = 10;

    /**
     * Le cuelga su código al documento y lo devuelve.
     *
     * Es idempotente: si ya tiene uno, no emite otro. Volver a llamarla desde
     * un reenvío o un reintento no puede gastar un código nuevo, porque el
     * anterior ya salió impreso.
     */
    public function asignar(Model $documento): Codigo
    {
        $existente = $documento->codigo()->first();

        if ($existente !== null) {
            return $existente;
        }

        $codigo = $documento->codigo()->create(['codigo' => $this->generar()]);

        // El morphOne queda cargado: quien llamó suele imprimir el código en
        // la línea siguiente, y sin esto dispara otra consulta.
        $documento->setRelation('codigo', $codigo);

        return $codigo;
    }

    /**
     * Una tira libre, comprobada contra la tabla.
     */
    public function generar(): string
    {
        for ($intento = 0; $intento < self::INTENTOS; $intento++) {
            $codigo = $this->azar(self::LARGO);

            if (! Codigo::query()->withTrashed()->where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        // Diez colisiones seguidas sobre 2,5 × 10²³ combinaciones no es mala
        // suerte: es que algo está mal en el generador. Falla ruidosamente en
        // vez de entregar un código repetido.
        throw new RuntimeException('No se pudo generar un código único después de '.self::INTENTOS.' intentos.');
    }

    /**
     * Una tira al azar del alfabeto.
     */
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
