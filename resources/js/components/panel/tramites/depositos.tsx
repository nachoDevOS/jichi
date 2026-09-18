import { useForm } from '@inertiajs/react';
import { Check, FileText, Pencil, Plus, Trash2, TriangleAlert, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AccionesControl, EstadoControl } from '@/components/panel/pagos/control-deposito';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, fecha, fechaInput } from '@/lib/utils';
import type { FormularioPago } from '@/types/pagos';
import type { FormularioCorreccionPago, PagoDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  LOS DEPÓSITOS DE UN TRÁMITE — Regla C
 * ============================================================================
 *
 * Las piezas viven acá y no dentro de una pantalla porque las usan DOS, y cada
 * una cubre un MOMENTO distinto del expediente:
 *
 *   EDITAR TRÁMITE   el borrador. Se carga, se corrige y se QUITA lo que no
 *                    corresponde. Nadie lo vio todavía.
 *
 *   FICHA            el expediente presentado. Se controla cada boleta —validar
 *                    u observar—, y se sigue pudiendo cargar y corregir, porque
 *                    al enviar se congelan los PAPELES pero no el dinero.
 *
 * ----------------------------------------------------------------------------
 *  EL FORMULARIO ESTUVO EN UNA SOLA PANTALLA, Y ESO DEJABA UN EXPEDIENTE SIN
 *  SALIDA
 * ----------------------------------------------------------------------------
 *
 * La idea era que quedara claro cuál era «el lugar» donde se cargan los
 * depósitos. El problema apareció con el control: OBSERVAR una boleta solo se
 * puede EN REVISIÓN, y «Editar trámite» no abre fuera de PENDIENTE. El revisor
 * marcaba «la boleta dice otra cosa» y del otro lado no había dónde corregirla.
 *
 * No son dos lugares para lo mismo: son dos momentos, y en cada uno se puede
 * una cosa distinta. Lo dicen los tres flags de `ListaDepositos` y, por debajo,
 * `Pago::admiteCorreccion()` y `admiteEliminacion()`.
 */

/**
 * Cuánto se cobró y cuánto falta, con barra de avance.
 *
 * La barra no es adorno: tres números sueltos obligan a restar mentalmente para
 * saber si el expediente está cerca de poder aprobarse, y el operador hace esa
 * cuenta decenas de veces por jornada.
 *
 * El estado se dice ADEMÁS con palabras —«Cubierto» / «Falta X»—: el color solo
 * no alcanza, porque un daltónico ve las dos barras iguales.
 */
export function ResumenDepositos({
    montoRequerido,
    montoPagado,
    saldoPendiente,
    moneda,
    porControlar,
}: {
    montoRequerido: number;
    montoPagado: number;
    saldoPendiente: number;
    moneda: string;
    /**
     * Cuántas boletas siguen frenando la aprobación.
     *
     * HACE FALTA ACÁ aunque hable de otra cosa que el dinero. Sin esto, el
     * cartel decía «Costo cubierto — el expediente se puede aprobar» sobre un
     * trámite que NO se podía aprobar porque nadie había controlado la boleta:
     * la plata estaba, pero el botón no aparecía y el cartel verde no ayudaba a
     * entender por qué.
     *
     * Opcional porque «corregir papeles» usa el mismo componente y ahí no se
     * controla nada.
     */
    porControlar?: { pendientes: number; observados: number };
}) {
    const cubierto = saldoPendiente <= 0;
    const sinControlar = (porControlar?.pendientes ?? 0) + (porControlar?.observados ?? 0);

    /*
     * Se corta en 100 %: un depósito puede venir por unos bolivianos de más
     * —el sistema no lo rechaza, ver PagoTramiteService— y sin el tope la barra
     * se saldría del riel.
     */
    const avance =
        montoRequerido > 0 ? Math.min(100, (montoPagado / montoRequerido) * 100) : 100;

    return (
        <div className="space-y-3 rounded-md bg-secondary/50 p-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <Cifra etiqueta="Costo" valor={bs(montoRequerido, moneda)} />
                <Cifra etiqueta="Cobrado" valor={bs(montoPagado, moneda)} />
                <Cifra
                    etiqueta="Saldo"
                    valor={bs(saldoPendiente, moneda)}
                    destacado={cubierto ? 'emerald' : 'amber'}
                />
            </div>

            <div>
                <div className="h-1.5 overflow-hidden rounded-full bg-border">
                    <div
                        className={
                            cubierto
                                ? 'h-full rounded-full bg-emerald-500 transition-all'
                                : 'h-full rounded-full bg-amber-500 transition-all'
                        }
                        style={{ width: `${avance}%` }}
                    />
                </div>

                {/*
                    TRES MENSAJES Y NO DOS, porque son tres situaciones:
                    falta plata, está la plata pero falta controlarla, o está
                    todo. El del medio es el que faltaba y hacía que el cartel
                    verde prometiera una aprobación que no iba a poder hacerse.

                    La BARRA sigue verde en ese caso: la plata está cubierta, y
                    eso es lo que la barra mide. Lo que falta es otra cosa y lo
                    dice el texto.
                */}
                <p className="mt-1.5 flex items-center gap-1.5 text-xs">
                    {cubierto && sinControlar > 0 ? (
                        <>
                            <TriangleAlert className="size-3.5 shrink-0 text-amber-600" />
                            <span className="text-muted-foreground">
                                Costo cubierto, pero{' '}
                                {sinControlar === 1
                                    ? 'falta controlar 1 depósito'
                                    : `faltan controlar ${sinControlar} depósitos`}{' '}
                                para poder aprobar
                            </span>
                        </>
                    ) : cubierto ? (
                        <>
                            <Check className="size-3.5 shrink-0 text-emerald-600" />
                            <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                Costo cubierto — el expediente se puede aprobar
                            </span>
                        </>
                    ) : (
                        <>
                            <TriangleAlert className="size-3.5 shrink-0 text-amber-600" />
                            <span className="text-muted-foreground">
                                Falta {bs(saldoPendiente, moneda)} para poder aprobar ·{' '}
                                {Math.round(avance)} % cobrado
                            </span>
                        </>
                    )}
                </p>
            </div>
        </div>
    );
}

function Cifra({
    etiqueta,
    valor,
    destacado,
}: {
    etiqueta: string;
    valor: string;
    destacado?: 'amber' | 'emerald';
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</p>
            <p
                className={
                    destacado === 'amber'
                        ? 'text-lg font-semibold tabular-nums text-amber-700 dark:text-amber-400'
                        : destacado === 'emerald'
                          ? 'text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-400'
                          : 'text-lg font-semibold tabular-nums'
                }
            >
                {valor}
            </p>
        </div>
    );
}

/**
 * La lista de depósitos ya cargados.
 *
 * El MONTO va primero y grande, porque es lo que se busca al cuadrar contra el
 * banco. El número de transacción y la fecha van abajo, en gris: son la
 * referencia, no el dato.
 */
export function ListaDepositos({
    pagos,
    moneda,
    vacio,
    conControl = false,
    conCorreccion = false,
}: {
    pagos: PagoDelTramite[];
    moneda: string;
    /** Qué decir cuando no hay ninguno. Cambia según la pantalla. */
    vacio: string;
    /**
     * Si se muestra el CONTROL de cada boleta y sus botones.
     *
     * Solo en la FICHA: el control es parte de la REVISIÓN, y en el borrador no
     * hay nada que controlar todavía —el expediente se está armando y las
     * boletas aún pueden cambiar—. Ver `Pago::admiteControl()`.
     */
    conControl?: boolean;
    /**
     * Si cada depósito se puede CORREGIR —y QUITAR— ahí mismo.
     *
     * VA EN LAS DOS PANTALLAS, y un tiempo estuvo solo en «Editar trámite».
     * Faltando en la ficha, un depósito OBSERVADO no tenía dónde corregirse:
     * observar solo pasa EN REVISIÓN y esa pantalla no abre ahí.
     *
     * Esto NO contradice «quien carga no valida»: esa separación la hacen los
     * PERMISOS —`pagos.registrar` contra `pagos.validar`— y
     * `Pago::puedeValidarlo()`, que compara contra quién cargó la boleta.
     * Repartir los botones en dos pantallas no impedía nada, porque con un solo
     * rol la misma persona abre las dos.
     *
     * Enciende los DOS botones, corregir y quitar, porque el servidor los
     * habilita con la misma regla: mientras la fila se pueda tocar —no
     * validada, y lo que se paga sigue cobrando— se puede tanto arreglarla como
     * sacarla. Ver `Pago::admiteEliminacion()`.
     *
     * Qué se puede con CADA depósito lo decide el servidor fila por fila, así
     * que acá alcanza con encenderlo: uno ya validado no muestra ninguno de los
     * dos, y en su lugar aparece el motivo.
     */
    conCorreccion?: boolean;
}) {
    if (pagos.length === 0) {
        return (
            <p className="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                Sin depósitos registrados todavía.
                <br />
                <span className="text-xs">{vacio}</span>
            </p>
        );
    }

    return (
        <ul className="space-y-2">
            {pagos.map((pago, i) => (
                <FilaDeposito
                    key={pago.id}
                    pago={pago}
                    orden={i + 1}
                    moneda={moneda}
                    conControl={conControl}
                    conCorreccion={conCorreccion}
                />
            ))}
        </ul>
    );
}

/**
 * Un depósito de la lista, que además se puede abrir para corregirlo.
 *
 * ES UN COMPONENTE PROPIO y no un `<li>` suelto dentro del `map` porque tiene
 * ESTADO —si está abierto en modo corrección— y un hook no se puede llamar
 * dentro de un `map`. Subir ese estado a la lista obligaría a guardar allá
 * arriba «cuál fila está abierta», que es justamente el dato que no le importa
 * a nadie más que a la fila.
 */
function FilaDeposito({
    pago,
    orden,
    moneda,
    conControl,
    conCorreccion,
}: {
    pago: PagoDelTramite;
    orden: number;
    moneda: string;
    conControl: boolean;
    conCorreccion: boolean;
}) {
    const { puede } = usePermisos();
    const [corrigiendo, setCorrigiendo] = useState(false);
    const [quitando, setQuitando] = useState(false);

    const baja = useForm({ motivo: '' });

    // `puede_corregirse` lo manda solo «Editar trámite». Si no vino, se asume
    // que no: esconder de más es preferible a ofrecer un botón que el servidor
    // va a rechazar.
    const puedeCorregirse = conCorreccion && pago.puede_corregirse === true;

    /*
     * QUITAR PIDE DOS COSAS, y las dos vienen de lados distintos:
     *
     *   el PERMISO `pagos.eliminar`  — quién puede, en general. Es de
     *                                  administración, no de ventanilla;
     *   `puede_eliminarse`           — si se puede quitar ESTE depósito, que es
     *                                  false apenas el expediente sale del
     *                                  borrador.
     *
     * Esconder el botón no es seguridad: quien lo aplica de verdad es el
     * middleware de la ruta y `PagoTramiteService::eliminar()`.
     */
    const puedeEliminarse =
        conCorreccion && pago.puede_eliminarse === true && puede('pagos.eliminar');

    function quitar() {
        baja.delete(route('pagos.destroy', pago.id), {
            preserveScroll: true,
            onSuccess: () => {
                setQuitando(false);
                baja.reset();
            },
        });
    }

    /*
     * LA FILA SE REEMPLAZA POR EL FORMULARIO, no se le agrega debajo.
     *
     * Lo que se corrige es lo que se está mirando. Con la fila todavía visible
     * arriba, el operador tiene dos montos en pantalla —el viejo y el que está
     * escribiendo— y termina comparando contra el equivocado.
     */
    if (corrigiendo) {
        return (
            <li className="rounded-md border border-primary/40 bg-secondary/30 p-3">
                <FormularioCorreccion
                    pago={pago}
                    orden={orden}
                    moneda={moneda}
                    onTerminar={() => setCorrigiendo(false)}
                />
            </li>
        );
    }

    return (
        <li className="flex items-center gap-3 rounded-md border border-border p-3">
            {/* El número de orden ubica el depósito dentro del
                expediente: con tres boletas, «el segundo» es como se lo
                nombra en ventanilla. */}
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold tabular-nums text-muted-foreground">
                {orden}
            </span>

            <div className="min-w-0 flex-1">
                <p className="text-[15px] font-semibold tabular-nums">{bs(pago.monto, moneda)}</p>
                <p className="truncate text-xs text-muted-foreground">
                    Nº {pago.nro_transaccion} · {fecha(pago.fecha_pago)}
                </p>
            </div>

            {/* El control va ANTES de la boleta a propósito: se mira
                el estado, y si hace falta se abre el archivo. Al revés,
                el botón de la boleta quedaba entre el estado y sus
                acciones, partiendo algo que se lee junto. */}
            {conControl && <EstadoControl pago={pago} />}

            {pago.comprobante_url && (
                <a
                    href={pago.comprobante_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs transition-colors hover:bg-secondary"
                >
                    <FileText className="size-3.5" />
                    Ver boleta
                </a>
            )}

            {/*
                CORREGIR — la única salida cuando la boleta se cargó mal.

                Los pagos no se anulan ni se borran: un pago «anulado» que sigue
                en la lista solo invita a sumarlo por error. Lo que decía antes
                queda registrado en `auditorias`.

                Y cuando NO se puede, se DICE —mismo criterio que el control—:
                el motivo lo manda el servidor, y el más común es que el
                depósito ya fue validado por alguien.
            */}
            {conCorreccion &&
                (puedeCorregirse ? (
                    <Button
                        key="corregir"
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCorrigiendo(true)}
                        title={`Corregir el depósito ${pago.nro_transaccion}`}
                        aria-label={`Corregir el depósito ${pago.nro_transaccion}`}
                    >
                        <Pencil className="size-3.5" />
                        Corregir
                    </Button>
                ) : (
                    pago.motivo_sin_correccion && (
                        <p
                            key="sin-correccion"
                            className="max-w-56 shrink-0 text-xs text-muted-foreground"
                        >
                            {pago.motivo_sin_correccion}
                        </p>
                    )
                ))}

            {/*
                QUITAR — la boleta cargada dos veces, o la de otra persona.

                Va DESPUÉS de corregir y en `ghost`, no porque importe menos
                sino porque es la salida que no tiene vuelta: corregir deja la
                fila y su historial, quitar la hace desaparecer. El botón que se
                usa todos los días va primero y se ve; el que casi nunca se usa
                queda al lado, sin llamar la atención.

                Cuando no se puede NO se dice nada acá: el motivo de corrección
                ya ocupa ese lugar, y dos textos seguidos explicando dos cosas
                parecidas no se leen. Lo que el operador necesita saber en ese
                caso es lo que SÍ puede hacer, y eso lo dice el otro texto.
            */}
            {puedeEliminarse && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setQuitando(true)}
                    title={`Quitar el depósito ${pago.nro_transaccion} del expediente`}
                    aria-label={`Quitar el depósito ${pago.nro_transaccion}`}
                >
                    <Trash2 className="size-3.5" />
                    Quitar
                </Button>
            )}

            {/*
                EL MOTIVO ES OBLIGATORIO, igual que al eliminar un expediente.

                La fila no queda: con ella se van el número de transacción, el
                monto y el vínculo con la boleta. Si el porqué no está en
                `auditorias`, dentro de un mes nadie puede explicar por qué este
                expediente hoy tiene cobrado menos que ayer.
            */}
            <ConfirmarConMotivo
                abierto={quitando}
                titulo={`Quitar el depósito ${pago.nro_transaccion}`}
                descripcion={
                    <>
                        Se quita del expediente {bs(pago.monto, moneda)} y la boleta se borra
                        del sistema. <strong>No se puede deshacer</strong>: si el dato está
                        mal pero el depósito existe, lo que corresponde es corregirlo. Si el
                        expediente ya se presentó, el recibo reimpreso va a mostrar el total
                        sin este depósito.
                    </>
                }
                etiquetaMotivo="Por qué se quita"
                ayuda="Es lo único que va a quedar de este depósito."
                placeholder="Ej.: la misma boleta se cargó dos veces por error."
                textoConfirmar="Quitar depósito"
                confirmacion="Confirmo que este depósito no corresponde a este expediente y que debe quitarse."
                valor={baja.data.motivo}
                onCambiar={(v) => baja.setData('motivo', v)}
                error={baja.errors.motivo}
                procesando={baja.processing}
                onConfirmar={quitar}
                onCancelar={() => setQuitando(false)}
            />

            {/*
                CON RÓTULO y no en modo compacto, al revés que en el
                libro de caja: acá hay lugar, y validar u observar es la
                acción que quien revisa vino a hacer. Un par de iconos
                sueltos la esconderían entre los demás botones de la
                ficha.
            */}
            {conControl && <AccionesControl pago={pago} />}
        </li>
    );
}

/**
 * ============================================================================
 *  CORREGIR UN DEPÓSITO YA CARGADO
 * ============================================================================
 *
 * Se abre EN EL LUGAR de la fila y no en una ventana aparte: una ventana modal
 * taparía el resumen de arriba —costo, cobrado, saldo—, que es contra lo que el
 * operador compara mientras escribe el monto.
 *
 * LA BOLETA ES OPCIONAL: lo más común es arreglar un número y dejar el archivo
 * como está. El campo lo dice con todas las letras, porque un selector vacío
 * arriba de un depósito que SÍ tiene boleta se lee como «no hay ninguna».
 *
 * Va por PUT a `pagos.update`, que cuelga del PAGO y no del trámite: el pago ya
 * sabe de qué es. El `_method` hace falta porque el formulario lleva un archivo
 * y `router.put()` no los manda.
 */
function FormularioCorreccion({
    pago,
    orden,
    moneda,
    onTerminar,
}: {
    pago: PagoDelTramite;
    orden: number;
    moneda: string;
    onTerminar: () => void;
}) {
    const form = useForm<FormularioCorreccionPago>({
        nro_transaccion: pago.nro_transaccion,
        // A texto, como todo lo que sale de un <input>: la conversión a número
        // la hace Laravel al validar con la regla `numeric`.
        monto: String(pago.monto),
        comprobante: null,
        // fechaInput() y no un corte de la cadena: lo que manda el servidor es
        // un instante en UTC, y cortarle los diez primeros caracteres daría el
        // día siguiente al que muestra la fila. Ver lib/utils.ts.
        fecha_pago: fechaInput(pago.fecha_pago),
        _method: 'put',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        form.post(route('pagos.update', pago.id), {
            forceFormData: true,
            // Sin esto la pantalla salta arriba de todo y el operador pierde de
            // vista la fila que acaba de corregir.
            preserveScroll: true,
            onSuccess: onTerminar,
        });
    }

    return (
        <form onSubmit={enviar} className="space-y-3">
            <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <Pencil className="size-3.5" />
                Corrigiendo el depósito {orden}
            </p>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Campo
                    etiqueta="Nº transacción"
                    htmlFor={`correccion-${pago.id}-nro`}
                    obligatorio
                    error={form.errors.nro_transaccion}
                    ayuda="El del comprobante del banco. Solo números."
                >
                    {/* Solo dígitos y se limpian AL TECLEAR, igual que en el
                        alta: el operador está copiando de un papel. */}
                    <Input
                        id={`correccion-${pago.id}-nro`}
                        inputMode="numeric"
                        value={form.data.nro_transaccion}
                        onChange={(e) =>
                            form.setData('nro_transaccion', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Campo>

                <Campo
                    etiqueta={`Monto (${moneda})`}
                    htmlFor={`correccion-${pago.id}-monto`}
                    obligatorio
                    error={form.errors.monto}
                    ayuda="Lo que dice la boleta"
                >
                    <Input
                        id={`correccion-${pago.id}-monto`}
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.data.monto}
                        onChange={(e) => form.setData('monto', e.target.value)}
                    />
                </Campo>

                <Campo
                    etiqueta="Fecha del depósito"
                    htmlFor={`correccion-${pago.id}-fecha`}
                    error={form.errors.fecha_pago}
                    ayuda="La del banco, no la de hoy."
                >
                    <Input
                        id={`correccion-${pago.id}-fecha`}
                        type="date"
                        value={form.data.fecha_pago}
                        onChange={(e) => form.setData('fecha_pago', e.target.value)}
                    />
                </Campo>

                <Campo
                    etiqueta="Boleta escaneada"
                    htmlFor={`correccion-${pago.id}-comprobante`}
                    ayuda="Déjelo vacío para conservar la actual."
                >
                    <SelectorArchivo
                        id={`correccion-${pago.id}-comprobante`}
                        archivo={form.data.comprobante}
                        onElegir={(a) => form.setData('comprobante', a)}
                        error={form.errors.comprobante}
                    />
                </Campo>
            </div>

            {/*
                QUE ESTO QUEDA REGISTRADO SE AVISA ANTES, no después.

                Corregir un cobro no es «guardar»: cambia el dinero que el
                expediente dice haber recibido. Que quede el valor anterior no
                es una amenaza al operador, es lo que después permite explicar
                por qué la suma de hoy no es la de ayer.
            */}
            <p className="text-xs text-muted-foreground">
                Queda registrado qué decía antes, quién lo cambió y cuándo.
                {pago.validacion === 'observado' &&
                    ' Al corregirlo vuelve a quedar sin controlar, porque la observación hablaba del dato anterior.'}
            </p>

            <div className="flex justify-end gap-2">
                {/* `key` distinto en cada botón: React reconcilia por posición,
                    y dos botones de tipo distinto en el mismo lugar terminan
                    siendo el MISMO <button> con el atributo cambiado — el
                    formulario se envía solo. Ver la trampa anotada en
                    CLAUDE.md. */}
                <Button
                    key="cancelar"
                    type="button"
                    variant="ghost"
                    onClick={onTerminar}
                    disabled={form.processing}
                >
                    <X className="size-4" />
                    Cancelar
                </Button>

                <Button key="guardar" type="submit" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Guardar corrección'}
                </Button>
            </div>
        </form>
    );
}

/**
 * El formulario para cargar un depósito.
 *
 * VIVE SOLO EN «CORREGIR PAPELES». La ficha del trámite los muestra pero no deja
 * cargarlos.
 *
 * Va en DOS FILAS y no en una de cuatro columnas: apretado en un renglón, el
 * selector de boleta quedaba tan angosto que cortaba el nombre del archivo.
 */
export function FormularioDeposito({
    tramiteId,
    moneda,
}: {
    tramiteId: number;
    moneda: string;
}) {
    const form = useForm<FormularioPago>({
        nro_transaccion: '',
        monto: '',
        comprobante: null,
        fecha_pago: '',
        observaciones: '',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        form.post(route('pagos.store', tramiteId), {
            forceFormData: true,
            // Al terminar se limpian los campos para poder cargar la siguiente
            // boleta sin recargar la pantalla.
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={enviar} className="space-y-3 border-t border-border pt-4">
            <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <Plus className="size-3.5" />
                Registrar un depósito
            </p>

            {/*
                CUATRO COLUMNAS, y la cuarta es la FECHA.

                Faltaba, y no era un olvido inocuo: sin el campo, todo depósito
                cargado acá se guardaba con la fecha de HOY. Una boleta del
                viernes que se registra el lunes quedaba fechada el lunes, y esa
                fecha es justo por donde se cruza el pago contra el extracto del
                banco. El formulario de alta del trámite —campo-pagos.tsx— sí lo
                pedía desde el principio; este no, así que el mismo dato se
                cargaba de dos maneras según por dónde entrara.

                Se apila a dos columnas antes que a cuatro: apretado en un
                renglón, el selector de boleta queda tan angosto que corta el
                nombre del archivo.
            */}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Campo
                    etiqueta="Nº transacción"
                    htmlFor="nro_transaccion"
                    obligatorio
                    error={form.errors.nro_transaccion}
                    ayuda="El del comprobante del banco. Solo números."
                >
                    {/*
                        Solo dígitos, y se limpian AL TECLEAR en vez de rebotar
                        el formulario: el operador está copiando de una boleta de
                        papel. No es `type="number"` a propósito — ese tipo acepta
                        `e`, `+`, `-` y coma decimal, y le pone flechitas a un
                        campo que no es una cantidad. Ver campo-pagos.tsx.
                    */}
                    <Input
                        id="nro_transaccion"
                        inputMode="numeric"
                        value={form.data.nro_transaccion}
                        onChange={(e) =>
                            form.setData('nro_transaccion', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Campo>

                <Campo
                    etiqueta={`Monto (${moneda})`}
                    htmlFor="monto"
                    obligatorio
                    error={form.errors.monto}
                    ayuda="Lo que dice la boleta"
                >
                    <Input
                        id="monto"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.data.monto}
                        onChange={(e) => form.setData('monto', e.target.value)}
                    />
                </Campo>

                {/* Vacío = hoy, y se dice: el servidor toma `now()` cuando no
                    llega nada. Es el mismo texto que campo-pagos.tsx, para que
                    el operador lea lo mismo entre por donde entre. */}
                <Campo
                    etiqueta="Fecha del depósito"
                    htmlFor="fecha_pago"
                    error={form.errors.fecha_pago}
                    ayuda="La del banco, no la de hoy. Vacío = hoy."
                >
                    <Input
                        id="fecha_pago"
                        type="date"
                        value={form.data.fecha_pago}
                        onChange={(e) => form.setData('fecha_pago', e.target.value)}
                    />
                </Campo>

                <Campo etiqueta="Boleta escaneada" htmlFor="comprobante" obligatorio>
                    <SelectorArchivo
                        id="comprobante"
                        archivo={form.data.comprobante}
                        onElegir={(a) => form.setData('comprobante', a)}
                        error={form.errors.comprobante}
                    />
                </Campo>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Registrar depósito'}
                </Button>
            </div>
        </form>
    );
}
