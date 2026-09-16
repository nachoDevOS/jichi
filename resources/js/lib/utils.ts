import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Formatea un monto en bolivianos: 1.250,50 Bs
 * Se usa el locale es-BO para que el separador de miles sea el punto.
 */
export function bs(monto: number | string | null | undefined, simbolo = 'Bs'): string {
    const valor = typeof monto === 'string' ? Number.parseFloat(monto) : (monto ?? 0);

    return `${new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(valor) ? valor : 0)} ${simbolo}`;
}

/**
 * ============================================================================
 *  UNA FECHA SUELTA NO ES UN INSTANTE, Y CONFUNDIRLOS RESTA UN DÍA
 * ============================================================================
 *
 * `new Date('2026-12-31')` NO da el 31 de diciembre en Bolivia. El estándar
 * manda interpretar una cadena `AAAA-MM-DD` como medianoche UTC, y Bolivia está
 * en UTC-4: esa medianoche es todavía el 30 de diciembre a las 20:00 hora local.
 * Al formatear en horario local, la pantalla muestra **30/12/2026**.
 *
 * No es un detalle cosmético. Con esa resta:
 *
 *   - el vencimiento de TODOS los carnets se mostraba un día antes,
 *   - una fecha de nacimiento del 1 de enero saltaba al año anterior,
 *   - la edad calculada se equivocaba el día del cumpleaños.
 *
 * La distinción que hay que hacer es entre dos cosas distintas:
 *
 *   FECHA SUELTA      '2026-12-31'                 -> un día del calendario.
 *                                                     No tiene hora ni zona: el
 *                                                     31 de diciembre es el 31
 *                                                     en todo el mundo.
 *
 *   INSTANTE          '2026-12-31T14:30:00-04:00'  -> un momento exacto. Sí
 *                                                     tiene zona, y convertirlo
 *                                                     a hora local es correcto.
 *
 * Por eso la cadena de solo fecha se arma con `new Date(año, mes, día)`, que
 * construye el día en horario LOCAL y no se corre. Lo que trae hora se deja
 * pasar tal cual, porque ahí la conversión sí corresponde.
 */
function aFechaLocal(valor: string | Date): Date {
    if (valor instanceof Date) {
        return valor;
    }

    const soloFecha = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);

    if (soloFecha) {
        const [, anio, mes, dia] = soloFecha;

        // El mes va de 0 a 11 en JavaScript: marzo es el 2, no el 3.
        return new Date(Number(anio), Number(mes) - 1, Number(dia));
    }

    return new Date(valor);
}

export function fecha(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(aFechaLocal(valor));
}

/**
 * Años CUMPLIDOS a partir de una fecha de nacimiento.
 *
 * Vive acá y no dentro de un componente porque la usa el formulario para la
 * vista previa y podría usarla cualquier otra pantalla. OJO: es un ESPEJO de
 * `Beneficiario::edad()` en PHP, y existe solo para poder mostrar la edad
 * mientras el operador escribe, sin una petición al servidor por cada tecla. El
 * valor que se guarda y el que se imprime salen siempre de PHP.
 *
 * No se hace una resta de años a secas —`hoy.getFullYear() - nacio.getFullYear()`—
 * porque eso le da un año de más a todo el que todavía no cumplió: en junio,
 * quien nació en diciembre figuraría con la edad que va a tener recién a fin de
 * año.
 */
export function edadEnAnios(valor: string | Date | null | undefined): number | null {
    if (!valor) return null;

    const nacio = aFechaLocal(valor);

    if (Number.isNaN(nacio.getTime())) return null;

    const hoy = new Date();

    let edad = hoy.getFullYear() - nacio.getFullYear();
    const mes = hoy.getMonth() - nacio.getMonth();

    // Si todavía no llegó el mes, o llegó pero no el día, falta un cumpleaños.
    if (mes < 0 || (mes === 0 && hoy.getDate() < nacio.getDate())) {
        edad--;
    }

    return Math.max(0, edad);
}

/**
 * Fecha y hora de un INSTANTE.
 *
 * Recibe siempre ISO 8601 con zona —lo que devuelve `toIso8601String()` de
 * PHP—, así que convertir a horario local es lo correcto y no hace falta el
 * cuidado de `fecha()`: ahí el problema es al revés, una cadena sin zona a la
 * que no hay que aplicarle ninguna.
 */
export function fechaHora(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(valor));
}

export function iniciales(nombre: string): string {
    return nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}
