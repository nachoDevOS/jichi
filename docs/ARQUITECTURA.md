# Arquitectura de Jichi

> **Este archivo reemplaza a leer el código.** Si venís a entender el sistema
> —o sos un agente al que le pidieron analizarlo— leé este documento y
> [MAPA-ARCHIVOS.md](MAPA-ARCHIVOS.md) antes de abrir un solo `.php`. Solo abrí
> el código cuando necesites cambiar algo concreto, y entonces abrí ESE archivo.

Sistema de gestión de carnets, rubros y trámites del sector pesquero del
Gobierno Autónomo Departamental del Beni, Bolivia.

**Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript · Tailwind 4 ·
PostgreSQL 18** (corre también en SQLite; las pruebas usan SQLite en memoria).

| Dato | Valor |
| --- | --- |
| Líneas de código | ~16.500 (`app/` + `resources/js/`) |
| Pruebas | **Ninguna.** `tests/Feature/` se vació el 14/09/2026 (ver §8) |
| Idioma del código | Español, sin excepciones salvo `components/ui/` |
| Estado | 7 módulos completos, 2 pendientes (Reportes, Configuración) |

---

## 1. El dominio en una página

Una persona —el **beneficiario**— saca un **carnet** por cada **rubro** (cada
actividad que ejerce), y cada carnet es anual. Para obtenerlo presenta un
**trámite**, que se cubre con uno o varios **pagos**. Cuando el trámite se
aprueba, el carnet queda habilitado y recién ahí la persona puede trabajar en esa
actividad.

Quien pesca y además comercializa tiene **dos carnets** en la misma gestión, con
dos plásticos, dos firmas de validación y dos cupos autorizados.

```
beneficiario ──< carnet ──< tramite ──< pago
                   │           │
                   │           └── rubro   (el mismo del carnet)
                   │           └── recibo  (armado al vuelo, sin tabla)
                   │
                   ├── rubro               (la actividad que habilita)
                   │
                   ├──< faena ──< pago     (carnet de Pescador)
                   └──< guia  ──< pago     (carnet de Comercializador)
                           └──< guia_detalle   (especie, condición, kilos)
```

**EL CARNET ES LA LLAVE ANUAL; CON ÉL SOLO NO SE SALE A TRABAJAR.** Lo que
autoriza el trabajo de cada día son los **permisos operativos** que cuelgan de
él, y se emiten muchos por gestión:

| Carnet de… | Emite | Qué autoriza |
| --- | --- | --- |
| **Pescador** | **faenas** | UNA salida: esta embarcación, este comandante, de tal día a tal día, con tanto en kilos |
| **Comercializador** | **guías** | UN traslado: de dónde a dónde, en qué vehículo, con qué carga |

Cuál emite cada actividad lo dicen `rubros.emite_faenas` y `rubros.emite_guias`,
**nunca un `match` sobre el nombre del rubro** — el catálogo lo edita la unidad
desde el panel y el mismo rubro figura como «Pescador» o como «Faena» según
quién lo cargó. Es el mismo criterio de `requiere_capacidad`.

**Las tres cosas se cobran con la MISMA tabla `pagos`**, que por eso es
polimórfica. Ver §3.

### La regla que ordena todo

> **Una persona tiene como máximo UN carnet por RUBRO y por gestión.**

Está escrita como índice único en la base
—`carnets_beneficiario_rubro_gestion_unique`— y de ella salen los dos únicos
tipos de trámite:

| Situación | Tipo | Qué hace |
| --- | --- | --- |
| No tiene carnet **de ese rubro** este año | `emision_inicial` | Crea el carnet y le cuelga el trámite |
| Ya lo tiene | `actualizacion` | Reutiliza ese carnet; al aprobar consolida cupo y asociación |

**El tipo lo decide el sistema, no el operador.** Hay un solo formulario de alta;
`SolicitudCarnetService::registrar()` mira si la persona ya tiene carnet de ese
rubro y resuelve. Dejarlo a elección de ventanilla sería pedirle al operador que
adivine algo que la base ya sabe, y equivocarse ahí significa cobrar de menos o
intentar emitir un carnet duplicado.

#### Lo que este modelo reemplazó

Antes el carnet era **uno por persona y gestión**, y los rubros se le colgaban en
una tabla intermedia `carnet_rubro`: sumar una actividad hacía crecer ese carnet
y se llamaba «adición de rubro».

Esa tabla y el enum `EstadoHabilitacion` **ya no existen**. El carnet ES la
habilitación: la fila del pivote decía «este carnet habilita esta actividad,
desde esta fecha, con este cupo, en este estado», y hoy eso es exactamente lo que
dice la fila del carnet. Mantener las dos era guardar el mismo dato dos veces con
la posibilidad de que discreparan.

Consecuencias que conviene tener presentes:

- **«Adición de rubro» ya no describe nada.** Pedir una actividad más no agranda
  ningún carnet: emite otro, y eso es una emisión inicial.
- **Suspender es por carnet.** `EstadoCarnet` absorbió `suspendido`. A un
  pescador se le puede cortar el transporte sin quitarle la pesca porque son dos
  carnets distintos.
- **El plástico ahora imprime el rubro y el cupo.** Antes no podía —una adición
  dejaba vieja la lista— y ahora hace falta: sin el rubro, dos carnets de la
  misma persona son tarjetas idénticas.

### El circuito del expediente

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
(borrador)  ▲                │                         │
            │                │                         └── el carnet queda habilitado
            │                ├── nace el RECIBO OFICIAL    ← el papel del pescador
            │                └──[rechazar]──▶ RECHAZADO (con motivo escrito, obligatorio)
            └────────────────────[reabrir]───────┘
```

**PENDIENTE es un BORRADOR.** Ventanilla lo arma, lo corrige y lo borra si sobra;
nadie lo vio todavía. **EN REVISIÓN ya está presentado**: no se edita ni se
elimina, solo se resuelve. **RECHAZADO está en el mostrador del pescador**: se
REABRE cuando vuelve con lo corregido, y ahí es borrador otra vez.

| Estado | Editar | Eliminar | Enviar | Aprobar | Rechazar | Reabrir |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| **Pendiente** | ✔ | ✔ | ✔ | ✘ | ✘ | ✘ |
| **En revisión** | ✘ | ✘ | ✘ | ✔ | ✔ | ✘ |
| **Rechazado** | ✘ | ✘ | ✘ | ✘ | ✘ | ✔ |
| Aprobado | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |

**Por qué se reabre en vez de presentar un expediente nuevo:** los depósitos
cuelgan del trámite (`pagable_id`) y no se trasladan solos. Un expediente nuevo
nacía con cero cobrado mientras el rechazado se quedaba con el dinero cargado,
así que el beneficiario figuraba debiendo todo de nuevo — y cada vuelta gastaba
un número de trámite. Reabrir lo devuelve a PENDIENTE con su plata y su
historial. El rechazo no se borra: queda en `auditorias` con su motivo. Solo
APROBADO es final. Ver `SolicitudCarnetService::reabrir()`.

**Al reenviar, `fecha_revision` no se pisa** —se escribe solo si está en NULL—
porque es la fecha del recibo que el pescador ya tiene en la mano. Si se
reescribiera, el mismo número saldría con dos fechas distintas.

**El DINERO se mueve mientras el expediente esté abierto, y se cierra al
aprobar.** En PENDIENTE y EN REVISIÓN se cargan, corrigen y quitan depósitos
—los papeles sí quedan congelados al enviar, el dinero no—; en APROBADO no.
`permitePagos()` aceptaba también aprobado, con el motivo «puede terminarse de
cobrar después», y eso dejó de poder pasar cuando aprobar pasó a exigir el monto
cubierto: un expediente aprobado está pagado por definición. Ver
[modulos/PAGOS.md](modulos/PAGOS.md).

Los dos huecos que sorprenden tienen el mismo motivo de fondo —*quién vio qué*—:

- **Pendiente no se rechaza.** Rechazar es responderle que no a alguien que
  presentó algo, y acá no se presentó nada: el expediente lo está armando la
  misma ventanilla. Si no sirve se ELIMINA, que también exige motivo y deja la
  línea en `auditorias`.
- **En revisión no se edita ni se elimina.** Al enviarlo se emitió el recibo
  oficial numerado y el pescador se llevó ese papel; borrar el expediente
  dejaría el recibo en la calle sin nada detrás. Y quien aprueba firma sobre los
  papeles que vio: si se pueden cambiar mientras tanto, la firma deja de decir
  sobre qué se firmó. Un escaneo ilegible se RECHAZA y se presenta de nuevo.

**PARA ENVIAR NO ALCANZA CON LOS PAPELES: EL COSTO TIENE QUE ESTAR CUBIERTO.**
La suma de los depósitos tiene que llegar al costo del rubro, o el expediente no
pasa a revisión y el aviso dice cuánto falta. Antes esto se comprobaba recién al
APROBAR, y eso dejaba pasar lo que no se puede deshacer: al enviar queda
habilitado el RECIBO OFICIAL, con su monto y su fecha, y el pescador se va del
mostrador con ese papel. Emitirlo por un expediente a medio pagar es entregar un
comprobante por dinero que no entró.

Encaja con lo que significa cada estado: PENDIENTE es donde la persona está
juntando la plata —ahí se cargan las boletas de a una— y ENVIAR es declarar que
el expediente está completo. Lo cobrado es parte de estar completo. El rubro
exento por ordenanza —costo cero— pasa sin depósitos. Ver
`Tramite::faltantesParaRevision()`.

Todo eso lo dictan `EstadoTramite::siguientes()`, `permiteEdicion()` y
`permiteEliminacion()`. **`estaAbierto()` NO es permiso de escritura**: agrupa
pendiente + en revisión para contar trabajo sin terminar y para detectar
solicitudes duplicadas. Usarlo como permiso fue el error que se corrigió.

**Impreso y entregado NO son estados**: son fechas (`fecha_generacion`,
`fecha_entrega`). Un estado obliga a mantener sincronizadas dos cosas que pueden
discrepar —un trámite «entregado» sin fecha de entrega—; una fecha en NULL dice
«todavía no pasó» sin posibilidad de contradicción.

Aprobado y Rechazado son **finales**. Un trámite aprobado por error no se
«des-aprueba»: ya se escribió la habilitación y posiblemente se imprimió el
plástico. Lo que corresponde es suspender el rubro o anular el carnet.

---

## 2. Las siete decisiones que explican el resto

Si entendés estas siete, el resto del código se deduce.

### 2.1 Las reglas viven en `app/Services/`, nunca en el controlador

El mismo caso de uso lo necesitan el formulario del panel, un comando de consola
para migrar el padrón en papel, y las pruebas. Escrito en el controlador, los
otros dos lo copian — y las copias se quedan viejas.

`TramiteController` tiene 645 líneas y **ni un solo `if ($tramite->estado === ...)`**.
Hace tres cosas: traducir HTTP a objetos del dominio, llamar al servicio, y
convertir el resultado (o la excepción) en un redirect.

Un `if` sobre un estado dentro de un controlador es la señal de que una regla se
está duplicando.

### 2.2 Los enums son la única fuente de verdad

Estados, tipos, roles, permisos y **transiciones** viven en `app/Enums/`. Qué
salto vale desde dónde lo dice `EstadoTramite::siguientes()` y nada más.

El flujo es siempre el mismo:

```
enum  ──▶  servicio pregunta  ──▶  controlador manda  ──▶  React recibe `puede_*`
```

React **nunca** recalcula una regla. La ficha del trámite recibe
`puede_revisar`, `puede_aprobar`, `puede_generar`… ya resueltos por el servidor.
Escrita en los dos lados, algún día las dos versiones dirían cosas distintas y el
operador vería un botón que el servidor rechaza — o peor, no vería uno que sí
puede usar.

Los enums van en columnas `string`, nunca tipos ENUM nativos de PostgreSQL: así
agregar un estado no exige `ALTER TYPE` ni bloquear la tabla.

### 2.3 La concurrencia se resuelve en tres capas

Dos ventanillas atendiendo a la misma persona en el mismo segundo es un caso
real. El sistema lo cubre tres veces:

| Capa | Qué hace | Dónde |
| --- | --- | --- |
| 1. Bloqueo | `lockForUpdate()` sobre la fila | `SolicitudCarnetService::bloquear()` |
| 2. Recomprobación | La guarda de transición corre **otra vez** ya dentro de la transacción | `verificarTransicion()` |
| 3. Índice único | La base rechaza el duplicado pase lo que pase | `carnets_beneficiario_rubro_gestion_unique`, `pagos.nro_transaccion` |

La capa 3 es la única que garantiza de verdad; las capas 1 y 2 existen para que
el operador vea un mensaje entendible en vez de «duplicate key value violates
unique constraint». Cuando igual salta, `traducir()` convierte el SQLSTATE 23505
en castellano de mostrador.

**Se bloquea al beneficiario y no al carnet** porque el caso a proteger es
justamente cuando el carnet todavía no existe: no se puede bloquear una fila que
no está.

> **Trampa conocida:** releer con `lockForUpdate()` devuelve **otra instancia**
> del mismo registro. La convención del servicio es fija: se **escribe** sobre la
> copia bloqueada y se **devuelve** `$tramite->refresh()` —la original—. Escribir
> sobre la copia y devolver esa deja a quien llamó con el estado viejo en memoria.

### 2.4 El disco no participa de la transacción

**Una transacción de base de datos NO deshace escrituras en disco.** De ahí sale
un orden que es distinto según la operación, y las dos direcciones son
deliberadas:

| Operación | Orden | Por qué |
| --- | --- | --- |
| **Registrar** | subir archivos → transacción → si falla, borrar archivos | Lo peor que puede pasar es un archivo de más, y el `catch` lo limpia |
| **Borrar** | juntar rutas → transacción → recién ahí borrar archivos | Lo peor que puede pasar es un archivo de menos: un adjunto borrado cuyo trámite sigue vivo es un enlace roto irrecuperable |

Subir antes tiene además una razón práctica: mandar a s3 puede tardar segundos, y
sostener una transacción abierta mientras tanto mantiene filas bloqueadas sin
ninguna necesidad.

Ver `ArchivoTramiteService` y el paso 2 de `SolicitudCarnetService::registrar()`.

### 2.5 Todo archivo pasa por `StorageController::file()`

Es el **único** punto del sistema que escribe en disco. Nunca `->store()`,
`->storeAs()` ni `Storage::put()` en otra clase. Aplica tres reglas que valen
para todos los adjuntos:

1. **Tope de 3 MB**, sin excepción.
2. **Nombre aleatorio** — el del usuario no se conserva nunca (evita `../` en el
   nombre, colisiones, y datos personales expuestos en la URL).
3. **En qué disco se escribe** — local o s3, según configuración.

`SubidaArchivosTest::test_ninguna_clase_escribe_en_disco_por_su_cuenta()` **revisa
el código fuente** y falla si aparece un atajo nuevo. Es raro y es correcto: lo
que hay que impedir no es que el código de hoy falle, sino que mañana alguien
tome el atajo.

**Lo que devuelve depende del disco**: una ruta cuando es local, una URL completa
cuando es s3. Las dos formas conviven en la misma columna, así que para armar el
enlace se pasa **siempre** por `Archivos::url()`, nunca por `Storage::url()`.

### 2.6 Lo que se entregó, se congela

Tres lugares copian un dato en vez de leerlo por relación, y siempre por el mismo
motivo: **algo ya salió del mostrador y no puede cambiar retroactivamente.**

| Dónde | Qué congela | Si no lo hiciera |
| --- | --- | --- |
| `tramites.monto_requerido` | La tarifa del rubro al presentar | Subir la tarifa en marzo dejaría impagos de golpe los expedientes de febrero |
| `carnets.capacidad_kg` y `carnets.asociacion` | El cupo y la asociación **autorizados** | Habría que remontar cuál trámite los autorizó — que puede no ser el último, si hubo correcciones |

> **`tramites.capacidad_kg` NO es una copia redundante de `carnets.capacidad_kg`.**
> Es la pregunta que más vuelve al mirar el esquema, porque las dos columnas se
> llaman igual y guardan kilos:
>
> | Columna | Qué guarda | Quién la escribe |
> | --- | --- | --- |
> | `tramites.capacidad_kg` | Lo **pedido** en ese expediente | El formulario, al registrar |
> | `carnets.capacidad_kg` | Lo **autorizado**, lo que rige HOY | `consolidarCarnet()`, solo al aprobar |
>
> Entre el registro y la aprobación valen cosas distintas, y ahí está el motivo.
> Alguien con 600 kg autorizados presenta un trámite para subir a 850: hasta que
> se cobre y se firme sigue rigiendo 600. Con una sola columna ese 850 pisaría al
> 600 —autorizando más kilos sin haber pagado— y si el trámite después se
> rechaza, el 600 ya no existiría en ningún lado para volver atrás.
>
> **El carnet nace con las dos columnas en NULL.** Es la misma línea que traza
> `Carnet::puedeImprimirse()`: el carnet existe desde PENDIENTE, pero no
> habilita, no autoriza un cupo y no se imprime hasta que hay un trámite
> aprobado. Lo mismo vale para `asociacion`.


### 2.7 Panel y público no se mezclan

Dos mitades en carpetas paralelas, en el backend y en el frontend:

| | Panel (con sesión) | Público (sin sesión) |
| --- | --- | --- |
| Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` |
| Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` |
| Pantallas | `pages/panel/` | `pages/publico/` |
| Componentes | `components/panel/` | `components/publico/` |
| Layout | `layouts/layout-panel.tsx` | `layouts/layout-publico.tsx` |

La vista pública no puede exponer datos personales completos ni pistas de la
estructura interna. La cédula va enmascarada (solo los últimos 3 dígitos), no
viaja ningún id interno, y los rubros suspendidos **no aparecen** —mostrarlos,
aunque fuera en rojo, arriesga que el inspector lea la fila y no el color—.

---

## 3. El modelo de datos

### Tablas del dominio

| Tabla | Qué guarda | Particularidades |
| --- | --- | --- |
| `beneficiarios` | La persona | Borrado lógico. Columnas en **camelCase** desde `primerNombre`. Índice único **parcial** |
| `carnets` | El documento anual **de una actividad** | Sin columna `codigo`. Se identifica por `firma_validacion`. Único por `(beneficiario, rubro, gestion)` |
| `rubros` | Catálogo de actividades | No se borra nunca, se pasa a `inactivo`. Tres banderas mandan comportamiento: `requiere_capacidad` (¿se autoriza por volumen?), `emite_faenas` y `emite_guias` (¿qué permiso operativo cuelga de sus carnets?) |
| ~~`carnet_rubro`~~ | — | **Eliminada.** El carnet ES la habilitación |
| `tramites` | El expediente | Cuelga del **carnet**, no del beneficiario |
| `pagos` | Cada depósito **y su control** | `registrado_por` ≠ `validado_por`: quien carga no valida. **POLIMÓRFICA**: `pagable_type` + `pagable_id` apuntan a un trámite, una faena o una guía. `nro_transaccion` único **global** — y eso es justamente lo que obliga a que sea una sola tabla |
| `faenas` | El permiso de UNA salida de pesca | Cuelga del carnet. `nro_permiso` es el número del **talonario de papel**, lo tipea el operador. No se borra: se anula |
| `guias` | La guía única de transporte | Cuelga del carnet. Cabecera nomás: quién, desde dónde, hasta dónde y en qué |
| `guia_detalles` | La carga, especie por especie | La **única cascada** del dominio. `imponible` es lo que dice el papel y **no se recalcula** |
| ~~`recibos`~~ | — | **Eliminada.** El comprobante se arma al vuelo desde las otras tablas |

### Tablas de infraestructura

| Tabla | Qué guarda |
| --- | --- |
| `auditorias` | Bitácora polimórfica de altas, cambios y bajas (trait `Auditable`) |
| `accesos` | Bitácora de login / logout |
| `configuraciones` | Ajustes editables, cacheados para siempre e invalidados al guardar |
| `correlativos` | Contadores por serie y año, con `SELECT ... FOR UPDATE` |
| `users`, `roles`, `permissions`… | `spatie/laravel-permission` |

### Decisiones de esquema que sorprenden

**El trámite cuelga del carnet, no del beneficiario.** Así la gestión del
expediente viene dada por construcción —todo trámite de un carnet de 2026 es de
2026— en vez de tener que deducirse de una fecha. A la persona se llega con
`hasOneThrough`. Una columna `beneficiario_id` propia abriría la puerta a que el
trámite y el carnet apunten a personas distintas, y la base no podría impedirlo.

**No hay columna `monto_pagado` ni `saldo`.** Lo pagado es la suma de `pagos` y
lo que falta es una resta, las dos calculadas al leer. Una columna así hay que
mantenerla al día en cada alta y cada corrección; el día que alguien inserte una
fila sin acordarse, el sistema cobra de menos y nada avisa.

**No hay columna `edad`.** Cambia sola: alguien de 39 pasa a 40 el día de su
cumpleaños sin que nadie toque el sistema.

**No hay `created_by` ni `aprobado_por`.** Quién hizo cada cosa vive en
`auditorias`, con usuario, IP, URL y los valores antes/después.

**El carnet no tiene número: tiene dos nombres.**

| Para | Qué se usa | Cómo |
| --- | --- | --- |
| Imprimir y dictar | El **registro** | El `id` con ceros: `000013` |
| Verificar | La **firma** | 16 caracteres al azar, únicos |

El registro es corto, correlativo y **no abre nada**. La firma es larga,
impredecible (~8·10²⁴ combinaciones) y es la llave de la verificación pública;
viaja **únicamente dentro del QR**. Ese reparto es lo que hace que las dos cosas
funcionen. La contrapartida: si el QR queda ilegible no hay forma de verificar
desde la calle, hay que pasar por la oficina.

**Una firma por carnet, o sea una por rubro.** Con el modelo viejo había que
aclarar que una adición no la cambiaba; hoy cada rubro es un carnet distinto y
cada uno nace con la suya. Lo que no cambia nunca es la firma DE UN carnet, o el QR ya
impreso dejaría de funcionar.

### Índices únicos y el borrado lógico

Nunca incluir `deleted_at` en un `unique()`. En SQL `NULL != NULL`, así que dos
filas vivas se consideran distintas y el índice no bloquea nada. La forma
correcta es un índice **parcial**:

```sql
CREATE UNIQUE INDEX beneficiarios_ci_unico
    ON beneficiarios (ci_nit, COALESCE(complemento, ''))
    WHERE deleted_at IS NULL
```

> Esto valía también para `recibos.tramite_id`, cuando esa tabla existía: ahí el
> `NULL != NULL` era justamente lo que se quería. Muchos recibos podían quedar
> huérfanos; lo que no
> puede es que dos apunten al mismo trámite.

---

## 4. Cómo viaja una petición

No hay API REST. No se escribe `fetch()` ni `axios` en ninguna parte.

```
1. Navegador      GET /panel/beneficiarios
2. routes/panel.php    decide el controlador  +  exige el permiso
3. Controlador         consulta con Eloquent
4. Inertia::render('panel/beneficiarios/index', [...datos...])
5. Inertia busca  resources/js/pages/panel/beneficiarios/index.tsx
6. Ese componente recibe los [...datos...] como PROPS
```

De vuelta: React usa `router.post()` / `useForm().post()`, que hace un POST
normal con token CSRF a una ruta normal. El controlador responde con un
`redirect()` —**nunca** con JSON— e Inertia pinta la página destino sin recargar.

**Props compartidas** por `HandleInertiaRequests::share()`: usuario con sus roles
y permisos, datos de la institución, límites de archivos, mensajes flash y
apariencia. Casi todas envueltas en `fn()` para que Inertia las evalúe solo
cuando se van a mandar de verdad.

---

## 5. Seguridad

### Permisos

**Se declaran en las rutas.** El middleware `permiso:` es la seguridad real:

```php
Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
    ->middleware('permiso:beneficiarios.ver')
    ->name('beneficiarios.index');
```

Esconder un botón en React con `usePermisos()` es **solo comodidad**. Siempre van
los dos.

Los permisos salen de `RolSistema`, repartidos en cuatro bloques (`$lectura`,
`$operacion`, `$supervision`, `$administracion`). **Hoy existe un solo rol**,
`administrador`, con todos.

> ⚠️ **Riesgo abierto:** con un solo rol, cualquier usuario puede aprobar sus
> propios trámites. No hay separación de funciones en un sistema que cobra
> dinero. Las rutas ya están instrumentadas, así que agregar el rol de
> ventanilla es una línea:
> `self::Operador => [...$lectura, ...$operacion],`

### Verbos HTTP

Los pasos del circuito son **PATCH**, nunca GET. Un verbo de lectura que escribe
se dispara solo: alcanza con que el navegador precargue el enlace, que un
antivirus corporativo lo visite o que alguien comparta la URL por chat. Un
trámite aprobado por el prefetch del navegador es un problema que no se puede
explicar después.

Cada paso tiene **su** ruta y **su** permiso, en vez de un único «cambiar estado»
con el destino en el cuerpo: con eso, quien recepciona podría aprobarse a sí
mismo el trámite que acaba de cargar.

**La única excepción es `GET /panel/tramites/{tramite}/recibo`**, y no es una
excepción a la regla: esa ruta no escribe nada, el recibo ya está emitido.

### Vista pública

Una sola pantalla sin sesión: `/verificar/{firma}`. Con `throttle:60,1` en la
consulta y `throttle:20,1` en el formulario — el motivo no es el costo sino la
fuerza bruta, porque cada visita es un intento de adivinar una firma.

---

## 6. Compatibilidad PostgreSQL / SQLite

Producción usa PostgreSQL; las pruebas usan SQLite en memoria. El código tiene
que correr igual en los dos.

- **SQL específico de motor** va en `app/Support/Sql.php` (`like()` resuelve
  `ILIKE` vs `LIKE`, `periodoMes()` resuelve el truncado de fecha).
- **Scopes con `qualifyColumn()`**, siempre: `tramites`, `carnets`, `rubros` y
  tienen **todas** una columna `estado` —y `carnets` y `tramites` comparten
  además `rubro_id`—, y los reportes las cruzan
  con `join`. Sin calificar, PostgreSQL responde `column reference "estado" is
  ambiguous` y la consulta ni corre.
- **camelCase obliga a entrecomillar** en SQL escrito a mano:
  `SELECT "primerNombre"`. Sin comillas, PostgreSQL pasa el nombre a minúscula y
  responde `column "primernombre" does not exist`. Eloquent entrecomilla solo; el
  problema aparece con `whereRaw` / `orderByRaw` (ver `Beneficiario::SQL_NOMBRE`).

---

## 7. Módulos

### Pagos — el control de cada depósito

Cada boleta se VALIDA u OBSERVA, y queda escrito quién y cuándo. Quien la cargó
no puede darla por buena, y un trámite con alguna boleta sin controlar **no se
puede aprobar**.

El detalle está en [modulos/PAGOS.md](modulos/PAGOS.md).

### Faenas y guías — los permisos operativos

Cuelgan de un carnet vigente y se emiten muchos por gestión: la **faena**
autoriza una salida de pesca; la **guía** ampara un traslado de carga, con su
detalle especie por especie.

**Ninguno se edita ni se borra.** El número sale de un talonario de papel que la
persona se lleva en el momento: editarlo dejaría al sistema contradiciendo al
documento, y borrarlo dejaría un hueco en la serie además de liberar un número
que el índice único volvería a aceptar. Se **anulan**, con motivo obligatorio.

La única corrección posible es el DETALLE de la guía, porque el peso real se
conoce en la balanza.

El detalle completo está en
[modulos/PERMISOS-OPERATIVOS.md](modulos/PERMISOS-OPERATIVOS.md).


| Módulo | Estado | Entrada principal |
| --- | --- | --- |
| Acceso y bitácora | ✅ Completo | `AuthenticatedSessionController` |
| Panel principal | ✅ Completo | `DashboardController` |
| **Beneficiarios** | ✅ Completo — **es la plantilla a copiar** | `BeneficiarioController` |
| **Trámites** | ✅ Completo | `SolicitudCarnetService` |
| **Pagos** | ✅ Completo (1 a N depósitos) | `PagoTramiteService` |
| **Recibos** | ✅ Completo — ver [modulos/RECIBOS.md](modulos/RECIBOS.md) | `ReciboTramiteService` |
| **Carnets** | ✅ Completo | `CarnetController` |
| **Rubros** | ✅ Completo | `RubroController` |
| Verificación pública | ✅ Completo | `VerificacionController` |
| Reportes | ❌ No existe | — |
| Configuración | ❌ No existe (la tabla sí) | — |

### El patrón a copiar

**Beneficiarios** es la plantilla del sistema, comentada paso a paso a propósito.
Para un módulo nuevo, copiar:

```
app/Http/Controllers/Panel/BeneficiarioController.php
app/Http/Requests/Panel/GuardarBeneficiarioRequest.php
routes/panel.php                            (el bloque de beneficiarios)
resources/js/pages/panel/beneficiarios/*.tsx
resources/js/components/panel/beneficiarios/*.tsx
resources/js/types/beneficiarios.ts
```

Si el módulo tiene reglas de negocio de verdad —no solo un CRUD—, copiar además
el par `SolicitudCarnetService` + `SolicitudInvalidaException`.

---

## 8. Verificar antes de dar algo por terminado

Los tres tienen que pasar:

```sh
npx tsc --noEmit        # tipos de TypeScript
./vendor/bin/pint       # formato del PHP
npm run build           # que el frontend compile
```

### Y los tres en verde NO alcanzan

Revisan **tipos y formato**. De la lógica de negocio no dicen nada, y desde que
no hay pruebas automáticas nada la revisa sola.

> ⚠️ **NO HAY PRUEBAS.** `tests/Feature/` se vació el 14/09/2026 por pedido del
> responsable del proyecto; eran 143 y cubrían el backend entero. Lo que
> protegían —que no se apruebe un trámite sin cobrar, que no se emitan dos
> carnets por gestión, que un recibo reimpreso conserve su número, que nadie
> suba archivos salteando `StorageController`— **hoy no lo comprueba nada, y un
> error de esos aparece recién en ventanilla.**
>
> Mientras no vuelvan: **todo cambio se prueba abriendo la pantalla en el
> navegador**, incluidos los casos borde.

**Y hay un tercer frente**, que ya mordió una vez: el estado de tu máquina. Al
agregar una migración, un permiso a `RolSistema` o una clave a
`ConfiguracionSeeder`, hay que correr a mano:

```sh
php artisan migrate
php artisan db:seed --class=RolPermisoSeeder
```

Pasó con la tabla `recibos`: la pantalla reventaba con
`relation "recibos" does not exist` mientras las pruebas de entonces estaban
todas en verde, porque corrían sobre un esquema armado desde cero en memoria.

---

## 9. Trampas conocidas

Las que ya costaron un error real. La lista completa con más contexto está en
[CLAUDE.md](../CLAUDE.md).

### Backend

- **`update()` descarta en silencio lo que no esté en `#[Fillable]`.** No lanza
  error: simplemente no escribe la columna. Fue el motivo de que
  `fecha_aprobacion` quedara en NULL al aprobar.
- **Un accesor camelCase NO puede ir en `#[Appends]`.** Laravel lo busca en
  snake_case, no lo encuentra, cae al accesor viejo y revienta. Acceder directo
  (`$beneficiario->nombreCompleto`) sí funciona.
- **`attach()` no dispara eventos de Eloquent**, así que `Auditable` no registra
  nada. Ya no aplica a las habilitaciones —el pivote se eliminó— pero sigue
  valiendo para cualquier relación muchos-a-muchos que se agregue.
- **Un DELETE en cascada tampoco dispara eventos.** Por eso los pagos se borran
  uno por uno con Eloquent: son dinero declarado y no pueden desaparecer sin
  rastro.
- **`$modelo->relacion()->where(...)` consulta SIEMPRE**, aunque quien llamó haya
  hecho `with('relacion')`. El `with()` queda escrito, se ve correcto, y el N+1
  sigue ahí en silencio. Un método del modelo debe preguntar con
  `relationLoaded()` — ver `Beneficiario::carnetDeGestion()`.
- **El estado guardado de un carnet puede mentir.** `vencido` lo escribe un
  comando programado que **todavía no existe**. Para saber si vale HOY se mira
  además `fecha_vencimiento`: `Carnet::estaVigente()`.
- **`->withQueryString()`** en todo paginador con filtros, o al cambiar de página
  se pierden.
- **`env()` solo dentro de `config/`.** Con `config:cache` activo devuelve `null`
  fuera, y el error es silencioso.

### Frontend

- **`new Date('2026-12-31')` NO da el 31 de diciembre.** El estándar lo
  interpreta como medianoche UTC, y Bolivia está en UTC-4: al formatear en local
  sale **30/12**. Toda fecha se muestra con `fecha()` de `lib/utils.ts`. Nunca
  `new Date(cadena)` directo en un componente.
- **Dos botones en la misma posición de un ternario necesitan `key` distinto.**
  React los reconcilia como el MISMO `<button>` y solo le cambia el `type`; el
  cambio ocurre mientras el clic se procesa y el formulario se envía solo.
- **Clases de Tailwind armadas juntando textos no funcionan.** Si se agrega un
  color a un enum de PHP, hay que agregarlo también al mapa de
  `components/ui/badge.tsx`.
- **Los gráficos de recharts necesitan que el contenedor padre tenga altura**
  (`h-72`), o se calculan con altura cero.

### PDF (DomPDF)

- **No es un navegador**: no entiende flexbox, grid ni variables CSS, y no
  ejecuta JavaScript. Se maqueta con tablas y `position: absolute`.
- **`opacity` es poco confiable**: según la versión lo ignora. Para el sello de
  agua, la atenuación va **horneada en el archivo PNG**.
- **Las imágenes van embebidas en base64.** Una ruta se resolvería contra el
  disco con las restricciones de `chroot` y en producción suele terminar en un
  recuadro vacío.
- **Cuidado con el peso**: embeber los PNG originales del panel hacía un PDF de
  5,4 MB por recibo. Hay copias a medida en `public/image/recibo-*.png`.

---

## 10. Dónde seguir

| Documento | Para qué |
| --- | --- |
| [MAPA-ARCHIVOS.md](MAPA-ARCHIVOS.md) | Qué hace cada archivo, en una línea |
| [modulos/RECIBOS.md](modulos/RECIBOS.md) | El recibo oficial, en detalle |
| [ESTRUCTURA.md](ESTRUCTURA.md) | Dónde va un archivo **nuevo** |
| [GUIA-INERTIA.md](GUIA-INERTIA.md) | Cómo se conectan Laravel y React |
| [PENDIENTES.md](PENDIENTES.md) | Qué falta y qué problemas siguen abiertos |
| [INSTALACION.md](INSTALACION.md) | Levantar el proyecto en una máquina nueva |
| `docs/sesiones/MM-AAAA/` | Bitácora de trabajo, un archivo por día |
