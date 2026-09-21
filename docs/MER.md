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
| `datos` | **json** null | La ficha del gremio: personería, representante, contacto |
| `estado` | string(20) | `EstadoAsociacion`. Activo / inactivo |

**LA FICHA VA EN UN JSON Y NO EN OCHO COLUMNAS** —agregado el 20/09/2026—.
Personería jurídica, representante legal y su cédula, teléfono, correo,
dirección, municipio, comunidad y fecha de fundación: nada de eso DECIDE algo en
el sistema, se guarda, se muestra y se imprime. Con una columna por dato, sumar
el noveno sería una migración; así es una línea en `Asociacion::CAMPOS`.

**Las claves son una lista CERRADA**, justamente por eso: `CAMPOS` es lo que el
formulario dibuja y lo que el Request deja pasar —descarta cualquier otra clave
que llegue del navegador—. Sin esa lista, la misma tabla termina con
«telefono», «teléfono» y «tel», y después ningún reporte los puede cruzar. Los
campos vacíos no se guardan: la columna queda en NULL si no se cargó nada.

> ⚠️ **Buscar dentro del JSON con `LIKE` exige escapar el término.** Laravel
> guarda el JSON con las tildes en `\uXXXX` y las barras en `\/`, así que un
> `LIKE '%Pérez%'` no encuentra `P\u00e9rez` y la búsqueda falla **en
> silencio**, justo con los apellidos de acá. Ver
> `AsociacionController::comoEnElJson()`.

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
| `tipo_actor` | string(20) | Para qué actividad sirve |
| `precio_bs` | decimal(10,2) | El arancel de HOY |
| `estado` | boolean | |

**El precio se copia al cobrar, no se lee de acá al imprimir.** Un carnet emitido
en marzo a 80 Bs tiene que seguir diciendo 80 Bs en agosto aunque el arancel haya
subido a 100. Esta columna sirve para armar el cobro; lo cobrado de verdad queda
en `pagos`, que no se recalcula.

**No confundirlo con `carnets.tipo_actor`.** El `tipo_actor` DEL CARNET es la
REGLA —qué habilita el documento, qué puede emitir—; `tipos_carnet` es el
CATÁLOGO —cómo se llama y cuánto sale—. **Nunca se decide nada con un `match`
sobre el nombre.**

**Y el catálogo lleva su propio `tipo_actor` —agregado el 20/09/2026—**: es con
lo que `EmitirCarnetService` comprueba que el tipo elegido y la actividad del
carnet digan lo mismo. Sin la columna, lo único comparable era el NOMBRE, que la
unidad edita, y así se emitía un «Carnet Comercializador» marcado como pescador:
el plástico decía una cosa y la base otra. El formulario de emisión además
FILTRA la lista con ella, así que los tipos de la otra actividad ni se ofrecen.

---

### `departamentos` — los nueve de Bolivia

| Columna | Tipo | Nota |
| --- | --- | --- |
| `codigo` | string(5), único | **La llave de negocio**: BN, LP, SC… |
| `nombre` | string(60) | Beni, La Paz, Santa Cruz… |

**CATÁLOGO CERRADO: no tiene pantalla, ni alta, ni baja.** No se agrega un
departamento. Por eso se siembra **dentro de su migración** y no en un seeder:
la tabla tiene que existir llena aunque nadie corra `db:seed`, o no se puede
cargar ni una ficha de beneficiario. La lista sale de `config('jichi.expedido')`,
que quedó solo como semilla.

**Sin `timestamps` ni `softDeletes`**, al revés que el resto del dominio: no es
una fila que alguien cargue, corrija o dé de baja, así que no hay nada que
auditar ni que preservar.

**`beneficiarios` la referencia con `departamento_id`**: la ficha guarda el ID y
nada más, no una copia del código.

**El código se resuelve con un MAPA MEMORIZADO, no con la relación.**
`documento_identidad` arma «1234567-1A BN» y se usa en cada fila de cada
listado: con `belongsTo` habría que acordarse de `with('beneficiario.departamento')`
en trece consultas, y el día que alguien lo olvide aparece un N+1 en silencio.
`Departamento::codigoDe()` lee las nueve filas UNA vez por petición y las
guarda —medido: 20 fichas, **1 consulta**—. La relación `departamento()` sigue
estando, para cuando hace falta el nombre completo.

---

### `beneficiarios` — la persona

| Grupo | Columnas |
| --- | --- |
| Documento | `ci`, `complemento`, `departamento_id` (**FK a `departamentos`**) |
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
| `tipo_embarcacion` | string(120) | El renglón del talonario. **Obligatorio** |
| `estado` | string(20) | `EstadoAprovechamiento` |
| `fecha_solicitud` | date | El día que la persona lo pidió |
| `fecha_emision` | date **null** | El día que lo firmaron. NULL hasta aprobar |
| `fecha_vencimiento` | date | Vence con la gestión |

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ AGOTADO | VENCIDO
(borrador)   ▲               │
             └──[rechazar]───┘

  editar     ✔               ✘                     ✘          ✘        ✘
  eliminar   ✔               ✘                     ✘          ✘        ✘
  pagos      ✔               ✘                     ✘          ✘        ✘
  faenas     ✘               ✘                     ✔          ✘        ✘
```

**Se llamaba `activo` y pasó a `aprobado` el 20/09/2026**, a pedido: en este
circuito el estado no dice «está andando» sino que ALGUIEN LO FIRMÓ, y eso es lo
que el operador busca en la columna.

**Nace PENDIENTE, y de ahí no sale por cobrarse.** Cobrarlo entero habilita el
ENVÍO; lo que lo aprueba es la firma, con las boletas ya controladas. Ver
`RevisarCupoService`.

**LOS DEPÓSITOS ENTRAN TODOS JUNTOS Y CUBRIENDO EL MONTO.** Se admiten varias
boletas —la persona depositó en dos veces— pero se cargan en un solo acto y
tienen que sumar el saldo: `CobrarService::registrarDepositos()` mira el
CONJUNTO y rechaza lo que no cubra. Un parcial guardado dejaba el expediente a
medio cobrar, sin recibo, sin poder enviarse y sin ninguna señal fuera de la
ficha.

**De MÁS sí se admite, y es la diferencia con Caja.** La boleta del banco dice
lo que dice: si depositaron 170 por un trámite de 165, el excedente queda a
favor de la entidad y el trámite se presenta igual. Con el tope puesto —el que
sigue valiendo en Caja, `excedeElSaldo()`— ese expediente quedaba trabado con la
plata ya depositada y sin forma de cargarla. Ver
[docs/modulos/PAGOS.md](modulos/PAGOS.md).

**DOS FECHAS, Y NO UNA.** `fecha_solicitud` es el día que la persona presentó el
pedido y la escribe el alta; `fecha_emision` es el día que alguien lo firmó y la
escribe `RevisarCupoService::aprobar()`. Estaban colapsadas en una sola columna,
escrita al crear: la ficha decía «Otorgado el 20/09» sobre un expediente que
nadie había aprobado —y que podía terminar rechazado—. Por eso `fecha_emision`
es **nullable**: en NULL significa «todavía no se otorgó», sin posibilidad de
contradicción, igual que las fechas de impreso y entregado del carnet.

**Y el vencimiento se recalcula al aprobar**, sobre la emisión: el cupo vale por
la GESTIÓN, así que un expediente pedido el 28/12 y firmado en enero vence con
el año nuevo y no con el que ya terminó.

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
| `codigo_carnet` | string(40), **único global** | 16 alfanuméricos: `PES26QK7RJ2M4XPB` |
| `estado` | string(20) | `EstadoCarnet` |

**EL CÓDIGO SON 16 CARACTERES, generados por el sistema** —cambiado el
20/09/2026; eran 12—. Los cinco primeros son el prefijo `PES`/`COM` más los dos
dígitos del año, y los once restantes salen de `random_int()`, el generador
criptográfico: el azar es lo único que impide recorrer el padrón entero
probando códigos en la verificación pública.

El alfabeto excluye **I, L, O, S, 0, 1 y 5**, que se confunden de a pares: el
código se dicta por teléfono y se tipea de un plástico gastado. Se guarda sin
separadores y se muestra de a cuatro —`PES2 6QK7 RJ2M 4XPB`— con
`Carnet::codigoLegible()`; lo que llega tipeado se limpia con
`normalizarCodigo()`.

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
usa el scope `enCurso()` —pendiente o aprobado, en fecha— y no `vigentes()`.

---

### `permisos_faena` — una salida

| Columna | Tipo | Nota |
| --- | --- | --- |
| `carnet_id` | FK RESTRICT | **La única**: de él cuelga la faena |
| `numero_faena` | int | Hoja del talonario del carnet |
| `kilos_extraidos` | decimal(12,2) | |
| `fecha_salida` / `fecha_limite` | date | Máximo **1 mes** |
| `estado` | string(20) | `EstadoFaena` |

**CUELGA DEL CARNET Y DE NADA MÁS** —cambiado el 20/09/2026—. Tuvo también un
`aprovechamiento_id`, con el argumento de que el cupo es de dónde SALEN LOS KILOS
y el carnet QUIÉN LOS EXTRAE. El problema de guardar las dos es que nada las
obligaba a coincidir: una faena podía descontar de un cupo distinto del que
respalda su propio carnet, y ninguna restricción lo impedía.

Hoy la bolsa se alcanza por el camino que ya existe —`carnets.aprovechamiento_id`—
y por eso `AprovechamientoPesq::faenas()` es un **`hasManyThrough`** por
`carnets`. `withSum` y `withMax` siguen funcionando igual, así que el saldo se
sigue calculando con una sola consulta.

**El único es `(carnet_id, numero_faena)`, no global**: cada talonario arranca su
numeración en 1, que es como se llena el papel. El correlativo lo resuelve
`Carnet::siguienteNumeroFaena()`.

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
| `carnet_id` | FK RESTRICT | **La única**: de él cuelga la guía |
| `asociacion_id` | FK RESTRICT | El aval impreso, COPIADO del carnet |
| `codigo_guia` | string(40), único | |
| `origen` / `destino` | string(160) | |
| `peso_total_kg` | decimal(12,2) | |
| `es_piscicultura` | boolean | **50% de descuento** |
| `fecha_emision` / `fecha_vencimiento` | **dateTime** | Máximo **5 días** |
| `estado` | string(20) | `EstadoGuia` |

**CUELGA DEL CARNET DE COMERCIALIZADOR Y DE NADA MÁS** —cambiado el 20/09/2026,
mismo criterio que `permisos_faena`—. Tuvo un `beneficiario_com_id` propio, y el
problema era el de siempre con dos claves: nada obligaba a que la persona de la
guía fuera la titular de su propio carnet. Hoy se llega por
`carnets.beneficiario_id`, y `Beneficiario::guias()` es un **`hasManyThrough`**
por `carnets`.

**La asociación SÍ se sigue copiando**, y no es una inconsistencia: es el aval
que va IMPRESO en el papel. Si la persona cambia de gremio el año que viene, las
guías ya entregadas tienen que seguir diciendo con qué aval salieron.

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
| `beneficiario_id` | FK RESTRICT | A nombre de quién sale |
| `numero_recibo` | string(40), único | `REC-2026-0016` |
| `monto_total` | decimal(12,2) | Suma **congelada** |
| `concepto` | text | Tal como se imprime |

**Por qué el recibo es una tabla y no se arma al vuelo.** Porque `numero_recibo`
es un CORRELATIVO DE CAJA, y un correlativo es justamente el dato que no se puede
derivar de otras tablas: no es el id de ningún trámite ni una cuenta de filas.
Contabilidad audita esa serie.

El **monto** y el **concepto** sí son inmutables: se congelan al emitir, así que
corregir un abono después no cambia el papel entregado.

**El número va como string y no como entero** aunque el talonario lo escriba
pelado: la serie lleva prefijo y año, y el año que viene el contador vuelve a 1.
Como entero, el 1 de 2027 chocaría con el 1 de 2026. Se reserva con
`CorrelativoService`, que bloquea la fila del contador para que dos ventanillas
cobrando al mismo tiempo nunca saquen el mismo.

**EL TITULAR VA POR ID, NO COPIADO** —cambiado el 20/09/2026, a pedido—. El
recibo guarda `beneficiario_id` y el nombre y la cédula se leen del padrón al
imprimir, así que un apellido mal tipeado se corrige en UN lugar. Lo que cuesta,
y hay que tenerlo presente: **el encabezado del comprobante dejó de ser
inmutable** —una reimpresión puede no decir lo mismo que el papel entregado— y
**se perdió emitir a nombre de un tercero**, que era lo que habilitaban las dos
columnas copiadas.

Como el titular sale de lo cobrado y no de un campo tipeado, un recibo no puede
amparar trámites de dos personas: `CobrarService::titularDe()` lo rechaza.

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
| `permisos_faena (carnet_id, numero_faena)` | **global** | La hoja del talonario se gastó |

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
| **Lo copiado se congela** (`volumen_total_kg`, `modalidad`, `monto_total`, `concepto`) | Los catálogos cambian por resolución; lo ya emitido no puede cambiar retroactivamente |
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
