# Modelo Entidad-Relación — Jichi

Sistema de carnets de pesca del Gobierno Autónomo Departamental del Beni.
PostgreSQL 18. Generado desde el esquema real (`storage/app/jichi-esquema.sql`).

Las 24 tablas de la base se dividen en tres grupos:

| Grupo | Tablas | Qué son |
| --- | --- | --- |
| **Dominio** | `beneficiarios`, `rubros`, `carnets`, `tramites`, `pagos` | El negocio. Son las que se modelan acá |
| **Soporte** | `users`, `correlativos`, `configuraciones`, `auditorias`, `accesos` | Operación del sistema |
| **Infraestructura** | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations` | Laravel y el paquete de permisos. No son del negocio |

---

## 1. Diagrama del dominio

```mermaid
erDiagram
    BENEFICIARIOS ||--o{ CARNETS : "obtiene"
    RUBROS        ||--o{ CARNETS : "es la actividad de"
    CARNETS       ||--o{ TRAMITES : "recibe"
    RUBROS        ||--o{ TRAMITES : "se solicita en"
    TRAMITES      ||--o{ PAGOS : "se cubre con"
    TRAMITES      ||--o| RECIBOS : "genera"

    BENEFICIARIOS {
        bigint    id PK
        varchar   ci_nit "UQ parcial junto con complemento"
        varchar   complemento "complemento del CI boliviano"
        varchar   expedido "BN, LP, SC, CB..."
        varchar   primerNombre
        varchar   segundoNombre "mucha gente no tiene"
        varchar   apellidoPaterno
        varchar   apellidoMaterno
        varchar   apellidoCasado "sin el de: se agrega al imprimir"
        date      fechaNacimiento
        varchar   genero "masculino | femenino"
        varchar   nacionalidad
        text      direccion
        varchar   ciudad
        varchar   provincia
        varchar   telefono
        varchar   email
        varchar   foto
        timestamp deleted_at "borrado logico"
    }

    RUBROS {
        bigint  id PK
        varchar nombre UK
        text    descripcion
        numeric costo "tarifa vigente en Bs"
        boolean requiere_capacidad "si se autoriza por volumen (kilos)"
        varchar estado "activo | inactivo"
    }

    CARNETS {
        bigint   id PK
        bigint   beneficiario_id FK "UQ junto con rubro_id y gestion"
        bigint   rubro_id FK "la actividad que habilita"
        varchar  firma_validacion UK "16 chars: identifica el carnet"
        smallint gestion "UQ junto con beneficiario_id y rubro_id"
        varchar  asociacion "copia de tramites.asociacion"
        numeric  capacidad_kg "cupo autorizado en kilos"
        date     fecha_emision
        date     fecha_vencimiento
        varchar  estado "vigente | suspendido | vencido | anulado"
    }

    TRAMITES {
        bigint    id PK
        bigint    carnet_id FK
        bigint    rubro_id FK
        varchar   tipo_tramite "emision_inicial | actualizacion"
        varchar   estado "pendiente | en_revision | aprobado | rechazado"
        varchar   ciFile "fotocopia de carnet de identidad"
        varchar   certAsociacionFile "certificado de la asociacion"
        varchar   asociacion
        numeric   capacidad_kg
        numeric   monto_requerido "copia de rubros.costo"
        timestamp fecha_solicitud
        timestamp fecha_revision "cuando se tomo para revisar"
        timestamp fecha_aprobacion
        timestamp fecha_generacion "cuando se imprimio"
        timestamp fecha_entrega
        text      observaciones
        text      motivo_rechazo
    }

    PAGOS {
        bigint    id PK
        bigint    tramite_id FK
        varchar   nro_transaccion UK
        numeric   monto
        varchar   urlFile "comprobante escaneado del deposito"
        timestamp fecha_pago
        text      observaciones
    }

    RECIBOS {
        bigint   id PK
        bigint   tramite_id FK "UQ, nullable"
        integer  numero "UQ junto con gestion"
        smallint gestion
        varchar  beneficiario_nombre "copia congelada"
        varchar  beneficiario_ci "copia congelada"
        varchar  concepto
        varchar  descripcion "permiso_faena | guia_transporte | otros..."
        numeric  monto
        varchar  forma_pago "deposito | efectivo"
        varchar  nro_deposito
        varchar  lugar
        date     fecha_emision
        json     detalle "renglones del cuadro de importes"
    }
```

### Cómo se lee

| Relación | Cardinalidad | En palabras |
| --- | --- | --- |
| `beneficiarios` → `carnets` | 1 : 0..N | Una persona tiene un carnet **por gestión**. Varios años, varios carnets; el mismo año, uno solo |
| `rubros` → `carnets` | 1 : N | Un carnet habilita UNA actividad; una actividad tiene muchos carnets emitidos |
| `carnets` → `tramites` | 1 : 0..N | Cada rubro pedido es un trámite distinto colgado del mismo carnet |
| `rubros` → `tramites` | 1 : 0..N | Un trámite pide exactamente un rubro |
| `tramites` → `pagos` | 1 : 0..N | Un trámite se cubre con uno o varios depósitos |
| `tramites` → *recibo* | 1 : 0..1 | **Sin tabla.** El comprobante se arma al vuelo; existe desde que hay `fecha_revision` |

**Acá había un N:M y ya no lo hay.** `carnets` ↔ `rubros` se resolvía con una entidad asociativa, `carnet_rubro`, que tenía identidad y atributos propios (`fecha_habilitacion`, `capacidad_kg`, `estado`) y nacía al aprobar un trámite: era la habilitación misma.

Al pasar a **un carnet por rubro**, esa entidad quedó sin razón de ser: sus atributos son los del carnet, y su clave `(carnet_id, rubro_id)` es redundante con la del carnet. Se eliminó, y con ella el enum `EstadoHabilitacion`, cuyos dos valores se absorbieron en `EstadoCarnet`. **El carnet ES la habilitación.**

---

## 2. Las reglas que el modelo impone

Lo que hace interesante a este MER no son las claves foráneas sino las restricciones de unicidad. Cada una es una regla del negocio escrita en la base, no en el código.

| Restricción | Tabla | Regla que garantiza |
| --- | --- | --- |
| `carnets_beneficiario_rubro_gestion_unique (beneficiario_id, rubro_id, gestion)` | `carnets` | **Una persona, un carnet por actividad y por año.** Es la regla que ordena todo el sistema |
| `carnets_firma_validacion_unique` | `carnets` | El carnet **no tiene número**: se identifica por su firma de 16 caracteres, que además es la llave de la verificación pública |
| `pagos_nro_transaccion_unique` | `pagos` | El mismo depósito no se carga dos veces |
| `rubros_nombre_unique` | `rubros` | No hay dos rubros con el mismo nombre |
| `correlativos_serie_anio_unique` | `correlativos` | Fuente del número de recibo: una fila por serie y año |
| `beneficiarios_ci_unico` | `beneficiarios` | Un CI + complemento por persona — tiene truco, ver abajo |

### El índice de `beneficiarios` es parcial, y tiene que serlo

```sql
CREATE UNIQUE INDEX beneficiarios_ci_unico
    ON beneficiarios (ci_nit, COALESCE(complemento, ''))
    WHERE deleted_at IS NULL;
```

Dos detalles que no se ven a simple vista:

- **`WHERE deleted_at IS NULL`** en lugar de meter `deleted_at` dentro del `UNIQUE`. En SQL `NULL != NULL`, así que un índice sobre `(ci_nit, deleted_at)` no bloquearía nada: todos los registros activos tienen `deleted_at` en NULL y el motor los considera distintos entre sí. El índice parcial solo mira los vivos.
- **`COALESCE(complemento, '')`** porque el complemento del CI boliviano es opcional, y sin eso dos personas con el mismo CI y complemento NULL tampoco chocarían.

---

## 3. Borrado: qué se cae y qué resiste

Las claves foráneas no son todas iguales, y ahí se lee el valor que el sistema le da a cada cosa.

| Clave foránea | Acción | Por qué |
| --- | --- | --- |
| `carnets.beneficiario_id` → `beneficiarios` | `RESTRICT` | No se borra a alguien que tiene carnets emitidos |
| `tramites.carnet_id` → `carnets` | `RESTRICT` | No se borra un carnet con expedientes |
| `tramites.rubro_id` → `rubros` | `RESTRICT` | No se borra un rubro que alguien solicitó |
| `carnets.rubro_id` → `rubros` | `RESTRICT` | Ni uno con carnets emitidos: el catálogo se inactiva, no se borra |
| `pagos.tramite_id` → `tramites` | `CASCADE` | El pago no existe sin su trámite |
| `auditorias.user_id` → `users` | `SET NULL` | El registro histórico no se borra con el usuario |
| `accesos.user_id` → `users` | `SET NULL` | Ídem |

Esos `SET NULL` son el patrón de **documento emitido**: lo que ya salió impreso y está en la calle no puede cambiar ni desaparecer porque se modifique el registro que lo originó.

---

## 4. Desnormalización deliberada

Hay campos repetidos a propósito. No son un error de diseño: son copias congeladas al momento de emitir.

| Campo copiado | Viene de | Cuándo se copia | Por qué |
| --- | --- | --- | --- |
| `tramites.monto_requerido` | `rubros.costo` | Al registrar el trámite | Si mañana sube la tarifa, el trámite viejo sigue debiendo lo que decía cuando se presentó |
| `carnets.asociacion` | `tramites.asociacion` | Al emitir el carnet | Queda impreso en el plástico |
| `carnets.capacidad_kg` | `tramites.capacidad_kg` | **Solo al aprobar** | El cupo autorizado es el de esa aprobación. Lo que venga en blanco NO pisa lo que ya había |
| `carnets.asociacion` | `tramites.asociacion` | **Solo al aprobar** | Igual que el cupo: el carnet nace en NULL y se llena con la primera firma |

**Las dos columnas de `capacidad_kg` no son el mismo dato.** `tramites` guarda lo **pedido**; `carnets`, lo **autorizado**. Entre el registro y la aprobación valen cosas distintas: quien tiene 600 kg autorizados y pide 850 sigue rigiendo por 600 hasta que se cobre y se firme. Con una sola columna, el pedido pisaría al vigente antes de pagarse — y un rechazo posterior no tendría a dónde volver.

La regla general: **un documento emitido guarda su propio texto**, no una referencia a algo que puede cambiar.

---

## 5. Estados: los dominios de valores

Todos van en columnas `varchar`, nunca en tipos `ENUM` nativos de PostgreSQL — así agregar un estado no exige `ALTER TYPE` ni bloquear la tabla. Los valores válidos los definen los enums de PHP en `app/Enums/`.

| Tabla.columna | Valores | Enum |
| --- | --- | --- |
| `tramites.estado` | `pendiente`, `en_revision`, `aprobado`, `rechazado` | `EstadoTramite` |
| `tramites.tipo_tramite` | `emision_inicial`, `actualizacion` | `TipoTramite` |
| `carnets.estado` | `vigente`, `vencido`, `anulado` | `EstadoCarnet` |
| `rubros.estado` | `activo`, `inactivo` | `EstadoRubro` |

### El ciclo de vida del trámite

```mermaid
stateDiagram-v2
    [*] --> pendiente : registrar
    pendiente --> en_revision : enviar — nace el RECIBO
    pendiente --> [*] : eliminar, con motivo
    en_revision --> aprobado : aprobar — el carnet queda habilitado
    en_revision --> rechazado : rechazar, con motivo
    aprobado --> [*]
    rechazado --> [*]
```

`pendiente` es un **borrador**, y eso explica qué se puede hacer en cada estado: se edita y se elimina, pero no se aprueba ni se rechaza. El salto directo `pendiente → aprobado` no existe, a propósito: con él, quien carga la solicitud podría aprobarla sin que nadie más la mire.

**Impreso y entregado no son estados sino fechas** — `fecha_generacion` y `fecha_entrega`. Un estado obligaría a sincronizar dos columnas que pueden contradecirse; una fecha en NULL dice «todavía no pasó» y no hay forma de que mienta.

---

## 6. Diagrama de soporte

```mermaid
erDiagram
    USERS ||--o{ AUDITORIAS : "genera"
    USERS ||--o{ ACCESOS : "registra"
    USERS }o--o{ ROLES : "tiene"
    ROLES }o--o{ PERMISSIONS : "otorga"

    USERS {
        bigint    id PK
        varchar   name
        varchar   email UK
        varchar   password
        varchar   ci
        varchar   cargo
        varchar   telefono
        boolean   activo
        timestamp ultimo_acceso_at
        timestamp deleted_at "borrado logico"
    }

    AUDITORIAS {
        bigint  id PK
        bigint  user_id FK "SET NULL"
        varchar evento
        varchar auditable_type "relacion polimorfica"
        bigint  auditable_id
        jsonb   valores_anteriores
        jsonb   valores_nuevos
        varchar descripcion
        varchar ip
        text    user_agent
        varchar url
    }

    ACCESOS {
        bigint  id PK
        bigint  user_id FK "SET NULL"
        varchar email
        varchar evento
        varchar ip
        text    user_agent
        varchar session_id
    }

    CORRELATIVOS {
        bigint   id PK
        varchar  serie "UQ junto con anio"
        smallint anio
        integer  ultimo_numero
    }

    CONFIGURACIONES {
        bigint  id PK
        varchar clave UK
        text    valor
        varchar tipo
        varchar grupo
        varchar etiqueta
        text    descripcion
        boolean publico
    }
```

`auditorias` usa una **relación polimórfica** (`auditable_type` + `auditable_id`): apunta a cualquier modelo del sistema sin necesidad de una clave foránea por tabla. Por eso no tiene flechas hacia el dominio en el diagrama — esa integridad la sostiene la aplicación, no el motor.

`correlativos` y `configuraciones` son **tablas sueltas**, sin relaciones. La segunda son pares clave-valor con los parámetros del sistema. La primera es un contador genérico que hoy **nadie usa**: alimentaba la serie del talonario de recibos y quedó libre al retirarse esa tabla. No se borró porque un correlativo es justamente el dato que no se puede derivar — ver `CorrelativoService`.

> **NO HAY TABLA `recibos`.** El comprobante se ARMA al vuelo con los datos de
> `beneficiarios`, `carnets`, `rubros`, `tramites` y `pagos` cada vez que
> alguien lo imprime; su número es el id del trámite y su fecha es
> `tramites.fecha_revision`. Ver `App\Support\ReciboArmado`, que explica qué se
> gana y qué se pierde con eso.

---

## 7. El recorrido completo, un paso por línea

1. Se registra un **beneficiario**, o se busca uno existente por CI.
2. Se presenta un **trámite** pidiendo un **rubro**. El sistema decide solo si es `emision_inicial` —y crea el **carnet de ese rubro**— o `actualizacion` —y reutiliza el que ya existe—.
3. Se cargan uno o más **pagos** con su boleta escaneada, hasta cubrir `monto_requerido`.
4. Se **envía a revisión**: en la misma transacción nace el **recibo** numerado, y el pescador se va con ese papel.
5. Se **aprueba**: el cupo y la asociación se consolidan en el **carnet** y recién ahí la persona queda habilitada.
6. Se **imprime** el carnet (`fecha_generacion`) y se **entrega** (`fecha_entrega`).

---

Ver también: [ARQUITECTURA.md](ARQUITECTURA.md) · [modulos/CARNETS.md](modulos/CARNETS.md) · [modulos/RECIBOS.md](modulos/RECIBOS.md)
