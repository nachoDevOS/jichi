import { Head } from '@inertiajs/react';
import { Contacto } from '@/components/publico/institucional/contacto';
import { Hero } from '@/components/publico/institucional/hero';
import { Pasos } from '@/components/publico/institucional/pasos';
import { Preguntas } from '@/components/publico/institucional/preguntas';
import { Servicios } from '@/components/publico/institucional/servicios';
import { Verificacion } from '@/components/publico/institucional/verificacion';
import LayoutInstitucional from '@/layouts/layout-institucional';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  PORTADA INSTITUCIONAL
 *
 *  Una sola página larga con anclas, que es lo que espera quien llega desde el
 *  teléfono: no hay nada que navegar, hay que leer de corrido.
 */
export default function Inicio({ portada }: { portada: InstitucionPortada }) {
    return (
        <LayoutInstitucional portada={portada}>
            <Head title="Inicio">
                <meta name="description" content={portada.descripcion} />
            </Head>

            <Hero portada={portada} />
            <Servicios />
            <Pasos />
            <Verificacion />
            <Preguntas />
            <Contacto portada={portada} />
        </LayoutInstitucional>
    );
}
