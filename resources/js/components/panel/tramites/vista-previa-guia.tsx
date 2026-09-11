import {
    BloqueQr,
    CODIGO_MUESTRA,
    HojaDocumento,
    Renglon,
    SeccionHoja,
} from '@/components/panel/tramites/hoja-documento';
import { importeDeFila, totalImporte, totalKg } from '@/components/panel/tramites/tabla-productos-guia';
import { bs, fecha } from '@/lib/utils';
import type {
    CatalogosGuiaTransporte,
    FormularioGuiaTransporte,
    TipoTramiteOpcion,
} from '@/types/tramites';

/**
 * Vista previa en vivo de la Guía Única de Transporte.
 *
 * Reproduce las cuatro secciones del talonario, incluida la tabla de productos
 * con su fila de TOTALES. Se actualiza mientras el operador carga.
 */
export function VistaPreviaGuia({
    datos,
    tipo,
    catalogos,
}: {
    datos: FormularioGuiaTransporte;
    tipo: TipoTramiteOpcion;
    catalogos: CatalogosGuiaTransporte;
}) {
    // Se traduce el valor guardado a la etiqueta que ve la gente: en el papel
    // dice "Fluvial", no "fluvial".
    const etiqueta = (lista: { value: string; label: string }[], valor: string) =>
        lista.find((o) => o.value === valor)?.label ?? '';

    return (
        <HojaDocumento
            titulo="Guía Única de Transporte de Productos Ictícolas"
            membrete={tipo.membrete!}
            numero="000309"
            pie={
                <div className="space-y-4">
                    <p className="text-[11px] leading-snug">{tipo.nota}</p>

                    <div className="flex items-end gap-4">
                        <BloqueQr codigo={CODIGO_MUESTRA} />

                        <div className="flex flex-1 justify-between gap-6 text-[10px]">
                            {tipo.firmas?.map((firma) => (
                                <span
                                    key={firma}
                                    className="flex-1 border-t border-[#1f5c3d]/40 pt-1 text-center"
                                >
                                    {firma}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            }
        >
            <div className="mb-4 flex flex-wrap items-center gap-3 text-[12px]">
                <span className="rounded border border-[#1f5c3d]/50 px-3 py-1">
                    Fecha:{' '}
                    <span className="font-semibold">
                        {datos.fecha ? fecha(datos.fecha) : '—'}
                    </span>
                </span>
                <span className="rounded border border-[#1f5c3d]/50 px-3 py-1">
                    N° Recibo:{' '}
                    <span className="font-mono font-semibold">{datos.nro_recibo || '—'}</span>
                </span>
            </div>

            <SeccionHoja titulo="A.- Interesado">
                <div className="space-y-2">
                    <Renglon etiqueta="Comerciante:" valor={datos.comerciante} />
                    <Renglon etiqueta="Documento de identidad:" valor={datos.documento_identidad} />
                </div>
            </SeccionHoja>

            <SeccionHoja titulo="B.- Ubicación">
                <table className="w-full border-collapse text-[11px]">
                    <thead>
                        <tr className="border-b border-[#1f5c3d]/40 text-left">
                            <th className="py-1 pr-2 font-semibold">&nbsp;</th>
                            <th className="py-1 pr-2 font-semibold">Lugar</th>
                            <th className="py-1 pr-2 font-semibold">Departamento</th>
                            <th className="py-1 pr-2 font-semibold">Provincia</th>
                            <th className="py-1 font-semibold">Distrito o cuenca</th>
                        </tr>
                    </thead>
                    <tbody>
                        <FilaUbicacion
                            rotulo="Producción u origen"
                            lugar={datos.origen_lugar}
                            departamento={datos.origen_departamento}
                            provincia={datos.origen_provincia}
                            distrito={datos.origen_distrito}
                        />
                        <FilaUbicacion
                            rotulo="Destino"
                            lugar={datos.destino_lugar}
                            departamento={datos.destino_departamento}
                            provincia={datos.destino_provincia}
                            distrito={datos.destino_distrito}
                        />
                    </tbody>
                </table>

                <p className="mt-2 text-[11px]">
                    Vía:{' '}
                    <span className="font-semibold">
                        {etiqueta(catalogos.vias, datos.via) || '—'}
                    </span>
                </p>
            </SeccionHoja>

            <SeccionHoja titulo="C.- Transporte">
                <div className="space-y-2">
                    <Renglon
                        etiqueta="Medio:"
                        valor={etiqueta(catalogos.medios, datos.medio)}
                    />
                    <div className="flex flex-col gap-2 sm:flex-row sm:gap-4">
                        <Renglon
                            etiqueta="Nombre/Tipo:"
                            valor={datos.transporte_nombre}
                            className="flex-1"
                        />
                        <Renglon
                            etiqueta="Placa:"
                            valor={datos.transporte_placa}
                            className="flex-1"
                        />
                        <Renglon
                            etiqueta="Cap. Máx.:"
                            valor={datos.transporte_capacidad}
                            className="flex-1"
                        />
                    </div>
                </div>
            </SeccionHoja>

            <SeccionHoja titulo="D.- Productos hidrobiológicos">
                <table className="w-full border-collapse text-[11px]">
                    <thead>
                        <tr className="border-b border-[#1f5c3d]/40 text-left">
                            <th className="py-1 pr-2 font-semibold">Especie</th>
                            <th className="py-1 pr-2 font-semibold">Presentación</th>
                            <th className="py-1 pr-2 text-right font-semibold">Cant. Kg</th>
                            <th className="py-1 pr-2 text-right font-semibold">Precio Kg</th>
                            <th className="py-1 text-right font-semibold">Imp. total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.productos.map((p) => (
                            <tr key={p.id} className="border-b border-[#1f5c3d]/15">
                                <td className="py-1 pr-2">{p.especie || '—'}</td>
                                <td className="py-1 pr-2">
                                    {etiqueta(catalogos.presentaciones, p.presentacion) || '—'}
                                </td>
                                <td className="py-1 pr-2 text-right">{p.cantidad_kg || '—'}</td>
                                <td className="py-1 pr-2 text-right">{p.precio_kg || '—'}</td>
                                <td className="py-1 text-right font-medium">
                                    {importeDeFila(p) ? bs(importeDeFila(p)) : '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-bold">
                            <td className="py-1.5 pr-2" colSpan={2}>
                                TOTALES
                            </td>
                            <td className="py-1.5 pr-2 text-right">
                                {totalKg(datos.productos).toFixed(2)}
                            </td>
                            <td />
                            <td className="py-1.5 text-right">
                                {bs(totalImporte(datos.productos))}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </SeccionHoja>

            {datos.observaciones && (
                <SeccionHoja titulo="Observaciones">
                    <p className="text-[11px] whitespace-pre-line">{datos.observaciones}</p>
                </SeccionHoja>
            )}
        </HojaDocumento>
    );
}

function FilaUbicacion({
    rotulo,
    lugar,
    departamento,
    provincia,
    distrito,
}: {
    rotulo: string;
    lugar: string;
    departamento: string;
    provincia: string;
    distrito: string;
}) {
    return (
        <tr className="border-b border-[#1f5c3d]/15">
            <td className="py-1 pr-2 font-semibold">{rotulo}</td>
            <td className="py-1 pr-2">{lugar || '—'}</td>
            <td className="py-1 pr-2">{departamento || '—'}</td>
            <td className="py-1 pr-2">{provincia || '—'}</td>
            <td className="py-1">{distrito || '—'}</td>
        </tr>
    );
}
