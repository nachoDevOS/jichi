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
 *  UNA FECHA SUELTA NO ES UN INSTANTE, Y CONFUNDIRLOS RESTA UN DÍA
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
 * La misma fecha, pero como la quiere un `<input type="date">`: AAAA-MM-DD.
 */
export function fechaInput(valor: string | Date | null | undefined): string {
    if (!valor) return '';

    return new Intl.DateTimeFormat('en-CA', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(aFechaLocal(valor));
}

/**
 * Años CUMPLIDOS a partir de una fecha de nacimiento.
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
 */
export function fechaHora(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        // 24 h, igual que `hora()`. El es-BO por defecto da «03:06 p. m.», y con
        // las dos funciones conviviendo en la misma pantalla —la fecha de
        // registro en 24 h y el control de la boleta en 12— la tabla parecía
        // decir dos horarios distintos.
        hour12: false,
    }).format(new Date(valor));
}

/**
 * Solo la HORA de un instante: «14:17».
 */
export function hora(valor: string | Date | null | undefined): string {
    if (!valor) return '—';

    return new Intl.DateTimeFormat('es-BO', {
        hour: '2-digit',
        minute: '2-digit',
        // 24 h y no el 12 h con «a. m. / p. m.» que es el de es-BO por defecto:
        // en una tabla esos cinco caracteres de más ensanchan la columna, y en
        // una oficina el horario corrido se lee y se dicta en 24 h.
        hour12: false,
    }).format(new Date(valor));
}

/**
 *  CUÁNTO HACE — «hace 3 minutos», «ayer», «hace 2 meses»
 */
export function hace(valor: string | Date | null | undefined): string {
    if (!valor) return '';

    const instante = new Date(valor);

    if (Number.isNaN(instante.getTime())) return '';

    const segundos = Math.round((Date.now() - instante.getTime()) / 1000);

    if (segundos < 60) return 'recién';

    /*
     * DOS FORMATOS, Y NO ES CAPRICHO.
     */
    const cercano = new Intl.RelativeTimeFormat('es-BO', { numeric: 'auto' });
    const lejano = new Intl.RelativeTimeFormat('es-BO', { numeric: 'always' });

    // De la unidad más chica a la más grande: se usa la primera en la que el
    // número queda por debajo de su tope. Los meses van de 30 días y los años
    // de 365 a propósito: es una aproximación de lectura, no un cálculo de
    // calendario —para eso está la fecha exacta al lado—.
    const escala: [Intl.RelativeTimeFormatUnit, number, number, Intl.RelativeTimeFormat][] = [
        ['minute', 60, 60, cercano],
        ['hour', 3600, 24, cercano],
        ['day', 86400, 30, cercano],
        ['month', 2592000, 12, lejano],
    ];

    for (const [unidad, enSegundos, tope, formato] of escala) {
        const cantidad = Math.floor(segundos / enSegundos);

        if (cantidad < tope) {
            return formato.format(-cantidad, unidad);
        }
    }

    return lejano.format(-Math.floor(segundos / 31536000), 'year');
}

export function iniciales(nombre: string): string {
    return nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}
