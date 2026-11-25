<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ApiApp\PantallaPortal;
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
        return Inertia::render('portal/papeles', [
            'papeles' => PantallaPortal::papeles($request->user()->beneficiario),
        ]);
    }
}
