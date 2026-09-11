<?php

namespace App\Support;

use App\Models\Documento;
use App\Models\Tramite;

/**
 * La respuesta a «¿este solicitante puede pedir este trámite?».
 *
 * ¿POR QUÉ NO DEVOLVER UN SIMPLE true/false?
 *
 * Porque en ventanilla el «no» sin explicación es inservible. El operador
 * tiene al pescador enfrente y necesita saber QUÉ le falta y qué hacer, y las
 * tres respuestas negativas piden acciones distintas:
 *
 *   - nunca sacó la cédula          → hay que emitírsela desde cero
 *   - la pidió y está en revisión   → no hay nada que hacer en ventanilla:
 *                                     falta que el supervisor la apruebe
 *   - la tuvo y se le venció        → hay que renovarla, y conviene decirle
 *                                     desde cuándo está vencida
 *   - la tiene vigente              → adelante, se registra solo el servicio
 *
 * Un booleano obliga a que cada pantalla vuelva a averiguar el motivo por su
 * cuenta, y ahí es donde las reglas se empiezan a contradecir entre sí.
 */
final readonly class Habilitacion
{
    private function __construct(
        public bool $habilitado,
        /** Qué falta, en lenguaje de ventanilla. NULL si está habilitado. */
        public ?string $motivo = null,
        /** Qué hacer para destrabarlo. NULL si está habilitado. */
        public ?string $comoResolver = null,
        /** La credencial encontrada, vigente o vencida. NULL si nunca tuvo. */
        public ?Documento $credencial = null,
        /** El trámite de cédula todavía sin emitir, si lo hay. */
        public ?Tramite $tramiteEnCurso = null,
    ) {}

    public static function permitida(?Documento $credencial = null): self
    {
        return new self(habilitado: true, credencial: $credencial);
    }

    public static function sinCredencial(): self
    {
        return new self(
            habilitado: false,
            motivo: 'El solicitante no tiene Cédula de Pescador.',
            comoResolver: 'Emitir primero la Cédula de Pescador. Recién con la cédula vigente se puede registrar este servicio.',
        );
    }

    /**
     * La pidió pero todavía no se la emitieron.
     *
     * La cédula no habilita al pedirla: pasa por revisión y recién cuando el
     * supervisor la aprueba y se emite el documento sirve para sacar una
     * faena o una guía. Mientras tanto no hay nada que ventanilla pueda
     * hacer, y decirlo evita que el operador vuelva a cargarle otra cédula
     * creyendo que la primera se perdió.
     */
    public static function credencialEnRevision(Tramite $tramite): self
    {
        return new self(
            habilitado: false,
            motivo: 'La Cédula de Pescador está en trámite ('.$tramite->estado->etiqueta().').',
            comoResolver: 'Falta que se apruebe y se emita. Recién con la cédula emitida se puede registrar este servicio.',
            tramiteEnCurso: $tramite,
        );
    }

    public static function credencialVencida(Documento $credencial): self
    {
        $fecha = $credencial->fecha_vencimiento?->format('d/m/Y');

        return new self(
            habilitado: false,
            motivo: $fecha
                ? "La Cédula de Pescador venció el {$fecha}."
                : 'La Cédula de Pescador no está vigente.',
            comoResolver: 'Renovar la Cédula de Pescador para la gestión en curso. Recién después se puede registrar este servicio.',
            credencial: $credencial,
        );
    }

    /**
     * Lo que se le manda a React. Nunca el modelo entero: la vista solo
     * necesita saber si puede seguir y qué decirle al operador.
     *
     * @return array<string, mixed>
     */
    public function paraVista(): array
    {
        return [
            'habilitado' => $this->habilitado,
            'motivo' => $this->motivo,
            'como_resolver' => $this->comoResolver,
            'credencial' => $this->credencial ? [
                'codigo_verificacion' => $this->credencial->codigo_verificacion,
                'fecha_emision' => $this->credencial->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $this->credencial->fecha_vencimiento?->toDateString(),
                'estado' => $this->credencial->estadoEfectivo()->value,
                'estado_etiqueta' => $this->credencial->estadoEfectivo()->etiqueta(),
            ] : null,
            'tramite_en_curso' => $this->tramiteEnCurso ? [
                // El trámite se identifica por su id y nada más: la columna
                // `codigo` se retiró, y esto seguía mandando un campo vacío.
                'id' => $this->tramiteEnCurso->id,
                'estado' => $this->tramiteEnCurso->estado->value,
                'estado_etiqueta' => $this->tramiteEnCurso->estado->etiqueta(),
                'estado_color' => $this->tramiteEnCurso->estado->color(),
            ] : null,
        ];
    }
}
