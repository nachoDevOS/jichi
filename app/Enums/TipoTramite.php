<?php

namespace App\Enums;

/**
 * Los dos tipos de solicitud que existen.
 *
 * ----------------------------------------------------------------------------
 *  ESTO NO LO ELIGE EL OPERADOR
 * ----------------------------------------------------------------------------
 *
 * El tipo sale de una sola pregunta, y la respuesta está en la base:
 *
 *     ¿la persona ya tiene carnet DE ESTE RUBRO en la gestión en curso?
 *         NO  ->  EmisionInicial   (hay que crear el carnet)
 *         SÍ  ->  Actualizacion    (se reutiliza el que tiene)
 *
 * Dejarlo como un selector del formulario sería pedirle a ventanilla que
 * adivine algo que el sistema ya sabe. Y equivocarse ahí no es un detalle: un
 * «emisión inicial» marcado de más intenta crear un segundo carnet del mismo
 * rubro para la misma gestión, que el índice único rechaza y voltea el trámite
 * entero.
 *
 * Quien decide es SolicitudCarnetService::registrar(). Este enum solo pone
 * nombre a las dos posibilidades y las etiquetas que se muestran en pantalla.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ YA NO EXISTE «ADICIÓN DE RUBRO»
 * ----------------------------------------------------------------------------
 *
 * Porque dejó de describir nada. Con el modelo viejo el carnet era uno por
 * persona y gestión, y pedir una actividad más lo hacía CRECER: eso era la
 * adición. Hoy cada actividad es un carnet propio, así que pedir un rubro más
 * no agranda nada — emite un documento nuevo, y eso ya tiene nombre: emisión
 * inicial, la del carnet de ESE rubro.
 *
 * Lo que quedó sin nombre es el otro caso: presentar un trámite sobre un carnet
 * que ya existe, para corregir el cupo o la asociación. Eso es la ACTUALIZACIÓN.
 */
enum TipoTramite: string
{
    case EmisionInicial = 'emision_inicial';
    case Actualizacion = 'actualizacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EmisionInicial => 'Emisión Inicial',
            self::Actualizacion => 'Actualización',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::EmisionInicial => 'El beneficiario no tenía carnet de este rubro en la gestión: se emite el documento.',
            self::Actualizacion => 'Ya tiene carnet vigente de este rubro: se actualizan los datos autorizados sobre el mismo documento.',
        };
    }

    /** ¿Este tipo hace nacer un carnet? */
    public function emiteCarnet(): bool
    {
        return $this === self::EmisionInicial;
    }

    /**
     * Clases de color del badge en las tablas de React.
     *
     * Devuelve el NOMBRE del color y no las clases armadas con texto, porque
     * Tailwind solo incluye en el CSS final las clases que puede leer
     * literalmente en el código: un `bg-${color}-100` escrito así nunca llega
     * a la hoja de estilos y el badge sale sin fondo.
     */
    public function color(): string
    {
        return match ($this) {
            self::EmisionInicial => 'sky',
            self::Actualizacion => 'violet',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $tipo): array => [
            'value' => $tipo->value,
            'label' => $tipo->etiqueta(),
            'color' => $tipo->color(),
        ], self::cases());
    }
}
