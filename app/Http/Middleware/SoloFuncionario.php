<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El panel es solo del personal. Va en el GRUPO entero de /panel, antes que
 * `permiso:`: una cuenta del portal con un rol puesto por error igual no pasa.
 */
class SoloFuncionario
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->esBeneficiario()) {
            return redirect()->route('portal.inicio');
        }

        return $next($request);
    }
}
