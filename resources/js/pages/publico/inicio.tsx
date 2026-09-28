import { Head } from '@inertiajs/react';
import { Contacto } from '@/components/publico/institucional/contacto';
import { Especies } from '@/components/publico/institucional/especies';
import { Hero } from '@/components/publico/institucional/hero';
import { Pasos } from '@/components/publico/institucional/pasos';
import { Preguntas } from '@/components/publico/institucional/preguntas';
import { Sedag } from '@/components/publico/institucional/sedag';
import { Servicios } from '@/components/publico/institucional/servicios';
import { Verificacion } from '@/components/publico/institucional/verificacion';
import LayoutInstitucional from '@/layouts/layout-institucional';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  Portada institucional
 *
 *  Una sola página larga con anclas, que es lo que espera quien llega desde el
 *  teléfono: no hay nada que navegar, hay que leer de corrido.
 */
export default function Inicio({
    portada,
    especies,
    provincias,
}: {
    portada: InstitucionPortada;
    especies: string[];
    provincias: string[];
}) {
    return (
        <LayoutInstitucional portada={portada}>
            <Head title="Inicio">
                <meta name="description" content={portada.descripcion} />
            </Head>

            <Hero portada={portada} especies={especies.length} provincias={provincias.length} />
            <Sedag provincias={provincias} />
            <Especies especies={especies} />
            <Servicios />
            <Pasos />
            <Verificacion />
            <Preguntas />
            <Contacto portada={portada} />
        </LayoutInstitucional>
    );
}
