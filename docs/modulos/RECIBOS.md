> # ⚠️ DESACTUALIZADO desde el 18/09/2026
>
> El núcleo de datos se rehízo desde cero: ya no existen `rubros`,
> `tramites`, `faenas`, `guias` ni `guia_detalles`, y `beneficiarios`,
> `carnets` y `pagos` cambiaron de columnas. Lo de abajo describe el modelo
> ANTERIOR: sirve para entender el código del panel, que todavía está escrito
> contra él, NO para entender el esquema.
>
> El esquema vigente está en las migraciones `database/migrations/2026_09_18_*`
> y explicado en [docs/sesiones/09-2026/2026-09-18.md](../sesiones/09-2026/2026-09-18.md).

---

# Módulo Recibos — el RECIBO OFICIAL del SEDAG

Reemplaza al talonario verde de tres copias que la unidad venía llenando a mano.
Es el papel que el pescador se lleva del mostrador cuando entrega sus documentos
y paga.

> ## ⚠️ NO HAY TABLA `recibos`
>
> La hubo, y buena parte de este documento fue escrita cuando existía. Se retiró
> a pedido del responsable: toda la información del comprobante ya vive en
> `beneficiarios`, `carnets`, `rubros`, `tramites` y `pagos`, así que duplicarla
> era mantener el mismo dato en dos lugares.
>
> **Hoy el recibo se ARMA al vuelo**, cada vez que alguien lo imprime, en
> `ReciboTramiteService::armar()`. El objeto que devuelve es
> `App\Support\ReciboArmado`, de solo lectura.
>
> | Dato | De dónde sale ahora |
> | --- | --- |
> | Número | El **id del trámite**, con ceros: `0016` |
> | Fecha | `tramites.fecha_revision` — el día en que se cobró de verdad |
> | Nombre y CI | `beneficiarios`, en vivo |
> | Concepto | `rubros.nombre` + la gestión del carnet |
> | Monto | Suma de `pagos`, o `tramites.monto_requerido` si no hay ninguno |
> | Casilla DESCRIPCIÓN | `ConceptoRecibo::desdeTramite()` |
>
> **LO QUE SE PERDIÓ, que hay que saber antes de tocar el módulo:**
>
> 1. **El recibo ya no es inmutable.** Corregir una tilde del apellido o un
>    dígito de la cédula cambia los comprobantes ya entregados.
> 2. **Borrar el trámite se lleva el recibo.** Antes quedaba huérfano pero
>    legible, con `ON DELETE SET NULL`.
> 3. **La serie tiene huecos.** No todo trámite emite recibo —nace al pasar a EN
>    REVISIÓN— así que el talonario va 0012, 0015, 0016, 0019.
>
> Si algún día Contabilidad exige una serie sin saltos, hay que volver a guardar
> el número: `CorrelativoService` quedó escrito y sin usar justamente para eso.
>
> Lo que sigue abajo describe el diseño del papel, que **no cambió**.

---

## 1. Cuándo nace, y por qué ahí

```
PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
                   ▲
                   │
             acá nace el recibo
```

**Al pasar a EN REVISIÓN. Ni antes ni después.**

El motivo es de mostrador, no de código: ese es el momento en que el pescador ya
entregó los papeles y la plata, y se tiene que ir con un comprobante en la mano
mientras la unidad revisa. Antes no hay nada que respaldar; después ya se fue.

Sin tabla, «nacer» significa otra cosa: el recibo EXISTE desde que el trámite
tiene `fecha_revision`. Lo decide `ReciboTramiteService::corresponde()`, que mira
esa fecha y no el estado — un trámite rechazado conserva su comprobante, porque
la plata entró igual.

Lo dispara `SolicitudCarnetService::tomarParaRevision()`, **dentro de su misma
transacción**. Si la toma para revisión se deshace, el recibo tampoco queda y el
número vuelve al contador —`CorrelativoService` participa de esa transacción—.
Un recibo numerado colgando de un trámite que quedó pendiente sería un papel
entregado que el sistema no puede explicar.

---

## 2. El número

Sale de `CorrelativoService`, serie `RECIBO`, **reiniciada cada año** igual que
el talonario de papel: en enero vuelve a empezar por el 1.

```php
$this->correlativos->siguienteNumero(ReciboTramiteService::SERIE, $gestion);
```

El contador se bloquea con `SELECT ... FOR UPDATE`, así dos ventanillas cobrando
en el mismo segundo nunca reciben el mismo número. Como el bloqueo vive dentro de
la transacción de `tomarParaRevision()`, la fila del contador queda tomada hasta
el commit — es breve y es lo que se quiere.

> `CorrelativoService` existía desde el principio y estaba **sin usar**:
> `PENDIENTES.md` lo había conservado con el argumento de que «el día que haga
> falta un número correlativo —de recibo, de resolución— ya está escrito y
> probado contra concurrencia». Este es ese día.

### Reimprimir da el MISMO número

`ReciboTramiteService::emitir()` es **idempotente**: si el trámite ya tiene
recibo, devuelve el que tiene y no consume otro.

Hace falta porque el papel ya se entregó. Un segundo recibo con otro número por
el mismo pago dejaría a Contabilidad con dos comprobantes que no puede cuadrar, y
al pescador con dos papeles por una sola plata.

La garantía de fondo es el `unique` sobre `recibos.tramite_id`.

---

## 3. Todo lo que dice está congelado

La tabla `recibos` guarda una **copia** del nombre, la cédula, el concepto, el
monto, la forma de pago y el lugar — datos que se podrían leer siguiendo
`tramite_id`.

Se copian igual, y es la decisión central del módulo:

> Un recibo es un papel **numerado que ya se entregó**. Si mañana alguien corrige
> un apellido mal tipeado en la ficha, o la unidad sube la tarifa del rubro por
> ordenanza, el original que el pescador tiene en el bolsillo no cambia — y la
> reimpresión tampoco puede cambiar, o dejaría de coincidir con lo que se entregó
> y con lo que Contabilidad archivó.

Es el mismo criterio de `tramites.monto_requerido`, llevado hasta el final: acá
se congela el documento entero.

Dos pruebas lo fijan:

- `test_corregir_el_beneficiario_no_cambia_un_recibo_ya_emitido`
- `test_subir_la_tarifa_del_rubro_no_cambia_un_recibo_ya_emitido`

---

## 4. El recibo sobrevive al borrado del trámite

`tramite_id` es `nullable` y va con `nullOnDelete()`: si el trámite desaparece,
la fila del recibo **queda**, con su número y su copia de los datos. Borrarlo en
cascada abriría un hueco en la serie numerada sin que nadie pueda explicar
después qué fue el 0016.

> **Hoy ese borrado no debería ocurrir.** El recibo nace al enviar a revisión, y
> desde EN REVISIÓN el expediente ya no se elimina: solo se aprueba o se rechaza
> (`EstadoTramite::permiteEliminacion()` responde solo por PENDIENTE). Cuando se
> diseñó esta tabla sí se podía borrar en revisión, y de ahí viene la columna
> nullable.
>
> Se deja así a propósito: el talonario se rinde ante la contraloría, y un hueco
> en la serie no se explica diciendo que cambió una regla del sistema. Que el
> recibo no dependa de que el trámite exista es más barato que confiar en que esa
> regla no se afloje nunca.

La copia congelada de §3 es lo que hace que esa fila huérfana siga siendo legible
sola.

> **El `unique` sobre una columna nullable es intencional.** En SQL
> `NULL != NULL`, así que los nulos no chocan entre sí: muchos recibos pueden
> quedar huérfanos, pero dos recibos no pueden apuntar al mismo trámite. Es el
> caso **inverso** al de `beneficiarios_ci_unico`, donde esa misma regla del SQL
> obligaba a usar un índice parcial.

---

## 5. Qué se imprime en cada campo

| Campo del papel | De dónde sale |
| --- | --- |
| `N°` (rojo, arriba) | `Recibo::numeroImpreso()` — el correlativo con ceros: `0016` |
| Lugar y Fecha | `recibos.lugar` (de Configuración) + `fecha_emision` |
| DIA \| MES \| AÑO | `Recibo::fechaEnCasilleros()` |
| Nombre y Apellido | Copia de `Beneficiario::nombreCompleto` |
| La suma de … -00/100 | `Recibo::montoEnLetras()` |
| Concepto | `«{rubro} — Carnet gestión {año}»` |
| Depósito Bancario \| Efectivo | `FormaPago` |
| N° (del depósito) | Los `nro_transaccion` de los pagos, separados por coma |
| DESCRIPCIÓN (6 casillas) | `ConceptoRecibo` — se imprimen **las seis**, se marca **Cédulas** |
| IMPORTE A PAGAR Bs. | `Recibo::lineas()` + `ReciboController::importeFormateado()` |
| C.I. | Copia de `Beneficiario::documento_identidad` |

### El monto en letras

`Number::spell($entero, locale: 'es')` de Laravel, que usa la extensión `intl`.

```
80.00  →  «OCHENTA 00/100 BOLIVIANOS»
```

El `-00/100` del papel es la forma clásica de cerrar un importe escrito a mano
para que nadie le agregue centavos después. Se reproduce con los centavos reales.

> Escribir a mano un conversor de número a palabras en castellano son doscientas
> líneas de casos especiales —«veintiuno», «quinientos», «un millón»— que ya
> están resueltas y probadas en `intl`.

### El monto: lo cobrado, no lo que costaba

Un recibo respalda **plata recibida**. Si el pescador pagó en cuotas y todavía
debe, el recibo dice lo que entregó —no el total del trámite— porque si no
estaría firmando que pagó algo que no pagó.

Cuando no hay ningún pago cargado se cae en `monto_requerido`: es el caso de la
plata puesta en el mostrador, que llega a revisión sin boleta y que este mismo
recibo respalda. Ahí se marca **Efectivo**.

### Las seis casillas de DESCRIPCIÓN

```
( ) Permiso por Faena
( ) Solicitud de Importe de Alevines
( ) Autorización de Pesca para Aprovechamiento Pesquero
( ) Guía única de Transporte
( ) Cédulas
( ) Otros
```

**Se imprimen siempre las seis**, aunque el sistema solo cobre una. El recibo
tiene que salir igual al papel: quien lo recibe está acostumbrado a esa lista, y
una versión recortada se lee como si fuera otro documento.

### Siempre se marca «Cédulas»

Las seis casillas son los **servicios que cobra el SEDAG**, cada uno con su
propio trámite:

| Casilla | Qué servicio es |
| --- | --- |
| Permiso por Faena | un permiso puntual de pesca |
| Solicitud de Importe de Alevines | la solicitud de alevines |
| Autorización de Pesca para Aprovechamiento Pesquero | el aprovechamiento pesquero |
| Guía única de Transporte | la guía que acompaña un cargamento |
| **Cédulas** | **la credencial ← esto es lo que emite Jichi** |
| Otros | el resto |

Este sistema emite **carnets**, que en el mostrador se llaman «cédula de
pescador». Cobre lo que cobre —emisión inicial o adición de rubro, Pescador o
Comercializador— lo que el pescador se lleva es su cédula.

Lo resuelve `ConceptoRecibo::desdeTramite()`, que hoy devuelve `Cedulas` fijo.
Recibe el trámite igual —y no ningún parámetro— para que el día que el sistema
maneje otro servicio la firma ya esté lista y solo haya que cambiar el cuerpo.

> ### El error que esto corrigió
>
> La primera versión miraba el **rubro** y marcaba:
>
> ```
> Pescador         ──▶ Permiso por Faena
> Comercializador  ──▶ Guía única de Transporte
> ```
>
> Era confundir **la actividad que el carnet autoriza** con **el papel que se
> está cobrando**. El pescador pagaba su carnet y el recibo declaraba que había
> pagado una guía de transporte — un documento distinto, con otro trámite y otra
> tarifa.
>
> Qué rubro se habilitó sí se lee en el recibo, pero donde corresponde: en el
> renglón **Concepto**, que dice «Comercializador — Carnet gestión 2026».

---

## 6. El PDF

### No se guarda en disco

Se arma al vuelo cada vez que alguien imprime, desde la fila de `recibos`.
Guardarlo no agregaría nada —los datos están congelados, el PDF de mañana sale
idéntico al de hoy— y sí traería el problema de siempre: un archivo más que
limpiar cuando el expediente se borra, y que con el disco en s3 no se puede
borrar.

Por eso `ReciboController` **no pasa por `StorageController`**: no escribe nada.

### La maqueta

`resources/views/documentos/recibo-oficial.blade.php`, un calco del talonario.

**Media carta apaisada: 612 × 396 puntos** (8,5" × 5,5"), fijado en
`ReciboController` y no en la plantilla — es una decisión de impresión, no de
diseño. El marco vive a 10 pt de cada borde, o sea **592 × 376 útiles**, y todas
las coordenadas del Blade son puntos dentro de ese marco.

> Si se cambia el tamaño del papel en el controlador, hay que revisar las
> coordenadas: están calculadas para estos 592 × 376.

**Todo va en `position: absolute`**, y por dos razones:

1. Esto no es una página web que se acomoda al ancho del que mira: es un papel de
   medida fija que tiene que salir **siempre igual**. Con el flujo normal del
   documento, un nombre más largo que otro corre todo lo que viene abajo y dos
   recibos salen distintos.
2. Lo dibuja DomPDF, que no es un navegador.

### Trampas de DomPDF que ya costaron tiempo

| Trampa | Solución aplicada |
| --- | --- |
| No entiende flexbox, grid ni variables CSS | Tablas y `position: absolute` |
| No ejecuta JavaScript | Todo llega resuelto desde PHP |
| `opacity` es poco confiable — según la versión lo ignora y el sello sale a pleno color tapando el texto | La atenuación va **horneada** en `recibo-sello.png` |
| Una ruta `/image/...` se resuelve contra el disco con las restricciones de `chroot`, y en producción termina en un recuadro vacío | Imágenes **embebidas en base64** |
| Embeber los PNG del panel (2,8 MB) daba un PDF de **5,4 MB por recibo** | Copias a medida: `recibo-escudo.png` (36 KB) y `recibo-sello.png` (23 KB) |
| Solo DejaVu Sans trae acentos y «ñ» completos; con Helvetica «PISCÍCOLA» sale partida | `font-family: 'DejaVu Sans'` |
| Blade escapa las entidades HTML de su interpolación: un `&nbsp;` saldría impreso como texto literal `(&nbsp;)` | Un `<span class="hueco">` de ancho fijo |

### La cuadrícula de importes

**Un solo cuadro, con un renglón por cobro y el TOTAL al pie.** Con más de un
depósito el cuadro crece hacia abajo; con uno solo se completa con renglones en
blanco para conservar el alto del talonario
(`ReciboController::RENGLONES_MINIMOS`, hoy 3).

**Dos columnas**: la descripción del cobro y el monto. La fila del pie repite esa
misma división — TOTAL a la izquierda, la cifra a la derecha.

```
        UN COBRO                           DOS COBROS
┌────────────────────────────┐    ┌────────────────────────────┐
│     IMPORTE A PAGAR Bs.    │    │     IMPORTE A PAGAR Bs.    │
├──────────────────┬─────────┤    ├──────────────────┬─────────┤
│ Dep. 6CF39608…   │  120,00 │    │ Dep. 6CF39608…   │   80,00 │
├──────────────────┼─────────┤    ├──────────────────┼─────────┤
│                  │         │    │ Dep. 77120044…   │   40,50 │
├──────────────────┼─────────┤    ├──────────────────┼─────────┤
│                  │         │    │                  │         │
├──────────────────┼─────────┤    ├──────────────────┼─────────┤
│         TOTAL    │  120,00 │    │         TOTAL    │  120,50 │
└──────────────────┴─────────┘    └──────────────────┴─────────┘
```

El monto va alineado a la **derecha**: las unidades quedan una debajo de otra y
la columna se puede sumar de un vistazo. Centrado, cada renglón arranca en un
lugar distinto y deja de leerse como columna.

Punto para los miles y coma para los decimales, como se escribe acá:
`1.250,50`.

Cada renglón lleva el **número de boleta**: es lo que permite cruzarlo contra el
extracto del banco. Sin él, dos depósitos del mismo monto en el mismo día son
indistinguibles.

### La cifra no se parte — se probaron dos formas y fallaron

| Intento | Cómo se veía | Por qué falló |
| --- | --- | --- |
| Un dígito por casillero | `[1][2][0][00]` | Se leía **12000**: el espacio entre celdas rompe el número y la coma decimal desaparece |
| Bolivianos \| centavos | `120 │ 00` | Se leía mejor, pero seguía obligando al ojo a juntar dos cifras para entender una |
| **Una sola celda** | `120,00` | ✅ No hay nada que juntar |

La idea de partirla venía de mirar el formulario **en blanco**: como trae la
columna dividida, parecía que había que repartir el número. Al llenarlo con un
monto de verdad se rompía.

> **Es un comprobante: la única propiedad que importa es que el número se lea de
> una y sin ambigüedad.** Ningún test puede verificar eso — hay que renderizar y
> mirar.

### Los renglones NO quedan congelados — y es la parte que más hay que tener presente

> Este apartado decía lo contrario, porque se escribió cuando existía la tabla
> `recibos` y los renglones se copiaban a una columna `detalle` (JSON). Esa
> tabla se retiró —ver el aviso del principio— y con ella la copia.

Hoy `ReciboTramiteService::detalle()` **lee `pagos` en el momento de imprimir**,
así que el detalle del comprobante refleja los depósitos que hay HOY, no los que
había cuando el pescador se llevó el papel. Lo que se conserva es el **número**
—el id del trámite— y la **fecha** —`tramites.fecha_revision`, que se escribe una
sola vez y no se pisa ni al reenviar un expediente reabierto—.

En la práctica, el detalle cambia cuando:

- se **corrige** el monto de un depósito (110,00 → 30,00);
- se **quita** un depósito del expediente;
- se **agrega** uno mientras el trámite sigue en revisión.

Las tres se pueden hacer desde la ficha, y las tres son necesarias: son la única
forma de arreglar una boleta mal cargada. Ver
[PAGOS.md](PAGOS.md#5-bis-corregir-un-depósito).

Lo que ya NO puede pasar es que el detalle se mueva después de aprobar: desde
que `EstadoTramite::permitePagos()` deja de aceptar depósitos en APROBADO, el
expediente queda quieto cuando se firma. Antes se podían cargar depósitos sobre
un trámite aprobado, y eso cambiaba el recibo de un carnet ya impreso.

**Es el costo conocido de no tener tabla `recibos`.** Si algún día molesta, la
solución no es volver a la tabla entera: alcanza con congelar el detalle en una
columna del trámite al momento de enviar a revisión.

El monto va alineado a la **derecha** contra la línea de los centavos, que es
como se lee una cifra de dinero: centrado, el ojo no encuentra dónde termina. El
separador de miles es el punto, como se escribe acá: `1.250`.

Lo arma `ReciboController::importePartido()`.

> **Antes iba un dígito por casillero, y estuvo mal.** La idea venía de que el
> formulario en blanco trae la columna dividida — pero al llenarlo se leía
> pésimo: `[1][2][0][00]` parece **12000**, no 120,00. El espacio entre celdas
> parte el número y la coma decimal desaparece. Peor todavía, la fila TOTAL
> arrancaba desde otra celda y mostraba `[2][0][00]`.
>
> Lo detectó el usuario mirando el recibo impreso, no una prueba — y no había
> prueba que pudiera detectarlo: el código era correcto y los números eran los
> correctos; lo que estaba mal era lo que el papel comunicaba.
>
> **Todo cambio en la plantilla se verifica renderizando y mirando el resultado,
> con un monto de tres cifras y con uno de miles.**

---

## 7. La ruta

```php
Route::get('/tramites/{tramite}/recibo', [ReciboController::class, 'imprimir'])
    ->middleware('permiso:recibos.imprimir')
    ->name('tramites.recibo');
```

**Es la única ruta del circuito que es GET**, y no es una excepción a la regla de
que los pasos van por PATCH: esta no escribe nada. El recibo ya se emitió solo,
dentro de la transacción que pasó el expediente a EN REVISIÓN. Que el navegador
precargue el enlace no cambia ningún dato ni consume ningún número de la serie.

El permiso `recibos.imprimir` vive en el bloque `$operacion` de `RolSistema`: es
de ventanilla y no de supervisión —lo imprime quien atiende, no quien aprueba—,
mismo criterio que `carnets.generar`.

### En la pantalla

`TramiteController::show()` manda la prop `recibo` (o `null`). La ficha dibuja el
botón **«Recibo N° 0016»** solo cuando existe, y va como `<a target="_blank">` y
no como `router.visit()`: abre el PDF en otra pestaña, y una navegación de
Inertia no sabe qué hacer con un archivo.

El botón **no se esconde** después de la primera impresión: perder el recibo es
justamente el caso en el que hay que volver a sacarlo.

---

## 8. Archivos del módulo

| Archivo | Qué es |
| --- | --- |
| `app/Enums/FormaPago.php` | `deposito \| efectivo` — las dos casillas |
| `app/Enums/ConceptoRecibo.php` | Las seis casillas de DESCRIPCIÓN + el puente con los rubros |
| `app/Models/Recibo.php` | La copia congelada + los métodos de presentación |
| `app/Services/ReciboTramiteService.php` | Emisión idempotente |
| `app/Http/Controllers/Panel/ReciboController.php` | Arma el PDF |
| `resources/views/documentos/recibo-oficial.blade.php` | El calco del talonario |
| `database/migrations/2026_09_14_100000_create_recibos_table.php` | El esquema, muy comentado |
| ~~`tests/Feature/ReciboTramiteTest.php`~~ | Eran 31 pruebas. Borrado el 14/09/2026 |
| `public/image/recibo-escudo.png`, `recibo-sello.png` | Assets de impresión |

Tocados: `CorrelativoService` (se le extrajo `siguienteNumero()`),
`SolicitudCarnetService::tomarParaRevision()`, `TramiteController::show()`,
`RolSistema`, `routes/panel.php`, `ConfiguracionSeeder`,
`resources/js/pages/panel/tramites/ver.tsx`, `resources/js/types/tramites.ts`.

---

## 9. Lo que quedó afuera

- **Un listado de recibos** (el «libro de recibos»). La tabla tiene el índice
  `['gestion', 'fecha_emision']` preparado para eso.
- **Anular un recibo.** Hoy no se puede. En el talonario de papel se anulaba
  escribiendo «ANULADO» sobre las tres copias y archivándolas; la versión
  digital necesitaría el equivalente y todavía no está definido con la unidad.
- **Emitir recibos para expedientes viejos**, los que se aprobaron directo desde
  PENDIENTE. La ruta avisa en vez de inventar uno: un recibo con fecha de hoy por
  una plata cobrada hace meses diría algo que no ocurrió.
