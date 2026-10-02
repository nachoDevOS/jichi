import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { PageProps } from '@/types';

/**
 * Convierte los mensajes flash de Laravel en toasts. Se monta una sola vez
 * en el layout; cada visita de Inertia trae un objeto flash nuevo.
 */
export function useFlash() {
    const { flash } = usePage<PageProps>().props;

    useEffect(() => {
        if (flash?.exito) toast.success(flash.exito);
        if (flash?.error) toast.error(flash.error, { duration: 10000 }); // el motivo es largo: 4 s no alcanzan
        if (flash?.info) toast.info(flash.info);
    }, [flash]);
}
