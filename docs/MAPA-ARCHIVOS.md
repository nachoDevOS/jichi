# Mapa de archivos

Qué hace cada archivo y **qué tiene de no obvio**. Pensado para no tener que
abrirlos: si la fila no dice nada raro, el archivo hace lo que su nombre indica.

Leer junto con [ARQUITECTURA.md](ARQUITECTURA.md), que explica el porqué de las
decisiones que acá solo se nombran.

> La columna «líneas» sirve para calibrar: un archivo de 50 líneas se abre sin
> pensar; uno de 1.000 conviene entenderlo desde acá primero.

---

## `app/Enums/` — los valores fijos del negocio

| Archivo | Ln | Qué es | No obvio |
| --- | --- | --- | --- |
| `EstadoTramite.php` | 261 | `pendiente → en_revision → aprobado \| rechazado` | **Única fuente de verdad de las transiciones.** `siguientes()` decide qué salto vale. Se puede aprobar directo desde pendiente. `abiertos()` agrupa pendiente + en revisión |
| `EstadoCarnet.php` | 73 | `vigente \| vencido \| anulado` | `vencido` lo escribiría un comando programado que **no existe todavía** |
| `EstadoHabilitacion.php` | 49 | `habilitado \| suspendido` — un rubro DENTRO de un carnet | Suspendido **bloquea igual** que habilitado: la habilitación existe |
| `EstadoRubro.php` | 48 | `activo \| inactivo` | Un rubro nunca se borra |
| `TipoTramite.php` | 74 | `emision_inicial \| adicion_rubro` | **Lo decide el sistema, no el operador** |
| `RolSistema.php` | 124 | Roles y **todos** sus permisos | Un solo rol hoy (`administrador`). Los permisos ya están en cuatro bloques para poder agregar el segundo en una línea |
| `FormaPago.php` | 52 | `deposito \| efectivo` | Son las dos casillas del recibo de papel |
| `ConceptoRecibo.php` | 110 | Las seis casillas de DESCRIPCIÓN del recibo | Puente rubro→casilla **por nombre**, con caída a `Otros`: el catálogo y el talonario evolucionan por separado |

## `app/Services/` — acá viven las reglas

| Archivo | Ln | Qué hace | No obvio |
| --- | --- | --- | --- |
| `SolicitudCarnetService.php` | 1053 | **El caso de uso central.** Reglas A, B y C + todo el circuito | El archivo más importante del sistema. Ver el desglose abajo |
| `PagoTramiteService.php` | 223 | Pagos parciales, 1 a N | Los métodos vienen **de a pares**: uno recibe `UploadedFile`, el otro `...ConRuta`. No es duplicación — ver §2.4 de ARQUITECTURA |
| `ArchivoTramiteService.php` | 107 | Subir/descartar adjuntos alrededor de una transacción | `descartar()` **no propaga errores**: se llama desde un `catch` y taparía la excepción original |
| `ReciboTramiteService.php` | 194 | Emite el RECIBO OFICIAL | `emitir()` es **idempotente**: reimprimir no gasta otro número |
| `CorrelativoService.php` | 103 | Números correlativos por serie y año | `SELECT ... FOR UPDATE`. Estuvo **sin usar** hasta que llegaron los recibos |

### Desglose de `SolicitudCarnetService`

| Método | Qué hace | Ojo con |
| --- | --- | --- |
| `registrar()` | Alta completa, todo o nada | 4 pasos; el orden archivos/transacción es la parte importante |
| `tomarParaRevision()` | `pendiente → en_revision` | **Acá nace el recibo**, dentro de la misma transacción |
| `aprobar()` | `→ aprobado` + nace `carnet_rubro` | No aprueba sin cobrar. Único lugar donde nace una habilitación |
| `rechazar()` | `→ rechazado` | Motivo obligatorio. El carnet recién creado **no se borra** |
| `eliminar()` | Borra de verdad (sin `deleted_at`) | Orden **inverso** al de registrar. Borra el carnet si quedó vacío |
| `generar()` / `entregar()` | Escriben las fechas de impreso/entregado | No son estados |
| `actualizarAdjuntos()` | Reemplaza papeles ilegibles | Guarda las rutas viejas ANTES del update |
| `bloquear()` | Relee con la fila bloqueada | **Devuelve otra instancia.** Se escribe sobre la copia, se devuelve la original refrescada |
| `verificarTransicion()` | La guarda | Se llama **dos veces** por operación: fuera y dentro de la transacción |
| `traducir()` | SQLSTATE 23505 → castellano | Sin esto el operador ve el error crudo de la base |

## `app/Models/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `Beneficiario.php` | 319 | `nombreCompleto` **NO** va en `#[Appends]` (camelCase). `SQL_NOMBRE` entrecomilla por el camelCase. `carnetDeGestion()` usa `relationLoaded()` para no caer en N+1. `deudaTotal()` **sí cae en N+1** — el comentario dice lo contrario |
| `Carnet.php` | 405 | Sin columna `codigo`. `registro()` = id con ceros (público), `firma_validacion` = la llave (secreta). `estaVigente()` mira estado **y** fecha. `vencimientoDeGestion()` = 31/12 siempre. `puedeImprimirse()` exige un rubro habilitado, no solo que el carnet exista |
| `Tramite.php` | 310 | Cuelga del **carnet**. `montoPagado()` reusa `pagos_sum_monto` si el listado hizo `withSum`. Las 5 fechas van en `#[Fillable]` aunque ningún formulario las mande — `update()` las descartaría |
| `CarnetRubro.php` | 71 | Pivote **con modelo propio**, porque `attach()` no dispara eventos y `Auditable` no registraría nada |
| `Pago.php` | 68 | La columna es **`urlFile`**, el accesor es `comprobante_url`. No se anulan ni se borran |
| `Recibo.php` | 156 | Todo es **copia congelada**. `montoEnLetras()` usa `Number::spell(locale: 'es')` (requiere `intl`) |
| `Rubro.php` | 70 | `Auditable` pero **sin** `SoftDeletes`: no se borra, se inactiva |
| `Configuracion.php` | 63 | Cache `rememberForever`, invalidada en `saved`/`deleted` |
| `Correlativo.php` | 18 | Solo la tabla del contador |
| `Auditoria.php`, `Acceso.php`, `User.php` | 50/21/72 | Infraestructura |

## `app/Http/Controllers/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `StorageController.php` | 163 | **El único que escribe en disco.** Devuelve ruta (local) o URL completa (s3) |
| `Panel/BeneficiarioController.php` | 418 | **La plantilla del sistema**, comentada paso a paso. `buscar()` devuelve JSON, no Inertia |
| `Panel/TramiteController.php` | 645 | **No decide nada.** `resumir()` arma lo que comparten tabla y ficha |
| `Panel/CarnetController.php` | 243 | **Sin `create()` ni `store()`**: un carnet nace en el servicio. La dirección del QR ya no se arma acá: la da `Carnet::urlVerificacion()` |
| `Panel/CarnetImpresionController.php` | 263 | El PDF del plástico. **No marca impreso** —eso sigue siendo `PATCH /tramites/{tramite}/generar`— ni guarda nada en disco. CR80: 243×153 pt |
| `Panel/PagoController.php` | 123 | Libro de caja + alta. **Sin anulación** |
| `Panel/RubroController.php` | 97 | **Sin `destroy()`** |
| `Panel/ReciboController.php` | 199 | Solo dibuja el PDF. Media carta apaisada. Imágenes embebidas |
| `Panel/DashboardController.php` | 330 | Cada bloque envuelto en `fn()` para las visitas parciales. `listos_para_aprobar` **cae en N+1**. `actividadDiaria()` arma la serie de 14 días de los indicadores |
| `Publico/VerificacionController.php` | 232 | Cédula enmascarada. Sin ids internos. **Sin rubros suspendidos** |

## `app/Http/Requests/Panel/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `RegistrarSolicitudRequest.php` | 224 | `pagosIniciales()` arma la lista que espera el servicio |
| `GuardarBeneficiarioRequest.php` | 186 | Índice único parcial ⇒ la regla `unique` ignora los dados de baja |
| `GuardarUsuarioRequest.php` | 319 | **Escrito y comentado, pero sin ruta ni controlador** |
| `RegistrarPagoRequest.php` | 103 | |
| `GuardarRubroRequest.php` | 78 | |

## `app/Support/` y `app/Traits/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `Sql.php` | 70 | `ILIKE` vs `LIKE`, truncado a mes (`periodoMes`) y a día (`periodoDia`). Lo que cambia entre motores |
| `Archivos.php` | 142 | `url()` mira qué recibió antes de decidir. **`borrar()` no puede borrar** lo guardado como URL completa. `contenido()` devuelve los bytes, para embeber en un PDF |
| `CodigoQr.php` | 134 | **Escrito y sin usar**: la tarjeta ya no lleva QR. Cuando vuelva, la parte difícil está resuelta — el PNG de simple-qrcode exige imagick, que no está |
| `SituacionCarnet.php` | 149 | Lo comparten el autocompletado y el formulario. Es **para la pantalla**, no la regla |
| `Paginacion.php` | 62 | Lista blanca de tamaños: el número llega por la URL |
| `Auditable.php` | 83 | Bitácora automática. **No se entera de `attach()` ni de los DELETE en cascada** |

## `resources/views/documentos/`

| Archivo | Qué es | No obvio |
| --- | --- | --- |
| `recibo-oficial.blade.php` | Calco del talonario del SEDAG | Todo en `position: absolute` sobre una grilla de 592×376 pt. Ver [modulos/RECIBOS.md](modulos/RECIBOS.md) |
| `carnet-pescador.blade.php` | La credencial impresa | Calco de la cédula de papel, una carilla de 243×153 pt (CR80). **Es el espejo de `vista-previa-carnet.tsx`**: si se toca una, se toca la otra. Ver [modulos/CARNETS.md](modulos/CARNETS.md) |
| `partes/texto-perfilado.blade.php` | Un texto con contorno | Lo dibuja **cinco veces** —cuatro copias corridas más la cara— porque DomPDF no tiene `-webkit-text-stroke` ni `text-shadow`. Lo usan el título de la cédula y los rótulos. El color, el cuerpo y el corrimiento los pone quien la incluye |

## `routes/`

| Archivo | Qué expone |
| --- | --- |
| `web.php` | Solo la raíz + incluye a los otros tres. Laravel carga **este** |
| `panel.php` | `/panel/...` — con sesión y con `permiso:` en cada ruta |
| `publico.php` | `/verificar/{firma?}` — sin sesión, con `throttle` |
| `auth.php` | `/login`, `/logout` |

> **El orden importa:** `/beneficiarios/crear` y `/beneficiarios/buscar` van
> ANTES de `/beneficiarios/{beneficiario}`, o esas palabras se toman como id.

## `resources/js/` — frontend

| Carpeta | Qué hay | No obvio |
| --- | --- | --- |
| `pages/panel/` | Una pantalla = un archivo | Reciben los props de `Inertia::render()` |
| `pages/publico/` | `verificar.tsx` | |
| `components/ui/` | Genéricas | **Única excepción a «todo en español»** |
| `components/panel/` | Por módulo | `crear.tsx` de trámites: cuidado con los `key` de los botones. `tramites/vista-previa-carnet.tsx` es **el molde del carnet impreso**: si se toca, se toca también el Blade |
| `components/panel/carnets/` | `dialogo-imprimir-carnet.tsx` | La vista previa antes de imprimir. Muestra el PDF DE VERDAD en un `iframe`, no una maqueta |
| `components/panel/layout/` | Barra lateral, encabezado, menú | El ancho de la barra está escrito **dos veces** —`w-16`/`w-64` en la barra y `lg:pl-16`/`lg:pl-64` en el layout— y los dos se mueven juntos. `moduloActual()` de `navegacion.ts` es lo ÚNICO que decide qué módulo está abierto: lo usan el menú y las migas |
| `components/panel/dashboard/` | Los bloques del tablero | `widget-estadistica.tsx` pinta de color entero: las clases van **escritas enteras**, como en `badge.tsx`. `mini-grafico.tsx` NO usa recharts a propósito |
| `components/publico/` | Hoja oficial, ficha, buscador | |
| `hooks/use-permisos.ts` | `puede('x.y')` | **Comodidad, no seguridad** |
| `lib/utils.ts` | `bs()`, `fecha()`, `fechaHora()`, `cn()` | `aFechaLocal()` resuelve el bug de UTC-4. **Sin pruebas** |
| `types/` | La forma de lo que manda Laravel | Hay que actualizarlos al cambiar un controlador |

## `tests/` — VACÍO

Solo queda `TestCase.php`, la clase base. `tests/Feature/` se vació el
**14/09/2026** por pedido del responsable del proyecto.

Eran 143 pruebas y cubrían el backend entero. Lo que comprobaban, y que hoy **no
comprueba nada**:

| Archivo que había | Qué protegía |
| --- | --- |
| `SolicitudCarnetTest.php` | Un carnet por persona y gestión; que un rollback no deje archivos huérfanos |
| `PagoTramiteTest.php` | Que no se apruebe sin cobrar; que no se cargue dos veces la misma boleta |
| `FlujoEstadoTramiteTest.php` | La máquina de estados: los saltos que **no** valen |
| `ReciboTramiteTest.php` | Que reimprimir conserve el número; que el recibo quede congelado |
| `SubidaArchivosTest.php` | Tope de 3 MB + **leía el código fuente** buscando quién se saltaba `StorageController` |
| `BeneficiarioTest.php` | CRUD, búsqueda, índice único parcial |
| `SituacionCarnetTest.php` | Lo que ve el formulario antes de cargar |
| `PantallasPanelTest.php` | Que cada pantalla responda 200 |
| `AccesoTest.php` | Login, logout, bitácora, verificación pública |

> Ese `SubidaArchivosTest` era el más difícil de reemplazar mirando la pantalla:
> no probaba que el código de hoy funcione, sino que **mañana nadie tome el
> atajo**. Esa clase de regla no se ve ejecutando el sistema.

`phpunit.xml`, `TestCase.php` y las dependencias de PHPUnit se dejaron en su
lugar: no molestan y permiten volver a escribir una prueba creando un solo
archivo. Ver el punto 10 de [PENDIENTES.md](PENDIENTES.md).

## `database/`

| Carpeta | Qué hay | No obvio |
| --- | --- | --- |
| `migrations/` | 16 archivos, en orden cronológico | Las del dominio (`2026_09_10_*`) están **muy** comentadas: son el mejor lugar para entender el esquema |
| `seeders/RubroSeeder` | Pescador (80 Bs), Comercializador (120 Bs) | Los dos rubros **son** dos casillas del recibo de papel |
| `seeders/ConfiguracionSeeder` | Datos de la institución | |
| `seeders/RolPermisoSeeder` | Lee los permisos de `RolSistema` | |
| `seeders/DemoSeeder` | Datos de prueba | **No pasa por el servicio** — es una copia que puede quedar vieja |
| `factories/` | `BeneficiarioFactory` | |

## `public/image/`

| Archivo | Para qué |
| --- | --- |
| `icon.png` | Escudo del GAD Beni, para el panel (1,7 MB) |
| `sedag.png` | Sello del SEDAG, para el panel (1 MB) |
| `recibo-escudo.png` | El mismo escudo a 220 px, para el PDF (36 KB) |
| `recibo-sello.png` | El sello a 360 px **y ya atenuado**, para el PDF (23 KB) |
| `carnet-fondo.png` | El verde del carnet **con el sello ya atenuado adentro**, 674×425 px. Degradado `#719327` → `#518411`. **Su gemelo es el `linear-gradient` de `vista-previa-carnet.tsx`**: el verde se toca en los dos o se separan |
| `carnet-escudo.png` | El escudo del encabezado, recortado de `recibo-escudo.png` (27 KB) |

> Las copias `recibo-*` existen porque embeber los originales hacía un PDF de
> 5,4 MB por recibo.
>
> `carnet-fondo.png` trae el degradado Y el sello horneados en el archivo porque
> DomPDF no entiende `linear-gradient` y su `opacity` es poco confiable. Es la
> misma decisión que el sello del recibo, llevada al fondo entero. El degradado
> es el MISMO que declara `vista-previa-carnet.tsx`, resuelto con la fórmula de
> CSS para que el verde impreso sea el de la pantalla.

---

## Lo que NO hay que buscar porque no existe

- API REST, `fetch()`, `axios` — todo pasa por Inertia
- Columnas `monto_pagado`, `saldo`, `edad`, `codigo` del carnet, `created_by`
- Tipos ENUM nativos de PostgreSQL
- Módulos de Reportes y Configuración
- Comando de vencimiento de carnets
- PDF del carnet (el del **recibo** sí existe)
- Pruebas de JavaScript
