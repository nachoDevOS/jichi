import { FlaskConical } from 'lucide-react';

/**
 * Cartel fijo que avisa que este módulo todavía no guarda nada.
 *
 * Existe por una razón concreta: las pantallas de Trámites se ven terminadas,
 * y sin este cartel un operador podría cargar una faena real, apretar guardar,
 * ver un mensaje verde y creer que quedó registrada. Mientras el módulo sea
 * una maqueta, tiene que decirlo en la cara.
 *
 * Cuando el módulo se construya de verdad, se borra este componente y la prop
 * `esMaqueta` del controlador.
 */
export function AvisoMaqueta({ children }: { children?: React.ReactNode }) {
    return (
        <div className="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4">
            <FlaskConical className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />

            <div className="space-y-1 text-sm">
                <p className="font-semibold text-amber-900 dark:text-amber-200">
                    Maqueta de interfaz — todavía no se guarda nada
                </p>
                <p className="text-amber-900/80 dark:text-amber-200/80">
                    {children ??
                        'Esta pantalla muestra cómo se vería el módulo. Los datos que se carguen acá no se escriben en la base de datos.'}
                </p>
            </div>
        </div>
    );
}
