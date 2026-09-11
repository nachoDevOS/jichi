import { Head, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Lock, Mail } from 'lucide-react';
import type { FormEvent } from 'react';
import { LogoJichi } from '@/components/comunes/logo-jichi';
import { ToggleApariencia } from '@/components/comunes/toggle-apariencia';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageProps } from '@/types';

export default function Login() {
    const { institucion } = usePage<PageProps>().props;

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    }

    return (
        <>
            <Head title="Iniciar sesión" />

            <div className="grid min-h-screen lg:grid-cols-2">
                {/* Panel institucional */}
                <div className="relative hidden flex-col justify-between bg-primary p-10 text-primary-foreground lg:flex">
                    <div className="flex items-center gap-3">
                        <LogoJichi className="size-10" />
                        <span className="text-lg font-bold tracking-tight">Jichi</span>
                    </div>

                    <div className="max-w-md space-y-4">
                        <h2 className="text-3xl font-semibold leading-tight">
                            Recaudación, certificación y credenciales del sector pesquero
                        </h2>
                        {/* El nombre de la institución ya termina en «del Beni»:
                            repetir «departamento del Beni» detrás, como estaba
                            cuando la institución era municipal, quedaba
                            duplicado. */}
                        <p className="text-sm opacity-80">
                            Plataforma institucional del{' '}
                            {institucion?.municipio ?? 'Gobierno Autónomo Departamental del Beni'}.
                        </p>
                    </div>

                    <p className="text-xs opacity-60">
                        Uso exclusivo de personal autorizado. Todos los accesos quedan registrados.
                    </p>
                </div>

                {/* Formulario */}
                <div className="flex flex-col items-center justify-center px-6 py-12">
                    <div className="absolute right-4 top-4">
                        <ToggleApariencia />
                    </div>

                    <div className="w-full max-w-sm">
                        <div className="mb-8 flex flex-col items-center gap-3 lg:hidden">
                            <LogoJichi className="size-12 text-primary" />
                            <span className="text-xl font-bold tracking-tight">Jichi</span>
                        </div>

                        <div className="mb-6 space-y-1">
                            <h1 className="text-2xl font-semibold tracking-tight">Iniciar sesión</h1>
                            <p className="text-sm text-muted-foreground">
                                Ingrese con su correo institucional.
                            </p>
                        </div>

                        <form onSubmit={enviar} className="space-y-4" noValidate>
                            <div className="space-y-2">
                                <Label htmlFor="email">Correo institucional</Label>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        aria-invalid={Boolean(errors.email)}
                                        className="pl-9"
                                        placeholder="admin@admin.com"
                                    />
                                </div>
                                {errors.email && (
                                    <p className="text-sm text-destructive" role="alert">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
                                    <Input
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        autoComplete="current-password"
                                        required
                                        aria-invalid={Boolean(errors.password)}
                                        className="pl-9"
                                        placeholder="••••••••"
                                    />
                                </div>
                                {errors.password && (
                                    <p className="text-sm text-destructive" role="alert">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="size-4 rounded border-input accent-primary"
                                />
                                Mantener la sesión iniciada
                            </label>

                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing && <LoaderCircle className="size-4 animate-spin" />}
                                Ingresar
                            </Button>
                        </form>

                        <p className="mt-8 text-center text-xs text-muted-foreground">
                            ¿Problemas para ingresar? Contacte a la Unidad de Sistemas de la Gobernación.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
