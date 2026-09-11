import { useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Caja para escribir a mano el código de verificación.
 *
 * Lo normal es llegar acá escaneando el QR, que ya trae el código en la URL.
 * Este formulario es el plan B: cuando el QR está borroso, mojado o el
 * teléfono no tiene cámara.
 *
 * Al enviar hace POST a /verificar y el controlador redirige a
 * /verificar/{codigo}. Podría haber sido un GET, pero con POST el código no
 * queda en el historial del navegador de una computadora compartida.
 */
export function BuscadorCodigo({ codigoInicial }: { codigoInicial: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        codigo: codigoInicial ?? '',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        post(route('verificar.buscar'));
    }

    return (
        <>
            {/* Sin margen inferior propio: quien lo usa decide la separación.
                Este buscador vive en dos sitios muy distintos —dentro de la
                hoja blanca y sobre el fondo verde, debajo del acta— y un
                margen fijo acá dejaba un hueco raro en uno de los dos. */}
            <form onSubmit={enviar} className="mx-auto flex max-w-md gap-2">
                <Input
                    value={data.codigo}
                    // Los códigos se guardan en mayúsculas: se convierte mientras
                    // se escribe para que no falle por escribirlo en minúscula.
                    onChange={(e) => setData('codigo', e.target.value.toUpperCase())}
                    placeholder="Ej. 4K7RJ2MXP9TQ3WHB"
                    /*
                     * Dieciséis, que es el largo exacto del código.
                     *
                     * El tope es generoso a propósito —24 y no 16— porque el
                     * código se imprime en grupos para poder leerlo, y quien
                     * copia de la credencial escribe los espacios o los
                     * guiones. Cortarle la mano al llegar a 16 le comería el
                     * final del código sin decirle por qué; el servidor
                     * limpia esos separadores antes de validar.
                     */
                    maxLength={24}
                    aria-label="Código de verificación"
                    aria-invalid={Boolean(errors.codigo)}
                    // font-mono: en monoespaciado no se confunden 0 con O ni 1 con l.
                    className="font-mono tracking-wider"
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
