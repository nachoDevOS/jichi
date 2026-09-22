<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  PORTADA INSTITUCIONAL — la cara pública del sistema
 *
 *  Todo lo que muestra sale de `configuraciones`: ningún dato de una persona ni
 *  del trabajo interno. Ver la regla 3 de CLAUDE.md.
 */
class InicioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('publico/inicio', [
            /*
             * Se llama `portada` y no `institucion` porque esa clave ya la
             * ocupa una prop compartida por HandleInertiaRequests, y una prop
             * de página con el mismo nombre la tapa sin avisar.
             */
            'portada' => fn (): array => $this->institucion(),
        ]);
    }

    /**
     * Los datos institucionales, todos de claves marcadas `publico` en la
     * tabla. Van con valor por defecto porque la portada tiene que dibujarse
     * igual en una base recién migrada, sin el seeder corrido.
     *
     * @return array<string, mixed>
     */
    private function institucion(): array
    {
        return [
            'nombre' => Configuracion::obtener('municipio.nombre', 'Gobierno Autónomo Departamental del Beni'),
            'sigla' => Configuracion::obtener('municipio.sigla', 'GAD-BENI'),
            'departamento' => Configuracion::obtener('municipio.departamento', 'Beni'),
            'direccion' => Configuracion::obtener('municipio.direccion'),
            'telefono' => Configuracion::obtener('municipio.telefono'),
            'email' => Configuracion::obtener('municipio.email'),

            // Todavía no es una clave sembrada: se atiende en horario de
            // oficina y el día que la unidad lo cambie, se agrega al seeder.
            'horario' => Configuracion::obtener('municipio.horario', 'Lunes a viernes, de 08:00 a 16:00'),

            'sistema' => Configuracion::obtener('sistema.nombre', 'Jichi'),
            'descripcion' => Configuracion::obtener(
                'sistema.descripcion',
                'Sistema de gestión de carnets, rubros y trámites del sector pesquero',
            ),
        ];
    }
}
