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
            'siguiente' => 'autorizacion-pesca',
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
        'autorizacion-pesca' => [
            'titulo' => 'Autorización de Pesca',
            'descripcion' => 'Registrar la bolsa de kilos del pescador para la gestión, cargar su pago y entregar el papel impreso.',
            'modulo' => 'Pescador · 1 de 3',
            'permiso' => 'aprovechamientos.crear',
            'siguiente' => 'carnet-pescador',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Presentación'],
                ['segundo' => 9, 'titulo' => 'Registrar la autorización'],
                ['segundo' => 49, 'titulo' => 'Pago en Recaudaciones'],
                ['segundo' => 88, 'titulo' => 'Imprimir la autorización'],
            ],
        ],
        'carnet-pescador' => [
            'titulo' => 'Carnet de pescador',
            'descripcion' => 'La credencial del año, respaldada por la autorización aprobada: registrarla, pagarla e imprimirla.',
            'modulo' => 'Pescador · 2 de 3',
            'permiso' => 'carnets.crear',
            'siguiente' => 'permiso-faena',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Registrar el carnet'],
                ['segundo' => 34, 'titulo' => 'Pago en Recaudaciones'],
                ['segundo' => 72, 'titulo' => 'Imprimir el carnet'],
            ],
        ],
        'permiso-faena' => [
            'titulo' => 'Permiso de faena',
            'descripcion' => 'El permiso de cada salida de pesca: los datos del talonario, el pago y el papel que lleva el pescador.',
            'modulo' => 'Pescador · 3 de 3',
            'permiso' => 'faenas.crear',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Registrar la faena'],
                ['segundo' => 41, 'titulo' => 'Pago en Recaudaciones'],
                ['segundo' => 80, 'titulo' => 'Imprimir el permiso'],
                ['segundo' => 95, 'titulo' => 'Listo para salir a pescar'],
            ],
        ],
        'carnet-comercializador' => [
            'titulo' => 'Carnet de comercializador',
            'descripcion' => 'La credencial de quien compra y traslada pescado: no lleva autorización. Registrarla, pagarla e imprimirla.',
            'modulo' => 'Comercializador · 1 de 2',
            'permiso' => 'carnets.crear',
            'siguiente' => 'guia-transporte',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Presentación'],
                ['segundo' => 9, 'titulo' => 'Registrar el carnet'],
                ['segundo' => 51, 'titulo' => 'Pago en Recaudaciones'],
                ['segundo' => 90, 'titulo' => 'Imprimir el carnet'],
            ],
        ],
        'guia-transporte' => [
            'titulo' => 'Guía de transporte',
            'descripcion' => 'Un traslado de pescado comprado a un pescador: sus kilos se descuentan de su autorización. Del registro a la guía impresa.',
            'modulo' => 'Comercializador · 2 de 2',
            'permiso' => 'guias.crear',
            'capitulos' => [
                ['segundo' => 0, 'titulo' => 'Emitir la guía'],
                ['segundo' => 11, 'titulo' => 'A qué pescador le compró'],
                ['segundo' => 29, 'titulo' => 'Productos y kilos'],
                ['segundo' => 51, 'titulo' => 'Ruta y transporte'],
                ['segundo' => 93, 'titulo' => 'Pago en Recaudaciones'],
                ['segundo' => 124, 'titulo' => 'Imprimir la guía'],
                ['segundo' => 139, 'titulo' => 'Cómo baja el cupo del pescador'],
                ['segundo' => 155, 'titulo' => 'Listo para trasladar'],
            ],
        ],
    ];

    /** La ruta del archivo del tutorial: `mp4` el video, `jpg` la portada. */
    public static function archivo(string $tutorial, string $extension): string
    {
        return resource_path("manual/{$tutorial}.{$extension}");
    }
}
