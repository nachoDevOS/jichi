import { Eye, LoaderCircle, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 *  La vista previa «NO VÁLIDO» de un trámite abierto
 *
 *  El PDF se dibuja en <canvas> con pdf.js, no en el visor del navegador: ese
 *  trae botones de descargar e imprimir, y en Android directamente baja el
 *  archivo. Sin clic derecho y oculto al imprimir la página. Es comodidad: lo que
 *  protege de verdad es la marca que ya viene horneada en el PDF.
 */
export function VisorVistaPrevia({ url, titulo, onCerrar }: { url: string; titulo: string; onCerrar: () => void }) {
    const contenedor = useRef<HTMLDivElement>(null);
    const [estado, setEstado] = useState<'cargando' | 'listo' | 'error'>('cargando');

    useEffect(() => {
        let cancelado = false;
        // Si se cierra antes de terminar, se corta la descarga en vez de dejarla colgada.
        let cancelar: (() => void) | null = null;
        const destino = contenedor.current;

        (async () => {
            try {
                // pdf.js pesa: se baja recién la primera vez que alguien abre una vista previa.
                const pdfjs = await import('pdfjs-dist');
                const trabajador = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
                pdfjs.GlobalWorkerOptions.workerSrc = trabajador.default;

                const carga = pdfjs.getDocument({
                    url,
                    withCredentials: true,
                    disableRange: true,
                    disableStream: true,
                });
                cancelar = () => void carga.destroy();
                const documento = await carga.promise;
                if (cancelado || !destino) return;
                destino.replaceChildren();

                for (let n = 1; n <= documento.numPages; n++) {
                    const pagina = await documento.getPage(n);
                    // Nítido en pantallas de alta densidad, sin pasarse de memoria en el celular.
                    const escala = Math.min(2, window.devicePixelRatio || 1) * 1.5;
                    const vista = pagina.getViewport({ scale: escala });
                    const lienzo = document.createElement('canvas');
                    lienzo.width = vista.width;
                    lienzo.height = vista.height;
                    lienzo.className = 'block h-auto w-full rounded-lg bg-white shadow-md';
                    lienzo.oncontextmenu = (e) => e.preventDefault();
                    destino.appendChild(lienzo);
                    await pagina.render({ canvas: lienzo, viewport: vista }).promise;
                    if (cancelado) return;
                }

                setEstado('listo');
            } catch {
                if (!cancelado) setEstado('error');
            }
        })();

        return () => {
            cancelado = true;
            cancelar?.();
        };
    }, [url]);

    useEffect(() => {
        const alTeclear = (e: KeyboardEvent) => e.key === 'Escape' && onCerrar();
        document.addEventListener('keydown', alTeclear);
        const desborde = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', alTeclear);
            document.body.style.overflow = desborde;
        };
    }, [onCerrar]);

    return (
        <div
            className="sin-imprimir fixed inset-0 z-50 flex items-end justify-center bg-slate-900/70 backdrop-blur-sm sm:items-center sm:p-4"
            onClick={onCerrar}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label={`Vista previa: ${titulo}`}
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-3xl bg-slate-100 shadow-2xl sm:max-w-2xl sm:rounded-3xl"
            >
                <div className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <Eye className="size-5 shrink-0 text-rio" />
                    <div className="min-w-0 flex-1 leading-tight">
                        <p className="truncate text-sm font-bold text-rio-profundo">{titulo}</p>
                        <p className="text-xs text-slate-500">Vista previa · no válida hasta que se apruebe</p>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        aria-label="Cerrar"
                        className="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                    >
                        <X className="size-5" />
                    </button>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto p-3 select-none sm:p-4">
                    {estado === 'cargando' && (
                        <p className="flex items-center justify-center gap-2 py-16 text-sm text-slate-500">
                            <LoaderCircle className="size-5 animate-spin" />
                            Preparando la vista previa…
                        </p>
                    )}
                    {estado === 'error' && (
                        <p className="flex items-center justify-center gap-2 py-16 text-sm text-rose-700">
                            <TriangleAlert className="size-5" />
                            No se pudo mostrar la vista previa. Intente de nuevo.
                        </p>
                    )}
                    <div ref={contenedor} className="space-y-3" />
                </div>
            </div>
        </div>
    );
}
