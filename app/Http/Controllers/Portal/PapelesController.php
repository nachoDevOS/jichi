<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ExpedienteBeneficiario;
use App\Support\ResumenPortal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Todo lo que alguna vez se aprobó, de todas las gestiones: vigente, vencido o
 * revocado (`situacion`, de ResumenPortal). Lo abierto va en «En curso».
 */
class PapelesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $beneficiario = $request->user()->beneficiario;

        $papeles = collect()
            ->merge(ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo')->map(ResumenPortal::aprovechamiento(...)))
            ->merge(ExpedienteBeneficiario::carnets($beneficiario)->map(ResumenPortal::carnet(...)))
            ->merge(ExpedienteBeneficiario::faenas($beneficiario)->load('codigo')->map(ResumenPortal::faena(...)))
            ->merge(ExpedienteBeneficiario::guias($beneficiario)->load('codigo')->map(ResumenPortal::guia(...)))
            ->whereNotNull('situacion')
            // Lo que vence (o venció) más tarde, primero: es lo más reciente.
            ->sortByDesc('vence_el')
            ->values();

        return Inertia::render('portal/papeles', ['papeles' => $papeles->all()]);
    }
}
