# Modelo Entidad-Relación — Jichi

Sistema de carnets de pesca del Gobierno Autónomo Departamental del Beni.
PostgreSQL 18. Generado desde el esquema real (`storage/app/jichi-esquema.sql`).

Las 24 tablas de la base se dividen en tres grupos:

| Grupo | Tablas | Qué son |
| --- | --- | --- |
| **Dominio** | `beneficiarios`, `rubros`, `carnets`, `carnet_rubro`, `tramites`, `pagos`, `recibos` | El negocio. Son las que se modelan acá |
| **Soporte** | `users`, `correlativos`, `configuraciones`, `auditorias`, `accesos` | Operación del sistema |
| **Infraestructura** | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations` | Laravel y el paquete de permisos. No son del negocio |

---

## 1. Diagrama del dominio

```mermaid
erDiagram
    BENEFICIARIOS ||--o{ CARNETS : "obtiene"
    CARNETS       ||--o{ CARNET_RUBRO : "habilita"
    RUBROS        ||--o{ CARNET_RUBRO : "se habilita en"
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
        varchar estado "activo | inactivo"
    }

    CARNETS {
        bigint   id PK
        bigint   beneficiario_id FK
        varchar  firma_validacion UK "16 chars: identifica el carnet"
        smallint gestion "UQ junto con beneficiario_id"
        varchar  asociacion "copia de tramites.asociacion"
        date     fecha_emision
        date     fecha_vencimiento
        varchar  estado "vigente | vencido | anulado"
    }

    CARNET_RUBRO {
        bigint  id PK
        bigint  carnet_id FK "UQ junto con rubro_id"
        bigint  rubro_id FK
        date    fecha_habilitacion
        numeric capacidad_kg "cupo autorizado en kilos"
        varchar estado "habilitado | suspendido"
    }

    TRAMITES {
        bigint    id PK
        bigint    carnet_id FK
        bigint    rubro_id FK
        varchar   tipo_tramite "emision_inicial | adicion_rubro"
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
| `carnets` ↔ `rubros` | N : M vía `carnet_rubro` | Un carnet habilita varios rubros; un rubro está en muchos carnets |
| `carnets` → `tramites` | 1 : 0..N | Cada rubro pedido es un trámite distinto colgado del mismo carnet |
| `rubros` → `tramites` | 1 : 0..N | Un trámite pide exactamente un rubro |
| `tramites` → `pagos` | 1 : 0..N | Un trámite se cubre con uno o varios depósitos |
| `tramites` → `recibos` | 1 : 0..1 | Un solo recibo por trámite, y recién al enviarlo a revisión |

`carnet_rubro` es la entidad asociativa que resuelve el N:M, pero **no es un pivote pelado**: tiene identidad propia (`id`), atributos propios (`fecha_habilitacion`, `capacidad_kg`, `estado`) y es la fila que *nace* cuando se aprueba un trámite. Es la habilitación misma.

---

## 2. Las reglas que el modelo impone

Lo que hace interesante a este MER no son las claves foráneas sino las restricciones de unicidad. Cada una es una regla del negocio escrita en la base, no en el código.

| Restricción | Tabla | Regla que garantiza |
| --- | --- | --- |
| `carnets_beneficiario_gestion_unique (beneficiario_id, gestion)` | `carnets` | **Una persona, un carnet por año.** Es la regla que ordena todo el sistema |
| `carnets_firma_validacion_unique` | `carnets` | El carnet **no tiene número**: se identifica por su firma de 16 caracteres, que además es la llave de la verificación pública |
| `carnet_rubro_unico (carnet_id, rubro_id)` | `carnet_rubro` | No se habilita dos veces el mismo rubro en el mismo carnet |
| `recibos_serie_unica (gestion, numero)` | `recibos` | Numeración del talonario sin saltos ni repetidos dentro del año |
| `recibos_tramite_id_unique` | `recibos` | Un recibo por trámite. Una reimpresión sale con el **mismo** número |
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
| `carnet_rubro.rubro_id` → `rubros` | `RESTRICT` | Ni uno que está habilitado |
| `carnet_rubro.carnet_id` → `carnets` | `CASCADE` | La habilitación no existe sin su carnet |
| `pagos.tramite_id` → `tramites` | `CASCADE` | El pago no existe sin su trámite |
| `recibos.tramite_id` → `tramites` | `SET NULL` | **El recibo sobrevive al trámite**: guarda su copia congelada de nombre, CI y monto |
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
| `carnet_rubro.capacidad_kg` | `tramites.capacidad_kg` | Al aprobar | El cupo autorizado es el de esa aprobación, no el de la siguiente |
| `recibos.beneficiario_nombre`, `beneficiario_ci`, `monto`, `detalle` | `beneficiarios` y `pagos` | Al emitir el recibo | El papel que se llevó el pescador dice eso, y tiene que poder reimprimirse idéntico años después |

La regla general: **un documento emitido guarda su propio texto**, no una referencia a algo que puede cambiar.

---

## 5. Estados: los dominios de valores

Todos van en columnas `varchar`, nunca en tipos `ENUM` nativos de PostgreSQL — así agregar un estado no exige `ALTER TYPE` ni bloquear la tabla. Los valores válidos los definen los enums de PHP en `app/Enums/`.

| Tabla.columna | Valores | Enum |
| --- | --- | --- |
| `tramites.estado` | `pendiente`, `en_revision`, `aprobado`, `rechazado` | `EstadoTramite` |
| `tramites.tipo_tramite` | `emision_inicial`, `adicion_rubro` | `TipoTramite` |
| `carnets.estado` | `vigente`, `vencido`, `anulado` | `EstadoCarnet` |
| `carnet_rubro.estado` | `habilitado`, `suspendido` | `EstadoHabilitacion` |
| `rubros.estado` | `activo`, `inactivo` | `EstadoRubro` |
| `recibos.forma_pago` | `deposito`, `efectivo` | `FormaPago` |
| `recibos.descripcion` | `permiso_faena`, `importe_alevines`, `aprovechamiento_pesquero`, `guia_transporte`, `cedulas`, `otros` | `ConceptoRecibo` |

### El ciclo de vida del trámite

```mermaid
stateDiagram-v2
    [*] --> pendiente : registrar
    pendiente --> en_revision : enviar — nace el RECIBO
    pendiente --> [*] : eliminar, con motivo
    en_revision --> aprobado : aprobar — nace CARNET_RUBRO
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

`correlativos` y `configuraciones` son **tablas sueltas**, sin relaciones. La primera es el contador del talonario de recibos; la segunda, pares clave-valor con los parámetros del sistema.

---

## 7. El recorrido completo, un paso por línea

1. Se registra un **beneficiario**, o se busca uno existente por CI.
2. Se presenta un **trámite** pidiendo un **rubro**. El sistema decide solo si es `emision_inicial` —y crea el **carnet**— o `adicion_rubro` —y reutiliza el del año en curso—.
3. Se cargan uno o más **pagos** con su boleta escaneada, hasta cubrir `monto_requerido`.
4. Se **envía a revisión**: en la misma transacción nace el **recibo** numerado, y el pescador se va con ese papel.
5. Se **aprueba**: nace la fila en **carnet_rubro** y recién ahí la persona queda habilitada.
6. Se **imprime** el carnet (`fecha_generacion`) y se **entrega** (`fecha_entrega`).

---

Ver también: [ARQUITECTURA.md](ARQUITECTURA.md) · [modulos/CARNETS.md](modulos/CARNETS.md) · [modulos/RECIBOS.md](modulos/RECIBOS.md)
