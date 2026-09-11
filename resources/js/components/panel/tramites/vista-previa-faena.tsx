import {
    BloqueQr,
    CODIGO_MUESTRA,
    HojaDocumento,
    Renglon,
} from '@/components/panel/tramites/hoja-documento';
import { fecha } from '@/lib/utils';
import type { FormularioPermisoFaena, TipoTramiteOpcion } from '@/types/tramites';

/**
 * Vista previa en vivo del Permiso por Faena.
 *
 * Reproduce el talonario del SEDAG renglón por renglón y se actualiza mientras
 * el operador escribe. Sirve para dos cosas: ver que lo cargado coincide con
 * el papel que se va a imprimir, y entender de un vistazo qué campo alimenta
 * qué parte del documento.
 */
export function VistaPreviaFaena({
    datos,
    tipo,
}: {
    datos: FormularioPermisoFaena;
    tipo: TipoTramiteOpcion;
}) {
    const hoy = new Date();

    return (
        <HojaDocumento
            titulo="PERMISO POR FAENA"
            membrete={tipo.membrete!}
            // En el talonario el número viene preimpreso. Acá se muestra el
            // que seguiría, como referencia: el definitivo lo entrega
            // CorrelativoService recién al guardar.
            numero="002052"
            pie={
                <div className="space-y-3">
                    <p className="text-[11px] leading-snug">
                        <span className="font-bold">NOTA.- </span>
                        {tipo.nota}
                    </p>

                    <div className="flex flex-wrap justify-between gap-x-4 gap-y-1 text-[10px]">
                        {tipo.copias?.map((copia) => <span key={copia}>{copia}</span>)}
                    </div>
                </div>
            }
        >
            {/* Recuadros de N° Recibo y monto, como en el papel. */}
            <div className="mb-4 flex flex-wrap items-center justify-center gap-3 text-[12px]">
                <span className="rounded border border-[#1f5c3d]/50 px-3 py-1">
                    N° Recibo:{' '}
                    <span className="font-mono font-semibold">{datos.nro_recibo || '—'}</span>
                </span>
                <span className="rounded-full border border-[#1f5c3d]/50 px-3 py-1 font-semibold">
                    Bs. {tipo.monto?.toFixed(2)}
                </span>
            </div>

            <p className="mb-1 text-[11px] leading-snug">{tipo.encabezado_legal}</p>
            <p className="mb-3 text-[12px] font-semibold">{tipo.autoriza}</p>

            <div className="space-y-2">
                <Renglon etiqueta="La embarcación:" valor={datos.embarcacion} />
                <Renglon etiqueta="De propiedad de:" valor={datos.propietario} />
                <Renglon etiqueta="Comandante de barco:" valor={datos.comandante} />

                <div className="flex flex-col gap-2 sm:flex-row sm:gap-4">
                    <Renglon
                        etiqueta="Bajo Matrícula Naval No.:"
                        valor={datos.matricula_naval}
                        className="flex-1"
                    />
                    <Renglon etiqueta="N° Kardex:" valor={datos.kardex} className="flex-1" />
                </div>

                <Renglon etiqueta="Pescar en la región desde:" valor={datos.region_desde} />
                <Renglon etiqueta="Hasta:" valor={datos.region_hasta} />
                <Renglon
                    etiqueta="Fecha de Salida:"
                    valor={datos.fecha_salida ? fecha(datos.fecha_salida) : ''}
                />
                <Renglon
                    etiqueta="Fecha de desembarque:"
                    valor={datos.fecha_desembarque ? fecha(datos.fecha_desembarque) : ''}
                />
                <Renglon
                    etiqueta="Cantidad autorizada de pescado extraído:"
                    valor={datos.cantidad_kg ? `${datos.cantidad_kg} Kg.` : ''}
                />
            </div>

            <p className="mt-5 text-center text-[12px]">
                Trinidad, {hoy.getDate()} de{' '}
                {hoy.toLocaleDateString('es-BO', { month: 'long' })} de {hoy.getFullYear()}
            </p>

            {/* El QR va junto a la firma, que es donde queda espacio libre en
                el talonario y donde el inspector lo busca. */}
            <div className="mt-4 flex items-end justify-between gap-4">
                <BloqueQr codigo={CODIGO_MUESTRA} />

                <p className="flex-1 text-center text-[11px] font-semibold">{tipo.responsable}</p>
            </div>
        </HojaDocumento>
    );
}
