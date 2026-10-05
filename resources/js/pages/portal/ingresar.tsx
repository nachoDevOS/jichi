import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, LogIn } from "lucide-react";
import type { FormEvent } from "react";
import { Toaster } from "sonner";
import { CampoPortal } from "@/components/portal/piezas";
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
            className="flex min-h-screen flex-col items-center justify-center px-4 py-10"
            style={{
                background:
                    "linear-gradient(170deg, var(--rio) 0%, var(--rio-profundo) 100%)",
            }}
        >
            <Head title="Ingresar a mi cuenta" />

            <div className="w-full max-w-sm">
                <div className="mb-6 text-center text-white">
                    <img
                        src="/image/icon.png"
                        alt=""
                        aria-hidden
                        className="mx-auto size-16 object-contain"
                    />
                    <h1 className="mt-3 text-2xl font-extrabold tracking-tight">
                        Mi cuenta
                    </h1>
                    <p className="text-sm text-white/75">
                        Pescadores y comercializadores · SEDAG Beni
                    </p>
                </div>

                <form
                    onSubmit={enviar}
                    className="space-y-4 rounded-3xl bg-white p-6 shadow-2xl"
                >
                    <CampoPortal
                        etiqueta="Cédula de identidad"
                        inputMode="numeric"
                        autoComplete="username"
                        placeholder="Ej.: 1234567"
                        ayuda="Solo el número, sin el complemento ni el lugar de expedición."
                        value={form.data.ci}
                        onChange={(e) => form.setData("ci", e.target.value)}
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

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="flex w-full items-center justify-center gap-2 rounded-xl bg-rio px-4 py-3 font-semibold text-white shadow-sm transition-colors hover:bg-rio-profundo disabled:opacity-60"
                    >
                        <LogIn className="size-4.5" />
                        Ingresar
                    </button>

                    <p className="text-center text-xs leading-relaxed text-slate-500">
                        ¿No tiene cuenta u olvidó su contraseña? Acérquese a
                        ventanilla de la Unidad de Pesca con su cédula.
                    </p>
                </form>

                <Link
                    href={route("inicio")}
                    className="mt-5 flex items-center justify-center gap-1.5 text-sm font-medium text-white/80 hover:text-white"
                >
                    <ArrowLeft className="size-4" />
                    Volver al sitio
                </Link>
            </div>

            <Toaster position="top-center" richColors closeButton />
        </div>
    );
}
