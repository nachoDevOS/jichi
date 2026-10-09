import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, KeyRound, ShieldCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Button, buttonVariants } from "@/components/ui/button";
import { Campo } from "@/components/ui/campo";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import LayoutPanel from "@/layouts/layout-panel";
import { cn } from "@/lib/utils";
import type { OpcionEnum } from "@/types";

interface UsuarioFormulario {
    id: number;
    nombre: string;
    mamore_id: string | null;
    rol: string | null;
    email: string | null;
    activo: boolean;
}

/**
 *  Alta y edición de un funcionario. Con Ibare encendido entra por su id de Ibare;
 *  correo y clave solo los lleva el administrador, para el acceso de emergencia.
 */
export default function UsuariosFormulario({
    usuario,
    roles,
    ibareActivo,
}: {
    /** null = alta. */
    usuario: UsuarioFormulario | null;
    roles: Omit<OpcionEnum, "color">[];
    ibareActivo: boolean;
}) {
    const esAlta = usuario === null;
    const form = useForm({
        nombre: usuario?.nombre ?? "",
        mamore_id: usuario?.mamore_id ?? "",
        rol: usuario?.rol ?? "",
        email: usuario?.email ?? "",
        password: "",
        password_confirmation: "",
        activo: usuario?.activo ?? true,
    });
    const titulo = esAlta ? "Nuevo usuario" : "Editar usuario";

    // Mismo criterio que GuardarUsuarioRequest::llevaClave().
    const llevaClave = !ibareActivo || form.data.rol === "administrador";
    // Con Ibare, el correo y la clave no hacen falta para entrar: se piden solo si se quiere el acceso de emergencia.
    const [conEmergencia, setConEmergencia] = useState(Boolean(usuario?.email));
    const muestraAcceso = !ibareActivo || conEmergencia;

    function enviar(e: FormEvent) {
        e.preventDefault();
        if (esAlta) form.post(route("usuarios.store"));
        else form.put(route("usuarios.update", usuario.id));
    }

    return (
        <LayoutPanel
            titulo={titulo}
            descripcion={
                !esAlta
                    ? usuario.nombre
                    : ibareActivo
                      ? "Un funcionario que entra al panel con su cuenta de Ibare."
                      : "Un funcionario que entra al panel con correo y clave."
            }
            acciones={
                <Link
                    href={route("usuarios.index")}
                    className={cn(buttonVariants({ variant: "outline" }))}
                >
                    <ArrowLeft className="size-4" />
                    Volver a usuarios
                </Link>
            }
        >
            <Head title={titulo} />

            <form onSubmit={enviar} className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-primary" />
                            Funcionario
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            etiqueta="Nombre completo"
                            htmlFor="nombre"
                            error={form.errors.nombre}
                            obligatorio
                            className="sm:col-span-2"
                        >
                            <Input
                                id="nombre"
                                value={form.data.nombre}
                                onChange={(e) =>
                                    form.setData("nombre", e.target.value)
                                }
                                aria-invalid={Boolean(form.errors.nombre)}
                                maxLength={255}
                                autoFocus
                            />
                        </Campo>
                        {ibareActivo && (
                            <Campo
                                etiqueta="Id de Ibare"
                                htmlFor="mamore_id"
                                error={form.errors.mamore_id}
                                obligatorio
                                ayuda="El número que muestra «Su cuenta de funcionario (id …) no tiene acceso a Jichi»."
                            >
                                <Input
                                    id="mamore_id"
                                    inputMode="numeric"
                                    value={form.data.mamore_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "mamore_id",
                                            e.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.mamore_id,
                                    )}
                                    maxLength={50}
                                    placeholder="1002"
                                />
                            </Campo>
                        )}
                        <Campo
                            etiqueta="Rol"
                            htmlFor="rol"
                            error={form.errors.rol}
                            obligatorio
                            ayuda="Define qué puede ver y hacer en el panel."
                        >
                            <Select
                                id="rol"
                                value={form.data.rol}
                                onChange={(e) =>
                                    form.setData("rol", e.target.value)
                                }
                                aria-invalid={Boolean(form.errors.rol)}
                            >
                                <option value="">Elegir…</option>
                                {roles.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </Select>
                        </Campo>
                        {!esAlta && (
                            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                <input
                                    type="checkbox"
                                    className="size-4 accent-primary"
                                    checked={form.data.activo}
                                    onChange={(e) =>
                                        form.setData("activo", e.target.checked)
                                    }
                                />
                                Cuenta activa
                                <span className="text-muted-foreground">
                                    — desactivada, no entra ni por Ibare ni por
                                    clave.
                                </span>
                            </label>
                        )}
                    </CardContent>
                </Card>

                {llevaClave && (
                    <Card>
                        <CardHeader>
                            {ibareActivo ? (
                                <label className="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        className="mt-1 size-4 accent-primary"
                                        checked={conEmergencia}
                                        onChange={(e) => {
                                            setConEmergencia(e.target.checked);
                                            // Sin acceso de emergencia no queda usuario con el que entrar por clave.
                                            if (!e.target.checked)
                                                form.setData({
                                                    ...form.data,
                                                    email: "",
                                                    password: "",
                                                    password_confirmation: "",
                                                });
                                        }}
                                    />
                                    <span>
                                        <span className="flex items-center gap-2 font-medium">
                                            <KeyRound className="size-4 text-amber-600" />
                                            Darle acceso de emergencia
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            No hace falta para entrar por Ibare.
                                            Es un usuario y una clave de Jichi
                                            para entrar si Ibare no responde.
                                        </span>
                                    </span>
                                </label>
                            ) : (
                                <CardTitle className="flex items-center gap-2">
                                    <KeyRound className="size-4 text-amber-600" />
                                    Acceso
                                </CardTitle>
                            )}
                        </CardHeader>
                        {muestraAcceso && (
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="Correo o usuario"
                                    htmlFor="email"
                                    error={form.errors.email}
                                    obligatorio={!ibareActivo}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="email"
                                        type="text"
                                        autoComplete="off"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                "email",
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.email,
                                        )}
                                        maxLength={255}
                                        placeholder="sedag.encargado o nombre@beni.gob.bo"
                                    />
                                </Campo>
                                <Campo
                                    etiqueta="Clave"
                                    htmlFor="password"
                                    error={form.errors.password}
                                    obligatorio={!ibareActivo && esAlta}
                                    ayuda={
                                        esAlta
                                            ? "Al menos 8 caracteres."
                                            : "En blanco, queda la que tiene."
                                    }
                                >
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData(
                                                "password",
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            form.errors.password,
                                        )}
                                    />
                                </Campo>
                                <Campo
                                    etiqueta="Repetir clave"
                                    htmlFor="password_confirmation"
                                    obligatorio={!ibareActivo && esAlta}
                                >
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) =>
                                            form.setData(
                                                "password_confirmation",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Campo>
                            </CardContent>
                        )}
                    </Card>
                )}

                <div className="flex justify-end gap-2">
                    <Link
                        href={route("usuarios.index")}
                        className={cn(buttonVariants({ variant: "outline" }))}
                    >
                        Cancelar
                    </Link>
                    <Button
                        key="guardar-usuario"
                        type="submit"
                        disabled={form.processing}
                    >
                        {esAlta ? "Crear usuario" : "Guardar cambios"}
                    </Button>
                </div>
            </form>
        </LayoutPanel>
    );
}
