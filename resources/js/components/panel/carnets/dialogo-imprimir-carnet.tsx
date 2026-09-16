import { ExternalLink, IdCard, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';

/**
 * ============================================================================
 *  VISTA PREVIA ANTES DE IMPRIMIR EL CARNET
 * ============================================================================
 *
 * Muestra el carnet en pantalla y recién después lo manda a la impresora.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE SE MUESTRA ES EL PDF DE VERDAD, NO UNA MAQUETA
 * ----------------------------------------------------------------------------
 *
 * El `iframe` apunta a la MISMA dirección que imprime —
 * `GET /panel/carnets/{carnet}/imprimir`—, así que lo que se ve acá es
 * exactamente el archivo que va a salir de la impresora: mismos milímetros,
 * misma foto, mismo QR.
 *
 * Podría haberse dibujado con `VistaPreviaCarnet`, el componente que el
 * operador ya mira mientras carga el trámite, y habría sido más rápido. No se
 * hizo, y el motivo es el mismo por el que ese componente existe: una maqueta es
 * una PROMESA de cómo va a salir algo. Para decidir si mandar a imprimir un
 * plástico —que se troquela, se lamina y no se puede corregir— hace falta ver la
 * cosa, no la promesa. Si algún día la maqueta y el PDF se separan, este diálogo
 * es donde eso se tiene que notar.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ EL PDF SE PIDE RECIÉN AL ABRIR
 * ----------------------------------------------------------------------------
 *
 * El `iframe` se monta cuando `abierto` pasa a true y se desmonta al cerrar. Si
 * estuviera siempre en el árbol, cada visita a la ficha de un trámite le pediría
 * al servidor un PDF que nadie va a mirar — y armarlo no es gratis: lee la foto
 * del disco, dibuja el QR y embebe el fondo.
 *
 * ----------------------------------------------------------------------------
 *  IMPRIMIR NO SE DISPARA DESDE ACÁ
 * ----------------------------------------------------------------------------
 *
 * No hay un botón que llame a `print()` sobre el `iframe`: con un PDF, el visor
 * del navegador lo ignora o lo bloquea según la versión, y un botón que a veces
 * no hace nada es peor que no tenerlo.
 *
 * Se imprime desde donde siempre se imprimió un PDF: la barra del propio visor
 * —que se ve arriba del documento— o la pestaña aparte, que es lo que ofrece el
 * botón «Abrir en pestaña nueva».
 *
 * Y ABRIR ESTA VENTANA NO MARCA NADA. El expediente se declara impreso con su
 * propio botón, «Marcar impreso», porque mirar el carnet en pantalla no es
 * haberlo sacado en la impresora de credenciales.
 */
export function DialogoImprimirCarnet({
    abierto,
    carnetId,
    registro,
    onCerrar,
}: {
    abierto: boolean;
    carnetId: number;
    /** El número impreso en la tarjeta: 000013. Va en el título. */
    registro?: string;
    onCerrar: () => void;
}) {
    /*
     * Mientras el servidor arma el PDF el `iframe` está en blanco, y un
     * rectángulo vacío se lee como que algo falló. El aviso se dibuja DEBAJO y
     * el `iframe` lo tapa cuando termina de cargar.
     */
    const [cargando, setCargando] = useState(true);

    // Cada apertura vuelve a pedir el archivo, así que el aviso vuelve a cero.
    useEffect(() => {
        if (abierto) setCargando(true);
    }, [abierto]);

    /*
     * Escape cierra. El `return` del efecto es la LIMPIEZA: sin él, cada
     * apertura dejaría un listener más pegado al documento.
     */
    useEffect(() => {
        if (!abierto) return;

        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCerrar();
        };

        document.addEventListener('keydown', alPresionar);

        return () => document.removeEventListener('keydown', alPresionar);
    }, [abierto, onCerrar]);

    if (!abierto) return null;

    const url = route('carnets.imprimir', carnetId);

    /*
     * EL FRAGMENTO ES PARA EL VISOR DEL NAVEGADOR, NO PARA EL SERVIDOR.
     *
     * Lo que va después del `#` no se manda en la petición, así que esto no
     * cambia el PDF: le pide al visor que entre la tarjeta ENTERA en la ventana
     * y que no abra el panel de miniaturas.
     *
     * `Fit` y no `FitH`: al ancho, Chrome amplía al 229% y la tarjeta se corta
     * abajo — se pierden justamente el renglón del registro y la cédula, que es
     * lo que uno viene a revisar. Y sin fragmento abre la miniatura al costado
     * —de una sola página— y dibuja el carnet al 100%, tan chico que no se leen
     * los renglones.
     *
     * Son parámetros de visor, no una norma: el que no los entienda los ignora
     * y muestra el PDF igual.
     */
    const urlVisor = `${url}#view=Fit&navpanes=0`;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-vista-previa-carnet"
        >
            <div className="flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                <div className="flex items-start justify-between gap-4 border-b border-border p-4">
                    <div className="min-w-0">
                        <h2
                            id="titulo-vista-previa-carnet"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <IdCard className="size-4 shrink-0" />
                            Carnet {registro ?? ''}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Así va a salir impreso. Revíselo antes de mandarlo a la impresora de
                            credenciales.
                        </p>
                    </div>

                    <Button variant="ghost" size="icon" onClick={onCerrar} aria-label="Cerrar">
                        <X className="size-4" />
                    </Button>
                </div>

                {/*
                    El alto se da en `vh` y no con la proporción de la tarjeta:
                    lo que ocupa el visor del navegador —su barra de
                    herramientas, su fondo gris— no lo controla esta pantalla.
                */}
                <div className="relative min-h-0 flex-1 bg-muted">
                    {cargando && (
                        <p className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                            Generando el carnet…
                        </p>
                    )}

                    <iframe
                        src={urlVisor}
                        title="Vista previa del carnet"
                        className="relative h-[60vh] w-full"
                        onLoad={() => setCargando(false)}
                    />
                </div>

                <div className="flex flex-wrap justify-end gap-2 border-t border-border p-4">
                    <Button variant="outline" onClick={onCerrar}>
                        Cerrar
                    </Button>

                    {/*
                        Va como <a> y no con router.visit(): abre un archivo, y
                        una navegación de Inertia no sabe qué hacer con eso.
                    */}
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener"
                        className={buttonVariants({ variant: 'dorado' })}
                    >
                        <ExternalLink className="size-4" />
                        Abrir en pestaña nueva
                    </a>
                </div>
            </div>
        </div>
    );
}
