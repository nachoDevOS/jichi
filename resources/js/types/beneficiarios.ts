import type { EstadoAprovechamiento, EstadoCarnet, TipoActor } from '@/types';

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
    ci: string;
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
    ci: string;
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
 * ----------------------------------------------------------------------------
 *  LA ACTIVIDAD VA PRIMERO, Y NO ES UN DETALLE DE ORDEN
 * ----------------------------------------------------------------------------
 *
 * Una persona puede tener DOS carnets vigentes a la vez —quien pesca y además
 * comercializa—, así que sin `tipo_actor` las dos filas se ven idénticas y el
 * operador no sabe cuál está mirando.
 *
 * `vigente` llega YA RESUELTO del servidor y la pantalla no lo deduce: la
 * columna `estado` puede estar desfasada, porque «vencido» lo escribe un
 * comando que corre una vez al día. Ver Carnet::estaVigente().
 */
export interface CarnetResumen {
    id: number;
    /** En grupos de cuatro: «PES2 6000 0017». Se guarda sin separadores. */
    codigo: string;
    /** El nombre del catálogo: «Carnet de Pescador». Es texto, no una regla. */
    tipo: string | null;
    /** La regla: de acá cuelga qué puede emitir y si lleva cupo. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    /** La que certificó al beneficiario. Se imprime en la tarjeta. */
    asociacion: string | null;
    /**
     * Los kilos impresos en el plástico, o null si es comercializador.
     *
     * Lo decide `TipoActor::requiereAprovechamiento()` en el servidor, NUNCA un
     * `if` sobre el nombre del tipo de carnet: ese nombre es un catálogo que la
     * unidad edita, y el mismo documento figura de dos formas distintas según
     * quién lo cargó.
     */
    cupo_kg: number | null;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    monto: number;
    /** Lo que falta cobrar. Se corta en cero: pagar de más no da saldo a favor. */
    saldo_pendiente: number;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/**
 * Una BOLSA MADRE de la persona: el cupo anual en kilos.
 *
 * Se manda el SALDO y no solo el volumen otorgado porque es lo único
 * accionable: «tiene 500 kg» no dice si puede salir a pescar mañana, y «le
 * quedan 20» sí.
 */
export interface CupoResumen {
    id: number;
    /** El tramo de la escala oficial: 1 a 7. */
    escala: number | null;
    /** El texto literal de la resolución: «201 Kg Hasta 500 Kg». */
    descripcion: string | null;
    volumen_total_kg: number;
    /** Lo comprometido por las faenas que consumen cupo (todas menos las vencidas). */
    kilos_consumidos: number;
    saldo_kg: number;
    porcentaje_usado: number;
    estado: EstadoAprovechamiento;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    saldo_pendiente: number;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/**
 * Un carnet vigente, tal como lo devuelve el autocompletado.
 *
 * LAS DOS BANDERAS LLEGAN CALCULADAS y la pantalla no las deduce. Un `if` sobre
 * el tipo en React sería una segunda copia de la regla, y se desincroniza en
 * cuanto alguien renombre una fila del catálogo o cambie la vigencia del cupo.
 * Ver Carnet::puedeEmitirFaenas() y ::puedeEmitirGuias().
 */
export interface CarnetVigenteSugerido {
    id: number;
    codigo: string;
    tipo: string | null;
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    /** Exige además una bolsa madre con saldo, no solo que sea de pescador. */
    puede_emitir_faenas: boolean;
    puede_emitir_guias: boolean;

    /**
     * Kilos que quedan en la bolsa madre. Null si el carnet no lleva cupo.
     *
     * Viene con el resultado de la búsqueda y no en un segundo viaje: el
     * formulario de faena lo necesita apenas se elige el carnet, y pedirlo
     * aparte se nota justo cuando el operador acaba de hacer clic.
     */
    saldo_kg: number | null;

    /**
     * El número de talonario que el sistema PROPONE. Null si no lleva cupo.
     *
     * Es una propuesta y no una imposición: el número sale de la hoja que el
     * operador tiene en la mano, y si no coincide hay algo que conviene mirar
     * antes de seguir, no autocorregir en silencio.
     */
    siguiente_numero_faena: number | null;
}

/** Una coincidencia del autocompletado de los formularios de emisión. */
export interface BeneficiarioSugerido {
    id: number;
    nombreCompleto: string;
    documento_identidad: string;
    foto_url: string | null;

    /*
     * El domicilio, que se imprime en el carnet debajo de la asociación.
     *
     * Sale de la FICHA del beneficiario y no del formulario de emisión: son
     * datos del padrón. Si están mal, se corrigen editando a la persona.
     */
    ciudad: string | null;
    provincia: string | null;
    direccion: string | null;

    /**
     * Sus credenciales vigentes. Vacío si no tiene ninguna, y en ese caso lo
     * que corresponde es emitirle una antes de cualquier otra cosa.
     */
    carnets_vigentes: CarnetVigenteSugerido[];
}
