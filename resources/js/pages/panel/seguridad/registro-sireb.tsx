import { Head, router } from "@inertiajs/react";
import {
    Check,
    ChevronRight,
    CircleAlert,
    CircleCheck,
    Copy,
    Download,
    Info,
    ScrollText,
    Search,
    WifiOff,
} from "lucide-react";
import { Fragment, useState, type FormEvent, type ReactNode } from "react";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { EstadoVacio } from "@/components/ui/estado-vacio";
import { Input } from "@/components/ui/input";
import { Paginacion } from "@/components/ui/paginacion";
import { Select } from "@/components/ui/select";
import { usePermisos } from "@/hooks/use-permisos";
import LayoutPanel from "@/layouts/layout-panel";
import { cn, fecha } from "@/lib/utils";
import type { OpcionEnum, Paginado } from "@/types";

type Resultado = "ok" | "rechazado" | "sin_respuesta" | "aviso";

interface Entrada {
    id: number;
    hora: string;
    resultado: Resultado;
    resultado_etiqueta: string;
    resultado_color: string;
    metodo: string | null;
    ruta: string | null;
    url: string | null;
    codigo: number | null;
    ms: number | null;
    mensaje: string;
    quien: string | null;
    enviado: unknown;
    respuesta: unknown;
    error: string | null;
    contexto: Record<string, unknown> | null;
}

/**
 *  Registro SIREB — cada pedido a SIREB y lo que contestó, leído de
 *  storage/logs/sireb-AAAA-MM-DD.log. Solo lectura.
 */
export default function RegistroSireb({
    entradas,
    dias,
    resumen,
    filtros,
    opcionesPorPagina,
    resultados,
}: {
    entradas: Paginado<Entrada>;
    dias: { dia: string; bytes: number }[];
    resumen: Record<Resultado, number>;
    filtros: {
        dia: string | null;
        resultado: Resultado | null;
        buscar: string | null;
        por_pagina: number;
    };
    opcionesPorPagina: number[];
    resultados: OpcionEnum[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? "");
    const [abierta, setAbierta] = useState<number | null>(null);

    function filtrar(valores: Record<string, string | number | null> = {}) {
        setAbierta(null);
        router.get(
            route("registro-sireb.index"),
            {
                dia: filtros.dia,
                resultado: filtros.resultado,
                buscar,
                ...valores,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <LayoutPanel
            titulo="Registro SIREB"
            descripcion="Lo que Jichi le pidió a Recaudaciones y lo que respondió, día por día. Se guarda 90 días."
            acciones={
                filtros.dia &&
                puede("sireb.exportar") && (
                    <a
                        href={route("registro-sireb.descargar", filtros.dia)}
                        className={cn(buttonVariants({ variant: "outline" }))}
                    >
                        <Download className="size-4" />
                        Descargar el día
                    </a>
                )
            }
        >
            <Head title="Registro SIREB" />

            <div className="space-y-6">
                {/* El día de un vistazo: si SIREB anduvo mal, salta acá. */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Contador
                        titulo="Bien"
                        valor={resumen.ok}
                        icono={<CircleCheck className="size-5" />}
                        tono="emerald"
                        activo={filtros.resultado === "ok"}
                        onClick={() =>
                            filtrar({
                                resultado:
                                    filtros.resultado === "ok" ? null : "ok",
                            })
                        }
                    />
                    <Contador
                        titulo="Rechazados"
                        valor={resumen.rechazado}
                        icono={<CircleAlert className="size-5" />}
                        tono="amber"
                        activo={filtros.resultado === "rechazado"}
                        onClick={() =>
                            filtrar({
                                resultado:
                                    filtros.resultado === "rechazado"
                                        ? null
                                        : "rechazado",
                            })
                        }
                    />
                    <Contador
                        titulo="Sin respuesta"
                        valor={resumen.sin_respuesta}
                        icono={<WifiOff className="size-5" />}
                        tono="rose"
                        activo={filtros.resultado === "sin_respuesta"}
                        onClick={() =>
                            filtrar({
                                resultado:
                                    filtros.resultado === "sin_respuesta"
                                        ? null
                                        : "sin_respuesta",
                            })
                        }
                    />
                    <Contador
                        titulo="Avisos"
                        valor={resumen.aviso}
                        icono={<Info className="size-5" />}
                        tono="slate"
                        activo={filtros.resultado === "aviso"}
                        onClick={() =>
                            filtrar({
                                resultado:
                                    filtros.resultado === "aviso"
                                        ? null
                                        : "aviso",
                            })
                        }
                    />
                </div>

                <Card className="min-w-0">
                    <CardContent className="space-y-4 p-0">
                        <div className="flex flex-wrap items-center gap-2 border-b border-border p-4">
                            <Select
                                className="w-auto min-w-44"
                                value={filtros.dia ?? ""}
                                onChange={(e) =>
                                    filtrar({
                                        dia: e.target.value,
                                        resultado: null,
                                    })
                                }
                                aria-label="Día"
                                disabled={dias.length === 0}
                            >
                                {dias.length === 0 && (
                                    <option value="">Sin registros</option>
                                )}
                                {dias.map((d) => (
                                    <option key={d.dia} value={d.dia}>
                                        {fecha(d.dia)} · {peso(d.bytes)}
                                    </option>
                                ))}
                            </Select>

                            <Select
                                className="w-auto min-w-40"
                                value={filtros.resultado ?? ""}
                                onChange={(e) =>
                                    filtrar({
                                        resultado: e.target.value || null,
                                    })
                                }
                                aria-label="Filtrar por resultado"
                            >
                                <option value="">Todos los resultados</option>
                                {resultados.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>

                            <Select
                                className="w-auto"
                                value={filtros.por_pagina}
                                onChange={(e) =>
                                    filtrar({
                                        por_pagina: Number(e.target.value),
                                    })
                                }
                                aria-label="Registros por página"
                            >
                                {opcionesPorPagina.map((n) => (
                                    <option key={n} value={n}>
                                        {n} por página
                                    </option>
                                ))}
                            </Select>

                            <form
                                className="relative min-w-48 flex-1 sm:ml-auto sm:max-w-sm"
                                onSubmit={(e: FormEvent) => {
                                    e.preventDefault();
                                    filtrar();
                                }}
                            >
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    className="pl-9"
                                    placeholder="Buscar: código, liquidación, funcionario…"
                                    value={buscar}
                                    onChange={(e) => setBuscar(e.target.value)}
                                    aria-label="Buscar en el registro"
                                />
                            </form>
                        </div>

                        {entradas.data.length === 0 ? (
                            <EstadoVacio
                                icono={ScrollText}
                                titulo={
                                    dias.length === 0
                                        ? "Todavía no hay registro"
                                        : "Nada para mostrar"
                                }
                                descripcion={
                                    dias.length === 0
                                        ? "Aparece en cuanto Jichi le hable a SIREB por primera vez."
                                        : "Ningún pedido de ese día coincide con los filtros."
                                }
                            />
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-y border-border text-left text-xs tracking-wide text-muted-foreground uppercase">
                                            <tr>
                                                <th className="w-8 px-3 py-2.5" />
                                                <th className="px-3 py-2.5 font-medium">
                                                    Hora
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    Resultado
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    Pedido
                                                </th>
                                                <th className="px-3 py-2.5 text-right font-medium">
                                                    Código
                                                </th>
                                                <th className="px-3 py-2.5 text-right font-medium">
                                                    Tiempo
                                                </th>
                                                <th className="px-3 py-2.5 font-medium">
                                                    Quién
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {entradas.data.map((e) => (
                                                <Fragment key={e.id}>
                                                    <tr
                                                        onClick={() =>
                                                            setAbierta(
                                                                abierta === e.id
                                                                    ? null
                                                                    : e.id,
                                                            )
                                                        }
                                                        className={cn(
                                                            "cursor-pointer hover:bg-secondary/50",
                                                            abierta === e.id &&
                                                                "bg-secondary/60",
                                                        )}
                                                    >
                                                        <td className="px-3 py-2.5 text-muted-foreground">
                                                            <ChevronRight
                                                                className={cn(
                                                                    "size-4 transition-transform",
                                                                    abierta ===
                                                                        e.id &&
                                                                        "rotate-90",
                                                                )}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2.5 font-mono text-xs whitespace-nowrap tabular-nums">
                                                            {e.hora}
                                                        </td>
                                                        <td className="px-3 py-2.5">
                                                            <Badge
                                                                color={
                                                                    e.resultado_color
                                                                }
                                                                className="whitespace-nowrap"
                                                            >
                                                                {
                                                                    e.resultado_etiqueta
                                                                }
                                                            </Badge>
                                                        </td>
                                                        <td className="max-w-md px-3 py-2.5">
                                                            {e.metodo ? (
                                                                <span className="flex min-w-0 items-center gap-2">
                                                                    <span className="shrink-0 rounded bg-secondary px-1.5 py-0.5 font-mono text-[11px] font-semibold">
                                                                        {
                                                                            e.metodo
                                                                        }
                                                                    </span>
                                                                    <span
                                                                        className="truncate font-mono text-xs"
                                                                        title={
                                                                            e.url ??
                                                                            ""
                                                                        }
                                                                    >
                                                                        {e.ruta}
                                                                    </span>
                                                                </span>
                                                            ) : (
                                                                <span className="text-xs">
                                                                    {e.mensaje}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-right font-mono text-xs tabular-nums">
                                                            {e.codigo ?? "—"}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                "px-3 py-2.5 text-right text-xs whitespace-nowrap tabular-nums",
                                                                (e.ms ?? 0) >=
                                                                    5000 &&
                                                                    "font-semibold text-amber-600",
                                                            )}
                                                        >
                                                            {e.ms === null
                                                                ? "—"
                                                                : e.ms >= 1000
                                                                  ? `${(e.ms / 1000).toFixed(1)} s`
                                                                  : `${e.ms} ms`}
                                                        </td>
                                                        <td className="px-3 py-2.5 text-xs whitespace-nowrap text-muted-foreground">
                                                            {e.quien ?? "—"}
                                                        </td>
                                                    </tr>
                                                    {abierta === e.id && (
                                                        <tr className="bg-secondary/30">
                                                            <td
                                                                colSpan={7}
                                                                className="px-4 py-4"
                                                            >
                                                                <Detalle
                                                                    entrada={e}
                                                                />
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <Paginacion paginado={entradas} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </LayoutPanel>
    );
}

/** Lo que se mandó y lo que volvió, uno al lado del otro. */
function Detalle({ entrada: e }: { entrada: Entrada }) {
    return (
        <div className="space-y-3">
            {e.url && (
                <p className="font-mono text-xs break-all text-muted-foreground">
                    {e.url}
                </p>
            )}
            {e.error && (
                <p className="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30">
                    <strong>No contestó:</strong> {e.error}
                </p>
            )}
            <div className="grid gap-3 lg:grid-cols-2">
                {e.contexto ? (
                    <BloqueJson titulo="Detalle" valor={e.contexto} />
                ) : (
                    <>
                        <BloqueJson
                            titulo="Lo que mandamos"
                            valor={e.enviado}
                        />
                        <BloqueJson
                            titulo="Lo que respondió SIREB"
                            valor={e.respuesta}
                            vacio={
                                e.error ? "No hubo respuesta." : "Sin cuerpo."
                            }
                        />
                    </>
                )}
            </div>
        </div>
    );
}

function BloqueJson({
    titulo,
    valor,
    vacio = "Nada.",
}: {
    titulo: string;
    valor: unknown;
    vacio?: string;
}) {
    const [copiado, setCopiado] = useState(false);
    const hay =
        valor !== null &&
        valor !== undefined &&
        !(Array.isArray(valor) && valor.length === 0);
    const texto =
        typeof valor === "string" ? valor : JSON.stringify(valor, null, 2);

    function copiar() {
        void navigator.clipboard?.writeText(texto).then(() => {
            setCopiado(true);
            setTimeout(() => setCopiado(false), 1500);
        });
    }

    return (
        <div className="min-w-0 overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex items-center justify-between border-b border-border px-3 py-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {titulo}
                </p>
                {hay && (
                    <button
                        type="button"
                        onClick={copiar}
                        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        {copiado ? (
                            <Check className="size-3.5 text-emerald-600" />
                        ) : (
                            <Copy className="size-3.5" />
                        )}
                        {copiado ? "Copiado" : "Copiar"}
                    </button>
                )}
            </div>
            {hay ? (
                <pre className="max-h-96 overflow-auto p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap break-all">
                    {texto}
                </pre>
            ) : (
                <p className="p-3 text-sm text-muted-foreground">{vacio}</p>
            )}
        </div>
    );
}

const TONOS = {
    emerald: "text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10",
    amber: "text-amber-600 bg-amber-50 dark:bg-amber-500/10",
    rose: "text-rose-600 bg-rose-50 dark:bg-rose-500/10",
    slate: "text-slate-600 bg-slate-100 dark:bg-slate-500/15 dark:text-slate-300",
};

/** Un contador del día; tocarlo filtra la lista por ese resultado. */
function Contador({
    titulo,
    valor,
    icono,
    tono,
    activo,
    onClick,
}: {
    titulo: string;
    valor: number;
    icono: ReactNode;
    tono: keyof typeof TONOS;
    activo: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                "flex items-center gap-3 rounded-xl border bg-card p-4 text-left transition-all hover:shadow-sm",
                activo
                    ? "border-primary ring-2 ring-primary/20"
                    : "border-border",
            )}
        >
            <span
                className={cn(
                    "flex size-10 shrink-0 items-center justify-center rounded-lg",
                    TONOS[tono],
                )}
            >
                {icono}
            </span>
            <span>
                <span className="block text-2xl leading-none font-semibold tabular-nums">
                    {valor}
                </span>
                <span className="mt-1 block text-xs text-muted-foreground">
                    {titulo}
                </span>
            </span>
        </button>
    );
}

function peso(bytes: number): string {
    return bytes >= 1048576
        ? `${(bytes / 1048576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
