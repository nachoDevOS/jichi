# Jichi — guía para agentes de IA

Sistema de gestión de carnets, rubros y trámites del Gobierno Autónomo
Departamental del Beni, Bolivia.

**Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript · Tailwind 4 ·
PostgreSQL 18** (corre también en SQLite; las pruebas usan SQLite en memoria).

---

> # ⚠️ EL NÚCLEO DE DATOS SE REHIZO EL 18/09/2026 — ESTE ARCHIVO ESTÁ VIEJO
>
> Todo lo que sigue —«El dominio en cinco líneas», el circuito del trámite, la
> tabla de estados, los rubros, los recibos armados al vuelo— describe el
> modelo **ANTERIOR**, y ya no existe en la base. Sigue sirviendo para entender
> el código del panel, que todavía está escrito contra él; **no** para entender
> el esquema.
>
> **La base corre hoy sobre `jichi1` con este esqueleto:**
>
> ```
> beneficiario ──< carnet (pescador)        ──< permiso_faena   (una por salida)
>              ──< carnet (comercializador) ──< guia_movimiento (una por traslado)
>              ──< aprovechamiento_pesq  ─── la bolsa madre: el cupo en kilos
>
> recibo ──< pago ──(polimórfico)──▶ Carnet | AprovechamientoPesq | GuiaMovimiento
> ```
>
> | Ya no existe | Lo reemplaza |
> | --- | --- |
> | `rubros`, `carnet_rubro` | `carnets.tipo_actor` (enum `TipoActor`) + `tipos_carnet` (catálogo) |
> | `tramites` y su circuito de estados | Nada: el documento se emite y se cobra; no hay expediente |
> | `faenas`, `guias`, `guia_detalles` | `permisos_faena`, `guias_movimiento` |
> | recibo armado al vuelo | tabla `recibos`, con correlativo propio y datos copiados |
> | `pagos.monto` | `pagos.monto_parcial` + `pagos.recibo_id` (se admite pagar en cuotas) |
> | `beneficiarios.ci_nit` | `beneficiarios.ci` |
>
> **Qué leer para ponerse al día, en este orden:**
> [docs/MER.md](docs/MER.md) —**está al día y es la fuente del esquema**— →
> [docs/sesiones/09-2026/2026-09-18.md](docs/sesiones/09-2026/2026-09-18.md) →
> `app/Traits/Pagable.php`.
>
> **Las migraciones son cortas a propósito: el porqué de cada decisión está en
> `MER.md`, no en ellas.** Las anteriores quedaron en
> `database/migrations-anterior/` como referencia; Laravel no las corre.
>
> **El panel NO está portado:** controladores, `app/Services/`, `app/Support/` y
> las pantallas de React siguen nombrando `Rubro`, `Tramite`, `Faena` y `Guia`,
> y las columnas que leen ya no existen. Ese es el trabajo siguiente.
>
> Las secciones que SÍ siguen valiendo enteras: **«Reglas que no se rompen»**,
> **«Verificar antes de dar algo por terminado»** y **«Trampas conocidas»**.

---

## Antes de tocar nada — y antes de leer código

> **NO leas el sistema entero para entenderlo.** Está documentado a propósito
> para que no haga falta:
>
> | Leé esto | Para |
> | --- | --- |
> | [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Entender el sistema. **Empezá acá siempre** |
> | [docs/MAPA-ARCHIVOS.md](docs/MAPA-ARCHIVOS.md) | Qué hace cada archivo y qué tiene de no obvio |
> | [docs/MER.md](docs/MER.md) | Las tablas, sus relaciones y qué regla impone cada restricción |
> | [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Dónde va un archivo **nuevo** |
> | [docs/modulos/](docs/modulos/) | Un módulo en profundidad |
> | [docs/PENDIENTES.md](docs/PENDIENTES.md) | Qué falta y qué está roto |
>
> Recién después abrí código, y abrí **el archivo que vas a cambiar**, no el
> resto. Si al terminar sabés algo que esos `.md` no decían, agregalo ahí.

---

## El dominio en cinco líneas

Un **beneficiario** saca un **carnet** por cada **rubro** (actividad) que
ejerce, y cada carnet es anual. Para obtenerlo presenta un **trámite**, que se
cubre con uno o varios **pagos**. Al aprobarlo, el carnet queda habilitado y
recién ahí la persona puede trabajar en esa actividad.

Quien pesca y además comercializa tiene **dos carnets** en la misma gestión, con
dos plásticos, dos firmas de validación y dos cupos autorizados.

> **EL CARNET ES LA LLAVE ANUAL; CON ÉL SOLO NO SE SALE A TRABAJAR.** De cada
> carnet cuelgan los **permisos operativos**, que son muchos por gestión y son
> los que autorizan el trabajo de cada día:
>
> ```
> beneficiario ──< carnet (Pescador, 2026)        ──< faena  (una por salida)
>              ──< carnet (Comercializador, 2026) ──< guia   (una por traslado)
>                                                        └──< guia_detalle
> ```
>
> Una **faena** autoriza UNA salida: esta embarcación, este comandante, de tal
> día a tal día, con tanto en kilos. Una **guía** ampara UN traslado: de dónde a
> dónde, en qué vehículo, con qué carga — y su detalle lista la carga especie
> por especie, con su condición y sus kilos.
>
> **Qué emite cada carnet lo dicen `rubros.emite_faenas` y `rubros.emite_guias`,
> NUNCA el nombre del rubro** — mismo criterio que `requiere_capacidad`, y por
> el mismo motivo: el catálogo lo edita la unidad desde el panel.
>
> Las dos tienen su módulo en el panel —listado, formulario y ficha— y sus
> reglas en `FaenaService` / `GuiaService`. **No se editan ni se borran**: el
> número sale de un talonario de papel que la persona se llevó, así que se
> ANULAN con motivo. La única corrección es el detalle de la guía, porque el
> peso real se conoce en la balanza. Ver
> [docs/modulos/PERMISOS-OPERATIVOS.md](docs/modulos/PERMISOS-OPERATIVOS.md).
>
> **Los tres se cobran con la MISMA tabla `pagos`, que es polimórfica**
> (`pagable_type` + `pagable_id`). Una sola tabla y no tres porque
> `nro_transaccion` es único GLOBAL: partido en tres dejaría de serlo, y la
> misma boleta podría pagar un trámite y una faena. El costo es que **se perdió
> la clave foránea** — la integridad la sostienen los `RESTRICT` de arriba y la
> aplicación, no el motor.

El expediente recorre este circuito:

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
(borrador)  ▲                │                         │
            │                │                         └── el carnet queda habilitado
            │                ├── queda habilitado el RECIBO OFICIAL
            │                └──[rechazar]──▶ RECHAZADO
            └────────────────────[reabrir]───────┘
```

**Enviar a revisión es OBLIGATORIO: no se aprueba desde PENDIENTE.** Mientras
está pendiente el expediente se arma —papeles, depósitos, correcciones—; al
enviarlo, ventanilla declara que está completo y pasa a quien lo firma. El
atajo `PENDIENTE ──▶ APROBADO` existió y se sacó: con él, quien cargaba la
solicitud podía aprobarla sin que nadie más la tocara.

**RECHAZADO NO ES EL FINAL: SE REABRE.** Rechazar es devolverle los papeles al
pescador con el motivo escrito, y lo que sigue es que vuelva con lo corregido.
Eso antes obligaba a presentar un expediente NUEVO, y ahí estaba el problema:
**los depósitos ya cargados se quedaban colgados del trámite muerto** —cuelgan
de él por `pagable_id` y no se trasladan solos—, así que la persona figuraba
debiendo todo de nuevo. Reabrir lo devuelve a PENDIENTE con su dinero y su
historial. No es «des-rechazar»: el rechazo queda en `auditorias` con su motivo.
Solo APROBADO es final.

**PENDIENTE es un BORRADOR, y eso define qué se puede hacer en cada estado:**

| Estado | Editar | Eliminar | Enviar | Aprobar | Rechazar | Reabrir |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| **Pendiente** | ✔ | ✔ | ✔ | ✘ | ✘ | ✘ |
| **En revisión** | ✘ | ✘ | ✘ | ✔ | ✔ | ✘ |
| **Rechazado** | ✘ | ✘ | ✘ | ✘ | ✘ | ✔ |
| Aprobado | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |

Las dos mitades de esa tabla salen de la misma idea:

- **En pendiente no se rechaza.** Rechazar es la respuesta a algo que alguien
  PRESENTÓ, y un borrador todavía no se presentó — lo está armando la misma
  ventanilla. Un borrador que no sirve se ELIMINA, que también pide motivo.
- **En revisión no se edita ni se elimina.** Al enviar salió el RECIBO OFICIAL
  numerado y el pescador se fue con ese papel; además, quien aprueba firma sobre
  los papeles que vio. Un expediente presentado que no sirve se RECHAZA, con su
  motivo escrito.
- **En rechazado no se hace nada hasta reabrirlo.** La fila entera es ✘ menos
  esa columna a propósito: editar, borrar o cobrar sobre un expediente que está
  en el mostrador del pescador sería trabajar sobre algo que no está en la mesa
  de nadie. Reabrir es el acto de decir «esto se retoma», y va antes que todo lo
  demás.

La tabla la dicta `EstadoTramite::permiteEdicion()`, `permiteEliminacion()` y
`siguientes()`. **Ojo con `estaAbierto()`**: agrupa pendiente + en revisión y
sirve para contar trabajo sin terminar, pero NO es permiso de escritura.

> **AL REENVIAR, `fecha_revision` NO SE PISA.** Es la fecha del recibo oficial,
> y ese papel ya está en manos del pescador desde el primer envío. Se escribe
> solo si está en NULL; si no, el mismo número saldría con dos fechas distintas.

**Lo que se congela al enviar son los PAPELES, no el dinero.** Un expediente en
revisión sigue aceptando depósitos, correcciones y bajas de depósitos —eso pasa
en la FICHA, no en «Editar trámite»—, porque el control de las boletas es parte
de la revisión y un depósito observado tiene que poder arreglarse ahí mismo.

> **QUITAR SE HABILITA EXACTAMENTE CUANDO CORREGIR**, y estuvo restringido al
> borrador por un argumento que no se sostiene: «el recibo ya salió». Corregir ya
> cambia el recibo igual —se arma al vuelo con los depósitos que hay, así que
> bajar un monto de 110 a 30 mueve el papel entregado tanto como borrar la fila—.
> Lo que separa a las dos no es el momento sino el PERMISO: quitar pide
> `pagos.eliminar`, que es de administración, y el motivo por escrito. Ver
> `Pago::admiteEliminacion()`, que delega en `admiteCorreccion()`.

> **AL APROBAR SE CIERRA EL DINERO.** `permitePagos()` vale solo en PENDIENTE y
> EN REVISIÓN. Decía además APROBADO, con el motivo «un trámite puede aprobarse
> y terminarse de cobrar después» — y eso dejó de poder pasar cuando
> `puedeAprobarse()` pasó a exigir el monto CUBIERTO: un expediente aprobado
> está pagado por definición. La regla quedó permitiendo algo imposible, y lo
> único que habilitaba era cargar plata de más sobre un carnet ya emitido y
> cambiar el detalle de un recibo ya entregado. Si entró dinero de más, no es un
> depósito de este expediente.

Impreso y entregado no son estados sino fechas: `fecha_generacion` y
`fecha_entrega`. Un estado obliga a sincronizar dos cosas que pueden discrepar;
una fecha en NULL dice «todavía no pasó» sin posibilidad de contradicción.

> **CADA DEPÓSITO SE CONTROLA, Y ESO FRENA LA APROBACIÓN.**
>
> ```
> PENDIENTE ──▶ VALIDADO    la boleta cuadra con el extracto del banco
>     ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
>     └──[corregir]──┘
> ```
>
> `pagos.estado_validacion` NO es el estado del pago —el dinero entró o no
> entró— sino el de su CONTROL. Un depósito observado **sigue sumando** en
> `montoPagado()`: existe y está cargado; lo que está en duda es si respalda lo
> que dice.
>
> **UN OBSERVADO NO SE VALIDA: PRIMERO SE CORRIGE.** Es la única salida y el
> botón «Validar» no aparece sobre él. Validarlo sin que nadie haya tocado el
> dato es dar por bueno justo lo que se marcó como malo, y el problema señalado
> se pierde sin que quede si se arregló. Al CORREGIRLO vuelve solo a PENDIENTE
> —el dato es nuevo y nadie lo miró— y desde ahí se valida. Si la observación
> estaba equivocada, se abre «Corregir» y se guarda sin cambiar nada: queda en
> `auditorias` quién lo hizo.
>
> **Y por eso corregir un depósito vive en la FICHA además de en «Editar
> trámite»**: observar solo pasa EN REVISIÓN, y esa pantalla no abre ahí. Con el
> botón en un solo lado el circuito no se podía cerrar y el expediente quedaba
> trabado — pasó de verdad.
>
> **QUIÉN CARGÓ Y QUIÉN VALIDÓ SE GUARDAN SIEMPRE**, en dos columnas distintas.
> Que el sistema EXIJA que sean personas distintas es configurable
> —`pagos.revisor_distinto`, apagado por defecto—: encendido con un solo usuario
> deja el circuito trabado, porque la misma cuenta carga y no puede validar.
>
> **El control es parte de la REVISIÓN**: solo se valida con el trámite EN
> REVISIÓN. Pendiente es un borrador y aprobado ya no admite reparos. Ver
> `Pago::admiteControl()`.
>
> `Tramite::puedeAprobarse()` exige las tres cosas: el estado, el monto cubierto
> y **todos los depósitos validados**. Sin la tercera, la validación sería
> decorativa.

Al pasar a EN REVISIÓN queda habilitado el **RECIBO OFICIAL** —el talonario
verde del SEDAG—: es el momento en que el pescador entregó los papeles y la
plata, y se va con su comprobante.

> **NO HAY TABLA `recibos`.** Se retiró a pedido: toda su información ya vive en
> `beneficiarios`, `carnets`, `rubros`, `tramites` y `pagos`, así que el
> comprobante se **arma al vuelo** cada vez que alguien lo imprime. Su número es
> el id del trámite y su fecha es `tramites.fecha_revision` —el dato que ya
> estaba guardado, y por eso una reimpresión de marzo sigue diciendo marzo—.
>
> Lo que eso cuesta, y hay que tenerlo presente: **el recibo dejó de ser
> inmutable**. Corregir un apellido en la ficha cambia los comprobantes ya
> entregados, borrar el trámite se lleva el recibo, y la serie tiene huecos
> porque no todo trámite emite uno. Está explicado en
> `App\Support\ReciboArmado`.

Ver [docs/modulos/RECIBOS.md](docs/modulos/RECIBOS.md).

Al aprobar, el carnet queda habilitado, y recién ahí se puede IMPRIMIR:
`GET /panel/carnets/{carnet}/imprimir` dibuja el plástico —una carilla, CR80,
calcando la cédula de papel—. **La maqueta impresa y `vista-previa-carnet.tsx`
son el mismo diseño escrito dos veces**: ese componente es el recuadro «así va a
salir el carnet» del paso 3 del formulario, así que si se toca una hay que tocar
la otra, o la vista previa pasa a prometer algo que el PDF no cumple. La tarjeta
NO lleva QR —se sacó a pedido— así que `App\Support\CodigoQr` queda escrito y
sin usar.

**El plástico SÍ lleva el rubro y el cupo**, al revés de lo que valía con el
modelo anterior: son seis renglones y tres de ellos comparten dos pares
—RUBRO + CUPO, CIUDAD + PROV., REGISTRO + GESTIÓN—. Ver
[docs/modulos/CARNETS.md](docs/modulos/CARNETS.md).

> **EL CUPO NO LO LLEVAN TODAS LAS ACTIVIDADES.** La pesca se autoriza por
> volumen —tantos kilos, contrastables contra una guía de transporte—; la
> comercialización no. Lo dice la columna `rubros.requiere_capacidad`, NUNCA un
> `match` sobre el nombre: el mismo rubro figura como «Pescador» o como «Faena»
> según quién lo cargó, y los que vengan por ordenanza entran sin pasar por
> código.
>
> De esa bandera cuelgan cuatro cosas: el formulario muestra u oculta el campo,
> la validación lo exige o lo **prohíbe**, la ficha del carnet lo muestra o no, y
> el plástico imprime el renglón CUPO o le da la tira entera al nombre del rubro.
> Ver `Rubro::requiereCapacidad()` y `CarnetImpresionController::renglonRubro()`.

> **LA REGLA QUE ORDENA TODO: una persona tiene como máximo UN carnet por RUBRO
> y por gestión.**

La garantiza el índice único `(beneficiario_id, rubro_id, gestion)` de la tabla
`carnets`. De ahí salen los dos únicos tipos de trámite, y **los decide el
sistema, no el operador**:

| Situación | Tipo | Qué hace |
| --- | --- | --- |
| No tiene carnet **de ese rubro** este año | `emision_inicial` | Crea el carnet y cuelga el trámite |
| Ya lo tiene | `actualizacion` | Reutiliza ese carnet; al aprobar, consolida cupo y asociación |

> **NO EXISTE LA «ADICIÓN DE RUBRO».** Existió, y la pregunta vuelve cada vez
> que alguien lee código viejo. Con el modelo anterior el carnet era uno por
> persona y los rubros se le colgaban en una tabla `carnet_rubro`: sumar una
> actividad hacía CRECER ese carnet, y eso era la adición. Hoy cada actividad es
> un documento propio, así que pedir un rubro más no agranda nada — emite otro
> carnet, y eso ya se llama emisión inicial.
>
> `carnet_rubro` y el enum `EstadoHabilitacion` **fueron eliminados**: el carnet
> ES la habilitación. Suspender una actividad es suspender su carnet.

Todo eso vive en `app/Services/SolicitudCarnetService.php`, en una transacción,
con la fila del beneficiario bloqueada. **No se replica en el controlador ni en
React.**

---

## Reglas que no se rompen

1. **Todo en español.** Nombres de archivo, variables, métodos, comentarios,
   mensajes al usuario, textos de la interfaz. La única excepción son los
   componentes de `resources/js/components/ui/` (`Button`, `Card`, `Input`,
   `Label`, `Badge`, `Select`, `Textarea`), que conservan el vocabulario
   estándar de React.

2. **Comentar el porqué, no el qué.** Este proyecto lo mantiene alguien que está
   aprendiendo React. Un comentario que repite lo que dice el código sobra; uno
   que explica por qué se eligió ese camino vale oro. Ejemplos del estilo
   esperado: `SolicitudCarnetService`, `ArchivoTramiteService`,
   `app/Support/Sql.php`, la migración `2026_09_10_100200_create_carnets_table`.

3. **Panel y público no se mezclan.** El sistema tiene dos mitades separadas en
   carpetas paralelas, en el backend y en el frontend:

   | | Panel (con sesión) | Público (sin sesión) |
   | --- | --- | --- |
   | Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` |
   | Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` |
   | Pantallas | `resources/js/pages/panel/` | `resources/js/pages/publico/` |
   | Componentes | `components/panel/` | `components/publico/` |
   | Layout | `layouts/layout-panel.tsx` | `layouts/layout-publico.tsx` |

   La vista pública no puede exponer datos personales completos ni pistas de la
   estructura interna. Ver `VerificacionController::datosPublicos()`.

4. **`env()` solo dentro de `config/`.** En el resto del código,
   `config('jichi.lo_que_sea')`. Con `config:cache` activo, `env()` devuelve
   `null` fuera de `config/` y el error es silencioso.

5. **Los permisos se declaran en las rutas.** El middleware `permiso:` es la
   seguridad real. Esconder un botón en React (`usePermisos()`) es solo
   comodidad: siempre van los dos. Hoy el único rol es `administrador` y los
   tiene todos, pero el middleware va igual en cada ruta.

6. **Los enums mandan.** Estados, tipos, roles y permisos viven en `app/Enums/`.
   No escribir esos valores como texto suelto en el código.

   Eso incluye las TRANSICIONES: qué salto de estado vale desde dónde lo dice
   `EstadoTramite::siguientes()`, y nada más. El servicio pregunta, el
   controlador no decide y React recibe la respuesta ya calculada en los campos
   `puede_*` de la ficha. Un `if ($tramite->estado === ...)` suelto en un
   controlador es la señal de que la regla se está duplicando.

7. **Enums en columnas `string`**, nunca tipos ENUM nativos de PostgreSQL: así
   agregar un estado no exige `ALTER TYPE` ni bloquear la tabla.

8. **SQL específico de motor va en `app/Support/Sql.php`.** El sistema tiene que
   correr igual en PostgreSQL y en SQLite.

9. **Scopes con `qualifyColumn()`.** `tramites`, `carnets` y `rubros` tienen
   todas una columna `estado`, y los reportes las cruzan con `join`. Desde que
   el carnet tiene `rubro_id`, `carnets` y `tramites` comparten además esa
   columna: un `where('rubro_id', ...)` sin calificar sobre una consulta con
   join responde «column reference is ambiguous».

10. **Las reglas de negocio van en `app/Services/`, no en el controlador.** El
    mismo caso de uso lo necesitan el formulario del panel, un comando de
    consola y las pruebas. Escrito en el controlador, los otros dos lo copian —y
    las copias se quedan viejas—.

11. **TODO archivo se sube con `StorageController::file()`.** Nunca `->store()`,
    `->storeAs()` ni `Storage::put()` en otra clase. Es el único lugar que aplica
    las tres reglas que valen para todos los adjuntos: el tope de **3 MB**, el
    nombre aleatorio —el del usuario no se conserva nunca— y en qué disco se
    escribe. `SubidaArchivosTest` revisa el código fuente y falla si aparece un
    atajo nuevo.

12. **MIENTRAS EL NÚCLEO SE ESTÉ ARMANDO, NO SE AGREGAN MIGRACIONES DE
    PARCHE.** Una columna nueva va DENTRO de la migración que crea su tabla, no
    en un `add_x_to_y` aparte.

    El motivo es práctico: el responsable del proyecto rearma la base con
    `migrate:fresh` cada vez que el esquema cambia, así que un archivo de parche
    solo agrega ruido a un esquema que igual se va a construir de cero. Y de paso
    el comentario de la columna queda al lado del resto de la tabla, que es donde
    alguien lo va a buscar.

    **Al editar una migración ya corrida hay que avisar que hay que volver a
    migrar**, y verificarlo antes: se arma el esquema completo en una base
    descartable —`DB_CONNECTION=sqlite DB_DATABASE=<archivo> php artisan
    migrate:fresh --seed`— y recién ahí se dice que funciona. Nunca sobre la base
    de trabajo.

    Esto deja de valer el día que el sistema esté en producción con datos reales:
    ahí una migración editada es una migración que nadie va a volver a correr.

    **Y la migración se mantiene CORTA.** El porqué de cada decisión de esquema
    va en [docs/MER.md](docs/MER.md), tabla por tabla; en la migración queda el
    encabezado de cuatro líneas y, a lo sumo, un renglón por columna que no se
    explica sola. Una migración con ensayos de treinta líneas no la lee nadie, y
    lo que hay que consultar de verdad —«¿por qué este índice es parcial?»— queda
    enterrado entre las diez tablas en vez de estar junto a las otras nueve.

    **El orden dentro del `Schema::create()` es siempre el mismo**, y
    `timestamps()` + `softDeletes()` van **al final de todo**, después de los
    índices:

    ```
    id → claves foráneas → datos → estado → fechas del negocio
       → índices → timestamps() → softDeletes()
    ```

    El orden de esas llamadas no cambia el esquema —los índices se crean después
    de las columnas igual—, así que es una convención de lectura: las diez tablas
    terminan iguales y se sabe de memoria dónde mirar.

    **Y `softDeletes()` va en TODAS las tablas del dominio**, con el trait
    `SoftDeletes` en su modelo. Nada del dominio se borra de verdad: cada fila
    lleva el nombre de una persona y respalda un papel. Al sumar una tabla nueva
    hay que decidir además de qué lado caen sus índices únicos —**el catálogo
    libera el valor, el papel entregado lo deja quemado**—; está tabulado en
    [docs/MER.md](docs/MER.md#3-borrado-lógico-las-diez-tablas-lo-tienen).

13. **Toda sesión de trabajo se registra.** Al terminar de trabajar hay que
    dejar el registro en `docs/sesiones/MM-AAAA/AAAA-MM-DD.md`, copiando
    [docs/sesiones/_plantilla.md](docs/sesiones/_plantilla.md). Un archivo por
    día. Cada trabajo lleva su problema, la tabla de archivos modificados y la
    solución con el porqué; al final va el **informe para presentación**, que se
    escribe en lenguaje simple —sin términos técnicos— porque se copia a Word y
    lo lee gente que no programa.

---

## El patrón a copiar

El módulo **Beneficiarios** es la plantilla del sistema. Está comentado paso a
paso a propósito. Para agregar un módulo nuevo, copiar:

```
app/Http/Controllers/Panel/BeneficiarioController.php
app/Http/Requests/Panel/GuardarBeneficiarioRequest.php
routes/panel.php                                   (el bloque de beneficiarios)
resources/js/pages/panel/beneficiarios/*.tsx
resources/js/components/panel/beneficiarios/*.tsx
resources/js/types/beneficiarios.ts
```

Si el módulo tiene reglas de negocio de verdad —no solo un CRUD—, copiar además
el par `SolicitudCarnetService` + `SolicitudInvalidaException`.

El procedimiento detallado está en
[docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md#7-agregar-un-módulo-nuevo-paso-a-paso).

---

## Verificar antes de dar algo por terminado

```sh
npx tsc --noEmit        # tipos de TypeScript
./vendor/bin/pint       # formato del PHP
npm run build           # que el frontend compile
```

> ⚠️ **YA NO HAY PRUEBAS AUTOMÁTICAS.** `tests/Feature/` se vació el 14/09/2026
> por pedido del responsable del proyecto. Eran 143 y cubrían el backend entero.
>
> **Consecuencia práctica, y hay que tenerla presente en cada cambio:** nada
> avisa si se rompe una regla de negocio. Que se apruebe un trámite sin cobrar,
> que se emitan dos carnets a la misma persona en un año, que un recibo
> reimpreso salga con otro número, que alguien suba un archivo salteando
> `StorageController` — todo eso pasaba a rojo en 18 segundos y ahora **solo se
> descubre en ventanilla**.
>
> Por eso, mientras no vuelvan: **todo cambio se verifica abriendo la pantalla en
> el navegador y probando el caso a mano**, incluidos los bordes. Los tres
> comandos de arriba revisan tipos y formato; de la lógica no dicen nada.
>
> El respaldo de las que había está anotado en
> [docs/PENDIENTES.md](docs/PENDIENTES.md).

Los cuatro tienen que pasar.

---

## Trampas conocidas de este proyecto

- **Una transacción de base de datos NO deshace escrituras en disco.** Si se
  sube un archivo dentro de la transacción y algo falla, el rollback borra las
  filas pero el archivo queda huérfano para siempre. Por eso TODOS los adjuntos
  —los del trámite y la boleta de cada pago— se suben ANTES de abrir la
  transacción, y el `catch` los borra. Ver `ArchivoTramiteService` y el paso 2 de
  `SolicitudCarnetService::registrar()`.
- **`env()` en `StorageController` devolvía null con `config:cache`.** El error
  era silencioso: el sistema creía que el disco no era s3 y escribía los adjuntos
  en el servidor local sin avisar. Ahora todo sale de `config(...)`; el prefijo
  del bucket vive en `jichi.archivos.prefijo_s3`.
- **Orden de rutas:** `/beneficiarios/crear` y `/beneficiarios/buscar` van ANTES
  de `/beneficiarios/{beneficiario}`, o esas palabras se toman como id.
- **`cascadeOnDelete` NO se dispara con una baja lógica.** Es una restricción
  del MOTOR y solo corre en un DELETE de verdad; `$modelo->delete()` sobre una
  tabla con `SoftDeletes` es un UPDATE. `pagos.recibo_id` es CASCADE, así que
  dar de baja un recibo dejaría sus pagos vivos y visibles en caja, colgando de
  un comprobante que ya no está. Hoy nada da de baja recibos; el día que algo lo
  haga, la baja tiene que arrastrar el detalle a mano.
- **Índices únicos con borrado lógico:** nunca incluir `deleted_at` en un
  `unique()`. En SQL `NULL != NULL`, así que el índice no bloquea nada. Usar
  índice parcial `WHERE deleted_at IS NULL` (ver la migración de
  `beneficiarios`).
- **`update()` descarta en silencio lo que no esté en `#[Fillable]`.** No lanza
  error: simplemente no escribe la columna. Fue el motivo de que
  `fecha_aprobacion` quedara en NULL al aprobar. Si un servicio escribe una
  columna, esa columna va en la lista.
- **Releer con `lockForUpdate()` devuelve OTRA instancia del mismo registro.**
  Escribir sobre esa copia deja la instancia de quien llamó con el estado viejo
  en memoria, y cualquier comprobación posterior responde como si el cambio no
  hubiera ocurrido. La convención del servicio: se escribe sobre la copia
  bloqueada y se devuelve `$tramite->refresh()` —la original—. Ver
  `SolicitudCarnetService::bloquear()`.
- **El estado guardado de un carnet puede mentir.** `vencido` lo escribe un
  comando programado que corre una vez al día. Para saber si un carnet vale HOY
  se mira además `fecha_vencimiento`. Ver `Carnet::estaVigente()`.
- **El carnet NO tiene número: se identifica por su `firma_validacion`.** Son 16
  caracteres alfanuméricos al azar, únicos, y son a la vez el identificador y la
  llave de la verificación pública. Se guardan sin separadores y se muestran en
  grupos de cuatro con `Carnet::firmaLegible()`; lo que llega tipeado se limpia
  con `Carnet::normalizarFirma()`. **Una firma por carnet, no por rubro**: una
  adición no la cambia, o el QR ya impreso dejaría de funcionar.
- **Dos botones en la misma posición de un ternario necesitan `key` distinto.**
  React los reconcilia como el MISMO `<button>` y solo le cambia el atributo
  `type`; si uno es `type="button"` y el otro `type="submit"`, el cambio ocurre
  mientras el clic se está procesando y el formulario se envía solo. En el
  formulario de trámite eso registraba la solicitud al pasar del paso 2 al 3, con
  los adjuntos vacíos. Ver la barra de navegación de `pages/panel/tramites/crear.tsx`.
- **Pedir columnas sueltas en un `with()` rompe los métodos del modelo, y no
  avisa.** `with('rubro:id,nombre')` deja `emite_faenas` sin cargar, así que
  `Carnet::puedeEmitirFaenas()` lee null, devuelve false, y el formulario de
  faenas abre vacío descartando en silencio un carnet perfectamente válido.
  Es la misma trampa que ya estaba anotada para las cinco columnas del nombre
  del beneficiario, pero vale para CUALQUIER columna que un método lea: si el
  modelo la consulta, va en el select. Ver `FaenaController::create()`.
- **Un `default` de la base NO llega al objeto que devuelve `create()`.** El
  INSERT lo aplica el motor, y el modelo en memoria se queda con la columna en
  `null` hasta que alguien haga `refresh()`. Eso rompe lo obvio: emitir una
  faena y preguntarle `estaEmitida()` en la línea siguiente contestaba que no,
  con la fila ya escrita y correcta en la base. Si una columna tiene valor por
  defecto y el código lo lee, va **también** en `protected $attributes` del
  modelo. Pasó TRES veces en un día —`faenas.estado`, `faenas.monto`,
  `pagos.estado_validacion`— y las tres se descubrieron igual: una prueba que
  preguntaba por el estado justo después de `create()` — con el `->value` del enum, porque `$attributes` se llena antes de que
  corran los casts. Ver `Faena` y `Guia`.
- **Una relación polimórfica NO se puede precargar con `with('pagable.carnet')`.**
  Eloquent no sabe qué es `pagable` hasta que lee la fila, así que no puede
  resolver lo que cuelga de él: lo que se escribe así se ignora y el N+1 sigue
  ahí, sin ningún error. Va con `morphWith`, declarando qué traer para cada
  tipo. Ver `PagoController::index()`.
- **Clases de Tailwind armadas juntando textos no funcionan.** Tailwind solo
  incluye en el CSS final las que puede leer literalmente en el código. Si se
  agrega un color a un enum de PHP, hay que agregarlo también al mapa de
  `components/ui/badge.tsx`.
- **Los gráficos de recharts necesitan que el contenedor padre tenga altura**
  (`h-72`), o se calculan con altura cero y no se ven.
- **Un `overflow-x-auto` adentro de una grilla NO desplaza nada sin `min-w-0`.**
  Un elemento de grilla arranca con `min-width: auto`, que significa «no te
  encojas por debajo de tu contenido». Con una tabla de seis columnas adentro,
  la tarjeta se estira a los 675 px que mide la tabla aunque la pantalla tenga
  375, y el que queda con barra de desplazamiento es **el documento entero**: en
  el celular se corre de costado la pantalla completa —menú, encabezado y todo—
  para leer una columna. `min-w-0` en el elemento de grilla devuelve el permiso
  de encogerse, y recién ahí el `overflow-x-auto` hace su trabajo. Lo mismo vale
  para un elemento `flex`. Ver `tabla-ultimos-tramites.tsx`. **No se nota en el
  escritorio**, que es donde se prueba: aparece solo al angostar la ventana.
- **`new Date('2026-12-31')` en JavaScript NO da el 31 de diciembre.** El
  estándar interpreta una cadena `AAAA-MM-DD` como medianoche UTC, y Bolivia está
  en UTC-4: al formatear en horario local sale **30/12**. Afectaba al vencimiento
  de todos los carnets y a las fechas de nacimiento. Toda fecha se muestra con
  `fecha()` de `lib/utils.ts`, que distingue una fecha suelta de un instante; ver
  `aFechaLocal()`. Nunca `new Date(cadena)` directo en un componente.

  **Y `fecha()` no alcanza si el SERVIDOR manda la forma equivocada.** Es la
  otra mitad de la misma trampa y mordió con `pagos.fecha_pago`: la columna es un
  timestamp, pero lo que guarda es el DÍA que dice la boleta. Mandada con
  `toIso8601String()` llegaba como `2026-09-17T00:00:00+00:00` —un instante— y
  `fecha()` hacía lo correcto con él: pasarlo a horario local, que en UTC-4 es el
  16 a las 20:00. **Un depósito del 17 se mostraba como 16/09**, y nadie lo notó
  hasta que un formulario tuvo que leer esa fecha de vuelta. La regla: si la
  columna guarda un DÍA, va con `toDateString()`; si guarda un MOMENTO —cuándo se
  validó, cuándo se cargó— va con `toIso8601String()`. Y para rellenar un
  `<input type="date">` va `fechaInput()`, nunca `slice(0, 10)`.
- **`pluck()` sobre una columna que no existe NO FALLA: devuelve nulls.** Es el
  mismo silencio de `update()` con algo fuera de `#[Fillable]`, y acá costó
  archivos: `rutasDeAdjuntos()` juntaba las boletas de los pagos con
  `pluck('comprobante')` —la columna es `urlFile`; `comprobante_url` es el
  accesor con la dirección completa— así que **cada expediente eliminado dejaba
  todas sus boletas tiradas en el disco**, para siempre y sin ningún error. Al
  escribir un `pluck()`, un `where()` o un `select()` a mano contra un nombre de
  columna, confirmarlo en el `#[Fillable]` del modelo o en la migración: los
  accesores `#[Appends]` se parecen a columnas y no lo son.
- **`beneficiarios` usa camelCase de `primerNombre` en adelante.** En PostgreSQL
  eso obliga a entrecomillar: `SELECT "primerNombre" ...`. Sin comillas el motor
  pasa el nombre a minúscula y responde `column "primernombre" does not exist`.
  Laravel entrecomilla solo; el problema aparece al escribir SQL a mano o al
  usar `whereRaw` / `orderByRaw` (ver `Beneficiario::SQL_NOMBRE`).
- **Un accesor camelCase NO puede ir en `#[Appends]`.** Laravel busca el accesor
  por el nombre del método pasado a snake_case, así que `nombreCompleto` no lo
  encuentra, cae al accesor de estilo viejo y revienta con
  «Call to undefined method getNombreCompletoAttribute()». Acceder directo
  (`$beneficiario->nombreCompleto`) sí funciona. Ver `app/Models/Beneficiario.php`.
- **`attach()` no dispara eventos de Eloquent**, así que el trait `Auditable` no
  registra nada. Por eso las habilitaciones se crean con `CarnetRubro::create()`
  y el pivote es un modelo propio.
- **`$modelo->relacion()->where(...)` consulta SIEMPRE**, aunque quien llamó haya
  hecho `with('relacion')` justamente para evitarlo. El `with()` queda escrito,
  se ve correcto, y el N+1 sigue ahí en silencio —el autocompletado de
  beneficiarios hacía 18 consultas por tecleada con el eager loading puesto—. Un
  método del modelo que lo use debe preguntar antes con `relationLoaded()`; ver
  `Beneficiario::carnetDeGestion()`.
- **`php artisan serve` LEE EL `.env` UNA SOLA VEZ, y REINICIAR EL SERVIDOR NO
  ES LO QUE PARECE.** Es la trampa más cara de este proyecto hasta ahora: costó
  dos diagnósticos equivocados.

  El proceso es un árbol de tres:

  ```
  php artisan serve          ← LEE el .env, una vez, al arrancar
    └── cmd.exe
          └── php -S ...     ← el que atiende, hereda el entorno YA congelado
  ```

  `artisan serve` **vigila el `.env` y reinicia solo al hijo** cuando cambia.
  Entonces el hijo aparece con hora de recién —parece reiniciado, y hasta
  coincide al segundo con la hora del archivo— pero recibe las variables del
  padre, que son las del arranque. Como Dotenv **no pisa** variables que ya
  existen en el entorno, cada request vuelve a leer el valor viejo. Puede seguir
  así durante días.

  El síntoma no delata nada de esto: sale como `relation "..." does not exist`
  sobre una tabla que uno acaba de migrar y puede ver en el gestor.

  **Antes de dudar del esquema, mirar qué base dice el error**: el mensaje trae
  `(Connection: pgsql, ..., Database: X)`. Y para saber quién quedó viejo, mirar
  la hora de arranque del **padre**, no la del que escucha el puerto:

  ```sh
  # PowerShell: todos los php con su hora de arranque y su línea de comando
  Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | ForEach-Object {
      $p = Get-Process -Id $_.ProcessId; "$($_.ProcessId)  $($p.StartTime)  $($_.CommandLine)"
  }
  ```

  Acá el padre de todo es `composer run dev` → `artisan dev`, que levanta
  `serve`, `queue:listen` y Vite: **hay que cortar ESO**, no el servidor solo.
  `queue:listen` arrastra el mismo entorno viejo.
- **`withSum()` devuelve NULL cuando no hay filas, no cero.** Es lo que
  contesta `sum()` en SQL sobre un conjunto vacío, y rompe el patrón de
  «reusar el agregado si vino en la consulta»: escrito como
  `if ($this->pagos_sum_monto === null) { consultar }`, justamente la fila SIN
  pagos —la que más aparece en un listado— se cae a la consulta agregada suelta,
  con el `withSum` puesto, viéndose correcto y sin ningún error. El N+1 sigue
  ahí para la mitad de las filas. Se pregunta si la CLAVE EXISTE:
  `array_key_exists('pagos_sum_monto_parcial', $this->getAttributes())`. Ver
  `App\Traits\Pagable::montoPagado()` y
  `AprovechamientoPesq::kilosConsumidos()`.
- **El trait `Auditable` YA registra el borrado: escribir la auditoría a mano
  deja DOS filas.** Engancha `created`, `updated` y `deleted`, así que un
  servicio que además llame a `registrarAuditoria('eliminado', …)` duplica el
  hecho — y la copia automática va SIN motivo, porque el motivo viaja por
  `$modelo->motivoAuditoria`. El historial muestra el mismo borrado dos veces,
  una de ellas sin ninguna explicación. La forma correcta es dejar el motivo y
  borrar:

  ```php
  $modelo->motivoAuditoria = $motivo;
  $modelo->delete();
  ```

  **Y el ensayo tampoco lo delata si busca con `first()`:** devuelve la fila
  correcta y da verde con la duplicada al lado. Al probar una auditoría, contar
  las filas, no leer la primera. Se descubrió mirando la tabla en el navegador.
- **Agregar un estado a un enum rompe cosas que no se ven, y el compilador no
  avisa de ninguna.** Pasó TRES veces con `EstadoAprovechamiento` —al sumar
  `pendiente` y al sumar `en_revision`— y siempre en el mismo lugar: el scope
  `enCurso()` y los servicios que enumeran estados a mano. **Al sumar un estado,
  la lista de lugares a revisar es fija:** los scopes del modelo, los
  `match`/`if` de los servicios que comparan contra un caso concreto, y las
  banderas `puede_*` que el controlador manda a la pantalla. Al sumar `pendiente` a `EstadoAprovechamiento` los `match`
  sí fallaron —eso sí lo marca PHP—, pero lo caro fue lo otro: cada lugar que
  preguntaba `vigentes()` pasó a contestar «no» para el estado nuevo, en
  silencio. Dos ejemplos reales, los dos encontrados por un ensayo y no leyendo:
  el carnet de pescador dejó de poder emitirse —exigía un cupo ya cobrado— y la
  regla de «una bolsa por persona» dejó de contar los cupos sin pagar, así que se
  podían otorgar cinco y quedarse con el mejor. **Al sumar un estado, buscar
  todos los scopes y helpers que enumeran estados y decidir uno por uno de qué
  lado cae el nuevo.**
- **Un método que «revive» un registro puede activar lo que nunca se autorizó.**
  El viejo `ampliar()` —retirado el 19/09/2026— escribía `estado = Activo` a
  secas para revivir un cupo agotado; cuando apareció `pendiente`, ampliar pasó a
  habilitar para pescar un cupo sin cobrar, que era la puerta de atrás del cobro.
  Un `update` de estado a un valor fijo hay que mirarlo de nuevo cada vez que se
  suma un estado del que ese valor no debería alcanzarse.
- **`validated()` devuelve SOLO las claves que vinieron en la petición.** Un
  campo `nullable` que el formulario no manda no existe en el arreglo, así que
  `$datos['campo']` revienta con «Undefined array key» y un 500 — no devuelve
  null. Va `$datos['campo'] ?? null`. Pasó con `nit_ci_factura` al cobrar desde
  la ficha del cupo, y **ningún ensayo lo detecta**: llamando al servicio
  directamente se pasan todos los argumentos, y el agujero solo aparece cuando
  lo llena un formulario de verdad.
- **Para borrar un método de PHP, NO se usa una expresión regular multilínea.**
  Las llaves anidadas no se pueden expresar con una regex, así que lo que sobra o
  falta no se nota hasta que el archivo ya está escrito: al sacar `ampliar()` de
  `OtorgarCupoService`, una regex «prudente» se llevó también `otorgar()`,
  `editar()` y `eliminar()` —de 397 líneas a 92—. Va por NÚMERO DE LÍNEA, con un
  `assert` sobre el contenido de cada borde antes de cortar. Y conviene mirar si
  el archivo está commiteado antes de empezar: `git checkout HEAD -- <archivo>`
  fue lo que lo salvó.
- **Probar una pantalla con `curl` y la cabecera `X-Inertia` devuelve 409, no
  la página.** Inertia compara la versión del manifiesto de assets y responde
  `409 Conflict` con `X-Inertia-Location` cuando no coincide —que es siempre, si
  el número se inventa—. **Se pide sin ninguna cabecera de Inertia:** las props
  viajan igual, adentro del atributo `data-page` del HTML, y se leen con un
  `grep` del nombre de la clave. Un 409 acá NO es un error de la pantalla.
- **Una bandera de configuración que apaga una validación tiene que llegar a la
  PANTALLA, o la pantalla miente.** Con `APROVECHAMIENTO_ESTRICTO=false` el
  servidor acepta una faena que se pasa del cupo, y el formulario la seguía
  frenando con «no entra en el cupo»: una regla inventada en React sobre algo
  que el sistema permite, sin ningún mensaje que lo explicara. El patrón que
  quedó es separar el HECHO de la CONSECUENCIA —`excede` y `bloquea`— y mandar
  el modo como prop. Ver `FaenaController::create()` y `faenas/crear.tsx`.
- **`estaVigente()` mezcla estado y fecha, y eso esconde botones.** Un cupo
  AGOTADO no está vigente —su estado no habilita— y es exactamente el que hay
  que poder tocar. Mordió diciendo «no tiene un aprovechamiento vigente» al
  emitir una faena sobre un cupo agotado pero en fecha, lo que mandaba al
  operador a otorgar uno nuevo que la regla de una bolsa por persona iba a
  rechazar. **Antes de usar `estaVigente()` como permiso, preguntarse qué se está
  preguntando de verdad**: hay `estaEnFecha()`, `puedeEditarse()`,
  `puedeEliminarse()` y `puedeEmitirFaena()`, y cada una mira cosas distintas.
- **`monto` NO es una columna de `aprovechamientos_pesq`.** Lo que se cobra lo
  calcula `montoACobrar()` leyendo el `valor_bs` de la escala con la que se
  otorgó. Pedirlo como propiedad devuelve vacío en silencio —la misma trampa que
  `pluck()` sobre una columna inexistente— y un ensayo que lo compare contra la
  tarifa da en rojo por el lado equivocado.
- **`->withQueryString()`** en todo paginador con filtros, o al cambiar de página
  se pierden.
- **LAS PRUEBAS NO AVISAN SI FALTA CORRER UNA MIGRACIÓN O UN SEEDER.** Corren
  sobre SQLite en memoria con `RefreshDatabase`, que arma el esquema entero desde
  cero en cada corrida: una tabla nueva existe ahí aunque nadie haya hecho
  `migrate` sobre PostgreSQL. Lo mismo con un permiso o una clave de
  configuración nuevos, que los seeders siembran en la base de pruebas y no en la
  de desarrollo. **Al agregar una migración, un permiso al enum `RolSistema` o
  una clave a `ConfiguracionSeeder`, hay que correr a mano:**

  ```sh
  php artisan migrate
  php artisan db:seed --class=RolPermisoSeeder    # si tocaste RolSistema
  ```

  Y después abrir la pantalla en el navegador. Pasó con la tabla `recibos`: la
  pantalla reventaba con `relation "recibos" does not exist` mientras las
  pruebas de entonces estaban todas en verde, porque corrían sobre un esquema
  armado desde cero en memoria.
- **DomPDF no es un navegador.** No entiende flexbox, grid ni variables CSS, y no
  ejecuta JavaScript: los documentos se maquetan con tablas y `position:
  absolute`. Además su `opacity` es poco confiable —según la versión lo ignora y
  un sello de agua sale a pleno color tapando el texto—, así que la atenuación va
  horneada en el PNG. Y las imágenes van **embebidas en base64**: una ruta se
  resuelve contra el disco con las restricciones de `chroot` y en producción
  termina en un recuadro vacío. Ojo con el peso: embeber los PNG del panel hacía
  un PDF de 5,4 MB por recibo; hay copias a medida en `public/image/recibo-*.png`.
- **Blade escapa las entidades HTML de su interpolación de dos llaves.** Un
  `&nbsp;` puesto ahí se imprime como texto literal `&nbsp;` en el PDF. Se
  resuelve con un elemento de ancho fijo, no con la entidad.
- **`simplesoftwareio/simple-qrcode` NO PUEDE generar PNG acá.** Su salida PNG
  exige la extensión `imagick`, que no está instalada (`php -m` lista `gd`), y
  revienta con «Extension 'Imagick' is required». Solo le queda SVG, y un QR es
  justamente donde no conviene depender de un renderizador aproximado: medio
  punto de corrimiento y la cámara deja de leerlo, cosa que no se descubre hasta
  que alguien intenta verificar un carnet en la calle. El QR se arma con
  `App\Support\CodigoQr`, que pide la matriz a BaconQrCode —la librería que ese
  paquete trae adentro— y la pinta con `gd`.
- **Un PNG de PALETA devuelve ÍNDICES, no colores.** `imagecolorat()` sobre una
  imagen de paleta no da el RGB sino la posición en la tabla, así que medir
  brillo o transparencia con esos números da resultados absurdos —el sello del
  recibo daba «luminancia 27, oscuro» cuando en realidad era casi blanco—. Hay
  que pasar por `imagecolorsforindex()`, o convertir primero con
  `imagepalettetotruecolor()`. `imageistruecolor()` dice cuál de los dos es.
- **El sello de agua del recibo se aclara u oscurece REGENERANDO EL PNG**, no con
  CSS: `opacity` no es confiable en DomPDF. Se mezcla `sedag.png` contra blanco
  con un factor —0,25 hoy— y se guarda **en paleta**, o el archivo se cuadruplica
  y va embebido en cada recibo. La receta está en el comentario de `.sello` de
  `recibo-oficial.blade.php`.
- **`rgba()` en DomPDF: la 3.1.6 SÍ lo respeta, comprobado.** La regla vieja
  —«depende de la versión»— sigue valiendo como advertencia, pero no como
  prohibición: se midió el PDF y el relleno del cuadro del SEDAG sale mezclado
  con el verde de abajo, no opaco. Antes de descartar una propiedad moderna de
  CSS conviene **probarla y medir el archivo**, que sale más barato que la
  maniobra para evitarla. Lo mismo pasó con `border-radius`, que la 3.1.6 dibuja
  bien.
- **DomPDF no dibuja degradados ni contornos de texto.** En el carnet, el degradado verde y el sello van HORNEADOS
  en `carnet-fondo.png`, y el blanco al 85% de la pantalla va como color sólido
  ya mezclado sobre el verde.

  **Por eso el verde del carnet vive en DOS lugares**: el PNG para el PDF y un
  `linear-gradient` de CSS para la vista previa. Se tocan los dos o se separan.
  Y para recolorearlo, el PNG **no se regenera** —habría que rehacer la mezcla
  del sello—: se le aplica un ajuste en HSV al archivo entero. Multiplicar el
  RGB a secas apaga el verde hacia el oliva.
- **DomPDF no dibuja contornos de texto.** No tiene `-webkit-text-stroke` ni
  `text-shadow`, y lo escrito con ellas se dibuja sin contorno y sin avisar. El
  perfilado se hace a mano dibujando el texto **cinco veces** —cuatro copias del
  color del borde corridas hacia cada esquina, y la cara encima—. Vive en
  `views/documentos/partes/texto-perfilado.blade.php`, que pone el andamio; el
  color, el cuerpo y el corrimiento los pone la hoja de estilos de quien la
  incluye. En el navegador alcanza con `text-shadow` en cuatro direcciones.

  **El corrimiento se elige contra el CUERPO, no contra el gusto: más o menos un
  6%.** El título del carnet va a 11 pt corrido 0,5; los rótulos, a 4,6 corridos
  0,3. Medio punto sobre 4,6 pt no perfila —engorda la letra hasta cerrarle los
  huecos, la «O» se llena y la «E» se vuelve una mancha— y el texto deja de
  leerse, que es lo contrario de lo que el contorno viene a hacer. Por eso
  tampoco sirve `-webkit-text-stroke` en la vista previa: su trazo va CENTRADO
  sobre el contorno, así que la mitad se come el relleno.
- **En el carnet, el RÓTULO del segundo par también tiene un ancho fijo, y nadie
  lo mide.** El cálculo de encogido de `texto()` protege a los VALORES: si un
  nombre no entra, se achica. Los rótulos no pasan por ahí —son constantes— así
  que uno largo se desborda en silencio sobre lo que tenga al lado. Pasó con
  «PROVINCIA»: a 6,1 pt bold mide 33,2 pt en una caja de 32, y en el PDF salía
  «PROVINCIACercado» pegado. Se abrevia a «PROV.». Antes de agregar un rótulo al
  segundo par, medirlo: **caracteres × 0,605 × cuerpo**.
- **`ANCHO_POR_CARACTER` del carnet está atado al GRUESO de la letra de la
  tira.** Es el número con el que `CarnetImpresionController::texto()` decide si
  un nombre entra o hay que achicarlo, y hay que moverlo si ese grueso cambia.
  Medido sobre las DejaVu Sans que embebe DomPDF: la **regular** promedia 0,539
  em por carácter, la **negrita** 0,605, y el peor caso —puras mayúsculas— 0,67.
  Se deja un ~2% de margen sobre el promedio a propósito: quedarse corto es peor
  que pasarse, porque lo que no entra lo recorta el `overflow: hidden` de la tira
  y ahí se pierden apellidos.
- **En CSS el `padding` SUMA al `width`**, y en una maqueta de coordenadas fijas
  eso descoloca sin avisar. **Volvió a morder al partir un renglón en dos:** la
  tira del rubro se declaró de 71 pt pensando que cerraba en 118,5, y con sus
  4 pt de relleno cerraba en 122,5 — así que el rótulo «CUPO», plantado en 121,
  salió impreso ENCIMA de la tira blanca. Al plantar una caja con coordenadas,
  el ancho declarado es el que se ocupa menos el relleno. Las tiras del carnet declaraban los 130 pt que tenían
  que ocupar MÁS 4 de relleno, así que terminaban 4 pt más allá de su columna:
  la tarjeta quedaba a 2,4 pt del borde derecho y a 8,5 del izquierdo. Nadie lo
  vio durante seis versiones porque las seis tiras desbordaban lo mismo —un error
  parejo se lee como un diseño—; saltó recién cuando un rótulo nuevo se les
  encimó. **Al plantar una caja con coordenadas, el ancho declarado es el que se
  ocupa menos el relleno.**
- **Un texto blanco puro puede verse GRIS, y no es un problema de color.** Es de
  grosor: a un cuerpo chico el trazo es tan fino que el ojo lo promedia con el
  fondo. Medirlo lo confirma —el núcleo del glifo da 255— así que subirle el
  blanco o ponerle un borde no toca la causa; **la única palanca es el cuerpo**.
  Los rótulos del carnet fueron 4,6 → 5,2 → 6,1 pt por eso.
- **Un texto claro sobre el verde del carnet puede no necesitar contorno.** El fondo no es
  liso: abajo corre el sello de agua del SEDAG, que le cambia el tono al texto
  según por dónde pase. Los rótulos en blanco puro se desdibujaban en los tramos
  claros del sello, y desaparecían del todo impresos con poco tóner.
- **DomPDF no tiene `object-fit`.** Una foto vertical metida en un recuadro
  cuadrado con `width` y `height` fijos sale APLASTADA, y en un documento de
  identidad eso es justamente lo que no puede pasar. El `cover` se hace a mano:
  la imagen se dibuja a su proporción real desbordando el recuadro, corrida con
  un margen negativo, y el contenedor con `overflow: hidden` la recorta. En un
  retrato el corte va a un TERCIO y no a la mitad: la cara está arriba.
- **La foto del beneficiario se REDUCE antes de embeberla.** Sale del teléfono
  de ventanilla con varios megapíxeles, y embebida entera hacía un carnet de
  442 KB para dibujar un cuadrito de 17 mm. Ver
  `CarnetImpresionController::reducir()`.
- **`iframe.onLoad` NO dispara con un PDF.** Medido: con el visor de PDF del
  navegador el evento no llega nunca, así que un «cargando…» que dependa solo de
  él se queda colgado. Y `iframe.contentWindow.print()` sobre un PDF lo ignora o
  lo bloquea según la versión — para imprimir se usa la barra del propio visor o
  una pestaña aparte.
- **En un documento impreso, un texto que no entra se ACHICA; no se corta.** El
  `truncate` de la pantalla acá pierde datos: un nombre recortado se queda sin
  los apellidos, que es lo que identifica a la persona en un control. Ver
  `CarnetImpresionController::texto()`. Tres cosas que costaron una vuelta cada
  una: el ancho contra el que se mide es el de la CAJA menos su relleno, no el
  de la columna —medido sobre la columna entran treinta caracteres menos de los
  que el cálculo cree—; al pasar a dos líneas hay que descontar un ~15%, porque
  las palabras no se parten y la primera línea deja sobrante; y el ALTO de la
  caja tiene que crecer con las líneas, porque con alto fijo la segunda sale
  cortada por la mitad, que se ve peor que si nunca hubiera entrado.
- **Un `position: absolute` de una segunda página se dibuja sobre la primera**
  si su contenedor no es `position: relative`: sin contenedor posicionado se mide
  contra la página y aterriza encima de lo anterior. Por eso la carilla del
  carnet es un `.carilla` relativo aunque hoy haya una sola.
- **Lo que se imprime en un documento tiene que ser lo que NO cambia.** Es el
  criterio, y conviene ver cómo se aplicó en los dos sentidos, porque la
  respuesta se dio vuelta con el cambio de modelo.

  Con el carnet viejo —uno por persona, con los rubros colgados— el plástico NO
  llevaba ni los rubros ni el cupo: una adición posterior dejaba vieja la lista
  impresa y el documento pasaba a decir MENOS de lo que la persona podía hacer.
  Hoy el carnet es de UN rubro que es parte de su llave y no cambia nunca, así
  que **los dos van impresos** — y hacen falta, porque sin el rubro dos carnets
  de la misma persona son plásticos idénticos.

  Lo que sigue sin imprimirse es el ESTADO: un carnet se suspende o se anula
  después de impreso y la tarjeta no se entera. Mismo criterio que la copia
  congelada del recibo, mirado desde el otro lado.

  **La lección no es «imprimir todo» ni «imprimir poco»: es preguntarse qué
  puede cambiar después de que el plástico salga de la impresora.**

---

## Lo que falta

Dos módulos: Reportes y Configuración. Aparecen en gris en el menú lateral. La
lista completa, con los problemas conocidos que siguen abiertos, está en
[docs/PENDIENTES.md](docs/PENDIENTES.md).

---

## Al terminar, actualizá la documentación

Estos archivos existen para que nadie —persona o agente— tenga que leer el
sistema entero para entenderlo. Eso solo se sostiene si se mantienen:

| Cambiaste... | Actualizá |
| --- | --- |
| Una regla de negocio, el esquema, un flujo | `docs/ARQUITECTURA.md` |
| Agregaste o cambiaste un archivo a fondo | `docs/MAPA-ARCHIVOS.md` |
| Un módulo entero | `docs/modulos/<MODULO>.md` |
| Faenas o guías | `docs/modulos/PERMISOS-OPERATIVOS.md` |
| Pagos o su control | `docs/modulos/PAGOS.md` |
| Resolviste o encontraste un problema | `docs/PENDIENTES.md` |
| Cualquier cosa | `docs/sesiones/MM-AAAA/AAAA-MM-DD.md` |

Y si tropezaste con algo que te hizo perder tiempo y no estaba anotado, va a
«Trampas conocidas» de este archivo. Es la sección que más tiempo ahorra.
