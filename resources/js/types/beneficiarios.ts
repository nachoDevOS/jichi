import type { EstadoCarnet, EstadoHabilitacion } from '@/types';

/**
 * Tipos del módulo Beneficiarios.
 *
 * Cada interfaz describe, campo por campo, lo que arma
 * App\Http\Controllers\Panel\BeneficiarioController. Si allá se renombra una
 * clave y acá no, el editor lo marca en rojo al instante en vez de descubrirlo
 * con una pantalla en blanco.
 */

/**
 * Una fila de la tabla del padrón.
 *
 * Los campos están agrupados como los pinta la tabla: identificación, la persona
 * y sus datos. No es casualidad —el controlador los arma en ese mismo orden—
 * para que agregar una columna sea encontrar el grupo al que pertenece.
 */
export interface BeneficiarioFila {
    id: number;

    // --- Columna «Beneficiario»
    foto_url: string | null;
    nombreCompleto: string;
    /** Ya armado por el modelo: «1234567-1A BN». */
    documento_identidad: string;

    // --- Columna «Datos»
    genero: string | null;
    telefono: string | null;
    fechaNacimiento: string | null;
    /**
     * Años CUMPLIDOS, calculados por el servidor.
     *
     * Llega hecha y no se calcula en el navegador a propósito: con dos
     * definiciones de «edad» —la de PHP y la de JavaScript— tarde o temprano
     * difieren por un día en los bordes (el cumpleaños de hoy, los bisiestos, la
     * zona horaria del teléfono del operador). Ver Beneficiario::edad().
     */
    edad: number | null;
}

/**
 * La ficha completa.
 *
 * `nombreCompleto` va en camelCase porque así llega de PHP: el modelo lo manda
 * con ese nombre explícito. Ver el comentario de App\Models\Beneficiario sobre
 * por qué ese accesor no puede ir en #[Appends].
 */
export interface BeneficiarioFicha {
    id: number;
    ci_nit: string;
    complemento: string | null;
    expedido: string | null;
    primerNombre: string;
    segundoNombre: string | null;
    apellidoPaterno: string;
    apellidoMaterno: string | null;
    /** Se guarda SIN el «de»: lo agrega el modelo al armar el nombre. */
    apellidoCasado: string | null;
    nombreCompleto: string;
    documento_identidad: string;
    fechaNacimiento: string | null;
    genero: string | null;
    nacionalidad: string | null;
    direccion: string | null;
    ciudad: string | null;
    provincia: string | null;
    telefono: string | null;
    email: string | null;
    foto_url: string | null;
    registrado: string | null;
}

/** Lo que el formulario de alta y edición manda de vuelta. */
export interface FormularioBeneficiario {
    ci_nit: string;
    complemento: string;
    expedido: string;
    primerNombre: string;
    segundoNombre: string;
    apellidoPaterno: string;
    apellidoMaterno: string;
    apellidoCasado: string;
    fechaNacimiento: string;
    genero: string;
    nacionalidad: string;
    direccion: string;
    ciudad: string;
    provincia: string;
    telefono: string;
    email: string;
    /** El archivo elegido. Null mientras no se toque el campo. */
    foto: File | null;
    /** Marca que la foto guardada hay que borrarla. Solo en edición. */
    quitar_foto: boolean;
    /** Inertia lo usa para simular el PUT desde un formulario con archivos. */
    _method?: 'put';
}

/**
 * El resumen de un carnet que muestra la ficha del beneficiario.
 *
 * NO trae la firma: esa es la llave de la verificación pública y solo viaja a la
 * ficha del carnet. Acá va el registro, que es por lo que la gente pregunta.
 */
export interface CarnetResumen {
    id: number;
    /** El número impreso en el carnet: 000013. Es el id con ceros adelante. */
    registro: string;
    gestion: number;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    /** Calculado contra la fecha, no leído del estado. Ver Carnet::estaVigente(). */
    vigente: boolean;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    tramites_count?: number;
    rubros?: string[];
}

/** Un rubro ya habilitado en un carnet, tal como lo ve el formulario. */
export interface RubroDelCarnet {
    id: number;
    nombre: string | null;
    estado: EstadoHabilitacion;
    estado_etiqueta: string;
    estado_color: string;
    fecha_habilitacion: string | null;
}

/** El carnet de la gestión, con sus rubros. */
export interface CarnetDeLaGestion {
    id: number;
    /**
     * El número que va IMPRESO en el plástico: 000013. Es el id del carnet
     * rellenado con ceros, así que es corto, se dicta de memoria y se compara
     * de un vistazo entre dos credenciales.
     *
     * La firma NO viaja acá: no se imprime en ningún lado del carnet, va solo
     * dentro del QR. Ver Carnet::registro().
     */
    registro: string;
    gestion: number;
    /** La que certificó al beneficiario al emitir. Se imprime en la tarjeta. */
    asociacion: string | null;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    /** Calculado contra la fecha, no leído del estado. Ver Carnet::estaVigente(). */
    vigente: boolean;
    admite_adiciones: boolean;
    fecha_vencimiento: string | null;
    rubros: RubroDelCarnet[];
}

/**
 * Qué tiene esta persona en la gestión en curso.
 *
 * Lo arma App\Support\SituacionCarnet y responde las tres preguntas que el
 * formulario de trámite necesita antes de ofrecer nada:
 *
 *   - ¿tiene carnet de este año? → emisión inicial o adición de rubro
 *   - ¿qué rubros ya tiene?      → esos no se pueden volver a pedir
 *   - ¿el carnet sigue vigente?  → uno anulado o vencido no admite adiciones
 *
 * ES PARA LA PANTALLA, NO ES LA REGLA. El servidor vuelve a comprobar todo al
 * registrar: entre que el operador ve esto y aprieta guardar pueden pasar
 * minutos, y en el medio otra ventanilla pudo haber habilitado el mismo rubro.
 */
export interface SituacionBeneficiario {
    gestion: number;
    tiene_carnet: boolean;
    carnet: CarnetDeLaGestion | null;
    /**
     * Los ids que el selector de rubros tiene que dejar deshabilitados.
     *
     * Incluye los SUSPENDIDOS: la habilitación existe igual, solo que cortada,
     * y lo que corresponde es que un supervisor la levante —no cobrar otro
     * trámite por lo mismo—.
     */
    rubros_ocupados: number[];
}

/** Una coincidencia del autocompletado del formulario de trámite. */
export interface BeneficiarioSugerido {
    id: number;
    nombreCompleto: string;
    documento_identidad: string;
    foto_url: string | null;

    /*
     * El domicilio, que se imprime en el carnet debajo de la asociación.
     *
     * Sale de la FICHA del beneficiario y no del formulario de trámite: son
     * datos del padrón. Si están mal, se corrigen editando a la persona —no
     * cargando otro trámite—.
     */
    ciudad: string | null;
    provincia: string | null;
    direccion: string | null;

    situacion: SituacionBeneficiario;
}
