# Arquitectura de Jichi

> **Reescrito el 27/09/2026 sobre el núcleo del 18/09/2026; al día con el código
> el 03/10/2026.** El modelo
> anterior —`rubros`, `tramites`, `faenas`, `guias`, el recibo armado al vuelo—
> ya no existe; si hace falta leerlo, está en el historial de git.
>
> **Este archivo reemplaza a leer el código.** Si venís a entender el sistema,
> leé esto y [MAPA-ARCHIVOS.md](MAPA-ARCHIVOS.md) antes de abrir un solo `.php`.

| Para | Leé |
| --- | --- |
| **Qué** tiene que hacer el sistema (manda sobre el código) | [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md) |
| **Cómo** está construido, en general | este archivo |
| Las tablas, columna por columna, y el porqué | [MER.md](MER.md) |
| Un módulo en detalle | [modulos/](modulos/) |

Sistema de credenciales y permisos de pesca del Gobierno Autónomo Departamental
del Beni (SEDAG). **Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript ·
Tailwind 4 · PostgreSQL 18** (corre también en SQLite).

| Dato | Valor |
| --- | --- |
| Líneas de código | ~37.000 (`app/` + `resources/js/`) |
| Pruebas | No hay: se retiraron el 27/09/2026 (ver §9) |
| Idioma del código | Español, sin excepciones salvo `components/ui/` |
| Pendiente | Reportes y Configuración (en gris en el menú) |

---

## 1. El dominio: dos ramas que salen de la misma persona

Todo nace en el **beneficiario**, registrado una sola vez por su C.I. De ahí
salen **dos ramas**, según la actividad:

```
                              BENEFICIARIO
                  pescador ┌──────┴──────┐ comercializador
                           ▼             ▼
   AUTORIZACIÓN DE PESCA PARA            CARNET (comercializador)
   APROVECHAMIENTO PESQUERO                    │  sin autorización
   (la bolsa madre: un cupo en kg)             ▼
                 │                       GUÍA ÚNICA DE TRANSPORTE
                 ▼                       (una por traslado, 5 días)
        CARNET (pescador)                      └──< detalle por especie
                 │
                 ▼
        PERMISO DE FAENA
        (una por salida, 30 días;
         reserva y descuenta kg de la autorización)

   Los cuatro —autorización, carnet, faena y guía— se PAGAN EN SIREB; al
   confirmarse el pago quedan aprobados y se entrega el RECIBO.
```

| Rama | Documento anual | Permiso de cada día | Controla kilos |
| --- | --- | --- | --- |
| **Pescador** | Autorización de Pesca para Aprovechamiento Pesquero + carnet de pescador | Permiso de faena | **Sí**: la faena reserva y descuenta del cupo |
| **Comercializador** | Carnet de comercializador | Guía única de transporte | No |

**EL CARNET ES LA LLAVE ANUAL; CON ÉL SOLO NO SE SALE A TRABAJAR.** Lo que
autoriza el trabajo de cada día son la faena y la guía, y se emiten muchas por
gestión.

Qué emite cada carnet lo dice **`carnets.tipo_actor`** (`TipoActor::emiteFaenas()`
/ `emiteGuias()`), nunca el nombre del tipo de carnet: ese catálogo lo edita la
unidad desde el panel.

### Las reglas que ordenan todo

- **Una Autorización de Pesca para Aprovechamiento Pesquero a la vez por
  persona**: la que está pendiente o aprobada, en fecha, ocupa el lugar. Lo
  libera pasar su fecha, agotarse o ser revocada.
- **Un carnet vigente por actividad**. Para reponer uno perdido se revoca y se
  emite otro con la misma autorización.
- **El pescador no saca carnet sin autorización; el comercializador nunca lleva
  una.**

El detalle, con ejemplos, está en [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md).

---

## 2. El circuito, igual para los cuatro documentos

```
            ┌─(SIREB: liquidación «pagada»)──▶ APROBADO ──▶ revocado / agotado
PENDIENTE ──┤                                     └── al aprobar sale el RECIBO
(borrador)  └─(SIREB: «vencida»)──▶ sigue PENDIENTE (08/10/2026; antes NO PAGADO)
   └──[corregir / eliminar: consulta SIREB y anula la liquidación]
```

*(02/10/2026: ya no hay `en_revision`, ni firma, ni tabla `pagos`. 03/10/2026: ya
no hay `vencido`. 08/10/2026: tampoco `no_pagado`, ni `completado` en la faena.)*

| Estado | Editar | Eliminar | Cargar pago | Verificar pago |
| --- | :-: | :-: | :-: | :-: |
| **Pendiente**, sin pago en SIREB | ✔ | ✔ | ✔ | ✔ |
| **Pendiente**, con pago en revisión | ✘ (*) | ✘ | ✘ | ✔ |
| **Aprobado** | ✘ | ✘ | ✘ | ✘ |

(*) La autorización se corrige igual —solo la embarcación, sin tocar el cobro—;
carnet, faena y guía no, si la corrección cambia lo que se cobra.

- **Pendiente es un borrador**: se corrige y, si sobra, se elimina con motivo.
  **Antes de eliminar se consulta la liquidación en SIREB**: solo se elimina si
  no tiene ningún pago cargado. Con un pago en revisión no se borra; con el pago
  validado, en vez de borrar **se aprueba** en ese momento.
- **Pagado en SIREB = aprobado.** `ConfirmarPagoService` lo aprueba y emite el
  recibo cuando SIREB da la liquidación por `pagada`. Recién aprobado el
  documento vale, se imprime y escribe sus fechas de vigencia.
- **Dos plazos que no se mezclan.** El de **pago** lo pone SIREB
  (`plazo_pago_dias`, 5 días): si la liquidación vence, el trámite **sigue
  pendiente** (08/10/2026) y se elimina a mano si ya no corresponde. La **vigencia** del trámite
  (autorización y carnet al 31/12, faena 30 días, guía 5) **no es un estado**: un
  aprobado sigue `aprobado` y si vale hoy lo dicen sus fechas (`estaVigente()`).
- **Revocar** (los cuatro trámites) es de supervisión, con motivo, y no se
  revierte. Revocar la autorización deja **sin efecto** a sus carnets y faenas
  sin reescribirlos: su vigencia mira al padre.
- La **guía** además se **anula**; no se registra su llegada: vence a los 5 días. La
  **autorización** además se **agota** cuando lo consumido llega al total.

El circuito lo dicen los enums (`EstadoAprovechamiento`, `EstadoCarnet`,
`EstadoFaena`, `EstadoGuia`) con `permiteEdicion()`, `permiteEliminacion()`,
`estaAbierto()`… Lo aplican `Emitir*Service` / `OtorgarCupoService` (crear,
corregir, eliminar, revocar), `Revisar*Service::aprobar()` (las reglas propias
de cada documento al aprobarse) y `ConfirmarPagoService` (pregunta a SIREB).

### El cobro: en SIREB

Cada documento registra su **liquidación** en SIREB al crearse (trait
`LiquidableSireb`, columnas `sireb_*`, `LiquidarSirebService`; la
`Idempotency-Key` se guarda antes de llamar). El titular paga y el pago se carga
en SIREB —allá, o desde la ficha de Jichi con **«Cargar pago»** (N° de
transacción y banco), solo si la liquidación está pendiente y sin pago—. El
encargado de SIREB lo valida. Jichi pregunta con `GET /liquidaciones/{id}` —botón
«Verificar pago» y `jichi:verificar-pagos` cada 10 minutos—: `pagada` → aprueba;
`vencida` → sigue pendiente, con aviso. La ficha muestra el pago informado («Por
validar» / «Validado»). Ver [modulos/SIREB.md](modulos/SIREB.md).

**El recibo** es una tabla (`recibos`), uno por documento (`recibible`), con
correlativo continuo `000001` que Contabilidad audita por huecos. Se emite al
aprobar y congela monto, concepto y el pago de SIREB (N° de transacción, banco,
fecha).
Ver [modulos/RECIBOS.md](modulos/RECIBOS.md).

### La verificación pública

Los cinco documentos que se entregan —autorización, carnet, faena, guía y
recibo— tienen un **código de 16 caracteres** al azar en la tabla polimórfica
`codigos`, único global, y lo llevan impreso en un QR. `/verificar/{codigo}`
muestra, sin sesión, el tipo de documento, el titular, la cédula enmascarada, el
estado de hoy y la vigencia.

El código **no reemplaza** a los correlativos —la columna `nro` de autorización,
carnet, faena y guía, y `numero_recibo`; todos de seis dígitos—: el correlativo es consecutivo para auditar
huecos; el código es imposible de adivinar para que nadie recorra el padrón.

---

## 3. El modelo de datos

El esquema completo, con el porqué de cada columna e índice, está en
[MER.md](MER.md). En una tabla:

| Tabla | Qué guarda | Lo que no es obvio |
| --- | --- | --- |
| `beneficiarios` | La persona | C.I. único con índice **parcial** (`WHERE deleted_at IS NULL`). Columnas en camelCase desde `primerNombre` |
| `aprovechamientos_pesq` | La bolsa madre, en kg | `nro` correlativo propio (no el id). Hereda volumen y modalidad de la escala. Sin columna de saldo: consumido y reservado se calculan de las faenas. Columnas `sireb_*` de su liquidación, igual que carnet, faena y guía |
| `carnets` | La credencial anual | `tipo_actor` decide qué emite. El de pescador lleva `aprovechamiento_id`; el de comercializador, NULL |
| `permisos_faena` | Una salida de pesca | Cuelga **solo** del carnet; llega a la autorización por `hasManyThrough` |
| `guias_movimiento` | Un traslado | Cuelga solo del carnet; copia la asociación porque va impresa |
| `guia_detalles` | El cuadro D, una fila por especie | La única FK CASCADE del dominio |
| `recibos` | El comprobante, uno por documento pagado | Polimórfico (`recibible`); correlativo continuo; monto, concepto y boleta de SIREB congelados |
| `codigos` | La llave pública de los cinco documentos | Polimórfica; único completo, un código impreso queda quemado |
| `asociaciones`, `categorias_aprovechamiento`, `tipos_carnet`, `productos_hidrobiologicos`, `departamentos` | Catálogos | Los edita la unidad desde el panel. Los productos llenan el cuadro D de la guía |

Infraestructura: `auditorias` (trait `Auditable`), `accesos`, `configuraciones`,
`correlativos` y las tablas de `spatie/laravel-permission`.

### Decisiones de esquema que sorprenden

- **Nada del dominio se borra de verdad**: todas las tablas tienen
  `softDeletes()`. Un índice único que la baja deba liberar es parcial; uno de
  papel entregado queda quemado. Tabulado en [MER.md](MER.md).
- **No hay columnas de saldo.** Lo pagado está en SIREB (y en el recibo); el
  saldo de kilos es una resta sobre las faenas. Una columna así hay
  que mantenerla al día en cada alta y cada corrección, y el día que alguien se
  olvide el sistema cobra o autoriza mal sin avisar.
- **La faena y la guía cuelgan solo del carnet.** Una segunda clave hacia la
  persona abriría la puerta a que el papel y el carnet apunten a gente distinta.
- **El código de un documento no es una columna suya**: vive en `codigos`. Se
  lee con `codigo_legible` y necesita `with('codigo')` para no hacer N+1.

---

## 4. Las decisiones que explican el resto

### 4.1 Las reglas viven en `app/Services/`, nunca en el controlador

El mismo caso de uso lo necesitan el formulario y un comando de consola.
Escrito en el controlador, el otro lo copia y la copia se queda vieja. El controlador traduce HTTP, llama al servicio y convierte el
resultado —o la excepción del dominio: `CarnetInvalidoException`,
`CupoInvalidoException`, `PermisoOperativoException`, `CuentaPortalException`, y
`SirebException` cuando SIREB frena— en un redirect.

| Servicio | Qué hace |
| --- | --- |
| `OtorgarCupoService` | Otorgar, corregir (solo la embarcación) y eliminar la autorización |
| `CargarPagoService` | Cargar en SIREB el pago de un trámite (N° de transacción y banco), si la liquidación está pendiente y sin pago |
| `RevisarCupoService` | Aprobar y **revocar** la autorización (sin cascada: ver REGLAS-NEGOCIO, Regla 5) |
| `EmitirCarnetService` | Emitir, corregir, eliminar, revocar y reponer el carnet |
| `RevisarCarnetService` | Aprobar el carnet (número de registro, vigencia) |
| `EmitirFaenaService` / `RevisarFaenaService` | Lo mismo para la faena; controla los kilos libres |
| `EmitirGuiaService` / `RevisarGuiaService` | Lo mismo para la guía; las dos con revocar |
| `LiquidarSirebService` | La liquidación en SIREB: preparar, enviar y anular |
| `ConfirmarPagoService` | Pregunta a SIREB: pagado → aprueba y emite el recibo; vencido → solo avisa, sigue pendiente. Guarda el pago informado |
| `RenovarLiquidacionService` | «Generar nueva liquidación»: la vencida sin pago pasa a `sireb_historial` y se pide otra con la tarifa vigente del catálogo (08/10/2026) |
| `CorrelativoService` | Los números correlativos, con la fila del contador bloqueada |
| `CodigoService` | El código de 16 caracteres |

### 4.2 Los enums son la única fuente de verdad

Estados, tipos, roles, permisos y **transiciones** viven en `app/Enums/`. El
flujo es siempre el mismo:

```
enum  ──▶  servicio pregunta  ──▶  controlador manda  ──▶  React recibe `puede_*`
```

React **nunca** recalcula una regla: la ficha recibe `puede_editarse`,
`puede_eliminarse`, `puede_verificar_pago`, `puede_cargar_pago`… ya resueltos. Un `if ($x->estado === …)`
suelto en un controlador es la señal de que la regla se está duplicando.

Los enums van en columnas `string`, nunca tipos ENUM de PostgreSQL: agregar un
estado no exige `ALTER TYPE`. **Pero agregar un estado rompe cosas que no se
ven**: los scopes y los helpers que enumeran estados lo dejan afuera en
silencio. La lista de lugares a revisar está en «Trampas conocidas» de
[CLAUDE.md](../CLAUDE.md).

### 4.3 La concurrencia se resuelve en tres capas

| Capa | Qué hace | Ejemplo |
| --- | --- | --- |
| 1. Bloqueo | `lockForUpdate()` sobre la fila que contiene el recurso escaso | El beneficiario al emitir un carnet; la autorización al emitir o aprobar una faena; el contador del correlativo |
| 2. Recomprobación | La regla corre **otra vez** dentro de la transacción | El estado del documento, los kilos libres |
| 3. Índice único | La base rechaza el duplicado pase lo que pase | `codigos.codigo`, `sireb_idempotency_key`, los números correlativos |

> **Trampa:** releer con `lockForUpdate()` devuelve **otra instancia**. Se
> escribe sobre la copia bloqueada y se devuelve `$modelo->refresh()` —la
> original—, o quien llamó se queda con el estado viejo en memoria.

### 4.4 El disco no participa de la transacción

Un rollback borra filas pero no archivos. Por eso los adjuntos —la cédula y el
aval del carnet— se **suben antes** de abrir la
transacción, y el `catch` los borra si algo falla. Al borrar, al revés: primero
la transacción, después los archivos.

### 4.5 Todo archivo pasa por `StorageController::file()`

Es el **único** lugar que escribe en disco: tope de **3 MB**, nombre aleatorio
(el del usuario no se conserva nunca) y el disco que corresponde, local o s3.
Nunca `->store()`, `->storeAs()` ni `Storage::put()` en otra clase. Para armar un
enlace se pasa siempre por `Archivos::url()`.

### 4.6 Lo que se entregó, se congela

Algo que ya salió del mostrador no puede cambiar retroactivamente, así que se
copia en vez de leerse por relación:

| Dónde | Qué congela |
| --- | --- |
| `permisos_faena.monto` | El arancel del día en que se emitió |
| `guias_movimiento.monto`, `guia_detalles.precio_kg` | El total del cuadro D y la tasa de cada producto al emitir (con el descuento de piscicultura) |
| `recibos.monto_total`, `concepto`, `numero_boleta` (N° de transacción), `entidad_bancaria`, `fecha_pago` | Lo que dice el papel entregado, con el pago tal como lo validó SIREB |
| `guias_movimiento.asociacion_id` | El aval impreso en la guía |
| `aprovechamientos_pesq.volumen_total_kg`, `modalidad` | Lo que se otorgó, aunque la escala cambie |
| `aprovechamientos_pesq.monto`, `sireb_tarifa_id` | El precio que dio SIREB al otorgar, solo si la tarifa y el servicio están activos y la tarifa es liquidable (ver [modulos/SIREB.md](modulos/SIREB.md#validación-de-la-tarifa-al-emitir)) |
| `carnets.monto`, `permisos_faena.monto`, `guia_detalles.precio_kg` (+ `sireb_tarifa_id`) | El precio de SIREB al emitir (30/09/2026), solo si la tarifa y el servicio están activos (02/10/2026) |

> **Lo que NO está congelado:** el titular del recibo se lee del padrón al
> imprimir: corregir un apellido cambia la reimpresión. Ver [MER.md](MER.md),
> tabla `recibos`. (Hasta el 30/09/2026 el arancel del carnet se leía en vivo
> del catálogo; hoy se congela al emitir.)

### 4.7 Panel, público y portal no se mezclan

| | Panel (funcionarios) | Público (sin sesión) | Portal (beneficiario) |
| --- | --- | --- | --- |
| Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` | `routes/portal.php`, prefijo `/mi-cuenta` |
| Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` | `Http/Controllers/Portal/` |
| Pantallas | `pages/panel/` | `pages/publico/` (`inicio`, `verificar`) | `pages/portal/` |
| Componentes | `components/panel/` | `components/publico/` | `components/portal/` |

El **portal** (28/09/2026) es de solo lectura: el beneficiario entra con su C.I.
y consulta sus papeles y pagos. Su cuenta es una fila de `users` con
`beneficiario_id`, y eso la deja fuera del panel. Ver
[modulos/PORTAL.md](modulos/PORTAL.md).

La mitad pública tiene dos pantallas: la **portada** (`/`), cuyo texto sale de
`configuraciones`, y la **verificación** (`/verificar/{codigo?}`). No puede
exponer datos personales completos ni ids internos: ver
`VerificacionController::datosPublicos()`.

---

## 5. Cómo viaja una petición

No hay API REST: no se escribe `fetch()` ni `axios`.

```
1. Navegador      GET /panel/faenas
2. routes/panel.php    elige el controlador y exige el permiso
3. Controlador         consulta con Eloquent
4. Inertia::render('panel/faenas/index', [...datos...])
5. React recibe los datos como PROPS de pages/panel/faenas/index.tsx
```

De vuelta, React usa `useForm().post()` / `router.patch()`; el controlador
responde con un `redirect()`, nunca con JSON. Las props compartidas
(`HandleInertiaRequests::share()`) son `auth`, `institucion`, `archivos`,
`flash`, `ziggy` y `apariencia`: una prop de página con uno de esos nombres la
tapa sin avisar.

---

## 6. Seguridad

**Los permisos se declaran en las rutas** con el middleware `permiso:`. Esconder
un botón con `usePermisos()` es solo comodidad; siempre van los dos.

**El catálogo es `RolSistema::CATALOGO`** (05/10/2026): módulo → nombre, sección del
menú y acciones, en el orden del menú y del trámite. Es la única fuente: de ahí salen la lista
completa, los nombres y las secciones del formulario de roles. 62 permisos:

| Sección | Módulo | Acciones |
| --- | --- | --- |
| General | Panel | ver |
| Ventanilla | Beneficiarios | ver, crear, editar, portal, eliminar |
| Ventanilla | Autorizaciones, faenas, guías | ver, crear, editar, verificar-pago, cargar-pago, renovar-liquidacion, imprimir, eliminar, revocar |
| Ventanilla | Carnets | lo mismo (reponer va con `crear`) |
| Pagos | Recibos | ver, imprimir |
| Parámetros | Asociaciones | ver, crear, editar |
| Catálogos | Catálogos (los cuatro) | ver, crear, editar |
| Administración | Reportes · Auditoría · Configuración | ver, exportar · ver · ver, editar |
| Seguridad | Usuarios · Roles | ver, crear, editar · ver, crear, editar, eliminar |

**Un permiso por acción real**: si la pantalla tiene listar, crear y editar, son `ver`,
`crear` y `editar`; nada de un `gestionar` que cubra varias. Reportes, auditoría,
configuración, `usuarios.crear` y `usuarios.editar` están declarados sin ruta, a la espera
de su módulo.

**Seguridad › Usuarios** (`/panel/seguridad/usuarios`, `usuarios.ver`, 05/10/2026): todas
las cuentas —funcionarios primero, con su rol; después los beneficiarios, con rol
«Beneficiario»—, con estado, último ingreso y desde cuándo, filtros por tipo y estado, y
búsqueda por nombre, C.I. o correo. Solo consulta: al beneficiario se le da, resetea y
desactiva el acceso en su ficha (`beneficiarios.portal`). El estado (activa, clave temporal,
desactivada) no es columna: sale de `activo` y `debe_cambiar_password` en
`UsuarioController`. Crear y editar funcionarios todavía es por consola.
`RolPermisoSeeder` borra de la base el permiso que sale del enum, y con él su asignación en
los roles armados desde el panel.

**Seguridad › Roles** (`/panel/seguridad/roles`, `roles.*`): la unidad crea, edita
y elimina roles; la lista dice cuántos permisos y usuarios tiene cada uno, y los permisos
se marcan módulo por módulo al crear o editar.
**Los permisos son código** (`RolSistema`, atados a las rutas); **los roles, datos**.
`administrador` es la excepción: lo siembra `RolPermisoSeeder` con todos y el panel no lo
toca. Las reglas están en `GestionarRolService`: el rol del sistema no se modifica, un rol
con usuarios no se borra, el nombre no repite a otro ni a uno del sistema (sin distinguir
mayúsculas). `roles` es tabla de Spatie, sin `SoftDeletes`: la baja es real y lo que tenía
queda en el motivo de la auditoría; los cambios de permisos se anotan a mano
(`syncPermissions` no dispara eventos). Una acción nueva necesita además su nombre en
`RolSistema::ACCIONES`, o la pantalla muestra la clave cruda.

> Aprobar no es de nadie: lo decide el pago validado en SIREB. Los demás perfiles
> (ventanilla, supervisión) se arman desde el panel; falta la pantalla de Usuarios
> para asignarlos.

**Lo que un rol a medida necesita para no chocar con un 403** (auditoría del 05/10/2026):

- **Toda acción arrastra el `ver` de su módulo** (`RolSistema::requiere()`): al terminar se
  vuelve a la ficha. Lo completa `GestionarRolService` al guardar y el formulario lo
  marca en cadena.
- **Reponer un carnet no tiene permiso propio**: va con `carnets.crear` (decidido el
  05/10/2026). Ojo: reponer **revoca** el carnet actual, así que quien registra carnets
  puede, por esta vía, dejar sin efecto uno sin tener `carnets.revocar`.
- **Al entrar se va a la primera pantalla que el rol puede ver** (`User::rutaInicio()`), no
  al tablero. Una cuenta sin ningún permiso no entra: el login le dice que pida un rol.
- **El buscador de beneficiarios** lo abre `beneficiarios.ver` o cualquier `*.crear` de los
  cuatro trámites: emitir exige elegir a la persona.
- **Los enlaces entre módulos** de las fichas (titular, carnet, autorización, faenas, guías) y
  el «Inicio» de las migas preguntan el permiso: sin él queda el texto
  (`EnlacePermitido`) o el botón no aparece. En el tablero, los pendientes y los avisos
  muestran el número igual, pero el enlace al listado llega `null` si el rol no ve ese
  módulo (`DashboardController::siPuedeVer()`).
- **Eliminar** se ofrece solo con el trámite `pendiente` y sin pago informado en la última
  consulta (`sinPagoInformado()`); el servicio igual vuelve a preguntar a SIREB antes de borrar.

**Login:** con `IBARE_ACTIVO=true` se entra con la cuenta del GAD por OAuth2
(Ibare) y el login por correo queda solo para administradores. Ver
[modulos/IBARE.md](modulos/IBARE.md).

**Los pasos del circuito son PATCH o POST, nunca GET**: un verbo de lectura que
escribe se dispara con solo precargar el enlace. Cada paso tiene su ruta y su
permiso.

**La verificación pública** tiene `throttle:60,1` en la consulta y
`throttle:20,1` en el formulario, contra la fuerza bruta de códigos.

---

## 7. PostgreSQL y SQLite

- **SQL específico de motor** va en `app/Support/Sql.php`.
- **Scopes con `qualifyColumn()`**: `aprovechamientos_pesq`, `carnets`,
  `permisos_faena` y `guias_movimiento` tienen todas una columna `estado`, y las
  consultas las cruzan con `join`.
- **camelCase obliga a entrecomillar** en SQL escrito a mano:
  `SELECT "primerNombre"`. Ver `Beneficiario::SQL_NOMBRE`.

---

## 8. Módulos

| Módulo | Estado | Entrada principal | Detalle |
| --- | --- | --- | --- |
| Acceso y bitácora | ✅ | `Auth/`, Ibare | [modulos/IBARE.md](modulos/IBARE.md) |
| Panel principal | ✅ | `DashboardController` | — |
| **Beneficiarios** | ✅ — **la plantilla a copiar** | `BeneficiarioController` | — |
| Autorización de Pesca para Aprovechamiento Pesquero | ✅ | `AprovechamientoController`, `OtorgarCupoService`, `RevisarCupoService` | [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md), paso 2 |
| Carnets | ✅ | `CarnetController`, `EmitirCarnetService` | [modulos/CARNETS.md](modulos/CARNETS.md) |
| Faenas y guías | ✅ | `FaenaController`, `GuiaController` | [modulos/PERMISOS-OPERATIVOS.md](modulos/PERMISOS-OPERATIVOS.md) |
| Cobro en SIREB y recibos | ✅ | `ConfirmarPagoService`, `LiquidarSirebService`, `ReciboController` | [modulos/SIREB.md](modulos/SIREB.md), [modulos/RECIBOS.md](modulos/RECIBOS.md) |
| Catálogos | ✅ | `AsociacionController`, `CategoriaAprovechamientoController`, `TipoCarnetController`, `ProductoHidrobiologicoController`, `ArancelSirebController` | [modulos/SIREB.md](modulos/SIREB.md) |
| Verificación pública | ✅ | `VerificacionController` | [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md), paso 7 |
| Portal del beneficiario (`/mi-cuenta`) | ✅ | `Controllers/Portal/`, `CuentaPortalService` | [modulos/PORTAL.md](modulos/PORTAL.md) |
| Reportes | ❌ No existe | — | [PENDIENTES.md](PENDIENTES.md) |
| Configuración | ❌ No existe (la tabla sí) | — | [PENDIENTES.md](PENDIENTES.md) |

El menú lateral agrupa: **Ventanilla** (Beneficiarios, Aprov. Pesquero, Carnets,
Faenas, Guías), **Pagos** (Recibos), **Catálogos** (Asociaciones, Tipos de
carnet, Productos, Escala, Aranceles) y **Administración** (Reportes, en gris).
Vive en `components/panel/layout/navegacion.ts`.

### El patrón a copiar

**Beneficiarios** es la plantilla del sistema. Para un módulo nuevo, copiar su
controlador, su Request, su bloque de `routes/panel.php`, sus pantallas y
componentes y su archivo de `types/`. Si el módulo tiene reglas de negocio de
verdad —no solo un CRUD—, copiar además el par `EmitirFaenaService` +
`PermisoOperativoException`. El paso a paso está en
[GUIA-INERTIA.md](GUIA-INERTIA.md).

---

## 9. Verificar antes de dar algo por terminado

```sh
npx tsc --noEmit        # tipos de TypeScript
./vendor/bin/pint       # formato del PHP
npm run build           # que el frontend compile
```

**No hay pruebas automáticas**: se retiraron el 27/09/2026 a pedido del
responsable; están en el historial de git (commit `7dc0ac6`). Los tres comandos
revisan tipos, formato y compilación, no reglas de negocio: **todo cambio se
prueba abriendo la pantalla y recorriendo el caso a mano.**

Al agregar una migración, un permiso a `RolSistema` o una clave a
`ConfiguracionSeeder`, hay que correr a mano `php artisan migrate` y
`php artisan db:seed --class=RolPermisoSeeder`: nada avisa si la base de
trabajo quedó vieja.

---

## 10. Trampas conocidas y dónde seguir

Las trampas que ya costaron un error real están en «Trampas conocidas» de
[CLAUDE.md](../CLAUDE.md), que es la lista completa y al día. No se duplican
acá para que no se separen.

| Documento | Para qué |
| --- | --- |
| [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md) | La especificación |
| [MER.md](MER.md) | Las tablas y el porqué de cada decisión |
| [MAPA-ARCHIVOS.md](MAPA-ARCHIVOS.md) | Qué hace cada archivo |
| [ESTRUCTURA.md](ESTRUCTURA.md) | Dónde va un archivo **nuevo** |
| [GUIA-INERTIA.md](GUIA-INERTIA.md) | Cómo se conectan Laravel y React |
| [PENDIENTES.md](PENDIENTES.md) | Qué falta y qué está roto |
| [NOTAS-CODIGO.md](NOTAS-CODIGO.md) | El porqué largo de una decisión puntual |
| `docs/diagramas/` | Los diagramas del flujo del negocio |
| `docs/sesiones/MM-AAAA/` | Bitácora de trabajo, un archivo por día |
