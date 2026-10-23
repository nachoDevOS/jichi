import { FileCheck2, FileClock } from 'lucide-react';
import type { EnlaceDirecto } from '@/components/panel/comunes/ficha';
import { fechaHora } from '@/lib/utils';

/** El PDF guardado al emitirse, como lo manda `DocumentoEmitidoService::resumen()`. */
export interface PdfEmitido {
    generado_en: string;
    /** Los primeros 12 caracteres del SHA-256 del archivo. */
    huella: string;
    /** Para abrirlo directo de S3; null fuera de s3. */
    enlace: EnlaceDirecto | null;
}

/** La línea de la ficha que dice si el papel está guardado tal como salió. */
export function EstadoPdf({ pdf, aprobado }: { pdf: PdfEmitido | null; aprobado: boolean }) {
    if (!aprobado) {
        return null;
    }

    return pdf ? (
        <p className="flex items-start gap-2 text-xs text-muted-foreground">
            <FileCheck2 className="mt-0.5 size-4 shrink-0 text-primary" />
            <span>
                PDF guardado el {fechaHora(pdf.generado_en)}. Se reimprime tal como salió · huella{' '}
                <span className="font-mono">{pdf.huella}</span>
            </span>
        </p>
    ) : (
        <p className="flex items-start gap-2 text-xs text-muted-foreground">
            <FileClock className="mt-0.5 size-4 shrink-0" />
            Todavía sin PDF guardado: se guarda en la primera impresión.
        </p>
    );
}
