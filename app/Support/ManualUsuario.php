<?php

namespace App\Support;

/**
 *  Manual de usuario — los videos tutoriales del panel. Cada uno muestra un
 *  trámite y se ofrece solo a quien tiene el permiso de hacerlo.
 *  Los archivos viven en resources/manual/; cómo se graban, en docs/manual/README.md.
 */
class ManualUsuario
{
    /** Los capítulos llevan el segundo del video donde empieza cada paso. */
    public const TUTORIALES = [
        'registrar-beneficiario' => [
            'titulo' => 'Buscar y registrar un beneficiario',
            'descripcion' => 'Cómo localizar a una persona en el padrón y, si no está, registrarla con los datos de su cédula.',
            'modulo' => 'Beneficiarios',
            'permiso' => 'beneficiarios.crear',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Presentación'],
                ['segundo' => 4, 'titulo' => 'Entrar a Beneficiarios'],
                ['segundo' => 12, 'titulo' => 'Buscar por cédula o nombre'],
                ['segundo' => 26, 'titulo' => 'Nuevo beneficiario'],
                ['segundo' => 33, 'titulo' => 'Documento de identidad'],
                ['segundo' => 40, 'titulo' => 'Nombre y datos personales'],
                ['segundo' => 54, 'titulo' => 'Contacto'],
                ['segundo' => 70, 'titulo' => 'Fotografía (opcional)'],
                ['segundo' => 74, 'titulo' => 'Registrar'],
                ['segundo' => 80, 'titulo' => 'La ficha del beneficiario'],
                ['segundo' => 86, 'titulo' => 'Localizarlo en el padrón'],
            ],
        ],
        'ser-pescador' => [
            'titulo' => 'Habilitar a un pescador: autorización, carnet y faena',
            'descripcion' => 'Los tres trámites en orden: la Autorización de Pesca, el carnet de pescador y el permiso de faena, con el pago de cada uno en Recaudaciones.',
            'modulo' => 'Aprovechamiento pesquero',
            'permiso' => 'aprovechamientos.crear',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Presentación'],
                ['segundo' => 9, 'titulo' => 'Registrar la autorización de pesca'],
                ['segundo' => 49, 'titulo' => 'Pago de la autorización'],
                ['segundo' => 88, 'titulo' => 'Imprimir la autorización'],
                ['segundo' => 104, 'titulo' => 'Registrar el carnet de pescador'],
                ['segundo' => 138, 'titulo' => 'Pago del carnet'],
                ['segundo' => 176, 'titulo' => 'Imprimir el carnet'],
                ['segundo' => 190, 'titulo' => 'Registrar el permiso de faena'],
                ['segundo' => 232, 'titulo' => 'Pago de la faena'],
                ['segundo' => 270, 'titulo' => 'Imprimir el permiso de faena'],
                ['segundo' => 286, 'titulo' => 'Listo para salir a pescar'],
            ],
        ],
        'ser-comercializador' => [
            'titulo' => 'Comercializador: carnet y guía de transporte',
            'descripcion' => 'El carnet de comercializador y la guía de un traslado, comprándole a un pescador: sus kilos se descuentan de su autorización.',
            'modulo' => 'Guías de transporte',
            'permiso' => 'guias.crear',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Presentación'],
                ['segundo' => 9, 'titulo' => 'Registrar el carnet de comercializador'],
                ['segundo' => 51, 'titulo' => 'Pago del carnet'],
                ['segundo' => 90, 'titulo' => 'Imprimir el carnet'],
                ['segundo' => 106, 'titulo' => 'Emitir la guía'],
                ['segundo' => 116, 'titulo' => 'A qué pescador le compró'],
                ['segundo' => 134, 'titulo' => 'Productos y kilos'],
                ['segundo' => 157, 'titulo' => 'Ruta y transporte'],
                ['segundo' => 198, 'titulo' => 'Pago de la guía'],
                ['segundo' => 230, 'titulo' => 'Imprimir la guía'],
                ['segundo' => 245, 'titulo' => 'Cómo baja el cupo del pescador'],
                ['segundo' => 261, 'titulo' => 'Listo para trasladar'],
            ],
        ],
    ];

    /** La ruta del archivo del tutorial: `mp4` el video, `jpg` la portada. */
    public static function archivo(string $tutorial, string $extension): string
    {
        return resource_path("manual/{$tutorial}.{$extension}");
    }
}
