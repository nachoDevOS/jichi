<?php

namespace App\Support;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lo que el portal /mi-cuenta muestra de cada documento. Sin ids ni banderas de
 * funcionario: el beneficiario solo consulta. Estado, vigencia y saldo salen de
 * los mismos métodos del modelo que usa el panel.
 */
class ResumenPortal
{
    /** @return array<string, mixed> */
    public static function aprovechamiento(AprovechamientoPesq $a): array
    {
        return [
            'clase' => 'aprovechamiento',
            'tipo' => 'Autorización de Pesca para Aprovechamiento Pesquero',
            'codigo' => $a->codigo_legible,
            'verificar' => self::urlVerificar($a->codigo?->codigo),
            'vista_previa' => self::urlVistaPrevia($a),
            // Con este código se paga en SIREB.
            'codigo_pago' => self::codigoPago($a),
            'puede_pagar_qr' => self::puedePagarQr($a),
            'pago' => self::estadoPago($a),
            'descargar' => self::urlDescargar($a),
            'escala' => $a->categoria?->descripcion_kg,
            'volumen_total_kg' => (float) $a->volumen_total_kg,
            // Antes de la firma no se consumió nada: el saldo recién vale aprobado.
            'ya_fue_aprobado' => $a->yaFueAprobado(),
            'kilos_consumidos' => $a->kilosConsumidos(),
            'saldo_kg' => $a->saldoKg(),
            'porcentaje_usado' => $a->porcentajeUsado(),
            'estado_etiqueta' => $a->estado->etiqueta(),
            'estado_color' => $a->estado->color(),
            'vigente' => $a->estaVigente(),
            'en_tramite' => $a->estado->estaAbierto(),
            'siguiente_paso' => self::siguientePaso($a),
            'etapa' => self::etapa($a),
            'situacion' => self::situacion($a),
            'vence_el' => $a->fecha_vencimiento?->toDateString(),
            'dias_restantes' => self::diasRestantes($a),
            'motivo_baja' => self::motivoBaja($a),
            'debe' => $a->porPagar(),
            'fecha_solicitud' => $a->fecha_solicitud?->toDateString(),
            'fecha_vencimiento' => $a->fecha_vencimiento?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function carnet(Carnet $c): array
    {
        return [
            'clase' => 'carnet',
            'tipo' => 'Carnet de '.mb_strtolower($c->tipo_actor->etiqueta()),
            'tipo_actor' => $c->tipo_actor->value,
            'codigo' => $c->codigo_legible,
            'verificar' => self::urlVerificar($c->codigo?->codigo),
            'vista_previa' => self::urlVistaPrevia($c),
            // Con este código se paga en SIREB.
            'codigo_pago' => self::codigoPago($c),
            'puede_pagar_qr' => self::puedePagarQr($c),
            'pago' => self::estadoPago($c),
            // El carnet NO se descarga desde el portal: perdido, se repone en ventanilla.
            'descargar' => null,
            'asociacion' => $c->asociacion?->nombre,
            'cupo_kg' => $c->cupoImpreso(),
            // «Sin efecto» manda sobre «Aprobado»: su autorización fue revocada.
            'estado_etiqueta' => $c->etiquetaEstado(),
            'estado_color' => $c->colorEstado(),
            'vigente' => $c->estaVigente(),
            'en_tramite' => $c->estado->estaAbierto(),
            'siguiente_paso' => self::siguientePaso($c),
            'etapa' => self::etapa($c),
            'situacion' => self::situacion($c),
            'vence_el' => $c->fecha_vencimiento?->toDateString(),
            'dias_restantes' => self::diasRestantes($c),
            'motivo_baja' => self::motivoBaja($c),
            'debe' => $c->porPagar(),
            'fecha_emision' => $c->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function faena(PermisoFaena $f): array
    {
        return [
            'clase' => 'faena',
            'tipo' => 'Permiso de Faena',
            'numero' => $f->numero_legible,
            'codigo' => $f->codigo_legible,
            'verificar' => self::urlVerificar($f->codigo?->codigo),
            'vista_previa' => self::urlVistaPrevia($f),
            // Con este código se paga en SIREB.
            'codigo_pago' => self::codigoPago($f),
            'puede_pagar_qr' => self::puedePagarQr($f),
            'pago' => self::estadoPago($f),
            'descargar' => self::urlDescargar($f),
            'kilos' => (float) $f->kilos_extraidos,
            'embarcacion' => $f->embarcacion,
            'region' => collect([$f->region_desde, $f->region_hasta])->filter()->implode(' → ') ?: null,
            'estado_etiqueta' => $f->etiquetaEstado(),
            'estado_color' => $f->colorEstado(),
            'vigente' => $f->estaVigente(),
            'en_tramite' => $f->estado->estaAbierto(),
            'siguiente_paso' => self::siguientePaso($f),
            'etapa' => self::etapa($f),
            'situacion' => self::situacion($f),
            'vence_el' => $f->fecha_desembarque?->toDateString(),
            'dias_restantes' => self::diasRestantes($f),
            'motivo_baja' => self::motivoBaja($f),
            'debe' => $f->porPagar(),
            'fecha_salida' => $f->fecha_salida?->toDateString(),
            'fecha_desembarque' => $f->fecha_desembarque?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function guia(GuiaMovimiento $g): array
    {
        return [
            'clase' => 'guia',
            'tipo' => 'Guía Única de Transporte',
            'numero' => $g->numero_legible,
            'codigo' => $g->codigo_legible,
            'verificar' => self::urlVerificar($g->codigo?->codigo),
            'vista_previa' => self::urlVistaPrevia($g),
            // Con este código se paga en SIREB.
            'codigo_pago' => self::codigoPago($g),
            'puede_pagar_qr' => self::puedePagarQr($g),
            'pago' => self::estadoPago($g),
            'descargar' => self::urlDescargar($g),
            'ruta' => $g->ruta,
            'kilos' => (float) $g->peso_total_kg,
            'estado_etiqueta' => $g->etiquetaEstado(),
            'estado_color' => $g->colorEstado(),
            'vigente' => $g->estaVigente(),
            'en_tramite' => $g->estado->estaAbierto(),
            'siguiente_paso' => self::siguientePaso($g),
            'etapa' => self::etapa($g),
            'situacion' => self::situacion($g),
            'vence_el' => $g->fecha_vencimiento?->toIso8601String(),
            'dias_restantes' => self::diasRestantes($g),
            'motivo_baja' => self::motivoBaja($g),
            'debe' => $g->porPagar(),
            // Un MOMENTO: la guía vale por horas, no por días.
            'fecha_vencimiento' => $g->fecha_vencimiento?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function recibo(Recibo $r): array
    {
        return [
            'numero' => $r->numero_recibo,
            'concepto' => $r->concepto,
            'monto_total' => (float) $r->monto_total,
            'emitido_en' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * Los trámites abiertos de la persona —pendientes de pago—, del más
     * nuevo al más viejo dentro de cada tipo. Los usan «En curso» y el inicio.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function abiertos(Beneficiario $beneficiario): Collection
    {
        $abierto = fn (Model $d): bool => $d->estado->estaAbierto();

        return collect()
            ->merge(ExpedienteBeneficiario::aprovechamientos($beneficiario)->load('codigo')->filter($abierto)->map(self::aprovechamiento(...)))
            ->merge(ExpedienteBeneficiario::carnets($beneficiario)->filter($abierto)->map(self::carnet(...)))
            ->merge(ExpedienteBeneficiario::faenas($beneficiario)->load('codigo')->filter($abierto)->map(self::faena(...)))
            ->merge(ExpedienteBeneficiario::guias($beneficiario)->load('codigo')->filter($abierto)->map(self::guia(...)))
            ->values();
    }

    /** En qué punto está un trámite abierto: `pago` (espera el pago en SIREB). Null si no está abierto. */
    private static function etapa(Model $documento): ?string
    {
        return $documento->estado->estaAbierto() ? 'pago' : null;
    }

    /** Los estados de baja: revocado por la Unidad. */
    private const DE_BAJA = [EstadoAprovechamiento::Revocado, EstadoCarnet::Revocado, EstadoFaena::Revocado, EstadoGuia::Revocada];

    /**
     * Dónde va en «Mis papeles»: `vigente`, `vencido` (incluye la autorización
     * agotada) o `revocado` (incluye lo que quedó sin efecto). Null
     * si todavía está abierto: eso va en «En curso».
     */
    private static function situacion(Model $documento): ?string
    {
        return match (true) {
            $documento->estado->estaAbierto() => null,
            $documento->estaVigente() => 'vigente',
            self::deBaja($documento) => 'revocado',
            default => 'vencido',
        };
    }

    /** La autorización no tiene «sin efecto»: la que se revoca es ella. */
    private static function deBaja(Model $documento): bool
    {
        return in_array($documento->estado, self::DE_BAJA, true)
            || (! $documento instanceof AprovechamientoPesq && $documento->sinEfecto());
    }

    /** Días hasta el vencimiento, solo de lo vigente: para el «faltan N días» y el «vence pronto». */
    private static function diasRestantes(Model $documento): ?int
    {
        $vence = $documento instanceof PermisoFaena ? $documento->fecha_desembarque : $documento->fecha_vencimiento;

        return $vence !== null && $documento->estaVigente()
            ? (int) floor(now()->startOfDay()->diffInDays($vence->copy()->startOfDay(), false))
            : null;
    }

    /** Por qué ya no vale, dicho para el titular. Null si vale o si solo venció. */
    private static function motivoBaja(Model $documento): ?string
    {
        if ($documento->estado === EstadoAprovechamiento::Agotado) {
            return 'Se usaron todos los kilos autorizados.';
        }

        if (in_array($documento->estado, self::DE_BAJA, true)) {
            return $documento instanceof GuiaMovimiento ? 'Revocada por la Unidad de Pesca.' : 'Revocado por la Unidad de Pesca.';
        }

        if (! $documento instanceof AprovechamientoPesq && ! $documento->estado->estaAbierto() && $documento->sinEfecto()) {
            return $documento instanceof GuiaMovimiento
                ? 'Sin efecto: su carnet de comercializador ya no vale.'
                : 'Sin efecto: su Autorización de Pesca fue revocada.';
        }

        return null;
    }

    /**
     * En qué está el pago de un trámite abierto, según la última consulta a SIREB:
     * `sin_pago`, `revision` (cargado, falta validar), `validado` (se aprueba en
     * minutos) o `caida` (vencida o anulada: hace falta un código nuevo). Null si no está abierto.
     */
    private static function estadoPago(Model $documento): ?string
    {
        if (! $documento->estado->estaAbierto()) {
            return null;
        }

        $pago = $documento->sireb_envio['pago'] ?? null;

        return match (true) {
            $documento->liquidacionCaida() !== null => 'caida',
            ($pago['estado'] ?? null) === 'confirmado' => 'validado',
            $pago !== null => 'revision',
            default => 'sin_pago',
        };
    }

    /** El código con el que se paga, solo si todavía sirve: el de una liquidación caída ya no cobra. */
    private static function codigoPago(Model $documento): ?string
    {
        return self::estadoPago($documento) === 'caida' ? null : $documento->sireb_codigo_publico;
    }

    /** Ofrece el QR según la última consulta; al abrirlo se vuelve a preguntar a SIREB (`PagoQrController`). */
    private static function puedePagarQr(Model $documento): bool
    {
        return self::estadoPago($documento) === 'sin_pago' && $documento->sireb_codigo_publico !== null;
    }

    /** Qué le falta a un trámite abierto, dicho para el titular. Null si no está abierto. */
    private static function siguientePaso(Model $documento): ?string
    {
        $monto = self::bs($documento->porPagar());

        return match (self::estadoPago($documento)) {
            null => null,
            'caida' => ($documento->liquidacionCaida() === 'anulada' ? 'Recaudaciones anuló el cobro.' : 'Venció el plazo para pagar.')
                .' Acérquese a ventanilla del SEDAG para que le den un nuevo código de pago.',
            'validado' => 'Su pago de '.$monto.' ya fue validado. En unos minutos queda aprobado.',
            'revision' => 'Su pago de '.$monto.' ya está cargado. Lo están revisando en Recaudaciones: cuando lo validen, queda aprobado solo.',
            default => 'Falta pagar '.$monto.' en Recaudaciones'
                .($documento->sireb_codigo_publico ? ' con el código '.$documento->sireb_codigo_publico : '')
                .'. Una vez validado el pago, queda aprobado solo.',
        };
    }

    private static function bs(float $monto): string
    {
        return number_format($monto, 2, ',', '.').' Bs';
    }

    /** La vista previa «NO VÁLIDO», solo de lo abierto. Ver Portal\VistaPreviaController. */
    private static function urlVistaPrevia(Model $documento): ?string
    {
        $codigo = $documento->codigo?->codigo;

        return $codigo !== null && $documento->estado->estaAbierto()
            ? route('portal.vista-previa', ['codigo' => $codigo], false)
            : null;
    }

    /** Solo lo vigente hoy se descarga desde el portal. Ver Portal\DescargarController. */
    private static function urlDescargar(Model $documento): ?string
    {
        $codigo = $documento->codigo?->codigo;

        return $codigo !== null && $documento->estaVigente()
            ? route('portal.descargar', ['codigo' => $codigo], false)
            : null;
    }

    /** Relativa: el mismo sitio del portal, sin depender del host de la petición. */
    private static function urlVerificar(?string $codigo): ?string
    {
        return $codigo === null ? null : route('verificar.show', ['codigo' => $codigo], false);
    }
}
