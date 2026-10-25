<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\VerificarPagoJob;
use App\Support\ExpedienteBeneficiario;
use App\Support\ResumenPortal;
use App\Support\TramitesDisponibles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que vale HOY, lo que tiene abierto (para decirle qué hacer ahora) y lo que
 * puede pedir en ventanilla.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiario = $request->user()->beneficiario;
        // Al entrar ya se consultan sus pagos: cuando abra «En curso», el estado está al día.
        VerificarPagoJob::encolarDe($beneficiario);

        $aprovechamientos = ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo');
        $carnets = ExpedienteBeneficiario::carnets($beneficiario);
        $faenas = ExpedienteBeneficiario::faenas($beneficiario)->load('codigo');
        $guias = ExpedienteBeneficiario::guias($beneficiario)->load('codigo');

        $abierto = fn ($d): bool => $d->estado->estaAbierto();
        $vigentes = collect()
            ->merge($aprovechamientos->filter->estaVigente()->map(ResumenPortal::aprovechamiento(...)))
            ->merge($carnets->filter->estaVigente()->map(ResumenPortal::carnet(...)))
            ->merge($faenas->filter->estaVigente()->map(ResumenPortal::faena(...)))
            ->merge($guias->filter->estaVigente()->map(ResumenPortal::guia(...)));

        return Inertia::render('portal/inicio', [
            'nombre' => $beneficiario->nombreCompleto,
            // Lo que vence antes, primero: es lo que hay que renovar.
            'vigentes' => $vigentes->sortBy('vence_el')->values()->all(),
            'tramites_disponibles' => TramitesDisponibles::para($beneficiario),
            // Lo abierto, para «Qué hacer ahora»: el detalle completo sigue en «En curso».
            'abiertos' => collect()
                ->merge($aprovechamientos->filter($abierto)->map(ResumenPortal::aprovechamiento(...)))
                ->merge($carnets->filter($abierto)->map(ResumenPortal::carnet(...)))
                ->merge($faenas->filter($abierto)->map(ResumenPortal::faena(...)))
                ->merge($guias->filter($abierto)->map(ResumenPortal::guia(...)))
                ->values()->all(),
            'deuda' => $beneficiario->deudaTotal(),
        ]);
    }
}
