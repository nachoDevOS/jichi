# Modelo Entidad-Relación — Jichi

Sistema de credenciales y permisos de pesca del Gobierno Autónomo Departamental
del Beni. **PostgreSQL 18** (corre igual en SQLite).

Describe el núcleo rehecho el **18/09/2026**. Las diez tablas del dominio se
crean en `database/migrations/2026_09_18_*`; **las migraciones quedaron cortas a
propósito y el porqué de cada decisión vive acá.**

> El modelo ANTERIOR —`rubros`, `tramites`, `faenas`, `guias`,
> `guia_detalles`— ya no existe en la base. Sus migraciones quedaron en
> `database/migrations-anterior/`, que Laravel no escanea.

---

## 1. El diagrama

```
                        ┌──────────────────┐
                        │  beneficiarios   │  la persona, UNA sola vez
                        └────────┬─────────┘
             ┌───────────────────┼───────────────────┐
             │                   │                   │
    ┌────────▼─────────┐ ┌───────▼────────┐ ┌────────▼──────────┐
    │ aprovechamientos │ │    carnets     │ │ guias_movimiento  │
    │      _pesq       │◄┤ (pescador o    │ │ (un traslado)     │
    │ (el cupo en kg)  │ │ comercializador)│ └───────────────────┘
    └────────┬─────────┘ └───────┬────────┘
             │                   │
             └────────┬──────────┘
                      │
             ┌────────▼─────────┐
             │  permisos_faena  │  una salida de pesca
             └──────────────────┘

    ┌──────────┐        ┌────────────────────────────────────┐
    │ recibos  │──────< │ pagos  (polimórfico: pagable_type) │
    └──────────┘        └───────┬────────────────────────────┘
                                │
                  ┌─────────────┼─────────────┐
                  ▼             ▼             ▼
               carnets   aprovechamientos   guias
                              _pesq       _movimiento

    Catálogos que alimentan lo de arriba:
      asociaciones · categorias_aprovechamiento · tipos_carnet
```

**El orden de ventanilla, que es el que explica las dependencias:**

```
1. beneficiario   se registra una vez
2. cupo           se otorga (nace PENDIENTE) ─┐
3. carnet         se emite con ese cupo       ├─ se cobran juntos
4. caja           un recibo cubre los dos ────┘  y el cupo pasa a ACTIVO
5. faena / guía   recién ahí se puede trabajar
```

---

## 2. Las tablas, una por una

### `asociaciones` — el gremio

| Columna | Tipo | Nota |
| --- | --- | --- |
| `nombre` | string(160) | |
| `sigla` | string(20) null | Es lo que entra en el renglón angosto del carnet |
| `estado` | string(20) | `EstadoAsociacion`. Activo / inactivo |

**Nunca se borra una fila.** Los carnets y las guías ya emitidas apuntan acá; una
asociación borrada los dejaría huérfanos. Para sacarla de circulación se pone
`estado = inactivo`, que la quita de los desplegables sin tocar lo histórico.

**El índice único es PARCIAL** (`WHERE deleted_at IS NULL`). Meter `deleted_at`
dentro de un `unique()` no sirve: en SQL `NULL != NULL`, así que todas las filas
vivas se considerarían distintas entre sí y el índice no bloquearía nada. Con el
WHERE, el nombre queda libre recién cuando la fila se da de baja. SQLite entiende
la misma sintaxis.

---

### `categorias_aprovechamiento` — la escala oficial

| Columna | Tipo | Nota |
| --- | --- | --- |
| `nro_escala` | smallint, único | 1 a 7, el orden oficial |
| `modalidad` | string(30) | `ModalidadAprovechamiento` |
| `descripcion_kg` | string(160) | El texto literal de la resolución |
| `kilos_min` / `kilos_max` | decimal(12,2) | |
| `valor_bs` | decimal(10,2) | |
| `estado` | boolean | Vigente o derogada |

**Por qué es una tabla y no un `match()` en código.** La escala la fija una
resolución y cambia sin avisar a nadie que programe. Escrita en PHP, actualizarla
es un despliegue; en una tabla, es una pantalla. Mismo criterio que
`tipos_carnet`.

**Por qué se guarda `descripcion_kg` si ya están min y max.** Porque el texto
oficial no siempre es la lectura literal del rango: el tramo más alto dice
«1001 kg Hasta 2000 Kg PAICHE», y ese «PAICHE» no está en ningún número. El
documento impreso tiene que decir lo que dice la resolución, no lo que el sistema
deduzca de dos decimales.

**Por qué los kilos van en decimal.** La balanza pesa con decimales y los topes se
comparan contra ese peso. Con enteros, un cupo de 100,5 kg caería fuera del primer
tramo por redondeo y el sistema cobraría el siguiente.

**`modalidad` vive acá y no en el formulario de otorgamiento.** La fija la
resolución al definir el tramo, no el operador al atender. Puesta allá, dos cupos
del mismo tramo podrían terminar con reglas distintas.

| Modalidad | De dónde sale el valor |
| --- | --- |
| `escala_general` | La progresión por kilos: a más volumen, más bolivianos |
| `especie_especial` | Tasación FIJA que la resolución puso para esa especie (paiche) |

**Por qué es un enum y no una columna `es_paiche`.** Porque el criterio no es la
especie sino el RÉGIMEN. Mañana la resolución puede sumar otra especie, y con un
booleano llamado por la especie habría que renombrar la columna —o peor, dejarla
mintiendo—.

> ⚠️ **Hoy la modalidad NO cambia ningún comportamiento del sistema.** Su única
> diferencia era que la escala general se podía AMPLIAR, y esa función se retiró
> el 19/09/2026: ahora los dos regímenes se comportan igual y agotado cualquiera
> de los dos hay que tramitar un cupo nuevo. Queda como CLASIFICACIÓN —se copia
> al cupo, se muestra en la ficha y en el catálogo— y como el lugar donde
> colgar una regla el día que la resolución distinga a las especies por algo
> más que el precio.

---

### `tipos_carnet` — el catálogo de credenciales

| Columna | Tipo | Nota |
| --- | --- | --- |
| `nombre` | string(120), único | |
| `precio_bs` | decimal(10,2) | El arancel de HOY |
| `estado` | boolean | |

**El precio se copia al cobrar, no se lee de acá al imprimir.** Un carnet emitido
en marzo a 80 Bs tiene que seguir diciendo 80 Bs en agosto aunque el arancel haya
subido a 100. Esta columna sirve para armar el cobro; lo cobrado de verdad queda
en `pagos`, que no se recalcula.

**No confundirlo con `carnets.tipo_actor`.** `tipo_actor` es la REGLA —qué
habilita el documento, qué puede emitir— y vive en un enum de PHP porque de ella
cuelga lógica. `tipos_carnet` es el CATÁLOGO —cómo se llama y cuánto sale— y vive
en una tabla porque de él no cuelga ninguna decisión. **Nunca se decide nada con
un `match` sobre este nombre.**

---

### `beneficiarios` — la persona

| Grupo | Columnas |
| --- | --- |
| Documento | `ci`, `complemento`, `expedido` |
| Nombre | `primerNombre`, `segundoNombre`, `apellidoPaterno`, `apellidoMaterno`, `apellidoCasado` |
| Personales | `fechaNacimiento`, `genero`, `nacionalidad` |
| Contacto | `direccion`, `ciudad`, `provincia`, `telefono`, `email`, `foto` |

**Es una tabla unificada y NO tiene columna de rol, a propósito.** Quien pesca y
además comercializa es UNA persona con DOS credenciales, no dos fichas. El rol
vive en `carnets.tipo_actor`, que es del documento. Guardarlo acá obligaría a
duplicar la ficha, y desde el momento en que hay dos filas ya no se sabe cuál
corregir: un cambio de teléfono entra en una y la otra queda vieja para siempre.

**El nombre va partido en cinco columnas porque así llega**: la cédula boliviana
lo trae separado. Partirlo después con código no se puede acertar siempre —«María
del Carmen Justiniano de Áñez» no tiene una regla que la resuelva—.
`apellidoCasado` se guarda sin el «de»: con el «de» adentro, buscar «Justiniano»
no encontraría a esa persona.

**No hay columna `nombreCompleto`**: se arma en el modelo, así no puede quedar
desfasado de sus partes.

> ⚠️ **De `primerNombre` en adelante van en camelCase.** En PostgreSQL eso obliga
> a entrecomillar en SQL escrito a mano: `SELECT "primerNombre"`. Sin comillas el
> motor pasa el nombre a minúscula y responde `column "primernombre" does not
> exist`. Eloquent entrecomilla solo; el problema aparece con `whereRaw` /
> `orderByRaw`. Ver `Beneficiario::SQL_NOMBRE`.

**El único parcial va sobre `ci` SOLO**, no sobre `(ci, complemento)`: el
complemento es parte del MISMO documento, no un documento distinto. Incluyéndolo,
cargar a la misma persona una vez con complemento y otra sin él pasaría el
control — y esa persona sacaría dos credenciales del mismo tipo, cada una con su
propia bolsa madre: el doble de cupo del que le corresponde.

**Sin `created_by` / `updated_by`**: quién cargó y quién modificó ya lo guarda
`auditorias`, con el detalle de lo que cambió.

---

### `aprovechamientos_pesq` — la bolsa madre

| Columna | Tipo | Nota |
| --- | --- | --- |
| `beneficiario_id` | FK RESTRICT | |
| `categoria_aprov_id` | FK RESTRICT | Bajo qué tramo se otorgó |
| `modalidad` | string(30) | **Copiada** del tramo |
| `volumen_total_kg` | decimal(12,2) | **Copiado** del techo del tramo |
| `tipo_embarcacion` | string(120) null | El renglón del talonario |
| `estado` | string(20) | `EstadoAprovechamiento` |
| `fecha_emision` / `fecha_vencimiento` | date | Vence con la gestión |

```
PENDIENTE ──[se cobra ENTERO]──▶ ACTIVO ──▶ AGOTADO | VENCIDO
(borrador)

  editar    ✔                      ✘         ✘         ✘
  eliminar  ✔                      ✘         ✘         ✘
  faenas    ✘                      ✔         ✘         ✘
```

**Nace PENDIENTE y solo la caja lo activa.** `CobrarService` lo pasa a `activo`
cuando el saldo llega a cero, y no hay ningún otro camino: la concesión pagada ES
la autorización. Se exige el saldo COMPLETO, no una cuota — si media cuota
alcanzara, habilitarse costaría un boliviano.

**UN CUPO NO SE AMPLÍA.** La función existió y se retiró: el volumen otorgado
queda congelado desde el otorgamiento, y lo único que lo mueve es corregir el
borrador —que vuelve a copiarlo del tramo elegido—. Si a un pescador le hacen
falta más kilos, eso es un trámite nuevo: elegir el tramo, cobrarlo y emitir otro
recibo. Esa vuelta completa ES el control.

**Eliminar un cupo pendiente es una BAJA LÓGICA.** La fila queda con
`deleted_at`, el scope global la esconde de todo —incluida la regla de una bolsa
por persona, que por eso no bloquea a nadie— y el motivo queda en `auditorias`.

**Pendiente es un borrador, y eso es lo que lo hace corregible.** Mientras no
entró plata, equivocarse de tramo se arregla corrigiendo la fila y un cupo cargado
por error se elimina con el motivo escrito. En cuanto entra el primer boliviano
hay un recibo numerado con el detalle impreso, y cambiar lo que ese papel dice por
detrás no es una corrección.

**Por qué cuelga del beneficiario y no del carnet.** El orden real de ventanilla
es: primero se define el cupo, después se emite el plástico —el carnet necesita
saber qué volumen imprimir—. Colgándolo del carnet habría que crear el carnet
primero y actualizarlo después, y entre las dos operaciones existiría una
credencial impresa sin cupo. Por eso la referencia va al revés, en
`carnets.aprovechamiento_id`.

**Por qué se copian `volumen_total_kg` y `modalidad`.** Porque la escala cambia
por resolución. Un cupo otorgado en marzo bajo una escala de 500 kg no puede pasar
a valer 800 en agosto porque alguien editó el catálogo. `categoria_aprov_id` queda
solo como referencia de bajo qué escala se otorgó. Derivar la modalidad por la
relación al leer sería el mismo error: un `join` que devuelve el valor de HOY para
una decisión que se tomó en marzo.

**El saldo NO se guarda en ninguna columna.** Es
`volumen_total_kg − suma de las faenas que consumen cupo`, y se calcula al leer
con `AprovechamientoPesq::saldoKg()`. Una columna `saldo` hay que actualizarla en
cada alta, cada anulación y cada corrección, y se olvida una sola vez para que el
número quede mintiendo para siempre sin ningún error que lo delate.

**`tipo_embarcacion` es texto libre y nullable.** Es el renglón «Tipo de
Embarcación» del talonario verde. No hay padrón de embarcaciones ni nomenclatura
fija —«canoa», «peque-peque», «bote», «chalana», «deslizador»— y un catálogo
cerrado obligaría a dar de alta un tipo nuevo con el pescador esperando en la
ventanilla. Nullable porque el renglón del papel tampoco es obligatorio.

**RESTRICT y no CASCADE** en las dos claves: borrar a una persona no puede
llevarse por delante su historial de cupos, que es lo que respalda cuánto se pescó
bajo su nombre.

---

### `carnets` — la credencial

| Columna | Tipo | Nota |
| --- | --- | --- |
| `beneficiario_id`, `asociacion_id`, `tipo_carnet_id` | FK RESTRICT | |
| `aprovechamiento_id` | FK **nullable**, nullOnDelete | Solo el pescador |
| `tipo_actor` | string(20) | `TipoActor`: pescador / comercializador |
| `codigo_carnet` | string(40), **único global** | Lo impreso en el plástico |
| `estado` | string(20) | `EstadoCarnet` |

**`aprovechamiento_id` es nullable y eso es la regla, no un descuido.** La pesca
se autoriza por VOLUMEN —tantos kilos, contrastables contra una guía de
transporte—; la comercialización no. Quién lleva cupo lo dice
`TipoActor::requiereAprovechamiento()`, **NUNCA un match sobre el nombre del tipo
de carnet**: `tipos_carnet` es un catálogo que edita la unidad, y el mismo
documento figura como «Carnet de Pescador» o «Pescador Artesanal» según quién lo
cargó.

De esa bandera cuelgan cuatro cosas: el formulario muestra u oculta el campo, la
validación lo exige o lo **prohíbe**, la ficha lo muestra o no, y el plástico
imprime el renglón CUPO o le da la tira entera al tipo de actor.

**`nullOnDelete` y no restrict**: sin el cupo, el carnet sigue siendo un documento
válido —se emitió y se entregó— y lo único que pierde es la referencia. Dejarlo en
RESTRICT trabaría la baja sin proteger nada que importe.

**El código es único GLOBAL y no por tipo**: un control en ruta lee un código y
tiene que llegar a UN documento, sin preguntar antes de qué tipo es.

**Qué se imprime y qué no.** Se imprime lo que NO cambia después de que el
plástico sale de la impresora: el nombre, el tipo de actor, la asociación, el cupo
y las fechas. **El ESTADO no se imprime**: un carnet se revoca después de impreso
y la tarjeta no se entera.

**El carnet acepta un cupo PENDIENTE.** Lo único que necesita de él es el volumen
que va impreso, y eso ya está decidido al otorgarlo; el carnet también nace sin
pagar y los dos se cobran juntos en el mismo recibo. Por eso `EmitirCarnetService`
usa el scope `enCurso()` —pendiente o activo, en fecha— y no `vigentes()`.

---

### `permisos_faena` — una salida

| Columna | Tipo | Nota |
| --- | --- | --- |
| `aprovechamiento_id` | FK RESTRICT | De dónde salen los kilos |
| `carnet_id` | FK RESTRICT | Quién los extrae |
| `numero_faena` | int | Hoja del talonario |
| `kilos_extraidos` | decimal(12,2) | |
| `fecha_salida` / `fecha_limite` | date | Máximo **1 mes** |
| `estado` | string(20) | `EstadoFaena` |

**Por qué apunta a dos cosas a la vez.** `aprovechamiento_id` es de dónde SALEN
LOS KILOS —la bolsa contra la que se descuenta— y `carnet_id` es QUIÉN LOS EXTRAE
—la credencial que un control en el río va a pedir—. Las dos apuntan a la misma
persona, pero por caminos distintos y con vidas distintas: el cupo se renueva por
resolución y el carnet por gestión, y no siempre en la misma fecha. Guardar solo
una obligaría a deducir la otra, y la deducción falla justamente en el caso raro.

**El único es `(aprovechamiento_id, numero_faena)`, no global**: cada bolsa madre
arranca su numeración en 1, que es como se llena el papel.

**`fecha_limite` se guarda calculada** en vez de derivarla al leer: si mañana la
resolución cambia el plazo a quince días, los permisos ya emitidos tienen que
seguir venciendo cuando dice el papel que el pescador tiene en la mano.

**No se edita ni se borra: se vence o se completa.** El número sale de un talonario
de papel que el pescador se llevó. Borrar la fila deja un hueco en la serie que
nadie puede explicar y libera un número que el índice único volvería a aceptar.

**Una faena ACTIVA ya consume cupo**, aunque no se haya descargado nada. Es lo
contrario de lo intuitivo y es el punto del cupo: si solo contaran las
completadas, un pescador podría tener diez faenas abiertas por el volumen entero
cada una. Lo que libera el volumen es que la faena VENZA sin cerrarse — ahí la
salida no ocurrió.

---

### `guias_movimiento` — un traslado

| Columna | Tipo | Nota |
| --- | --- | --- |
| `beneficiario_com_id` | FK RESTRICT | **Quien comercializa** |
| `asociacion_id` | FK RESTRICT | |
| `codigo_guia` | string(40), único | |
| `origen` / `destino` | string(160) | |
| `peso_total_kg` | decimal(12,2) | |
| `es_piscicultura` | boolean | **50% de descuento** |
| `fecha_emision` / `fecha_vencimiento` | **dateTime** | Máximo **5 días** |
| `estado` | string(20) | `EstadoGuia` |

**Se llama `beneficiario_com_id` y no `beneficiario_id`** porque acá la persona
entra en un papel concreto: es quien comercializa. El nombre largo evita que
alguien la confunda con el pescador que extrajo la carga, que es otra persona y no
está en esta tabla.

**`es_piscicultura` no es un dato descriptivo: es plata.** Marcado, el arancel se
cobra al 50% — el pescado de criadero no sale del río, así que no consume el
recurso que la tasa viene a proteger. El descuento se aplica en UN SOLO lugar,
`GuiaMovimiento::factorArancel()`, y no se replica en el controlador ni en React:
escrito en tres lados, el día que la resolución cambie el 50% a 40% se corrige en
dos y el tercero sigue cobrando mal sin que nadie lo note.

**Las fechas son `dateTime` y no `date`** porque los cinco días se cuentan desde
la HORA de emisión: una guía emitida a las 18:00 del lunes vence a las 18:00 del
sábado, no a la medianoche del viernes. Con `date` se le regalaría o se le
quitaría al transportista casi un día.

> Por lo mismo, al mandarlas a React van con `toIso8601String()` —son MOMENTOS—
> mientras que las de faenas y carnets van con `toDateString()`.

---

### `recibos` — la cabecera del comprobante

| Columna | Tipo | Nota |
| --- | --- | --- |
| `numero_recibo` | string(40), único | `REC-2026-0016` |
| `monto_total` | decimal(12,2) | Suma **congelada** |
| `concepto` | text | Tal como se imprime |
| `nit_ci_factura` / `nombre_factura` | string | **Copiados** al emitir |

**Por qué el recibo es una tabla y no se arma al vuelo.** Porque `numero_recibo`
es un CORRELATIVO DE CAJA, y un correlativo es justamente el dato que no se puede
derivar de otras tablas: no es el id de ningún trámite ni una cuenta de filas.
Contabilidad audita esa serie.

Y porque el comprobante tiene que ser **inmutable**. Armado al vuelo, corregir un
apellido en la ficha cambiaría los comprobantes ya entregados y una reimpresión de
marzo saldría distinta de la original.

**El número va como string y no como entero** aunque el talonario lo escriba
pelado: la serie lleva prefijo y año, y el año que viene el contador vuelve a 1.
Como entero, el 1 de 2027 chocaría con el 1 de 2026. Se reserva con
`CorrelativoService`, que bloquea la fila del contador para que dos ventanillas
cobrando al mismo tiempo nunca saquen el mismo.

**El nombre y el NIT se copian** porque el comprobante puede emitirse a nombre de
un tercero —la empresa que paga por el pescador— y tiene que seguir diciendo lo
mismo dentro de cinco años.

**`monto_total` también se guarda, y no es redundante**: es la suma de los pagos en
el momento de emitir. Recalcularla al leer haría que el papel entregado cambiara si
después se corrige un abono. Ver `Recibo::montoCalculado()` para el contraste entre
lo impreso y lo que hay hoy.

---

### `pagos` — el detalle, abono por abono

| Columna | Tipo | Nota |
| --- | --- | --- |
| `recibo_id` | FK **CASCADE**, **nullable** | En NULL hasta que el trámite emite su recibo |
| `registrado_por` | FK users, nullable | Quién lo cargó en el mostrador |
| `validado_por` | FK users, nullable | Quién controló la boleta |
| `pagable_type` / `pagable_id` | morphs | Carnet, cupo o guía |
| `monto_parcial` | decimal(12,2) | **Este abono**, no el total |
| `nro_transaccion` | string(60), **único** | El número de la boleta del banco |
| `fecha_deposito` | date | La que dice la boleta, no la de carga |
| `comprobante` | string | Ruta de la foto o el PDF de la boleta |
| `estado_validacion` | string(20) | `pendiente` \| `validado` \| `observado` |
| `observacion` | string, nullable | Por qué se observó |
| `validado_en` | timestamp, nullable | Cuándo se miró la boleta |

**Por qué es polimórfica.** Se cobran tres cosas distintas y las tres se pagan
igual. Una tabla por cada una obligaría a repetir el mismo circuito de caja tres
veces, y peor: `numero_recibo` dejaría de ser único global, así que el mismo papel
podría amparar un carnet y una guía sin que nada lo impida.

> ⚠️ **El costo: se pierde la clave foránea.** El motor no puede exigir que
> `pagable_id` exista, porque no sabe en qué tabla buscarlo. La integridad la
> sostienen los RESTRICT de las otras tablas y la aplicación, no la base.

> ⚠️ **No se precarga con `with('pagable.beneficiario')`.** Eloquent no sabe qué
> es `pagable` hasta que lee la fila, así que lo escrito así se **ignora en
> silencio** y el N+1 sigue ahí. Va con `morphWith`, declarando qué traer para
> cada tipo. Ver `CajaController::index()`.

**CASCADE y no RESTRICT, al revés que en el resto del sistema**: un pago que
perdió su recibo no se puede imprimir ni entra en ningún arqueo. Si algún día se
anula un recibo entero, su detalle se va con él.

> ⚠️ **`recibo_id` ES NULLABLE, Y ES LO QUE SOSTIENE «UN RECIBO POR TRÁMITE».**
>
> El aprovechamiento es un TRÁMITE, y su comprobante es UNO SOLO con el total de
> todos los depósitos: la persona entrega sus boletas —una o cinco— y se lleva un
> papel. Ese papel se emite al pasar a **EN REVISIÓN**, que es cuando el
> expediente se presenta; hasta entonces los depósitos ya están cargados y
> todavía no hay recibo que ponerles.
>
> ```
> PENDIENTE   ──< pago 330,00 (boleta 1242134)   recibo_id NULL
>             ──< pago  82,50 (boleta 42341234)  recibo_id NULL
>      │
> [enviar a revisión]  ──▶  REC-2026-0001 (412,50) ──< los dos pagos
> ```
>
> Exigiéndolo desde el INSERT —como estaba— cada depósito tenía que traer su
> propio recibo para poder escribirse, y eso es exactamente lo que estaba mal:
> **dos boletas de un mismo cupo salían como REC-2026-0001 y REC-2026-0002**, se
> gastaban dos números de una serie que Contabilidad audita y el arqueo del día
> mostraba dos cobros donde hubo uno.
>
> **En Caja llega lleno desde el primer momento**: ahí se cobra y se entrega el
> papel en el mismo acto. Los dos caminos conviven, y por eso la columna admite
> NULL en vez de haberse movido a otro lado.
>
> Lo escriben `CobrarService::registrarDepositos()` (lo deja en NULL) y
> `CobrarService::emitirRecibo()` (lo llena), que llama
> `RevisarCupoService::enviar()` dentro de su misma transacción: o el cupo se
> presenta CON su papel o no se presenta.
>
> Consecuencia para quien consulte la tabla: **un pago sin `recibo_id` es plata
> que entró y todavía no tiene comprobante**, no un dato roto. Suma en el arqueo
> —`CajaController::arqueoDelDia()` cuenta pagos, no recibos— y el listado de
> Caja lo muestra como «Sin recibo».

**NO HAY COLUMNA `metodo_pago`, y no es un olvido.** En esta unidad no se cobra
en efectivo ni por QR: **todo pago es un depósito bancario**. Una columna con un
solo valor posible no informa nada, y peor, invita a suponer que alguna vez hubo
otra cosa. Por eso las tres columnas de la boleta son OBLIGATORIAS.

> ⚠️ **`estado_validacion` NO ES EL ESTADO DEL PAGO: ES EL DE SU CONTROL.**
>
> ```
> PENDIENTE ──▶ VALIDADO    la boleta cuadra con el extracto del banco
>     ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
>     └──[corregir]──┘
> ```
>
> Que el dinero entró ya lo dice que la fila exista. Esto contesta si alguien
> MIRÓ esa boleta contra el extracto y qué encontró.
>
> **Un OBSERVADO sigue sumando en `Pagable::montoPagado()`**: está cargado y la
> plata está; lo que se puso en duda es si la boleta respalda lo que dice.
> Sacarlo de la suma dejaría al trámite figurando sin cubrir por una observación
> que puede estar equivocada.
>
> **Un observado no se valida: se corrige.** `EstadoValidacionPago::admiteControl()`
> solo deja pasar lo que nadie miró, así que el botón «Validar» no existe sobre
> él. Darlo por bueno sin que el dato cambie es aprobar justo lo que se marcó
> como malo. Al corregirlo vuelve a PENDIENTE y se le borra el control entero
> —`validado_por` y `validado_en` incluidos—: quien validó lo hizo sobre otros
> números.
>
> **El control es parte de la REVISIÓN.** Solo corre con el trámite EN REVISIÓN:
> en pendiente el expediente todavía puede cambiar entero, y aprobado ya no
> admite reparos. Ver `AprovechamientoPesq::admiteControlDePagos()`.
>
> **Y frena la aprobación**: `RevisarCupoService::aprobar()` exige las tres cosas
> —estado, monto cubierto y **todas las boletas validadas**—. Sin la tercera, la
> validación sería decorativa.

**QUIÉN CARGÓ Y QUIÉN VALIDÓ VAN EN DOS COLUMNAS DISTINTAS.** No son el mismo
acto ni la misma responsabilidad: uno tipeó la boleta en el mostrador, el otro la
comparó contra el extracto y la dio por buena. En una sola, «quién responde por
esta plata» deja de tener respuesta. Y son dos PERMISOS distintos —
`pagos.corregir` de ventanilla, `pagos.controlar` de supervisión— porque con uno
solo la misma persona objetaría y resolvería su propia objeción.

Van en `pagos` y no solo en `auditorias` porque son un DATO del pago: se muestran
en la ficha y se filtran. La auditoría dice qué pasó; esto dice quién responde.

**Y por eso el arqueo del día son DOS números, no un reparto por método:**

| Número | Qué responde |
| --- | --- |
| `created_at` de hoy | Lo CARGADO: cuadra el trabajo del día |
| `fecha_deposito` de hoy | Lo DEPOSITADO: se cruza contra el extracto del banco |

Un depósito hecho el viernes y registrado el lunes entra en el primero y no en el
segundo, y esa diferencia es justamente la que hay que poder ver.

**LA BOLETA DEL BANCO VIVE EN EL PAGO, no en el recibo.** Si la persona hizo
dos depósitos, son dos boletas distintas y cada una respalda su monto; guardada
en el recibo, la segunda pisaría a la primera.

`nro_transaccion` es **único global entre los pagos vivos**, con índice parcial
`WHERE deleted_at IS NULL`. Es lo que impide
cargar la misma boleta dos veces —contra el mismo trámite o contra otro—, que es
la forma más fácil de que algo figure pagado sin que haya entrado la plata. El parcial deja
fuera las filas dadas de baja, para que un cobro anulado libere su boleta.

**Cuando un mismo depósito cubre varias líneas**, la segunda en adelante llevan
el número con un sufijo —«0012345678-2»—, porque es único global y la columna ya
no admite NULL. Se sigue leyendo cuál es la boleta, y el índice no choca.

> El archivo lo sube `StorageController::file()` —regla 11— **antes de abrir la
> transacción**, porque un rollback no deshace escrituras en disco. Si el cobro
> falla, el `catch` lo borra con `Archivos::borrar()`.

**Por qué `monto_parcial` y no `monto`.** Porque el nombre dice la regla: un
trámite se puede pagar en cuotas. Un carnet de 80 Bs admite dos filas de 40, cada
una con su recibo y su fecha. **Lo que se debe NO se guarda en ninguna columna**:
es el precio menos la suma de estas filas, y se calcula al leer con el trait
`Pagable` — guardado, quedaría desfasado en cuanto alguien corrija un abono.

**`morphs()` crea las dos columnas MÁS el índice compuesto**
`(pagable_type, pagable_id)`, que es el que resuelve la consulta caliente:
«cuánto se pagó de ESTE carnet». En ese orden y no al revés, porque el tipo es lo
que primero acota.

---

## 3. Borrado lógico: las diez tablas lo tienen

**Todas** las tablas del dominio llevan `softDeletes()` y su modelo usa el trait.
Nada del dominio se borra de verdad: se da de baja, la fila queda con
`deleted_at` y el scope global la saca de todas las consultas.

Es lo que corresponde para un sistema de documentos oficiales: cada fila lleva el
nombre de una persona y respalda —o respaldó— un papel. Que alguien haya cargado
2000 kg a nombre de Fulano y lo haya dado de baja cinco minutos después es
exactamente el tipo de cosa que después hay que poder mirar.

### Qué hay que cuidar cuando una tabla pasa a borrado lógico

**1. Los índices únicos cambian de significado.** Una fila dada de baja sigue
ocupando su valor, así que hay que decidir tabla por tabla si el valor se libera
o queda quemado:

| Único | Tipo | Por qué |
| --- | --- | --- |
| `asociaciones.nombre` | **parcial** | Catálogo: dada de baja, el nombre se libera |
| `categorias_aprovechamiento.nro_escala` | **parcial** | Ídem: la escala 3 tiene que poder volver a cargarse |
| `tipos_carnet.nombre` | **parcial** | Ídem |
| `beneficiarios.ci` | **parcial** | Una ficha dada de baja libera la cédula |
| `carnets.codigo_carnet` | **global** | El plástico ya salió y está en la calle |
| `guias_movimiento.codigo_guia` | **global** | El papel ya se entregó |
| `recibos.numero_recibo` | **global** | Correlativo que Contabilidad audita: el hueco es lo que la hace auditable |
| `permisos_faena (aprovechamiento_id, numero_faena)` | **global** | La hoja del talonario se gastó |

> El criterio en una línea: **el catálogo libera, el papel entregado no.**

**2. `unique()` con `deleted_at` adentro NO sirve**, y es la trampa que más se
repite: en SQL `NULL != NULL`, así que todas las filas vivas se considerarían
distintas entre sí. Va índice PARCIAL con `WHERE deleted_at IS NULL`. SQLite
entiende la misma sintaxis.

**3. Las reglas de unicidad de negocio siguen valiendo solas**, porque el scope
global excluye lo dado de baja. La de «una bolsa vigente por persona» es la que
importa acá: si NO excluyera las bajas, dar de baja un cupo dejaría a esa persona
sin poder recibir otro nunca más.

> ⚠️ **`pagos.recibo_id` es `cascadeOnDelete`, y eso es del MOTOR: no se dispara
> con una baja lógica.** Dar de baja un recibo dejaría sus pagos vivos y visibles
> en caja, colgando de un comprobante que ya no está. Hoy nada da de baja
> recibos; el día que algo lo haga, la baja tiene que arrastrar el detalle a
> mano.

---

## 4. Convenciones que valen para todo el esquema

| Regla | Por qué |
| --- | --- |
| **Enums en columnas `string`**, nunca ENUM nativo de PostgreSQL | Sumar un estado no exige `ALTER TYPE` ni bloquear la tabla. Los valores válidos los impone el enum de PHP y el cast del modelo |
| **Todas las tablas del dominio llevan `softDeletes()`** | Cada fila respalda un papel con el nombre de alguien: se da de baja, no se borra |
| **Índices únicos con borrado lógico: parciales en catálogos, globales en papeles** | Ver la sección 3 |
| **RESTRICT por defecto**, CASCADE solo en `pagos → recibos` | Borrar no puede llevarse por delante un historial que respalda papeles entregados |
| **El orden del `Schema::create()` es siempre el mismo**: `id` → claves foráneas → datos → estado → fechas del negocio → índices → `timestamps()` → `softDeletes()` | Las diez tablas terminan iguales. No cambia el esquema: es convención de lectura |
| **Lo copiado se congela** (`volumen_total_kg`, `modalidad`, `monto_total`, `nombre_factura`) | Los catálogos cambian por resolución; lo ya emitido no puede cambiar retroactivamente |
| **Lo calculable NO se guarda** (saldo en kg, saldo en Bs) | Una columna derivada se desfasa en cuanto alguien corrige un dato, y no avisa |

**Tablas de soporte**, fuera del dominio: `users`, `correlativos`,
`configuraciones`, `auditorias`, `accesos`. Más las de Laravel y las de
`spatie/laravel-permission`.

---

## 5. Dónde está cada cosa

| Qué | Dónde |
| --- | --- |
| El esquema | `database/migrations/2026_09_18_*` |
| Las reglas de negocio | `app/Services/` |
| Los estados y sus transiciones | `app/Enums/` |
| El esquema anterior | `database/migrations-anterior/` (Laravel no lo escanea) |
| El registro de cómo se llegó acá | [docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md) |
