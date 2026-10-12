<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ModoVigencia;
use App\Enums\TramiteConVigencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarVigenciasRequest;
use App\Support\Vigencia;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Vigencias — hasta cuándo vale cada trámite que se aprueba. Lo ya emitido no
 *  cambia: su vencimiento quedó escrito al aprobarlo.
 */
class VigenciaController extends Controller
{
    /**
     * Pantalla — GET /panel/configuracion/vigencias
     */
    public function index(): Response
    {
        return Inertia::render('panel/configuracion/vigencias', [
            'tramites' => array_map(fn (TramiteConVigencia $t): array => [
                'tramite' => $t->value,
                'etiqueta' => $t->etiqueta(),
                'modo' => Vigencia::modo($t)->value,
                'dias' => Vigencia::dias($t),
                'texto' => Vigencia::texto($t),
            ], TramiteConVigencia::cases()),
            'modos' => ModoVigencia::opciones(),
        ]);
    }

    /**
     * Guardar — PUT /panel/configuracion/vigencias
     */
    public function update(GuardarVigenciasRequest $request): RedirectResponse
    {
        foreach (TramiteConVigencia::cases() as $t) {
            $datos = $request->validated()[$t->value];
            $modo = ModoVigencia::from($datos['modo']);
            // En «fin de año» los días no se usan, pero se guardan los que había para no perderlos.
            Vigencia::guardar($t, $modo, (int) ($datos['dias'] ?? Vigencia::dias($t)));
        }

        return back()->with('exito', 'Vigencias guardadas. Rigen para lo que se apruebe desde ahora.');
    }
}
