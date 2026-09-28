# Qué falta y qué sigue abierto

> Reescrito el 27/09/2026: se sacó todo lo que hablaba del modelo anterior
> (trámites, rubros, el recibo armado al vuelo) y lo ya resuelto. Si hace falta,
> está en el historial de git.

---

## 🔴 Separación de funciones: hoy la misma persona carga y aprueba

- **Hay un solo rol, `administrador`, con todos los permisos.** Es una decisión
  —ver el comentario de `RolSistema`—, pero significa que cualquier usuario
  aprueba lo que él mismo cargó. Los permisos ya están repartidos por bloque
  (`$lectura`, `$operacion`, `$supervision`, `$administracion`) y cada ruta
  declara su `permiso:`, así que el rol de ventanilla es una línea:

  ```php
  self::Operador => [...$lectura, ...$operacion],
  ```

- **`pagos.revisor_distinto` NO ESTÁ IMPLEMENTADO** (encontrado el 27/09/2026).
  La clave existe en `ConfiguracionSeeder` y apagada por defecto, pero ningún
  código la lee: `ControlarPagoService::validar()` deja validar a quien cargó la
  boleta aunque se encienda. Hay que agregar la comprobación antes de encenderla,
  y encenderla recién cuando haya un segundo usuario —con uno solo el circuito
  queda trabado—.
- **No hay pantalla de usuarios**: se crean por consola (`php artisan tinker`).
  El borrador `GuardarUsuarioRequest` se quitó el 28/09/2026 por no tener ruta
  ni controlador; está en el historial de git (commit `8511537`).

## 🔴 Nada vence solo

No hay comando programado que pase a `vencido` ni tarea en el scheduler. Lo que
eso deja mal:

- **Una faena pendiente abandonada reserva sus kilos para siempre**, hasta que
  alguien la elimine a mano. En modo estricto eso traba al pescador.
- Los carnets, autorizaciones, faenas y guías fuera de fecha siguen diciendo
  `aprobado` en la base y en el **filtro por estado** de los listados.

Lo que sí está bien: la vigencia (`estaVigente()`, scopes `vigentes()` y
`enCurso()`) compara además contra la fecha, así que ninguna regla se equivoca.

## 🟠 Datos de desarrollo con 4 horas de corrimiento

El 27/09/2026 la zona horaria pasó de UTC a `America/La_Paz` (ver «Trampas» en
CLAUDE.md). Lo cargado antes quedó con `created_at` en hora UTC y ahora se lee 4
horas más tarde: un cobro de las 21:00 aparece a la 01:00 del día siguiente. Se
resuelve rearmando la base de desarrollo (`migrate:fresh --seed`).

## 🟠 Quedan notas viejas en NOTAS-CODIGO.md

El 27/09/2026 se unieron las secciones repetidas, se sacaron las de archivos
borrados y las que contradecían el circuito actual. **Siguen quedando notas
escritas contra el modelo anterior** —hablan de trámites, rubros o
`codigo_carnet`—, sobre todo en `ConceptoRecibo`, `CarnetImpresionController`,
`VerificacionController`, `carnet-pescador.blade.php` y las rutas. Se dejaron
porque tienen partes que siguen valiendo y separarlas pide leerlas una por una.
Ante una contradicción, mandan REGLAS-NEGOCIO.md y MER.md.

## 🟠 RECIBOS.md y PAGOS.md siguen escritos contra el modelo anterior

`docs/modulos/RECIBOS.md` habla de `tramite_id`, `ReciboTramiteService` y la
migración `2026_09_14_*`, que ya no existen; `PAGOS.md` habla de la «ficha del
trámite». Hay que reescribirlos sobre `recibos`, `CobrarService` y
`ControlarPagoService`. Mientras tanto, ante una duda, mandan MER.md y
ARQUITECTURA.md.

## 🟠 No hay pruebas automáticas

Se retiraron el 27/09/2026 a pedido del responsable (204 de PHP y 13 de React;
están en el commit `7dc0ac6`). Todo cambio se verifica a mano en el navegador.
Si se retoman, lo que más daño evita es, en este orden: el circuito de cobro y
aprobación, la reserva de kilos de la faena y que todo archivo pase por
`StorageController`.

## 🟠 No se puede quitar un depósito

Un depósito cargado mal se **corrige** (`POST /panel/pagos/{pago}/corregir`),
pero no hay ruta para **quitarlo**: la misma boleta cargada dos veces, o la de
otra persona en el trámite equivocado, no tiene salida desde la pantalla.

## 🟠 El recibo no se puede anular

En el talonario de papel se anulaba escribiendo «ANULADO» sobre las tres copias.
Una vez emitido, el recibo digital queda. Está sin definir con la unidad qué
pasa con la plata en ese caso. Ojo al construirlo: `pagos.recibo_id` es CASCADE
y eso no se dispara con una baja lógica (ver «Trampas» en CLAUDE.md).

---

## ✅ Módulo del pescador — cerrado el 27/09/2026

Autorización de Pesca para Aprovechamiento Pesquero, carnet de pescador y
permiso de faena: circuito completo, cobro y control de boletas, impresión de
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
—filtros + `Paginacion` + `through()`— y exportar con `maatwebsite/excel`, que
ya está instalado. El libro de recibos ya existe (`/panel/recibos`).

### Configuración

Aparece en gris en el menú. La tabla `configuraciones` existe y está sembrada;
falta la pantalla para editarla agrupada por `grupo`, subir el logo y el escudo
(tipo `archivo`) y el alta de usuarios (ver arriba).

> Al construir usuarios, ojo con una regla escrita y APAGADA: el correo del
> funcionario puede exigirse que termine en el dominio de la Gobernación. La
> enciende `jichi.dominio_institucional`; para encenderla se agrega al `.env`,
> sin arroba: `JICHI_DOMINIO_INSTITUCIONAL=beniautonomo.gob.bo`.

---

## Orden sugerido

1. Separación de funciones: `revisor_distinto`, rol de ventanilla y pantalla
   mínima de usuarios — antes de poner el sistema en manos de varias personas.
2. El comando diario de vencimiento — libera los kilos reservados por faenas
   abandonadas.
3. Confirmar los catálogos contra la resolución (los precios ya son plata).
4. Reportes.
5. Configuración.
