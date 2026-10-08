# Mapa de archivos

Qué hace cada archivo y **qué tiene de no obvio**. Pensado para no tener que
abrirlos: si la fila no dice nada raro, el archivo hace lo que su nombre indica.

Leer junto con [ARQUITECTURA.md](ARQUITECTURA.md), que explica el porqué de las
decisiones que acá solo se nombran, y [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md),
que es la especificación.

> Reescrito el 03/10/2026 contra el código: el mapa anterior describía el modelo
> de `rubros` y `tramites`, que ya no existe. La columna «Ln» calibra: un archivo
> de 50 líneas se abre sin pensar; uno de 800 conviene entenderlo desde acá.

---

## El recorrido de un documento, archivo por archivo

```
Panel ─▶ Controller ─▶ Emitir*Service / OtorgarCupoService   (crea PENDIENTE + liquidación)
                          └─ LiquidarSirebService ─▶ SirebService ─▶ SIREB (POST /liquidaciones)
Ficha ─▶ «Cargar pago»    ─▶ CargarPagoService   ─▶ SIREB (POST /pago-manual)   — solo carga
Ficha ─▶ «Verificar pago» ─▶ ConfirmarPagoService ─▶ SIREB (GET /liquidaciones/{id})
jichi:verificar-pagos ─────┘   ├─ pagada    ─▶ Revisar*Service::aprobar() + Recibo  ─▶ APROBADO
                               └─ vencida / anulada ─▶ sigue PENDIENTE: «Generar nueva liquidación»
Eliminar ─▶ Servicio::eliminar() ─▶ LiquidarSirebService::anular() (consulta antes: con pago no borra)
```

---

## `app/Enums/` — los valores fijos del negocio

Las transiciones viven acá (`permite*()`, `estaAbierto()`, `habilita()`): el
servicio pregunta y el controlador manda la respuesta en los `puede_*`.

| Archivo | Ln | Valores | No obvio |
| --- | --- | --- | --- |
| `EstadoAprovechamiento.php` | 98 | `pendiente`, `aprobado`, `agotado`, `revocado` | `estaAbierto()` = esperando pago. **No hay `vencido`**: la vigencia la dicen las fechas. `no_pagado` = venció el plazo de pago en SIREB sin pago |
| `EstadoCarnet.php` | 116 | `pendiente`, `aprobado`, `revocado` | Solo `aprobado` habilita; si vale HOY lo dice `Carnet::estaVigente()` con la fecha |
| `EstadoFaena.php` | 122 | `pendiente`, `aprobado`, `revocado` | `consumeCupo()` (aprobado/revocado) y `reservaCupo()` (pendiente): de acá sale el saldo de kilos. `revocado` es histórico |
| `EstadoGuia.php` | 101 | `pendiente`, `aprobado` (`Aprobada`), `revocado` (`Revocada`) | Los casos van en femenino; los valores, iguales a los otros tres |
| `EstadoLiquidacionSireb.php` | 36 | `por_enviar`, `registrada`, `anulada` | Dónde está la liquidación respecto de SIREB, no si se pagó: eso se pregunta |
| `TipoActor.php` | 60 | `pescador`, `comercializador` | **Qué emite cada carnet**: `emiteFaenas()`, `emiteGuias()`, `requiereAprovechamiento()`. Nunca por el nombre del tipo de carnet |
| `ModalidadAprovechamiento.php` | 62 | `escala_general`, `especie_especial` | Se copia al otorgar: reclasificar el tramo no cambia lo otorgado |
| `ConceptoArancel.php` | 28 | `faena` | Cobros sin catálogo: una fila de `aranceles_sireb` por caso |
| `ConceptoRecibo.php` | 50 | Las seis casillas del recibo | `desdeDocumento()` elige la casilla por el tipo de documento pagado |
| `CondicionProducto.php` | 75 | Las diez columnas del cuadro D | Un solo enum: el talonario no cruza estado × presentación |
| `MedioTransporte.php` / `TipoTransporte.php` | 33 / 37 | Por dónde / en qué viaja la guía | Separados porque el papel los pregunta por separado |
| `EstadoAsociacion.php` | 51 | `activo`, `inactivo` | Una asociación no se borra: se inactiva |
| `RolSistema.php` | 123 | `administrador` y **todos** los permisos | Un solo rol hoy. Tocarlo exige `db:seed --class=RolPermisoSeeder` |

## `app/Services/` — acá viven las reglas

| Archivo | Ln | Qué hace | No obvio |
| --- | --- | --- | --- |
| `OtorgarCupoService.php` | 231 | Otorgar, corregir y eliminar la autorización | Una autorización en curso por persona (`enCurso()`). El precio sale de SIREB (`verificarPrecio()`). **Corregir cambia solo la embarcación**: el tramo no se toca nunca. El N° (`nro`) es correlativo continuo |
| `EmitirCarnetService.php` | 383 | Emitir, corregir, eliminar, revocar y reponer el carnet | Un carnet vigente por actividad. Pescador exige autorización; comercializador la prohíbe. Adjuntos SUBIDOS antes de la transacción |
| `EmitirFaenaService.php` | 295 | Emitir, corregir y eliminar la faena | Reserva kilos al registrarse; en modo estricto no deja pasar lo libre. Ver `PermisoFaena::cupo` |
| `EmitirGuiaService.php` | 356 | Emitir, corregir, eliminar y revocar la guía | Cabecera y detalle en la misma transacción. `eliminar()` baja el detalle a mano: CASCADE no corre con baja lógica |
| `Revisar*Service.php` (Cupo, Carnet, Faena, Guia) | 41–76 | `aprobar()` de cada documento | **Los llama solo `ConfirmarPagoService`.** Conservan las reglas propias (autorización revocada, kilos libres, fechas, número de registro). `RevisarCupoService` además revoca |
| `LiquidarSirebService.php` | 145 | La liquidación de un documento en SIREB | Dos tiempos: `preparar()` guarda la `Idempotency-Key` DENTRO de la transacción, `enviar()` llama DESPUÉS. `anular()` **consulta antes**: con pago cargado o validado frena (`SirebException::pagoEnRevision()` / `liquidacionPagada()`); vencida sin pago no pide anular |
| `ConfirmarPagoService.php` | 206 | Pregunta a SIREB y actúa | `pagada` + pago `confirmado` → aprueba y emite el recibo (y marca la liquidación `pagada` en el historial); `vencida` → solo avisa, el trámite sigue pendiente. Guarda en `sireb_envio.consulta` lo que dijo SIREB. Guarda el pago informado en `sireb_envio.pago` (solo si cambió: el comando corre cada 10 min y audita). `alNoPoderEliminar()`: al eliminar algo con pago, verifica y arma el aviso |
| `RenovarLiquidacionService.php` | 180 | «Generar nueva liquidación» (08/10/2026) | Solo con la vencida sin pago. **Relee la tarifa del catálogo**, no la del trámite. Escribe con la fila bloqueada y envía después del commit. No cambia el estado |
| `CargarPagoService.php` | 77 | Carga en SIREB el pago de un trámite | **Solo carga.** Antes consulta: liquidación `pendiente` y sin pago, o no carga y corre `verificar()` para poner la ficha al día |
| `CorrelativoService.php` | 78 | Números correlativos con la fila bloqueada | `siguienteContinuo()` (año 0, no reinicia) para lo impreso: recibo, autorización, faena, guía. `siguienteNumero()` por gestión: el registro del carnet. `rellenar()` → seis dígitos |
| `CodigoService.php` | 88 | El código de verificación de 16 caracteres | `asignar()` es idempotente: un reenvío no gasta otro código |
| `CuentaPortalService.php` | 97 | Cuentas del portal | Clave temporal, obligatoria de cambiar. Esas cuentas nunca llevan roles |
| `GestionarRolService.php` | 85 | Crear, editar y eliminar roles | El de `RolSistema` no se toca; con usuarios no se borra. `syncPermissions` no dispara eventos: el cambio se audita a mano (agregó/quitó) |

## `app/Sireb/` — cliente de Recaudaciones

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `SirebService.php` | 277 | Token de Ibare (client_credentials) en caché. `servicios()` en caché `SIREB_CACHE_MINUTOS`; `tarifa()` sin caché. Liquidaciones: `registrarLiquidacion()`, `liquidacion()`, `registrarPagoManual()`, `anularLiquidacion()`. Las escrituras reintentan ante red o 5xx, nunca ante 4xx |
| `PrecioSireb.php` | 60 | El precio al emitir, para congelarlo. Tarifa y servicio `activo` o no se emite (`liquidable` para la autorización) |
| `VistaSireb.php` | 162 | Lo que las pantallas de catálogo necesitan ya armado: select, precios, historial. Abre con SIREB caído |
| `SirebException.php` | 75 | Códigos propios de Jichi `LIQUIDACION_PAGADA` y `PAGO_EN_REVISION` (`frenaPorPago()`). `paraVentanilla()` es lo que muestra el manejador de `bootstrap/app.php` |
| `SinPrecioException.php` | 15 | SIREB no dio un precio cobrable |

## `app/Models/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `Rol.php` | 39 | Hereda el `Role` de Spatie para llevar `Auditable`; registrado en `config/permission.php`. `esDelSistema()` = está en `RolSistema` |
| `AprovechamientoPesq.php` | 451 | La bolsa madre. **Sin columnas de saldo**: `kilosConsumidos()`, `kilosReservados()`, `saldoKg()`, `libreKg()` se calculan sobre las faenas, reusando el `withSum` si vino (por CLAVE, no por null). `sincronizarEstadoPorSaldo()` solo mueve entre aprobado y agotado. `numeroLegible()` lee `nro` |
| `Carnet.php` | 425 | `estaVigente()`, `sinEfecto()` y `etiquetaEstado()` **miran a su autorización**: la consulta tiene que precargarla. `amparaSusPapeles()`: faenas y guías valen si el titular tiene carnet vigente de la actividad. `registro_legible` = `nro` con seis dígitos |
| `PermisoFaena.php` | 305 | Vigencia de 30 días (`fecha_desembarque`). Vale si su carnet y su autorización valen. `$attributes` lleva los defaults que lee el código |
| `GuiaMovimiento.php` | 330 | Vigencia de 5 días. `factorArancel()`: piscicultura al 50%. `numero_legible` lee `nro` |
| `GuiaDetalle.php` | 67 | Un renglón del cuadro D; copia el precio por kilo |
| `Beneficiario.php` | 273 | **camelCase** de `primerNombre` en adelante: en SQL a mano va entrecomillado (`SQL_NOMBRE`). `documento_identidad` lee `ci`, `complemento` y `departamento_id`: los tres van en el select |
| `Recibo.php` | 65 | Copia congelada: monto, concepto, N° de transacción, banco y fecha de pago de SIREB. `recibible` es polimórfica: se precarga con `morphWith` |
| `Codigo.php` | 31 | El código de verificación de los cinco documentos, en una tabla aparte. Ver trait `Codificable` |
| `CategoriaAprovechamiento.php`, `TipoCarnet.php`, `ProductoHidrobiologico.php`, `ArancelSireb.php` | 39–90 | Catálogos con `servicio_sireb` + `tarifa_sireb` (uuid). El precio lo da SIREB |
| `Asociacion.php`, `Departamento.php` | 150 / 83 | Departamento es catálogo cerrado, sembrado en su migración |
| `User.php` | 75 | Con `beneficiario_id` = cuenta del portal, nunca entra al panel. `scopeFuncionarios()` para el módulo Usuarios |
| `Auditoria.php`, `Acceso.php`, `Configuracion.php`, `Correlativo.php` | 18–63 | Soporte |

## `app/Traits/`

| Archivo | No obvio |
| --- | --- |
| `LiquidableSireb.php` | Columnas `sireb_*`, relación `recibo`, `resumenSireb()` (estado, código de pago, `pago`, `pago_consultado`) y `puedeCargarPago()`. Lo usan los cuatro documentos |
| `Codificable.php` | `codigo_legible` y `asignarCodigo()`. Toda consulta que muestre el código necesita `with('codigo')` |
| `Auditable.php` | Audita `created`/`updated`/`deleted`. El motivo viaja por `$modelo->motivoAuditoria`: no escribir la auditoría a mano |
| `HistorialSireb.php` | Al cambiar la tarifa de un catálogo, guarda el par anterior en `sireb_historial` |

## `app/Http/Controllers/`

| Archivo | Ln | No obvio |
| --- | --- | --- |
| `Panel/BeneficiarioController.php` | 481 | **El patrón a copiar**, comentado paso a paso |
| `Panel/AprovechamientoController.php` | 534 | `edit()` manda los datos fijos: solo se corrige la embarcación |
| `Panel/CarnetController.php` | 615 | Revocar y reponer, además del CRUD |
| `Panel/FaenaController.php`, `Panel/GuiaController.php` | 447 / 513 | `excede` y `bloquea` separados: el modo flexible tiene que llegar a la pantalla |
| Los cuatro anteriores | — | `verificarPago()`, `cargarPago()` y `destroy()` con la captura de `frenaPorPago()`: si al eliminar hay pago, vuelven a la ficha con el aviso |
| `Panel/*ImpresionController.php`, `Panel/AutorizacionPescaController.php` | 160–516 | Devuelven bytes (DomPDF), no pantallas. `documento()` lo reusa el portal con la marca «NO VÁLIDO». `CarnetImpresionController::texto()` achica lo que no entra |
| `Panel/ReciboController.php` | 203 | Libro de recibos; `recibible` con `morphWith` |
| `Panel/DashboardController.php` | 202 | El tablero |
| `Panel/UsuarioController.php` | 125 | Seguridad › Usuarios: todas las cuentas, funcionarios y beneficiarios. Solo lista; las acciones del beneficiario siguen en `CuentaPortalController` (la ficha) |
| `Panel/RolController.php` | 175 | Seguridad › Roles: lista y CRUD de roles. Lee la base (lo que hace cumplir el middleware); los módulos, nombres y secciones salen de `RolSistema::CATALOGO`. Las reglas, en `GestionarRolService` |
| Catálogos: `Asociacion`, `CategoriaAprovechamiento`, `TipoCarnet`, `ProductoHidrobiologico`, `ArancelSireb` | 85–198 | Eligen la tarifa de SIREB con `VistaSireb` |
| `Panel/CuentaPortalController.php` | 68 | La clave temporal vuelve por flash una sola vez |
| `Portal/*` | 25–80 | Solo lectura; nada recibe un id: todo sale de la cuenta con sesión. `VistaPreviaController` dibuja «NO VÁLIDO» sobre lo abierto |
| `Publico/VerificacionController.php` | 335 | La única pantalla sin sesión. `datosPublicos()` decide qué se muestra: nada de datos personales completos |
| `StorageController.php` | 75 | **El único que escribe archivos**: 3 MB, nombre aleatorio, disco |

## `app/Http/Requests/` y `app/Exceptions/`

Un Request por formulario (`Emitir*`, `Editar*`/`Actualizar*`, `CargarPagoRequest`,
`EditarCupoRequest`…), salvo **`MotivoRequest`**, que sirve a eliminar, revocar y reponer
los cuatro trámites: las nueve clases que había validaban lo mismo. `validated()` devuelve
**solo lo que vino**: un `nullable` ausente va con `?? null`.

Las excepciones de negocio (`CupoInvalidoException`, `CarnetInvalidoException`,
`PermisoOperativoException`, `CuentaPortalException`, `RolInvalidoException`) traen el mensaje que ve el
funcionario; el controlador lo pone en el campo o en el aviso.

## `app/Support/`

| Archivo | No obvio |
| --- | --- |
| `ExpedienteBeneficiario.php` | Las consultas del expediente compartidas por la ficha y el portal: los `with()` se escriben una vez |
| `ResumenPortal.php` | Lo que el portal muestra de cada documento. `situacion`: vigente / vencido (por fecha) / revocado (incluye sin efecto y no pagado) |
| `../Jobs/VerificarPagoJob.php` | Consulta en SIREB el pago de un trámite, después de mandar la página | Lo dispara el portal (inicio y «En curso») con `dispatchAfterResponse()`: sin trabajador de cola. `encolarDe()` usa la reserva de 2 min por trámite. Saltea lo vencido o anulado, como el comando |
| `TramitesDisponibles.php` | Qué puede pedir la persona (el sello «Usted puede pedirlo» de la vitrina del portal): pregunta a los mismos métodos del modelo |
| `DocumentoDelPortal.php` | Busca por código público, solo lo propio: lo ajeno da 404 igual que lo inexistente |
| `QrVerificacion.php`, `CodigoQr.php` | URL **relativa + `APP_URL`** (nunca el host de la petición); QR pintado con `gd` |
| `ReciboImpreso.php`, `TextoVertical.php`, `MarcaAgua.php` | Piezas de los PDF: lo que DomPDF no sabe hacer va horneado en PNG |
| `Archivos.php`, `Paginacion.php`, `Sql.php` | `Sql` tiene lo que cambia entre PostgreSQL y SQLite |

## `app/AuthIbare/` — login con Ibare

Módulo cerrado: provider, controlador, servicio, rutas y el comando
`jichi:vincular-ibare`. Sacar el provider de `bootstrap/providers.php` lo
desconecta entero. Ver [modulos/IBARE.md](modulos/IBARE.md).

## `app/Console/Commands/`

| Archivo | No obvio |
| --- | --- |
| `VerificarPagosCommand.php` | `jichi:verificar-pagos`, cada 10 min (`routes/console.php`). Revisa lo `pendiente` con liquidación `registrada`. **En producción necesita el cron de Laravel** |
| `ProbarSirebCommand.php` | `jichi:sireb`: prueba token y catálogo. Diagnóstico |

## `routes/`

| Archivo | No obvio |
| --- | --- |
| `panel.php` | Prefijo `/panel`, middleware `funcionario` en el grupo y `permiso:` en cada ruta. Cada documento tiene `verificar-pago` y `cargar-pago`, cada una con su permiso del mismo nombre. Las rutas literales (`crear`, `buscar`) van antes de `{id}` |
| `portal.php` | `/mi-cuenta`, middleware `beneficiario` |
| `publico.php` | Portada y verificación por código |
| `console.php` | El scheduler: `jichi:verificar-pagos` |

## `resources/js/` — frontend

| Carpeta / archivo | No obvio |
| --- | --- |
| `pages/panel/<modulo>/{index,crear,ver,editar}.tsx` | Una carpeta por módulo. Las fichas (`ver.tsx`) reciben los `puede_*` resueltos |
| `pages/panel/catalogos/*` | Cada catálogo con su formulario e historial de tarifas de SIREB |
| `pages/panel/seguridad/roles.tsx` | La lista: rol, descripción, cuántos permisos, usuarios, editar/eliminar |
| `pages/panel/seguridad/usuarios.tsx` | La lista de usuarios, con filtro por estado y buscador |
| `pages/panel/seguridad/roles-formulario.tsx` | Alta y edición: casillas por módulo (la del módulo queda a medias con `indeterminate`) y «Copiar permisos de» |
| `components/panel/pagos/tarjeta-recaudaciones.tsx` | **La tarjeta de pago de las cuatro fichas**: estado de la liquidación, código de pago copiable, «Pago informado» / «Pago cargado: No», formulario «Cargar pago», «Verificar pago» y recibo |
| `components/panel/comunes/enlace-permitido.tsx` | Enlace a otro módulo que solo es enlace si el rol tiene su `ver`; si no, queda el texto |
| `components/comunes/texto-copiable.tsx` | Copiar con un clic; respaldo con `execCommand` fuera de HTTPS |
| `components/panel/beneficiarios/*` | La ficha del beneficiario por pestañas: pescador, comercializador, pagos |
| `components/portal/*`, `pages/portal/*` | El portal: colores claros fijos, sin `dark:` |
| `components/publico/*`, `pages/publico/*` | Portada institucional y verificación |
| `components/ui/*` | Los únicos con vocabulario en inglés (`Button`, `Card`…). Un color nuevo va también a `badge.tsx` |
| `lib/utils.ts` | `bs()`, `fecha()`, `fechaInput()`, `fechaHora()`, `hace()`. **Nunca `new Date(cadena)` directo**: una fecha suelta se corre un día en UTC-4 |
| `types/*.ts` | Espejo de lo que manda cada controlador. `index.d.ts` tiene los estados de los enums |

## `resources/views/documentos/` — los PDF

`autorizacion-pesca`, `carnet-pescador`, `permiso-faena`, `guia-transporte` y
`recibo-oficial`, más `partes/` (QR, marca «NO VÁLIDO», texto perfilado).
Maquetados con tablas y coordenadas: DomPDF no tiene flexbox, grid, `transform`
ni `object-fit`. Las trampas están en `CLAUDE.md`.

## `database/`

| Archivo | No obvio |
| --- | --- |
| `migrations/2026_09_18_*` | **El núcleo**, una migración por tabla. Columna nueva va DENTRO de su migración (regla 12): al cambiar una, se rearma la base. Explicadas en [MER.md](MER.md) |
| `migrations/2026_09_01_*` | Soporte: campos del usuario, correlativos, configuración, auditoría, accesos, permisos |
| `seeders/CatalogoSeeder.php` | Los catálogos con sus tarifas de SIREB |
| `seeders/BeneficiarioSeeder.php` + `factories/BeneficiarioFactory.php` | Datos de prueba: los únicos del sistema |
| `seeders/RolPermisoSeeder.php`, `UsuarioSeeder.php`, `ConfiguracionSeeder.php` | Correrlos a mano al tocar `RolSistema` o la configuración |

## Lo que NO hay que buscar porque no existe

`rubros`, `tramites`, `faenas` (la tabla vieja), `guias`, tabla `pagos`, Caja,
`en_revision`, firma de supervisión, depósitos cargados en Jichi, el estado
`vencido`, `maatwebsite/excel`, `simple-qrcode` y las pruebas automáticas. Todo
está en el historial de git.
