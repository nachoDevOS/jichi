import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { AvisoMaqueta } from '@/components/panel/tramites/aviso-maqueta';
import { FormularioGuiaTransporte } from '@/components/panel/tramites/formulario-guia-transporte';
import { filaVacia } from '@/components/panel/tramites/tabla-productos-guia';
import { VistaPreviaGuia } from '@/components/panel/tramites/vista-previa-guia';
import { Button } from '@/components/ui/button';
import LayoutPanel from '@/layouts/layout-panel';
import type {
    CatalogosGuiaTransporte,
    FormularioGuiaTransporte as DatosGuia,
    TipoTramiteOpcion,
} from '@/types/tramites';
import type { SolicitanteDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  NUEVO TRÁMITE — GUÍA ÚNICA DE TRANSPORTE DE PRODUCTOS ICTÍCOLAS
 * ============================================================================
 *
 * Mismo esquema que el Permiso por Faena: formulario a la izquierda, hoja
 * armándose a la derecha. La diferencia está en el contenido, no en la forma,
 * y por eso los dos archivos se parecen: el que aprenda uno entiende el otro.
 *
 * Este documento depende de otra secretaría que el permiso por faena
 * (Desarrollo Productivo y Economía Plural, no Recursos Naturales y Medio
 * Ambiente). No es un error: así está impreso en cada talonario, y por eso el
 * membrete viaja dentro de cada tipo de trámite en vez de estar fijo.
 */
interface Props {
    esMaqueta: boolean;
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
    catalogos: CatalogosGuiaTransporte;
}

export default function CrearGuiaTransporte({
    esMaqueta,
    tipo,
    solicitante,
    catalogos,
}: Props) {
    const [datos, setDatos] = useState<DatosGuia>({
        tipo: tipo.codigo,
        solicitante: solicitante.id,
        fecha: new Date().toISOString().slice(0, 10),
        nro_recibo: '',
        comerciante: '',
        documento_identidad: '',
        origen_lugar: '',
        origen_departamento: 'Beni',
        origen_provincia: '',
        origen_distrito: '',
        destino_lugar: '',
        destino_departamento: '',
        destino_provincia: '',
        destino_distrito: '',
        via: '',
        medio: '',
        transporte_nombre: '',
        transporte_placa: '',
        transporte_capacidad: '',
        productos: [filaVacia()],
        observaciones: '',
    });

    return (
        <LayoutPanel
            titulo="Guía Única de Transporte"
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
            <Head title="Guía Única de Transporte" />

            <div className="space-y-4">
                {esMaqueta && <AvisoMaqueta />}

                <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                    <FormularioGuiaTransporte
                        tipo={tipo}
                        solicitante={solicitante}
                        catalogos={catalogos}
                        onCambio={setDatos}
                    />

                    <div className="lg:sticky lg:top-6">
                        <p className="mb-2 text-sm font-semibold text-muted-foreground">
                            Vista previa del documento
                        </p>
                        <VistaPreviaGuia datos={datos} tipo={tipo} catalogos={catalogos} />
                    </div>
                </div>
            </div>
        </LayoutPanel>
    );
}
