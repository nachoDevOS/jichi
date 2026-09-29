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
            'qr_pago' => self::urlPagar($a),
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
            'en_revision' => $a->estado->permiteRevision(),
            'siguiente_paso' => self::siguientePaso($a),
            'etapa' => self::etapa($a),
            'situacion' => self::situacion($a),
            'vence_el' => $a->fecha_vencimiento?->toDateString(),
            'dias_restantes' => self::diasRestantes($a),
            'motivo_baja' => self::motivoBaja($a),
            'debe' => $a->admitePagos() ? $a->saldoPendiente() : 0.0,
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
            'qr_pago' => self::urlPagar($c),
            // El carnet NO se descarga desde el portal: perdido, se repone en ventanilla.
            'descargar' => null,
            'asociacion' => $c->asociacion?->nombre,
            'cupo_kg' => $c->cupoImpreso(),
            // «Sin efecto» manda sobre «Aprobado»: su autorización fue revocada.
            'estado_etiqueta' => $c->etiquetaEstado(),
            'estado_color' => $c->colorEstado(),
            'vigente' => $c->estaVigente(),
            'en_tramite' => $c->estado->estaAbierto(),
            'en_revision' => $c->estado->permiteRevision(),
            'siguiente_paso' => self::siguientePaso($c),
            'etapa' => self::etapa($c),
            'situacion' => self::situacion($c),
            'vence_el' => $c->fecha_vencimiento?->toDateString(),
            'dias_restantes' => self::diasRestantes($c),
            'motivo_baja' => self::motivoBaja($c),
            'debe' => $c->admitePagos() ? $c->saldoPendiente() : 0.0,
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
            'qr_pago' => self::urlPagar($f),
            'descargar' => self::urlDescargar($f),
            'kilos' => (float) $f->kilos_extraidos,
            'embarcacion' => $f->embarcacion,
            'region' => collect([$f->region_desde, $f->region_hasta])->filter()->implode(' → ') ?: null,
            'estado_etiqueta' => $f->etiquetaEstado(),
            'estado_color' => $f->colorEstado(),
            'vigente' => $f->estaVigente(),
            'en_tramite' => $f->estado->estaAbierto(),
            'en_revision' => $f->estado->permiteRevision(),
            'siguiente_paso' => self::siguientePaso($f),
            'etapa' => self::etapa($f),
            'situacion' => self::situacion($f),
            'vence_el' => $f->fecha_desembarque?->toDateString(),
            'dias_restantes' => self::diasRestantes($f),
            'motivo_baja' => self::motivoBaja($f),
            'debe' => $f->admitePagos() ? $f->saldoPendiente() : 0.0,
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
            'qr_pago' => self::urlPagar($g),
            'descargar' => self::urlDescargar($g),
            'ruta' => $g->ruta,
            'kilos' => (float) $g->peso_total_kg,
            'estado_etiqueta' => $g->etiquetaEstado(),
            'estado_color' => $g->colorEstado(),
            'vigente' => $g->estaVigente(),
            'en_tramite' => $g->estado->estaAbierto(),
            'en_revision' => $g->estado->permiteRevision(),
            'siguiente_paso' => self::siguientePaso($g),
            'etapa' => self::etapa($g),
            'situacion' => self::situacion($g),
            'vence_el' => $g->fecha_vencimiento?->toIso8601String(),
            'dias_restantes' => self::diasRestantes($g),
            'motivo_baja' => self::motivoBaja($g),
            'debe' => $g->admitePagos() ? $g->saldoPendiente() : 0.0,
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
            'depositos' => $r->pagos_count,
            'emitido_en' => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * Los trámites abiertos de la persona —pendientes y en revisión—, del más
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

    /**
     * En qué punto del circuito está un trámite abierto, para la línea de avance
     * del portal: `pago` (falta plata), `envio` (cubierto, falta presentarlo) o
     * `revision` (lo controla la Unidad). Null si no está abierto.
     */
    private static function etapa(Model $documento): ?string
    {
        return match (true) {
            $documento->estado->permiteRevision() => 'revision',
            ! $documento->estado->permiteEnvio() => null,
            $documento->saldoPendiente() > 0 => 'pago',
            default => 'envio',
        };
    }

    /** Los estados de baja: revocado por la Unidad, o la guía anulada. */
    private const DE_BAJA = [EstadoAprovechamiento::Revocado, EstadoCarnet::Revocado, EstadoFaena::Revocado, EstadoGuia::Anulada];

    /**
     * Dónde va en «Mis papeles»: `vigente`, `vencido` (incluye la autorización
     * agotada) o `revocado` (incluye lo anulado y lo que quedó sin efecto). Null
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
            return $documento instanceof GuiaMovimiento ? 'Anulada por la Unidad de Pesca.' : 'Revocado por la Unidad de Pesca.';
        }

        if (! $documento instanceof AprovechamientoPesq && ! $documento->estado->estaAbierto() && $documento->sinEfecto()) {
            return $documento instanceof GuiaMovimiento
                ? 'Sin efecto: su carnet de comercializador ya no vale.'
                : 'Sin efecto: su Autorización de Pesca fue revocada.';
        }

        return null;
    }

    /** Qué le falta a un trámite abierto, dicho para el titular. Null si no está abierto. */
    private static function siguientePaso(Model $documento): ?string
    {
        return match (true) {
            $documento->estado->permiteRevision() => 'La Unidad de Pesca lo está revisando.',
            ! $documento->estado->permiteEnvio() => null,
            // El QR se ofrece solo si el botón existe: ver `jichi.portal.pago_qr`.
            $documento->saldoPendiente() > 0 => config('jichi.portal.pago_qr')
                ? 'Falta pagar '.self::bs($documento->saldoPendiente()).': con QR o por depósito bancario.'
                : 'Falta pagar '.self::bs($documento->saldoPendiente()).': depósito bancario y comprobante en ventanilla.',
            default => 'Pago completo: ventanilla lo envía a revisión.',
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

    /** El PNG del «Pagar» simulado, solo si todavía admite depósitos y falta plata. */
    private static function urlPagar(Model $documento): ?string
    {
        $codigo = $documento->codigo?->codigo;

        return config('jichi.portal.pago_qr') && $codigo !== null && $documento->admitePagos() && $documento->saldoPendiente() > 0
            ? route('portal.pagar.qr', ['codigo' => $codigo], false)
            : null;
    }

    /** Relativa: el mismo sitio del portal, sin depender del host de la petición. */
    private static function urlVerificar(?string $codigo): ?string
    {
        return $codigo === null ? null : route('verificar.show', ['codigo' => $codigo], false);
    }
}
