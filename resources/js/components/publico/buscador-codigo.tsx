import { useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Caja para escribir a mano la firma del carnet.
 *
 * Lo normal es llegar acá escaneando el QR, que ya la trae en la URL. Este
 * formulario es el plan B: cuando el QR está borroso, mojado o el teléfono no
 * tiene cámara.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO CAMPO, Y ES LA LLAVE ENTERA
 * ----------------------------------------------------------------------------
 *
 * El carnet no tiene número: se identifica por su firma de validación, dieciséis
 * caracteres alfanuméricos generados al azar. Antes eran dos campos —un código
 * público y esta firma— porque el código era predecible y hacía falta un segundo
 * dato que lo protegiera; al retirarse el código, la firma cumple los dos
 * papeles.
 *
 * Eso funciona porque es IMPREDECIBLE: 16 caracteres alfanuméricos son ~8 · 10^24
 * combinaciones, y la ruta limita a 20 intentos por minuto.
 *
 * Al enviar hace POST a /verificar y el controlador redirige a /verificar/{firma}.
 * Podría haber sido un GET, pero con POST el dato no queda en el historial del
 * navegador de una computadora compartida.
 */
export function BuscadorCodigo({ firmaInicial }: { firmaInicial?: string | null }) {
    const { data, setData, post, processing, errors } = useForm({
        firma: firmaInicial ?? '',
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
            <form onSubmit={enviar} className="mx-auto flex max-w-md gap-2">
                <Input
                    value={data.firma}
                    // Se guarda en mayúsculas: se convierte mientras se escribe
                    // para que no falle por tipearla en minúscula, que es como
                    // arranca el teclado del teléfono.
                    onChange={(e) => setData('firma', e.target.value.toUpperCase())}
                    placeholder="Firma del carnet — ej. 4K7R J2MX P9TQ 3WHB"
                    /*
                     * Dieciséis es el largo exacto, pero el tope es generoso a
                     * propósito —24 y no 16— porque la firma se imprime en grupos
                     * para poder leerla, y quien la copia escribe los espacios o
                     * los guiones. Cortarle la mano al llegar a 16 le comería el
                     * final sin decirle por qué; el servidor limpia los
                     * separadores antes de validar.
                     */
                    maxLength={24}
                    aria-label="Firma de validación del carnet"
                    aria-invalid={Boolean(errors.firma)}
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

            {errors.firma && (
                <p className="mt-3 text-center text-sm text-destructive" role="alert">
                    {errors.firma}
                </p>
            )}
        </>
    );
}
