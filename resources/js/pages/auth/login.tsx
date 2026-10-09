import { Head, Link, useForm, usePage } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    CircleAlert,
    Fish,
    IdCard,
    KeyRound,
    LifeBuoy,
    LoaderCircle,
    Lock,
    ShieldCheck,
    Truck,
    type LucideIcon,
} from "lucide-react";
import { useState, type FormEvent } from "react";
import { CampoPortal } from "@/components/portal/piezas";
import { FranjaTricolor } from "@/components/publico/institucional/franja-tricolor";
import {
    Cardumen,
    Olas,
} from "@/components/publico/institucional/ilustraciones";
import { cn } from "@/lib/utils";
import type { PageProps } from "@/types";

/**
 * Ingreso de funcionarios. Mismo esqueleto que el del portal (portal/ingresar.tsx)
 * para que se lean como la misma casa; cambia el color: azul institucional y dorado.
 */

const TAREAS: { icono: LucideIcon; titulo: string; texto: string }[] = [
    {
        icono: IdCard,
        titulo: "Carnets",
        texto: "De pescadores y comercializadores, listos para imprimir.",
    },
    {
        icono: Fish,
        titulo: "Autorizaciones y faenas",
        texto: "El cupo de cada pescador y lo que lleva sacado.",
    },
    {
        icono: Truck,
        titulo: "Guías de transporte",
        texto: "Cada traslado de pescado, con su detalle.",
    },
];

// `ibare` = IBARE_ACTIVO: el login local queda plegado como acceso de emergencia del administrador.
export default function Login({ ibare }: { ibare: boolean }) {
    const { errors: erroresPagina } = usePage<PageProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false as boolean,
    });

    const [loginLocal, setLoginLocal] = useState(
        !ibare || Boolean(errors.email || errors.password),
    );

    function enviar(e: FormEvent) {
        e.preventDefault();
        post(route("login"), { onFinish: () => reset("password") });
    }

    return (
        <div
            className="relative min-h-screen overflow-hidden text-white"
            style={{
                background:
                    "radial-gradient(ellipse at top left, color-mix(in oklch, var(--institucional-dorado) 22%, transparent), transparent 50%), radial-gradient(ellipse at bottom right, color-mix(in oklch, var(--institucional-azul-claro) 70%, transparent), transparent 60%), linear-gradient(165deg, var(--institucional-azul-claro) 0%, var(--institucional-azul) 70%)",
            }}
        >
            <Head title="Iniciar sesión" />
            <FranjaTricolor className="absolute inset-x-0 top-0 z-20" />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.06)_1px,transparent_1px)] [background-size:22px_22px]"
            />
            <Cardumen />

            <div className="relative z-10 mx-auto grid min-h-screen max-w-5xl items-center gap-8 px-4 pt-10 pb-32 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:gap-14">
                {/* La bienvenida: qué se hace adentro. En el celular basta la tarjeta. */}
                <section className="hidden lg:block">
                    <Marca />

                    <span className="mt-7 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wider text-institucional-dorado uppercase ring-1 ring-white/15">
                        <span className="size-1.5 rounded-full bg-institucional-dorado" />
                        Solo personal del SEDAG
                    </span>

                    <h1 className="mt-3 text-4xl leading-[1.1] font-bold tracking-tight">
                        Pesca en orden,
                        <span className="block text-institucional-dorado">
                            trámites al día.
                        </span>
                    </h1>
                    <p className="mt-3 max-w-md text-base text-white/75">
                        Todo lo que se atiende en ventanilla, en un solo lugar.
                    </p>

                    <ul className="mt-6 space-y-2.5">
                        {TAREAS.map(({ icono: Icono, titulo, texto }) => (
                            <li
                                key={titulo}
                                className="flex items-center gap-3 rounded-2xl bg-white/[0.07] px-3.5 py-3 ring-1 ring-white/10 backdrop-blur-sm"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-institucional-dorado/15 text-institucional-dorado ring-1 ring-institucional-dorado/25">
                                    <Icono className="size-5" />
                                </span>
                                <span>
                                    <span className="block text-sm font-semibold">
                                        {titulo}
                                    </span>
                                    <span className="block text-xs text-white/65">
                                        {texto}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <div className="mx-auto w-full max-w-[25rem]">
                    <div className="mb-6 lg:hidden">
                        <Marca centrada />
                    </div>

                    <div className="animar-entrada rounded-3xl bg-white p-6 text-slate-900 shadow-2xl ring-1 ring-white/20 sm:p-7">
                        <p className="text-xs font-semibold tracking-wider text-institucional-dorado-oscuro uppercase">
                            ¡Hola!
                        </p>
                        <h2 className="mt-0.5 text-xl font-bold tracking-tight text-institucional-azul">
                            Ingrese al panel
                        </h2>
                        <p className="mt-1 text-[13px] text-slate-500">
                            {ibare
                                ? "Con su cuenta de la Gobernación, la misma de los demás sistemas."
                                : "Con su correo institucional y su contraseña."}
                        </p>

                        {ibare && erroresPagina.ibare && (
                            <div
                                role="alert"
                                className="mt-5 flex gap-3 rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-200"
                            >
                                <CircleAlert
                                    aria-hidden
                                    className="mt-0.5 size-5 shrink-0 text-rose-600"
                                />
                                <div className="text-sm">
                                    <p className="font-semibold text-rose-900">
                                        No pudo ingresar
                                    </p>
                                    <p className="mt-0.5 leading-relaxed text-rose-800/80">
                                        {erroresPagina.ibare}
                                    </p>
                                </div>
                            </div>
                        )}

                        {ibare && <BotonGobernacion />}

                        {ibare && !loginLocal && (
                            <button
                                type="button"
                                onClick={() => setLoginLocal(true)}
                                className="mt-5 flex w-full items-center justify-center gap-1.5 text-xs text-slate-400 transition-colors hover:text-slate-700"
                            >
                                <KeyRound className="size-3.5" />
                                Acceso de emergencia (solo administrador)
                            </button>
                        )}

                        {loginLocal && (
                            <form
                                onSubmit={enviar}
                                noValidate
                                className={cn(
                                    "space-y-4",
                                    ibare
                                        ? "mt-6 border-t border-slate-200 pt-6"
                                        : "mt-6",
                                )}
                            >
                                <CampoPortal
                                    etiqueta="Correo o usuario"
                                    autoComplete="username"
                                    placeholder="admin@admin.com"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    error={errors.email}
                                    autoFocus
                                />
                                <CampoPortal
                                    etiqueta="Contraseña"
                                    type="password"
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData("password", e.target.value)
                                    }
                                    error={errors.password}
                                />

                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) =>
                                            setData(
                                                "remember",
                                                e.target.checked,
                                            )
                                        }
                                        className="size-4 rounded accent-institucional-azul"
                                    />
                                    Mantener la sesión iniciada
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="group flex w-full items-center justify-center gap-2 rounded-xl bg-institucional-azul px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-institucional-azul/30 transition-all hover:bg-institucional-azul-claro disabled:opacity-70"
                                >
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="size-5 animate-spin" />
                                            Ingresando…
                                        </>
                                    ) : (
                                        <>
                                            Ingresar
                                            <ArrowRight className="size-5 transition-transform group-hover:translate-x-1" />
                                        </>
                                    )}
                                </button>
                            </form>
                        )}

                        <div className="mt-5 flex gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-100">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-institucional-azul shadow-sm">
                                <LifeBuoy className="size-4" />
                            </span>
                            <div className="text-sm">
                                <p className="font-semibold text-institucional-azul">
                                    ¿No puede entrar?
                                </p>
                                <p className="mt-0.5 text-[13px] leading-snug text-slate-600">
                                    Llame a la Unidad de Sistemas de la
                                    Gobernación: le ayudan con su usuario y su
                                    acceso.
                                </p>
                            </div>
                        </div>

                        <p className="mt-4 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                            <Lock className="size-3.5 text-emerald-600" />
                            Todos los accesos quedan registrados.
                        </p>
                    </div>

                    <Link
                        href={route("inicio")}
                        className="mt-5 flex items-center justify-center gap-1.5 text-sm font-medium text-white/80 transition-colors hover:text-white"
                    >
                        <ArrowLeft className="size-4" />
                        Volver al sitio
                    </Link>
                </div>
            </div>

            <Olas />
            <p className="absolute inset-x-0 bottom-3 z-10 text-center text-xs font-medium text-institucional-azul/70">
                Gobierno Autónomo Departamental del Beni · SEDAG
            </p>
        </div>
    );
}

/** El botón principal: halo dorado que late y flecha que empuja (ver `animar-*-boton` en app.css). */
function BotonGobernacion() {
    return (
        <div className="relative mt-5">
            <span
                aria-hidden
                className="animar-halo-boton absolute -inset-1 rounded-[1.25rem] bg-institucional-dorado/40 blur-lg"
            />
            <a
                href={route("ibare.redirigir")}
                className="group relative flex h-14 w-full items-center gap-3 rounded-2xl bg-gradient-to-b from-institucional-azul-claro to-institucional-azul px-3 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.18),0_10px_24px_-8px_rgb(0_0_0/0.45)] ring-1 ring-white/10 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[inset_0_1px_0_rgb(255_255_255/0.22),0_16px_30px_-10px_rgb(0_0_0/0.5)] focus-visible:ring-4 focus-visible:ring-institucional-dorado/60 focus-visible:outline-none active:translate-y-0"
            >
                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                    <ShieldCheck className="size-5 text-institucional-dorado" />
                </span>
                <span className="flex-1 text-left leading-tight">
                    <span className="block text-[15px] font-semibold">
                        Ingresar con mi cuenta
                    </span>
                    <span className="block text-xs text-white/65">
                        Gobernación del Beni
                    </span>
                </span>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-institucional-dorado text-institucional-azul shadow-md transition-transform duration-200 group-hover:scale-110">
                    <ArrowRight className="animar-empuje-flecha size-5" />
                </span>
            </a>
        </div>
    );
}

function Marca({ centrada = false }: { centrada?: boolean }) {
    return (
        <div
            className={cn(
                "flex items-center gap-3",
                centrada && "flex-col text-center",
            )}
        >
            <span className="flex size-11 items-center justify-center rounded-xl bg-white/95 p-1.5 shadow-lg">
                <img
                    src="/image/icon.png"
                    alt=""
                    aria-hidden
                    className="size-full object-contain"
                />
            </span>
            <span>
                <span className="block text-lg leading-tight font-bold tracking-tight">
                    Panel de funcionarios
                </span>
                <span className="block text-xs text-white/70">
                    Jichi · SEDAG Beni
                </span>
            </span>
        </div>
    );
}
