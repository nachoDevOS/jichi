import { useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

// 16 caracteres en grupos de cuatro, igual que va impreso: «EFGT-96R4-CJ42-AHYJ».
const LARGO_CODIGO = 16;

/** Deja solo letras y números, en mayúsculas, y pone el guion cada cuatro. Sirve también al pegar. */
function formatearCodigo(valor: string): string {
    const limpio = valor
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, LARGO_CODIGO);

    return limpio.match(/.{1,4}/g)?.join('-') ?? '';
}

/**
 * Caja para escribir a mano el código de un documento.
 */
export function BuscadorCodigo({ codigoInicial }: { codigoInicial?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        codigo: formatearCodigo(codigoInicial ?? ''),
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        post(route('verificar.buscar'));
    }

    return (
        <>
            {/* Sin margen inferior propio: quien lo usa decide la separación.
                Este buscador vive en dos sitios muy distintos —dentro de la hoja
                blanca y sobre el fondo verde, debajo del acta— y un margen fijo
                acá dejaba un hueco raro en uno de los dos. */}
            <form onSubmit={enviar} className="mx-auto flex justify-center gap-2">
                <Input
                    value={data.codigo}
                    // Los guiones los pone la caja: quien copia escribe solo los 16 caracteres.
                    onChange={(e) => setData('codigo', formatearCodigo(e.target.value))}
                    placeholder="XXXX-XXXX-XXXX-XXXX"
                    // Sin maxLength: cortaría un código pegado con espacios antes de limpiarlo.
                    autoCapitalize="characters"
                    autoComplete="off"
                    spellCheck={false}
                    aria-label="Código del documento"
                    aria-invalid={Boolean(errors.codigo)}
                    // font-mono: no se confunden 0 con O. 26ch = los 19 caracteres con guiones, el espaciado y el relleno.
                    className="w-[26ch] flex-none text-center font-mono tracking-wider"
                />

                <Button type="submit" disabled={processing}>
                    {processing ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Search className="size-4" />
                    )}
                    Verificar
                </Button>
            </form>

            {errors.codigo && (
                <p className="mt-3 text-center text-sm text-destructive" role="alert">
                    {errors.codigo}
                </p>
            )}
        </>
    );
}
