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
 *     ¿la persona ya tiene carnet de la gestión en curso?
 *         NO  ->  EmisionInicial   (hay que crear el carnet)
 *         SÍ  ->  AdicionRubro     (se reutiliza el que tiene)
 *
 * Dejarlo como un selector del formulario sería pedirle a ventanilla que
 * adivine algo que el sistema ya sabe. Y equivocarse ahí no es un detalle: un
 * «emisión inicial» marcado de más intenta crear un segundo carnet para la
 * misma gestión, que el índice único rechaza y voltea el trámite entero.
 *
 * Quien decide es SolicitudCarnetService::registrar(). Este enum solo pone
 * nombre a las dos posibilidades y las etiquetas que se muestran en pantalla.
 */
enum TipoTramite: string
{
    case EmisionInicial = 'emision_inicial';
    case AdicionRubro = 'adicion_rubro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EmisionInicial => 'Emisión Inicial',
            self::AdicionRubro => 'Adición de Rubro',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::EmisionInicial => 'Primer carnet de la gestión: se emite el documento y se habilita el rubro solicitado.',
            self::AdicionRubro => 'El beneficiario ya tiene carnet vigente en esta gestión: se le suma un rubro más.',
        };
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
            self::AdicionRubro => 'violet',
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
