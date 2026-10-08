# Qué falta y qué sigue abierto

> Reescrito el 27/09/2026 y puesto al día con el código el 03/10/2026. Lo del
> modelo anterior (trámites, rubros, el recibo armado al vuelo) y lo ya resuelto
> están en el historial de git.

---

## ✅ Precios de SIREB conectados — 30/09/2026

La autorización, el carnet, la faena y la guía piden el precio a SIREB al
emitirse (`PrecioSireb`) y lo congelan. La escala es un servicio con una tarifa
por tramo, y `CatalogoSeeder` siembra las tarifas de los cuatro catálogos. Lo
que sigue abierto está en [modulos/SIREB.md](modulos/SIREB.md#lo-que-falta).

## 🔴 Hay que rearmar la base de trabajo — 03/10/2026 (y de nuevo el 08/10/2026)

**08/10/2026:** columna `sireb_historial` en `aprovechamientos_pesq`, `carnets`,
`permisos_faena` y `guias_movimiento`, y `aprovechamientos_pesq.sireb_tarifa_id`
pasó a `uuid`. Además, permiso nuevo `*.renovar-liquidacion` en los cuatro módulos:
`php artisan db:seed --class=RolPermisoSeeder`. Sin migrar, las fichas responden
`column "sireb_historial" does not exist`. Verificado sobre SQLite descartables
(mismos 60 índices que antes).

Cambiaron migraciones ya corridas (regla 12): la columna `nro` en
`aprovechamientos_pesq`, `carnets`, `permisos_faena` y `guias_movimiento`
(reemplaza a `numero_faena`, `numero_guia`, `nro_registro` y al id impreso), y
comentarios de columnas. Los estados `vencido` pasaron a `no_pagado`. **Hasta
correr `php artisan migrate:fresh --seed` la base de trabajo no coincide con el
código** y las pantallas responden `column "nro" does not exist`. Verificado
sobre SQLite descartables. Antes ya hacía falta por las columnas del portal
(`users.beneficiario_id`, `debe_cambiar_password`) y de Ibare (`users.mamore_id`).

## 🔴 El pago en SIREB: falta probarlo de punta a punta — 02/10/2026

Los cuatro documentos registran su liquidación en SIREB, el pago se puede cargar
desde Jichi («Cargar pago»), y se aprueban solos cuando SIREB los da por pagados
(si la liquidación vence, siguen pendientes: 08/10/2026). Probado con SIREB
simulado y con la consulta real de una liquidación de test.sireb (solo lectura).
**Falta, contra test.sireb:** cargar un pago desde Jichi, validarlo allá y ver la
aprobación y el recibo; dejar vencer una liquidación sin pago; eliminar un
pendiente y ver la anulación. Ver [modulos/PAGOS.md](modulos/PAGOS.md).

## 🟠 Lo que falta definir con Recaudaciones — 03/10/2026

- ~~Vencida con un pago en revisión~~ — **resuelto el 08/10/2026**: no existe. En
  SIREB una liquidación vence PORQUE no se pagó; vencida nunca tiene pago.
- **La imagen del comprobante:** la API no la expone (el `Pago` trae N°, banco,
  fechas, monto y estado). Hace falta un `comprobante_url` o un endpoint.
- **Una liquidación anulada desde SIREB** deja el documento pendiente sin salida
  automática; se corrige o se elimina a mano.
- **El plazo de pago no se muestra en la ficha** («Pagar hasta…»): la fecha está
  en `sireb_envio.respuesta.fecha_vencimiento`.

## 🔴 En producción hace falta el cron de Laravel — 02/10/2026

*(08/10/2026)* **En Coolify:** «Scheduled Task» con `php artisan schedule:run` cada
minuto (ver DOCKER-TECNICO.md). No hace falta trabajador de cola: el portal corre
sus consultas después de la respuesta (`dispatchAfterResponse`).

`jichi:verificar-pagos` está programado cada 10 minutos en `routes/console.php`,
pero corre solo si el servidor tiene el cron de `php artisan schedule:run` (cada
minuto). Sin él nada se aprueba solo: queda el botón «Verificar pago» de cada
ficha.

## 🟠 La guía de piscicultura no viaja a SIREB con su descuento — 02/10/2026

La liquidación de la guía manda un ítem por renglón (tarifa por kilo × kilos):
SIREB cobra la tarifa entera. El 50% de piscicultura (`factorArancel()`) no
tiene cómo expresarse ahí. Hoy no pasa porque la casilla está oculta; antes de
reactivarla hay que acordar con Recaudaciones una tarifa propia de
piscicultura.

## ✅ «Registrar» la autorización vuelve a guardar — 02/10/2026

Se quitó el modo de solo verificación. `otorgar()` verifica la tarifa en SIREB
(`verificarPrecio()`) y, si está activa, guarda; si no, no guarda y el motivo
sale en el aviso rojo de arriba.

## ✅ El precio al emitir: endpoint definitivo de SIREB — 02/10/2026

`PrecioSireb::de()` pide la tarifa con `SirebService::tarifa($servicio, $tarifa)`
(`GET /api/v1/catalogo/servicios/{s}/tarifas/{t}`, sin caché) y exige tarifa y
servicio `activo`; la autorización exige además `liquidable`. Flujo completo en
[modulos/SIREB.md](modulos/SIREB.md#validación-de-la-tarifa-al-emitir).
Probado contra `test.sireb` y el tramo real; **falta probar desde las pantallas
los cuatro documentos**, incluido el caso de una tarifa dada de baja.

## 🟡 La guía hace una llamada a SIREB por tarifa distinta — 02/10/2026

Antes era una por servicio. Con SIREB de prueba cada llamada tardó ~3 s, así que
una guía con cinco productos de tarifas distintas puede tardar ~15 s en
registrarse. Medirlo en la pantalla; si molesta, la guía puede volver a pedir el
servicio entero (`servicio($id)`, que trae todas sus tarifas con su `estado`).

## 🟠 Portal del beneficiario: lo que quedó afuera a propósito — 28/09/2026

- **Imprime desde casa lo vigente** (autorización, faena y guía; el carnet no, se repone en ventanilla). La copia
  impresa no se distingue del papel de ventanilla: si la unidad quiere, se le
  agrega una marca «COPIA DEL TITULAR».
- **Sin trámites:** el portal solo consulta.
- **Sin «olvidé mi contraseña»:** se resetea en ventanilla.
- Las tarjetas se probaron con datos reales solo del lado del servidor
  (`ResumenPortal`). Después de migrar, conviene recorrer el portal de un
  beneficiario con carnet, faena y guía.

## 🟠 Separación de funciones: un solo rol

- **Hay un solo rol, `administrador`, con todos los permisos.** Aprobar ya no es
  de nadie —lo decide el pago validado en SIREB, 02/10/2026—, pero eliminar,
  revocar sigue siendo de cualquiera. Los permisos ya están repartidos por bloque
  —y desde el 05/10/2026 pago, reposición y asociaciones tienen el suyo—
  (`$lectura`, `$operacion`, `$supervision`, `$administracion`) y cada ruta
  declara su `permiso:`, así que el rol de ventanilla es una línea:

  ```php
  self::Operador => [...$lectura, ...$operacion],
  ```

- La clave `pagos.revisor_distinto` salió de `ConfiguracionSeeder` (ya no hay
  control de boletas en Jichi). Si quedó en la tabla `configuraciones` de una base
  vieja, no la lee nadie.
- **Roles: se crean desde el panel** en Seguridad › Roles (05/10/2026), pero **todavía
  no se pueden asignar**: falta la pantalla de Usuarios. Mientras tanto, por tinker:
  `User::find($id)->syncRoles(['Operador'])`.
- **Seguridad › Usuarios lista hoy solo a los beneficiarios con cuenta** (05/10/2026).
  **Los funcionarios** siguen creándose por consola (`php artisan tinker`), sin pantalla
  para darles rol, activarlos ni vincularlos con Ibare (`jichi:vincular-ibare`).
  El borrador `GuardarUsuarioRequest` se quitó el 28/09/2026 por no tener ruta
  ni controlador; está en el historial de git (commit `8511537`).

## ✅ Nada vence solo, y no hace falta — 03/10/2026

Ya no hay estado `vencido` ni comando diario: la **vigencia** se lee de las fechas
(`estaVigente()`, scopes `vigentes()` y `enCurso()`) y un aprobado queda
`aprobado` —en el filtro «Aprobado» de los listados aparecen también los que
pasaron su fecha, y es a propósito—. Desde el 08/10/2026 el plazo de **pago**
tampoco cambia el estado: con la liquidación vencida el trámite sigue pendiente
y se genera otra, o se elimina para liberar los kilos y el lugar.

## 🟠 Datos de desarrollo con 4 horas de corrimiento

El 27/09/2026 la zona horaria pasó de UTC a `America/La_Paz` (ver «Trampas» en
CLAUDE.md). Lo cargado antes quedó con `created_at` en hora UTC y ahora se lee 4
horas más tarde: un cobro de las 21:00 aparece a la 01:00 del día siguiente. Se
resuelve rearmando la base de desarrollo (`migrate:fresh --seed`).

## 🟡 NOTAS-CODIGO.md: puede quedar alguna mención suelta — 03/10/2026

Se sacaron las secciones de archivos borrados y las notas del circuito viejo, y
se reescribieron las de los estados. Puede quedar alguna palabra del modelo
anterior (trámite, rubro) dentro de una nota que vale en lo demás. Ante una
contradicción, mandan REGLAS-NEGOCIO.md y MER.md.

## 🟠 No hay pruebas automáticas

Se retiraron el 27/09/2026 a pedido del responsable (204 de PHP y 13 de React;
están en el commit `7dc0ac6`). Todo cambio se verifica a mano en el navegador.
Si se retoman, lo que más daño evita es, en este orden: la liquidación en
SIREB y la aprobación por pago (con `Http::fake`), la reserva de kilos de la faena y que todo archivo pase por
`StorageController`.

## 🟠 El recibo no se puede anular

En el talonario de papel se anulaba escribiendo «ANULADO» sobre las tres copias.
Una vez emitido, el recibo digital queda. Está sin definir con la unidad —y con
Recaudaciones, que es donde está el pago— qué pasa con la plata en ese caso.

---

## ✅ Módulo del pescador — cerrado el 27/09/2026

Autorización de Pesca para Aprovechamiento Pesquero, carnet de pescador y
permiso de faena: circuito completo, cobro en SIREB, impresión de
los tres papeles, reserva de kilos y revocación (sin cascada: deja «sin efecto»
a carnets y faenas, ver REGLAS-NEGOCIO, Regla 5). La especificación está en
[REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md) y el diagrama en
`docs/diagramas/flujo-pescador.html`.

Quedan dos observaciones que **no bloquean** el trabajo de ventanilla:

- **Paiche:** la especie especial se comporta igual que la escala general. Si la
  unidad quiere otra dinámica, es un cambio en `ModalidadAprovechamiento`.
- **Reposición de carnet:** precio, adjuntos y vínculo entre carnets quedaron
  con decisiones por defecto; ver [REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md).

---

## Módulo del comercializador

Revisado de punta a punta el 27/09/2026.

### ✅ La escala ya es la del reglamento — 28/09/2026

`CatalogoSeeder` siembra los 7 tramos del Art. 20 del Reglamento de Pesca del
SEDAG-BENI (25/11/2016), con el paiche a 500 Bs. Se aplica **al volver a
migrar** con `--seed`: sobre la base de trabajo actual el seeder no pisa nada,
porque usa `firstOrCreate`.

### 🟠 Piscicultura: la casilla está OCULTA — 28/09/2026

El reglamento (Art. 22 VIII) da el 50% de la guía solo a comunidades indígenas y
campesinas, organizaciones e iniciativas familiares, durante **3 años** desde el
inicio de la producción y **con registro previo** en el SEDAG. El sistema lo
aplicaba a cualquiera que marcara la casilla, así que se ocultó
(`MOSTRAR_PISCICULTURA` en `campos-guia.tsx`). La lógica del descuento sigue
en el servidor. Para reactivarla hace falta un registro de piscicultores, y
conviene imprimir el descuento en la guía: hoy el papel no dice por qué el
importe es la mitad.

### 🟠 Falta el reporte por especie y por período

Es para lo que `guia_detalles` es una tabla aparte. Desde el 27/09/2026 la
especie sale del catálogo `productos_hidrobiologicos` (`producto_id`), así que
el reporte ya puede agrupar sin pelear con «Surubí» / «surubi» / «SURUBI».

### 🟠 Los precios por kilo de los productos son de PLANTILLA

Desde el 27/09/2026 la guía cobra el total de su cuadro D, así que el precio de
cada producto es plata. El catálogo se sembró con los 13 nombres del talonario y
precios de ejemplo entre 0,20 y 0,50 Bs/kg; hay que confirmarlos contra la
resolución en Catálogos → Productos.

### 🟠 Los catálogos están sembrados con valores de PLANTILLA (afecta a los dos módulos)

`CatalogoSeeder` llena `asociaciones` (4) y `tipos_carnet` (2) con valores de
ejemplo, para que el circuito se pueda recorrer en desarrollo. La escala
(`categorias_aprovechamiento`) ya es la oficial: ver arriba.

Qué se supuso, para que se sepa qué hay que confirmar:

- Los NOMBRES de los dos tipos de carnet sí son los oficiales; sus precios no.
- Las asociaciones son nombres verosímiles, no el registro real del SEDAG.

Dos cosas al reemplazarlos:

1. El seeder usa `firstOrCreate` a propósito —para no pisar lo que la unidad
   ajuste desde el panel—, así que volver a correrlo **no** actualiza los
   valores. Hay que editarlos en la base o vaciar las tablas primero.
2. Los tramos de la escala tienen que quedar **contiguos y sin huecos**: el
   `kilos_min` de cada uno es el `kilos_max` del anterior más 1. Con un hueco,
   los volúmenes que caen adentro no encuentran escala.

Mientras sigan siendo plantilla, `CatalogoSeeder` corre **solo fuera de
producción**. En cuanto sean los de la resolución, sube al bloque de siempre de
`DatabaseSeeder`.

Ver [docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md).


---

## 🟠 En el celular, los botones del encabezado se salen de la pantalla — 27/09/2026

En la ficha del beneficiario, a 390 px la página mide 613 de ancho: la fila de
acciones del `LayoutPanel` («Registrar aprovechamiento», «Emitir carnet»,
«Editar», «Dar de baja») no se parte en renglones. Es del layout, así que
probablemente pasa en toda ficha con varias acciones. En la ficha del
beneficiario dejó de pasar al quitar «Registrar aprovechamiento» y «Emitir
carnet» (27/09/2026); falta revisar las otras fichas. Se midió con
`document.documentElement.scrollWidth` en Chrome sin cabeza.

## 🟠 Login con Ibare: hecho del lado de Jichi, falta cargar Ibare — 24/09/2026

- **`users.mamore_id` es columna nueva** en la migración de campos institucionales:
  la base de trabajo no la tiene hasta volver a migrar.
- En Ibare falta registrar el cliente `jichi` y dar de alta los funcionarios.
  Ver [modulos/IBARE.md](modulos/IBARE.md).
- **Problemas de Ibare que Jichi no puede arreglar:** su login de funcionarios no
  tiene `throttle`, y el refresh token no vuelve a consultar el contrato
  (Jichi no usa refresh, así que no lo afecta).
- No hay pantalla para vincular usuarios: se hace con `php artisan jichi:vincular-ibare`.

## 🟠 Antes de imprimir un lote: `APP_URL`

El carnet, la autorización, la faena y el recibo llevan el QR con
`<APP_URL>/verificar/<codigo>`, y `APP_URL` es la única fuente del dominio. En
desarrollo es `http://127.0.0.1:8000` a propósito; **al pasar a producción hay
que poner el dominio público** y correr `php artisan config:clear`. Lo impreso
con la dirección local sale con un QR que no abre (el código escrito al lado
se puede tipear igual en `/verificar`).

---

## 🟠 Falta cargar quién firma el dorso del carnet — 22/09/2026

El carnet ya se imprime de los dos lados; el dorso lleva el reglamento del SEDAG
y el recuadro de la firma. Ver
[docs/modulos/CARNETS.md](modulos/CARNETS.md) §6 bis.

**`carnet.firmante_nombre` se siembra VACÍO a propósito** —estampar el nombre de
quien ya no está en el cargo es peor que no poner ninguno—, así que hoy el dorso
sale con el cargo y sin nombre. Hay que cargar el del Gobernador en curso.

Las dos claves son nuevas en `ConfiguracionSeeder`, así que hasta correrlo no
existen en la base. El cargo tiene valor por defecto en el controlador y el
dorso sale completo igual; lo único que falta es poder editarlos:

```sh
php artisan db:seed --class=ConfiguracionSeeder
```

> ⚠️ **Ese seeder usa `updateOrCreate`:** vuelve a escribir TODAS las claves con
> el valor de la lista. Si alguna se ajustó a mano en la base, se pierde. Para
> sumar solo las dos nuevas, `firstOrCreate` sobre esas claves.

**Y dos cosas del dorso quedaron sin resolver:**

- En el plástico de papel hay además una **franja blanca al pie**, a la
  izquierda del recuadro de la firma. No se reprodujo porque en la foto está
  cortada por el borde y no se ve qué lleva impreso —puede ser el borde blanco
  de la tarjeta—. Hay que mirar un plástico de cerca.
- La regla 5 dice **«dictadas por el DDAG - BENI»**. En la foto la sigla está
  borrosa y podría ser UDAG. Confirmar con la unidad antes de imprimir un lote.

---

## 🟢 La portada institucional ya existe — 22/09/2026

`/` dejó de redirigir al login y abre el sitio público: servicios, pasos,
verificación, preguntas y contacto. Ver
[docs/sesiones/09-2026/2026-09-22.md](sesiones/09-2026/2026-09-22.md).

Tres cosas quedaron abiertas a propósito:

### 🟠 Los textos de la portada están escritos en el código, no en la base

Servicios, pasos y preguntas viven como constantes dentro de sus componentes.
Es lo correcto hoy —son la especificación de `REGLAS-NEGOCIO.md`, no un dato que
la unidad edite—, pero el día que quieran cambiar una respuesta sin tocar código
hay que moverlos a `configuraciones` o a una tabla propia.

### 🟠 El texto «Sobre el SEDAG» es una BASE — 28/09/2026

La portada se rediseñó con la paleta «Ríos del Beni» y ganó la sección
`#sedag` (`components/publico/institucional/sedag.tsx`). Su texto —qué es el
SEDAG y las cuatro tareas de la Unidad de Pesca— se escribió sin cifras ni
fechas, pero **no es el oficial**: hay que confirmarlo con la unidad o
reemplazarlo por su misión y visión. Las cifras del hero y la franja de
especies sí son reales: salen de `jichi.provincias` y del catálogo de productos.

### 🟠 `municipio.horario` no está en el seeder

La portada lo lee con un valor por defecto («Lunes a viernes, de 08:00 a
16:00»), así que se dibuja igual, pero no se puede cambiar desde el panel hasta
que la clave exista. Va a `ConfiguracionSeeder` con `publico = true`, y después
hay que correr `php artisan db:seed --class=ConfiguracionSeeder`.

### 🟠 Los contadores públicos se dejaron fuera

Se evaluó mostrar carnets vigentes, permisos emitidos y kilos autorizados.
Publicar volumen operativo es una decisión del responsable, no técnica, y además
son consultas agregadas que habría que cachear. El hueco está listo en la
portada, entre servicios y pasos.

### 🟠 El sitio público no se indexa bien

Inertia pinta la portada en el navegador, así que un buscador que no ejecute
JavaScript ve una página vacía. Hoy no importa —se llega por el dominio o por el
QR—, pero si se quiere que aparezca en Google hay que activar SSR o servir la
portada desde Blade.


---

## Módulos que faltan

### Reportes

Aparece en gris en el menú. No existe ni la ruta ni el controlador. Debería
tener:

- Recaudación por tipo de documento y por período, exportable a Excel.
- Padrón de carnets vigentes de una gestión, para imprimir.
- Kilos autorizados y consumidos por autorización; carga por producto de las
  guías (ver «Falta el reporte por especie y por período», arriba).

Por dónde empezar: copiar el patrón de `BeneficiarioController::index()`
—filtros + `Paginacion` + `through()`— y exportar a Excel con un paquete a elegir
(`maatwebsite/excel` se retiró el 03/10/2026: nadie lo usaba). El libro de recibos ya existe (`/panel/recibos`).

### Configuración

Aparece en gris en el menú. La tabla `configuraciones` existe y está sembrada;
falta la pantalla para editarla agrupada por `grupo`, subir el logo y el escudo
(tipo `archivo`) y el alta de usuarios (ver arriba).

> Al construir usuarios, se pensó exigir que el correo del funcionario termine en
> el dominio de la Gobernación (`beniautonomo.gob.bo`). La clave de configuración
> que lo iba a encender (`jichi.dominio_institucional`) se quitó el 08/10/2026
> porque nadie la leía: va de nuevo con el módulo.

---

## Orden sugerido

1. Rearmar la base, probar el pago en SIREB de punta a punta y dejar el cron de
   Laravel en el servidor.
2. Separación de funciones: rol de ventanilla y pantalla mínima de usuarios —
   antes de poner el sistema en manos de varias personas.
3. Definir con Recaudaciones lo de la vencida con pago, la imagen del comprobante
   y mostrar el plazo de pago en la ficha.
4. Confirmar los catálogos contra la resolución (los precios ya son plata).
5. Reportes.
6. Configuración.
