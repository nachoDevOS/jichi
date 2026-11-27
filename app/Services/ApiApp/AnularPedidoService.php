<?php

namespace App\Services\ApiApp;

use App\Exceptions\PermisoOperativoException;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Services\ConfirmarPagoService;
use App\Services\EmitirFaenaService;
use App\Services\EmitirGuiaService;
use App\Sireb\SirebException;
use App\Support\ApiApp\DocumentoDelPortal;
use Illuminate\Database\Eloquent\Model;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * «Anular pedido» (27/11/2026, decisión del responsable): el titular anula su faena o
 * guía **pendiente** que pidió él mismo **en línea** —desde la app o desde el portal
 * web, da igual dónde: lo que importa es que la pidió el beneficiario—, para poder
 * pedir otra. Lo registrado en ventanilla lo anula el panel.
 *
 * Es el mismo «Eliminar» del panel (EmitirFaenaService / EmitirGuiaService::eliminar):
 * primero pregunta a SIREB y anula el cobro (pago en revisión o pagado: frena; pago
 * rechazado: ya no cuenta), y la guía devuelve sus kilos al pescador. En la auditoría,
 * la cuenta del titular y el motivo fijo.
 */
class AnularPedidoService
{
    public const MOTIVO = 'Anulado por el titular desde la app o el portal web';

    public function __construct(
        private readonly EmitirFaenaService $faenas,
        private readonly EmitirGuiaService $guias,
        private readonly ConfirmarPagoService $pagos,
    ) {}

    /**
     * [$deUsted]: el portal web le habla de usted; la app, de vos.
     *
     * @return array{resultado: 'anulado'|'no_encontrado'|'no_se_puede'|'ya_pagado', mensaje: string, aprobado: bool}
     */
    public function anular(int $beneficiarioId, string $codigo, bool $deUsted = false): array
    {
        $tramite = DocumentoDelPortal::buscar($codigo, $beneficiarioId);

        // Ajeno, inexistente o que no es faena ni guía: lo mismo, sin distinguir.
        if (! $tramite instanceof PermisoFaena && ! $tramite instanceof GuiaMovimiento) {
            return self::resultado('no_encontrado', 'No encontramos ese pedido entre los suyos.');
        }

        $cual = self::cual($tramite);

        if (! $tramite->estado->permiteEliminacion()) {
            return self::resultado('no_se_puede', "Ya no se puede anular {$cual}: está ".mb_strtolower($tramite->estado->etiqueta()).'.');
        }

        if ($tramite->origen_tramite?->esEnLinea() !== true) {
            return self::resultado('no_se_puede', "Anular {$cual} se hace en ventanilla de la Unidad de Pesca: ahí se registró.");
        }

        try {
            $tramite instanceof PermisoFaena
                ? $this->faenas->eliminar($tramite, self::MOTIVO)
                : $this->guias->eliminar($tramite, self::MOTIVO);
        } catch (PermisoOperativoException $e) {
            return self::resultado('no_se_puede', $e->getMessage());
        } catch (SirebException $e) {
            if (! $e->frenaPorPago()) {
                throw $e;
            }

            // Con un pago cargado no se anula. Si el pago ya está validado, queda aprobado ahora.
            $aprobado = $this->pagos->alNoPoderEliminar($tramite)['aprobado'];

            return self::resultado(
                'ya_pagado',
                $aprobado
                    ? ($deUsted ? 'Ya pagó: no se anula. Su trámite quedó aprobado.' : 'Ya pagaste: no se anula. Tu trámite quedó aprobado.')
                    : 'Tiene un pago cargado: no se anula. Cuando Recaudaciones lo confirme, queda aprobado solo.',
                $aprobado,
            );
        }

        return self::resultado(
            'anulado',
            ($deUsted ? 'Anuló ' : 'Anulaste ').$cual.'. No se cobra'
                .($tramite instanceof GuiaMovimiento ? ' y los kilos volvieron al pescador' : '')
                .($deUsted ? '. Ya puede pedir otro.' : '. Ya podés pedir otro.'),
        );
    }

    /**
     * ¿Se ofrece «Anular pedido»? Lo usa ResumenPortal para la app y la web: su faena o
     * guía pendiente, pedida en línea y sin un pago cargado. Al anular, igual se
     * vuelve a preguntar a SIREB.
     */
    public static function sePuede(Model $tramite, ?string $pago): bool
    {
        return ($tramite instanceof PermisoFaena || $tramite instanceof GuiaMovimiento)
            && $tramite->estado->permiteEliminacion()
            && $tramite->origen_tramite?->esEnLinea() === true
            && in_array($pago, ['sin_pago', 'caida'], true);
    }

    private static function cual(PermisoFaena|GuiaMovimiento $tramite): string
    {
        return $tramite instanceof PermisoFaena
            ? "el permiso de faena N° {$tramite->numero_legible}"
            : "la guía de transporte N° {$tramite->numero_legible}";
    }

    /** @return array{resultado: 'anulado'|'no_encontrado'|'no_se_puede'|'ya_pagado', mensaje: string, aprobado: bool} */
    private static function resultado(string $resultado, string $mensaje, bool $aprobado = false): array
    {
        return ['resultado' => $resultado, 'mensaje' => $mensaje, 'aprobado' => $aprobado];
    }
}
