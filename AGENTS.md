# Jichi — guía para agentes de IA

Sistema de gestión de carnets, rubros y trámites del Gobierno Autónomo
Departamental del Beni, Bolivia.

**Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript · Tailwind 4 ·
PostgreSQL 18** (corre también en SQLite; las pruebas usan SQLite en memoria).

## Antes de tocar nada — y antes de leer código

> **NO leas el sistema entero para entenderlo.** Está documentado a propósito
> para que no haga falta:
>
> | Leé esto | Para |
> | --- | --- |
> | [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Entender el sistema. **Empezá acá siempre** |
> | [docs/MAPA-ARCHIVOS.md](docs/MAPA-ARCHIVOS.md) | Qué hace cada archivo y qué tiene de no obvio |
> | [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Dónde va un archivo **nuevo** |
> | [docs/modulos/](docs/modulos/) | Un módulo en profundidad |
> | [docs/PENDIENTES.md](docs/PENDIENTES.md) | Qué falta y qué está roto |
>
> Recién después abrí código, y abrí **el archivo que vas a cambiar**, no el
> resto. Si al terminar sabés algo que esos `.md` no decían, agregalo ahí.

---

## El dominio en cinco líneas

Un **beneficiario** saca un **carnet**, que es anual. Sobre ese carnet se
habilitan **rubros** (actividades). Para habilitar un rubro se presenta un
**trámite**, que se cubre con uno o varios **pagos**. Al aprobarlo nace la fila
en `carnet_rubro`, y recién ahí la persona queda habilitada.

El expediente recorre este circuito:

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
(borrador)                   │                         │
                             │                         └── nace carnet_rubro
                             ├── nace el RECIBO OFICIAL
                             └──[rechazar]──▶ RECHAZADO
```

**Enviar a revisión es OBLIGATORIO: no se aprueba desde PENDIENTE.** Mientras
está pendiente el expediente se arma —papeles, depósitos, correcciones—; al
enviarlo, ventanilla declara que está completo y pasa a quien lo firma. El
atajo `PENDIENTE ──▶ APROBADO` existió y se sacó: con él, quien cargaba la
solicitud podía aprobarla sin que nadie más la tocara.

**PENDIENTE es un BORRADOR, y eso define qué se puede hacer en cada estado:**

| Estado | Editar | Eliminar | Enviar | Aprobar | Rechazar |
| --- | :-: | :-: | :-: | :-: | :-: |
| **Pendiente** | ✔ | ✔ | ✔ | ✘ | ✘ |
| **En revisión** | ✘ | ✘ | ✘ | ✔ | ✔ |
| Aprobado / Rechazado | ✘ | ✘ | ✘ | ✘ | ✘ |

Las dos mitades de esa tabla salen de la misma idea:

- **En pendiente no se rechaza.** Rechazar es la respuesta a algo que alguien
  PRESENTÓ, y un borrador todavía no se presentó — lo está armando la misma
  ventanilla. Un borrador que no sirve se ELIMINA, que también pide motivo.
- **En revisión no se edita ni se elimina.** Al enviar salió el RECIBO OFICIAL
  numerado y el pescador se fue con ese papel; además, quien aprueba firma sobre
  los papeles que vio. Un expediente presentado que no sirve se RECHAZA, con su
  motivo escrito.

La tabla la dicta `EstadoTramite::permiteEdicion()`, `permiteEliminacion()` y
`siguientes()`. **Ojo con `estaAbierto()`**: agrupa pendiente + en revisión y
sirve para contar trabajo sin terminar, pero NO es permiso de escritura.

Impreso y entregado no son estados sino fechas: `fecha_generacion` y
`fecha_entrega`. Un estado obliga a sincronizar dos cosas que pueden discrepar;
una fecha en NULL dice «todavía no pasó» sin posibilidad de contradicción.

Al pasar a EN REVISIÓN se emite el **RECIBO OFICIAL** —el talonario verde del
SEDAG— dentro de la misma transacción: es el momento en que el pescador entregó
los papeles y la plata, y se va con su comprobante. Ver
[docs/modulos/RECIBOS.md](docs/modulos/RECIBOS.md).

Al aprobar nace la habilitación, y recién ahí el carnet se puede IMPRIMIR:
`GET /panel/carnets/{carnet}/imprimir` dibuja el plástico —una carilla, CR80,
calcando la cédula de papel—. **La maqueta impresa y `vista-previa-carnet.tsx`
son el mismo diseño escrito dos veces**: ese componente es el recuadro «así va a
salir el carnet» del paso 3 del formulario, así que si se toca una hay que tocar
la otra, o la vista previa pasa a prometer algo que el PDF no cumple. La tarjeta
NO lleva QR —se sacó a pedido— así que `App\Support\CodigoQr` queda escrito y
sin usar. Ver [docs/modulos/CARNETS.md](docs/modulos/CARNETS.md). Ver [docs/modulos/CARNETS.md](docs/modulos/CARNETS.md).

> **LA REGLA QUE ORDENA TODO: una persona tiene como máximo UN carnet por
> gestión.**

De ahí salen los dos únicos tipos de trámite, y **los decide el sistema, no el
operador**:

| Situación | Tipo | Qué hace |
| --- | --- | --- |
| No tiene carnet de este año | `emision_inicial` | Crea el carnet y cuelga el trámite |
| Ya tiene carnet de este año | `adicion_rubro` | Reutiliza ese carnet |

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

9. **Scopes con `qualifyColumn()`.** `tramites`, `carnets`, `rubros` y
   `carnet_rubro` tienen todas una columna `estado`, y los reportes las cruzan
   con `join`.

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

12. **Toda sesión de trabajo se registra.** Al terminar de trabajar hay que
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
- **Clases de Tailwind armadas juntando textos no funcionan.** Tailwind solo
  incluye en el CSS final las que puede leer literalmente en el código. Si se
  agrega un color a un enum de PHP, hay que agregarlo también al mapa de
  `components/ui/badge.tsx`.
- **Los gráficos de recharts necesitan que el contenedor padre tenga altura**
  (`h-72`), o se calculan con altura cero y no se ven.
- **`new Date('2026-12-31')` en JavaScript NO da el 31 de diciembre.** El
  estándar interpreta una cadena `AAAA-MM-DD` como medianoche UTC, y Bolivia está
  en UTC-4: al formatear en horario local sale **30/12**. Afectaba al vencimiento
  de todos los carnets y a las fechas de nacimiento. Toda fecha se muestra con
  `fecha()` de `lib/utils.ts`, que distingue una fecha suelta de un instante; ver
  `aFechaLocal()`. Nunca `new Date(cadena)` directo en un componente.
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
- **DomPDF no dibuja degradados, ni contornos de texto, ni entiende `rgba()`
  según la versión.** En el carnet, el degradado verde y el sello van HORNEADOS
  en `carnet-fondo.png`, y el blanco al 85% de la pantalla va como color sólido
  ya mezclado sobre el verde.
- **DomPDF no dibuja contornos de texto.** No tiene `-webkit-text-stroke` ni
  `text-shadow`. El título rojo perfilado de dorado del carnet son **cinco
  copias** del mismo texto: cuatro en dorado corridas 0,7 pt hacia cada esquina
  y la quinta en rojo encima. En el navegador —la vista previa— alcanza con
  `text-shadow` en cuatro direcciones.
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
- **Lo que se imprime en un documento tiene que ser lo que NO cambia.** El
  plástico del carnet no lleva los rubros ni el cupo en kilos, y no es por falta
  de lugar: el carnet es uno por persona y por gestión, y una adición posterior
  dejaría vieja cualquier lista impresa —el documento diría MENOS de lo que la
  persona puede hacer—. Eso se consulta escaneando el QR. Mismo criterio que la
  copia congelada del recibo, mirado desde el otro lado.

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
| Resolviste o encontraste un problema | `docs/PENDIENTES.md` |
| Cualquier cosa | `docs/sesiones/MM-AAAA/AAAA-MM-DD.md` |

Y si tropezaste con algo que te hizo perder tiempo y no estaba anotado, va a
«Trampas conocidas» de este archivo. Es la sección que más tiempo ahorra.
