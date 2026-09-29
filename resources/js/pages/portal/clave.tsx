import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { CampoPortal } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';

/**
 * Cambiar la contraseña. Con la clave temporal de ventanilla es obligatorio y no
 * hay menú: el middleware no deja ir a ningún otro lado hasta hacerlo.
 */
export default function Clave({ obligatorio }: { obligatorio: boolean }) {
    const form = useForm({ actual: '', password: '', password_confirmation: '' });

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('portal.clave.update'), { onFinish: () => form.reset() });
    };

    return (
        <LayoutPortal titulo="Cambiar contraseña" sinMenu={obligatorio}>
            <Head title="Cambiar contraseña" />

            {obligatorio && (
                <p className="mb-5 flex gap-3 rounded-2xl bg-sky-50 p-4 text-sm text-sky-900">
                    <ShieldCheck className="mt-0.5 size-5 shrink-0" />
                    <span>
                        Está usando la contraseña que le dieron en ventanilla. Por su seguridad, elija una propia antes
                        de continuar.
                    </span>
                </p>
            )}

            <form onSubmit={enviar} className="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                <CampoPortal
                    etiqueta={obligatorio ? 'Contraseña de ventanilla' : 'Contraseña actual'}
                    type="password"
                    autoComplete="current-password"
                    value={form.data.actual}
                    onChange={(e) => form.setData('actual', e.target.value)}
                    error={form.errors.actual}
                />
                <CampoPortal
                    etiqueta="Contraseña nueva"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                    ayuda="Al menos 8 caracteres, con letras y números."
                />
                <CampoPortal
                    etiqueta="Repita la contraseña nueva"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                />

                <button
                    type="submit"
                    disabled={form.processing}
                    className="w-full rounded-xl bg-rio px-4 py-3 font-semibold text-white shadow-sm transition-colors hover:bg-rio-profundo disabled:opacity-60"
                >
                    Guardar contraseña
                </button>
            </form>
        </LayoutPortal>
    );
}
