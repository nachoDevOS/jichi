import { Head, useForm, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarClock,
    Check,
    Fish,
    IdCard,
    Info,
    RefreshCw,
    TriangleAlert,
    Truck,
    Users,
    Wallet,
    Waves,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Aviso, BandaFormulario, kg, SeccionFormulario } from '@/components/panel/comunes/ficha';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { OpcionEnum, PageProps, TipoActor } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';
import type { AsociacionElegible, CupoVigente, PreciosCarnet, ReposicionDeCarnet } from '@/types/carnets';

/** Qué habilita cada actividad: es lo que decide `TipoActor` en el servidor. */
const ACTIVIDAD: Record<TipoActor, { icono: LucideIcon; habilita: string }> = {
    pescador: { icono: Fish, habilita: 'Saca permisos de faena con los kilos de su autorización' },
    comercializador: { icono: Truck, habilita: 'Saca guías de transporte; no lleva autorización' },
};

/**
 *  Registrar un carnet (paso 3 del flujo): queda pendiente hasta que se pague en SIREB
 */
export default function CrearCarnet({
    beneficiario,
    reposicion,
    aprovechamientoId,
    asociaciones,
    actividades,
    precios,
    servicioConfigurado,
}: {
    beneficiario: BeneficiarioSugerido | null;
    /** Llega de «Reponer»: los datos del carnet revocado, para no volver a elegirlos. */
    reposicion: ReposicionDeCarnet | null;
    /** Llega de la ficha de la autorización: la que se preselecciona. */
    aprovechamientoId: number | null;
    asociaciones: AsociacionElegible[];
    actividades: OpcionEnum[];
    /** El precio de cada actividad en SIREB; null si no responde. */
    precios: PreciosCarnet | null;
    /** false = falta elegir el servicio en Catálogos › Aranceles. */
    servicioConfigurado: boolean;
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        beneficiario_id: beneficiario?.id ?? null,
        asociacion_id: reposicion ? String(reposicion.asociacion_id) : '',
        tipo_actor: (reposicion?.tipo_actor ?? '') as TipoActor | '',
        // Se manda explícito en vez de dejar que el servidor adivine: pantalla y base dicen lo mismo.
        aprovechamiento_id: (reposicion?.aprovechamiento_id ??
            beneficiario?.cupos_elegibles?.find((c) => c.id === aprovechamientoId)?.id ??
            beneficiario?.cupos_elegibles?.[0]?.id ??
            null) as number | null,
        // Los dos respaldos suben con el formulario: el post va con `forceFormData`.
        archivo_ci: null as File | null,
        archivo_asociacion: null as File | null,
    });

    const actor = form.data.tipo_actor;
    const esPescador = actor === 'pescador';
    const precio = actor !== '' ? (precios?.[actor] ?? null) : null;
    const sinPrecio =
        actor === ''
            ? null
            : !servicioConfigurado
              ? 'Falta elegir el servicio «Carnet» de SIREB en Catálogos › Aranceles.'
              : precios === null
                ? 'Recaudaciones (SIREB) no responde: no se puede saber el precio. Intente en unos minutos.'
                : precio === null
                  ? `Falta definir la tarifa del carnet de ${actor} en Catálogos › Aranceles › Definir tarifas, o SIREB la dio de baja.`
                  : null;

    // Sus autorizaciones en curso: pendiente o aprobada respaldan el carnet; se cobran juntos.
    const cupos: CupoVigente[] = persona?.cupos_elegibles ?? [];
    const cupo = cupos.find((c) => c.id === form.data.aprovechamiento_id) ?? null;
    const faltaCupo = esPescador && persona !== null && cupos.length === 0;
    const asociacion = asociaciones.find((a) => String(a.id) === form.data.asociacion_id) ?? null;
    const conArchivos = form.data.archivo_ci !== null && form.data.archivo_asociacion !== null;

    const listo =
        persona !== null &&
        actor !== '' &&
        precio !== null &&
        (!esPescador || cupo !== null) &&
        asociacion !== null &&
        conArchivos;

    // La sección de la autorización solo existe para el pescador: las demás se renumeran.
    let n = 0;
    const siguiente = () => ++n;

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        form.setData((datos) => ({
            ...datos,
            beneficiario_id: elegida?.id ?? null,
            // La autorización es de la persona: se propone la más reciente.
            aprovechamiento_id: datos.tipo_actor === 'pescador' ? (elegida?.cupos_elegibles?.[0]?.id ?? null) : null,
        }));
        // Un error de la persona anterior («ya tiene carnet vigente») mentiría bajo otra.
        form.clearErrors('beneficiario_id');
    }

    function elegirActividad(elegido: TipoActor) {
        // Al comercializador se le limpia la autorización: el servidor la rechaza.
        form.setData((datos) => ({
            ...datos,
            tipo_actor: elegido,
            aprovechamiento_id:
                elegido === 'comercializador'
                    ? null
                    : (datos.aprovechamiento_id ?? persona?.cupos_elegibles?.[0]?.id ?? null),
        }));
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        // La autorización solo viaja si el carnet es de pescador: es lo que exige el servidor.
        form.transform((datos) => ({ ...datos, aprovechamiento_id: esPescador ? datos.aprovechamiento_id : null }));
        // Sin `forceFormData` Inertia manda JSON y los archivos se pierden sin ningún error.
        form.post(route('carnets.store'), { forceFormData: true });
    }

    return (
        <LayoutPanel titulo="Registrar carnet">
            <Head title="Registrar carnet" />

            <BandaFormulario
                icono={IdCard}
                titulo={
                    reposicion
                        ? `Reposición del carnet N° ${reposicion.registro ?? '—'}`
                        : 'Carnet de pescador o comercializador'
                }
                bajada="La credencial de la gestión. Queda pendiente hasta que se pague; se imprime aprobada."
                pasos={[
                    { texto: 'Titular', hecho: persona !== null },
                    { texto: 'Actividad', hecho: actor !== '' },
                    ...(esPescador ? [{ texto: 'Autorización', hecho: cupo !== null }] : []),
                    { texto: 'Asociación', hecho: asociacion !== null },
                    { texto: 'Respaldos', hecho: conArchivos },
                ]}
            />

            {reposicion && (
                <div className="mt-4">
                    <Aviso
                        tono="aviso"
                        icono={RefreshCw}
                        titulo={`Reposición del carnet ${reposicion.codigo}, ya revocado`}
                    >
                        Actividad, asociación y autorización vienen del anterior; falta adjuntar los respaldos y
                        registrar.
                    </Aviso>
                </div>
            )}

            <form onSubmit={enviar} className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <SeccionFormulario
                        obligatorio
                        numero={siguiente()}
                        titulo="¿Para quién es?"
                        ayuda="Tiene que estar en el padrón. Si es la primera vez que viene, regístrelo antes."
                        hecho={persona !== null}
                    >
                        <BuscadorBeneficiario seleccionado={persona} onSeleccionar={elegirPersona} />
                        {form.errors.beneficiario_id && (
                            <p className="text-sm text-destructive">{form.errors.beneficiario_id}</p>
                        )}
                    </SeccionFormulario>

                    <SeccionFormulario
                        obligatorio
                        numero={siguiente()}
                        titulo="Actividad"
                        ayuda="Decide qué habilita el carnet y cuánto se cobra."
                        hecho={actor !== ''}
                    >
                        {actividades.length === 0 ? (
                            <Aviso tono="peligro" icono={TriangleAlert}>
                                Ninguna actividad tiene tarifa definida. Defínalas en Catálogos › Aranceles › Carnet ›
                                Definir tarifas.
                            </Aviso>
                        ) : (
                            <div role="radiogroup" aria-label="Actividad" className="grid gap-2 sm:grid-cols-2">
                                {actividades.map((a) => {
                                    const clave = a.value as TipoActor;
                                    const Icono = ACTIVIDAD[clave]?.icono ?? IdCard;
                                    const elegida = actor === clave;
                                    const suPrecio = precios?.[clave] ?? null;

                                    return (
                                        <button
                                            key={a.value}
                                            type="button"
                                            role="radio"
                                            aria-checked={elegida}
                                            onClick={() => elegirActividad(clave)}
                                            className={cn(
                                                'relative flex items-start gap-3 rounded-lg border px-3 py-2.5 text-left transition-all',
                                                elegida
                                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                                    : 'border-border hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-9 shrink-0 items-center justify-center rounded-full',
                                                    elegida
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-secondary text-primary',
                                                )}
                                            >
                                                <Icono className="size-4.5" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="flex items-baseline justify-between gap-2">
                                                    <span className="font-semibold">
                                                        {/* «Carnet de» sobra: la sección ya dice que es un carnet. */}
                                                        {a.label
                                                            .replace(/^carnet de /i, '')
                                                            .replace(/^./, (l) => l.toUpperCase())}
                                                    </span>
                                                    {suPrecio && (
                                                        <span className="text-xs font-medium whitespace-nowrap text-primary tabular-nums">
                                                            {bs(suPrecio.monto, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {ACTIVIDAD[clave]?.habilita}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                        {sinPrecio && (
                            <Aviso tono="peligro" icono={TriangleAlert}>
                                {sinPrecio}
                            </Aviso>
                        )}
                        {form.errors.tipo_actor && <p className="text-sm text-destructive">{form.errors.tipo_actor}</p>}
                    </SeccionFormulario>

                    {esPescador && (
                        <SeccionFormulario
                            obligatorio
                            numero={siguiente()}
                            titulo="Autorización que lo respalda"
                            ayuda={
                                cupos.length > 1
                                    ? 'Tiene más de una en curso: elija cuál respalda este carnet.'
                                    : 'Su volumen se imprime en el carnet.'
                            }
                            hecho={cupo !== null}
                        >
                            {faltaCupo ? (
                                <Aviso tono="aviso" icono={TriangleAlert} titulo="Sin autorización en curso">
                                    El carnet de pescador imprime el volumen autorizado: registre antes su Autorización
                                    de Pesca para Aprovechamiento Pesquero.
                                </Aviso>
                            ) : persona === null ? (
                                <p className="text-sm text-muted-foreground">Elija primero al titular.</p>
                            ) : (
                                <div role="radiogroup" aria-label="Autorización" className="grid gap-2 sm:grid-cols-2">
                                    {cupos.map((c) => {
                                        const elegida = form.data.aprovechamiento_id === c.id;

                                        return (
                                            <button
                                                key={c.id}
                                                type="button"
                                                role="radio"
                                                aria-checked={elegida}
                                                onClick={() => form.setData('aprovechamiento_id', c.id)}
                                                className={cn(
                                                    'relative rounded-lg border px-3 py-2.5 text-left transition-all',
                                                    elegida
                                                        ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                                        : 'border-border hover:border-primary/50 hover:shadow-md',
                                                )}
                                            >
                                                {elegida && (
                                                    <Check className="absolute top-2 right-2 size-3.5 rounded-full bg-primary p-0.5 text-primary-foreground" />
                                                )}
                                                <span className="flex items-center gap-2 pr-5">
                                                    <span className="text-lg font-semibold tabular-nums">
                                                        {kg(c.volumen_total_kg)}
                                                    </span>
                                                    <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    Vence {fecha(c.fecha_vencimiento)}
                                                </span>
                                                {!c.habilita_faenas && (
                                                    <span className="mt-1 block text-[11px] text-amber-700 dark:text-amber-400">
                                                        Todavía no autoriza a pescar: las faenas salen cuando se
                                                        apruebe.
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            {form.errors.aprovechamiento_id && (
                                <p className="text-sm text-destructive">{form.errors.aprovechamiento_id}</p>
                            )}
                        </SeccionFormulario>
                    )}

                    {/* El error de la autorización aunque su sección no se dibuje: si no, el botón parece no andar. */}
                    {form.errors.aprovechamiento_id && !esPescador && (
                        <Aviso tono="peligro" icono={TriangleAlert}>
                            {form.errors.aprovechamiento_id}
                        </Aviso>
                    )}

                    <SeccionFormulario
                        obligatorio
                        numero={siguiente()}
                        titulo="Asociación"
                        ayuda="La que certifica al titular. Se imprime en el carnet."
                        hecho={asociacion !== null}
                    >
                        <Select
                            id="asociacion_id"
                            value={form.data.asociacion_id}
                            onChange={(e) => form.setData('asociacion_id', e.target.value)}
                            aria-invalid={Boolean(form.errors.asociacion_id)}
                            aria-label="Asociación"
                        >
                            <option value="">Elija una asociación…</option>
                            {asociaciones.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.nombre}
                                </option>
                            ))}
                        </Select>
                        {form.errors.asociacion_id && (
                            <p className="text-sm text-destructive">{form.errors.asociacion_id}</p>
                        )}
                    </SeccionFormulario>

                    <SeccionFormulario
                        obligatorio
                        numero={siguiente()}
                        titulo="Respaldos"
                        ayuda="Los papeles que trae al mostrador quedan adjuntos al carnet. Foto o PDF, hasta 3 MB."
                        hecho={conArchivos}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Cédula del titular"
                                htmlFor="archivo_ci"
                                error={form.errors.archivo_ci}
                                obligatorio
                            >
                                <SelectorArchivo
                                    id="archivo_ci"
                                    archivo={form.data.archivo_ci}
                                    onElegir={(a) => form.setData('archivo_ci', a)}
                                    error={form.errors.archivo_ci}
                                />
                            </Campo>
                            <Campo
                                etiqueta="Documento de la asociación"
                                htmlFor="archivo_asociacion"
                                error={form.errors.archivo_asociacion}
                                obligatorio
                            >
                                <SelectorArchivo
                                    id="archivo_asociacion"
                                    archivo={form.data.archivo_asociacion}
                                    onElegir={(a) => form.setData('archivo_asociacion', a)}
                                    error={form.errors.archivo_asociacion}
                                />
                            </Campo>
                        </div>
                    </SeccionFormulario>
                </div>

                {/* El resumen acompaña al bajar: es lo que se confirma antes de registrar. */}
                <Card className="h-fit overflow-hidden lg:sticky lg:top-40">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 pt-5 text-sm">
                        <div className="rounded-lg bg-secondary/60 px-4 py-3">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Habilita
                            </p>
                            <p className={cn('font-semibold', actor ? 'text-primary' : 'text-muted-foreground')}>
                                {actor === ''
                                    ? 'Elija la actividad'
                                    : esPescador
                                      ? 'Permisos de faena'
                                      : 'Guías de transporte'}
                            </p>
                            {esPescador && (
                                <p className="text-xs text-muted-foreground">
                                    {cupo
                                        ? `${kg(cupo.volumen_total_kg)} autorizados`
                                        : 'Necesita una autorización en curso'}
                                </p>
                            )}
                        </div>

                        <Renglon icono={IdCard} etiqueta="Titular" valor={persona?.nombreCompleto ?? null} />
                        <Renglon
                            icono={actor === 'comercializador' ? Truck : Fish}
                            etiqueta="Actividad"
                            valor={actividades.find((a) => a.value === actor)?.label ?? null}
                        />
                        {esPescador && (
                            <Renglon
                                icono={Waves}
                                etiqueta="Autorización"
                                valor={cupo ? kg(cupo.volumen_total_kg) : null}
                            />
                        )}
                        <Renglon
                            icono={Users}
                            etiqueta="Asociación"
                            valor={asociacion?.sigla ?? asociacion?.nombre ?? null}
                        />
                        <Renglon icono={CalendarClock} etiqueta="Solicitud" valor={fecha(new Date())} />

                        <div className="flex items-baseline justify-between border-t border-border pt-4">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <Wallet className="size-4" />A cobrar
                            </span>
                            {/* De referencia: el que vale lo pide el servidor a SIREB al guardar. */}
                            <span className="text-xl font-semibold tabular-nums">
                                {precio ? bs(precio.monto, institucion.moneda) : '—'}
                            </span>
                        </div>

                        <p className="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            Queda pendiente: se paga en Recaudaciones y, cuando lo validan, queda aprobado y se puede
                            imprimir.
                        </p>

                        <Button type="submit" disabled={form.processing || !listo} className="w-full">
                            Registrar carnet
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

function Renglon({ icono: Icono, etiqueta, valor }: { icono: LucideIcon; etiqueta: string; valor: string | null }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2 text-muted-foreground">
                <Icono className="size-4" />
                {etiqueta}
            </span>
            <span className={cn('truncate text-right', valor ? 'font-medium' : 'text-muted-foreground')}>
                {valor ?? '—'}
            </span>
        </div>
    );
}
