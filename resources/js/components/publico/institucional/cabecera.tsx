import { Link, usePage } from "@inertiajs/react";
import {
    LayoutDashboard,
    LogIn,
    Mail,
    Menu,
    Phone,
    UserRound,
    X,
} from "lucide-react";
import { useState } from "react";
import { FranjaTricolor } from "@/components/publico/institucional/franja-tricolor";
import type { PageProps } from "@/types";
import type { InstitucionPortada } from "@/types/publico";

/** Las anclas de la página larga. El orden es el de las secciones. */
const SECCIONES = [
    { id: "sedag", texto: "SEDAG" },
    { id: "tramites", texto: "Trámites" },
    { id: "pasos", texto: "En ventanilla" },
    { id: "verificacion", texto: "Verificar documento" },
    { id: "preguntas", texto: "Preguntas" },
    { id: "contacto", texto: "Contacto" },
];

/**
 *  Cabecera institucional — barra de contacto, escudo y navegación
 */
export function Cabecera({ portada }: { portada: InstitucionPortada }) {
    const { auth } = usePage<PageProps>().props;
    const [abierto, setAbierto] = useState(false);

    return (
        <header className="sticky top-0 z-40">
            {/* Barra de contacto. Se esconde en el teléfono: ahí el teléfono y
                el correo ya están en la sección de contacto, y ocuparían la
                mitad de la primera pantalla. */}
            <div className="hidden bg-rio-profundo text-white/85 sm:block">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-1.5 text-xs sm:px-6">
                    <span className="font-semibold tracking-wide">
                        {portada.sigla}
                    </span>

                    <div className="flex items-center gap-5">
                        {portada.telefono && (
                            <a
                                href={`tel:${portada.telefono.replace(/[^+\d]/g, "")}`}
                                className="flex items-center gap-1.5 hover:text-white"
                            >
                                <Phone className="size-3.5" />
                                {portada.telefono}
                            </a>
                        )}
                        {portada.email && (
                            <a
                                href={`mailto:${portada.email}`}
                                className="flex items-center gap-1.5 hover:text-white"
                            >
                                <Mail className="size-3.5" />
                                {portada.email}
                            </a>
                        )}
                    </div>
                </div>
            </div>

            <FranjaTricolor />

            <div className="border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur-md">
                <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
                    <img
                        src="/image/icon.png"
                        alt={`Escudo del ${portada.departamento}`}
                        className="size-11 shrink-0 object-contain sm:size-12"
                    />

                    {/* min-w-0 para que el nombre largo de la institución pueda
                        truncarse en vez de empujar al botón fuera de pantalla. */}
                    <div className="min-w-0 flex-1 leading-tight">
                        <p className="truncate text-[11px] font-semibold tracking-[0.12em] text-rio-profundo uppercase sm:text-xs">
                            {portada.nombre}
                        </p>
                        <p className="truncate text-[11px] text-slate-500">
                            SEDAG · Unidad de Pesca · Sistema {portada.sistema}
                        </p>
                    </div>

                    <nav className="hidden items-center gap-1 lg:flex">
                        {SECCIONES.map((s) => (
                            <a
                                key={s.id}
                                href={`#${s.id}`}
                                className="rounded-md px-2.5 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-rio-espuma hover:text-rio"
                            >
                                {s.texto}
                            </a>
                        ))}
                    </nav>

                    <BotonMiCuenta />
                    {!auth.user?.es_beneficiario && (
                        <BotonAcceso autenticado={auth.user !== null} />
                    )}

                    <button
                        type="button"
                        onClick={() => setAbierto((v) => !v)}
                        aria-label="Menú"
                        aria-expanded={abierto}
                        className="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                    >
                        {abierto ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>

                {abierto && (
                    <nav className="border-t border-slate-200 bg-white lg:hidden">
                        {SECCIONES.map((s) => (
                            <a
                                key={s.id}
                                href={`#${s.id}`}
                                onClick={() => setAbierto(false)}
                                className="block border-b border-slate-100 px-5 py-3 text-sm font-medium text-slate-700 active:bg-slate-50"
                            >
                                {s.texto}
                            </a>
                        ))}
                    </nav>
                )}
            </div>
        </header>
    );
}

// Las dos puertas comparten forma: «Mi cuenta» se llena porque es la del público;
// «Funcionarios» va de borde, presente pero sin competir.
const BASE_BOTON =
    "flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-all hover:-translate-y-px sm:px-4 sm:text-sm";

/**
 * Quien ya tiene sesión abierta no necesita volver a entrar: se le ofrece el
 * panel directo.
 */
function BotonAcceso({ autenticado }: { autenticado: boolean }) {
    const destino = autenticado ? route("dashboard") : route("login");
    const Icono = autenticado ? LayoutDashboard : LogIn;
    const texto = autenticado ? "Ir al panel" : "Funcionarios";

    return (
        // aria-label aunque el texto esté al lado: en el teléfono se esconde y
        // el botón se queda sin nombre para un lector de pantalla.
        <Link
            href={destino}
            aria-label={texto}
            className={`${BASE_BOTON} border-rio/25 bg-white text-rio hover:border-rio hover:bg-rio-espuma`}
        >
            <Icono className="size-4" />
            <span className="hidden sm:inline">{texto}</span>
        </Link>
    );
}

/** El portal del beneficiario: con sesión abierta de beneficiario va directo a su cuenta. */
function BotonMiCuenta() {
    const { auth } = usePage<PageProps>().props;
    const destino = auth.user?.es_beneficiario
        ? route("portal.inicio")
        : route("portal.ingresar");

    return (
        <Link
            href={destino}
            aria-label="Mi cuenta"
            className={`${BASE_BOTON} border-institucional-dorado-oscuro/40 bg-institucional-dorado text-rio-profundo shadow-md shadow-institucional-dorado/40 hover:bg-institucional-dorado-oscuro`}
        >
            <UserRound className="size-4" />
            <span className="hidden sm:inline">Mi cuenta</span>
        </Link>
    );
}
