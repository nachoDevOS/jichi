import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';

type Tramite = 'autorizacion' | 'carnet_pescador' | 'carnet_comercializador' | 'faena' | 'guia';
type Modo = 'fin_de_anio' | 'dias';

interface VigenciaTramite {
    tramite: Tramite;
    etiqueta: string;
    modo: Modo;
    dias: number;
    /** Lo que dicen hoy las pantallas de emisión: «30 días desde la aprobación». */
    texto: string;
}

type Formulario = Record<Tramite, { modo: Modo; dias: string }>;

/**
 *  Hasta cuándo vale cada trámite que se aprueba
 */
export default function Vigencias({
    tramites,
    modos,
}: {
    tramites: VigenciaTramite[];
    modos: { value: Modo; label: string }[];
}) {
    const { puede } = usePermisos();
    const editable = puede('configuracion.editar');

    const form = useForm<Formulario>(
        Object.fromEntries(tramites.map((t) => [t.tramite, { modo: t.modo, dias: String(t.dias) }])) as Formulario,
    );
    const errores = form.errors as Record<string, string | undefined>;

    function cambiar(tramite: Tramite, campo: 'modo' | 'dias', valor: string) {
        form.setData(tramite, { ...form.data[tramite], [campo]: valor });
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('vigencias.update'), { preserveScroll: true });
    }

    return (
        <LayoutPanel titulo="Vigencias" descripcion="Hasta cuándo vale cada trámite que se aprueba.">
            <Head title="Vigencias" />

            <form onSubmit={enviar} className="space-y-6">
                <p className="flex items-start gap-2 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    Rige para lo que se apruebe desde que se guarda. Lo ya aprobado conserva la fecha de
                    vencimiento que quedó en su papel.
                </p>

                <div className="grid gap-6 lg:grid-cols-3">
                    {tramites.map((t) => {
                        const datos = form.data[t.tramite];

                        return (
                            <Card key={t.tramite}>
                                <CardHeader>
                                    <CardTitle>{t.etiqueta}</CardTitle>
                                    <p className="text-sm text-muted-foreground">Hoy: vale {t.texto}.</p>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    <Campo
                                        etiqueta="Vence"
                                        htmlFor={`${t.tramite}-modo`}
                                        error={errores[`${t.tramite}.modo`]}
                                    >
                                        <Select
                                            id={`${t.tramite}-modo`}
                                            value={datos.modo}
                                            onChange={(e) => cambiar(t.tramite, 'modo', e.target.value)}
                                            disabled={!editable}
                                        >
                                            {modos.map((m) => (
                                                <option key={m.value} value={m.value}>
                                                    {m.label}
                                                </option>
                                            ))}
                                        </Select>
                                    </Campo>

                                    {datos.modo === 'dias' && (
                                        <Campo
                                            etiqueta="Días desde la aprobación"
                                            htmlFor={`${t.tramite}-dias`}
                                            error={errores[`${t.tramite}.dias`]}
                                            obligatorio
                                        >
                                            <Input
                                                id={`${t.tramite}-dias`}
                                                type="number"
                                                min={1}
                                                max={3650}
                                                step={1}
                                                value={datos.dias}
                                                onChange={(e) => cambiar(t.tramite, 'dias', e.target.value)}
                                                disabled={!editable}
                                                aria-invalid={Boolean(errores[`${t.tramite}.dias`])}
                                                className="sm:max-w-40"
                                            />
                                        </Campo>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {editable && (
                    <Button type="submit" disabled={form.processing}>
                        Guardar vigencias
                    </Button>
                )}
            </form>
        </LayoutPanel>
    );
}
