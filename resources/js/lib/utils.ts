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
 * La misma fecha, pero como la quiere un `<input type="date">`: AAAA-MM-DD.
 *
 * Es la inversa de `fecha()` y hace falta cada vez que un formulario abre con
 * un valor que ya existe —corregir un depósito, por ejemplo—.
 *
 * NO ES `valor.slice(0, 10)`, y por eso está acá. Lo que manda el servidor es
 * un INSTANTE en UTC (`2026-09-16T02:00:00+00:00`); cortarle los diez primeros
 * caracteres devuelve el día en UTC, que en Bolivia —UTC-4— puede ser el
 * SIGUIENTE al que la pantalla venía mostrando. El operador abriría el
 * formulario y vería una fecha distinta de la que dice la fila de al lado, sin
 * haber tocado nada.
 *
 * `en-CA` se usa por su formato, no por el idioma: es el que da AAAA-MM-DD. El
 * cálculo del día se hace en horario local, igual que `fecha()`.
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
        // 24 h, igual que `hora()`. El es-BO por defecto da «03:06 p. m.», y con
        // las dos funciones conviviendo en la misma pantalla —la fecha de
        // registro en 24 h y el control de la boleta en 12— la tabla parecía
        // decir dos horarios distintos.
        hour12: false,
    }).format(new Date(valor));
}

/**
 * Solo la HORA de un instante: «14:17».
 *
 * Aparte de `fechaHora()` porque en una tabla las dos partes van en renglones
 * distintos —la fecha arriba, la hora abajo— y juntas en una sola línea obligan
 * a ensanchar la columna.
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
 * ============================================================================
 *  CUÁNTO HACE — «hace 3 minutos», «ayer», «hace 2 meses»
 * ============================================================================
 *
 * Acompaña a la fecha exacta, nunca la reemplaza. Las dos contestan preguntas
 * distintas y las dos hacen falta: la fecha sirve para buscar el papel en el
 * archivo, y el «hace tanto» para saber de un vistazo si esto entró recién o
 * está esperando desde la semana pasada.
 *
 * ----------------------------------------------------------------------------
 *  SE USA Intl.RelativeTimeFormat Y NO UNA CADENA ARMADA A MANO
 * ----------------------------------------------------------------------------
 *
 * Porque el castellano no es «hace N <unidad>» a secas: es «hace 1 minuto» y
 * «hace 2 minutos», y con `numeric: 'auto'` un día atrás sale «ayer» en vez de
 * «hace 1 día», que es como se dice. Armarlo con `if` es reescribir mal lo que
 * el navegador ya sabe.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NO SE ACTUALIZA SOLO
 * ----------------------------------------------------------------------------
 *
 * Se calcula al pintar y se queda quieto: un trámite cargado hace tres minutos
 * va a seguir diciendo «hace 3 minutos» hasta que se recargue la pantalla. Un
 * temporizador por fila obligaría a volver a pintar la tabla entera cada minuto
 * para un dato que nadie mira fijo. Al navegar o filtrar se recalcula solo.
 *
 * El RELOJ DEL NAVEGADOR puede estar adelantado respecto del servidor, y ahí un
 * instante recién guardado da negativo. Por eso todo lo que caiga dentro del
 * minuto —incluido el futuro— se muestra como «recién».
 */
export function hace(valor: string | Date | null | undefined): string {
    if (!valor) return '';

    const instante = new Date(valor);

    if (Number.isNaN(instante.getTime())) return '';

    const segundos = Math.round((Date.now() - instante.getTime()) / 1000);

    if (segundos < 60) return 'recién';

    /*
     * DOS FORMATOS, Y NO ES CAPRICHO.
     *
     * `numeric: 'auto'` reemplaza el número por la palabra cuando existe, y
     * para lo cercano eso es exactamente como se habla: un día atrás sale
     * «ayer» y no «hace 1 día».
     *
     * De meses para arriba se da vuelta y ESTORBA: cuarenta días atrás salía
     * «el mes pasado», que en un calendario puede ser dos meses atrás, y
     * cuatrocientos días salían como «el año pasado». Ahí se prefiere el
     * número: «hace 1 mes», «hace 1 año».
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
