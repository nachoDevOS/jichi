/**
 * Tipos del módulo Trámites.
 *
 * Corresponden a lo que arma App\Http\Controllers\Panel\TramiteController.
 *
 * OJO: hoy ese controlador es una MAQUETA y devuelve arrays escritos a mano.
 * Estos tipos igual se escribieron pensando en el modelo definitivo, para que
 * el día que los datos salgan de la base no haya que rehacerlos.
 */

/** El membrete impreso en la cabecera del documento en papel. */
export interface MembreteTipoTramite {
    institucion: string;
    secretaria: string;
    programa: string;
    unidad: string;
}

/** La credencial del solicitante, tal como la manda el controlador. */
export interface CredencialDelSolicitante {
    /** El documento no tiene número propio: se identifica por su código. */
    codigo_verificacion: string;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    estado: string;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
}

/** Un trámite de cédula que todavía no llegó a emitirse. */
export interface TramiteEnCurso {
    id: number;
    estado: string;
    estado_etiqueta: string;
    estado_color: string;
}

/**
 * El solicitante que hace el trámite.
 *
 * Viaja con su credencial porque es lo primero que el operador mira: sin
 * Cédula de Pescador vigente no se le puede emitir ni una faena ni una guía.
 */
export interface SolicitanteDelTramite {
    id: number;
    nombreCompleto: string;
    /** Ya armado para mostrar: '7656924-1A'. */
    documento_identidad: string;
    /** Las partes sueltas, para precargar los formularios de trámite. */
    ci_nit: string;
    complemento: string | null;
    expedido: string | null;
    direccion: string | null;
    ciudad: string | null;
    provincia: string | null;
    telefono: string | null;
    /** La foto de su ficha. Es la que se imprime en la credencial. */
    foto_url: string | null;
    credencial: CredencialDelSolicitante | null;
    /** Cédula pedida y todavía sin emitir. null si no hay ninguna en curso. */
    credencial_en_tramite: TramiteEnCurso | null;
}

/**
 * La respuesta del servidor a «¿este solicitante puede pedir este servicio?».
 *
 * No es un booleano a propósito: en ventanilla el «no» sin explicación es
 * inservible. Corresponde a App\Support\Habilitacion en PHP.
 */
export interface HabilitacionVista {
    habilitado: boolean;
    /** Qué falta. null si está habilitado. */
    motivo: string | null;
    /** Qué hacer para destrabarlo. null si está habilitado. */
    como_resolver: string | null;
    credencial: CredencialDelSolicitante | null;
    tramite_en_curso: TramiteEnCurso | null;
}

/**
 * Un tipo de trámite del catálogo.
 *
 * Sale de la tabla `tipos_tramite`. Los campos desde `membrete` en adelante
 * NO son columnas: son los textos impresos en los talonarios del SEDAG y solo
 * se usan para dibujar la vista previa del documento. Por eso son opcionales,
 * y un tipo sin formulario construido no los trae.
 */
export interface TipoTramiteOpcion {
    codigo: string;
    nombre: string;
    area: string;
    icono: string;
    /** false = se muestra en el selector pero su formulario no existe aún. */
    habilitado: boolean;
    /** null = no tiene monto fijo impreso; se liquida según lo declarado. */
    monto: number | null;
    categoria_documento: string;
    resumen: string;
    /** Ya legible: «Hasta el 31 de diciembre de la gestión · un solo uso». */
    vigencia: string;
    /** Se agota al usarse, aunque no haya vencido. */
    uso_unico: boolean;
    /** Exige Cédula de Pescador vigente del solicitante. */
    requiere_credencial: boolean;
    /** Nombre de la ruta del formulario propio de este servicio. */
    ruta?: string | null;

    /** null cuando todavía no se eligió solicitante. */
    habilitacion?: HabilitacionVista | null;

    membrete?: MembreteTipoTramite;
    encabezado_legal?: string;
    autoriza?: string;
    nota?: string;
    responsable?: string;
    copias?: string[];
    firmas?: string[];
    requisitos?: string[];
}

/** Opción simple de una lista desplegable. */
export interface Opcion {
    value: string;
    label: string;
}

/** Presentación del producto, con el grupo al que pertenece en el papel. */
export interface OpcionPresentacion extends Opcion {
    grupo: string;
}

/** Las listas fijas que necesita la Guía de Transporte. */
export interface CatalogosGuiaTransporte {
    vias: Opcion[];
    medios: Opcion[];
    presentaciones: OpcionPresentacion[];
    especies: string[];
}

/**
 * Una fila de la tabla "D.- PRODUCTOS HIDROBIOLÓGICOS".
 *
 * El talonario trae cinco renglones fijos. Acá se agregan y quitan según haga
 * falta, que es lo que el papel no puede hacer.
 */
export interface FilaProductoGuia {
    /** Clave estable para React. No viaja al servidor. */
    id: string;
    especie: string;
    presentacion: string;
    /** Cantidad adquirida en kilos, en descarga o trasbordo. */
    cantidad_kg: string;
    /** Precio pagado por kilo en el lugar de origen. */
    precio_kg: string;
}

/**
 * Los campos de la Guía Única de Transporte de Productos Ictícolas.
 *
 * Siguen el orden de las secciones del formulario en papel: A interesado,
 * B ubicación, C transporte, D productos. Igual que en el permiso por faena,
 * en el modelo definitivo esto va dentro de `datos_adicionales`.
 */
export interface FormularioGuiaTransporte {
    tipo: string;

    /**
     * A nombre de quién va el trámite.
     *
     * Viaja en el formulario porque el servidor lo necesita para volver a
     * comprobar la compuerta al guardar: sin cédula de pescador vigente el
     * trámite no se registra, y esa comprobación no puede depender de que la
     * pantalla anterior haya hecho bien su trabajo.
     */
    solicitante: number;

    fecha: string;
    nro_recibo: string;

    /** A.- INTERESADO */
    comerciante: string;
    documento_identidad: string;

    /** B.- UBICACIÓN — origen y destino, con su división política. */
    origen_lugar: string;
    origen_departamento: string;
    origen_provincia: string;
    origen_distrito: string;
    destino_lugar: string;
    destino_departamento: string;
    destino_provincia: string;
    destino_distrito: string;
    via: string;

    /** C.- TRANSPORTE */
    medio: string;
    transporte_nombre: string;
    transporte_placa: string;
    transporte_capacidad: string;

    /** D.- PRODUCTOS HIDROBIOLÓGICOS */
    productos: FilaProductoGuia[];

    observaciones: string;
}

/**
 * Los campos propios del Permiso por Faena.
 *
 * Son exactamente los renglones del talonario del SEDAG - BENI. En el modelo
 * definitivo NO son columnas de `tramites`: van dentro de `datos_adicionales`,
 * la columna jsonb que la migración dejó preparada para esto.
 *
 * Todos son texto porque un <input> siempre devuelve texto, incluso el de
 * fecha y el de número. La conversión la hace Laravel al validar.
 */
export interface FormularioPermisoFaena {
    /** Tipo elegido en el selector. Hoy siempre 'PPF'. */
    tipo: string;

    /**
     * A nombre de quién va el trámite.
     *
     * Viaja en el formulario porque el servidor lo necesita para volver a
     * comprobar la compuerta al guardar: sin cédula de pescador vigente el
     * trámite no se registra, y esa comprobación no puede depender de que la
     * pantalla anterior haya hecho bien su trabajo.
     */
    solicitante: number;

    /** N° de recibo de caja, el recuadro vacío del talonario. */
    nro_recibo: string;

    embarcacion: string;
    propietario: string;
    comandante: string;
    matricula_naval: string;
    kardex: string;

    /** "Pescar en la región desde ... hasta ...". */
    region_desde: string;
    region_hasta: string;

    fecha_salida: string;
    fecha_desembarque: string;

    /** Cantidad autorizada de pescado extraído, en kilogramos. */
    cantidad_kg: string;

    observaciones: string;
}

/**
 * Una forma de pago, tal como la manda el servidor desde `FormaPago`.
 *
 * `requiere_referencia` viene calculado del enum y no se vuelve a decidir acá:
 * si mañana el efectivo pasara a exigir número de transacción, la pantalla se
 * entera sola. Duplicar esa condición en React sería la clase de detalle que
 * se cambia en un lado y se olvida en el otro.
 */
export interface FormaPagoOpcion {
    value: string;
    label: string;
    requiere_referencia: boolean;
}

/**
 * Un pago declarado en ventanilla: una transferencia, un QR o un efectivo.
 *
 * Es un objeto entero y no tres listas sueltas porque el comprobante, su
 * número de transacción y su monto son la MISMA operación. Separados, al
 * borrar una fila del medio los montos quedan apuntando al comprobante
 * equivocado. Ver `TramiteController::registrarPagos()`.
 */
export interface PagoDeclarado {
    /** Uno de los `value` de `FormaPagoOpcion`. */
    forma: string;

    /** El código del banco. Va vacío cuando se pagó en efectivo. */
    nro_transaccion: string;

    banco: string;

    /**
     * Texto y no número porque es lo que devuelve un `<input>`. El servidor lo
     * convierte al guardar; convertirlo acá haría que borrar el último dígito
     * deje el campo en `NaN` y el operador no pueda escribir.
     */
    monto: string;

    comprobante: File | null;

    /*
     * LOS DOS SIGUIENTES SOLO SE USAN AL CORREGIR.
     *
     * Un pago que ya está en el expediente conserva su comprobante: la ruta
     * viaja en `archivo_actual`, escondida en el formulario, y `url_actual` es
     * para poder abrirlo y mirarlo. Sin eso, corregir un monto obligaría a
     * volver a adjuntar el comprobante que ya está cargado.
     *
     * En el alta van vacíos: no hay nada anterior que conservar.
     */
    archivo_actual?: string;
    url_actual?: string | null;
}

/**
 * Lo que se dibuja en el renglón «REGISTRO» mientras la cédula no está
 * guardada.
 *
 * Es un molde, no un valor: muestra la FORMA que va a tener el código —tres
 * grupos de cuatro— sin inventar uno. Dejar el renglón en blanco haría creer
 * que la credencial se va a imprimir sin número.
 *
 * Vive acá y no escrito en dos componentes porque lo usan el formulario y la
 * vista previa, y tienen que decir lo mismo.
 */
export const MOLDE_REGISTRO = 'xxxx-xxxx-xxxx';

/** Las listas fijas que necesita la Cédula de Pescador. */
export interface CatalogosCedulaPescador {
    asociaciones: string[];
    provincias: string[];
    formas_pago: FormaPagoOpcion[];
}

/**
 * Los campos de la Cédula de Pescador.
 *
 * Son los renglones impresos en la credencial plastificada que emite el SEDAG.
 * A diferencia de los otros dos servicios, este documento lleva **fotografía**:
 * `CategoriaDocumento::requiereFoto()` ya lo contempla para la categoría
 * `credencial`, y `formatoPagina()` devuelve el tamaño CR80 (85,6 × 54 mm).
 */
export interface FormularioCedulaPescador {
    tipo: string;

    /**
     * A nombre de quién va el trámite.
     *
     * Viaja en el formulario porque el servidor lo necesita para volver a
     * comprobar la compuerta al guardar: sin cédula de pescador vigente el
     * trámite no se registra, y esa comprobación no puede depender de que la
     * pantalla anterior haya hecho bien su trabajo.
     */
    solicitante: number;

    /*
     * C.I. del titular y su lugar de expedición, como se imprime:
     * «7656924 BN». Ninguno de los dos se edita en el trámite —salen de la
     * ficha y el servidor los vuelve a leer de ahí al guardar—: viajan solo
     * para dibujar la vista previa de la credencial.
     */
    ci: string;
    expedido: string;

    nombre: string;
    asociacion: string;

    /*
     * Ciudad, provincia y dirección son renglones impresos en la credencial,
     * pero NO se editan en el trámite: son datos de la persona y salen de su
     * ficha. Viajan en el formulario solo para dibujar la vista previa; al
     * guardar, el servidor los vuelve a tomar de la ficha y pisa lo que llegue.
     * Ver `TramiteController::datosDeLaFicha()`.
     */
    ciudad: string;
    provincia: string;
    direccion: string;

    /*
     * Los mismos tres datos, pero para COMPLETAR la ficha cuando viene sin
     * ellos —el padrón viejo tiene fichas sin dirección—. Solo entonces
     * aparecen como campos, y lo que se escriba se guarda en `solicitantes`, no
     * en el trámite. Igual que `foto_solicitante`.
     */
    ciudad_solicitante: string;
    provincia_solicitante: string;
    direccion_solicitante: string;

    /**
     * El código de registro impreso en la credencial: «78T3-8K9T-789P».
     *
     * NO se escribe en el formulario: lo asigna el servidor al guardar, único
     * en todo el padrón. Viaja vacío y queda vacío; está en el tipo solo porque
     * la vista previa dibuja ese renglón, y mientras esté vacío muestra el
     * molde. Ver `RegistroPescadorService`.
     */
    registro: string;

    /** Cupo autorizado de extracción, en kilogramos. */
    capacidad_kg: string;

    /**
     * La foto del titular, SOLO si la ficha todavía no tiene una.
     *
     * No es un adjunto del trámite: se guarda en `solicitantes.foto`, porque es
     * un dato de la persona y de ahí lo toman todas las credenciales que se le
     * emitan. El campo aparece únicamente cuando la ficha viene sin foto —al
     * pescador se le saca en ese momento, en vez de mandarlo a otra pantalla—.
     *
     * Cuando la ficha ya tiene foto, este campo ni se muestra y el servidor
     * ignora lo que llegue: reemplazarla es una corrección de la ficha y se
     * hace desde la ficha. Si se pudiera cambiar en cada trámite, la misma
     * persona terminaría con una cara distinta en cada credencial.
     */
    foto_solicitante: File | null;

    /*
     * Los papeles que el pescador tiene que presentar SÍ O SÍ antes de que se
     * le emita la credencial. No se imprimen en la tarjeta: quedan como
     * respaldo del expediente.
     *
     * Son `File | null` y no texto porque el operador los adjunta escaneados;
     * el formulario se manda con forceFormData justamente por esto.
     */
    certificacion_asociacion: File | null;
    copia_ci: File | null;

    /**
     * El pago, que puede ser uno o varios.
     *
     * La cédula se cancela a veces en dos veces —media transferencia hoy y el
     * saldo la semana que viene—, y cada parte llega con su propio comprobante
     * y su propio número de transacción. Por eso es una lista y no un archivo
     * suelto: nunca está vacía, arranca con un pago en blanco.
     */
    pagos: PagoDeclarado[];

    observaciones: string;
}

/** Lo que se manda al corregir un trámite en curso. */
export interface FormularioCorreccionTramite {
    asociacion: string;
    capacidad_kg: string;
    observaciones: string;

    /** NULL = dejar el papel que ya está cargado. */
    certificacion_asociacion: File | null;
    copia_ci: File | null;

    pagos: PagoDeclarado[];
}

/** Una fila del listado de trámites. */
export interface TramiteFila {
    id: number;

    /**
     * El código impreso en la credencial, cuando el servicio lo lleva.
     *
     * Vacío en el permiso por faena y en la guía: esos no imprimen un registro.
     * El identificador del expediente es el `id` y nada más — antes había
     * además un código correlativo y eran dos números para la misma fila.
     */
    registro: string;

    solicitante: string;
    ci_nit: string;
    embarcacion: string;
    tipo: string;
    area: string;
    icono: string;
    estado: string;
    estado_etiqueta: string;
    estado_color: string;
    monto_total: number;
    creado: string;
}

/**
 * Lo que el listado tiene puesto en la barra de direcciones.
 *
 * Vuelve del controlador para que el campo de búsqueda aparezca con lo que el
 * operador ya había escrito: si solo viviera en React, al recargar la página
 * la URL seguiría filtrada pero la caja se vería vacía.
 */
export interface FiltrosTramites {
    buscar: string | null;
    /** Estado elegido en el selector. Null = todos. */
    estado: string | null;
    /** Filas por página elegidas en la pantalla. El servidor ya la validó. */
    por_pagina: number;
}

/** Una opción del selector de estado. Sale de `EstadoTramite::opciones()`. */
export interface OpcionEstado {
    value: string;
    label: string;
    color: string;
}

/**
 * ============================================================================
 *  LA FICHA DE UN TRÁMITE
 * ============================================================================
 *
 * Lo que necesita la pantalla donde el trámite se revisa, se aprueba, se emite
 * y se entrega. Es más de lo que muestra el listado a propósito: quien revisa
 * tiene que poder ABRIR los papeles y ver los pagos sin salir de acá.
 */

/** Un paso del circuito que ya ocurrió: cuándo y quién. */
export interface HitoTramite {
    fecha: string;
    quien: string | null;
}

/** Un papel adjunto, con su enlace para abrirlo. */
export interface RequisitoAdjunto {
    campo: string;
    etiqueta: string;
    url: string;
    archivo: string;
}

/** Un pago declarado, tal como quedó guardado en el expediente. */
export interface PagoRegistrado {
    forma: string | null;
    forma_etiqueta: string;
    nro_transaccion: string | null;
    banco: string | null;
    monto: number;
    /** Ruta del comprobante en el disco. La necesita el formulario de corrección. */
    archivo: string | null;
    url: string | null;
}

/** El documento emitido, si el trámite ya llegó a ese paso. */
export interface DocumentoEmitido {
    codigo_verificacion: string;
    url_verificacion: string;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    dias_para_vencer: number | null;
    estado: string;
    estado_etiqueta: string;
    estado_color: string;
    emitido_por: string | null;
}

export interface TramiteDetalle {
    /** El identificador del expediente. No hay otro. */
    id: number;

    estado: string;
    estado_etiqueta: string;
    estado_color: string;

    /**
     * Los estados a los que ESTE trámite puede pasar ahora mismo.
     *
     * Sale de `EstadoTramite::siguientes()` en el servidor. La pantalla dibuja
     * los botones a partir de esta lista en vez de tener su propia copia de las
     * reglas: así los botones que se ven son exactamente los que el servidor va
     * a aceptar, y agregar un estado no obliga a tocar React.
     */
    siguientes: string[];

    /**
     * ¿Todavía se puede corregir el expediente?
     *
     * Sale de `EstadoTramite::permiteEdicion()`: solo mientras está en
     * revisión. Desde aprobado ya no —alguien firmó mirando esos papeles—.
     */
    puede_editarse: boolean;

    /**
     * ¿Corresponde dibujar el botón de emitir el documento?
     *
     * No sale de `siguientes` como los demás botones porque emitir dejó de ser
     * un cambio de estado: el trámite se queda aprobado hasta que se entrega.
     * Lo calcula el servidor —aprobado y sin documento todavía— en el mismo
     * lugar donde lo comprueba antes de aceptar.
     */
    puede_emitirse: boolean;

    tipo: {
        codigo: string;
        nombre: string;
        area: string;
        icono: string;
        vigencia: string;
        categoria_documento: string;
    };

    solicitante: {
        id: number;
        nombreCompleto: string;
        documento_identidad: string;
        telefono: string | null;
        ciudad: string | null;
        provincia: string | null;
        direccion: string | null;
        foto_url: string | null;
    };

    monto_total: number;
    monto_pagado: number;
    saldo_pendiente: number;
    esta_pagado: boolean;

    /** Los campos propios del servicio, tal como se guardaron en la columna jsonb. */
    datos_adicionales: Record<string, unknown>;

    observaciones: string | null;
    motivo_rechazo: string | null;
    modo_entrega: string | null;

    requisitos: RequisitoAdjunto[];
    pagos: PagoRegistrado[];
    documento: DocumentoEmitido | null;

    hitos: {
        recepcion: HitoTramite | null;
        revision: HitoTramite | null;
        aprobacion: HitoTramite | null;
        emision: HitoTramite | null;
        entrega: HitoTramite | null;
    };
}
