import { Head, usePage } from '@inertiajs/react';
import { Banknote, FileCheck2, FileText, Users } from 'lucide-react';
import { DocumentosPorTipo } from '@/components/panel/dashboard/documentos-por-tipo';
import { GraficoRecaudacionMensual } from '@/components/panel/dashboard/grafico-recaudacion-mensual';
import { GraficoRecaudacionPorArea } from '@/components/panel/dashboard/grafico-recaudacion-por-area';
import { Indicador } from '@/components/panel/dashboard/indicador';
import { PanelAlertas } from '@/components/panel/dashboard/panel-alertas';
import { TablaUltimosTramites } from '@/components/panel/dashboard/tabla-ultimos-tramites';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    AlertasOperativas,
    DocumentosPorTipo as FilaDocumento,
    RecaudacionArea,
    RecaudacionMes,
    ResumenDelDia,
    UltimoTramite,
} from '@/types/dashboard';

/**
 * ============================================================================
 *  PANEL PRINCIPAL
 * ============================================================================
 *
 * Esta pantalla NO trae datos por su cuenta: los recibe ya listos desde
 * App\Http\Controllers\Panel\DashboardController.
 *
 * Cada clave del array que allá se pasó a Inertia::render() llega acá como una
 * prop con el mismo nombre. Si en PHP se escribió 'porArea' => [...], acá
 * aparece `porArea`. Esa es toda la conexión: no hay fetch ni API de por medio.
 *
 * El archivo quedó corto a propósito: solo ORDENA los bloques en la pantalla.
 * Cada bloque es un componente en components/panel/dashboard/ y se lee solo.
 */

/**
 * Las props que envía el controlador. Escribirlas obliga a TypeScript a avisar
 * si se usa un campo que el backend no manda, o si cambia un nombre en PHP.
 */
interface Props {
    resumen: ResumenDelDia;
    porArea: RecaudacionArea[];
    porMes: RecaudacionMes[];
    porTipoDocumento: FilaDocumento[];
    alertas: AlertasOperativas;
    ultimosTramites: UltimoTramite[];
}

export default function Dashboard({
    // Esto es "desestructuración": en vez de recibir un objeto `props` y
    // escribir props.resumen, se sacan directo las claves por su nombre.
    resumen,
    porArea,
    porMes,
    porTipoDocumento,
    alertas,
    ultimosTramites,
}: Props) {
    // Las props compartidas (usuario, institución) no vienen por parámetro:
    // se piden con usePage() desde cualquier componente, sin encadenarlas.
    const { institucion } = usePage<PageProps>().props;
    const moneda = institucion?.moneda ?? 'Bs';

    return (
        <LayoutPanel
            titulo="Panel principal"
            descripcion={`${institucion?.municipio ?? 'GAD-BENI'} — resumen operativo`}
        >
            {/* <Head> escribe el <title> de la pestaña del navegador. */}
            <Head title="Panel" />

            <div className="space-y-6">
                {/* ---------- Fila 1: los cuatro números del día ---------- */}
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Indicador
                        icono={Banknote}
                        etiqueta="Recaudado hoy"
                        valor={bs(resumen.recaudado_hoy, moneda)}
                        pie={`Mes en curso: ${bs(resumen.recaudado_mes, moneda)}`}
                        destacado
                    />
                    <Indicador
                        icono={FileText}
                        etiqueta="Trámites recibidos hoy"
                        valor={resumen.tramites_recibidos.toString()}
                        pie={`${resumen.tramites_pendientes} pendientes en total`}
                    />
                    <Indicador
                        icono={FileCheck2}
                        etiqueta="Documentos emitidos hoy"
                        valor={resumen.documentos_emitidos.toString()}
                        pie={`${alertas.documentos_vencidos} vencidos en el sistema`}
                    />
                    <Indicador
                        icono={Users}
                        etiqueta="Solicitantes atendidos"
                        valor={resumen.solicitantes_atendidos.toString()}
                        pie={`${alertas.pendientes_aprobacion} esperando aprobación`}
                    />
                </section>

                {/* ---------- Fila 2: los dos gráficos ---------- */}
                {/* grid de 5 columnas: la línea ocupa 3 y las barras 2. */}
                <section className="grid gap-4 lg:grid-cols-5">
                    <GraficoRecaudacionMensual datos={porMes} moneda={moneda} />
                    <GraficoRecaudacionPorArea datos={porArea} moneda={moneda} />
                </section>

                {/* ---------- Fila 3: tabla + alertas ---------- */}
                <section className="grid gap-4 lg:grid-cols-5">
                    <TablaUltimosTramites tramites={ultimosTramites} />

                    <div className="space-y-4 lg:col-span-2">
                        <PanelAlertas alertas={alertas} />
                        <DocumentosPorTipo datos={porTipoDocumento} />
                    </div>
                </section>
            </div>
        </LayoutPanel>
    );
}
