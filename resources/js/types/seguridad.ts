/** Un rol, tal como está en la base. */
export interface RolFila {
    id: number;
    nombre: string;
    etiqueta: string;
    descripcion: string | null;
    /** Sembrado desde RolSistema: no se edita ni se borra desde el panel. */
    del_sistema: boolean;
    usuarios: number;
    /** Cuántos tiene; cuáles, en el formulario. */
    permisos: number;
    puede_editarse: boolean;
    puede_eliminarse: boolean;
}

/** Un módulo con sus permisos, en el orden del menú. */
export interface ModuloPermisos {
    clave: string;
    etiqueta: string;
    /** La sección del menú: Ventanilla, Pagos, Seguridad… */
    grupo: string;
    permisos: { nombre: string; accion: string }[];
}

/** Lo que abre el formulario de edición. */
export interface RolFormulario {
    id: number;
    nombre: string;
    permisos: string[];
}

/** Un rol existente del que se pueden copiar los permisos. */
export interface PlantillaRol {
    nombre: string;
    permisos: string[];
}

/** Una cuenta de acceso —funcionario o beneficiario—, en Seguridad › Usuarios. */
export interface UsuarioFila {
    id: number;
    /** null = funcionario. */
    beneficiario_id: number | null;
    nombre: string;
    /** El correo del funcionario o la C.I. del beneficiario: con eso entra. */
    detalle: string | null;
    /** Los roles del funcionario, o «Beneficiario». */
    rol: string;
    foto_url: string | null;
    estado_etiqueta: string;
    estado_color: string;
    ultimo_acceso: string | null;
    creada: string | null;
}
