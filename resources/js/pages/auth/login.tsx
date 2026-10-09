import { Head, useForm, usePage } from "@inertiajs/react";
import {
    ArrowRight,
    CircleAlert,
    Fish,
    IdCard,
    Info,
    LoaderCircle,
    Lock,
    Mail,
    ShieldCheck,
    Truck,
    type LucideIcon,
} from "lucide-react";
import { useState, type FormEvent } from "react";
import { LogoJichi } from "@/components/comunes/logo-jichi";
import { ToggleApariencia } from "@/components/comunes/toggle-apariencia";
import { Cardumen } from "@/components/publico/institucional/ilustraciones";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import type { PageProps } from "@/types";

const TAREAS: { icono: LucideIcon; texto: string }[] = [
    { icono: IdCard, texto: "Carnets de pescadores y comercializadores" },
    { icono: Fish, texto: "Autorizaciones de pesca y permisos de faena" },
    { icono: Truck, texto: "Guías de transporte de pescado" },
];

// `ibare` = IBARE_ACTIVO: el login local queda plegado como acceso de emergencia del administrador.
export default function Login({ ibare }: { ibare: boolean }) {
    const { institucion, errors: erroresPagina } = usePage<PageProps>().props;

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
        <>
            <Head title="Iniciar sesión" />

            <div className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
                {/* Panel institucional */}
                <div className="relative hidden overflow-hidden bg-institucional-azul p-12 text-white lg:flex lg:flex-col lg:justify-between">
                    {/* Fondo hecho con CSS y SVG: un PNG con fondo propio deja un recuadro visible. */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0"
                    >
                        <div className="absolute -top-32 -left-32 size-[30rem] rounded-full bg-institucional-azul-claro/40 blur-3xl" />
                        <div className="absolute top-1/3 -right-40 size-[26rem] rounded-full bg-institucional-dorado/15 blur-3xl" />
                        <div className="absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.07)_1px,transparent_1px)] [background-size:22px_22px]" />
                        <Olas />
                        <Cardumen />
                    </div>

                    <div className="relative flex items-center gap-3">
                        <span className="flex size-12 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm">
                            <LogoJichi className="size-full" />
                        </span>
                        <div className="leading-tight">
                            <p className="text-xl font-bold tracking-tight">
                                Jichi
                            </p>
                            <p className="text-xs text-white/70">
                                SEDAG · Gobernación del Beni
                            </p>
                        </div>
                    </div>

                    <div className="relative max-w-md space-y-8">
                        <div className="space-y-3">
                            <h2 className="text-4xl leading-tight font-semibold">
                                Pesca en orden,
                                <br />
                                <span className="text-institucional-dorado">
                                    trámites al día.
                                </span>
                            </h2>
                            <p className="text-white/75">
                                Plataforma institucional del{" "}
                                {institucion?.municipio ??
                                    "Gobierno Autónomo Departamental del Beni"}
                                .
                            </p>
                        </div>

                        <ul className="space-y-2.5">
                            {TAREAS.map(({ icono: Icono, texto }) => (
                                <li
                                    key={texto}
                                    className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-3 backdrop-blur-sm"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-institucional-dorado/15">
                                        <Icono
                                            aria-hidden
                                            className="size-4 text-institucional-dorado"
                                        />
                                    </span>
                                    <span className="text-sm text-white/90">
                                        {texto}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative flex items-center gap-2 text-xs text-white/60">
                        <Lock aria-hidden className="size-3.5" />
                        Uso exclusivo de personal autorizado. Todos los accesos
                        quedan registrados.
                    </p>
                </div>

                {/* Formulario */}
                <div className="relative flex flex-col items-center justify-center bg-muted/40 px-4 py-12 sm:px-6">
                    <div className="absolute top-4 right-4">
                        <ToggleApariencia />
                    </div>

                    <div className="w-full max-w-md">
                        <div className="rounded-2xl border border-border bg-card p-8 shadow-sm sm:p-10">
                            <div className="mb-8 flex flex-col items-center text-center">
                                <span className="mb-4 flex size-16 items-center justify-center rounded-2xl bg-secondary p-2">
                                    <LogoJichi className="size-full" />
                                </span>
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    ¡Bienvenido!
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {ibare
                                        ? "Ingrese con su cuenta de la Gobernación para empezar a trabajar."
                                        : "Ingrese con su correo institucional."}
                                </p>
                            </div>

                            {ibare && erroresPagina.ibare && (
                                <div
                                    role="alert"
                                    className="mb-6 flex gap-3 rounded-xl border border-destructive/25 bg-destructive/5 p-4"
                                >
                                    <CircleAlert
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-destructive"
                                    />
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-semibold">
                                            No pudo ingresar
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {erroresPagina.ibare}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {ibare && (
                                <div className="space-y-4">
                                    <div className="relative">
                                        {/* Halo dorado detrás del botón: late solo, sin esperar el mouse. */}
                                        <span
                                            aria-hidden
                                            className="animar-halo-boton absolute -inset-1 rounded-[1.25rem] bg-institucional-dorado/35 blur-lg"
                                        />
                                        <a
                                            href={route("ibare.redirigir")}
                                            className="group relative flex h-16 w-full items-center gap-3.5 rounded-2xl bg-gradient-to-b from-institucional-azul-claro to-institucional-azul pr-3 pl-3 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.18),0_10px_24px_-8px_rgb(0_0_0/0.45)] ring-1 ring-white/10 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[inset_0_1px_0_rgb(255_255_255/0.22),0_16px_30px_-10px_rgb(0_0_0/0.5)] focus-visible:ring-4 focus-visible:ring-institucional-dorado/60 focus-visible:outline-none active:translate-y-0"
                                        >
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15">
                                                <ShieldCheck className="size-5 text-institucional-dorado" />
                                            </span>
                                            <span className="flex-1 text-left leading-tight">
                                                <span className="block text-base font-semibold">
                                                    Ingresar con mi cuenta
                                                </span>
                                                <span className="block text-xs text-white/65">
                                                    Gobernación del Beni
                                                </span>
                                            </span>
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-institucional-dorado text-institucional-azul shadow-md transition-transform duration-200 group-hover:scale-110">
                                                <ArrowRight className="animar-empuje-flecha size-5" />
                                            </span>
                                        </a>
                                    </div>

                                    {/* Ibare es el sistema de cuentas: el funcionario lo
                                        reconoce por su usuario, no por el nombre. */}
                                    <p className="flex gap-2 rounded-lg bg-secondary/60 p-3 text-xs text-muted-foreground">
                                        <Info
                                            aria-hidden
                                            className="mt-px size-4 shrink-0"
                                        />
                                        Es el mismo usuario y contraseña que usa
                                        en los demás sistemas de la Gobernación.
                                    </p>
                                </div>
                            )}

                            {ibare && !loginLocal && (
                                <button
                                    type="button"
                                    onClick={() => setLoginLocal(true)}
                                    className="mt-6 w-full text-center text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                >
                                    Acceso de emergencia (solo administrador)
                                </button>
                            )}

                            {loginLocal && (
                                <form
                                    onSubmit={enviar}
                                    className={cn(
                                        "space-y-4",
                                        ibare &&
                                            "mt-6 border-t border-border pt-6",
                                    )}
                                    noValidate
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="email">
                                            Correo o usuario
                                        </Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />
                                            <Input
                                                id="email"
                                                type="text"
                                                name="email"
                                                value={data.email}
                                                onChange={(e) =>
                                                    setData(
                                                        "email",
                                                        e.target.value,
                                                    )
                                                }
                                                autoComplete="username"
                                                autoFocus
                                                required
                                                aria-invalid={Boolean(
                                                    errors.email,
                                                )}
                                                className="pl-9"
                                                placeholder="admin@admin.com"
                                            />
                                        </div>
                                        {errors.email && (
                                            <p
                                                className="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="password">
                                            Contraseña
                                        </Label>
                                        <div className="relative">
                                            <Lock className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />
                                            <Input
                                                id="password"
                                                type="password"
                                                name="password"
                                                value={data.password}
                                                onChange={(e) =>
                                                    setData(
                                                        "password",
                                                        e.target.value,
                                                    )
                                                }
                                                autoComplete="current-password"
                                                required
                                                aria-invalid={Boolean(
                                                    errors.password,
                                                )}
                                                className="pl-9"
                                                placeholder="••••••••"
                                            />
                                        </div>
                                        {errors.password && (
                                            <p
                                                className="text-sm text-destructive"
                                                role="alert"
                                            >
                                                {errors.password}
                                            </p>
                                        )}
                                    </div>

                                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <input
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={(e) =>
                                                setData(
                                                    "remember",
                                                    e.target.checked,
                                                )
                                            }
                                            className="size-4 rounded border-input accent-primary"
                                        />
                                        Mantener la sesión iniciada
                                    </label>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        )}
                                        Ingresar
                                    </Button>
                                </form>
                            )}
                        </div>

                        <p className="mt-6 text-center text-xs text-muted-foreground">
                            ¿Problemas para ingresar? Llame a la Unidad de
                            Sistemas de la Gobernación.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

// Tres olas superpuestas al pie del panel: el río, que es de donde sale todo lo que el sistema registra.
function Olas() {
    return (
        <svg
            viewBox="0 0 1440 320"
            preserveAspectRatio="none"
            className="absolute inset-x-0 bottom-0 h-56 w-full"
        >
            <path
                fill="rgb(255 255 255 / 0.04)"
                d="M0,160 C240,96 480,224 720,176 C960,128 1200,64 1440,128 L1440,320 L0,320 Z"
            />
            <path
                fill="rgb(255 255 255 / 0.05)"
                d="M0,224 C200,176 440,272 720,232 C1000,192 1240,160 1440,208 L1440,320 L0,320 Z"
            />
            <path
                fill="rgb(0 0 0 / 0.12)"
                d="M0,272 C260,240 500,304 760,280 C1020,256 1240,240 1440,264 L1440,320 L0,320 Z"
            />
        </svg>
    );
}
