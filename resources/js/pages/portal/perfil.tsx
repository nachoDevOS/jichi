import { Head, Link } from '@inertiajs/react';
import { Fish, IdCard, Info, KeyRound, MapPin, ShieldCheck, Truck, User } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { Dato } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { fecha, fechaHora, hace } from '@/lib/utils';
import type { ActividadPortal, CuentaPortal, DatosPortal } from '@/types/portal';

/**
 * Sus datos, para leer. Si algo está mal se corrige en ventanilla con la cédula:
 * así el padrón no se llena de cambios que nadie verificó.
 */
export default function Perfil({
    datos,
    actividades,
    cuenta,
}: {
    datos: DatosPortal;
    actividades: ActividadPortal[];
    cuenta: CuentaPortal;
}) {
    const capitalizar = (t: string | null) => (t ? t.charAt(0).toUpperCase() + t.slice(1) : null);

    return (
        <LayoutPortal titulo="Mis datos">
            <Head title="Mis datos" />

            <div className="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm">
                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-rio-espuma text-rio ring-4 ring-rio-espuma">
                    {datos.foto_url ? (
                        <img src={datos.foto_url} alt={datos.nombre} className="size-full object-cover" />
                    ) : (
                        <User className="size-8" />
                    )}
                </div>
                <div className="min-w-0">
                    <p className="text-lg leading-tight font-bold text-rio-profundo">{datos.nombre}</p>
                    <p className="mt-1 text-sm text-slate-500">C.I. {datos.documento_identidad}</p>
                    {datos.registrado && (
                        <p className="text-xs text-slate-400">En el padrón desde {fecha(datos.registrado)}</p>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <Bloque titulo="Identidad" icono={<IdCard className="size-4.5" />}>
                    <Dato rotulo="Nombres" ancho>
                        {datos.nombres}
                    </Dato>
                    <Dato rotulo="Apellido paterno">{datos.apellido_paterno}</Dato>
                    <Dato rotulo="Apellido materno">{datos.apellido_materno}</Dato>
                    {datos.apellido_casado && (
                        <Dato rotulo="Apellido de casada" ancho>
                            {datos.apellido_casado}
                        </Dato>
                    )}
                    <Dato rotulo="Cédula de identidad">{datos.documento_identidad}</Dato>
                    <Dato rotulo="Expedida en">{datos.expedido}</Dato>
                    <Dato rotulo="Nacimiento">
                        {datos.fecha_nacimiento &&
                            `${fecha(datos.fecha_nacimiento)}${datos.edad !== null ? ` · ${datos.edad} años` : ''}`}
                    </Dato>
                    <Dato rotulo="Género">{capitalizar(datos.genero)}</Dato>
                    <Dato rotulo="Nacionalidad" ancho>
                        {datos.nacionalidad}
                    </Dato>
                </Bloque>

                <Bloque titulo="Contacto y domicilio" icono={<MapPin className="size-4.5" />}>
                    <Dato rotulo="Teléfono">{datos.telefono}</Dato>
                    <Dato rotulo="Correo" ancho>
                        {datos.email}
                    </Dato>
                    <Dato rotulo="Dirección" ancho>
                        {datos.direccion}
                    </Dato>
                    <Dato rotulo="Ciudad">{datos.ciudad}</Dato>
                    <Dato rotulo="Provincia">{datos.provincia}</Dato>
                </Bloque>

                <Bloque titulo="Mis actividades" icono={<Fish className="size-4.5" />}>
                    {actividades.length === 0 ? (
                        <p className="col-span-2 text-sm text-slate-500">
                            Hoy no tiene un carnet vigente. Sin carnet no puede pescar ni comercializar.
                        </p>
                    ) : (
                        actividades.map((a) => (
                            <div key={a.actividad} className="col-span-2 flex gap-3 rounded-xl bg-rio-espuma p-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-rio">
                                    {a.actividad === 'Pescador' ? (
                                        <Fish className="size-5" />
                                    ) : (
                                        <Truck className="size-5" />
                                    )}
                                </span>
                                <div className="min-w-0 text-sm">
                                    <p className="font-bold text-rio-profundo">{a.actividad}</p>
                                    <p className="text-slate-600">{a.asociacion ?? 'Sin asociación'}</p>
                                    <p className="text-xs text-slate-500">Carnet vigente hasta {fecha(a.vence)}</p>
                                </div>
                            </div>
                        ))
                    )}
                </Bloque>

                <Bloque titulo="Mi cuenta" icono={<ShieldCheck className="size-4.5" />}>
                    <Dato rotulo="Usuario">Su cédula</Dato>
                    <Dato rotulo="Cuenta creada">{fecha(cuenta.creada)}</Dato>
                    <Dato rotulo="Último ingreso" ancho>
                        {cuenta.ultimo_acceso && `${fechaHora(cuenta.ultimo_acceso)} · ${hace(cuenta.ultimo_acceso)}`}
                    </Dato>
                    <Link
                        href={route('portal.clave.edit')}
                        className="col-span-2 mt-1 flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white p-3 text-sm font-semibold text-rio-profundo hover:bg-slate-50"
                    >
                        <KeyRound className="size-4" />
                        Cambiar mi contraseña
                    </Link>
                </Bloque>
            </div>

            <p className="mt-4 flex gap-2 rounded-2xl bg-sky-50 p-4 text-sm text-sky-900">
                <Info className="mt-0.5 size-4 shrink-0" />
                ¿Algún dato está mal? Acérquese a ventanilla con su cédula de identidad para corregirlo.
            </p>
        </LayoutPortal>
    );
}

function Bloque({ titulo, icono, children }: PropsWithChildren<{ titulo: string; icono: ReactNode }>) {
    return (
        <section className="rounded-2xl bg-white p-5 shadow-sm">
            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold tracking-wide text-rio-profundo uppercase">
                <span className="text-rio">{icono}</span>
                {titulo}
            </h2>
            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">{children}</dl>
        </section>
    );
}
