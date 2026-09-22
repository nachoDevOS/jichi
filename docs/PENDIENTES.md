# Qué falta y qué sigue abierto

---

## 🔴 ABIERTO Y BLOQUEANTE — el panel no está portado al núcleo nuevo

El **18/09/2026** se rehízo desde cero el núcleo de datos y se migró sobre
`jichi1`. **La base y los modelos están listos y probados; la capa de
aplicación no.**

**Estado medido el 18/09/2026**, pidiendo cada ruta contra un servidor limpio.
**Ninguna ruta declarada da 500:** el código del modelo anterior se retiró en
vez de dejarlo apuntando a tablas que no existen.

| Ruta | | |
| --- | :-: | --- |
| `/panel/dashboard` | ✅ 200 | portado |
| `/panel/beneficiarios` | ✅ 200 | portado |
| `/panel/beneficiarios/crear` | ✅ 200 | portado |
| `/panel/beneficiarios/{id}` | ✅ 200 | ficha nueva: carnets + cupos |
| `/panel/beneficiarios/{id}/editar` | ✅ 200 | portado |
| `/panel/beneficiarios/buscar` | ✅ 200 | devuelve `carnets_vigentes` |
| `/panel/catalogos/asociaciones` | ✅ 200 | CRUD sin borrado |
| `/panel/catalogos/categorias-aprovechamiento` | ✅ 200 | rechaza solapes, avisa huecos |
| `/panel/catalogos/tipos-carnet` | ✅ 200 | CRUD sin borrado |
| `/panel/aprovechamientos` | ✅ 200 | listado con saldo en kilos |
| `/panel/aprovechamientos/crear` | ✅ 200 | una bolsa vigente por persona |
| `/panel/aprovechamientos/{id}` | ✅ 200 | ficha con faenas |
| `/panel/carnets` | ✅ 200 | busca también por código |
| `/panel/carnets/crear` | ✅ 200 | una credencial vigente por actividad |
| `/panel/carnets/{id}` | ✅ 200 | ficha con qué habilita hoy |
| `/panel/carnets/{id}/imprimir` | ✅ 200 | PDF CR80, 148 KB |
| `/panel/faenas` | ✅ 200 | marca las caducadas sin cerrar |
| `/panel/faenas/crear` | ✅ 200 | descuenta del cupo, con el número propuesto |
| `/panel/faenas/{id}` | ✅ 200 | ficha con cierre y corrección de kilos |
| `/panel/guias` | ✅ 200 | busca por número, nombre, origen y destino |
| `/panel/guias/crear` | ✅ 200 | bloques A–D, con el arancel y el descuento |
| `/panel/guias/{id}/editar` | ✅ 200 | solo el borrador y sin depósitos cargados |
| `/panel/guias/{id}` | ✅ 200 | circuito completo, cierre con peso y anulación con motivo |
| `/panel/guias/{id}/imprimir` | ✅ PDF | una página, QR legible a 150 dpi |
| `/panel/caja` | ✅ 200 | abonos + arqueo del día por método |
| `/panel/caja/cobrar` | ✅ 200 | un recibo cubre varios trámites |
| `/panel/recibos` | ✅ 200 | avisa los que dejaron de cuadrar |
| `/panel/recibos/{id}` | ✅ 200 | detalle del comprobante |
| `/verificar` y `/verificar/{codigo}` | ✅ 200 | portado a `codigo_carnet` |

Probado además el camino de escritura: alta → 302, alta con cédula repetida →
422 con el mensaje correcto bajo el campo `ci`.

### Lo que se BORRÓ, y dónde encontrarlo

Todo está en git, en el commit `8d48422`. Se retiraron porque no tienen
equivalente en el núcleo nuevo o porque hay que rehacerlos:

| Capa | Qué se fue |
| --- | --- |
| Modelos | `Rubro`, `Tramite`, `Faena`, `Guia`, `GuiaDetalle` |
| Enums | `EstadoTramite`, `TipoTramite`, `EstadoRubro`, `EstadoValidacionPago`, `EstadoPermiso`, `ConceptoRecibo`, `CondicionProducto`, `TipoTransporte`, `FormaPago` |
| Support | `SituacionCarnet`, `ReciboArmado`, `ControlDePago` |
| Servicios | `SolicitudCarnetService`, `PagoTramiteService`, `ValidacionPagoService`, `ReciboTramiteService`, `FaenaService`, `GuiaService`, `ArchivoTramiteService` |
| Controladores | `Tramite`, `Rubro`, `Faena`, `Guia`, `Pago`, `Recibo`, `Carnet`, `CarnetImpresion` |
| Requests | los seis del modelo anterior |
| React | `pages/panel/{tramites,rubros,carnets,faenas,guias,pagos}`, sus componentes y sus `types/*` |
| Excepciones | `SolicitudInvalidaException`; `PermisoOperativoException` se reescribió |

> **MIRAR ANTES DE REHACER CARNETS:** `CarnetImpresionController` tenía resuelta
> la maqueta del plástico en DomPDF —el encogido de texto que achica en vez de
> cortar, el `cover` a mano de la foto, el texto perfilado de cinco copias, el
> ancho por carácter medido sobre las DejaVu—. Nada de eso cambia con el modelo
> nuevo y volver a deducirlo cuesta días. Las plantillas Blade de
> `views/documentos/` NO se borraron.

Las migraciones del modelo anterior quedaron en `database/migrations-anterior/`
—Laravel no recorre esa carpeta— por si hace falta consultarlas.

### Cómo quedó el menú, y por qué

`navegacion.ts` se reordenó según el FLUJO y no por abecedario. Los módulos sin
ruta declarada se dibujan en gris y no se pueden pinchar:

```
Panel         ✅
VENTANILLA    Beneficiarios ✅ · Cupos de pesca ✅ · Carnets ✅ · Faenas ✅ · Guías ✅
CAJA          Cobros ✅ · Recibos ✅
CATÁLOGOS     Asociaciones ✅ · Escala ✅ · Tipos de carnet ✅
ADMIN         Reportes · Configuración
```

Leído de arriba hacia abajo, Ventanilla ES el procedimiento del mostrador.

### Orden para seguir

1. ~~**Catálogos**~~ — ✅ hecho el 18/09/2026. Los tres con `index` + `store` +
   `update`, sin `destroy`, y con el formulario al lado de la tabla. **Los
   valores siguen siendo la plantilla**: se pueden corregir desde el panel, pero
   nadie los corrigió todavía.
2. ~~**Aprovechamientos**~~ — ✅ hecho el 18/09/2026. `OtorgarCupoService` con
   la fila del beneficiario bloqueada y la ficha con las faenas que explican el
   saldo. **Ampliado el mismo día** con las dos modalidades —escala general y
   especie especial— y el interruptor `APROVECHAMIENTO_ESTRICTO`. El 19/09 se
   retiró «ampliar cupo» y se sumaron corrección y baja del borrador.
3. ~~**Carnets**~~ — ✅ hecho el 18/09/2026. `EmitirCarnetService`, revocación
   con motivo auditado, y la impresión del plástico **recuperada de `8d48422` y
   adaptada**: se conservó toda la maqueta DomPDF y cambió solo el dominio.
4. ~~**Faenas y Guías**~~ — ✅ hechos el 18/09/2026. Faenas con el
   aprovechamiento bloqueado y cierre con corrección contra la balanza; guías
   con el descuento de piscicultura, cierre con peso y anulación con motivo —y
   la regla de que una guía CERRADA ya no se anula.
5. ~~**Caja**~~ — ✅ hecho el 18/09/2026. `CobrarService` con la fila del
   trámite bloqueada, correlativo reservado dentro de la transacción, cobros
   fraccionados y un recibo que cubre varios trámites.

**Con esto los tres circuitos del diagrama están completos.** Lo que queda son
los dos módulos que nunca existieron —Reportes y Configuración— y la impresión
del recibo en PDF: la plantilla `views/documentos/recibo-oficial.blade.php`
sobrevivió al cambio de núcleo y espera `$recibo`, `$renglones`, `$total`,
`$fecha`, `$casillas` y `$esDeposito`; adaptarla es el mismo trabajo que se hizo
con el carnet.

De adentro hacia afuera en cada uno: servicio → Request → controlador → ruta →
tipos de TypeScript → pantalla. El patrón a copiar es **Beneficiarios**, que
está comentado paso a paso a propósito.

### 🟢 El aprovechamiento ya es un borrador corregible — RESUELTO el 18/09/2026

Antes nacía `activo` y no se podía corregir ni eliminar: una carga equivocada
quedaba ahí para siempre. Ahora:

```
PENDIENTE ──[se cobra ENTERO]──▶ ACTIVO ──▶ AGOTADO | VENCIDO
(se edita, se elimina,
 NO emite faenas)
```

Quien lo activa es la caja y no hay otro camino. Eliminar borra la fila de
verdad y deja el motivo en `auditorias`, con casilla de consentimiento en la
ventana. Ver el Trabajo 14 de
[docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md).

**Lo que hace falta confirmar con la unidad:** que un cupo sin cobrar NO
autorice a pescar. Es la consecuencia que más se nota en ventanilla, y es una
decisión administrativa, no técnica. Si la unidad quiere que autorice igual, es
una línea en `EstadoAprovechamiento::habilita()`.

### 🔴 Falta IMPRIMIR la autorización de aprovechamiento

El sistema guarda los datos del talonario verde —«AUTORIZACIÓN DE PESCA PARA
APROVECHAMIENTO PESQUERO»— pero **no emite el papel**. Es el mismo trabajo que
se hizo con el carnet: una plantilla Blade maquetada para DomPDF.

Mirando el formulario de papel completo, faltan además **dos datos** que no
tienen dónde guardarse:

1. **El número preimpreso del talonario** («N° 000536»). En
   `permisos_faena.numero_faena` ese número existe, pero desde el 21/09/2026 lo
   GENERA el sistema con un correlativo continuo en vez de copiarlo del papel:
   si acá se quiere el mismo trato, la columna sigue sin existir.
2. **«Tiempo de Cancelación»** — el plazo que se le da al pescador para pagar la
   concesión.

Lo que sí está cubierto: titular, documento, domicilio, **tipo de embarcación**
(agregado el 18/09), volumen establecido, vigencia y valor de la concesión.

La tabla de especies con sus tamaños mínimos y las reglas de mallas son texto
fijo del formulario: van en la plantilla, no en la base.

### 🟠 Una faena PENDIENTE reserva kilos y nada la caduca

**Desde el 20/09/2026 la faena nace PENDIENTE** y sus kilos ya pesan contra el
cupo —`EstadoFaena::consumeCupo()` solo deja afuera a la vencida—, que es lo que
impide que tres solicitudes por el cupo entero pasen las tres.

El costo: una solicitud que nadie cobra ni rechaza **se queda reservando esos
kilos para siempre**. El comando diario que pasa las faenas a `vencido` todavía
no existe —es el mismo que le falta al carnet—, así que hoy no hay nada que
suelte una pendiente abandonada. Mientras no exista, la salida es rechazarla o
eliminarla a mano.

**Al escribir ese comando hay que decidir de qué lado cae la pendiente**: lo
razonable es caducar también las que pasaron su `fecha_limite` sin firmarse,
porque la salida que amparaban ya no puede ocurrir.

### 🟢 RESUELTO — el arancel de la faena sale del talonario

`config('jichi.faenas.tarifa_base')` —`JICHI_FAENA_TARIFA_BASE`— arrancaba en
**30 Bs**, puesto por analogía con la guía (50 Bs). El 21/09/2026 se bajó a
**15 Bs**, que es lo que dice impreso la hoja del talonario.

Y dejó de leerse al mostrar: `permisos_faena.monto` guarda la **copia
congelada** del arancel al emitir, así que una suba por resolución no mueve el
monto de un papel ya entregado. La config solo la lee el servicio al crear la
fila.

### 🟠 El control de tope de cupo se puede APAGAR, y hoy está encendido

Desde el **18/09/2026** el sistema tiene un interruptor,
`APROVECHAMIENTO_ESTRICTO` en el `.env`, que se lee con
`config('jichi.aprovechamiento.estricto')` y nunca con `env()`:

| Valor | Qué hace al emitir una faena |
|---|---|
| `true` (**hoy**) | La faena que no entra en el saldo se RECHAZA. Al llegar a 0 kg el cupo queda `agotado` y no emite más |
| `false` | La comprobación del tope se OMITE: la faena se emite igual y el exceso queda registrado |

**Existe porque los catálogos siguen siendo plantilla** (ver el punto de
arriba): frenar a un pescador real contra un tope de ejemplo hace más daño que
respetarlo. En cuanto la escala oficial esté cargada, esto se deja en `true` y
no se vuelve a tocar.

**Tres cosas que el interruptor NO apaga**, y conviene tenerlas presentes antes
de suponer que apagarlo desactiva el módulo:

- **La fecha.** Un cupo vencido no emite faenas en ningún modo.
- **La modalidad.** Una especie especial no se amplía ni con el control apagado.
- **El registro.** El saldo, el estado `agotado` y `kilosExcedidos()` se
  calculan siempre.

**Al cambiarlo hay que reiniciar el servidor ENTERO**, no solo el proceso hijo:
`php artisan serve` lee el `.env` una vez al arrancar y el recargador reinicia
únicamente al hijo, que hereda el entorno viejo. Si `composer run dev` está
corriendo, se mata ese proceso y se vuelve a levantar.

### 🟠 El paiche funciona distinto, y hace falta que la unidad lo confirme

Los siete tramos de la escala ya no son todos iguales: el tramo 7 quedó marcado
como **especie especial** y los otros seis como **escala general**. La
diferencia era UNA sola —si el cupo se podía ampliar— y **se retiró el
19/09/2026 junto con la función**. Hoy los dos regímenes se comportan igual:

- **Escala general** — cupo acumulativo. Las faenas lo descuentan y se AMPLÍA.
- **Especie especial** — cuota de la especie, tasación fija. Las faenas lo
  descuentan igual, pero **NO se amplía**: agotado, hay que tramitar uno nuevo,
  cobrarlo y emitir otro recibo.

**Lo que hay que confirmar** es esa interpretación de «sin la misma dinámica de
recarga continua». Si lo que se quería era que las faenas de paiche no
descuenten, o que su tope sea rígido aunque el resto esté en modo flexible, es
un cambio de una línea en el enum.

La modalidad la fija el CATÁLOGO —la resolución al definir el tramo— y se COPIA
al cupo al otorgarlo: reclasificar un tramo no le cambia el régimen a lo ya
otorgado y cobrado.

### 🟠 Los catálogos están sembrados con valores de PLANTILLA

`CatalogoSeeder` llena `asociaciones` (4), `categorias_aprovechamiento` (los 7
tramos) y `tipos_carnet` (2), para que el circuito se pueda recorrer en
desarrollo. **Los números NO son los de la resolución.**

Qué se supuso, para que se sepa qué hay que confirmar:

- De la escala, los únicos textos oficiales que había eran los DOS EXTREMOS:
  «1 Kg Hasta 100 Kg» y «1001 kg Hasta 2000 Kg PAICHE». Los cinco tramos del
  medio están repartidos a ojo.
- Los precios siguen una regla lineal de 55 Bs cada 100 kg, que hace cerrar los
  dos valores bajos conocidos (55 y 110). **El tercer valor conocido —500 Bs—
  NO cae en esa recta**, así que la escala real casi seguro no es lineal: es la
  señal más clara de que esto hay que confirmarlo.
- Los NOMBRES de los dos tipos de carnet sí son los oficiales; sus precios no.
- Las asociaciones son nombres verosímiles, no el registro real del SEDAG.

Dos cosas al reemplazarlos:

1. El seeder usa `firstOrCreate` a propósito —para no pisar lo que la unidad
   ajuste desde el panel—, así que volver a correrlo **no** actualiza los
   valores. Hay que editarlos en la base o vaciar las tablas primero.
2. Los tramos de la escala tienen que quedar **contiguos y sin huecos**: el
   `kilos_min` de cada uno es el `kilos_max` del anterior más 1. Con un hueco,
   `CategoriaAprovechamiento::paraVolumen()` devuelve null para los volúmenes
   que caen adentro y el formulario no ofrece ninguna escala, sin ningún error
   que lo explique.

Mientras sigan siendo plantilla, `CatalogoSeeder` corre **solo fuera de
producción**. En cuanto sean los de la resolución, sube al bloque de siempre de
`DatabaseSeeder`.

Ver [docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md).

---

## ~~🟠 La guía de transporte no tiene PDF~~ — ✅ resuelto el 22/09/2026

`GuiaImpresionController` + `documentos/guia-transporte.blade.php`, calcando el
talonario: los cuatro bloques, el cuadro D y el QR. Sale con
`GET /panel/guias/{guia}/imprimir`, permiso `guias.imprimir`, y **solo con la
guía aprobada**. Los cinco documentos se imprimen ahora.

Medido sobre el PDF, no mirado: una página, el texto cierra en x=577 contra el
borde de la hoja en 578, el QR mide los 46 pt declarados y decodifica
rasterizando la página hasta 150 dpi.

---

## 🔴 HAY QUE VOLVER A MIGRAR — 22/09/2026

`carnets.codigo_carnet` **dejó de existir**: la llave de verificación se mudó a
la tabla nueva `codigos`, compartida por los cinco documentos. Se editó la
migración `create_carnets_table` y se agregó `create_codigos_table`, así que el
esquema real queda viejo hasta que alguien corra:

```sh
php artisan migrate:fresh --seed
```

**Hasta que se corra, el panel revienta** con `no such column: codigo_carnet` en
cuanto se abra un listado de carnets. Se verificó el esquema completo en una
base descartable, no sobre la de trabajo.

## 🔴 `APP_URL` queda IMPRESO en CUATRO documentos — revisarlo antes de un lote

El carnet, la autorización de pesca, el permiso de faena y el recibo llevan el
QR con `<APP_URL>/verificar/<codigo>`. **`APP_URL` es la única fuente del
dominio** desde el 22/09/2026: se retiraron las otras dos
—`JICHI_URL_VERIFICACION` del `.env` y `sistema.url_verificacion` de
`configuraciones`— porque tres lugares que dicen lo mismo se contradicen.

**Hoy `APP_URL=http://jichi.test`**, que es un nombre local: un teléfono con
datos móviles no lo resuelve. Cada documento que se imprima así sale con un QR
que no abre nada, y el papel ya está entregado cuando alguien lo nota.

> ⚠️ **`route()` absoluta NO respeta `APP_URL`: usa el host de la petición.**
> Un operador que entre al panel por la IP de la red imprimiría documentos con
> el QR apuntando a esa IP. Por eso `QrVerificacion` arma la ruta RELATIVA y le
> pega `config('app.url')`. Está medido; ver «Trampas conocidas».

El código escrito al lado del QR sigue funcionando igual —se tipea en
`/verificar`—, así que un documento mal impreso no queda sin forma de
verificarse.

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

## El circuito del depósito observado quedó cerrado — y un rechazo ya no es el final

El **17/09/2026**, más tarde, apareció en ventanilla un expediente TRABADO: en
revisión, con un depósito validado y otro observado. No se podía aprobar —faltaba
controlar el observado—, no se podía corregir —el botón vivía en «Editar
trámite», que no abre fuera de PENDIENTE— y no se podía agregar otro depósito por
lo mismo. La única salida que ofrecía la pantalla era «Validar» sobre el
observado, que es justo la que no corresponde.

El agujero de fondo: **observar solo pasa EN REVISIÓN y corregir solo se podía en
PENDIENTE**, así que el circuito observar → corregir → validar no se podía
completar en ninguna pantalla.

Tres cosas cambiaron:

- **Un depósito OBSERVADO ya no ofrece «Validar».** La salida es corregirlo, y al
  corregirlo vuelve solo a «sin controlar». La regla está en
  `ValidacionPagoService::validar()`, no solo en la pantalla.
- **Los depósitos se cargan, se corrigen y se quitan desde la FICHA**, no solo
  desde «Editar trámite». Al enviar se congelan los PAPELES del expediente, no el
  dinero.
- **Al APROBAR se cierra el dinero.** Un trámite aprobado ya no admite depósitos
  nuevos ni cambios en los que tiene. `permitePagos()` aceptaba aprobado con el
  motivo «puede terminarse de cobrar después», y eso dejó de poder pasar cuando
  aprobar pasó a exigir el monto cubierto. Lo único que habilitaba era cargar
  plata de más sobre un carnet ya emitido y cambiar el detalle de un recibo ya
  entregado.
- **Quitar se habilita exactamente cuando corregir.** Estuvo restringido al
  borrador por un argumento que no se sostiene —«el recibo ya salió»—, y corregir
  ya cambia el recibo igual: se arma al vuelo, así que bajar un monto de 110 a 30
  mueve el papel entregado tanto como borrar la fila. Lo que separa a las dos es
  el PERMISO (`pagos.eliminar`, de administración) y el motivo por escrito. La
  regla vieja además dejaba sin salida una boleta cargada DOS VECES sobre un
  expediente ya presentado: corregirla no sirve y quitarla estaba prohibido.
- **Un trámite RECHAZADO se puede REABRIR** y vuelve a PENDIENTE con sus
  depósitos y su historial. Antes había que presentar uno nuevo, y eso dejaba la
  plata colgada del trámite muerto: los pagos cuelgan del trámite por
  `pagable_id` y no se trasladan solos.

**Lo que hay que tener presente en el uso diario:**

- **Al reenviar un expediente reabierto, el recibo NO cambia de número ni de
  fecha.** `fecha_revision` se escribe solo si está en NULL. Lo que sí cambia es
  su DETALLE, porque el comprobante se arma al vuelo con los depósitos que hay
  —ver `ReciboTramiteService::detalle()`—: si se corrigió un monto o se sumó una
  boleta, una reimpresión no dice lo mismo que el papel entregado. Es el costo
  conocido de no tener tabla `recibos`, y ahora se paga más seguido.
- **Reabrir se niega si el carnet ya tiene otro expediente abierto.** Mientras
  estaba rechazado se pudo haber presentado uno nuevo; dos abiertos harían que el
  beneficiario pague dos veces por una habilitación.

---

## Corregir y quitar depósitos: hecho, y con eso cerró el circuito de la observación

Desde el **17/09/2026** una boleta cargada mal se corrige desde «Editar
trámite», con el botón «Corregir» de cada depósito. Era lo único que faltaba
para que «observar» sirviera de algo: quien revisa marcaba que el monto no
cuadraba y del otro lado no había dónde arreglarlo. Ver
[modulos/PAGOS.md](modulos/PAGOS.md).

**Lo que cambia en el uso diario, y hay que saberlo:**

- Un depósito **ya validado no se puede corregir**. Para cambiarlo, quien revisa
  tiene que observarlo primero. Es deliberado: validar es una firma con nombre y
  hora, y no puede quedar puesta sobre un número que cambió después.
- **Un trámite ya no se envía a revisión sin el costo cubierto.** Antes se podía,
  y el RECIBO OFICIAL salía igual — un comprobante por dinero que no había
  entrado. El aviso de la ficha dice cuánto falta.

El mismo día se agregó **quitar un depósito**, para el caso en que corregir no
alcanza: la misma boleta cargada dos veces, o la de otra persona pegada en el
expediente equivocado.

- Permiso propio, **`pagos.eliminar`**, del grupo de administración. Hubo que
  correr `php artisan db:seed --class=RolPermisoSeeder`.
- **Solo mientras el expediente sea un BORRADOR.** Una vez enviado ya salió el
  recibo oficial: ahí se puede corregir, pero no quitar. La pantalla lo dice.
- Motivo obligatorio y casilla de confirmación, como al eliminar un expediente.
  La boleta se borra del disco con la fila.

**Esto NO es la «anulación de pagos» que el sistema no tiene**, y la diferencia
importa: anular dejaría la fila a la vista con un estado, invitando a sumarla por
error. Acá la fila se va y queda la línea de `auditorias`.

- [ ] **Falta corregir y quitar depósitos de FAENAS y GUÍAS.** Las reglas del
      modelo ya valen para los tres —`admiteCorreccion()` y
      `admiteEliminacion()` contemplan el permiso anulado— pero los botones solo
      están en «Editar trámite». Las fichas de faena y guía todavía no muestran
      ninguno — va junto con el alta de pagos de esos dos, que sigue pendiente
      más abajo.

### Resuelto de paso: el alta de depósito no pedía la fecha

El formulario «Registrar un depósito» de «Editar trámite» tenía tres campos y
ninguno era la fecha, así que **todo depósito cargado ahí quedaba con la fecha de
hoy**. Una boleta del viernes registrada el lunes quedaba fechada el lunes, y esa
fecha es justo por donde se cruza el pago contra el extracto del banco. El
formulario del alta del trámite sí la pedía desde el principio, así que el mismo
dato se cargaba de dos maneras según por dónde entrara.

### Resuelto de paso: al eliminar un expediente, sus boletas quedaban en el disco

`SolicitudCarnetService::rutasDeAdjuntos()` juntaba las boletas con
`pluck('comprobante')`, y esa columna **no existe** —es `urlFile`; `comprobante_url`
es el accesor con la dirección completa—. `pluck()` sobre un nombre equivocado no
falla: devuelve una lista de nulls. Así que cada expediente eliminado dejaba
todas sus boletas tiradas en el disco, para siempre y sin ningún error.

### Resuelto de paso: la fecha del depósito se mostraba un día antes

`fecha_pago` viajaba como INSTANTE (`2026-09-17T00:00:00+00:00`) y el navegador
la pasaba a horario local: en Bolivia —UTC-4— eso es el 16 a las 20:00, así que
**un depósito del 17 se veía como 16/09**, en la ficha y en el libro de caja. El
operador tipeaba una fecha y la pantalla le contestaba otra. Ahora viaja como
fecha suelta (`toDateString()`), que es lo que realmente es.

---

## Control de depósitos: hecho, con una consecuencia que hay que saber

Desde el **16/09/2026** cada depósito se valida u observa, y queda escrito quién
y cuándo. Ver [modulos/PAGOS.md](modulos/PAGOS.md).

**LO QUE CAMBIA EN EL USO DIARIO:** un trámite ya no se puede aprobar hasta que
alguien haya controlado TODAS sus boletas.

La separación de funciones —que quien carga no valide— empezó siendo una regla
fija y se convirtió en **configuración** el mismo día: encendida, con un solo
usuario dejaba el circuito trabado. Hoy arranca APAGADA.

- [ ] **Encender `pagos.revisor_distinto`** el día que haya un segundo usuario.
      Es el control que corresponde cuando hay dos personas.
- [ ] Para eso hace falta crear ese usuario, y **no hay pantalla de usuarios
      todavía**: se crea por consola (`php artisan tinker`).
- [ ] Tampoco hay pantalla de Configuración, así que el interruptor se cambia
      por consola: `App\Models\Configuracion::guardar('pagos.revisor_distinto', '1')`.



---

## Faenas y guías: la base está, las pantallas no

El **16/09/2026** se crearon las tablas, los enums y los modelos de los permisos
operativos, y `pagos` pasó a ser polimórfica. Lo que quedó escrito y probado:

| Capa | Estado |
| --- | --- |
| Migraciones `faenas`, `guias`, `guia_detalles`, `pagos` polimórfica | Escritas y probadas desde cero. **Consolidadas**: no hay migraciones `add_*`, las tablas nacen con su forma final |
| Enums `EstadoPermiso`, `TipoTransporte`, `CondicionProducto` | Hechos |
| Modelos `Faena`, `Guia`, `GuiaDetalle` + relaciones | Hechos y probados a mano |
| `Carnet::faenas()` / `guias()` / `puedeEmitirFaenas()` / `puedeEmitirGuias()` | Hechos |
| `rubros.emite_faenas` / `emite_guias` + `RubroSeeder` | Sembrados |
| Libro de caja mostrando los tres conceptos | Hecho |

El **16/09/2026**, más tarde, se agregó la interfaz completa: servicios,
controladores, rutas, permisos, formularios y fichas. **El módulo se puede usar
en ventanilla.**

| Capa | Estado |
| --- | --- |
| `FaenaService` / `GuiaService` — las reglas de emisión y anulación | Hechos |
| `PermisoOperativoException` — los mensajes de mostrador | Hecha |
| Controladores, rutas y permisos (`faenas.*`, `guias.*`) | Hechos |
| `GuardarFaenaRequest` / `GuardarGuiaRequest` | Hechos |
| Listado, formulario y ficha de cada uno + ítem en el menú | Hechos |
| Buscador de carnets filtrado por permiso | Hecho |
| Las faenas y guías en la ficha del carnet, con su botón de alta | Hecho |

**Lo que sigue faltando:**

- [ ] **El ALTA DE PAGOS de faenas y guías.** `PagoTramiteService` solo sabe de
      trámites, y su nombre lo dice. Las fichas muestran «Sin depósitos
      registrados» y no ofrecen ningún botón. **Es lo más urgente**: hoy los dos
      permisos se emiten pero no se puede registrar contra ellos el depósito, así
      que el saldo de una faena queda siempre en deuda.
- [ ] **Los PDF** de la faena y de la guía, calcando los formularios de papel.
      Mientras tanto se siguen llenando a mano y el sistema solo los registra.
- [ ] Un reporte por especie y por período, que es para lo que `guia_detalles`
      es una tabla aparte.
- [ ] `php artisan db:seed --class=RolPermisoSeeder` hay que correrlo para que
      los seis permisos nuevos existan en la base — o hacer `migrate:fresh --seed`.

### Un detalle que va a doler si no se resuelve antes de cargar mucho

`guia_detalles.especie` es **texto libre**. Es la decisión correcta hoy —no
existe un padrón escrito de las especies del Beni, y una lista cerrada
incompleta impediría emitir la guía— pero significa que «Surubí», «surubi» y
«SURUBI» van a convivir en la columna por la que después se filtra. El scope
`deEspecie()` compara con `ILIKE` para tapar lo peor. Cuando la unidad tenga la
lista oficial, esto pasa a `especie_id` con una migración que mapee lo cargado,
y cuantas menos filas haya ese día, mejor.

Estado al **15 de septiembre de 2026**, después de agregar la impresión del carnet.

---

## Lo que ya funciona

| Módulo | Estado | Dónde mirar |
| --- | --- | --- |
| Acceso y bitácora | Completo | `AuthenticatedSessionController`, tabla `accesos` |
| Panel principal | Completo | `DashboardController` |
| **Beneficiarios** | Completo — es la plantilla del sistema | `BeneficiarioController` |
| **Trámites** | Completo: solicitud → aprobación → impresión → entrega | `SolicitudCarnetService` |
| **Pagos** | Completo: 1 a N depósitos por trámite | `PagoTramiteService` |
| **Recibos** | Completo: el talonario del SEDAG en PDF | `ReciboTramiteService` |
| **Carnets** | Completo: consulta, suspensión, anulación, **impresión del plástico**. Uno por persona, rubro y gestión | `CarnetController`, `CarnetImpresionController` |
| **Rubros** | Completo: catálogo con tarifa vigente | `RubroController` |
| Verificación pública | Completo, con firma de validación | `VerificacionController` |

---

## Módulo 1 — Reportes

Aparece en gris en el menú. No existe ni la ruta ni el controlador.

**Qué debería tener:**

- Recaudación por rubro y por periodo, exportable a Excel.
- Padrón de carnets vigentes de una gestión, para imprimir.
- Trámites rechazados con su motivo: sirve para detectar qué requisito falla más
  seguido y corregir el instructivo de ventanilla.
- Carnets suspendidos, por actividad.

**Por dónde empezar:** copiar el patrón de `BeneficiarioController::index()`
—filtros + `Paginacion` + `through()`— y agregar una acción de exportación con
`maatwebsite/excel`, que ya está instalado.

---

## Módulo 2 — Configuración

Aparece en gris en el menú. La tabla `configuraciones` existe y está sembrada;
falta la pantalla para editarla.

**Qué debería tener:**

- Editar los valores de la tabla `configuraciones` agrupados por `grupo`.
- Subir el logo y el escudo (son del tipo `archivo`).
- Alta y edición de usuarios: `GuardarUsuarioRequest` ya está escrito y
  comentado, pero **no tiene ruta ni controlador todavía**.

  > Al construirlo, ojo con una regla que está escrita y APAGADA: el correo del
  > funcionario puede exigirse que termine en el dominio de la Gobernación. La
  > enciende `jichi.dominio_institucional`, y su variable **se sacó del `.env` y
  > del `.env.example` el 22/09/2026** justamente porque el módulo no existe.
  > Para encenderla se agrega al `.env`, sin arroba:
  > `JICHI_DOMINIO_INSTITUCIONAL=beniautonomo.gob.bo`.

---

## Problemas conocidos que siguen abiertos

### 1. ~~El carnet no se imprime en PDF~~ — RESUELTO

Se hizo: `GET /panel/carnets/{carnet}/imprimir` dibuja el plástico en CR80
(243 × 153 pt), una sola carilla, **calcando la cédula de papel**. La vista
previa del panel —`components/panel/tramites/vista-previa-carnet.tsx`, el
recuadro «ASÍ VA A SALIR EL CARNET» del paso 3— muestra ese mismo molde, así que
lo que el operador ve mientras carga el trámite es lo que sale impreso. Ver
[modulos/CARNETS.md](modulos/CARNETS.md).

Tres cosas que este punto daba por sentadas y no eran así:

- **`simplesoftwareio/simple-qrcode` no sirve en este servidor.** Su salida PNG
  exige la extensión `imagick`, que no está instalada (`php -m` lista `gd`). El
  QR lo arma `App\Support\CodigoQr`, con la matriz de BaconQrCode —la librería
  que ese paquete trae adentro— pintada con `gd`. El paquete queda instalado y
  sin usar.
- **La dirección del QR ya no la arma `CarnetController`**, sino
  `Carnet::urlVerificacion()`: la necesitan la ficha del panel y la impresión, y
  escrita dos veces un día dirían cosas distintas.
- **Imprimir no marca impreso.** `tramites.fecha_generacion` la sigue escribiendo
  `PATCH /tramites/{tramite}/generar`, que es otro botón. Abrir la vista previa
  no es haber sacado el plástico, y si esta ruta marcara alcanzaría con que el
  navegador precargara el enlace.

**EL CARNET NO LLEVA QR: se sacó a pedido.** Y conviene tenerlo presente,
porque es lo que dejó a la credencial sin poder verificarse desde la calle: la
pantalla pública y la firma de validación siguen existiendo, pero quien tiene el
plástico en la mano ya no tiene forma de llegar a ellas. Es la misma limitación
que tenía la cédula de papel.

`App\Support\CodigoQr` queda **escrito y sin usar**, igual que quedó
`CorrelativoService` en su momento: el día que el QR vuelva, la parte difícil ya
está resuelta. Lo que hay que saber es que `simplesoftwareio/simple-qrcode` —el
paquete instalado justamente para esto— NO sirve en este servidor: su salida PNG
exige `imagick`, que no está.

### 2. Nadie marca los carnets como vencidos

`EstadoCarnet::Vencido` nunca se escribe solo. Falta el comando programado que
recorra los carnets de gestiones cerradas y actualice la columna.

**Mientras tanto el sistema no miente**, porque `Carnet::estaVigente()` compara
además contra `fecha_vencimiento`. Lo que sí queda mal es el filtro por estado
del listado, que muestra como «vigentes» carnets del año pasado hasta que el
comando exista.

### 3. ~~`StorageController` devuelve URL completa cuando el disco es s3~~ — RESUELTO

Se hizo lo que este mismo punto proponía: **`StorageController` devuelve siempre
la ruta, y la URL se arma al leer** en `Archivos::url()`, con el disco que el
sistema tenga configurado en ese momento.

Se descubrió porque con `FILESYSTEM_DISK=s3` los adjuntos abrían en
`http://jichi.test/storage/...`: `Archivos::url()` estaba clavado en
`disk('public')` e ignoraba el disco activo.

Con el cambio se arreglan tres cosas de una:

- **Se pueden borrar.** Con la ruta se borra del disco activo; desde una URL no
  había forma de volver a la clave del objeto.
- **El dominio deja de estar congelado.** Cambiar de bucket, endpoint o poner un
  CDN es cambiar el `.env`; antes había que reescribir filas.
- **Una sola forma en la columna.** Ya no conviven rutas y direcciones.

`Archivos::url()` conserva la rama que devuelve tal cual lo que empieza con
`http`, por las filas viejas. Se puede sacar el día que no queden.

También se retiró `jichi.archivos.prefijo_s3`: el disco ya lleva
`'root' => env('AWS_ROOT')` y Flysystem lo antepone solo. Tenerlo en los dos
lados duplicaba la carpeta —`dev/dev/tramites/...`—.

### 3b. El enlace `public/storage` apuntaba a OTRO PROYECTO

`public/storage` era un enlace a `surubiNet/storage/app/public`, no a `jichi`.
Un `storage:link` mal hecho o heredado de otro proyecto. Se rehízo.

Con el disco en s3 casi no se notaba; el día que se pase a disco local, habría
servido los archivos del proyecto equivocado.

### 3c. `APP_URL` no coincide con cómo se accede — ABIERTO

`.env` dice `APP_URL=http://jichi.test`, pero el sistema se usa en
`http://127.0.0.1:8000`, y `jichi.test` no resuelve.

Con el disco en s3 ya no afecta a los adjuntos, pero `APP_URL` la usan las rutas
absolutas, Ziggy y cualquier enlace que se mande por correo. **Hay que ponerlo en
la dirección real de cada entorno** —o crear el host `jichi.test` en Laragon—.

No se cambió desde el código: es configuración del entorno de cada máquina.

### 4. Solo hay un rol

`RolSistema` tiene un único `case`: `administrador`, con todos los permisos. Es
una decisión, no un olvido —ver el comentario del enum—, pero significa que hoy
**cualquier usuario del sistema puede aprobar sus propios trámites**.

Los permisos ya están repartidos por bloque dentro del enum (`$lectura`,
`$operacion`, `$supervision`, `$administracion`), así que agregar el rol de
ventanilla es escribir una línea:

```php
self::Operador => [...$lectura, ...$operacion],
```

Y las rutas ya lo respetan, porque cada una declara su `permiso:`.

### 5. `GuardarUsuarioRequestTest` se eliminó

Probaba escenarios de cuatro roles —degradar al último administrador, por
ejemplo— que hoy no pueden ocurrir. Se borró al dejar un solo rol. Cuando el
módulo de Usuarios se construya, hay que volver a escribirlo como pruebas HTTP
contra sus rutas.

### 6. ~~`DemoSeeder` no pasa por el servicio~~ — RESUELTO

Ya no escribe carnets, trámites ni pagos: siembra **solo el padrón** —treinta
beneficiarios— y el circuito se carga desde la pantalla. El motivo por el que la
copia era mala se confirmó en el cambio a «un carnet por rubro»: ese guion
escrito a mano habría habido que reescribirlo entero, y ninguna prueba habría
avisado si quedaba mal.

Lo que sí hace falta sembrado es el padrón: tipear treinta personas para probar
el buscador o la paginación no prueba nada y cuesta una tarde.

### 7. No hay edición ni anulación de pagos

Una boleta cargada con el monto equivocado no se puede corregir desde la
pantalla. La tabla no tiene `estado` ni `deleted_at` a propósito —un pago
«anulado» que sigue en la lista invita a sumarlo por error— pero falta la
pantalla de edición, que sí correspondería: el trait `Auditable` ya guardaría el
valor anterior.

### 8. ~~Un rubro suspendido no se puede volver a tramitar~~ — RESUELTO

El comportamiento sigue siendo el mismo —un rubro suspendido bloquea, porque la
habilitación existe y volver a tramitarla sería cobrar dos veces— pero ahora el
mensaje lo explica y dice qué hacer: `SolicitudInvalidaException::rubroSuspendido()`.

Además el formulario ya no lo ofrece: el selector de rubros deshabilita los que
el carnet tiene, y la tarjeta de situación los muestra con su estado.

### 9. ~~`CorrelativoService` quedó sin usar~~ — RESUELTO

Se lo había conservado con el argumento de que «el día que haga falta un número
correlativo —de recibo, de resolución— ya está escrito y probado contra
concurrencia». Ese día llegó: el **módulo de Recibos** lo usa para numerar el
talonario, serie `RECIBO`, reiniciada cada gestión.

Se le extrajo `siguienteNumero()`, que devuelve el entero crudo — el recibo
necesita el número pelado (`0016`) y guardarlo como entero para poder ordenarlo,
cosa que con el código formateado no se podía. El bloqueo sigue viviendo en un
solo lugar.

### 11. El recibo no se puede anular

En el talonario de papel se anulaba escribiendo «ANULADO» sobre las tres copias
y archivándolas. La versión digital no tiene el equivalente: una vez emitido, el
recibo queda.

No es urgente —el número nunca se reusa y el rastro está completo— pero el día
que se cobre mal y haya que dejar constancia, hace falta. Está sin definir con la
unidad qué debería pasar con la plata en ese caso, y por eso no se construyó
adivinando.

### 12. No hay libro de recibos

**OJO: este pendiente cambió de forma.** La tabla `recibos` se retiró, así que
ya no hay nada que listar directamente; un libro de recibos hoy se arma
recorriendo los trámites con `fecha_revision`. El índice que estaba preparado
para el listado, pero no existe la pantalla. Contabilidad lo va a pedir junto con
Reportes: es el equivalente a revisar el talonario para cuadrar contra caja.

### 13. Las boletas de pago no se borran con el trámite — BUG

`SolicitudCarnetService::rutasDeAdjuntos()` hace
`$tramite->pagos->pluck('comprobante')`, pero la columna de `pagos` se llama
**`urlFile`**; `comprobante` no existe como atributo —el accesor es
`comprobante_url`— así que devuelve `null` por cada pago y `descartar()` los
filtra en silencio.

**Efecto:** al borrar un expediente, sus dos adjuntos propios sí se borran, pero
**cada boleta escaneada queda huérfana en el disco para siempre**. Nada se rompe
y nadie se entera.

Se arregla cambiando `pluck('comprobante')` por `pluck('urlFile')`. Las pruebas
de borrado verifican filas, no disco, por eso no lo detectaron.

### 14. Dos N+1 silenciosos — BUG

Los dos calculan en PHP lo que el comentario dice que se calcula en SQL:

- **`DashboardController::resumenDelDia()`**, en `listos_para_aprobar`: hace
  `Tramite::abiertos()->get()` sin `withSum` y después llama a `estaPagado()` por
  fila, o sea una consulta agregada por trámite abierto. Es la pantalla de
  entrada del sistema. Se arregla agregando `->withSum('pagos', 'monto')` antes
  del `get()` — el propio archivo ya lo hace bien en `ultimosTramites()`.

- **`Beneficiario::deudaTotal()`**: su docblock dice «la resta se hace en SQL y no
  trayendo las filas a PHP», y el código hace exactamente lo contrario. Lo llama
  `BeneficiarioController::show()`.

### 15. `CarnetImpresionController::filas()` es código muerto que además no compila

Nadie la llama —el PDF sale de `datos()`— y adentro invoca `$this->celda()`, un
método **que no existe en la clase**. Es el sobrante de la versión 2 del diseño,
la que tenía siete renglones con GESTIÓN y VENCE compartiendo uno. Si alguien la
llamara, error fatal; y como nada la llama, nada lo avisa.

Lo que la vuelve una trampa y no solo basura es su docblock: encabeza «LOS SIETE
RENGLONES DE LA TARJETA» y explica el reparto de GESTIÓN + VENCE. Hoy la tarjeta
tiene **seis** renglones y el segundo par es GESTIÓN, sin VENCE. Quien entre a
tocar los renglones de la tarjeta encuentra primero ese comentario, que describe
una tarjeta que no existe.

Se arregla borrando el método y su docblock. Se dejó para no mezclarlo con el
cambio de diseño del 15/09/2026.

### 16. ~~El tablero se corría de costado en el celular~~ — RESUELTO

La tabla de últimos trámites mide 675 px —seis columnas— y vivía dentro de un
elemento de grilla. Un elemento de grilla arranca con `min-width: auto`, que le
prohíbe encogerse por debajo de su contenido, así que la tarjeta se estiraba a
675 px aunque la pantalla midiera 375. El `overflow-x-auto` que la tabla ya tenía
no servía de nada: el que quedaba desplazable era **el documento entero**, y en
un celular había que correr de costado la pantalla completa —menú, encabezado y
todo— para leer una columna.

Se arregló con `min-w-0` en la tarjeta (`tabla-ultimos-tramites.tsx`). Medido en
390, 768 y 1440 px: el documento ya no desborda en ninguno, y la tabla se
desplaza sola dentro de su tarjeta.

No se notaba porque el tablero se prueba en el escritorio, donde sobra ancho.
Los otros cinco listados se revisaron y **no** tienen el problema: son los únicos
que no están dentro de una grilla. La trampa quedó anotada en CLAUDE.md.

### 17. El cambio a «un carnet por rubro» dejó dos cosas por decidir

El modelo nuevo funciona y está verificado, pero abrió dos preguntas que no
corresponde resolver sin la unidad:

**a) Un carnet ANULADO bloquea el rubro por el resto del año.** El índice único
`(beneficiario, rubro, gestión)` no distingue estados, así que anular el carnet
de Pescador de alguien le impide sacar otro de Pescador hasta enero. Antes
pasaba lo mismo pero con TODO el carnet, así que no es una regresión — y es
coherente con que anular sea una sanción. Pero si la unidad quiere permitir
reemitir tras una anulación, hace falta decidir cómo: un índice parcial
`WHERE estado != 'anulado'` lo permitiría, al precio de que dos carnets del mismo
rubro convivan en la misma gestión.

**b) El trámite de ACTUALIZACIÓN no tiene tarifa propia.** Copia
`rubros.costo`, igual que una emisión inicial, así que corregir un cupo cuesta lo
mismo que emitir el carnet. Puede ser lo querido o no; hoy nadie lo definió.

### 10. NO HAY PRUEBAS AUTOMÁTICAS — de nada

El **14 de septiembre de 2026** se vació `tests/Feature/` por pedido del
responsable del proyecto. Eran **143 pruebas** y cubrían el backend entero.
Frontend nunca hubo.

**Qué dejó de estar cubierto**, que es lo que importa de este punto:

| Regla | Qué pasa si se rompe y nadie avisa |
| --- | --- |
| Un carnet por persona y gestión | Se emiten dos documentos a la misma persona |
| No aprobar sin cobrar | Un rubro queda habilitado sin que entre la plata |
| El recibo conserva su número al reimprimir | Contabilidad recibe dos comprobantes por un pago |
| El recibo queda congelado | Una reimpresión dice algo distinto al papel entregado |
| Todo archivo pasa por `StorageController` | Alguien sube sin el tope de 3 MB ni el nombre aleatorio |
| Los saltos de estado válidos | Un expediente resuelto vuelve atrás |

El más difícil de reemplazar es el último de la tabla: `SubidaArchivosTest`
**leía el código fuente** y fallaba si aparecía un `->store()` nuevo. Esa clase
de regla no se detecta mirando la pantalla, porque no es un error de hoy sino un
atajo de mañana.

**Mientras tanto, cada cambio se verifica abriendo la pantalla y probando el caso
a mano.** `npx tsc --noEmit` y `pint` siguen revisando tipos y formato; de la
lógica de negocio no dicen nada.

**El andamiaje quedó en su lugar** —`phpunit.xml`, `tests/TestCase.php` y las
dependencias de PHPUnit—, así que volver a escribir una prueba es crear un solo
archivo, sin instalar nada.

**Por dónde volver a empezar, si algún día se retoma.** En este orden, que es el
de mayor daño posible:

1. `SolicitudCarnetTest` — la Regla A es la que sostiene todo el dominio.
2. `PagoTramiteTest` — es dinero.
3. `SubidaArchivosTest` — es la única forma de proteger el embudo de archivos.
4. `ReciboTramiteTest` — el recibo es un papel numerado que se entrega.

Y del frontend, lo más barato: `resources/js/lib/utils.ts` —`fecha()`,
`edadEnAnios()`, `bs()`— son funciones puras y se probarían en veinte líneas con
vitest. `fecha()` ya costó un error real: mostraba todas las fechas un día antes
por la zona horaria, y se descubrió mirando la pantalla.

---

## Orden sugerido

1. **Los dos bugs (13 y 14)** — son de media hora entre los dos y uno pierde
   archivos en silencio.
2. El comando de vencimiento de carnets (problema 2) — es de una tarde y arregla
   un dato que ya se muestra mal.
3. ~~El PDF del carnet (problema 1)~~ — hecho el 15/09/2026.
4. Reportes — la unidad de recaudación los pide todos los meses. Va junto con el
   libro de recibos (problema 12).
5. Usuarios y roles (problema 4) — antes de poner el sistema en manos de varias
   personas.
6. Configuración.
