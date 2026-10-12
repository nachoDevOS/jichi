<?php

namespace App\Support;

use App\Enums\ModoVigencia;
use App\Enums\TramiteConVigencia;
use App\Models\Configuracion;
use Illuminate\Support\Carbon;

/**
 * Hasta cuándo vale un trámite que se aprueba en una fecha. Lo configura la
 * unidad (Administración › Vigencias); cambiarlo no toca lo ya emitido, porque
 * el vencimiento se escribe en el papel al aprobar.
 */
final class Vigencia
{
    public static function modo(TramiteConVigencia $tramite): ModoVigencia
    {
        return ModoVigencia::tryFrom((string) Configuracion::obtener($tramite->claveModo()))
            ?? $tramite->modoInicial();
    }

    public static function dias(TramiteConVigencia $tramite): int
    {
        $dias = (int) Configuracion::obtener($tramite->claveDias(), 0);

        return $dias > 0 ? $dias : $tramite->diasIniciales();
    }

    /** El vencimiento. Conserva la hora de `$desde`: la guía se cuenta con hora. */
    public static function hasta(TramiteConVigencia $tramite, Carbon|string $desde): Carbon
    {
        $desde = Carbon::parse($desde);

        return self::modo($tramite) === ModoVigencia::FinDeAnio
            ? $desde->copy()->endOfYear()
            : $desde->copy()->addDays(self::dias($tramite));
    }

    /** Para las pantallas: «30 días desde la aprobación» o «hasta el 31 de diciembre». */
    public static function texto(TramiteConVigencia $tramite): string
    {
        return self::modo($tramite) === ModoVigencia::FinDeAnio
            ? 'hasta el 31 de diciembre'
            : self::dias($tramite).' días desde la aprobación';
    }

    public static function guardar(TramiteConVigencia $tramite, ModoVigencia $modo, int $dias): void
    {
        $grupo = 'vigencias';

        Configuracion::updateOrCreate(['clave' => $tramite->claveModo()], [
            'valor' => $modo->value, 'tipo' => 'string', 'grupo' => $grupo,
            'etiqueta' => "Vigencia: {$tramite->etiqueta()} (modo)", 'publico' => false,
        ]);
        Configuracion::updateOrCreate(['clave' => $tramite->claveDias()], [
            'valor' => (string) $dias, 'tipo' => 'number', 'grupo' => $grupo,
            'etiqueta' => "Vigencia: {$tramite->etiqueta()} (días)", 'publico' => false,
        ]);
    }
}
