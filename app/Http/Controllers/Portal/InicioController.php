<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\VerificarPagoJob;
use App\Support\ApiApp\PantallaPortal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que vale HOY, lo que tiene abierto (para decirle qué hacer ahora) y lo que
 * puede pedir. Los datos los arma PantallaPortal: la app ve lo mismo.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiario = $request->user()->beneficiario;
        // Al entrar ya se consultan sus pagos: cuando abra «En curso», el estado está al día.
        VerificarPagoJob::encolarDe($beneficiario);

        return Inertia::render('portal/inicio', PantallaPortal::inicio($beneficiario));
    }
}
