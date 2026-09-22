> # ⚠️ DESACTUALIZADO desde el 18/09/2026
>
> El núcleo de datos se rehízo desde cero: ya no existen `rubros`,
> `tramites`, `faenas`, `guias` ni `guia_detalles`, y `beneficiarios`,
> `carnets` y `pagos` cambiaron de columnas. Lo de abajo describe el modelo
> ANTERIOR: sirve para entender el código del panel, que todavía está escrito
> contra él, NO para entender el esquema.
>
> El esquema vigente está en las migraciones `database/migrations/2026_09_18_*`
> y explicado en [docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md).

---

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
| `EstadoCarnet.php` | 137 | `vigente \| suspendido \| vencido \| anulado` | Absorbió al enum `EstadoHabilitacion`: con un carnet por rubro, suspender la actividad es suspender el carnet. `vencido` lo escribiría un comando programado que **no existe todavía** |
| `EstadoRubro.php` | 48 | `activo \| inactivo` | Un rubro nunca se borra |
| `TipoTramite.php` | 92 | `emision_inicial \| actualizacion` | **Lo decide el sistema, no el operador**. «Adición de rubro» ya no existe: pedir otro rubro emite otro carnet |
| `RolSistema.php` | 124 | Roles y **todos** sus permisos | Un solo rol hoy (`administrador`). Los permisos ya están en cuatro bloques para poder agregar el segundo en una línea |
| `EstadoValidacionPago.php` | 113 | `pendiente \| validado \| observado` | NO es el estado del pago sino el de su CONTROL. Nace PENDIENTE: si arrancara validado, todo estaría aprobado por omisión. Tres estados y no dos — «sin mirar» y «no cuadra» son cosas distintas |
| `EstadoPermiso.php` | 106 | `emitido \| anulado` | **Lo comparten faenas y guías**, porque su ciclo de vida es idéntico. Solo dos valores: no hay circuito — el permiso se llena, se cobra y se entrega en el acto. Se **anula**, nunca se borra: el número ya se gastó del talonario |
| `TipoTransporte.php` | 79 | `fluvial \| aerea \| terrestre` | Dice **quién controla y dónde**: la naval en el río, un retén en la carretera. Por eso la columna es obligatoria mientras el resto del transporte es opcional. `rotuloIdentificacion()` cambia «Placa» por «Matrícula» |
| `CondicionProducto.php` | 70 | `fresco \| congelado \| seco \| salado` | Lista **cerrada**, al revés de la especie vecina: son cuatro, las usa el formulario de papel y no aparecen nuevas |
| `FormaPago.php` | 52 | `deposito \| efectivo` | Son las dos casillas del recibo de papel |
| `ConceptoRecibo.php` | 110 | Las seis casillas de DESCRIPCIÓN del recibo | Puente rubro→casilla **por nombre**, con caída a `Otros`: el catálogo y el talonario evolucionan por separado |

## `app/Services/` — acá viven las reglas

| Archivo | Ln | Qué hace | No obvio |
| --- | --- | --- | --- |
| `SolicitudCarnetService.php` | 1053 | **El caso de uso central.** Reglas A, B y C + todo el circuito | El archivo más importante del sistema. Ver el desglose abajo |
| `PagoTramiteService.php` | 223 | Pagos parciales, 1 a N | Los métodos vienen **de a pares**: uno recibe `UploadedFile`, el otro `...ConRuta`. No es duplicación — ver §2.4 de ARQUITECTURA |
| `ValidacionPagoService.php` | 127 | Validar u observar un depósito | Aparte de `PagoTramiteService` porque son actos de PERSONAS distintas: uno es de ventanilla, este de supervisión. Y vale para los tres, porque `pagos` es polimórfica |
| `FaenaService.php` | 155 | Emitir y anular faenas | Tres comprobaciones y el ORDEN importa: el rubro primero, porque elegir el carnet equivocado es el error más probable. El número se comprueba ANTES del INSERT, porque en PostgreSQL un INSERT fallido aborta la transacción |
| `GuiaService.php` | 244 | Emitir y anular guías, y reemplazar su carga | Cabecera y detalle se escriben en la MISMA transacción. `reemplazarDetalle()` borra todo y reinserta a propósito: la grilla manda la lista completa, no un diff |
| `ArchivoTramiteService.php` | 107 | Subir/descartar adjuntos alrededor de una transacción | `descartar()` **no propaga errores**: se llama desde un `catch` y taparía la excepción original |
| `ReciboTramiteService.php` | 200 | **Arma** el RECIBO OFICIAL, no lo guarda | `armar()` lo reconstruye desde el trámite. No hay tabla `recibos` |
| `CorrelativoService.php` | 78 | Dos usos distintos: `siguienteContinuo()` para lo que se IMPRIME —recibos y permisos de faena, seis dígitos y sin reinicio, guardados bajo el **año 0**— y `siguienteNumero()` con gestión para lo que sí cuenta por año, como el registro del carnet |

### Desglose de `SolicitudCarnetService`

| Método | Qué hace | Ojo con |
| --- | --- | --- |
| `registrar()` | Alta completa, todo o nada | 4 pasos; el orden archivos/transacción es la parte importante |
| `tomarParaRevision()` | `pendiente → en_revision` | **Acá nace el recibo**, dentro de la misma transacción |
| `aprobar()` | `→ aprobado` + consolida el carnet | No aprueba sin cobrar. Copia cupo y asociación al carnet; lo que viene en blanco NO pisa lo que ya había |
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
| `FaenaController.php` | 400 | Listado, alta, ficha, cobro y circuito de revisión, más **corregir y eliminar el BORRADOR** (21/09/2026): solo en PENDIENTE y sin un peso cargado, porque el número lo pone el sistema y el papel sale recién al aprobar. El número NO viene del formulario —lo genera el correlativo continuo— y el alta guarda además los siete renglones del talonario. `edit()` manda el saldo del cupo **con los kilos de esta faena sumados de vuelta**, o el formulario diría que no entra lo que ya entró |
| `GuiaController.php` | 358 | Ídem, más `actualizarDetalle()` — la única corrección que el módulo permite. `resumir()` usa el `withSum` del listado para no calcular los kilos por fila |
| `Beneficiario.php` | 319 | `nombreCompleto` **NO** va en `#[Appends]` (camelCase). `SQL_NOMBRE` entrecomilla por el camelCase. `carnetDeGestion()` usa `relationLoaded()` para no caer en N+1. `deudaTotal()` **sí cae en N+1** — el comentario dice lo contrario |
| `Carnet.php` | 405 | Sin columna `codigo`. `registro()` = id con ceros (público), `firma_validacion` = la llave (secreta). `estaVigente()` mira estado **y** fecha. `vencimientoDeGestion()` = 31/12 siempre. `puedeImprimirse()` exige un rubro habilitado, no solo que el carnet exista |
| `Tramite.php` | 310 | Cuelga del **carnet**. `montoPagado()` reusa `pagos_sum_monto` si el listado hizo `withSum`. Las 5 fechas van en `#[Fillable]` aunque ningún formulario las mande — `update()` las descartaría |
| `CarnetRubro.php` | 71 | Pivote **con modelo propio**, porque `attach()` no dispara eventos y `Auditable` no registraría nada |
| `Pago.php` | 243 | **`pagable()` es un `morphTo`**: el depósito cubre un trámite, una faena o una guía. Sin morphMap — en la columna va el nombre completo de la clase. La columna del archivo es **`urlFile`**, el accesor es `comprobante_url`. No se anulan ni se borran |
| `Faena.php` | 288 | Cuelga del **carnet**, no del beneficiario. `$attributes` declara `estado` por defecto **en memoria**: el default de la base no llega al objeto que devuelve `create()`. `diasAutorizados()` suma uno — salir y desembarcar el mismo día es un día, no cero. `estaVigente()` mira TRES cosas, y la que se olvida es el carnet |
| `Guia.php` | 279 | Cabecera; la carga está en `GuiaDetalle`. **`montoRequerido()` no es una columna**: sale de sumar el detalle, porque la guía se cobra sobre lo que traslada y no por tarifa fija. `excedeCapacidad()` devuelve `null` cuando no se sabe la capacidad |
| `GuiaDetalle.php` | 133 | `$table` declarado a mano. **`importe()` prefiere `imponible` sobre cantidad × precio**, y el orden no es intercambiable: `imponible` es lo que dice el papel firmado |
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
| `Panel/PermisoFaenaImpresionController.php` | 130 | El «Permiso por Faena» en PDF. Carta vertical. Sale recién con la faena **aprobada**. El monto es la copia congelada de la fila, no la tarifa de hoy; la fecha del pie sale de `fecha_emision` para que una reimpresión diga lo mismo |
| `Panel/AutorizacionPescaController.php` | 187 | La autorización de pesca en PDF. Carta vertical. Sale recién con el cupo **aprobado**; la tabla de tamaños mínimos y las reglas de redes van como constantes —son texto del reglamento, no de la base— |
| `Panel/DashboardController.php` | 330 | Cada bloque envuelto en `fn()` para las visitas parciales. `listos_para_aprobar` **cae en N+1**. `actividadDiaria()` arma la serie de 14 días de los indicadores |
| `Publico/VerificacionController.php` | 232 | Cédula enmascarada. Sin ids internos. **Sin rubros suspendidos** |
| `Publico/InicioController.php` | 52 | La portada institucional. **No consulta el dominio**: todo sale de `configuraciones`, con valor por defecto para que se dibuje en una base sin seeder. Manda la prop como `portada` y no `institucion` porque esa clave ya la ocupa una prop compartida, y una de página con el mismo nombre la tapa sin avisar |

## `app/Http/Requests/Panel/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `RegistrarSolicitudRequest.php` | 224 | `pagosIniciales()` arma la lista que espera el servicio |
| `GuardarBeneficiarioRequest.php` | 186 | Índice único parcial ⇒ la regla `unique` ignora los dados de baja |
| `GuardarUsuarioRequest.php` | 319 | **Escrito y comentado, pero sin ruta ni controlador** |
| `RegistrarPagoRequest.php` | 103 | |
| `CorregirPagoRequest.php` | 118 | Corregir una boleta ya cargada. La boleta es OPCIONAL y el `unique` del número **ignora la propia fila**, o guardar sin tocar el número se acusaría a sí mismo |
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
| `permiso-faena.blade.php` | Calco del talonario «PERMISO POR FAENA» | Texto que FLUYE dentro de un MARCO redondeado —`border-radius`, que la 3.1.6 de DomPDF dibuja bien—. El «Kg.» lleva la línea de ancho fijo, o se sale del marco. Carta vertical, 612×792 pt |
| `autorizacion-pesca.blade.php` | Calco de la autorización de pesca | Texto que FLUYE, al revés que el carnet y el recibo: el papel son párrafos, no coordenadas fijas. Carta vertical, 612×792 pt |
| `recibo-oficial.blade.php` | Calco del talonario del SEDAG | Todo en `position: absolute` sobre una grilla de 592×376 pt. Ver [modulos/RECIBOS.md](modulos/RECIBOS.md) |
| `carnet-pescador.blade.php` | La credencial impresa | Calco de la cédula de papel, una carilla de 243×153 pt (CR80). **Es el espejo de `vista-previa-carnet.tsx`**: si se toca una, se toca la otra. Ver [modulos/CARNETS.md](modulos/CARNETS.md) |
| `partes/texto-perfilado.blade.php` | Un texto con contorno | Lo dibuja **cinco veces** —cuatro copias corridas más la cara— porque DomPDF no tiene `-webkit-text-stroke` ni `text-shadow`. Lo usan el título de la cédula y los rótulos. El color, el cuerpo y el corrimiento los pone quien la incluye |

## `routes/`

| Archivo | Qué expone |
| --- | --- |
| `web.php` | **Solo incluye** a los otros tres. Laravel carga este. La raíz se mudó a `publico.php` el 22/09/2026 |
| `panel.php` | `/panel/...` — con sesión y con `permiso:` en cada ruta |
| `publico.php` | `/` (portada) y `/verificar/{codigo?}` — sin sesión, con `throttle` |
| `auth.php` | `/login`, `/logout` |

> **El orden importa:** `/beneficiarios/crear` y `/beneficiarios/buscar` van
> ANTES de `/beneficiarios/{beneficiario}`, o esas palabras se toman como id.

## `resources/js/` — frontend

| Carpeta | Qué hay | No obvio |
| --- | --- | --- |
| `pages/panel/` | Una pantalla = un archivo | Reciben los props de `Inertia::render()` |
| `pages/publico/` | `inicio.tsx` (portada) · `verificar.tsx` (acta) | Dos pantallas con marcos distintos: la portada es ancha y azul, el acta es angosta, verde y **se imprime** |
| `components/ui/` | Genéricas | **Única excepción a «todo en español»** |
| `ui/button.tsx` | La escala de botones | Las variantes `ver`, `editar` y `eliminar` son el estilo ÚNICO de esas tres acciones en todo el sistema —celeste mira, ámbar cambia, rojo saca—. `eliminar` cubre también dar de baja, anular, revocar y rechazar. Una pantalla nueva las usa; **no** escribe las clases de color a mano |
| `components/panel/` | Por módulo | `crear.tsx` de trámites: cuidado con los `key` de los botones. `tramites/vista-previa-carnet.tsx` es **el molde del carnet impreso**: si se toca, se toca también el Blade |
| `components/panel/tramites/depositos.tsx` | Resumen, lista y los dos formularios | Un solo archivo para las DOS pantallas, que son dos MOMENTOS y no dos lugares para lo mismo: «Editar trámite» es el borrador y la FICHA es el expediente presentado —lo único que cambia es que ahí se CONTROLA—. `conCorreccion` va en las dos y enciende corregir Y quitar: un depósito observado tiene que poder arreglarse en la ficha, porque observar solo pasa en revisión. `FilaDeposito` es componente propio y no un `<li>` dentro del `map` porque tiene estado, y un hook no va en un `map`. «Quitar» pide además el permiso `pagos.eliminar`, que es de administración |
| `components/panel/carnets/` | `dialogo-imprimir-carnet.tsx` | La vista previa antes de imprimir. Muestra el PDF DE VERDAD en un `iframe`, no una maqueta |
| `components/panel/layout/` | Barra lateral, encabezado, menú | El ancho de la barra está escrito **dos veces** —`w-16`/`w-64` en la barra y `lg:pl-16`/`lg:pl-64` en el layout— y los dos se mueven juntos. `moduloActual()` de `navegacion.ts` es lo ÚNICO que decide qué módulo está abierto: lo usan el menú y las migas |
| `components/panel/dashboard/` | Los bloques del tablero | `widget-estadistica.tsx` pinta de color entero: las clases van **escritas enteras**, como en `badge.tsx`. `mini-grafico.tsx` NO usa recharts a propósito |
| `components/publico/` | Hoja oficial, ficha, buscador | `buscador-codigo.tsx` vive en TRES sitios —el acta, su fondo verde y la portada— y por eso no lleva margen propio |
| `components/publico/institucional/` | Las seis secciones de la portada, su cabecera y su pie | Los textos de servicios, pasos y preguntas salen de `docs/REGLAS-NEGOCIO.md`: si la regla cambia, cambian con ella. `seccion.tsx` lleva `scroll-mt` porque la cabecera es sticky y sin eso el ancla deja el título tapado |
| `hooks/use-permisos.ts` | `puede('x.y')` | **Comodidad, no seguridad** |
| `ui/confirmar-accion.tsx` · `ui/confirmar-con-motivo.tsx` | Las ventanas de confirmación | La prop `confirmacion` agrega una CASILLA que hay que marcar, y apaga el botón hasta entonces. Se usa solo en lo irreversible y en lo que es una declaración: marcada sin leer no protege nada. Se limpia al cerrar |
| `lib/utils.ts` | `bs()`, `fecha()`, `fechaInput()`, `hora()`, `fechaHora()`, `hace()`, `cn()` | `aFechaLocal()` resuelve el bug de UTC-4. `fechaInput()` es su inversa —AAAA-MM-DD para un `<input type="date">`— y **no es `slice(0,10)`**: cortar un instante UTC da el día siguiente en Bolivia. `hace()` usa DOS `RelativeTimeFormat`: `auto` hasta días —para que salga «ayer»— y `always` de meses para arriba, o 40 días dirían «el mes pasado». **Sin pruebas** |
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
| `migrations/` | 17 archivos, en orden cronológico | Las del dominio (`2026_09_10_*`) están **muy** comentadas: son el mejor lugar para entender el esquema |
| `seeders/RubroSeeder` | Pescador (80 Bs), Comercializador (120 Bs) | Los dos rubros **son** dos casillas del recibo de papel. Siembra además `emite_faenas` / `emite_guias` |
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
