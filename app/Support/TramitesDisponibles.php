<?php

namespace App\Support;

use App\Enums\TipoActor;
use App\Models\Beneficiario;
use App\Models\Carnet;

/**
 * «Puede tramitar» del inicio del portal: qué documento puede pedir hoy en
 * ventanilla y, si no, por qué. No inventa reglas: pregunta a los mismos métodos
 * del modelo que usa el panel para dejar emitir (`puedeEmitirFaenas()`,
 * `motivoSinPermisos()`, `enCurso()`…).
 */
class TramitesDisponibles
{
    /**
     * @return list<array{clase: string, titulo: string, disponible: bool, detalle: string}>
     */
    public static function para(Beneficiario $beneficiario): array
    {
        $pescador = $beneficiario->carnetVigenteDe(TipoActor::Pescador);
        $comercializador = $beneficiario->carnetVigenteDe(TipoActor::Comercializador);
        // Una autorización a la vez: la que está pendiente, en revisión o aprobada en fecha ocupa el lugar.
        $autorizacion = $beneficiario->aprovechamientos()->enCurso()->latest('id')->first();

        $lista = [
            self::faena($pescador),
            self::guia($comercializador),
            $autorizacion === null
                ? self::item('aprovechamiento', 'Autorización de Pesca', true, 'El cupo de kilos de la gestión, según la escala oficial.')
                : null,
            self::carnet($beneficiario, TipoActor::Pescador, $pescador, $autorizacion !== null),
            self::carnet($beneficiario, TipoActor::Comercializador, $comercializador, true),
        ];

        // Lo disponible primero: es lo que la persona viene a ver.
        return collect($lista)->filter()->sortByDesc('disponible')->values()->all();
    }

    /** @return array{clase: string, titulo: string, disponible: bool, detalle: string}|null */
    private static function faena(?Carnet $pescador): ?array
    {
        if ($pescador === null) {
            return null;
        }

        $pescador->loadMissing('aprovechamiento');

        if (! $pescador->puedeEmitirFaenas()) {
            return self::item('faena', 'Permiso de Faena', false, (string) $pescador->motivoSinPermisos());
        }

        // En modo flexible (`jichi.aprovechamiento.estricto`) se emite aun sin kilos libres.
        $saldo = $pescador->aprovechamiento?->saldoKg() ?? 0;

        return self::item('faena', 'Permiso de Faena', true, $saldo > 0
            ? 'Le quedan '.self::kg($saldo).' en su autorización.'
            : 'Su autorización ya no tiene kilos libres: consulte en ventanilla.');
    }

    /** @return array{clase: string, titulo: string, disponible: bool, detalle: string}|null */
    private static function guia(?Carnet $comercializador): ?array
    {
        if ($comercializador === null) {
            return null;
        }

        return $comercializador->puedeEmitirGuias()
            ? self::item('guia', 'Guía de Transporte', true, 'Una por cada traslado; vale 5 días.')
            : self::item('guia', 'Guía de Transporte', false, (string) $comercializador->motivoSinPermisos());
    }

    /**
     * Solo si todavía no lo tiene ni lo está tramitando: un carnet vigente por actividad.
     *
     * @return array{clase: string, titulo: string, disponible: bool, detalle: string}|null
     */
    private static function carnet(Beneficiario $beneficiario, TipoActor $actor, ?Carnet $vigente, bool $requisito): ?array
    {
        $enTramite = $beneficiario->carnets()
            ->where('tipo_actor', $actor->value)
            ->get()
            ->contains(fn (Carnet $c): bool => $c->estado->estaAbierto());

        if ($vigente !== null || $enTramite) {
            return null;
        }

        $titulo = 'Carnet de '.mb_strtolower($actor->etiqueta());

        // El pescador no saca carnet sin autorización; el comercializador nunca la lleva.
        return $requisito
            ? self::item('carnet', $titulo, true, 'Con el aval de su asociación.')
            : self::item('carnet', $titulo, false, 'Primero necesita la Autorización de Pesca.');
    }

    /** @return array{clase: string, titulo: string, disponible: bool, detalle: string} */
    private static function item(string $clase, string $titulo, bool $disponible, string $detalle): array
    {
        return compact('clase', 'titulo', 'disponible', 'detalle');
    }

    private static function kg(float $kilos): string
    {
        return number_format($kilos, 0, ',', '.').' kg';
    }
}
