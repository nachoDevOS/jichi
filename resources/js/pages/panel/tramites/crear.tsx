import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Tags, UserSearch } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/tramites/buscador-beneficiario';
import { CampoPagos } from '@/components/panel/tramites/campo-pagos';
import { SituacionBeneficiarioCard } from '@/components/panel/tramites/situacion-beneficiario';
import { VistaPreviaCarnet } from '@/components/panel/tramites/vista-previa-carnet';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { Textarea } from '@/components/ui/textarea';
import { useArchivos } from '@/hooks/use-archivos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';
import type { FormularioSolicitud, RubroOpcion } from '@/types/tramites';

/**
 * ============================================================================
 *  UN SOLO FORMULARIO PARA LOS DOS TIPOS DE TRÁMITE, EN TRES PASOS
 * ============================================================================
 *
 *     1 · BENEFICIARIO  ──▶  2 · RUBRO  ──▶  3 · REQUISITOS
 *     ¿quién es y qué        ¿qué actividad    los papeles, los
 *      tiene hoy?             se le habilita?   depósitos y el carnet
 *                                               que va a recibir
 *
 * No hay una pantalla de «emisión inicial» y otra de «adición de rubro». El
 * operador carga siempre lo mismo y el sistema decide el tipo al guardar,
 * mirando si esa persona ya tiene carnet de la gestión en curso.
 *
 * ¿Por qué no dejarlo a elección de ventanilla? Porque sería pedirle al operador
 * que adivine algo que la base ya sabe, y equivocarse ahí no es un detalle: un
 * «emisión inicial» marcado de más intenta crear un segundo carnet para la misma
 * gestión, que el índice único rechaza y voltea el trámite entero.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ EN PASOS Y NO TODO JUNTO
 * ----------------------------------------------------------------------------
 *
 * Porque el orden no es cosmético, es una DEPENDENCIA REAL: hasta no saber quién
 * es la persona no se sabe qué rubros se le pueden ofrecer —los que su carnet ya
 * tiene quedan bloqueados—, y hasta no saber el rubro no se sabe cuánto hay que
 * cobrar. Todo junto en una pantalla, el operador podía adjuntar los papeles
 * primero y descubrir al final que el rubro no correspondía; con los archivos ya
 * elegidos, que al volver con el error NO se recuperan porque los navegadores no
 * permiten rellenar un campo de tipo file.
 *
 * Los pasos hacen que ese descubrimiento ocurra antes de escanear nada.
 *
 * ----------------------------------------------------------------------------
 *  EL FORMULARIO ES UNO SOLO, AUNQUE SE VEA EN TRES
 * ----------------------------------------------------------------------------
 *
 * `useForm` guarda los datos de los tres pasos a la vez y se manda una sola
 * petición al final. Los pasos son una forma de MOSTRAR el formulario, no tres
 * formularios encadenados: volver atrás no pierde nada de lo ya cargado, y no
 * hace falta guardar un borrador en el servidor.
 */
export default function CrearTramite({
    gestion,
    beneficiarioPreseleccionado,
    rubros,
}: {
    gestion: number;
    /** Viene relleno cuando se entra desde la ficha de un beneficiario. */
    beneficiarioPreseleccionado: BeneficiarioSugerido | null;
    rubros: RubroOpcion[];
}) {
    const { institucion } = usePage<PageProps>().props;
    // `acepta` ya no se usa acá: lo resuelve SelectorArchivo por su cuenta.
    const { ayudaPeso } = useArchivos();

    const [beneficiario, setBeneficiario] = useState<BeneficiarioSugerido | null>(
        beneficiarioPreseleccionado,
    );

    // Si se entró desde la ficha de alguien, el paso 1 ya está resuelto: se
    // arranca en el 2 para no hacerle confirmar algo que ya eligió.
    const [paso, setPaso] = useState(beneficiarioPreseleccionado ? 2 : 1);

    const form = useForm<FormularioSolicitud>({
        beneficiario_id: beneficiarioPreseleccionado ? String(beneficiarioPreseleccionado.id) : '',
        rubro_id: '',
        ciFile: null,
        certAsociacionFile: null,
        asociacion: '',
        capacidad_kg: '',
        observaciones: '',
        pagos: [],
    });

    const rubroElegido = rubros.find((r) => String(r.id) === form.data.rubro_id) ?? null;

    /*
     * Los rubros que este carnet YA tiene. Vacío mientras no haya beneficiario
     * elegido, que es lo correcto: sin saber de quién se trata no hay nada que
     * bloquear.
     */
    const ocupados = beneficiario?.situacion.rubros_ocupados ?? [];
    const disponibles = rubros.filter((r) => !ocupados.includes(r.id));

    function elegirBeneficiario(elegido: BeneficiarioSugerido | null) {
        setBeneficiario(elegido);
        form.setData('beneficiario_id', elegido ? String(elegido.id) : '');

        /*
         * Se limpia el rubro si el que estaba elegido lo tiene la persona nueva.
         *
         * Sin esto queda seleccionado un rubro deshabilitado: el <select> lo
         * sigue mostrando —`disabled` en una opción no la deselecciona— y el
         * operador manda un trámite que el servidor rechaza sin entender por qué,
         * porque en pantalla se veía elegido.
         */
        const ocupadosNuevos = elegido?.situacion.rubros_ocupados ?? [];

        if (form.data.rubro_id && ocupadosNuevos.includes(Number(form.data.rubro_id))) {
            form.setData('rubro_id', '');
        }
    }

    /*
     * Qué falta para poder avanzar.
     *
     * Es una comprobación de NAVEGACIÓN, no de validación: sirve para no dejar
     * pasar al paso siguiente sin lo que ese paso necesita. Las reglas de verdad
     * las aplica RegistrarSolicitudRequest en el servidor, y ninguna de las dos
     * reemplaza a la otra.
     */
    const puedeAvanzar = paso === 1 ? beneficiario !== null : form.data.rubro_id !== '';

    function enviar(e: FormEvent) {
        e.preventDefault();

        // forceFormData porque van archivos: sin eso Inertia manda JSON y los
        // adjuntos no viajan.
        form.post(route('tramites.store'), {
            forceFormData: true,
            /*
             * Si el servidor rechaza algo, se vuelve al paso donde está el campo
             * que falló. Sin esto el operador se queda en el paso 3 mirando un
             * formulario sin errores visibles, porque el mensaje quedó en una
             * pantalla que ya no está viendo.
             */
            onError: (errores) => {
                if (errores.beneficiario_id) {
                    setPaso(1);
                } else if (errores.rubro_id) {
                    setPaso(2);
                }
            },
        });
    }

    return (
        <LayoutPanel
            titulo="Nueva solicitud"
            descripcion={`Gestión ${gestion}. El sistema decide si corresponde emitir el carnet o sumar un rubro al que ya tiene.`}
        >
            <Head title="Nueva solicitud" />

            {/*
                EL ANCHO: `max-w-7xl` y no 5xl, que es lo que tenía.

                Este es el formulario más cargado del sistema —el paso 3 pone
                los datos del trámite, los dos adjuntos y la lista de depósitos
                de un lado y la vista previa del carnet del otro, en una grilla
                de cinco columnas—, y estaba MÁS ANGOSTO que el de beneficiario,
                que tiene la mitad de contenido. En una pantalla de 1600 px
                sobraban 320 px a los costados y las dos columnas del paso 3
                quedaban apretadas.

                No se quita del todo el límite: en un monitor muy ancho, un
                formulario sin tope estira los renglones hasta que leerlos
                obliga a barrer la cabeza de lado a lado.
            */}
            <form onSubmit={enviar} className="mx-auto max-w-7xl space-y-6">
                <Pasos actual={paso} />

                {/* =========================================== 1 · Beneficiario */}
                {paso === 1 && (
                    <Card>
                        <CardContent className="space-y-4 pt-5">
                            <BuscadorBeneficiario
                                seleccionado={beneficiario}
                                onSeleccionar={elegirBeneficiario}
                                error={form.errors.beneficiario_id}
                            />

                            {form.errors.beneficiario_id && (
                                <p className="text-sm text-destructive" role="alert">
                                    {form.errors.beneficiario_id}
                                </p>
                            )}

                            {/*
                                Apenas hay beneficiario elegido, se dice qué tiene:
                                el carnet de la gestión si existe, sus rubros y si
                                admite adiciones. Es lo que evita cargar un
                                expediente entero para que lo rechacen al final.
                            */}
                            {beneficiario && (
                                <SituacionBeneficiarioCard situacion={beneficiario.situacion} />
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* ================================================= 2 · Rubro */}
                {paso === 2 && beneficiario && (
                    <Card>
                        <CardContent className="space-y-4 pt-5">
                            {/*
                                SIN RUBROS DISPONIBLES no se muestra un selector
                                vacío. Un desplegable con una sola opción que dice
                                «Seleccione…» parece un error del sistema; esto
                                explica qué pasa y qué se puede hacer en su lugar.
                            */}
                            {disponibles.length === 0 ? (
                                <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                    <p className="font-medium">No queda ningún rubro por habilitar</p>
                                    <p className="text-amber-800/80 dark:text-amber-200/80">
                                        Este carnet ya tiene todos los rubros del catálogo. Si alguno
                                        está suspendido, lo que corresponde es levantarle la
                                        suspensión desde la ficha del carnet, no cobrar otro trámite.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <Campo
                                        etiqueta="Actividad a habilitar"
                                        htmlFor="rubro_id"
                                        obligatorio
                                        error={form.errors.rubro_id}
                                        ayuda={
                                            ocupados.length > 0
                                                ? 'Los rubros que el carnet ya tiene aparecen en gris y no se pueden elegir.'
                                                : undefined
                                        }
                                    >
                                        <Select
                                            id="rubro_id"
                                            value={form.data.rubro_id}
                                            onChange={(e) => form.setData('rubro_id', e.target.value)}
                                            aria-invalid={Boolean(form.errors.rubro_id)}
                                        >
                                            <option value="">Seleccione un rubro…</option>

                                            {/*
                                                Se recorren TODOS los rubros y se
                                                deshabilitan los ocupados, en vez de
                                                sacarlos de la lista. Una opción que
                                                desaparece deja al operador
                                                buscándola —«¿y Pescador dónde
                                                está?»—; una en gris que dice «ya lo
                                                tiene» responde la pregunta sin que
                                                la haga.
                                            */}
                                            {rubros.map((r) => {
                                                const ocupado = ocupados.includes(r.id);

                                                return (
                                                    <option key={r.id} value={r.id} disabled={ocupado}>
                                                        {r.nombre}
                                                        {ocupado
                                                            ? ' — ya lo tiene este carnet'
                                                            : ` — ${bs(r.costo, institucion.moneda)}`}
                                                    </option>
                                                );
                                            })}
                                        </Select>
                                    </Campo>

                                    {rubroElegido && (
                                        <div className="rounded-md bg-secondary/50 p-4 text-sm">
                                            <p className="font-medium">{rubroElegido.nombre}</p>
                                            {rubroElegido.descripcion && (
                                                <p className="text-muted-foreground">
                                                    {rubroElegido.descripcion}
                                                </p>
                                            )}
                                            <p className="mt-2">
                                                Costo del trámite:{' '}
                                                <strong className="tabular-nums">
                                                    {bs(rubroElegido.costo, institucion.moneda)}
                                                </strong>
                                            </p>
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* ============================================ 3 · Requisitos */}
                {paso === 3 && beneficiario && (
                    <div className="grid gap-6 lg:grid-cols-5">
                        <div className="space-y-6 lg:col-span-3">
                            <Card>
                                <CardContent className="grid gap-4 pt-5 sm:grid-cols-2">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground sm:col-span-2">
                                        Respaldos
                                    </p>

                                    {/*
                                        SelectorArchivo y no <Input type="file">:
                                        el control del navegador no deja ver de un
                                        vistazo cuál adjunto quedó cargado y cuál
                                        no, y el operador carga tres seguidos. Con
                                        esto, apenas se elige, el recuadro pasa a
                                        verde con la tilde, el nombre y el peso — y
                                        la miniatura si es una imagen.
                                    */}
                                    <Campo
                                        etiqueta="Fotocopia de carnet de identidad"
                                        htmlFor="ciFile"
                                        obligatorio
                                        ayuda={`PDF o imagen, ${ayudaPeso}`}
                                    >
                                        <SelectorArchivo
                                            id="ciFile"
                                            archivo={form.data.ciFile}
                                            onElegir={(a) => form.setData('ciFile', a)}
                                            error={form.errors.ciFile}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Certificado de la asociación"
                                        htmlFor="certAsociacionFile"
                                        obligatorio
                                        ayuda={`PDF o imagen, ${ayudaPeso}`}
                                    >
                                        <SelectorArchivo
                                            id="certAsociacionFile"
                                            archivo={form.data.certAsociacionFile}
                                            onElegir={(a) => form.setData('certAsociacionFile', a)}
                                            error={form.errors.certAsociacionFile}
                                        />
                                    </Campo>

                                    {/*
                                        LA ASOCIACIÓN VA JUNTO AL CERTIFICADO, no
                                        suelta en otra sección: es lo que ese papel
                                        respalda. Sin el nombre escrito, el archivo
                                        adjunto es un PDF que nadie puede buscar ni
                                        cruzar con nada.
                                    */}
                                    <Campo
                                        etiqueta="Asociación a la que pertenece"
                                        htmlFor="asociacion"
                                        obligatorio
                                        error={form.errors.asociacion}
                                        ayuda="Tal como figura en el certificado. Se imprime en el carnet."
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            id="asociacion"
                                            maxLength={150}
                                            value={form.data.asociacion}
                                            onChange={(e) =>
                                                form.setData('asociacion', e.target.value)
                                            }
                                            aria-invalid={Boolean(form.errors.asociacion)}
                                        />
                                    </Campo>

                                    {/*
                                        EL CUPO NO SE IMPRIME EN EL CARNET.
                                        El carnet de papel lo traía —«600 KG» bajo
                                        el domicilio— pero el nuevo no, a pedido de
                                        la unidad. Se pide igual porque es con lo
                                        que después se contrasta una guía de
                                        transporte.

                                        Por eso la ayuda lo dice explícito: sin esa
                                        línea, quien carga el formulario espera
                                        verlo aparecer en la vista previa de al
                                        lado y va a creer que algo falla.

                                        OBLIGATORIO: sin cupo, la habilitación no
                                        dice cuánto autoriza y el control no tiene
                                        contra qué comparar.
                                    */}
                                    <Campo
                                        etiqueta="Capacidad autorizada (Kg)"
                                        htmlFor="capacidad_kg"
                                        obligatorio
                                        error={form.errors.capacidad_kg}
                                        ayuda="Uso interno: no se imprime en el carnet."
                                    >
                                        <Input
                                            id="capacidad_kg"
                                            type="number"
                                            inputMode="decimal"
                                            min="0"
                                            step="0.01"
                                            placeholder="600"
                                            value={form.data.capacidad_kg}
                                            onChange={(e) =>
                                                form.setData('capacidad_kg', e.target.value)
                                            }
                                            aria-invalid={Boolean(form.errors.capacidad_kg)}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Observaciones"
                                        htmlFor="observaciones"
                                        error={form.errors.observaciones}
                                        className="sm:col-span-2"
                                    >
                                        <Textarea
                                            id="observaciones"
                                            rows={2}
                                            value={form.data.observaciones}
                                            onChange={(e) =>
                                                form.setData('observaciones', e.target.value)
                                            }
                                        />
                                    </Campo>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="space-y-4 pt-5">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Depósitos (opcional)
                                    </p>

                                    <CampoPagos
                                        pagos={form.data.pagos}
                                        onCambiar={(pagos) => form.setData('pagos', pagos)}
                                        costoRubro={rubroElegido?.costo ?? 0}
                                        moneda={institucion.moneda}
                                        errores={form.errors as unknown as Record<string, string>}
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        {/* ------------------------------- El carnet que va a salir */}
                        <div className="lg:col-span-2">
                            <Card className="lg:sticky lg:top-20">
                                <CardContent className="space-y-3 pt-5">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        Así va a salir el carnet
                                    </p>

                                    <VistaPreviaCarnet
                                        situacion={beneficiario.situacion}
                                        beneficiario={beneficiario}
                                        asociacion={form.data.asociacion}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}

                {/* =============================================== Navegación */}
                <div className="sticky bottom-0 -mx-4 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <p className="mr-auto text-xs text-muted-foreground">
                            Paso {paso} de 3
                        </p>

                        {paso > 1 && (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={form.processing}
                                onClick={() => setPaso((p) => p - 1)}
                            >
                                <ArrowLeft className="size-4" />
                                Atrás
                            </Button>
                        )}

                        {/*
                            OJO CON EL `key` DE ESTOS DOS BOTONES: NO SE PUEDE QUITAR.

                            Los dos ocupan la misma posición en el árbol, así que
                            sin `key` React los reconcilia como EL MISMO <button> y
                            se limita a cambiarle el atributo `type` de "button" a
                            "submit". Eso ocurre mientras el clic todavía se está
                            procesando: para cuando el navegador ejecuta la acción
                            por defecto, el botón ya es de tipo submit y el
                            formulario se envía solo.

                            El síntoma es desconcertante: al pasar del paso 2 al 3
                            la solicitud se registraba sin que nadie apretara
                            «Registrar», con los adjuntos vacíos.

                            Con `key` distinto React desmonta uno y monta el otro, y
                            el nodo que recibió el clic deja de existir antes de que
                            haya ninguna acción por defecto que ejecutar.
                        */}
                        {paso < 3 ? (
                            <Button
                                key="continuar"
                                type="button"
                                disabled={!puedeAvanzar}
                                onClick={() => setPaso((p) => p + 1)}
                            >
                                Continuar
                                <ArrowRight className="size-4" />
                            </Button>
                        ) : (
                            /*
                                El botón de registrar SOLO existe en el paso 3.
                                Con un submit visible desde el paso 1, un Enter en
                                el buscador mandaría el formulario a medio cargar.
                            */
                            <Button key="registrar" type="submit" disabled={form.processing}>
                                {form.processing ? 'Registrando…' : 'Registrar solicitud'}
                            </Button>
                        )}
                    </div>
                </div>
            </form>
        </LayoutPanel>
    );
}

/**
 * La barra de pasos.
 *
 * No es decoración: dice cuántos faltan y en cuál se está, que es lo primero que
 * se pregunta quien abre un formulario partido. Los pasos ya recorridos llevan
 * un tilde en vez del número, para que se distingan de un vistazo de los que
 * faltan.
 *
 * NO SE PUEDE SALTAR haciendo clic en un paso adelantado, y es a propósito: el
 * paso 2 necesita saber quién es la persona para saber qué rubros ofrecer, y el
 * 3 necesita el rubro para saber cuánto cobrar. Volver ATRÁS sí se puede, con el
 * botón de la barra de abajo.
 */
function Pasos({ actual }: { actual: number }) {
    const pasos = [
        { numero: 1, titulo: 'Beneficiario', icono: UserSearch },
        { numero: 2, titulo: 'Rubro', icono: Tags },
        { numero: 3, titulo: 'Requisitos', icono: Check },
    ];

    return (
        <ol className="flex items-center gap-2">
            {pasos.map((paso, i) => (
                <li key={paso.numero} className="flex flex-1 items-center gap-2">
                    <Paso
                        numero={paso.numero}
                        titulo={paso.titulo}
                        icono={paso.icono}
                        estado={
                            actual > paso.numero
                                ? 'hecho'
                                : actual === paso.numero
                                  ? 'actual'
                                  : 'pendiente'
                        }
                    />

                    {/* La línea que une un paso con el siguiente. No va después
                        del último: quedaría una raya apuntando a la nada. */}
                    {i < pasos.length - 1 && (
                        <span
                            aria-hidden
                            className={cn(
                                'h-px flex-1',
                                actual > paso.numero ? 'bg-primary' : 'bg-border',
                            )}
                        />
                    )}
                </li>
            ))}
        </ol>
    );
}

function Paso({
    numero,
    titulo,
    icono: Icono,
    estado,
}: {
    numero: number;
    titulo: string;
    icono: typeof Check;
    estado: 'hecho' | 'actual' | 'pendiente';
}) {
    return (
        <div className="flex shrink-0 items-center gap-2">
            <span
                className={cn(
                    'flex size-8 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                    estado === 'hecho' && 'bg-primary text-primary-foreground',
                    estado === 'actual' && 'bg-primary text-primary-foreground ring-4 ring-primary/20',
                    estado === 'pendiente' && 'bg-muted text-muted-foreground',
                )}
                // aria-current le dice al lector de pantalla en qué paso está,
                // que es la información que el color da a quien ve.
                aria-current={estado === 'actual' ? 'step' : undefined}
            >
                {estado === 'hecho' ? <Check className="size-4" /> : numero}
            </span>

            <span
                className={cn(
                    'hidden text-sm sm:inline',
                    estado === 'pendiente' ? 'text-muted-foreground' : 'font-medium',
                )}
            >
                <Icono className="mr-1 inline size-3.5 align-[-2px]" aria-hidden />
                {titulo}
            </span>
        </div>
    );
}
