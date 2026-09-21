import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Banknote,
    BadgeCheck,
    Check,
    ExternalLink,
    Eye,
    Paperclip,
    Pencil,
    Plus,
    Printer,
    Send,
    Ship,
    Trash2,
    TriangleAlert,
    Undo2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { DialogoCorregirPago } from '@/components/panel/pagos/dialogo-corregir-pago';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import type {
    CarnetDelCupo,
    CupoFicha,
    FaenaDelCupo,
    PagoDelCupo,
    ReciboDelCupo,
} from '@/types/aprovechamientos';

/**
 *  LA FICHA DE UN CUPO
 */
export default function VerCupo({
    cupo,
    carnets,
    faenas,
    pagos,
    recibo,
    modoEstricto,
}: {
    cupo: CupoFicha;
    carnets: CarnetDelCupo[];
    faenas: FaenaDelCupo[];
    /** Los depósitos que pagaron este cupo, del más nuevo al más viejo. */
    pagos: PagoDelCupo[];
    /**
     * EL RECIBO DEL TRÁMITE, uno solo. Llega en null mientras el cupo está
     * pendiente: recién se emite al enviarlo a revisión.
     */
    recibo: ReciboDelCupo | null;
    /** Lo que dice APROVECHAMIENTO_ESTRICTO: cambia qué significa un saldo en cero. */
    modoEstricto: boolean;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [eliminando, setEliminando] = useState(false);
    const [rechazando, setRechazando] = useState(false);
    const [aprobando, setAprobando] = useState(false);
    const [confirmandoPago, setConfirmandoPago] = useState(false);

    // Guardan el PAGO entero y no su id: las dos ventanas muestran sus datos.
    const [observando, setObservando] = useState<PagoDelCupo | null>(null);
    const [corrigiendo, setCorrigiendo] = useState<PagoDelCupo | null>(null);

    /* Validar no manda ningún dato: es un PATCH y el servidor sabe quién es. */
    const control = useForm({});
    const observacion = useForm({ motivo: '' });

    /*
     * FORMULARIO PROPIO PARA EL RECHAZO, separado del borrado. Los dos tienen un
     * campo `motivo` pero distinto destino y distintas reglas: mezclados, el
     * error de uno se pintaría en la ventana del otro —las dos leen
     * `form.errors.motivo`— y el texto escrito para uno seguiría ahí al abrir el
     * otro.
     */
    const rechazo = useForm({ motivo: '' });
    const envio = useForm({});

    const borrado = useForm({ motivo: '' });

    /*
     *  EL FORMULARIO ES UNA LISTA DE SECCIONES, NO UN PAGO
     */
    const seccionNueva = () => ({
        // `key` estable para React: sin ella, quitar la sección del medio
        // remonta las de abajo y les vacía el archivo elegido.
        key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        // VACÍO, no el saldo propuesto: el monto es el que dice la BOLETA, y un
        // campo ya lleno se confirma sin leerlo. Lo que falta se ve abajo.
        monto: '',
        nro_transaccion: '',
        // Se propone hoy, que es lo normal: la boleta suele traerse el mismo día.
        fecha_deposito: new Date().toISOString().slice(0, 10),
        comprobante: null as File | null,
    });

    const pago = useForm({
        pagos: [] as ReturnType<typeof seccionNueva>[],
        /*
         * LA INTENCIÓN DE ENVIAR, que viaja con los depósitos.
         */
        enviar: false,
    });

    const cobrando = pago.data.pagos.length > 0;

    /*
     * LO QUE SUMAN LAS SECCIONES, para saber si con esto alcanza.
     *
     * `Number('')` da 0 y no NaN, así que una sección recién agregada no rompe
     * la cuenta mientras el operador todavía no escribió el monto.
     */
    const sumaSecciones = pago.data.pagos.reduce((s, x) => s + Number(x.monto || 0), 0);
    const faltaDespues = Math.round((cupo.saldo_pendiente - sumaSecciones) * 100) / 100;

    /*
     * ¿CON ESTO ALCANZA? Son DOS preguntas y estaban en una sola: cubrir el
     * monto es lo que habilita REGISTRAR —los depósitos entran todos juntos,
     * no en cuotas— y enviar pide además el permiso. Mezcladas, a quien no
     * puede enviar se le apagaba el botón de cargar boletas.
     */
    const cubierto = faltaDespues <= 0;
    const cubre = cubierto && puede('aprovechamientos.enviar');

    function agregarSeccion() {
        pago.setData('pagos', [...pago.data.pagos, seccionNueva()]);
    }

    /**
     * Guarda los depósitos, y los ENVÍA si cubren el monto.
     */
    function registrarDepositos() {
        /*
         * `transform` y no `setData`: setData es asincrónico y el post saldría
         * con el valor anterior. Transform corre justo antes de armar el cuerpo.
         */
        pago.transform((datos) => ({ ...datos, enviar: cubre }));

        /*
         * `forceFormData` es obligatorio: sin él Inertia manda el cuerpo como
         * JSON y los archivos se pierden en el camino, sin ningún error.
         */
        pago.post(route('aprovechamientos.pagar', cupo.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                pago.reset();
                setConfirmandoPago(false);
            },
            // Se cierra también al fallar: los errores se pintan sobre el
            // formulario, y con la ventana encima no se ven.
            onError: () => setConfirmandoPago(false),
        });
    }

    function quitarSeccion(key: string) {
        pago.setData('pagos', pago.data.pagos.filter((x) => x.key !== key));
    }

    function cambiarSeccion(key: string, campo: string, valor: string | File | null) {
        pago.setData(
            'pagos',
            pago.data.pagos.map((x) => (x.key === key ? { ...x, [campo]: valor } : x)),
        );
    }

    /** El número de boleta es numérico: lo que no sea dígito no entra. */
    const soloDigitos = (valor: string): string => valor.replace(/\D/g, '');

    /** El error que el servidor devolvió para la sección `i`. */
    const errorDe = (i: number, campo: string): string | undefined =>
        (pago.errors as Record<string, string | undefined>)[`pagos.${i}.${campo}`];

    return (
        <LayoutPanel
            /*
             * EL ENCABEZADO NO REPITE AL TITULAR. El nombre, la cédula y el
             * tramo están en la tarjeta de abajo, con la foto al lado; acá
             * decían lo mismo sin la cara, y la pantalla abría con el nombre
             * escrito dos veces. Arriba quedan las acciones, que es lo que se
             * busca en el encabezado.
             */
            // El nombre oficial del documento, el mismo que imprime el
            // recibo. Ver App\Enums\ConceptoRecibo.
            titulo="Autorización de Pesca para Aprovechamiento Pesquero"
            acciones={
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="ver"
                        onClick={() =>
                            router.visit(route('beneficiarios.show', cupo.beneficiario_id))
                        }
                    >
                        <Eye className="size-4" />
                        Ver al pescador
                    </Button>

                    {/* LA AUTORIZACIÓN DE PESCA, en PDF. Sale recién con el cupo
                        firmado, y abre una pestaña porque lo que vuelve es un
                        archivo: el visor del navegador es desde donde se imprime. */}
                    {puede('aprovechamientos.imprimir') && cupo.ya_fue_aprobado && (
                        <a
                            href={route('aprovechamientos.autorizacion', cupo.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Printer className="size-4" />
                            Autorización de pesca
                        </a>
                    )}

                    {/*
                        EDITAR Y ELIMINAR SOLO SOBRE EL BORRADOR.
                    */}
                    {puede('aprovechamientos.editar') && cupo.puede_editarse && (
                        <Button
                            variant="editar"
                            onClick={() => router.visit(route('aprovechamientos.edit', cupo.id))}
                        >
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}

                    {puede('aprovechamientos.eliminar') && cupo.puede_eliminarse && (
                        <Button
                            variant="eliminar"
                            onClick={() => setEliminando(true)}
                        >
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}

                    {/*
                        ENVIAR A REVISIÓN. `puede_enviarse` ya trae las dos
                        condiciones adentro: pendiente Y con el monto cubierto.
                        Con solo el estado, el botón se ofrecería sobre un cupo a
                        medio pagar y el servidor lo rechazaría.
                    */}
                    {puede('aprovechamientos.enviar') && cupo.puede_enviarse && (
                        <Button
                            onClick={() =>
                                envio.post(route('aprovechamientos.enviar', cupo.id), {
                                    preserveScroll: true,
                                })
                            }
                            disabled={envio.processing}
                        >
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}

                    {/*
                        APROBAR Y RECHAZAR son de SUPERVISIÓN, y van juntas: quien
                        puede firmar puede devolver. Solo sobre lo presentado.
                    */}
                    {puede('aprovechamientos.aprobar') && cupo.puede_revisarse && (
                        <>
                            {/* Se apaga mientras falte validar alguna boleta, y
                                el title dice cuántas: el servidor lo exige igual,
                                y un botón que promete y falla es peor. */}
                            <Button
                                onClick={() => setAprobando(true)}
                                disabled={envio.processing || !cupo.puede_aprobarse}
                                title={
                                    cupo.puede_aprobarse
                                        ? undefined
                                        : `Faltan ${cupo.pagos_sin_validar} depósito(s) por validar`
                                }
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <Check className="size-4" />
                                Aprobar
                            </Button>

                            <Button
                                variant="eliminar"
                                onClick={() => setRechazando(true)}
                            >
                                <Undo2 className="size-4" />
                                Rechazar
                            </Button>
                        </>
                    )}

                    {/*
                        NO HAY BOTÓN «COBRAR» ACÁ, y es deliberado: los depósitos
                        se cargan más abajo, en la tarjeta de Pagos, con una
                        sección por boleta. Mandar al operador a Caja lo sacaba de
                        la ficha para hacer lo mismo que puede hacer sin moverse,
                        y perdiendo de vista el saldo.
                    */}
                </div>
            }
        >
            <Head title={`Autorización de pesca · ${cupo.beneficiario ?? ''}`} />

            {/*
                EL TITULAR, CON SU FOTO. El encabezado del layout solo admite
                texto, y sobre un cupo la primera pregunta es de QUIÉN es: la
                cara al lado del nombre es lo que deja confirmarlo de un
                vistazo contra la persona que está en el mostrador.
            */}
            <Card className="mb-6 min-w-0">
                <CardContent className="flex flex-wrap items-center gap-4 p-4">
                    <Retrato
                        url={cupo.foto_url}
                        nombre={cupo.beneficiario ?? 'Sin nombre'}
                        className="size-16"
                    />

                    <div className="min-w-0">
                        {/* Al nombre se le va: la ficha de la persona es donde
                            están sus otros carnets y sus otros trámites. */}
                        <Link
                            href={route('beneficiarios.show', cupo.beneficiario_id)}
                            className="text-lg font-semibold text-primary hover:underline"
                        >
                            {cupo.beneficiario ?? '—'}
                        </Link>

                        {/* Con el rótulo adelante: «3944217 PT» solo no dice
                            qué número es. tabular-nums para que la cédula quede
                            alineada con el resto de los números de la ficha. */}
                        <p className="tabular-nums text-sm text-muted-foreground">
                            C.I. {cupo.documento ?? '—'}
                        </p>

                        {/* El TRAMO sin su número: «Escala 3» es un dato del
                            catálogo interno, y el rango en kilos es lo que
                            dice de verdad cuánto se autorizó. */}
                        <p className="text-sm text-muted-foreground">
                            {cupo.descripcion ?? '—'}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------------ El saldo */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Volumen</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* Antes de la firma dice «kg solicitados» y no dibuja saldo. */}
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

                        {/*
                            EL DESGLOSE APARECE CUANDO HAY ALGO QUE DESGLOSAR.
                            «Otorgado» recién después de la firma —antes es lo
                            pedido— y consumido/disponible/usado recién cuando
                            se emitió alguna faena: con el cupo entero los tres
                            dicen lo mismo que el primero, y «disponible 300 de
                            300» sobre un cupo sin usar suena a que algo pasó.
                        */}
                        <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            {! cupo.ya_fue_aprobado ? (
                                <Dato etiqueta="Solicitado" valor={`${cupo.volumen_total_kg} kg`} />
                            ) : cupo.kilos_consumidos <= 0 ? (
                                <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                            ) : (
                                <>
                                    <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                                    <Dato etiqueta="Consumido" valor={`${cupo.kilos_consumidos} kg`} />
                                    <Dato etiqueta="Disponible" valor={`${cupo.saldo_kg} kg`} />
                                    <Dato etiqueta="Usado" valor={`${cupo.porcentaje_usado}%`} />
                                </>
                            )}
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
                                    <strong>Pendiente.</strong> Todavía no autoriza a pescar. Cargue
                                    los depósitos hasta cubrir el monto y recién ahí se puede enviar
                                    a revisión. Mientras tanto se puede corregir o eliminar.
                                </span>
                            </p>
                        )}

                        {/*
                            EN REVISIÓN TAMPOCO AUTORIZA, y conviene decirlo: el
                            monto ya está cubierto, así que sin este aviso se
                            leería como «listo» y alguien intentaría emitir una
                            faena para descubrirlo con el error.
                        */}
                        {cupo.estado === 'en_revision' && (
                            <p className="flex items-start gap-2 rounded-md bg-indigo-50 p-3 text-sm text-indigo-900 dark:bg-indigo-500/10 dark:text-indigo-200">
                                <Send className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    <strong>En revisión.</strong> Los depósitos cubren el monto y el
                                    expediente está presentado. Todavía no autoriza a pescar: hace
                                    falta que alguien verifique las boletas y lo apruebe.
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
                            valor={cupo.tipo_embarcacion}
                        />

                        {/* Dos fechas distintas: cuándo lo pidió y cuándo se lo
                            firmaron. La segunda no existe hasta la aprobación. */}
                        <Dato etiqueta="Solicitado el" valor={fecha(cupo.fecha_solicitud)} />

                        {cupo.fecha_emision !== null && (
                            <Dato etiqueta="Otorgado el" valor={fecha(cupo.fecha_emision)} />
                        )}
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
                            La conclusión y su MOTIVO, los dos resueltos por el
                            servidor: un cupo pendiente no es uno vencido, y
                            decirlo mal manda a buscar un problema que no existe.
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
                                : cupo.motivo_sin_faena}
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
                        */}
                        {/*
                            SOLO MIENTRAS ADMITE PAGOS. En revisión el monto ya
                            está cubierto y el expediente presentado; aprobado, la
                            plata que entre de más no es de este trámite.
                        */}
                        {puede('caja.cobrar') && cupo.admite_pagos && (
                            <div className="flex gap-2">
                                {cobrando && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            pago.setData('pagos', []);
                                            pago.clearErrors();
                                        }}
                                    >
                                        <X className="size-4" />
                                        Cancelar
                                    </Button>
                                )}

                                <Button size="sm" onClick={agregarSeccion}>
                                    <Plus className="size-4" />
                                    Agregar pago
                                </Button>
                            </div>
                        )}
                    </CardHeader>

                    <CardContent className="space-y-5 p-0">
                        {cobrando && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    // El botón no guarda: abre la confirmación.
                                    setConfirmandoPago(true);
                                }}
                                className="mx-5 space-y-4"
                            >
                                {/* El error general del arreglo: «agregue al
                                    menos uno», o el que devuelve el servicio. */}
                                {pago.errors.pagos && (
                                    <p className="text-sm text-destructive">{pago.errors.pagos}</p>
                                )}

                                {pago.data.pagos.map((s, i) => (
                                    <div
                                        key={s.key}
                                        className="space-y-4 rounded-md border border-border bg-secondary/30 p-4"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium">Depósito {i + 1}</p>

                                            {/* QUITAR ESTA SECCIÓN. Va por `key` y
                                                no por índice: por índice, quitar
                                                la del medio corre a las de abajo
                                                y se llevan el archivo equivocado. */}
                                            <Button
                                                type="button"
                                                variant="eliminar"
                                                size="sm"
                                                onClick={() => quitarSeccion(s.key)}
                                                aria-label={`Quitar el depósito ${i + 1}`}
                                                title="Quitar este depósito"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Campo
                                                etiqueta="Monto del depósito"
                                                htmlFor={`monto-${s.key}`}
                                                error={errorDe(i, 'monto')}
                                                obligatorio
                                            >
                                                <Input
                                                    id={`monto-${s.key}`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    placeholder="0.00"
                                                    value={s.monto}
                                                    onChange={(e) =>
                                                        cambiarSeccion(s.key, 'monto', e.target.value)
                                                    }
                                                    aria-invalid={Boolean(errorDe(i, 'monto'))}
                                                />
                                            </Campo>

                                            <Campo
                                                etiqueta="Fecha del depósito"
                                                htmlFor={`fecha-${s.key}`}
                                                error={errorDe(i, 'fecha_deposito')}
                                                ayuda="La que dice la boleta, no la de hoy."
                                                obligatorio
                                            >
                                                <Input
                                                    id={`fecha-${s.key}`}
                                                    type="date"
                                                    value={s.fecha_deposito}
                                                    onChange={(e) =>
                                                        cambiarSeccion(
                                                            s.key,
                                                            'fecha_deposito',
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={Boolean(errorDe(i, 'fecha_deposito'))}
                                                />
                                            </Campo>

                                            <Campo
                                                etiqueta="N° de transacción"
                                                htmlFor={`nro-${s.key}`}
                                                error={errorDe(i, 'nro_transaccion')}
                                                ayuda="No se puede repetir: una misma boleta no respalda dos pagos."
                                                obligatorio
                                            >
                                                {/*
                                                    SOLO DÍGITOS, y el campo es de
                                                    TEXTO: un `type="number"` se
                                                    come los ceros de adelante, y
                                                    la boleta suele empezar con
                                                    ellos. Ver `soloDigitos()`.
                                                */}
                                                <Input
                                                    id={`nro-${s.key}`}
                                                    inputMode="numeric"
                                                    value={s.nro_transaccion}
                                                    onChange={(e) =>
                                                        cambiarSeccion(
                                                            s.key,
                                                            'nro_transaccion',
                                                            soloDigitos(e.target.value),
                                                        )
                                                    }
                                                    aria-invalid={Boolean(errorDe(i, 'nro_transaccion'))}
                                                    placeholder="0012345678"
                                                    className="font-mono"
                                                    maxLength={60}
                                                />
                                            </Campo>

                                            <Campo
                                                etiqueta="Boleta del depósito"
                                                htmlFor={`comprobante-${s.key}`}
                                                error={errorDe(i, 'comprobante')}
                                                ayuda="Foto o PDF, hasta 3 MB."
                                                obligatorio
                                            >
                                                <SelectorArchivo
                                                    id={`comprobante-${s.key}`}
                                                    archivo={s.comprobante}
                                                    onElegir={(a) =>
                                                        cambiarSeccion(s.key, 'comprobante', a)
                                                    }
                                                    error={errorDe(i, 'comprobante')}
                                                />
                                            </Campo>
                                        </div>
                                    </div>
                                ))}

                                {/*
                                    LA CUENTA A LA VISTA, que es lo que decide si
                                    el cupo se va a poder presentar: las secciones
                                    tienen que cubrir el saldo. De MÁS se admite
                                    —la boleta dice lo que dice y el excedente
                                    queda a favor de la entidad—; de menos no.
                                */}
                                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md bg-secondary/50 p-3 text-sm">
                                    <span>
                                        Suman{' '}
                                        <strong className="tabular-nums">
                                            {bs(sumaSecciones, institucion.moneda)}
                                        </strong>{' '}
                                        de {bs(cupo.saldo_pendiente, institucion.moneda)} pendientes
                                    </span>

                                    <span
                                        className={
                                            faltaDespues > 0
                                                ? 'font-medium text-amber-700 dark:text-amber-400'
                                                : 'font-medium text-emerald-700 dark:text-emerald-400'
                                        }
                                    >
                                        {faltaDespues > 0
                                            ? `Faltan ${bs(faltaDespues, institucion.moneda)}`
                                            : faltaDespues < 0
                                              ? `Cubre el monto · ${bs(-faltaDespues, institucion.moneda)} de más`
                                              : 'Cubre el monto'}
                                    </span>
                                </div>

                                {/* APAGADO MIENTRAS NO CUBRA. El servidor lo
                                    rechaza igual —ver CobrarService— y un botón
                                    que promete y falla es peor que uno gris. */}
                                <Button
                                    type="submit"
                                    disabled={
                                        pago.processing ||
                                        !cubierto ||
                                        pago.data.pagos.some(
                                            (s) =>
                                                Number(s.monto) <= 0 ||
                                                s.fecha_deposito === '' ||
                                                s.nro_transaccion.trim() === '' ||
                                                s.comprobante === null,
                                        )
                                    }
                                >
                                    <Banknote className="size-4" />
                                    {cubre
                                        ? `Registrar ${pago.data.pagos.length} depósito(s) y enviar a revisión`
                                        : `Registrar ${pago.data.pagos.length} depósito(s)`}
                                </Button>

                                {/* Se dice qué va a pasar al apretar, porque el
                                    botón hace DOS cosas y una de ellas cierra la
                                    puerta: en revisión ya no se edita ni se
                                    elimina. */}
                                {!cubierto ? (
                                    <p className="text-xs text-amber-700 dark:text-amber-400">
                                        Faltan {bs(faltaDespues, institucion.moneda)} para cubrir el
                                        monto. El trámite se cobra entero: agregue las boletas que
                                        falten y regístrelas todas juntas.
                                    </p>
                                ) : (
                                    cubre && (
                                        <p className="text-xs text-muted-foreground">
                                            Al registrarlos, el aprovechamiento pasa a EN REVISIÓN y
                                            deja de poder editarse o eliminarse.
                                        </p>
                                    )
                                )}
                            </form>
                        )}

                        {/* El recibo va UNA vez, arriba del detalle que ampara.
                            Era una columna de la tabla, y el número repetido en
                            cada fila se leía como «un recibo por depósito». */}
                        {pagos.length > 0 && (
                            <div className="mx-5 mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-secondary/40 px-4 py-3">
                                {recibo ? (
                                    <>
                                        <div className="min-w-0">
                                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                                Recibo del trámite
                                            </p>

                                            <Link
                                                href={route('recibos.show', recibo.id)}
                                                className="font-mono font-medium text-primary hover:underline"
                                            >
                                                {recibo.numero_recibo}
                                            </Link>

                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {pagos.length} depósito(s) · emitido el{' '}
                                                {fechaHora(recibo.emitido_en)}
                                            </span>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <span className="font-medium tabular-nums">
                                                {bs(recibo.monto_total, institucion.moneda)}
                                            </span>

                                            {/* Abre una pestaña y no navega con
                                                Inertia: lo que vuelve es un PDF,
                                                y el visor del navegador es desde
                                                donde se imprime. */}
                                            {puede('recibos.imprimir') && (
                                                <a
                                                    href={route('recibos.imprimir', recibo.id)}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className={cn(buttonVariants({ variant: 'outline' }))}
                                                >
                                                    <Printer className="size-4" />
                                                    Imprimir
                                                </a>
                                            )}
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Todavía no se emitió el recibo. Sale uno solo, con el total de
                                        todos los depósitos, al enviar el aprovechamiento a revisión.
                                    </p>
                                )}
                            </div>
                        )}

                        {/* El vacío se calla mientras se está cargando un
                            depósito: decir «sin pagos» abajo del formulario
                            abierto se lee como que lo tipeado no entró. */}
                        {pagos.length === 0 ? (
                            !cobrando && (
                                <EstadoVacio
                                    icono={Banknote}
                                    titulo="Sin pagos registrados"
                                    descripcion="El cupo no autoriza faenas hasta que la concesión esté cobrada."
                                />
                            )
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Boleta</th>
                                            <th className="px-5 py-2.5 font-medium">Depositado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                            <th className="px-5 py-2.5 font-medium">Cargado</th>
                                            <th className="px-5 py-2.5 font-medium">Validación</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {pagos.map((p) => (
                                            <tr key={p.id} className="hover:bg-secondary/50">
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
                                                    {p.registrado_por && (
                                                        <span className="block text-xs">
                                                            por {p.registrado_por}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* El control de la boleta: quién la
                                                    miró y cuándo. El motivo va DEBAJO
                                                    y no en un title — es lo que dice
                                                    qué corregir. */}
                                                <td className="px-5 py-2.5">
                                                    <Badge color={p.estado_validacion_color}>
                                                        {p.estado_validacion_etiqueta}
                                                    </Badge>

                                                    {p.validado_por && (
                                                        <span className="mt-1 block text-xs text-muted-foreground">
                                                            {p.validado_por} · {fechaHora(p.validado_en)}
                                                        </span>
                                                    )}

                                                    {p.observacion && (
                                                        <span className="mt-1 block max-w-60 text-xs text-rose-700 dark:text-rose-300">
                                                            {p.observacion}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Validar y observar son de
                                                    SUPERVISIÓN; corregir, de
                                                    ventanilla. Sobre un observado no
                                                    aparece «Validar»: se corrige. */}
                                                <td className="px-5 py-2.5">
                                                    <div className="flex justify-end gap-1">
                                                        {puede('pagos.controlar') &&
                                                            p.puede_validarse && (
                                                                <>
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            control.patch(
                                                                                route(
                                                                                    'pagos.validar',
                                                                                    p.id,
                                                                                ),
                                                                                { preserveScroll: true },
                                                                            )
                                                                        }
                                                                        disabled={control.processing}
                                                                        title="La boleta cuadra con el extracto del banco"
                                                                        className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-500/40 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                                                    >
                                                                        <Check className="size-4" />
                                                                        Validar
                                                                    </Button>

                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            setObservando(p)
                                                                        }
                                                                        title="No cuadra: hay que escribir por qué"
                                                                        className="border-rose-300 text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                                                    >
                                                                        <TriangleAlert className="size-4" />
                                                                        Observar
                                                                    </Button>
                                                                </>
                                                            )}

                                                        {puede('pagos.corregir') &&
                                                            p.puede_corregirse && (
                                                                <Button
                                                                    variant="editar"
                                                                    size="sm"
                                                                    onClick={() => setCorrigiendo(p)}
                                                                    title="Corregir el monto, la boleta o la fecha"
                                                                >
                                                                    <Pencil className="size-4" />
                                                                    Corregir
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
                    </CardContent>
                </Card>

                {/*
                    LAS CÉDULAS QUE SE APOYAN EN ESTE CUPO, de la más nueva a
                    la más vieja. Aparece desde que el cupo está firmado —antes
                    no puede haber ninguna— o si igual hubiera filas.
                */}
                {(cupo.ya_fue_aprobado || carnets.length > 0) && (
                    <Card className="min-w-0 lg:col-span-3">
                        <CardHeader>
                            <CardTitle>Cédulas emitidas con este autorización</CardTitle>
                        </CardHeader>

                        <CardContent className="p-0">
                            {carnets.length === 0 ? (
                                <EstadoVacio
                                    icono={BadgeCheck}
                                    titulo="Sin cédulas"
                                    descripcion="Todavía no se registró ninguna credencial contra este cupo."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-5 py-2.5 font-medium">Registro</th>
                                                <th className="px-5 py-2.5 font-medium">Tipo</th>
                                                <th className="px-5 py-2.5 font-medium">Solicitado</th>
                                                <th className="px-5 py-2.5 font-medium">Emitido</th>
                                                <th className="px-5 py-2.5 font-medium">Vence</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                {/* Sin rótulo: el ojo se explica solo. */}
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {carnets.map((c) => (
                                                <tr key={c.id} className="hover:bg-secondary/50">
                                                    <td className="px-5 py-2.5">
                                                        <a
                                                            href={route('carnets.show', c.id)}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="font-mono font-medium text-primary hover:underline"
                                                        >
                                                            {c.registro ?? c.codigo}
                                                        </a>

                                                        {/* El código va debajo y en gris: en el
                                                            mostrador se dicta el registro. */}
                                                        {c.registro && (
                                                            <p className="font-mono text-xs text-muted-foreground">
                                                                {c.codigo}
                                                            </p>
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        {c.tipo ?? '—'}
                                                        <p className="text-xs text-muted-foreground">
                                                            {c.tipo_actor_etiqueta}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-2.5 text-muted-foreground">
                                                        {fecha(c.fecha_solicitud)}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-muted-foreground">
                                                        {c.fecha_emision ? fecha(c.fecha_emision) : '—'}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-muted-foreground">
                                                        {fecha(c.fecha_vencimiento)}
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <Badge color={c.estado_color}>
                                                            {c.estado_etiqueta}
                                                        </Badge>
                                                    </td>

                                                    {/*
                                                        EN UNA PESTAÑA APARTE, igual que el enlace
                                                        al cupo desde la ficha del carnet: la
                                                        cédula se abre para contrastarla con lo
                                                        que se está mirando acá, y salir obliga a
                                                        volver y buscar el cupo de nuevo.
                                                    */}
                                                    <td className="px-5 py-2.5">
                                                        <div className="flex justify-end">
                                                            <a
                                                                href={route('carnets.show', c.id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                title="Abrir la cédula en otra pestaña"
                                                                aria-label={`Ver la cédula ${c.registro ?? c.codigo}`}
                                                                className={cn(
                                                                    buttonVariants({
                                                                        variant: 'ver',
                                                                        size: 'sm',
                                                                    }),
                                                                )}
                                                            >
                                                                <ExternalLink className="size-4" />
                                                                Ver
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/*
                    LAS FAENAS, solo desde que el cupo pasó por la firma. Antes
                    no puede haber ninguna —emitirlas lo exige activo— así que la
                    tarjeta solo decía «sin faenas» sobre un cupo recién creado,
                    como si faltara hacer algo. Si igual hay filas —un cupo que
                    venció después de emitir— se muestran.
                */}
                {(cupo.ya_fue_aprobado || faenas.length > 0) && (
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
                )}
            </div>

            {/*
                AMPLIAR PIDE MOTIVO POR ESCRITO. Es dar más kilos de los que la
                escala otorgaba —lo que el cupo viene a limitar— así que sin el
                motivo, dentro de seis meses nadie puede explicar por qué esta
                persona tuvo 800 kg cuando su tramo daba 500.
            */}
            {/*
                 ELIMINAR PIDE MOTIVO **Y** CASILLA DE CONSENTIMIENTO
            */}
            {/*
                REGISTRAR LOS DEPÓSITOS TAMBIÉN SE CONFIRMA. Cuando cubren el
                monto el botón hace DOS cosas —guarda y presenta— y la segunda
                cierra la puerta: sale el recibo numerado y el cupo deja de
                poder editarse o eliminarse. La ventana dice cuánto se va a
                cargar, para contrastarlo con las boletas que están sobre el
                mostrador antes de que sea tarde.
            */}
            <ConfirmarAccion
                abierto={confirmandoPago}
                tono="afirmativo"
                titulo={cubre ? 'Registrar y enviar a revisión' : 'Registrar los depósitos'}
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se cargan{' '}
                            <strong>
                                {pago.data.pagos.length} depósito(s) por{' '}
                                {bs(sumaSecciones, institucion.moneda)}
                            </strong>{' '}
                            al cupo de <strong>{cupo.beneficiario ?? 'el pescador'}</strong>.
                        </p>

                        {cubre && (
                            <p>
                                El aprovechamiento pasa a <strong>EN REVISIÓN</strong>, se emite el
                                recibo con el total y deja de poder editarse o eliminarse.
                            </p>
                        )}
                    </div>
                }
                confirmacion={
                    cubre
                        ? 'Los montos y los números de boleta coinciden con los comprobantes del banco.'
                        : undefined
                }
                textoConfirmar={cubre ? 'Registrar y enviar' : 'Registrar'}
                procesando={pago.processing}
                onCancelar={() => setConfirmandoPago(false)}
                onConfirmar={registrarDepositos}
            />

            {/*
                APROBAR PIDE CASILLA. No destruye nada, pero es la FIRMA: desde
                acá el cupo autoriza a pescar y el expediente ya no vuelve —no
                hay «des-aprobar»—. La casilla es la declaración de que las
                boletas se miraron contra el extracto, que es lo que esa firma
                significa.
            */}
            <ConfirmarAccion
                abierto={aprobando}
                tono="afirmativo"
                titulo="Aprobar el aprovechamiento"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El cupo de <strong>{cupo.beneficiario ?? 'el pescador'}</strong> queda
                            ACTIVO por{' '}
                            <strong>
                                {cupo.volumen_total_kg} kg hasta el {fecha(cupo.fecha_vencimiento)}
                            </strong>
                            , y desde ese momento se le pueden emitir faenas.
                        </p>
                        <p>
                            Se registra la fecha de otorgamiento de hoy.{' '}
                            <strong>No se puede deshacer.</strong>
                        </p>
                    </div>
                }
                confirmacion="Verifiqué las boletas contra el extracto del banco y el expediente está completo."
                textoConfirmar="Aprobar"
                procesando={envio.processing}
                onCancelar={() => setAprobando(false)}
                onConfirmar={() =>
                    envio.patch(route('aprovechamientos.aprobar', cupo.id), {
                        preserveScroll: true,
                        onSuccess: () => setAprobando(false),
                    })
                }
            />

            {/*
                RECHAZAR PIDE MOTIVO **Y** CASILLA. El motivo es lo único que le
                dice a ventanilla QUÉ corregir —sin él el expediente rebota y se
                vuelve a presentar igual— y la casilla iguala el peso de las dos
                mitades de la firma: aprobar y rechazar se confirman igual.
            */}
            <ConfirmarConMotivo
                abierto={rechazando}
                titulo="Rechazar y devolver a ventanilla"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El cupo de <strong>{cupo.beneficiario ?? 'el pescador'}</strong> vuelve a
                            PENDIENTE.
                        </p>
                        <p>
                            Los depósitos ya cargados <strong>no se tocan</strong>: siguen colgando
                            del cupo, así que ventanilla corrige lo que haga falta y lo vuelve a
                            presentar sin recargar nada.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo del rechazo"
                ayuda="Es lo que va a leer quien tenga que corregirlo. Queda en la auditoría con su nombre."
                placeholder="La boleta DEP-0002 no figura en el extracto del banco."
                confirmacion="El expediente vuelve a ventanilla con este motivo escrito, y queda registrado a mi nombre."
                textoConfirmar="Rechazar"
                valor={rechazo.data.motivo}
                onCambiar={(v) => rechazo.setData('motivo', v)}
                error={rechazo.errors.motivo}
                procesando={rechazo.processing}
                onCancelar={() => {
                    setRechazando(false);
                    rechazo.reset();
                }}
                onConfirmar={() =>
                    rechazo.patch(route('aprovechamientos.rechazar', cupo.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRechazando(false);
                            rechazo.reset();
                        },
                    })
                }
            />

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

            {/* OBSERVAR — misma ventana que rechazar: quien corrige es otra
                persona y sin el texto no sabe qué arreglar. */}
            <ConfirmarConMotivo
                abierto={observando !== null}
                titulo="Observar este depósito"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Boleta{' '}
                            <strong className="font-mono">{observando?.nro_transaccion}</strong> por{' '}
                            <strong>{bs(observando?.monto_parcial ?? 0, institucion.moneda)}</strong>.
                        </p>
                        <p>
                            El depósito <strong>sigue sumando</strong> en el saldo: lo que queda en
                            duda es si la boleta respalda lo que dice, no que la plata esté.
                        </p>
                        <p>
                            Un observado <strong>no se valida: se corrige</strong>. Hasta que
                            ventanilla lo arregle, el aprovechamiento no se puede aprobar.
                        </p>
                    </div>
                }
                etiquetaMotivo="Qué no cuadra"
                ayuda="Es lo que va a leer quien tenga que corregirlo. Queda en la auditoría con su nombre."
                placeholder="El monto de la boleta dice 82,50 y en el extracto figuran 80,00."
                textoConfirmar="Observar"
                valor={observacion.data.motivo}
                onCambiar={(v) => observacion.setData('motivo', v)}
                error={observacion.errors.motivo}
                procesando={observacion.processing}
                onCancelar={() => {
                    setObservando(null);
                    observacion.reset();
                }}
                onConfirmar={() => {
                    if (!observando) return;

                    observacion.patch(route('pagos.observar', observando.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setObservando(null);
                            observacion.reset();
                        },
                    });
                }}
            />

            {/* CORREGIR — la única salida de una observación. */}
            <DialogoCorregirPago pago={corrigiendo} onCerrar={() => setCorrigiendo(null)} />
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
