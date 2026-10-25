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
    ];

    /** La ruta del archivo del tutorial: `mp4` el video, `jpg` la portada. */
    public static function archivo(string $tutorial, string $extension): string
    {
        return resource_path("manual/{$tutorial}.{$extension}");
    }
}
