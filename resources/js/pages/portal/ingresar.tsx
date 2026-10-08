import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    FileCheck2,
    LifeBuoy,
    LoaderCircle,
    Printer,
    QrCode,
    Receipt,
    ShieldCheck,
    Sparkles,
    Zap,
} from "lucide-react";
import type { FormEvent, ReactNode } from "react";
import { Toaster } from "sonner";
import { CampoPortal } from "@/components/portal/piezas";
import { FranjaTricolor } from "@/components/publico/institucional/franja-tricolor";
import {
    Cardumen,
    Olas,
} from "@/components/publico/institucional/ilustraciones";
import { useFlash } from "@/hooks/use-flash";

/**
 * Ingreso del beneficiario con su C.I. La cuenta y la primera clave se entregan
 * en ventanilla; acá no hay registro ni «olvidé mi contraseña».
 */
export default function Ingresar() {
    useFlash();
    const form = useForm({ ci: "", password: "" });

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        form.post(route("portal.ingresar.store"), {
            onFinish: () => form.reset("password"),
        });
    };

    return (
        <div
            className="relative min-h-screen overflow-hidden text-white"
            style={{
                background:
                    "radial-gradient(ellipse at top left, color-mix(in oklch, var(--rio-claro) 35%, transparent), transparent 55%), linear-gradient(165deg, var(--rio) 0%, var(--rio-profundo) 75%)",
            }}
        >
            <Head title="Ingresar a mi cuenta" />
            <FranjaTricolor className="absolute inset-x-0 top-0 z-20" />
            <Cardumen />

            <div className="relative z-10 mx-auto grid min-h-screen max-w-6xl items-center gap-10 px-4 pt-12 pb-36 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
                {/* La bienvenida: qué gana con entrar. En el celular se resume debajo de la tarjeta. */}
                <section className="hidden lg:block">
                    <Marca />
                    <h1 className="mt-10 text-5xl leading-[1.05] font-extrabold tracking-tight">
                        Todos sus trámites,
                        <span className="block text-rio-claro">a mano.</span>
                    </h1>
                    <p className="mt-5 max-w-md text-lg text-white/80">
                        Consulte su carnet, su autorización, sus faenas, sus
                        guías y sus pagos cuando quiera, desde su celular.
                    </p>

                    <MuyPronto className="mt-8" />

                    <ul className="mt-6 space-y-3">
                        {BENEFICIOS.map((b) => (
                            <Beneficio key={b.titulo} {...b} />
                        ))}
                    </ul>
                </section>

                <div className="mx-auto w-full max-w-md">
                    <div className="mb-6 lg:hidden">
                        <Marca centrada />
                    </div>

                    <form
                        onSubmit={enviar}
                        className="animar-entrada rounded-3xl bg-white p-6 text-slate-900 shadow-2xl ring-1 ring-white/20 sm:p-8"
                    >
                        <p className="text-sm font-semibold tracking-wide text-rio uppercase">
                            ¡Hola!
                        </p>
                        <h2 className="mt-1 text-2xl font-extrabold tracking-tight text-rio-profundo">
                            Ingrese a su cuenta
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Con su cédula y la contraseña que le dieron en
                            ventanilla.
                        </p>

                        <div className="mt-6 space-y-4">
                            <CampoPortal
                                etiqueta="Cédula de identidad"
                                inputMode="numeric"
                                autoComplete="username"
                                placeholder="Ej.: 1234567"
                                ayuda="Solo el número, sin el complemento ni el lugar de expedición."
                                value={form.data.ci}
                                onChange={(e) =>
                                    form.setData("ci", e.target.value)
                                }
                                error={form.errors.ci}
                                autoFocus
                            />
                            <CampoPortal
                                etiqueta="Contraseña"
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData("password", e.target.value)
                                }
                                error={form.errors.password}
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="group mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-rio px-4 py-3.5 text-base font-semibold text-white shadow-lg shadow-rio/30 transition-all hover:bg-rio-profundo hover:shadow-xl disabled:opacity-70"
                        >
                            {form.processing ? (
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

                        {/* Sin «olvidé mi contraseña»: la cuenta se entrega y se resetea en ventanilla. */}
                        <div className="mt-6 flex gap-3 rounded-2xl bg-rio-espuma p-4">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-rio shadow-sm">
                                <LifeBuoy className="size-5" />
                            </span>
                            <div className="text-sm">
                                <p className="font-semibold text-rio-profundo">
                                    ¿Primera vez u olvidó su contraseña?
                                </p>
                                <p className="mt-0.5 leading-relaxed text-slate-600">
                                    Acérquese a ventanilla de la Unidad de Pesca
                                    con su cédula: le entregan su cuenta en el
                                    momento.
                                </p>
                            </div>
                        </div>

                        <p className="mt-4 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                            <ShieldCheck className="size-4 text-emerald-600" />
                            No comparta su contraseña con nadie.
                        </p>
                    </form>

                    <Link
                        href={route("inicio")}
                        className="mt-5 flex items-center justify-center gap-1.5 text-sm font-medium text-white/80 transition-colors hover:text-white"
                    >
                        <ArrowLeft className="size-4" />
                        Volver al sitio
                    </Link>

                    <MuyPronto className="mt-8 lg:hidden" />

                    <ul className="mt-6 grid gap-3 lg:hidden">
                        {BENEFICIOS.map((b) => (
                            <Beneficio key={b.titulo} {...b} />
                        ))}
                    </ul>
                </div>
            </div>

            <Olas />
            <p className="absolute inset-x-0 bottom-3 z-10 text-center text-xs font-medium text-rio-profundo/70">
                Gobierno Autónomo Departamental del Beni · SEDAG
            </p>

            <Toaster position="top-center" richColors closeButton />
        </div>
    );
}

/**
 * El anuncio de lo que viene: pagar con QR y tramitar desde el celular. Dice
 * «Muy pronto» a propósito: hoy el portal solo consulta y el QR todavía no cobra.
 */
function MuyPronto({ className = "" }: { className?: string }) {
    return (
        <aside
            className={`relative overflow-hidden rounded-3xl bg-gradient-to-br from-institucional-dorado to-amber-300 p-5 text-rio-profundo shadow-2xl shadow-black/20 ring-1 ring-white/40 ${className}`}
        >
            {/* Un brillo de fondo: que se note que es otra cosa, no un dato más. */}
            <span className="pointer-events-none absolute -top-10 -right-10 size-36 rounded-full bg-white/30 blur-2xl" aria-hidden />

            <span className="relative inline-flex items-center gap-1.5 rounded-full bg-rio-profundo px-3 py-1 text-xs font-bold tracking-wider text-institucional-dorado uppercase">
                <span className="relative flex size-2">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-institucional-dorado opacity-75 motion-reduce:hidden" />
                    <span className="relative inline-flex size-2 rounded-full bg-institucional-dorado" />
                </span>
                Muy pronto
            </span>

            <p className="relative mt-3 text-2xl leading-tight font-extrabold tracking-tight">
                Pague con QR y tramite al momento
            </p>
            <p className="relative mt-1.5 text-sm font-medium text-rio-profundo/80">
                Sin ir al banco ni a ventanilla del SEDAG: todo desde su celular.
            </p>

            <div className="relative mt-4 grid gap-2.5 sm:grid-cols-2">
                <span className="flex items-center gap-2.5 rounded-2xl bg-white/60 p-3 backdrop-blur-sm">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rio-profundo text-institucional-dorado">
                        <QrCode className="size-5" />
                    </span>
                    <span className="text-sm leading-tight font-semibold">
                        Pague con la app de su banco
                    </span>
                </span>
                <span className="flex items-center gap-2.5 rounded-2xl bg-white/60 p-3 backdrop-blur-sm">
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-rio-profundo text-institucional-dorado">
                        <Zap className="size-5" />
                    </span>
                    <span className="text-sm leading-tight font-semibold">
                        Su trámite listo al momento
                    </span>
                </span>
            </div>

            <p className="relative mt-3 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-rio-profundo/80">
                <Sparkles className="size-3.5" />
                {TRAMITES.map((t, i) => (
                    <span key={t}>
                        {t}
                        {i < TRAMITES.length - 1 && " ·"}
                    </span>
                ))}
            </p>
        </aside>
    );
}

const TRAMITES = ["Autorización de pesca", "Carnet", "Permiso de faena", "Guía de transporte"];

/** Lo que puede hacer adentro, dicho para el pescador y el comercializador. */
const BENEFICIOS: { icono: ReactNode; titulo: string; texto: string }[] = [
    {
        icono: <FileCheck2 className="size-5" />,
        titulo: "Sus papeles al día",
        texto: "Carnet, autorización, faenas y guías, y hasta cuándo valen.",
    },
    {
        icono: <Receipt className="size-5" />,
        titulo: "Sus pagos y recibos",
        texto: "Qué pagó, qué le falta y el código para pagar.",
    },
    {
        icono: <Printer className="size-5" />,
        titulo: "Imprima desde su casa",
        texto: "La autorización, la faena y la guía vigentes.",
    },
];

function Beneficio({
    icono,
    titulo,
    texto,
}: {
    icono: ReactNode;
    titulo: string;
    texto: string;
}) {
    return (
        <li className="flex gap-3.5 rounded-2xl bg-white/[0.07] p-3.5 ring-1 ring-white/10 backdrop-blur-sm">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rio-claro/30 text-white ring-1 ring-white/15">
                {icono}
            </span>
            <span>
                <span className="block font-semibold">{titulo}</span>
                <span className="block text-sm text-white/70">{texto}</span>
            </span>
        </li>
    );
}

function Marca({ centrada = false }: { centrada?: boolean }) {
    return (
        <div
            className={`flex items-center gap-3 ${centrada ? "flex-col text-center" : ""}`}
        >
            <span className="flex size-14 items-center justify-center rounded-2xl bg-white/95 p-2 shadow-lg">
                <img
                    src="/image/icon.png"
                    alt=""
                    aria-hidden
                    className="size-full object-contain"
                />
            </span>
            <span>
                <span className="block text-2xl font-extrabold tracking-tight">
                    Mi cuenta
                </span>
                <span className="block text-sm text-white/75">
                    Pescadores y comercializadores · SEDAG Beni
                </span>
            </span>
        </div>
    );
}
