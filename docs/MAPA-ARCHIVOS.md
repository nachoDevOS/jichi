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
| `CodigoService.php` | 93 | Genera el código de 16, reintenta diez veces y falla ruidoso. `asignar()` es **idempotente**: un reenvío no puede gastar un código nuevo, porque el anterior ya salió impreso |
| `SolicitudCarnetService.php` | 1053 | **El caso de uso central.** Reglas A, B y C + todo el circuito | El archivo más importante del sistema. Ver el desglose abajo |
| `PagoTramiteService.php` | 223 | Pagos parciales, 1 a N | Los métodos vienen **de a pares**: uno recibe `UploadedFile`, el otro `...ConRuta`. No es duplicación — ver §2.4 de ARQUITECTURA |
| `ValidacionPagoService.php` | 127 | Validar u observar un depósito | Aparte de `PagoTramiteService` porque son actos de PERSONAS distintas: uno es de ventanilla, este de supervisión. Y vale para los tres, porque `pagos` es polimórfica |
| `FaenaService.php` | 155 | Emitir y anular faenas | Tres comprobaciones y el ORDEN importa: el rubro primero, porque elegir el carnet equivocado es el error más probable. El número se comprueba ANTES del INSERT, porque en PostgreSQL un INSERT fallido aborta la transacción |
| `EmitirGuiaService.php` | 330 | Emitir, corregir, eliminar y anular guías | Cabecera y detalle en la MISMA transacción. El detalle se **reemplaza entero** al corregir: casarlo fila por fila sin un id estable del papel inventa una identidad que el talonario no tiene. `eliminar()` baja el detalle A MANO —la FK es CASCADE, y eso no se dispara con una baja lógica—. El arancel se copia DESPUÉS del `create()`: el factor de piscicultura lo calcula el modelo leyendo su propia columna |
| `RevisarGuiaService.php` | 118 | El circuito de la guía: enviar, aprobar, rechazar | Espejo de `RevisarFaenaService`. **Las dos fechas de vigencia las escribe `aprobar()`**: los 5 días corren desde la firma, no desde que se cargó el borrador. Aprobar exige las tres cosas: estado, arancel cubierto y ninguna boleta sin validar |
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
| `FaenaController.php` | 400 | Listado, alta, ficha, cobro y circuito de revisión, más **corregir y eliminar el BORRADOR** (21/09/2026): solo en PENDIENTE y sin un peso cargado, porque el número lo pone el sistema y el papel sale recién al aprobar. El número NO viene del formulario —lo genera el correlativo continuo— y el alta guarda además los siete renglones del talonario. `edit()` manda el saldo del cupo **con los kilos de esta faena sumados de vuelta**, o el formulario diría que no entra lo que ya entró; y `libre_kg`, lo libre más lo que ella misma reservaba (27/09/2026) |
| `GuiaController.php` | 745 | Listado, alta, ficha, corrección del borrador, cobro y circuito de revisión, más anular. `resumir()` usa el `withSum` del listado para no calcular el saldo por fila, y `reciboDe()` lo resuelve desde los pagos ya precargados. El buscador OMITE la condición del número cuando el término no trae dígitos: un `like '%%'` traería la tabla entera |
| `ProductoHidrobiologicoController.php` | 80 | Catálogo de productos hidrobiológicos: listado, alta y corrección, sin baja. `catalogosDelFormulario()` de `GuiaController` le pasa a la guía los activos más los que la guía ya usa |
| `Beneficiario.php` | 319 | `nombreCompleto` **NO** va en `#[Appends]` (camelCase). `SQL_NOMBRE` entrecomilla por el camelCase. `carnetDeGestion()` usa `relationLoaded()` para no caer en N+1. `deudaTotal()` **sí cae en N+1** — el comentario dice lo contrario. Suma faenas y guías con el mismo corte que Caja (`admitePagos()`); antes olvidaba las faenas |
| `Carnet.php` | 405 | Sin columna `codigo`. `registro()` = id con ceros (público), `firma_validacion` = la llave (secreta). `estaVigente()` mira estado **y** fecha. `vencimientoDeGestion()` = 31/12 siempre. `puedeImprimirse()` exige un rubro habilitado, no solo que el carnet exista |
| `Tramite.php` | 310 | Cuelga del **carnet**. `montoPagado()` reusa `pagos_sum_monto` si el listado hizo `withSum`. Las 5 fechas van en `#[Fillable]` aunque ningún formulario las mande — `update()` las descartaría |
| `CarnetRubro.php` | 71 | Pivote **con modelo propio**, porque `attach()` no dispara eventos y `Auditable` no registraría nada |
| `Pago.php` | 243 | **`pagable()` es un `morphTo`**: el depósito cubre un trámite, una faena o una guía. Sin morphMap — en la columna va el nombre completo de la clase. La columna del archivo es **`urlFile`**, el accesor es `comprobante_url`. No se anulan ni se borran |
| `Faena.php` | 288 | Cuelga del **carnet**, no del beneficiario. `$attributes` declara `estado` por defecto **en memoria**: el default de la base no llega al objeto que devuelve `create()`. `diasAutorizados()` suma uno — salir y desembarcar el mismo día es un día, no cero. `estaVigente()` mira TRES cosas, y la que se olvida es el carnet |
| `GuiaMovimiento.php` | 350 | Cuelga del **carnet**, no del beneficiario; la carga está en `GuiaDetalle`. **`montoACobrar()` lee la COLUMNA**, no `config()`: el arancel se congela al emitir. `factorArancel()` es el único lugar donde vive el 50% de piscicultura. `$attributes` declara `estado`, `monto` y `peso_total_kg` **en memoria**: el default de la base no llega al objeto que devuelve `create()`. `beneficiarioId` es un accesor —no una columna— para que `CobrarService` le hable igual que al carnet |
| `GuiaDetalle.php` | 70 | Un renglón del cuadro D. `condicion` es UN enum de diez casos y no dos columnas: partirlo en estado × presentación dejaría combinaciones que el talonario no tiene. Desde el 27/09/2026 apunta a `producto_id` y guarda **copias** de nombre y precio: el papel no cambia si el catálogo cambia. `precio_kg` es lo pagado en origen, no el arancel |
| `ProductoHidrobiologico.php` | 45 | El catálogo del cuadro D: nombre, tasa por kilo (mínimo 0,20) y estado; la guía cobra la suma de su cuadro D. `vigentes()` es lo que se elige en una guía nueva. Sin baja: uno usado se pone inactivo |
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
| `Panel/GuiaImpresionController.php` | 190 | La «Guía Única de Transporte» en PDF. Carta vertical. Sale recién con la guía **aprobada**. **Rellena el cuadro D hasta cinco renglones** aunque la guía traiga menos: la hoja impresa tiene que medir siempre lo mismo que la preimpresa del archivo. Un cero entra como celda VACÍA, no como «0,00» |
| `Panel/AutorizacionPescaController.php` | 187 | La autorización de pesca en PDF. Carta vertical. Sale recién con el cupo **aprobado**; la tabla de tamaños mínimos y las reglas de redes van como constantes —son texto del reglamento, no de la base— |
| `Panel/DashboardController.php` | 223 | Trabajo pendiente (borradores y por firmar, con URL filtradas), cuatro números, recaudación de 12 meses, avisos y últimos carnets. Cada bloque en `fn()` para las visitas parciales |
| `Publico/VerificacionController.php` | 265 | Atiende los CINCO documentos, no solo el carnet: una consulta a `codigos` y `morphTo`. Devuelve **renglones ya resueltos**, así que sumar un tipo no toca React. Cédula enmascarada, sin ids internos |
| `Panel/CuentaPortalController.php` | 80 | Dar acceso al portal, resetear y desactivar, desde la ficha. La clave temporal vuelve por flash y se ve UNA vez |
| `Portal/*` | — | El portal `/mi-cuenta`: `AccesoController`, `InicioController`, `PapelesController`, `PagosController` y `PerfilController`. **Ninguno recibe un id**: todo sale de `$request->user()->beneficiario`. Ver `docs/modulos/PORTAL.md` |
| `Publico/InicioController.php` | 52 | La portada institucional. **No consulta el dominio**: todo sale de `configuraciones`, con valor por defecto para que se dibuje en una base sin seeder. Manda la prop como `portada` y no `institucion` porque esa clave ya la ocupa una prop compartida, y una de página con el mismo nombre la tapa sin avisar |

## `app/Http/Requests/Panel/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `RegistrarSolicitudRequest.php` | 224 | `pagosIniciales()` arma la lista que espera el servicio |
| `GuardarBeneficiarioRequest.php` | 186 | Índice único parcial ⇒ la regla `unique` ignora los dados de baja |
| `RegistrarPagoRequest.php` | 103 | |
| `CorregirPagoRequest.php` | 118 | Corregir una boleta ya cargada. La boleta es OPCIONAL y el `unique` del número **ignora la propia fila**, o guardar sin tocar el número se acusaría a sí mismo |
| `GuardarRubroRequest.php` | 78 | |

## `app/AuthIbare/` — login con Ibare, módulo cerrado

Todo lo del login con Ibare vive en esta carpeta. Fuera de ella solo quedan la config
(`jichi.ibare`, porque `env()` va en `config/`), la columna `users.mamore_id`, la
restricción del login por correo en `LoginRequest` y el botón de `pages/auth/login.tsx`.

| Archivo | Qué hace |
| --- | --- |
| `AuthIbareServiceProvider.php` | Carga `rutas.php` con `web` + `guest` y registra el comando. Sacarlo de `bootstrap/providers.php` desconecta el módulo |
| `rutas.php` | `/auth/ibare` y `/auth/ibare/callback` (404 con `IBARE_ACTIVO=false`) |
| `IbareController.php` | Redirige a Ibare y recibe la vuelta; registra en `accesos` |
| `IbareService.php` | URL con PKCE, canje del código, validación del JWT contra el JWKS. Devuelve un `User` o lanza `IbareException` |
| `IbareException.php` | Los mensajes que ve el funcionario |
| `VincularIbareCommand.php` | `jichi:vincular-ibare` — carga `users.mamore_id` mientras no haya módulo de Usuarios |

Ver [modulos/IBARE.md](modulos/IBARE.md).

## `app/Sireb/` — precios de Recaudaciones (SIREB)

| Archivo | Qué hace |
| --- | --- |
| `SirebService.php` | Pide a Ibare el token de máquina (`client_credentials`), baja el catálogo del SEDAG y lo guarda en caché. `precioDe()` falla si el código no existe o no tiene UNA tarifa general; `catalogoSiResponde()` devuelve null en vez de fallar, para las pantallas que solo muestran |
| `SirebException.php` | Los mensajes que ve el funcionario |

Hoy lo usa solo la autorización (`OtorgarCupoService`, la escala y los formularios
de otorgar y corregir). Ver [modulos/SIREB.md](modulos/SIREB.md).

## `app/Support/` y `app/Traits/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `Sql.php` | 53 | `ILIKE` vs `LIKE` y truncado a mes (`periodoMes`). Lo que cambia entre motores |
| `Archivos.php` | 142 | `url()` mira qué recibió antes de decidir. **`borrar()` no puede borrar** lo guardado como URL completa. `contenido()` devuelve los bytes, para embeber en un PDF |
| `QrVerificacion.php` | 57 | El bloque de verificación de los CUATRO PDF: QR + código + URL. **Fuerza `APP_URL`**, porque `route()` absoluta usa el host de la petición y el QR queda impreso |
| `CodigoQr.php` | 134 | El QR en sí. BaconQrCode + `gd`, porque el PNG de simple-qrcode exige `imagick` y acá no está |
| `Codificable.php` | 66 | El código de 16 de los CINCO documentos que se entregan. `$doc->codigo_legible` para mostrar, `$doc->asignarCodigo()` para emitir. **Toda consulta que lo muestre necesita `with('codigo')`** o hace N+1 en silencio |
| `SituacionCarnet.php` | 149 | Lo comparten el autocompletado y el formulario. Es **para la pantalla**, no la regla |
| `Paginacion.php` | 62 | Lista blanca de tamaños: el número llega por la URL |
| `ExpedienteBeneficiario.php` | 85 | Las consultas del expediente de una persona, con sus `with()`. Las comparten la ficha del panel y el portal: arreglar un N+1 acá lo arregla en los dos |
| `ResumenPortal.php` | 130 | Lo que el portal muestra de cada documento: sin ids ni banderas de funcionario. `clase` dice qué documento es |
| `Auditable.php` | 83 | Bitácora automática. **No se entera de `attach()` ni de los DELETE en cascada** |

## `resources/views/documentos/`

| Archivo | Qué es | No obvio |
| --- | --- | --- |
| `permiso-faena.blade.php` | Calco del talonario «PERMISO POR FAENA» | Texto que FLUYE dentro de un MARCO redondeado —`border-radius`, que la 3.1.6 de DomPDF dibuja bien—. El «Kg.» lleva la línea de ancho fijo, o se sale del marco. Carta vertical, 612×792 pt |
| `guia-transporte.blade.php` | Calco del talonario «Guía Única de Transporte» | Al revés que la faena, NO es texto que fluye: es una grilla de cuadros con anchos declarados. **El relleno de cada celda suma al ancho y acá se paga por columna** — el cuadro D tiene quince, así que sus 2 pt de cada lado son 60 que hay que descontar de los 544 de la hoja. Los rótulos rotados del papel se apilan **una letra por renglón**: DomPDF no tiene `transform` ni `writing-mode`. El sello de agua va ABAJO, detrás de observaciones y las firmas, no detrás del cuadro de productos |
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
| `auth.php` | `/login`, `/logout`. Las rutas de Ibare NO están acá: ver `app/AuthIbare/rutas.php` |
| `portal.php` | `/mi-cuenta/...` — el portal del beneficiario, con `auth` + `beneficiario`. Ninguna ruta recibe un id |

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
| `components/panel/beneficiarios/` | Ficha en pestañas: `pestana-pescador.tsx`, `pestana-comercializador.tsx`, `pestana-pagos.tsx` y `partes-ficha.tsx` | La pestaña va a la URL (`?pestana=`). Pescador se agrupa por **período** = autorización → cédulas (`aprovechamiento_id`) → faenas (`carnet_id`), en `armarPeriodos()`; Comercializador, por cédula → guías. Sin eso, al renovar la autorización se mezclaban las faenas de dos años. Los botones de emitir salen de las banderas del servidor —`puede_emitir`—, no de reglas propias. `CredencialMini` **no** es la vista previa del plástico: esa es `vista-previa-carnet.tsx` |
| `components/panel/carnets/` | `dialogo-imprimir-carnet.tsx` | La vista previa antes de imprimir. Muestra el PDF DE VERDAD en un `iframe`, no una maqueta |
| `components/panel/layout/` | Barra lateral, encabezado, menú | El ancho de la barra está escrito **dos veces** —`w-16`/`w-64` en la barra y `lg:pl-16`/`lg:pl-64` en el layout— y los dos se mueven juntos. `moduloActual()` de `navegacion.ts` es lo ÚNICO que decide qué módulo está abierto: lo usan el menú y las migas |
| `components/panel/dashboard/` | Gráfico de recaudación, avisos y últimos carnets | El gráfico se carga con `lazy()`. Los avisos solo listan lo que tiene algo, y enlazan al listado filtrado |
| `components/publico/` | Hoja oficial, ficha, buscador | `buscador-codigo.tsx` vive en TRES sitios —el acta, su fondo verde y la portada— y por eso no lleva margen propio |
| `pages/portal/`, `components/portal/`, `layouts/layout-portal.tsx` | El portal del beneficiario | Claro siempre, como la portada: `piezas.tsx` tiene sus propios chips e inputs porque `Badge` e `Input` cambian en modo oscuro. `fila-papel.tsx` es la fila de un papel aprobado, en el inicio y en «Mis papeles» |
| `components/publico/institucional/` | Las seis secciones de la portada, su cabecera y su pie | Los textos de servicios, pasos y preguntas salen de `docs/REGLAS-NEGOCIO.md`: si la regla cambia, cambian con ella. `seccion.tsx` lleva `scroll-mt` porque la cabecera es sticky y sin eso el ancla deja el título tapado |
| `hooks/use-permisos.ts` | `puede('x.y')` | **Comodidad, no seguridad** |
| `ui/confirmar-accion.tsx` · `ui/confirmar-con-motivo.tsx` | Las ventanas de confirmación | La prop `confirmacion` agrega una CASILLA que hay que marcar, y apaga el botón hasta entonces. Se usa solo en lo irreversible y en lo que es una declaración: marcada sin leer no protege nada. Se limpia al cerrar |
| `lib/utils.ts` | `bs()`, `fecha()`, `fechaInput()`, `hora()`, `fechaHora()`, `hace()`, `cn()` | `aFechaLocal()` resuelve el bug de UTC-4. `fechaInput()` es su inversa —AAAA-MM-DD para un `<input type="date">`— y **no es `slice(0,10)`**: cortar un instante UTC da el día siguiente en Bolivia. `hace()` usa DOS `RelativeTimeFormat`: `auto` hasta días —para que salga «ayer»— y `always` de meses para arriba, o 40 días dirían «el mes pasado». **Sin pruebas** |
| `lib/rueda-numerica.ts` | `bloquearRuedaEnNumericos()` | Se llama una vez en `app.tsx`. Quita el foco al `<input type="number">` cuando le giran la rueda encima: sin foco el navegador no cambia el valor y la página se desplaza igual |
| `types/` | La forma de lo que manda Laravel | Hay que actualizarlos al cambiar un controlador |

## `database/`

| Carpeta | Qué hay | No obvio |
| --- | --- | --- |
| `migrations/` | 23 archivos: los de Laravel (`0001_*`) y el núcleo del dominio (`2026_09_18_*`) | Cortos a propósito: el porqué de cada columna e índice está en [MER.md](MER.md) |
| `seeders/RolPermisoSeeder` | Lee los permisos de `RolSistema` | Correrlo a mano al sumar un permiso |
| `seeders/ConfiguracionSeeder` | Datos de la institución | Usa `updateOrCreate`: pisa lo ajustado a mano |
| `seeders/UsuarioSeeder` | El administrador inicial | |
| `seeders/CatalogoSeeder` | Asociaciones, escala, tipos de carnet y productos | Valores de **plantilla**; solo fuera de producción. Ver PENDIENTES |
| `seeders/BeneficiarioSeeder` | Padrón de prueba | Solo el padrón: el circuito se carga desde la pantalla |
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
- Pruebas automáticas (se retiraron el 27/09/2026; están en el commit `7dc0ac6`)
