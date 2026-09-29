<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ExpedienteBeneficiario;
use App\Support\ResumenPortal;
use App\Support\TramitesDisponibles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lo que vale HOY y lo que puede pedir en ventanilla. Los trámites abiertos van
 * en «En curso»; acá, cuántos son.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiario = $request->user()->beneficiario;

        $aprovechamientos = ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo');
        $carnets = ExpedienteBeneficiario::carnets($beneficiario);
        $faenas = ExpedienteBeneficiario::faenas($beneficiario)->load('codigo');
        $guias = ExpedienteBeneficiario::guias($beneficiario)->load('codigo');

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
            // Solo cuántos: el detalle está en «En curso».
            'en_curso' => collect([$aprovechamientos, $carnets, $faenas, $guias])
                ->sum(fn ($grupo) => $grupo->filter(fn ($d) => $d->estado->estaAbierto())->count()),
            'deuda' => $beneficiario->deudaTotal(),
        ]);
    }
}
