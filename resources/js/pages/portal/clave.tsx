import { Head, useForm } from '@inertiajs/react';
import { Check, KeyRound, LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { CampoPortal } from '@/components/portal/piezas';
import LayoutPortal from '@/layouts/layout-portal';
import { cn } from '@/lib/utils';

/**
 * Cambiar la contraseña. Con la clave temporal de ventanilla es obligatorio y no
 * hay menú: el middleware no deja ir a ningún otro lado hasta hacerlo. Pocas
 * palabras y letra grande: la usa gente que casi no usa el celular.
 */
export default function Clave({ obligatorio }: { obligatorio: boolean }) {
    const form = useForm({ actual: '', password: '', password_confirmation: '' });
    const { actual, password, password_confirmation: repetida } = form.data;

    // Las de CambiarClaveRequest, dichas simple. «Distinta de la actual» la avisa el servidor.
    const reglas = [
        { ok: password.length >= 8, texto: 'Que tenga 8 o más letras o números' },
        { ok: /\p{L}/u.test(password) && /\d/.test(password) && /^[\p{L}\p{N}]+$/u.test(password), texto: 'Letras y números, sin signos' },
        { ok: repetida !== '' && repetida === password, texto: 'Que las dos sean iguales' },
    ];
    const lista = actual !== '' && reglas.every((r) => r.ok);

    const enviar = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('portal.clave.update'), { onFinish: () => form.reset() });
    };

    return (
        <LayoutPortal titulo="Cambiar contraseña" sinMenu={obligatorio}>
            <Head title="Cambiar contraseña" />

            <form onSubmit={enviar} className="mx-auto max-w-md rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                <div className="flex items-center gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rio-espuma text-rio">
                        <KeyRound className="size-5" />
                    </span>
                    <p className="leading-snug text-slate-700">
                        {obligatorio
                            ? 'Es su primera vez: cambie la contraseña de ventanilla por una suya.'
                            : 'Escriba su contraseña de ahora y después la nueva.'}
                    </p>
                </div>

                <div className="mt-4 space-y-3">
                    <CampoPortal
                        etiqueta={obligatorio ? '1. La contraseña que le dieron en ventanilla' : '1. Su contraseña de ahora'}
                        type="password"
                        autoComplete="current-password"
                        value={actual}
                        onChange={(e) => form.setData('actual', e.target.value)}
                        error={form.errors.actual}
                        autoFocus
                    />
                    <CampoPortal
                        etiqueta="2. Su contraseña nueva"
                        type="password"
                        autoComplete="new-password"
                        value={password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        error={form.errors.password}
                    />
                    <CampoPortal
                        etiqueta="3. Escriba otra vez la nueva"
                        type="password"
                        autoComplete="new-password"
                        value={repetida}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    />
                </div>

                {/* Se tildan solas mientras escribe: no tiene que adivinar qué falta. */}
                <ul className="mt-4 space-y-1.5">
                    {reglas.map((r) => (
                        <li key={r.texto} className={cn('flex items-center gap-2 text-sm', r.ok ? 'font-semibold text-emerald-700' : 'text-slate-500')}>
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors',
                                    r.ok ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 text-transparent',
                                )}
                            >
                                <Check className="size-3" strokeWidth={3} />
                            </span>
                            {r.texto}
                        </li>
                    ))}
                </ul>

                <button
                    type="submit"
                    disabled={form.processing || !lista}
                    className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-rio px-4 py-3 text-base font-bold text-white shadow-lg shadow-rio/25 transition-colors hover:bg-rio-profundo disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
                >
                    {form.processing && <LoaderCircle className="size-5 animate-spin" />}
                    {form.processing ? 'Guardando…' : 'Guardar'}
                </button>
            </form>
        </LayoutPortal>
    );
}
