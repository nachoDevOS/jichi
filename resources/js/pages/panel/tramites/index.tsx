import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, FilePlus2, FileText, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha, hace, hora } from '@/lib/utils';
import type { OpcionEnum, Paginado } from '@/types';
import type { TramiteFila } from '@/types/tramites';

/** El listado de expedientes, con filtros por estado, tipo y gestión. */
export default function IndiceTramites({
    tramites,
    filtros,
    estados,
    tipos,
    gestiones,
    opcionesPorPagina,
}: {
    tramites: Paginado<TramiteFila>;
    filtros: {
        buscar: string | null;
        estado: string | null;
        tipo: string | null;
        gestion: number | null;
        por_pagina: number;
    };
    /** Salen de los enums de PHP, no escritos acá: ver TramiteController::index(). */
    estados: OpcionEnum[];
    tipos: OpcionEnum[];
    gestiones: number[];
    opcionesPorPagina: number[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * El expediente que se está por borrar, o null si el diálogo está cerrado.
     *
     * Se guarda la FILA entera y no solo el id porque el texto de confirmación
     * nombra al beneficiario y al rubro: «el trámite 14 de Fulano (Pescador)».
     * Un diálogo que solo dice «¿eliminar?» invita a confirmarlo sin leer, y
     * esto no se puede deshacer.
     */
    const [aEliminar, setAEliminar] = useState<TramiteFila | null>(null);

    /*
     * EL MOTIVO DEL BORRADO, OBLIGATORIO.
     *
     * Se usa useForm y no un useState suelto porque hace falta lo que trae:
     * `processing` para apagar los botones mientras viaja, y `errors` para
     * pintar lo que responda el servidor bajo el campo.
     *
     * El método va en `_method` porque el borrado lleva cuerpo —el motivo— y
     * un DELETE con cuerpo no atraviesa bien todos los proxys. Es el mismo
     * truco que usan los formularios con archivos para mandar un PUT.
     */
    const borrado = useForm({ motivo: '', _method: 'delete' as const });

    function eliminar() {
        if (!aEliminar) return;

        borrado.post(route('tramites.destroy', aEliminar.id), {
            // El cierre va en onSuccess y no en onFinish: si el servidor rechaza
            // —el motivo quedó corto, u otro supervisor aprobó el trámite entre
            // medio— la ventana tiene que quedar abierta con el texto escrito y
            // el error a la vista, en vez de cerrarse y perderlo.
            onSuccess: () => {
                setAEliminar(null);
                borrado.reset();
            },
        });
    }

    function filtrar(valores: Record<string, string | number | null>) {
        router.get(
            route('tramites.index'),
            {
                buscar,
                estado: filtros.estado,
                tipo: filtros.tipo,
                gestion: filtros.gestion,
                por_pagina: filtros.por_pagina,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Trámites de carnet"
            descripcion="Expedientes de emisión y actualización de carnets."
            acciones={
                puede('tramites.crear') && (
                    <Button onClick={() => router.visit(route('tramites.create'))}>
                        <FilePlus2 className="size-4" />
                        Nueva solicitud
                    </Button>
                )
            }
        >
            <Head title="Trámites" />

            <Card>
                {/*
                    Misma barra que en Beneficiarios: «Mostrar N» a la izquierda,
                    buscador a la derecha en cuatro columnas, y en el medio los
                    filtros propios de esta pantalla.

                    Todo se resuelve en el SERVIDOR. Los valores de estado y tipo
                    salen de los enums de PHP (ver TramiteController::index) y no
                    escritos acá: repetidos en los dos lados, algún día dirían
                    cosas distintas y el filtro dejaría de encontrar nada.
                */}
                <div className="grid grid-cols-1 gap-3 border-b border-border p-4 sm:grid-cols-12 sm:items-end">
                    <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-3">
                        Mostrar
                        <Select
                            className="w-auto"
                            value={filtros.por_pagina}
                            onChange={(e) => filtrar({ por_pagina: e.target.value })}
                            aria-label="Registros por página"
                        >
                            {opcionesPorPagina.map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </Select>
                        registros
                    </label>

                    <Select
                        className="sm:col-span-2"
                        value={filtros.estado ?? ''}
                        onChange={(e) => filtrar({ estado: e.target.value || null })}
                        aria-label="Estado"
                    >
                        <option value="">Todos los estados</option>
                        {estados.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>

                    <Select
                        className="sm:col-span-2"
                        value={filtros.tipo ?? ''}
                        onChange={(e) => filtrar({ tipo: e.target.value || null })}
                        aria-label="Tipo de trámite"
                    >
                        <option value="">Todos los tipos</option>
                        {tipos.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>

                    <Select
                        className="sm:col-span-1"
                        value={filtros.gestion ?? ''}
                        onChange={(e) => filtrar({ gestion: e.target.value || null })}
                        aria-label="Gestión"
                    >
                        <option value="">Año</option>
                        {gestiones.map((g) => (
                            <option key={g} value={g}>
                                {g}
                            </option>
                        ))}
                    </Select>

                    <form
                        className="relative sm:col-span-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            filtrar({});
                        }}
                    >
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-9"
                            placeholder="Buscar…"
                            value={buscar}
                            onChange={(e) => setBuscar(e.target.value)}
                            aria-label="Buscar por número de registro o beneficiario"
                        />
                    </form>
                </div>

                {tramites.data.length === 0 ? (
                    <EstadoVacio
                        icono={FileText}
                        titulo="Sin trámites"
                        descripcion="No hay expedientes que coincidan con el filtro."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-medium">ID</th>
                                    <th className="px-5 py-3 font-medium">Beneficiario</th>
                                    <th className="px-5 py-3 font-medium">Rubro</th>
                                    {/*
                                        NO HAY COLUMNA «TIPO», y se sacó a pedido.

                                        Decía «Emisión Inicial» en casi todas las
                                        filas —es el caso normal: cada rubro nuevo
                                        emite su carnet— así que gastaba una
                                        columna para repetir lo mismo.

                                        El dato NO se perdió: sigue en la base
                                        (`tramites.tipo_tramite`), en la ficha del
                                        trámite y en el filtro de acá arriba, que
                                        es donde de verdad sirve: «mostrame solo
                                        las actualizaciones».
                                    */}
                                    <th className="px-5 py-3 font-medium">Estado</th>
                                    {/*
                                        TAMPOCO HAY COLUMNA «SALDO», y también se
                                        sacó a pedido.

                                        El motivo es de circuito, no de espacio:
                                        el expediente se arma, se ENVÍA a revisión,
                                        y ahí alguien lo abre y controla los
                                        papeles Y los pagos antes de aprobar. O
                                        sea, lo que la columna adelantaba se mira
                                        igual, de a uno, en la ficha — y ahí está
                                        completo: cuánto se requiere, cuánto entró,
                                        con qué boleta y en qué fecha.

                                        Puesto acá solo repetía un dato que nadie
                                        decide desde el listado: `puedeAprobarse()`
                                        ya exige el pago cubierto, así que un
                                        trámite impago no se puede aprobar aunque
                                        el revisor no haya mirado la columna.
                                    */}
                                    {/*
                                        «Fecha de registro» y no «Fecha» a secas:
                                        es cuándo se CARGÓ el expediente acá, no
                                        cuándo la persona presentó los papeles.
                                        Casi siempre coinciden, pero un trámite
                                        atrasado se carga con fecha de solicitud
                                        anterior y entonces son distintas.
                                    */}
                                    <th className="px-5 py-3 font-medium">Fecha de registro</th>
                                    <th className="px-5 py-3 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {tramites.data.map((t) => (
                                    <tr key={t.id} className="hover:bg-secondary/50">
                                        {/*
                                            El id es el número del expediente: es
                                            por lo que se pregunta en ventanilla
                                            («el trámite 14») y el que se dicta por
                                            teléfono. La tabla viene ordenada por
                                            él, así que verlo hace visible el orden.
                                        */}
                                        <td className="px-5 py-3 tabular-nums text-muted-foreground">
                                            {t.id}
                                        </td>

                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <Retrato
                                                    url={t.foto_url}
                                                    nombre={t.beneficiario ?? ''}
                                                />

                                                <div className="min-w-0">
                                                    <Link
                                                        href={route('tramites.show', t.id)}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {t.beneficiario ?? '—'}
                                                    </Link>

                                                    {/* tabular-nums: los dígitos
                                                        ocupan lo mismo y las
                                                        cédulas quedan alineadas
                                                        entre filas. */}
                                                    <p className="tabular-nums text-xs text-muted-foreground">
                                                        {t.documento_identidad ?? '—'}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">{t.rubro ?? '—'}</td>
                                        <td className="px-5 py-3">
                                            <Badge color={t.estado_color}>{t.estado_etiqueta}</Badge>
                                        </td>
                                        {/*
                                            TRES DATOS EN UNA CELDA, y cada uno
                                            contesta otra pregunta:

                                              la FECHA    para buscar el papel en
                                                          el archivo;
                                              la HORA     para ordenar dos
                                                          expedientes del mismo
                                                          día;
                                              el «HACE»   para ver de un vistazo
                                                          si entró recién o está
                                                          esperando desde la
                                                          semana pasada.

                                            El «hace tanto» se calcula al pintar
                                            y no se refresca solo: ver `hace()`
                                            en lib/utils.ts.
                                        */}
                                        <td className="px-5 py-3">
                                            <p className="tabular-nums">{fecha(t.created_at)}</p>
                                            <p className="tabular-nums text-xs text-muted-foreground">
                                                {hora(t.created_at)}
                                            </p>
                                            {t.created_at && (
                                                <p className="text-xs text-muted-foreground">
                                                    {hace(t.created_at)}
                                                </p>
                                            )}
                                        </td>

                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {/*
                                                    VER está siempre, aunque el
                                                    nombre del beneficiario ya
                                                    enlace a la ficha: ese enlace
                                                    no se ve como un botón y en una
                                                    tabla densa nadie lo busca.
                                                */}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.visit(route('tramites.show', t.id))
                                                    }
                                                    title="Ver el expediente"
                                                >
                                                    <Eye className="size-4" />
                                                    Ver
                                                </Button>

                                                {/*
                                                    ELIMINAR solo aparece si el
                                                    servidor dijo que se puede
                                                    —hoy, solo en PENDIENTE: el
                                                    borrador— Y si quien mira
                                                    tiene el permiso.

                                                    Las dos condiciones son
                                                    necesarias y ninguna alcanza:
                                                    `puede_eliminarse` es el estado
                                                    del expediente, `puede()` es
                                                    quién está mirando. Esconder el
                                                    botón es comodidad; la
                                                    seguridad real es el middleware
                                                    de la ruta más la comprobación
                                                    del servicio.
                                                */}
                                                {t.puede_eliminarse &&
                                                    puede('tramites.eliminar') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setAEliminar(t)}
                                                            title="Eliminar el expediente"
                                                            className="text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Eliminar
                                                        </Button>
                                                    )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Paginacion paginado={tramites} />
            </Card>

            {/*
                LA CONFIRMACIÓN DICE QUÉ SE LLEVA PUESTO, no solo «¿está seguro?».

                Borrar un expediente arrastra sus depósitos y sus archivos, y en
                una emisión inicial también el carnet que ese trámite creó. Quien
                aprieta el botón tiene que verlo escrito antes, porque después no
                hay vuelta atrás.
            */}
            <ConfirmarConMotivo
                abierto={aEliminar !== null}
                titulo={`Eliminar el trámite ${aEliminar?.id ?? ''}`}
                descripcion={
                    <>
                        <p>
                            Expediente de{' '}
                            <strong>{aEliminar?.beneficiario ?? '—'}</strong>
                            {aEliminar?.rubro ? ` — ${aEliminar.rubro}` : ''}.
                        </p>
                        <p className="mt-2">
                            Se borran también sus depósitos y los archivos
                            adjuntos. Si es la emisión inicial, se borra además el
                            carnet que este trámite creó, porque quedaría ocupando
                            la gestión sin ningún rubro habilitado.
                        </p>
                        <p className="mt-2 font-medium">Esto no se puede deshacer.</p>
                    </>
                }
                confirmacion="Entiendo que se borran también los depósitos, los archivos adjuntos y —si corresponde— el carnet, y que no se puede deshacer."
                etiquetaMotivo="¿Por qué se elimina?"
                ayuda="Es lo ÚNICO que va a quedar del expediente: la fila desaparece y solo se guarda esta línea en la bitácora."
                placeholder="Ej.: cargado dos veces por error, este es el duplicado."
                textoConfirmar="Eliminar"
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setAEliminar(null);
                    borrado.reset();
                }}
                onConfirmar={eliminar}
            />
        </LayoutPanel>
    );
}
