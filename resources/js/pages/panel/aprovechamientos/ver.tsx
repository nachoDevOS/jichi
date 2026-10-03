import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Ban, Banknote, BadgeCheck, ExternalLink, Eye, Pencil, Plus, Printer, Receipt, RefreshCw, Ship, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { BarraSaldo } from '@/components/panel/aprovechamientos/barra-saldo';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    CarnetDelCupo,
    CupoFicha,
    FaenaDelCupo,
    ReciboDelCupo,
} from '@/types/aprovechamientos';

/**
 *  La ficha de un cupo
 */
export default function VerCupo({
    cupo,
    carnets,
    faenas,
    recibo,
    modoEstricto,
    carnetParaFaena,
}: {
    cupo: CupoFicha;
    carnets: CarnetDelCupo[];
    faenas: FaenaDelCupo[];
    /** El recibo: llega en null mientras está pendiente; se emite cuando SIREB confirma el pago. */
    recibo: ReciboDelCupo | null;
    /** Lo que dice APROVECHAMIENTO_ESTRICTO: cambia qué significa un saldo en cero. */
    modoEstricto: boolean;
    /** El carnet aprobado con el que se emite la próxima faena; null si ninguno puede. */
    carnetParaFaena: number | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [eliminando, setEliminando] = useState(false);
    const [reponiendo, setReponiendo] = useState<CarnetDelCupo | null>(null);
    const reposicion = useForm({ motivo: '' });
    const [revocando, setRevocando] = useState(false);
    const revocacion = useForm({ motivo: '' });

    const borrado = useForm({ motivo: '' });


    return (
        <LayoutPanel
            /*
             * El encabezado no repite al titular. El nombre, la cédula y el
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
                        Beneficiario
                    </Button>

                    {/* La autorización de pesca, en PDF. Sale recién con el cupo
                        firmado, y abre una pestaña porque lo que vuelve es un
                        archivo: el visor del navegador es desde donde se imprime. */}
                    {puede('aprovechamientos.imprimir') && cupo.puede_imprimirse && (
                        <a
                            href={route('aprovechamientos.autorizacion', cupo.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Printer className="size-4" />
                            Autorización
                        </a>
                    )}

                    {/* El recibo también se imprime desde arriba. Estaba solo
                        dentro de «Pagos», al final de la ficha: el operador que
                        venía a reimprimir el comprobante tenía que bajar a
                        buscarlo. Misma condición y mismo PDF que el de allá. */}
                    {puede('recibos.imprimir') && recibo && (
                        <a
                            href={route('recibos.imprimir', recibo.id)}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(buttonVariants({ variant: 'outline' }))}
                        >
                            <Receipt className="size-4" />
                            Recibo
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




                    {/* Revocar es una sanción: motivo obligatorio y no se deshace. */}
                    {puede('aprovechamientos.revocar') && cupo.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
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
            <Head title={`Autorización de Pesca para Aprovechamiento Pesquero · ${cupo.beneficiario ?? ''}`} />

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
                    </div>
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* ------------------------------------------------ El saldo */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Volumen</CardTitle>
                        {/* El TRAMO sin su número: el rango en kilos es lo que dice cuánto se autorizó. */}
                        <p className="text-sm text-muted-foreground">Capacidad: {cupo.descripcion ?? '—'}</p>
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
                            ) : cupo.kilos_consumidos <= 0 && cupo.kilos_reservados <= 0 ? (
                                <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                            ) : (
                                <>
                                    <Dato etiqueta="Otorgado" valor={`${cupo.volumen_total_kg} kg`} />
                                    <Dato etiqueta="Consumido" valor={`${cupo.kilos_consumidos} kg`} />
                                    <Dato etiqueta="Disponible" valor={`${cupo.saldo_kg} kg`} />
                                    <Dato etiqueta="Usado" valor={`${cupo.porcentaje_usado}%`} />
                                    {/* Reservar no descuenta: aparta kilos de faenas pendientes o en revisión. */}
                                    {cupo.kilos_reservados > 0 && (
                                        <>
                                            <Dato etiqueta="Reservado" valor={`${cupo.kilos_reservados} kg`} />
                                            <Dato etiqueta="Libre para faena" valor={`${cupo.libre_kg} kg`} />
                                        </>
                                    )}
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

                        {/* La llave del QR de la autorización impresa. */}
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">Código</span>
                            <span className="text-right font-mono font-medium">{cupo.codigo ?? '—'}</span>
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

                {/* El cobro: se paga en SIREB, igual que los otros tres documentos. */}
                <TarjetaRecaudaciones
                    className="lg:col-span-3"
                    monto={cupo.monto}
                    sireb={cupo.sireb}
                    recibo={recibo}
                    puedeVerificar={cupo.puede_verificar_pago}
                    rutaVerificar={route('aprovechamientos.verificar-pago', cupo.id)}
                    permiso="aprovechamientos.crear"
                />

                {/*
                    LAS CÉDULAS QUE SE APOYAN EN ESTE CUPO, de la más nueva a
                    la más vieja. Aparece desde que el cupo está firmado —antes
                    no puede haber ninguna— o si igual hubiera filas.
                */}
                {(cupo.ya_fue_aprobado || carnets.length > 0) && (
                    <Card className="min-w-0 lg:col-span-3">
                        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                            <CardTitle>Cédulas emitidas con este autorización</CardTitle>

                            {/* Llega con la persona y este cupo ya elegidos. */}
                            {puede('carnets.crear') && cupo.puede_emitir_carnet && (
                                <Button
                                    size="sm"
                                    onClick={() =>
                                        router.visit(route('carnets.create', { aprovechamiento: cupo.id }))
                                    }
                                >
                                    <Plus className="size-4" />
                                    Emitir cédula
                                </Button>
                            )}
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
                                                        <div className="flex justify-end gap-2">
                                                            {puede('carnets.revocar') &&
                                                                puede('carnets.crear') &&
                                                                c.puede_reponerse && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="eliminar"
                                                                        onClick={() => setReponiendo(c)}
                                                                    >
                                                                        <RefreshCw className="size-4" />
                                                                        Reponer
                                                                    </Button>
                                                                )}
                                                            {puede('carnets.imprimir') && c.puede_imprimirse && (
                                                                <a
                                                                    href={route('carnets.imprimir', c.id)}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                                                                >
                                                                    <Printer className="size-4" />
                                                                    Imprimir
                                                                </a>
                                                            )}
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
                        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                            <CardTitle>Faenas emitidas</CardTitle>

                            {/* Llega con el carnet aprobado de este cupo ya elegido. */}
                            {puede('faenas.crear') && carnetParaFaena !== null && (
                                <Button
                                    size="sm"
                                    onClick={() => router.visit(route('faenas.create', { carnet: carnetParaFaena }))}
                                >
                                    <Plus className="size-4" />
                                    Emitir faena
                                </Button>
                            )}
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
                                                <th className="px-5 py-2.5 font-medium">Carnet</th>
                                                <th className="px-5 py-2.5 text-right font-medium">Kilos</th>
                                                <th className="px-5 py-2.5 font-medium">Estado</th>
                                                <th className="px-5 py-2.5 font-medium">Salida</th>
                                                <th className="px-5 py-2.5 font-medium">Desembarque</th>
                                                <th className="px-5 py-2.5" />
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-border">
                                            {faenas.map((f) => (
                                                <tr key={f.id} className="hover:bg-secondary/50">
                                                    {/* El número lo arma el servidor con los
                                                        seis ceros del talonario: rellenarlo acá
                                                        daba «0001» donde el resto del sistema
                                                        dice «000001». */}
                                                    <td className="px-5 py-2.5 font-mono tabular-nums">
                                                        {f.numero_legible}
                                                    </td>

                                                    {/* De qué carnet cuelga. Un cupo puede
                                                        respaldar más de una credencial, y esta es
                                                        la columna que dice cuál gastó esos kilos. */}
                                                    <td className="px-5 py-2.5 font-mono tabular-nums text-muted-foreground">
                                                        {f.carnet_id !== null ? (
                                                            <Link
                                                                href={route('carnets.show', f.carnet_id)}
                                                                className="text-primary hover:underline"
                                                            >
                                                                N° {f.carnet_registro ?? '—'}
                                                            </Link>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-2.5 text-right tabular-nums">
                                                        {/*
                                                            Tachado cuando NO consume cupo —todavía sin
                                                            firmar, o vencida—: es lo que hace que la
                                                            suma de la columna cuadre con el saldo de
                                                            arriba.
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
                                                                {f.estado === 'vencido' || f.estado === 'revocado'
                                                                    ? 'liberados'
                                                                    : 'sin descontar'}
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
                                                        {fecha(f.fecha_desembarque)}
                                                    </td>

                                                    <td className="px-5 py-2.5">
                                                        <div className="flex justify-end gap-2">
                                                            {puede('faenas.imprimir') && f.puede_imprimirse && (
                                                                <a
                                                                    href={route('faenas.imprimir', f.id)}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className={cn(buttonVariants({ variant: 'outline', size: 'sm' }))}
                                                                >
                                                                    <Printer className="size-4" />
                                                                    Imprimir
                                                                </a>
                                                            )}
                                                            <a
                                                                href={route('faenas.show', f.id)}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className={cn(buttonVariants({ variant: 'ver', size: 'sm' }))}
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
            </div>



            {/*
                RECHAZAR PIDE MOTIVO **Y** CASILLA. El motivo es lo único que le
                dice a ventanilla QUÉ corregir —sin él el expediente rebota y se
                vuelve a presentar igual— y la casilla iguala el peso de las dos
                mitades de la firma: aprobar y rechazar se confirman igual.
            */}
            {/* REPONER: el mismo modal que revocar; después abre el formulario del nuevo. */}
            <ConfirmarConMotivo
                abierto={reponiendo !== null}
                titulo={`Reponer el carnet N° ${reponiendo?.registro ?? ''}`}
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{reponiendo?.codigo}</strong> de{' '}
                            <strong>{cupo.beneficiario ?? 'el titular'}</strong> deja de valer, y al
                            escanearlo figura como revocado. <strong>No se revierte.</strong>
                        </p>
                        <p>
                            Las faenas ya emitidas con él <strong>siguen vigentes</strong>. Después se abre
                            el formulario del carnet nuevo con esta misma Autorización de Pesca para Aprovechamiento Pesquero.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la reposición"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Extravío del carnet: el titular tramita la reposición."
                confirmacion="Entiendo que el carnet deja de valer y que la revocación no se revierte."
                textoConfirmar="Reponer carnet"
                valor={reposicion.data.motivo}
                onCambiar={(v) => reposicion.setData('motivo', v)}
                error={reposicion.errors.motivo}
                procesando={reposicion.processing}
                onCancelar={() => {
                    setReponiendo(null);
                    reposicion.reset();
                }}
                onConfirmar={() =>
                    reponiendo && reposicion.patch(route('carnets.reponer', reponiendo.id))
                }
            />

            <ConfirmarConMotivo
                abierto={revocando}
                titulo="Revocar la Autorización de Pesca para Aprovechamiento Pesquero"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La autorización de <strong>{cupo.beneficiario ?? 'el pescador'}</strong> queda
                            REVOCADA aunque siga en fecha.
                        </p>
                        <ul className="list-disc space-y-1 pl-5">
                            <li>No se emiten más carnets ni faenas con ella.</li>
                            <li>
                                Sus carnets y faenas aprobados <strong>quedan sin efecto</strong>: siguen
                                registrados como estaban, pero ya no están vigentes, el QR lo informa y no se
                                imprimen. La salida en curso se corta.
                            </li>
                            <li>Los que están pendientes o en revisión ya no se pueden aprobar.</li>
                            <li>La persona puede tramitar una autorización nueva.</li>
                        </ul>
                        <p>
                            <strong>No se puede deshacer.</strong>
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre. Es lo que explica la sanción más adelante."
                placeholder="Resolución SEDAG N° 045/2026: pesca en época de veda."
                confirmacion="Entiendo que la autorización deja de valer y que esto no se deshace desde el panel."
                textoConfirmar="Revocar"
                valor={revocacion.data.motivo}
                onCambiar={(v) => revocacion.setData('motivo', v)}
                error={revocacion.errors.motivo}
                procesando={revocacion.processing}
                onCancelar={() => {
                    setRevocando(false);
                    revocacion.reset();
                }}
                onConfirmar={() =>
                    revocacion.patch(route('aprovechamientos.revocar', cupo.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRevocando(false);
                            revocacion.reset();
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
                            <strong>{cupo.beneficiario ?? 'el pescador'}</strong>:{' '}
                            {cupo.volumen_total_kg} kg.
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


            {/* CORREGIR — la única salida de una observación. */}
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
