# Pagos — los depósitos y su control

Un **trámite**, una **faena** o una **guía** se cubren con uno o varios depósitos
bancarios. Cada uno llega con su boleta escaneada y su número de transacción.

```
tramite ──┐
faena   ──┼──< pago   (pagable_type + pagable_id)
guia    ──┘
```

Una sola tabla para los tres, y el motivo de fondo es el **índice único de
`nro_transaccion`**: partido en tres dejaría de ser único, y la misma boleta
podría pagar un trámite y una faena. Ver la migración `create_pagos_table`.

---

## 1. El control: quién dio por bueno ese dinero

> **La fila la tipea una persona y el archivo adjunto puede ser cualquier cosa.**
> Hasta que alguien MÁS abra la boleta y la compare contra el extracto del banco,
> lo que hay es una declaración, no un cobro.

```
PENDIENTE ──▶ VALIDADO    la boleta cuadra
    ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
    └──[corregir]──┘
```

**UN OBSERVADO NO SE VALIDA: PRIMERO SE CORRIGE.** El botón «Validar» no aparece
sobre él. Validarlo sin que nadie haya tocado el dato sería dar por bueno justo
lo que se marcó como malo, y el problema señalado se perdería sin dejar si se
arregló. Al corregirlo vuelve solo a PENDIENTE —el dato es nuevo y nadie lo miró
todavía— y desde ahí se valida.

Estuvo al revés, y tenía sentido mientras no existía la corrección: era la única
salida que había. Hoy la regla vive en `ValidacionPagoService::validar()`, no
solo en la pantalla.

**Si la observación estaba equivocada**, se abre «Corregir» y se guarda sin
cambiar nada: eso lo devuelve a sin controlar y queda en `auditorias` quién lo
hizo. No hay un botón «Levantar observación» — son dos pasos en un caso poco
frecuente, y una tercera acción de control complicaría una pantalla que ya tiene
dos.

| Columna | Qué guarda |
| --- | --- |
| `registrado_por` | Quién lo cargó en ventanilla |
| `estado_validacion` | `pendiente` \| `validado` \| `observado` |
| `validado_por` | Quién lo controló |
| `validado_at` | Cuándo. Aparte de `updated_at`, que se mueve con cualquier corrección |
| `motivo_observacion` | Qué no cuadra. Obligatorio al observar |

**`estado_validacion` NO es el estado del pago.** El dinero entró o no entró, y
eso no cambia; esto dice si alguien lo comprobó. Por eso un depósito observado
**sigue sumando** en `montoPagado()`.

### Tres estados y no dos

«Sin validar» y «no cuadra» parecen lo mismo y no lo son. El primero es trabajo
que falta hacer; el segundo es un problema encontrado. Con dos estados, un
depósito observado se vería igual que uno que nadie miró, y ventanilla no sabría
cuál tiene que ir a corregir.

### Nace PENDIENTE

Si arrancara en «validado», el control no existiría: todo estaría aprobado por
omisión y solo se marcaría lo malo, que es justo lo que nadie se acuerda de
hacer.

---

## 2. CUÁNDO se controla

**El control es parte de la REVISIÓN**, así que depende del estado de lo que se
paga y no del depósito.

| Se paga | Se controla |
| --- | --- |
| **Trámite** | Solo **EN REVISIÓN** |
| **Faena / Guía** | Mientras no estén anuladas |

- **PENDIENTE no**, porque el expediente es un borrador que ventanilla todavía
  está armando —se agregan y se corrigen boletas—: controlar ahí es revisar algo
  que aún puede cambiar.
- **APROBADO o RECHAZADO tampoco.** Observar la boleta de un carnet que ya se
  imprimió no deshace nada, y deja el expediente diciendo que algo está mal
  cuando la decisión ya se tomó.

Las faenas y las guías no tienen circuito de revisión —se llenan y se entregan en
el acto— así que el control se hace cuando se pueda.

Lo decide `Pago::admiteControl()`, y `Pago::motivoSinControl()` arma el texto que
la pantalla muestra en lugar de los botones: **cuando no se puede, se dice**, en
vez de esconderlos y dejar al operador preguntándose qué pasó.

---

## 3. ¿Quien carga puede validar? — es CONFIGURABLE

`registrado_por` y `validado_por` son dos columnas distintas, así que **siempre
queda registrado** quién hizo cada cosa. Lo que se puede encender o apagar es si
el sistema EXIGE que sean personas distintas:

```
pagos.revisor_distinto = 0   (por defecto)  la misma persona puede validar
pagos.revisor_distinto = 1                  tiene que ser otra
```

Con dos personas, encenderlo es lo correcto: quien dice «entraron 150 Bs» no
puede además declarar que lo comprobó, porque entonces no lo comprobó nadie. Es
el mismo criterio de «quien arma no firma» que rige para el trámite.

**Arranca APAGADO porque hoy hay un solo usuario**, y encendido dejaba el
circuito trabado: la misma cuenta carga y por lo tanto no podía validar, así que
el trámite no se aprobaba nunca.

> **Por qué configuración y no una regla en el código:** escrita en el código, la
> única salida era borrarla. Como configuración, la unidad la enciende el día que
> tenga un segundo usuario sin que nadie toque nada. Ver `ConfiguracionSeeder`.

Cuando está encendido y `registrado_por` es null —lo cargado antes de que esto
existiera— no hay con quién comparar y se deja pasar: negarlo dejaría esos
depósitos imposibles de validar para siempre.

---

## 4. Sin control no hay aprobación

`Tramite::puedeAprobarse()` exige **tres** cosas:

1. que el salto de estado valga — no se aprueba lo ya resuelto;
2. que el monto esté cubierto (Regla C), contra la SUMA de los pagos;
3. que **todos** esos depósitos estén validados.

La tercera es la que convierte el control en control. Sin ella se podía aprobar
un expediente sin que nadie hubiera abierto una sola boleta.

El botón «Aprobar» desaparece solo, pero eso no alcanza: la ficha muestra además
un aviso que dice **cuántos** faltan y en qué estado, porque las dos salidas son
distintas —un pendiente se valida, un observado hay que corregirlo antes—.

Un trámite **sin** depósitos pasa la condición 3, y está bien: es el rubro exento
por ordenanza. Quien frena ahí es la condición 2.

---

## 5. Dónde se trabaja

| Pantalla | Para qué |
| --- | --- |
| **Ficha del trámite** | Donde quien revisa trabaja: abre el expediente, mira las boletas una por una y recién entonces aprueba. **También se cargan, se corrigen y se quitan acá**, porque un depósito observado tiene que poder arreglarse sin salir de la pantalla donde se lo observó |
| **Editar trámite** | El borrador: lo mismo, menos el control — en un borrador no hay nada que controlar todavía |
| **Libro de caja** (`/panel/pagos`) | Todos los depósitos juntos, para cuadrar contra el extracto. Tiene columna «Control» y filtro por estado |

| Ruta | Permiso | Qué es |
| --- | --- | --- |
| `POST /panel/tramites/{tramite}/pagos` | `pagos.registrar` | Cargar una boleta |
| `PUT /panel/pagos/{pago}` | `pagos.registrar` | Corregir la fila |
| `DELETE /panel/pagos/{pago}` | `pagos.eliminar` | Quitarla del expediente |
| `PATCH /panel/pagos/{pago}/validar` | `pagos.validar` | Darla por buena |
| `PATCH /panel/pagos/{pago}/observar` | `pagos.validar` | Marcar que no cuadra |

**Los tres permisos son de tres manos distintas**, y el reparto es el punto:
`pagos.registrar` es de VENTANILLA —carga y corrige—, `pagos.validar` es de
SUPERVISIÓN —da por bueno— y `pagos.eliminar` es de ADMINISTRACIÓN —hace
desaparecer—.

**`pagos.validar` es de SUPERVISIÓN**, no de ventanilla: quien carga la boleta no
puede darla por buena, así que el permiso tiene que estar en otras manos.

Las dos acciones piden **confirmación con casilla**. Validar no es «guardar»: es
DECLARAR que se comparó la boleta contra el extracto, y esa declaración queda
firmada con nombre y hora. Un solo clic la vuelve un trámite de memoria —a la
décima vez la mano va sola— que es lo contrario de un control. La frase de la
casilla es lo que después respalda esa firma.

**Van por PATCH y no por GET**, como todos los pasos que escriben: un verbo de
lectura que escribe se dispara solo con que el navegador precargue el enlace.

---

## 5 bis. Corregir un depósito

Una boleta se tipea a mano y a veces sale mal: el monto que no es el del papel,
un dígito de menos en el número, la fecha de hoy en vez de la del banco.
**Corregir la fila es la única salida**, porque los pagos no se anulan ni se
borran.

Se hace desde **«Editar trámite»**, con el botón «Corregir» de cada depósito: el
formulario se abre en el lugar de la fila, con los valores cargados. La boleta es
opcional —se conserva la que está si no se elige otra—.

```
PUT /panel/pagos/{pago}        permiso: pagos.registrar
```

**Va con `pagos.registrar` y no con un permiso nuevo**: arreglar lo que se tipeó
mal es el mismo acto de ventanilla que cargarlo. Lo que ventanilla NO puede
hacer es darlo por bueno, y eso sigue pidiendo `pagos.validar`.

**Se corrige desde las DOS pantallas**, y eso es un cambio respecto de cómo
empezó:

| Pantalla | Cuándo | Cargar | Corregir | Quitar | Controlar |
| --- | --- | :-: | :-: | :-: | :-: |
| **Editar trámite** | Solo borrador | ✔ | ✔ | ✔ | ✘ |
| **Ficha del trámite** | Mientras esté abierto | ✔ | ✔ | ✔ | ✔ |

Al aprobar, la ficha deja de ofrecer todo eso: el formulario desaparece y los
botones también.

Al principio corregir vivía SOLO en «Editar trámite», para que quedara claro cuál
era «el lugar» donde se tocan los depósitos. Eso dejó un expediente sin salida, y
apareció en ventanilla: **observar solo pasa EN REVISIÓN —el control es parte de
la revisión— y «Editar trámite» no abre fuera de PENDIENTE.** El revisor marcaba
«la boleta dice otra cosa» y del otro lado no había dónde corregirla; el único
botón que quedaba era «Validar», que es justamente el que no corresponde.

No son dos lugares para lo mismo: **son dos momentos del expediente**. En el
borrador se arma; presentado se controla. Y el dinero se sigue pudiendo mover en
los dos, porque al enviar se congelan los PAPELES, no los depósitos.

**Y esto no contradice «quien carga no valida»**: esa separación la hacen los
PERMISOS —`pagos.registrar` contra `pagos.validar`— y `Pago::puedeValidarlo()`,
que compara contra quién cargó la boleta. Repartir los botones en dos pantallas
no impedía nada, porque con un solo rol la misma persona abre las dos.

### Cuándo se puede corregir

Dos condiciones, de naturaleza distinta (`Pago::admiteCorreccion()`):

- **VALIDADO NO SE TOCA.** Alguien declaró, con su nombre y la hora, que esa
  boleta cuadra con el extracto. Cambiarle el monto después dejaría esa firma
  puesta sobre otro número, que es justo lo que el control viene a evitar. Para
  corregirlo hay que OBSERVARLO primero: ahí quien revisa retira lo que había
  dado por bueno, y queda escrito.
- **Lo que se paga tiene que seguir admitiendo pagos.** Un trámite rechazado, o
  una faena o guía anuladas, ya no cobran nada.

Cuando no se puede, la pantalla **lo dice** en lugar del botón: el texto sale de
`Pago::motivoSinCorreccion()`, que es el mismo que usa el servicio para rechazar
la operación.

### Corregir un OBSERVADO lo devuelve a «sin controlar»

Es el circuito de la observación cerrándose. Quien revisa escribió «la boleta
dice 120 y está cargado 150», ventanilla corrige, y lo que hay ahora es un dato
nuevo que **nadie miró todavía**. Dejarlo observado mostraría un problema ya
resuelto; darlo por validado sería validarlo sin que nadie lo comparara contra el
extracto. El motivo viejo se borra con él: habla de un número que ya no está.

### Lo que NO se valida al corregir

**No se compara contra el saldo.** Bajar un monto puede dejar el expediente sin
cubrir, y eso no es un error de carga: es la realidad del expediente. Quien
frena ahí es el envío a revisión, que exige el costo cubierto y dice cuánto
falta. Ver `Tramite::faltantesParaRevision()`.

---

## 5 ter. Quitar un depósito

Corregir no alcanza cuando **no hay ningún dato correcto que poner**: la misma
boleta cargada dos veces, o la de otra persona pegada en el expediente
equivocado. Ese depósito no va acá, y hay que sacarlo.

```
DELETE /panel/pagos/{pago}        permiso: pagos.eliminar
```

**Permiso propio, y del grupo de ADMINISTRACIÓN.** Es la misma separación que
hay entre `tramites.editar` y `tramites.eliminar`: quien atiende el mostrador
corrige lo que tipeó; hacer desaparecer dinero declarado es otra cosa.

**EL MOTIVO ES OBLIGATORIO** (mínimo 10 caracteres) y va con casilla de
confirmación. La fila no queda: con ella se van el número de transacción, el
monto y el vínculo con la boleta —que además se borra del disco—. Si el porqué
no está en `auditorias`, dentro de un mes nadie puede explicar por qué ese
expediente hoy tiene cobrado menos que ayer.

### Se quita cuando se corrige — es la misma condición

| | Cargar | Corregir | Quitar |
| --- | :-: | :-: | :-: |
| Trámite **pendiente** | ✔ | ✔ | ✔ |
| Trámite **en revisión** | ✔ | ✔ | ✔ |
| Trámite **aprobado** | ✘ | ✘ | ✘ |
| Trámite **rechazado** | ✘ | ✘ | ✘ |
| Depósito **validado** | — | ✘ | ✘ |

**Al APROBAR se cierra el dinero.** `permitePagos()` vale solo mientras el
expediente esté abierto. Decía además APROBADO —«un trámite puede aprobarse y
terminarse de cobrar después»— y eso dejó de poder pasar cuando
`Tramite::puedeAprobarse()` pasó a exigir el monto CUBIERTO: un aprobado está
pagado por definición. Lo único que la regla habilitaba era cargar plata de más
sobre un carnet ya emitido. Y como todos sus depósitos están validados —también
condición para aprobar—, corregir y quitar ya quedaban fuera por ese lado.

`Pago::admiteEliminacion()` delega en `admiteCorreccion()`, y `motivoSinEliminacion()`
en `motivoSinCorreccion()`: si las dos acciones se habilitan juntas, el motivo por
el que no se pueden es uno solo.

**Quitar fue más estricto y la distinción no se sostenía.** Estuvo permitido solo
en el borrador con este argumento: al enviar salió el RECIBO OFICIAL y el pescador
se fue con ese papel, así que hacer desaparecer un depósito lo dejaría cobrando
más de lo que el expediente muestra.

El argumento se cae solo, porque **corregir ya hace exactamente eso**: el recibo
se arma al vuelo con los depósitos que hay —`ReciboTramiteService::detalle()`—,
así que bajar un monto de 110 a 30 cambia el papel entregado igual que borrar la
fila. Si una se permite, la otra no se puede prohibir invocando el recibo.

Y dejaba sin salida el caso que apareció en ventanilla: **una boleta cargada dos
veces sobre un expediente ya presentado**. Corregirla no sirve —no hay dato
correcto que poner, ese depósito no existe— y quitarla estaba prohibido. Quedaba
sumando para siempre.

Lo que sí las distingue es qué queda: corregir deja la fila con su historial,
quitar deja solo la línea de `auditorias`. Eso no pide otro MOMENTO, pide otro
PERMISO —`pagos.eliminar`, de administración— y el motivo por escrito. Las dos
cosas ya estaban.

### Esto NO es «anulación de pagos»

Sigue sin haberla, y es distinto: anular sería dejar la fila con un estado
`anulado` que la mantiene a la vista invitando a sumarla por error. Acá la fila
se va. Lo que queda es la línea de `auditorias` con el valor anterior, quién lo
hizo, cuándo y por qué — igual que al eliminar un expediente.

---

## 6. Lo que hay que saber antes de tocarlo

- **No hay anulación de pagos.** Una boleta cargada mal se corrige editando la
  fila, y `auditorias` deja el valor anterior. Un pago «anulado» que sigue en la
  lista solo invita a sumarlo por error.
- **La fecha del depósito viaja como fecha SUELTA, no como instante.** La
  columna es un timestamp, pero lo que guarda es el día que dice la boleta.
  Mandada con `toIso8601String()` salía «2026-09-17T00:00:00+00:00», y el
  navegador la convertía a horario local: en Bolivia —UTC-4— eso es el 16 a las
  20:00, así que un depósito del 17 se mostraba **como 16/09**. Va con
  `toDateString()`, y el formulario de corrección la lee con `fechaInput()`.
- **Observar no es definitivo.** Ventanilla corrige el dato o vuelve a subir la
  boleta y se valida. Se distingue de rechazar el trámite en que acá el problema
  es de UN depósito y se arregla sin voltear el expediente entero.
- **Al validar se limpia `motivo_observacion`.** Si antes estaba observado y
  ahora cuadra, dejar el texto viejo haría creer que el problema sigue.
- **`App\Support\ControlDePago` arma los campos para las DOS pantallas.** Escrito
  dos veces, alcanzaría con tocar uno para que una diga «Validado» y la otra
  muestre el botón de validar sobre el mismo depósito.
- **`User::pagosRegistrados()` apuntaba a `user_id`, una columna que nunca
  existió.** La relación estaba rota desde el primer día y no se notaba porque
  nadie la llamaba. Se arregló al agregar `registrado_por`.

---

Ver también: [ARQUITECTURA.md](../ARQUITECTURA.md) · [MER.md](../MER.md) ·
[PERMISOS-OPERATIVOS.md](PERMISOS-OPERATIVOS.md)
