import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { AvisoMaqueta } from '@/components/panel/tramites/aviso-maqueta';
import {
    FormularioCedulaPescador,
    valoresInicialesCedula,
} from '@/components/panel/tramites/formulario-cedula-pescador';
import { VistaPreviaCedula } from '@/components/panel/tramites/vista-previa-cedula';
import { Button } from '@/components/ui/button';
import LayoutPanel from '@/layouts/layout-panel';
import type {
    CatalogosCedulaPescador,
    FormularioCedulaPescador as DatosCedula,
    TipoTramiteOpcion,
} from '@/types/tramites';
import type { SolicitanteDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  NUEVO TRÁMITE — CÉDULA DE PESCADOR
 * ============================================================================
 *
 * Mismo esquema que los otros dos servicios: formulario a la izquierda,
 * documento armándose a la derecha.
 *
 * La diferencia es que acá el documento no es una hoja sino una TARJETA CR80
 * plastificada, y lleva fotografía.
 *
 * La foto sale de la FICHA del solicitante, no del trámite. La vista previa usa
 * `solicitante.foto_url`, salvo cuando la ficha no tiene ninguna: ahí el
 * formulario deja cargarla y avisa hacia arriba la URL temporal, para que la
 * credencial se dibuje con la cara recién elegida antes de guardar nada.
 */
interface Props {
    esMaqueta: boolean;
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
    catalogos: CatalogosCedulaPescador;
}

export default function CrearCedulaPescador({
    esMaqueta,
    tipo,
    solicitante,
    catalogos,
}: Props) {
    /*
     * La URL temporal de la foto recién elegida, cuando la ficha no tenía
     * ninguna. NULL significa «usar la de la ficha», que es el caso normal.
     */
    const [fotoNueva, setFotoNueva] = useState<string | null>(null);

    /*
     * EL MISMO objeto con el que arranca el formulario, no una copia escrita a
     * mano. Este estado solo alimenta la vista previa —el formulario tiene el
     * suyo y es el que se envía—, pero si arrancaran distintos, la credencial
     * de la derecha saldría en blanco hasta que el operador tocara un campo.
     *
     * Va como función y no como objeto: así se arma una sola vez, en el primer
     * render, y no en cada tecla que se escribe.
     */
    const [datos, setDatos] = useState<DatosCedula>(() =>
        valoresInicialesCedula(tipo, solicitante, catalogos),
    );

    return (
        <LayoutPanel
            titulo="Cédula de Pescador"
            descripcion={`${tipo.area} · ${tipo.vigencia}`}
            acciones={
                <Link href={route('tramites.create')}>
                    <Button variant="outline">
                        <ArrowLeft className="size-4" />
                        Cambiar de servicio
                    </Button>
                </Link>
            }
        >
            <Head title="Cédula de Pescador" />

            <div className="space-y-4">
                {esMaqueta && <AvisoMaqueta />}

                <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                    <FormularioCedulaPescador
                        tipo={tipo}
                        solicitante={solicitante}
                        catalogos={catalogos}
                        onCambio={setDatos}
                        onFotoNueva={setFotoNueva}
                    />

                    <div className="lg:sticky lg:top-6">
                        <p className="mb-2 text-sm font-semibold text-muted-foreground">
                            Vista previa de la credencial
                        </p>
                        <VistaPreviaCedula
                            datos={datos}
                            tipo={tipo}
                            foto={fotoNueva ?? solicitante.foto_url}
                        />
                    </div>
                </div>
            </div>
        </LayoutPanel>
    );
}
