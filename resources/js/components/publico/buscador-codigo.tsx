import { useForm } from '@inertiajs/react';
import { LoaderCircle, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Caja para escribir a mano el código del carnet.
 *
 * Lo normal es llegar acá escaneando el QR, que ya lo trae en la URL. Este
 * formulario es el plan B: cuando el QR está borroso, mojado o el teléfono no
 * tiene cámara.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO CAMPO, Y ES LA LLAVE ENTERA
 * ----------------------------------------------------------------------------
 *
 * El carnet se identifica por su `codigo_carnet`, que es único global y va
 * impreso en el plástico. Es lo único que hay que saber para consultarlo.
 *
 * Que esté impreso significa que no es un secreto, así que lo que protege del
 * barrido automático NO es el código sino el límite de intentos por minuto de
 * la ruta. Por eso conviene que el código lleve una parte al azar al generarse:
 * uno correlativo se recorre entero probando de 1 en adelante.
 *
 * Al enviar hace POST a /verificar y el controlador redirige a /verificar/{codigo}.
 * Podría haber sido un GET, pero con POST el dato no queda en el historial del
 * navegador de una computadora compartida.
 */
export function BuscadorCodigo({ codigoInicial }: { codigoInicial?: string | null }) {
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
                Este buscador vive en dos sitios muy distintos —dentro de la hoja
                blanca y sobre el fondo verde, debajo del acta— y un margen fijo
                acá dejaba un hueco raro en uno de los dos. */}
            <form onSubmit={enviar} className="mx-auto flex max-w-md gap-2">
                <Input
                    value={data.codigo}
                    // Se guarda en mayúsculas: se convierte mientras se escribe
                    // para que no falle por tipearlo en minúscula, que es como
                    // arranca el teclado del teléfono.
                    onChange={(e) => setData('codigo', e.target.value.toUpperCase())}
                    placeholder="Código del carnet — ej. PES2 6000 0017"
                    /*
                     * El tope es generoso a propósito: el código se imprime en
                     * grupos de cuatro para poder leerlo, y quien lo copia
                     * escribe los espacios o los guiones. Cortarle la mano al
                     * llegar al largo exacto le comería el final sin decirle por
                     * qué; el servidor limpia los separadores antes de validar.
                     */
                    maxLength={50}
                    aria-label="Código del carnet"
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
