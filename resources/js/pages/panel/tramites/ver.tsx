import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    FileText,
    IdCard,
    Pencil,
    Printer,
    Receipt,
    RotateCcw,
    Send,
    TriangleAlert,
    Truck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { DialogoImprimirCarnet } from '@/components/panel/carnets/dialogo-imprimir-carnet';
import {
    FormularioDeposito,
    ListaDepositos,
    ResumenDepositos,
} from '@/components/panel/tramites/depositos';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { CarnetDelTramite, PagoDelTramite, ReciboDelTramite, TramiteFicha } from '@/types/tramites';

/**
 * ============================================================================
 *  LA FICHA DEL EXPEDIENTE
 * ============================================================================
 *
 * Papeles, dinero y los botones del circuito:
 *
 *     PENDIENTE ──[enviar]──▶ EN REVISIÓN ──┬──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
 *     (borrador)                            └──▶ RECHAZADO
 *
 * De ahí sale qué botones ve el operador, y son dos juegos que no se mezclan:
 *
 *   PENDIENTE    Editar · Eliminar · Enviar a revisión.  Es el borrador.
 *   EN REVISIÓN  Aprobar · Rechazar.  Ya está presentado: no se toca más.
 *
 * TODOS LOS `puede_*` LOS CALCULA EL SERVIDOR. La pantalla pregunta, no decide.
 * La regla de qué salto vale desde cada estado vive en
 * App\Enums\EstadoTramite; escrita otra vez acá, las dos versiones terminarían
 * diciendo cosas distintas y el operador vería un botón que el servidor rechaza
 * —o peor, no vería uno que sí puede usar—.
 *
 * Todas las acciones van por PATCH y no por POST: modifican parcialmente un
 * expediente que ya existe. Lo que no pueden ser nunca es GET, porque un verbo
 * de lectura que escribe se dispara solo con que el navegador precargue el
 * enlace.
 */
export default function VerTramite({
    tramite,
    carnet,
    pagos,
    recibo,
}: {
    tramite: TramiteFicha;
    carnet: CarnetDelTramite;
    pagos: PagoDelTramite[];
    recibo: ReciboDelTramite | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [rechazando, setRechazando] = useState(false);
    const [reabriendo, setReabriendo] = useState(false);
    const [imprimiendo, setImprimiendo] = useState(false);
    const rechazo = useForm({ motivo_rechazo: '' });

    return (
        <LayoutPanel
            titulo={`Trámite #${tramite.id}`}
            descripcion={`${tramite.tipo_etiqueta} · ${tramite.rubro ?? '—'} · carnet ${carnet.gestion}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {tramite.puede_editar && puede('tramites.editar') && (
                        <Button variant="outline" onClick={() => router.visit(route('tramites.edit', tramite.id))}>
                            <Pencil className="size-4" />
                            Editar trámite
                        </Button>
                    )}

                    {/*
                        REABRIR — el camino de vuelta de un rechazo.

                        El pescador volvió al mostrador con lo que le faltaba. El
                        expediente vuelve a ser BORRADOR, con sus depósitos y su
                        historial, en vez de obligar a presentar uno nuevo: el
                        nuevo nacería con cero cobrado mientras el dinero se
                        queda colgado del rechazado.

                        Va en `default` porque en un expediente rechazado es LA
                        acción que corresponde — no hay ninguna otra.

                        Pide confirmación pero NO motivo, al revés que rechazar:
                        esto no decide nada, devuelve los papeles a la mesa de
                        quien los arma, y el porqué ya está escrito en el rechazo
                        que se está atendiendo.
                    */}
                    {tramite.puede_reabrir && puede('tramites.editar') && (
                        <Button onClick={() => setReabriendo(true)}>
                            <RotateCcw className="size-4" />
                            Reabrir para corregir
                        </Button>
                    )}

                    {/*
                        ENVIAR A REVISIÓN — el paso obligatorio del circuito.

                        Mientras está PENDIENTE el expediente se arma; al
                        enviarlo, ventanilla declara que está completo y pasa a
                        quien lo verifica y firma. Sin esto no se puede aprobar:
                        el botón «Aprobar» no aparece hasta que el trámite está
                        EN REVISIÓN.

                        Va en `default` y no en `secondary`: en un expediente
                        pendiente es LA acción que corresponde, y tiene que
                        distinguirse de «Editar trámite», que es la otra.
                    */}
                    {tramite.puede_enviar && puede('tramites.editar') && (
                        <Button onClick={() => router.patch(route('tramites.enviar', tramite.id))}>
                            <Send className="size-4" />
                            Enviar a revisión
                        </Button>
                    )}

                    {/*
                        APROBAR SOLO APARECE CON EL MONTO CUBIERTO. El servidor lo
                        vuelve a comprobar igual —consultando la suma de los pagos
                        en el momento— pero esconder el botón evita que el
                        supervisor lo apriete y se lleve un error.
                    */}
                    {tramite.puede_aprobar && puede('tramites.aprobar') && (
                        <Button onClick={() => router.patch(route('tramites.aprobar', tramite.id))}>
                            <Check className="size-4" />
                            Aprobar
                        </Button>
                    )}

                    {/*
                        RECHAZAR NO APARECE EN PENDIENTE.

                        No es que el botón estorbe: es que no hay nada que
                        rechazar. Un expediente pendiente es un BORRADOR que la
                        misma ventanilla está armando, y todavía no se lo
                        presentó a nadie. Si no sirve —se cargó dos veces, con
                        la persona equivocada— lo que corresponde es ELIMINARLO,
                        que también pide su motivo.

                        Rechazar es la respuesta a algo presentado, y por eso
                        `puede_rechazar` llega en true recién desde EN REVISIÓN.
                    */}
                    {tramite.puede_rechazar && puede('tramites.rechazar') && (
                        <Button variant="destructive" onClick={() => setRechazando((v) => !v)}>
                            <X className="size-4" />
                            Rechazar
                        </Button>
                    )}

                    {/*
                        EL RECIBO OFICIAL — el papel que se lleva el pescador.

                        Aparece desde que el expediente pasó por revisión, que es
                        cuando la plata entró. El servidor ya resolvió si
                        corresponde: si `recibo` llegó, el botón va.

                        Va como <a> y no como router.visit(): abre el PDF en otra
                        pestaña, y una navegación de Inertia no sabe qué hacer con
                        un archivo.

                        Reimprimir sale SIEMPRE con el mismo número —es el id del
                        expediente, que no cambia— así que el botón no se esconde
                        después de la primera vez: perder el recibo es justamente
                        el caso en el que hay que volver a sacarlo.
                    */}
                    {recibo && puede('recibos.imprimir') && (
                        <a
                            href={route('tramites.recibo', tramite.id)}
                            target="_blank"
                            rel="noopener"
                            className={buttonVariants({ variant: 'outline' })}
                        >
                            <Receipt className="size-4" />
                            Recibo N° {recibo.numero}
                        </a>
                    )}

                    {/*
                        EL CARNET — el plástico que se lleva la persona.

                        Aparece recién cuando hay algo que imprimir, y eso NO es
                        lo mismo que «el carnet existe»: la fila nace con el
                        expediente en PENDIENTE, pero el rubro se habilita al
                        APROBAR. Antes de eso el plástico saldría sin autorizar
                        nada. Lo decide el servidor en `puede_imprimirse`.

                        NO ABRE EL PDF DE UNA: muestra primero la vista
                        previa. Lo que se imprime es un plástico que se troquela
                        y se lamina, así que no se corrige — conviene mirarlo
                        antes de mandarlo, sobre todo para descubrir la ficha sin
                        foto. Ver DialogoImprimirCarnet.

                        ABRIRLO NO MARCA NADA. Mirar el documento en pantalla no
                        es haberlo sacado en la impresora de credenciales, y por
                        eso «Marcar impreso» sigue siendo un botón aparte.
                    */}
                    {carnet.puede_imprimirse && puede('carnets.generar') && (
                        <Button variant="outline" onClick={() => setImprimiendo(true)}>
                            <IdCard className="size-4" />
                            Imprimir carnet
                        </Button>
                    )}

                    {tramite.puede_generar && puede('carnets.generar') && (
                        <Button variant="dorado" onClick={() => router.patch(route('tramites.generar', tramite.id))}>
                            <Printer className="size-4" />
                            Marcar impreso
                        </Button>
                    )}

                    {tramite.puede_entregar && puede('carnets.entregar') && (
                        <Button variant="dorado" onClick={() => router.patch(route('tramites.entregar', tramite.id))}>
                            <Truck className="size-4" />
                            Marcar entregado
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`Trámite #${tramite.id}`} />

            <DialogoImprimirCarnet
                abierto={imprimiendo}
                carnetId={carnet.id}
                registro={carnet.registro}
                onCerrar={() => setImprimiendo(false)}
            />

            {/*
                EL AVISO DE LO QUE FALTA.

                Va ARRIBA DE TODO y no escondido junto a los adjuntos: es lo
                único que impide avanzar con el expediente, así que el operador
                tiene que verlo al abrir la ficha y no después de apretar un
                botón que no funciona.

                La lista la calcula el servidor —Tramite::faltantesParaRevision()—
                y es la MISMA que usa el servicio para rechazar la operación. Si
                la pantalla la recalculara por su cuenta, algún día diría una cosa
                distinta de la que aplica el servidor.
            */}
            {/*
                POR QUÉ NO SE PUEDE APROBAR TODAVÍA.

                Que el botón «Aprobar» desaparezca no alcanza: el supervisor tiene
                que poder saber el motivo sin apretar nada. Esto aparece solo
                cuando el dinero YA está cubierto —si falta plata, ese es el
                problema real y ese aviso lo da el bloque de depósitos— y todavía
                queda alguna boleta sin controlar.

                Los pendientes y los observados se cuentan por separado porque las
                dos salidas son distintas: uno se valida, el otro hay que
                corregirlo antes.
            */}
            {tramite.estado === 'en_revision' &&
                tramite.pagado &&
                tramite.pagos_por_controlar.pendientes + tramite.pagos_por_controlar.observados >
                    0 && (
                    <Card className="mb-6 border-amber-500/40 bg-amber-50/60 dark:bg-amber-950/20">
                        <CardContent className="flex gap-3 p-4 text-sm">
                            <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />

                            <div className="space-y-1">
                                <p className="font-medium">
                                    Falta controlar los depósitos: no se puede aprobar.
                                </p>

                                <ul className="list-inside list-disc text-muted-foreground">
                                    {tramite.pagos_por_controlar.pendientes > 0 && (
                                        <li>
                                            {tramite.pagos_por_controlar.pendientes === 1
                                                ? 'Hay 1 depósito sin validar'
                                                : `Hay ${tramite.pagos_por_controlar.pendientes} depósitos sin validar`}
                                        </li>
                                    )}

                                    {tramite.pagos_por_controlar.observados > 0 && (
                                        <li>
                                            {tramite.pagos_por_controlar.observados === 1
                                                ? '1 quedó observado y hay que corregirlo'
                                                : `${tramite.pagos_por_controlar.observados} quedaron observados y hay que corregirlos`}
                                        </li>
                                    )}
                                </ul>

                                <p className="text-muted-foreground">
                                    Se controlan boleta por boleta en el bloque de depósitos, más
                                    abajo.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

            {tramite.faltantes.length > 0 && (
                <Card className="mb-6 border-amber-500/40 bg-amber-50/60 dark:bg-amber-950/20">
                    <CardContent className="flex gap-3 p-4 text-sm">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />

                        <div className="space-y-1">
                            <p className="font-medium">
                                El expediente está incompleto: no se puede tomar para revisión.
                            </p>

                            <ul className="list-inside list-disc text-muted-foreground">
                                {tramite.faltantes.map((falta) => (
                                    <li key={falta}>Falta {falta}</li>
                                ))}
                            </ul>

                            {/* Los papeles se reemplazan en «Editar trámite»;
                                los depósitos se cargan acá abajo, en la tarjeta
                                de esta misma pantalla. */}
                            <p className="text-muted-foreground">
                                Los papeles se cargan con «Editar trámite»; los depósitos, más
                                abajo.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/*
                LA VENTANA DE RECHAZO.

                Se dibuja acá pero se posiciona sobre toda la pantalla, así que el
                lugar en el árbol no importa.

                El motivo NO es burocracia: el pescador vuelve a ventanilla a
                preguntar por qué le devolvieron los papeles, y sin el texto
                guardado nadie puede responderle. Además es lo que le permite
                presentar de nuevo con lo corregido.
            */}
            <ConfirmarConMotivo
                abierto={rechazando}
                titulo={`Rechazar el trámite #${tramite.id}`}
                // Ya no dice «no se puede volver atrás», porque ahora sí se
                // puede: el expediente se REABRE cuando el beneficiario trae lo
                // corregido, y vuelve con sus depósitos. Lo que no se deshace es
                // el rechazo en sí, que queda registrado.
                descripcion="Se le devuelven los papeles al beneficiario con el motivo escrito. Cuando vuelva con lo corregido, el expediente se puede reabrir y seguir trabajándolo."
                etiquetaMotivo="Motivo del rechazo"
                ayuda="Lo lee el beneficiario cuando vuelve a preguntar. Sea concreto: qué papel faltó o qué estaba mal."
                placeholder="Ej.: la fotocopia del carnet está ilegible, no se lee el número."
                textoConfirmar="Confirmar rechazo"
                valor={rechazo.data.motivo_rechazo}
                onCambiar={(v) => rechazo.setData('motivo_rechazo', v)}
                error={rechazo.errors.motivo_rechazo}
                procesando={rechazo.processing}
                onConfirmar={() =>
                    rechazo.patch(route('tramites.rechazar', tramite.id), {
                        onSuccess: () => setRechazando(false),
                    })
                }
                onCancelar={() => setRechazando(false)}
            />

            {/*
                LA VENTANA DE REABRIR.

                Sin motivo —ver el comentario del botón— pero CON confirmación,
                porque el expediente cambia de manos: deja de estar cerrado y
                vuelve a la mesa de ventanilla, donde se puede editar y donde el
                dinero se puede mover otra vez.

                La descripción dice lo que el operador necesita saber y no
                adivina: que los depósitos siguen ahí —que es justamente por lo
                que esto existe— y que el recibo ya entregado no cambia.
            */}
            <ConfirmarAccion
                abierto={reabriendo}
                titulo={`Reabrir el trámite #${tramite.id}`}
                descripcion={
                    <>
                        Vuelve a quedar <strong>pendiente</strong>, con sus depósitos y sus
                        papeles, para corregirlo y volver a enviarlo a revisión. El rechazo y
                        su motivo quedan registrados, y el recibo oficial conserva su número y
                        su fecha.
                    </>
                }
                textoConfirmar="Reabrir expediente"
                onConfirmar={() =>
                    router.patch(
                        route('tramites.reabrir', tramite.id),
                        {},
                        { onSuccess: () => setReabriendo(false) },
                    )
                }
                onCancelar={() => setReabriendo(false)}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                {/* --------------------------------------------- Columna izquierda */}
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Estado del expediente</CardTitle>
                        </CardHeader>

                        <CardContent className="space-y-3 text-sm">
                            <div className="flex flex-wrap gap-2">
                                <Badge color={tramite.estado_color}>{tramite.estado_etiqueta}</Badge>
                                <Badge color={tramite.tipo_color}>{tramite.tipo_etiqueta}</Badge>
                            </div>

                            {/*
                                NO VA EL CARTEL DE «qué sigue». Se quitó a
                                pedido: repetía en una frase lo que los botones
                                de arriba ya dicen —si está el botón «Enviar a
                                revisión», eso es lo que sigue— y empujaba los
                                datos del expediente hacia abajo.

                                El enum conserva `queSigue()` por si vuelve a
                                hacer falta en otra pantalla.
                            */}
                            <Dato etiqueta="Beneficiario" valor={tramite.beneficiario} />
                            <Dato etiqueta="Rubro solicitado" valor={tramite.rubro} />

                            {/*
                                El cupo NO se imprime en el carnet —así se pidió—
                                pero sí se muestra en el expediente: es el dato
                                contra el que se contrastan las guías de
                                transporte.

                                `!== null` y no un truthy: un cupo de 0 daría
                                falsy y el renglón mostraría «—» como si no se
                                hubiera declarado nada.
                            */}
                            <Dato
                                etiqueta="Capacidad autorizada"
                                valor={
                                    tramite.capacidad_kg !== null
                                        ? `${tramite.capacidad_kg} Kg`
                                        : null
                                }
                            />
                            <Dato etiqueta="Presentado" valor={fechaHora(tramite.fecha_solicitud)} />
                            <Dato etiqueta="Enviado a revisión" valor={fechaHora(tramite.fecha_revision)} />
                            <Dato etiqueta="Aprobado" valor={fechaHora(tramite.fecha_aprobacion)} />
                            <Dato etiqueta="Impreso" valor={fechaHora(tramite.fecha_generacion)} />
                            <Dato etiqueta="Entregado" valor={fechaHora(tramite.fecha_entrega)} />

                            {tramite.observaciones && (
                                <p className="rounded-md bg-secondary/50 p-3 text-muted-foreground">
                                    {tramite.observaciones}
                                </p>
                            )}

                            {tramite.motivo_rechazo && (
                                <p className="rounded-md bg-rose-50 p-3 text-rose-900 dark:bg-rose-500/10 dark:text-rose-200">
                                    <strong>Motivo del rechazo: </strong>
                                    {tramite.motivo_rechazo}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Respaldos</CardTitle>
                        </CardHeader>

                        {/*
                            SOLO LOS DOS ADJUNTOS DEL EXPEDIENTE.

                            La fotografía del beneficiario NO va acá: es un campo
                            de su ficha, no un respaldo de este trámite. Estuvo
                            un rato y se sacó — mezclaba dos cosas que viven en
                            tablas distintas y que se corrigen en pantallas
                            distintas.

                            Que falte se sigue avisando donde corresponde: la
                            vista previa del carnet, al cargar la solicitud, dice
                            «La ficha no tiene fotografía. El carnet se va a
                            imprimir con el recuadro vacío».
                        */}
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <Adjunto titulo="Fotocopia de CI" url={tramite.ci_file_url} />
                            <Adjunto
                                titulo="Certificado de asociación"
                                url={tramite.cert_asociacion_file_url}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Depósitos</CardTitle>
                        </CardHeader>

                        {/*
                            ========================================================
                             ACÁ LOS DEPÓSITOS TAMBIÉN SE TRABAJAN — Y ANTES NO
                            ========================================================

                            Esta tarjeta era de solo lectura: los depósitos se
                            cargaban y se corregían únicamente desde «Editar
                            trámite», para que quedara claro cuál era «el lugar».

                            Eso dejaba un expediente sin salida, y apareció en
                            ventanilla: OBSERVAR una boleta solo se puede EN
                            REVISIÓN —el control es parte de la revisión— pero
                            «Editar trámite» no abre fuera de PENDIENTE. El
                            revisor marcaba «la boleta dice otra cosa» y del otro
                            lado no había dónde corregirla; el único botón que
                            quedaba era «Validar», que es justamente el que no
                            corresponde.

                            La regla de fondo no cambió, se precisó: al ENVIAR se
                            congelan los PAPELES del expediente —eso es lo que
                            quien aprueba firma— pero NO el dinero. El servidor
                            ya lo decía (`permitePagos()` y `admiteCorreccion()`
                            aceptan en revisión); lo que faltaba era la pantalla.

                            QUITAR también vive acá, y estuvo prohibido un rato
                            con este argumento: el recibo ya salió, hacer
                            desaparecer un depósito lo dejaría cobrando más de lo
                            que el expediente muestra. Se cae solo, porque
                            CORREGIR ya hace eso mismo —el recibo se arma al
                            vuelo, así que bajar un monto de 110 a 30 cambia el
                            papel entregado igual que borrar la fila—. Y dejaba
                            sin salida el caso que apareció en ventanilla: una
                            boleta cargada DOS VECES sobre un expediente ya
                            presentado, que no se puede corregir —no hay dato
                            correcto que poner— y quedaba sumando para siempre.

                            Lo que separa a las dos no es el momento sino el
                            PERMISO: quitar pide `pagos.eliminar`, que es de
                            administración, y el motivo por escrito.

                            DONDE SÍ SE CIERRA TODO ES AL APROBAR. Ahí el
                            formulario desaparece y los botones también: un
                            expediente aprobado está pagado por definición —no se
                            aprueba sin cubrir el monto— así que un depósito más
                            no pertenece a este trámite, y tocarlo cambiaría el
                            recibo de un carnet ya emitido.
                        */}
                        <CardContent className="space-y-4">
                            <ResumenDepositos
                                montoRequerido={tramite.monto_requerido}
                                montoPagado={tramite.monto_pagado}
                                saldoPendiente={tramite.saldo_pendiente}
                                moneda={institucion.moneda}
                                porControlar={tramite.pagos_por_controlar}
                            />

                            {/*
                                LOS DOS JUNTOS, y no se contradice con «quien
                                carga no valida».

                                Esa separación la hacen los PERMISOS —
                                `pagos.registrar` contra `pagos.validar`— y
                                `Pago::puedeValidarlo()`, que compara contra
                                quién cargó la boleta. La pantalla no es el
                                control: repartir los botones en dos pantallas
                                no impedía nada, porque con un solo rol la misma
                                persona abría las dos.
                            */}
                            <ListaDepositos
                                pagos={pagos}
                                moneda={institucion.moneda}
                                vacio="Cargue las boletas acá abajo."
                                conControl
                                conCorreccion={puede('pagos.registrar')}
                            />

                            {tramite.admite_pagos && puede('pagos.registrar') && (
                                <FormularioDeposito
                                    tramiteId={tramite.id}
                                    moneda={institucion.moneda}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ---------------------------------------------- Columna derecha */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Carnet de la gestión {carnet.gestion}</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-3 text-sm">
                        <div className="flex flex-wrap gap-2">
                            <Badge color={carnet.estado_color}>{carnet.estado_etiqueta}</Badge>
                            <Badge color="slate">Gestión {carnet.gestion}</Badge>
                        </div>

                        <Dato etiqueta="Vence" valor={fecha(carnet.fecha_vencimiento)} />

        {/*
                            LA ACTIVIDAD DEL CARNET, que es siempre la misma que la
                            del trámite: el expediente cuelga del carnet de ese
                            rubro. Se muestra igual porque el supervisor está
                            firmando un documento concreto y tiene que ver cuál.

                            Antes acá iba la lista de rubros ya habilitados, para
                            dar contexto —«en qué carnet entra el que estoy por
                            aprobar»—. Esa pregunta desapareció con el pivote.
                        */}
                        <Dato etiqueta="Rubro" valor={carnet.rubro} />

                        {/*
                            EL CUPO DEL CARNET PUEDE DIFERIR DEL DEL TRÁMITE, y por
                            eso se muestra el del carnet y no el del expediente: el
                            trámite PROPONE un cupo y la aprobación lo CONSOLIDA.
                            Mientras está pendiente, el carnet sigue mostrando el
                            que tenía —o nada, si es una emisión inicial—.
                        */}
                        <Dato etiqueta="Cupo autorizado" valor={carnet.capacidad} />

                        <Link
                            href={route('carnets.show', carnet.id)}
                            className="inline-block text-primary hover:underline"
                        >
                            Ver el carnet completo
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/**
 * ============================================================================
 *  UN RESPALDO DEL EXPEDIENTE: CARGADO O NO
 * ============================================================================
 *
 * La versión anterior distinguía las dos situaciones con un borde punteado
 * contra uno sólido, y no alcanzaba: de reojo los tres recuadros se veían
 * iguales, y el operador tenía que leer «: sin archivo» al final del renglón
 * para darse cuenta.
 *
 * Acá el estado se ve por el COLOR y por el ícono antes de leer nada: verde con
 * tilde si está, ámbar con signo si falta. Y si lo cargado es una imagen, se
 * muestra la MINIATURA — que es la única forma de notar de un vistazo que se
 * adjuntó el escaneo equivocado.
 */
function Adjunto({ titulo, url }: { titulo: string; url: string | null }) {
    if (!url) {
        return (
            <div className="rounded-md border border-dashed border-destructive/50 bg-destructive/5 p-3">
                <p className="flex items-center gap-1.5 text-[13px] font-medium">
                    <TriangleAlert className="size-3.5 shrink-0 text-destructive" />
                    {titulo}
                </p>

                {/* Los dos adjuntos son obligatorios para enviar a revisión —ver
                    Tramite::faltantesParaRevision()—, así que el aviso va en
                    rojo y dice qué se traba, no solo que falta. */}
                <p className="mt-0.5 text-xs text-muted-foreground">
                    Falta — no se puede enviar a revisión
                </p>
            </div>
        );
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="flex items-center gap-3 rounded-md border border-emerald-500/50 bg-emerald-50/60 p-3 transition-colors hover:bg-emerald-100/60 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/40"
        >
            {esImagen(url) ? (
                <img src={url} alt="" className="size-10 shrink-0 rounded object-cover" />
            ) : (
                <span className="flex size-10 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                    <FileText className="size-5" />
                </span>
            )}

            <div className="min-w-0">
                <p className="flex items-center gap-1.5 text-[13px] font-medium">
                    <Check className="size-3.5 shrink-0 text-emerald-600" />
                    <span className="truncate">{titulo}</span>
                </p>
                <p className="text-xs text-muted-foreground">Abrir en otra pestaña</p>
            </div>
        </a>
    );
}

/**
 * ¿La dirección apunta a una imagen?
 *
 * Se mira la extensión y no el tipo MIME porque acá solo se tiene la URL: el
 * archivo vive en el disco o en s3 y no se descarga para preguntarle. Se corta
 * en el `?` por si la dirección trae parámetros —las de s3 firmadas los traen—.
 */
function esImagen(url: string): boolean {
    const sinParametros = url.split('?')[0].toLowerCase();

    return /\.(jpg|jpeg|png|webp|gif)$/.test(sinParametros);
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null | undefined }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right font-medium">{valor || '—'}</span>
        </div>
    );
}
