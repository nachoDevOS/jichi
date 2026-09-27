# Arquitectura de Jichi

> **Reescrito el 27/09/2026 sobre el núcleo del 18/09/2026.** El modelo
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
Tailwind 4 · PostgreSQL 18** (corre también en SQLite; las pruebas usan SQLite
en memoria).

| Dato | Valor |
| --- | --- |
| Líneas de código | ~37.000 (`app/` + `resources/js/`) |
| Pruebas | 25, en `tests/Feature/Aprovechamientos/` (ver §8) |
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

   Los cuatro —autorización, carnet, faena y guía— se cobran con la MISMA
   tabla `pagos`, y el papel que se entrega es el RECIBO.
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
  persona**: la que está pendiente, en revisión o aprobada, en fecha, ocupa el
  lugar. Lo libera vencer, agotarse o ser revocada.
- **Un carnet vigente por actividad**. Para reponer uno perdido se revoca y se
  emite otro con la misma autorización.
- **El pescador no saca carnet sin autorización; el comercializador nunca lleva
  una.**

El detalle, con ejemplos, está en [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md).

---

## 2. El circuito, igual para los cuatro documentos

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ vencido / revocado / agotado
(borrador)  ▲                │   └── al enviar sale el RECIBO
   │        └──[rechazar]────┘
   └──[eliminar, con motivo]
```

| Estado | Editar | Eliminar | Cargar pagos | Enviar | Aprobar / Rechazar |
| --- | :-: | :-: | :-: | :-: | :-: |
| **Pendiente** | ✔ | ✔ | ✔ | ✔ | ✘ |
| **En revisión** | ✘ | ✘ | ✘ | ✘ | ✔ |
| **Aprobado** | ✘ | ✘ | ✘ | ✘ | ✘ |

- **Pendiente es un borrador**: se corrige y, si sobra, se elimina con motivo.
  Editar y eliminar exigen además que no haya ningún depósito cargado.
- **Enviar exige el monto cubierto**, y ahí sale el recibo con número propio.
- **Aprobar exige todas las boletas validadas.** Recién aprobado el documento
  vale, se imprime y escribe sus fechas de vigencia.
- **Rechazar devuelve a pendiente**, con el motivo en `auditorias`. No hay un
  estado «rechazado» final.
- **Revocar** (autorización y carnet) es de supervisión, con motivo, y no se
  revierte. Revocar la autorización arrastra sus carnets y faenas vigentes.
- La **guía** además se **cierra** al llegar la carga o se **anula**; la
  **autorización** además se **agota** cuando lo consumido llega al total.

El circuito lo dicen los enums (`EstadoAprovechamiento`, `EstadoCarnet`,
`EstadoFaena`, `EstadoGuia`) con `permiteEdicion()`, `permiteEliminacion()`,
`permiteEnvio()`, `permiteRevision()`… Lo aplican los servicios `Revisar*Service`
(enviar, aprobar, rechazar) y `Emitir*Service` / `OtorgarCupoService` (crear,
corregir, eliminar, revocar).

### El cobro y el control de las boletas

Los cuatro documentos usan el trait `Pagable` y la tabla polimórfica **`pagos`**
(`pagable_type` + `pagable_id`). Es una sola tabla porque `nro_transaccion` es
único **global**: partida en cuatro, la misma boleta podría pagar dos cosas.

```
depósito:  PENDIENTE ──▶ VALIDADO     cuadra con el extracto del banco
               ▲     └─▶ OBSERVADO    no cuadra, con motivo
               └──[corregir]──┘
```

Un depósito observado **sigue sumando** —el dinero entró; lo que está en duda es
el dato— y no se valida directo: primero se corrige. Quién cargó y quién validó
quedan en dos columnas distintas. Ver [modulos/PAGOS.md](modulos/PAGOS.md).

**El recibo** es una tabla (`recibos`) con correlativo continuo `000001`, que
Contabilidad audita por huecos, y monto y concepto congelados al emitir. Sale
**uno por trámite al enviar a revisión**; en **Caja** se cobra y se entrega en
el mismo acto, y un recibo puede cubrir varios trámites de la misma persona
(`CobrarService::cobrar()`). Ver [modulos/RECIBOS.md](modulos/RECIBOS.md).

### La verificación pública

Los cinco documentos que se entregan —autorización, carnet, faena, guía y
recibo— tienen un **código de 16 caracteres** al azar en la tabla polimórfica
`codigos`, único global, y lo llevan impreso en un QR. `/verificar/{codigo}`
muestra, sin sesión, el tipo de documento, el titular, la cédula enmascarada, el
estado de hoy y la vigencia.

El código **no reemplaza** a los correlativos (`numero_faena`, `numero_guia`,
`numero_recibo`, `nro_registro`): el correlativo es consecutivo para auditar
huecos; el código es imposible de adivinar para que nadie recorra el padrón.

---

## 3. El modelo de datos

El esquema completo, con el porqué de cada columna e índice, está en
[MER.md](MER.md). En una tabla:

| Tabla | Qué guarda | Lo que no es obvio |
| --- | --- | --- |
| `beneficiarios` | La persona | C.I. único con índice **parcial** (`WHERE deleted_at IS NULL`). Columnas en camelCase desde `primerNombre` |
| `aprovechamientos_pesq` | La bolsa madre, en kg | Hereda volumen y modalidad de la escala. Sin columna de saldo: consumido y reservado se calculan de las faenas |
| `carnets` | La credencial anual | `tipo_actor` decide qué emite. El de pescador lleva `aprovechamiento_id`; el de comercializador, NULL |
| `permisos_faena` | Una salida de pesca | Cuelga **solo** del carnet; llega a la autorización por `hasManyThrough` |
| `guias_movimiento` | Un traslado | Cuelga solo del carnet; copia la asociación porque va impresa |
| `guia_detalles` | El cuadro D, una fila por especie | La única FK CASCADE del dominio |
| `recibos` | El comprobante | Correlativo continuo; monto y concepto congelados |
| `pagos` | Cada depósito y su control | Polimórfica; `recibo_id` nullable hasta que el trámite emite su recibo |
| `codigos` | La llave pública de los cinco documentos | Polimórfica; único completo, un código impreso queda quemado |
| `asociaciones`, `categorias_aprovechamiento`, `tipos_carnet`, `departamentos` | Catálogos | Los edita la unidad desde el panel |

Infraestructura: `auditorias` (trait `Auditable`), `accesos`, `configuraciones`,
`correlativos` y las tablas de `spatie/laravel-permission`.

### Decisiones de esquema que sorprenden

- **Nada del dominio se borra de verdad**: todas las tablas tienen
  `softDeletes()`. Un índice único que la baja deba liberar es parcial; uno de
  papel entregado queda quemado. Tabulado en [MER.md](MER.md).
- **No hay columnas de saldo ni de monto pagado.** Lo pagado es la suma de
  `pagos`; el saldo de kilos es una resta sobre las faenas. Una columna así hay
  que mantenerla al día en cada alta y cada corrección, y el día que alguien se
  olvide el sistema cobra o autoriza mal sin avisar.
- **La faena y la guía cuelgan solo del carnet.** Una segunda clave hacia la
  persona abriría la puerta a que el papel y el carnet apunten a gente distinta.
- **El código de un documento no es una columna suya**: vive en `codigos`. Se
  lee con `codigo_legible` y necesita `with('codigo')` para no hacer N+1.

---

## 4. Las decisiones que explican el resto

### 4.1 Las reglas viven en `app/Services/`, nunca en el controlador

El mismo caso de uso lo necesitan el formulario, un comando de consola y las
pruebas. Escrito en el controlador, los otros dos lo copian y las copias se
quedan viejas. El controlador traduce HTTP, llama al servicio y convierte el
resultado —o la excepción del dominio: `CarnetInvalidoException`,
`CupoInvalidoException`, `PermisoOperativoException`, `CobroInvalidoException`—
en un redirect.

| Servicio | Qué hace |
| --- | --- |
| `OtorgarCupoService` | Otorgar, corregir y eliminar la autorización |
| `RevisarCupoService` | Enviar, aprobar, rechazar y **revocar** la autorización (con la cascada) |
| `EmitirCarnetService` | Emitir, corregir, eliminar, revocar y reponer el carnet |
| `RevisarCarnetService` | Enviar, aprobar y rechazar el carnet |
| `EmitirFaenaService` / `RevisarFaenaService` | Lo mismo para la faena; controla los kilos libres |
| `EmitirGuiaService` / `RevisarGuiaService` | Lo mismo para la guía, más cerrar y anular |
| `CobrarService` | Cargar depósitos y emitir recibos; la Caja |
| `ControlarPagoService` | Validar, observar y corregir una boleta |
| `CorrelativoService` | Los números correlativos, con la fila del contador bloqueada |
| `CodigoService` | El código de 16 caracteres |

### 4.2 Los enums son la única fuente de verdad

Estados, tipos, roles, permisos y **transiciones** viven en `app/Enums/`. El
flujo es siempre el mismo:

```
enum  ──▶  servicio pregunta  ──▶  controlador manda  ──▶  React recibe `puede_*`
```

React **nunca** recalcula una regla: la ficha recibe `puede_enviarse`,
`puede_revisarse`, `puede_editarse`… ya resueltos. Un `if ($x->estado === …)`
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
| 3. Índice único | La base rechaza el duplicado pase lo que pase | `pagos.nro_transaccion`, `codigos.codigo`, los números correlativos |

> **Trampa:** releer con `lockForUpdate()` devuelve **otra instancia**. Se
> escribe sobre la copia bloqueada y se devuelve `$modelo->refresh()` —la
> original—, o quien llamó se queda con el estado viejo en memoria.

### 4.4 El disco no participa de la transacción

Un rollback borra filas pero no archivos. Por eso los adjuntos —la cédula y el
aval del carnet, la boleta de cada pago— se **suben antes** de abrir la
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
| `permisos_faena.monto`, `guias_movimiento.monto` | El arancel del día en que se emitió (con el descuento de piscicultura) |
| `recibos.monto_total`, `recibos.concepto` | Lo que dice el papel entregado |
| `guias_movimiento.asociacion_id` | El aval impreso en la guía |
| `aprovechamientos_pesq.volumen_total_kg`, `modalidad` | Lo que se otorgó, aunque la escala cambie |

> **Lo que NO está congelado:** el arancel de la autorización y del carnet se
> lee del catálogo en vivo (`valor_bs` de la escala, `precio_bs` del tipo). Por
> eso no se puede cambiar el tipo de un carnet con cobros cargados. Y el titular
> del recibo se lee del padrón al imprimir: corregir un apellido cambia la
> reimpresión. Ver [MER.md](MER.md), tabla `recibos`.

### 4.7 Panel y público no se mezclan

| | Panel (con sesión) | Público (sin sesión) |
| --- | --- | --- |
| Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` |
| Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` |
| Pantallas | `pages/panel/` | `pages/publico/` (`inicio`, `verificar`) |
| Componentes | `components/panel/` | `components/publico/` |

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

`RolSistema` reparte los permisos en bloques pensados para dos personas:

| Bloque | Qué hace |
| --- | --- |
| Lectura | Ver cada módulo |
| **Ventanilla** (operación) | Cargar, corregir el borrador, cobrar, enviar a revisión, cerrar guías, imprimir, corregir depósitos |
| **Supervisión** | Aprobar, rechazar, validar u observar boletas, eliminar, revocar, anular |
| Administración | Catálogos, usuarios, configuración |

> ⚠️ **Hoy existe un solo rol, `administrador`, con todos los permisos**: la
> misma persona puede cargar y aprobar. Las rutas ya están separadas, así que
> agregar el rol de ventanilla es una línea en `RolSistema`. Además
> `pagos.revisor_distinto` (configurable, apagado por defecto) exige que quien
> valida una boleta no sea quien la cargó.

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
| Caja, recibos y pagos | ✅ | `CajaController`, `ReciboController`, `PagoController`, `CobrarService` | [modulos/PAGOS.md](modulos/PAGOS.md), [modulos/RECIBOS.md](modulos/RECIBOS.md) |
| Catálogos | ✅ | `AsociacionController`, `CategoriaAprovechamientoController`, `TipoCarnetController` | — |
| Verificación pública | ✅ | `VerificacionController` | [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md), paso 7 |
| Reportes | ❌ No existe | — | [PENDIENTES.md](PENDIENTES.md) |
| Configuración | ❌ No existe (la tabla sí) | — | [PENDIENTES.md](PENDIENTES.md) |

El menú lateral agrupa: **Ventanilla** (Beneficiarios, Aprov. Pesquero, Carnets,
Faenas, Guías), **Caja** (Cobros, Recibos), **Catálogos** (Asociaciones, Escala,
Tipos de carnet) y **Administración** (Reportes, Configuración). Vive en
`components/panel/layout/navegacion.ts`.

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
php artisan test        # las 25 pruebas
```

**Las pruebas cubren poco**: la revocación de la autorización (19 casos) y la
reserva de kilos de la faena (6). `tests/Feature/` se vació el 14/09/2026 por
pedido del responsable; el resto de las reglas no lo comprueba nada
automáticamente. **Todo cambio se prueba además abriendo la pantalla en el
navegador**, incluidos los casos borde.

Al agregar una migración, un permiso a `RolSistema` o una clave a
`ConfiguracionSeeder`, hay que correr a mano `php artisan migrate` y
`php artisan db:seed --class=RolPermisoSeeder`: las pruebas arman su propio
esquema en memoria y no avisan si la base de trabajo quedó vieja.

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
