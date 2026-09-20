<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\CobroInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\CorregirPagoRequest;
use App\Http\Requests\Panel\ObservarPagoRequest;
use App\Models\Pago;
use App\Services\ControlarPagoService;
use App\Support\Archivos;
use Illuminate\Http\RedirectResponse;

/**
 * El control de las boletas: validar, observar y corregir un depósito.
 */
class PagoController extends Controller
{
    public function __construct(private readonly ControlarPagoService $servicio) {}

    /** VALIDAR — PATCH /panel/pagos/{pago}/validar */
    public function validar(Pago $pago): RedirectResponse
    {
        try {
            $this->servicio->validar($pago);
        } catch (CobroInvalidoException $e) {
            return back()->withErrors(['pagos' => $e->getMessage()]);
        }

        return back()->with('exito', 'Depósito validado.');
    }

    /**
     * OBSERVAR — PATCH /panel/pagos/{pago}/observar
     *
     * El depósito sigue sumando en el saldo: lo que se duda es la boleta.
     */
    public function observar(ObservarPagoRequest $request, Pago $pago): RedirectResponse
    {
        try {
            $this->servicio->observar($pago, $request->validated()['motivo']);
        } catch (CobroInvalidoException $e) {
            return back()->withErrors(['pagos' => $e->getMessage()]);
        }

        return back()->with('exito', 'Depósito observado. Ventanilla tiene que corregirlo.');
    }

    /**
     * CORREGIR — POST /panel/pagos/{pago}/corregir
     */
    public function corregir(CorregirPagoRequest $request, Pago $pago): RedirectResponse
    {
        $datos = $request->validated();
        $subido = null;

        try {
            if ($request->hasFile('comprobante')) {
                $subido = app(StorageController::class)
                    ->file($request->file('comprobante'), 'comprobantes');
            }

            $this->servicio->corregir($pago, [
                'monto_parcial' => $datos['monto_parcial'],
                'nro_transaccion' => $datos['nro_transaccion'],
                'fecha_deposito' => $datos['fecha_deposito'],
                'comprobante' => $subido,
            ]);
        } catch (CobroInvalidoException $e) {
            if ($subido !== null) {
                Archivos::borrar($subido);
            }

            return back()->withInput()->withErrors(['pagos' => $e->getMessage()]);
        } catch (\Throwable $e) {
            if ($subido !== null) {
                Archivos::borrar($subido);
            }

            throw $e;
        }

        return back()->with(
            'exito',
            'Depósito corregido. Vuelve a quedar SIN VALIDAR: hace falta validarlo de nuevo.',
        );
    }
}
