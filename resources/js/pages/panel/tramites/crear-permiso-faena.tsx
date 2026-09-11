import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { AvisoMaqueta } from '@/components/panel/tramites/aviso-maqueta';
import { FormularioPermisoFaena } from '@/components/panel/tramites/formulario-permiso-faena';
import { VistaPreviaFaena } from '@/components/panel/tramites/vista-previa-faena';
import { Button } from '@/components/ui/button';
import LayoutPanel from '@/layouts/layout-panel';
import type {
    FormularioPermisoFaena as DatosFaena,
    SolicitanteDelTramite,
    TipoTramiteOpcion,
} from '@/types/tramites';

/**
 * ============================================================================
 *  NUEVO TRÁMITE — PERMISO POR FAENA
 * ============================================================================
 *
 * Formulario a la izquierda, vista previa del documento a la derecha.
 *
 * ¿PARA QUÉ LA VISTA PREVIA?
 *
 * Porque este permiso se imprime en un talonario preimpreso y el operador
 * tiene el papel en la mano. Ver el documento armándose mientras carga le
 * permite comparar renglón por renglón antes de guardar, en vez de imprimir,
 * descubrir el error y perder una hoja del talonario.
 *
 * El estado vive acá y no dentro del formulario porque lo necesitan DOS
 * componentes hermanos: el formulario para editarlo y la vista previa para
 * mostrarlo. Cuando dos hermanos comparten un dato, este sube al padre común
 * — en React se llama "levantar el estado".
 */
interface Props {
    esMaqueta: boolean;
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
}

export default function CrearPermisoFaena({ esMaqueta, tipo, solicitante }: Props) {
    const [datos, setDatos] = useState<DatosFaena>({
        tipo: tipo.codigo,
        solicitante: solicitante.id,
        nro_recibo: '',
        embarcacion: '',
        propietario: '',
        comandante: '',
        matricula_naval: '',
        kardex: '',
        region_desde: '',
        region_hasta: '',
        fecha_salida: '',
        fecha_desembarque: '',
        cantidad_kg: '',
        observaciones: '',
    });

    return (
        <LayoutPanel
            titulo="Permiso por Faena"
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
            <Head title="Permiso por Faena" />

            <div className="space-y-4">
                {esMaqueta && <AvisoMaqueta />}

                {/* lg:items-start evita que las dos columnas se estiren a la
                    misma altura: la vista previa queda pegada arriba y no
                    crece con un formulario mucho más largo. */}
                <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                    <FormularioPermisoFaena
                        tipo={tipo}
                        solicitante={solicitante}
                        onCambio={setDatos}
                    />

                    {/* lg:sticky mantiene el documento a la vista mientras se
                        baja por el formulario. */}
                    <div className="lg:sticky lg:top-6">
                        <p className="mb-2 text-sm font-semibold text-muted-foreground">
                            Vista previa del documento
                        </p>
                        <VistaPreviaFaena datos={datos} tipo={tipo} />
                    </div>
                </div>
            </div>
        </LayoutPanel>
    );
}
