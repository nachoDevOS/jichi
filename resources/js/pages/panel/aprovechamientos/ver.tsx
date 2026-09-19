import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Banknote, Paperclip, Pencil, Plus, Ship, Trash2, TriangleAlert, X } from 'lucide-react';
import { useState } from 'react';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import type { CupoFicha, FaenaDelCupo, PagoDelCupo } from '@/types/aprovechamientos';

/**
 * ============================================================================
 *  LA FICHA DE UN CUPO
 * ============================================================================
 *
 * Arriba el saldo, abajo las faenas que lo explican. Ese orden es el punto:
 * «le quedan 20 kg» es un número que hay que creer hasta que se ve de dónde
 * sale.
 *
 * ----------------------------------------------------------------------------
 *  LAS FAENAS VENCIDAS SE MARCAN APARTE
 * ----------------------------------------------------------------------------
 *
 * Una faena vencida LIBERA su volumen: la salida no ocurrió. Sin marcarlas, la
 * suma de la lista no cuadra con el saldo de arriba y parece un error del
 * sistema. Por eso van tachadas y con la aclaración al lado.
 */
export default function VerCupo({
    cupo,
    faenas,
    pagos,
    modoEstricto,
}: {
    cupo: CupoFicha;
    faenas: FaenaDelCupo[];
    /** Los depósitos que pagaron este cupo, del más nuevo al más viejo. */
    pagos: PagoDelCupo[];
    /** Lo que dice APROVECHAMIENTO_ESTRICTO: cambia qué significa un saldo en cero. */
    modoEstricto: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [eliminando, setEliminando] = useState(false);

    const borrado = useForm({ motivo: '' });

    const [cobrando, setCobrando] = useState(false);

    /*
     * EL MONTO ARRANCA EN EL SALDO ENTERO, que es el caso normal: se cobra todo.
     * Quien está cargando el primero de dos depósitos lo baja, y el segundo
     * vuelve a proponer lo que falte porque la pantalla se recarga con el saldo
     * nuevo.
     */
    const pago = useForm({
        monto: String(cupo.saldo_pendiente),
        nro_transaccion: '',
        // Se propone hoy, que es lo normal: la boleta suele traerse el mismo día.
        fecha_deposito: new Date().toISOString().slice(0, 10),
        comprobante: null as File | null,
    });

    return (
        <LayoutPanel
            titulo={cupo.beneficiario ?? 'Cupo de pesca'}
            descripcion={`Escala ${cupo.escala ?? '—'} · ${cupo.descripcion ?? ''}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.visit(route('beneficiarios.show', cupo.beneficiario_id))
                        }
                    >
                        Ver al pescador
                    </Button>

                    {/*
                        COBRAR ES LA ACCIÓN QUE SIGUE A OTORGAR, y por eso el
                        botón aparece mientras quede saldo.

                        Otorgar ya deja al operador en la caja; esto cubre el otro
                        camino: el cupo que quedó a medio pagar y que alguien abre
                        días después. Sin el botón habría que ir a Caja y volver a
                        buscar a la persona a mano.

                        Va con `?beneficiario=` —y no con el id del cupo— porque
                        el formulario de cobro trae TODAS las deudas de esa
                        persona: un mismo recibo cubre el carnet y la autorización
                        si los dos están pendientes, que es lo que hace la
                        ventanilla.
                    */}
                    {/*
                        EDITAR Y ELIMINAR SOLO SOBRE EL BORRADOR.

                        Las dos banderas llegan resueltas del servidor: no son
                        «el estado es pendiente» sino eso Y que no haya entrado
                        plata —y para eliminar, además, que no tenga faenas—.
                        Deducirlas acá sería una segunda copia de tres reglas.
                    */}
                    {puede('aprovechamientos.editar') && cupo.puede_editarse && (
                        <Button
                            variant="outline"
                            onClick={() => router.visit(route('aprovechamientos.edit', cupo.id))}
                            className="border-amber-300 text-amber-700 hover:bg-amber-50 hover:text-amber-800 dark:border-amber-500/40 dark:text-amber-300 dark:hover:bg-amber-500/10"
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('aprovechamientos.eliminar') && cupo.puede_eliminarse && (
                        <Button
                            variant="outline"
                            onClick={() => setEliminando(true)}
                            className="border-rose-300 text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10"
                        >
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}

                    {puede('caja.cobrar') && !cupo.pagado && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(route('caja.create', { beneficiario: cupo.beneficiario_id }))
                            }
                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-500/40 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                        >
                            <Banknote className="size-4" />
                            Cobrar
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Cupo · ${cupo.beneficiario ?? ''}`} />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------------ El saldo */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Volumen</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <BarraSaldo cupo={cupo} />

                        {/*
                            EL RÉGIMEN VA JUNTO AL VOLUMEN porque explica de dónde
                            salió el monto: de la progresión por kilos, o de una
                            tasación fija que la resolución puso para esa especie.
                        */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge color={cupo.modalidad_color}>{cupo.modalidad_etiqueta}</Badge>

                            <span className="text-sm text-muted-foreground">
                                {cupo.modalidad === 'especie_especial'
                                    ? 'Cuota específica de la especie, con tasación fija por resolución.'
                                    : 'Tramo de la escala progresiva: a más kilos, más valor.'}
                            </span>
                        </div>

                        <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                            <Dato etiqueta="Consumido" valor={`${cupo.kilos_consumidos} kg`} />
                            <Dato etiqueta="Disponible" valor={`${cupo.saldo_kg} kg`} />
                            <Dato etiqueta="Usado" valor={`${cupo.porcentaje_usado}%`} />
                        </dl>

                        {/*
                            EL EXCESO SOLO PUEDE EXISTIR EN MODO FLEXIBLE: con la
                            validación encendida la emisión frena antes. Cuando
                            aparece es un hecho consumado —el pescado ya se
                            extrajo— así que se muestra como dato, no como un error
                            que alguien pueda corregir desde acá.
                        */}
                        {/*
                            PENDIENTE NO ES UN DETALLE DE COLOR: el cupo existe,
                            está en fecha y con el volumen entero, y aun así NO
                            autoriza a pescar. Sin decirlo acá, la única señal
                            sería una etiqueta celeste y el operador emitiría una
                            faena para descubrirlo recién con el error.
                        */}
                        {cupo.estado === 'pendiente' && (
                            <p className="flex items-start gap-2 rounded-md bg-sky-50 p-3 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                                <Banknote className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    <strong>Pendiente de pago.</strong> Todavía no autoriza a pescar:
                                    lo que habilita es la concesión cobrada. Mientras tanto se puede
                                    corregir o eliminar.
                                </span>
                            </p>
                        )}

                        {cupo.excedido && (
                            <p className="flex items-start gap-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Las faenas emitidas suman{' '}
                                    <strong>{cupo.kilos_excedidos} kg por encima</strong> del volumen
                                    otorgado. El control de cupo está desactivado, así que la emisión
                                    no lo frenó.
                                </span>
                            </p>
                        )}

                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Estado y cobro */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Situación</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Estado</span>
                            <Badge color={cupo.estado_color}>{cupo.estado_etiqueta}</Badge>
                        </div>

                        {/*
                            EL RENGLÓN «Tipo de Embarcación» DEL TALONARIO. Se
                            distingue el NULL de una cadena vacía: «no declarada»
                            dice que nadie lo llenó, un renglón en blanco no dice
                            nada.
                        */}
                        <Dato
                            etiqueta="Embarcación"
                            valor={cupo.tipo_embarcacion ?? 'No declarada'}
                        />

                        <Dato etiqueta="Otorgado el" valor={fecha(cupo.fecha_emision)} />
                        <Dato etiqueta="Vence el" valor={fecha(cupo.fecha_vencimiento)} />
                        <Dato etiqueta="Monto" valor={bs(cupo.monto, institucion.moneda)} />

                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Cobro</span>
                            {cupo.pagado ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                    Pagado
                                </span>
                            ) : (
                                <span className="font-medium text-amber-700 dark:text-amber-400">
                                    debe {bs(cupo.saldo_pendiente, institucion.moneda)}
                                </span>
                            )}
                        </div>

                        {/*
                            La conclusión, ya resuelta por el servidor: las tres
                            condiciones —vigente, con saldo, sin agotar— juntas.
                            La pantalla no las vuelve a evaluar.
                        */}
                        <p
                            className={
                                cupo.puede_emitir_faena
                                    ? 'rounded-md bg-emerald-50 p-3 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200'
                                    : 'rounded-md bg-amber-50 p-3 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200'
                            }
                        >
                            {cupo.puede_emitir_faena
                                ? modoEstricto
                                    ? 'Habilitado para emitir faenas.'
                                    : 'Habilitado para emitir faenas. El control de saldo está desactivado: se siguen emitiendo aunque el cupo se agote.'
                                : 'No se le pueden emitir faenas: el cupo está vencido o sin saldo.'}
                        </p>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Los depósitos */}
                <Card className="min-w-0 lg:col-span-3">
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>Pagos</CardTitle>

                        {/*
                            SE COBRA DESDE ACÁ Y NO SOLO DESDE CAJA, porque el
                            caso que se repite es cargar DOS depósitos seguidos
                            por el mismo cupo: yendo a Caja hay que volver a
                            buscar a la persona en cada vuelta.

                            Es el mismo cobro —sale con su recibo numerado y entra
                            al arqueo—, así que pide el permiso de caja.
                        */}
                        {puede('caja.cobrar') && !cupo.pagado && (
                            <Button
                                size="sm"
                                onClick={() => setCobrando((v) => !v)}
                                variant={cobrando ? 'outline' : 'default'}
                            >
                                {cobrando ? <X className="size-4" /> : <Plus className="size-4" />}
                                {cobrando ? 'Cancelar' : 'Agregar pago'}
                            </Button>
                        )}
                    </CardHeader>

                    <CardContent className="space-y-5 p-0">
                        {cobrando && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    /*
                                     * `forceFormData` es obligatorio cuando hay
                                     * archivo: sin él Inertia manda el cuerpo como
                                     * JSON y el adjunto se pierde en el camino, sin
                                     * ningún error que lo explique.
                                     */
                                    pago.post(route('aprovechamientos.pagar', cupo.id), {
                                        // Siempre hay archivo, así que siempre
                                        // va como multipart.
                                        forceFormData: true,
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setCobrando(false);
                                            pago.reset();
                                        },
                                    });
                                }}
                                className="mx-5 space-y-4 rounded-md border border-border bg-secondary/30 p-4"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Campo
                                        etiqueta="Monto del depósito"
                                        htmlFor="monto"
                                        error={pago.errors.monto}
                                        ayuda={`Quedan ${bs(cupo.saldo_pendiente, institucion.moneda)} por cobrar.`}
                                        obligatorio
                                    >
                                        <Input
                                            id="monto"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={pago.data.monto}
                                            onChange={(e) => pago.setData('monto', e.target.value)}
                                            aria-invalid={Boolean(pago.errors.monto)}
                                        />
                                    </Campo>


                                    <Campo
                                        etiqueta="Fecha del depósito"
                                        htmlFor="fecha_deposito"
                                        error={pago.errors.fecha_deposito}
                                        ayuda="La que dice la boleta, no la de hoy: un depósito del viernes puede cargarse el lunes."
                                        obligatorio
                                    >
                                        <Input
                                            id="fecha_deposito"
                                            type="date"
                                            value={pago.data.fecha_deposito}
                                            onChange={(e) =>
                                                pago.setData('fecha_deposito', e.target.value)
                                            }
                                            aria-invalid={Boolean(pago.errors.fecha_deposito)}
                                        />
                                    </Campo>

                                    <Campo
                                                etiqueta="N° de transacción"
                                                htmlFor="nro_transaccion"
                                                error={pago.errors.nro_transaccion}
                                                ayuda="No se puede repetir: una misma boleta no respalda dos pagos."
                                                obligatorio
                                            >
                                                <Input
                                                    id="nro_transaccion"
                                                    value={pago.data.nro_transaccion}
                                                    onChange={(e) =>
                                                        pago.setData('nro_transaccion', e.target.value)
                                                    }
                                                    aria-invalid={Boolean(pago.errors.nro_transaccion)}
                                                    placeholder="0012345678"
                                                    className="font-mono"
                                                    maxLength={60}
                                                />
                                            </Campo>

                                            <Campo
                                                etiqueta="Boleta del depósito"
                                                htmlFor="comprobante"
                                                error={pago.errors.comprobante}
                                                ayuda="Foto o PDF, hasta 3 MB."
                                                obligatorio
                                            >
                                                <SelectorArchivo
                                                    id="comprobante"
                                                    archivo={pago.data.comprobante}
                                                    onElegir={(a) => pago.setData('comprobante', a)}
                                                    error={pago.errors.comprobante}
                                                />
                                            </Campo>
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        pago.processing ||
                                        Number(pago.data.monto) <= 0 ||
                                        pago.data.fecha_deposito === '' ||
                                        pago.data.comprobante === null ||
                                        pago.data.nro_transaccion.trim() === ''
                                    }
                                >
                                    <Banknote className="size-4" />
                                    Registrar pago
                                </Button>
                            </form>
                        )}

                        {pagos.length === 0 ? (
                            <EstadoVacio
                                icono={Banknote}
                                titulo="Sin pagos registrados"
                                descripcion="El cupo no autoriza faenas hasta que la concesión esté cobrada."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Recibo</th>
                                            <th className="px-5 py-2.5 font-medium">Boleta</th>
                                            <th className="px-5 py-2.5 font-medium">Depositado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                            <th className="px-5 py-2.5 font-medium">Cargado</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {pagos.map((p) => (
                                            <tr key={p.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <Link
                                                        href={route('recibos.show', p.recibo_id)}
                                                        className="font-mono font-medium text-primary hover:underline"
                                                    >
                                                        {p.numero_recibo ?? '—'}
                                                    </Link>
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    {p.comprobante_url ? (
                                                        <a
                                                            href={p.comprobante_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="flex items-center gap-1 text-primary hover:underline"
                                                            title="Abrir la boleta del depósito"
                                                        >
                                                            <Paperclip className="size-3.5" />
                                                            <span className="font-mono text-xs">
                                                                {p.nro_transaccion}
                                                            </span>
                                                        </a>
                                                    ) : (
                                                        <span className="font-mono text-xs">
                                                            {p.nro_transaccion}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* La fecha de la BOLETA, que no
                                                    es la de carga: se muestra con
                                                    fecha() porque es un día. */}
                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(p.fecha_deposito)}
                                                </td>

                                                <td className="px-5 py-2.5 text-right font-medium tabular-nums">
                                                    {bs(p.monto_parcial, institucion.moneda)}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fechaHora(p.cobrado_en)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Las faenas */}
                <Card className="min-w-0 lg:col-span-3">
                    <CardHeader>
                        <CardTitle>Faenas emitidas</CardTitle>
                    </CardHeader>

                    <CardContent className="p-0">
                        {faenas.length === 0 ? (
                            <EstadoVacio
                                icono={Ship}
                                titulo="Sin faenas"
                                descripcion="Todavía no se emitió ninguna salida contra este cupo."
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">N°</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 font-medium">Salida</th>
                                            <th className="px-5 py-2.5 font-medium">Límite</th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {faenas.map((f) => (
                                            <tr key={f.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5 font-mono tabular-nums">
                                                    {String(f.numero_faena).padStart(4, '0')}
                                                </td>

                                                <td className="px-5 py-2.5 text-right tabular-nums">
                                                    {/*
                                                        Tachado cuando NO consume cupo: es lo que
                                                        hace que la suma de la columna cuadre con
                                                        el saldo de arriba.
                                                    */}
                                                    <span
                                                        className={
                                                            f.consume_cupo
                                                                ? undefined
                                                                : 'text-muted-foreground line-through'
                                                        }
                                                    >
                                                        {f.kilos_extraidos} kg
                                                    </span>
                                                    {!f.consume_cupo && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            liberados
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(f.fecha_salida)}
                                                </td>

                                                <td className="px-5 py-2.5 text-muted-foreground">
                                                    {fecha(f.fecha_limite)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/*
                AMPLIAR PIDE MOTIVO POR ESCRITO. Es dar más kilos de los que la
                escala otorgaba —lo que el cupo viene a limitar— así que sin el
                motivo, dentro de seis meses nadie puede explicar por qué esta
                persona tuvo 800 kg cuando su tramo daba 500.
            */}
            {/*
                ================================================================
                 ELIMINAR PIDE MOTIVO **Y** CASILLA DE CONSENTIMIENTO
                ================================================================

                Las dos cosas, y cada una tapa algo distinto:

                  - EL MOTIVO es lo único que sobrevive. La fila se borra de
                    verdad, así que dentro de seis meses la única respuesta
                    posible a «¿y el cupo de Fulano?» es la línea de auditoría.
                    Sin texto ahí, esa respuesta es «alguien lo borró».

                  - LA CASILLA frena el clic automático. Escribir un motivo es
                    una tarea; marcar «entiendo que esto no se deshace» es una
                    decisión, y son dos actos distintos a propósito.

                El mínimo de 10 caracteres es el mismo que exige
                EliminarCupoRequest: si acá fuera menor, el botón se habilitaría
                y el servidor rechazaría igual.
            */}
            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar este aprovechamiento"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja el cupo de{' '}
                            <strong>{cupo.beneficiario ?? 'el pescador'}</strong>: escala{' '}
                            {cupo.escala ?? '—'}, {cupo.volumen_total_kg} kg.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente de pago</strong>, sin ningún
                            cobro ni faena encima. No se deshace.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre, y es lo que va a explicar la baja dentro de seis meses."
                placeholder="Cargado por error: el tramo corresponde a otro pescador."
                textoConfirmar="Eliminar aprovechamiento"
                confirmacion="Entiendo que el cupo desaparece del sistema y que esto no se deshace desde el panel."
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('aprovechamientos.destroy', cupo.id), {
                        preserveScroll: true,
                        // Sin onSuccess: al borrarse, el servidor redirige al
                        // listado y esta pantalla deja de existir.
                        onError: () => setEliminando(true),
                    })
                }
            />

            
        </LayoutPanel>
    );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right font-medium tabular-nums">{valor}</span>
        </div>
    );
}
