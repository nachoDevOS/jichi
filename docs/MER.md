# Modelo Entidad-Relación — Jichi

Sistema de credenciales y permisos de pesca del Gobierno Autónomo Departamental
del Beni. **PostgreSQL 18** (corre igual en SQLite).

Describe el núcleo rehecho el **18/09/2026**. Las doce tablas del dominio se
crean en `database/migrations/2026_09_18_*`; **las migraciones quedaron cortas a
propósito y el porqué de cada decisión vive acá.**

> El modelo ANTERIOR —`rubros`, `tramites`, `faenas`, `guias`— ya no existe en
> la base. Sus migraciones se borraron el 22/09/2026: quedan en el historial
> de git.
>
> ⚠️ `guia_detalles` VOLVIÓ el 22/09/2026, y no es la tabla vieja: aquella
> colgaba de `guias`, esta cuelga de `guias_movimiento` y calca el cuadro D del
> talonario. Ver su sección.

---

## 1. El diagrama

```
                        ┌──────────────────┐
                        │  beneficiarios   │  la persona, UNA sola vez
                        └────────┬─────────┘
                    ┌────────────┴────────────┐
                    │                         │
           ┌────────▼─────────┐     ┌─────────▼────────┐
           │ aprovechamientos │     │     carnets      │
           │      _pesq       │◄────┤  (pescador o     │
           │  (el cupo en kg) │     │ comercializador) │
           └──────────────────┘     └─────────┬────────┘
                                 ┌────────────┴────────────┐
                                 │                         │
                        ┌────────▼─────────┐     ┌─────────▼─────────┐
                        │  permisos_faena  │     │ guias_movimiento  │
                        │   una salida     │     │   un traslado     │
                        └──────────────────┘     └─────────┬─────────┘
                                                           │
                                                 ┌─────────▼─────────┐
                                                 │  guia_detalles    │
                                                 │ una fila x especie│
                                                 └───────────────────┘

    El pago se hace en SIREB. Cada documento guarda su liquidación (`sireb_*`)
    y, al confirmarse el pago, recibe UN recibo:
    ┌──────────────────────────────────────────┐
    │ recibos  (polimórfico: recibible_type)   │  uno por documento
    └───────┬──────────────────────────────────┘
          ┌─┴───────────┬─────────────┬─────────────┐
          ▼             ▼             ▼             ▼
       carnets   aprovechamientos  permisos_      guias
                      _pesq         faena      _movimiento

    La llave pública, para los CINCO documentos que se entregan:
    ┌─────────────────────────────────────────┐
    │ codigos  (polimórfico: codigable_type)  │  16 al azar, único GLOBAL
    └──────────────────┬──────────────────────┘
        ┌────────┬─────┴────┬──────────┬─────────┐
        ▼        ▼          ▼          ▼         ▼
     carnets  aprov.   permisos_   guias_    recibos
              _pesq      faena    movimiento

    Catálogos que alimentan lo de arriba:
      asociaciones · categorias_aprovechamiento · tipos_carnet
      · productos_hidrobiologicos (el cuadro D de la guía)
```

**El orden de ventanilla, que es el que explica las dependencias:**

```
1. beneficiario   se registra una vez
2. cupo           se otorga (nace PENDIENTE) y registra su cobro en SIREB
3. pago en SIREB  pagado y validado allá → APROBADO, con su recibo
4. carnet         se emite con ese cupo y recorre el mismo camino
5. faena / guía   igual: se piden, se pagan en SIREB y recién aprobadas
                  autorizan a trabajar
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
| `nro_escala` | smallint, único | El orden oficial. **Lo asigna el sistema** al dar de alta —el más alto más uno, contando los dados de baja— y no se edita (30/09/2026) |
| `modalidad` | string(30) | `ModalidadAprovechamiento` |
| `descripcion_kg` | string(160) | El texto literal de la resolución |
| `kilos_min` / `kilos_max` | decimal(12,2) | |
| `servicio_sireb` | uuid | Id del servicio en Recaudaciones (SIREB). Lo comparten todos los tramos |
| `tarifa_sireb` | uuid | Id de la tarifa del tramo dentro de ese servicio: de ahí sale el precio. Única entre las filas vivas (lo exige el Request) |
| `sireb_historial` | json, nullable | (Igual en `tipos_carnet`, ver abajo.) Los pares servicio/tarifa **anteriores**, cada uno con `desde`, `hasta` y `cambiado_por`. El actual vive en las dos columnas de arriba. Lo escribe el evento `updating` del modelo al cambiar cualquiera de las dos; no pasa por `#[Fillable]` ni por la auditoría (30/09/2026) |
| `estado` | boolean | Vigente o derogada |

**Por qué no tiene precio** *(29/09/2026)*. El precio lo fija Recaudaciones
(SIREB), que es la fuente de verdad del arancel del GAD: cada tramo es un
servicio propio allá, con una sola tarifa vigente. La escala guarda el código;
el monto se pide al otorgar y se congela en `aprovechamientos_pesq.monto`. Los
kilos se quedan acá porque son del CUPO, no del precio. Ver
[modulos/SIREB.md](modulos/SIREB.md).

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
| `servicio_sireb` / `tarifa_sireb` | uuid, nullable | Servicio y tarifa de SIREB del tipo: de ahí sale el precio. **Ya no hay `precio_bs`** (30/09/2026). Nullable porque un tipo puede quedar sin ellos —y sin tarifa no emite—; `CatalogoSeeder` siembra los dos con su tarifa; el Request los exige al editar, y la tarifa es única entre las filas vivas |
| `sireb_historial` | json, nullable | Los pares anteriores, igual que en la escala: trait `HistorialSireb` |
| `estado` | boolean | |

**El precio se copia al emitir, no se lee de acá al imprimir.** Un carnet emitido
en marzo a 80 Bs tiene que seguir diciendo 80 Bs en agosto aunque el arancel haya
subido a 100: queda en `carnets.monto` y, una vez pagado, en su recibo.

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

**El catálogo NO se da de alta desde el panel —20/09/2026—.** Los tipos salen de
la resolución, así que la pantalla corrige nombre, actividad, arancel y vigencia,
pero no agrega filas: se retiraron el botón, el `store()` del controlador y la
ruta POST. Las filas iniciales las siembra `CatalogoSeeder`.

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
| `nro` | unsigned, único **global** | El N° impreso: correlativo **continuo** (`AUTORIZACION-PESCA`), `000001`. Lo pone el sistema al otorgar; antes del 03/10/2026 se imprimía el `id` |
| `modalidad` | string(30) | **Copiada** del tramo |
| `volumen_total_kg` | decimal(12,2) | **Copiado** del techo del tramo |
| `monto` | decimal(10,2) | **Congelado** de SIREB al otorgar. Corregir no lo toca: solo cambia la embarcación (03/10/2026) |
| `sireb_tarifa_id` | uuid **null** | La tarifa de SIREB de ese monto (string(36) hasta el 08/10/2026, igualada a los otros tres) |
| `sireb_idempotency_key` | uuid **null**, único global | La `Idempotency-Key` de la venta en SIREB. Se guarda ANTES de llamar; corregir con otra tarifa genera otra (02/10/2026) |
| `sireb_liquidacion_id`, `sireb_codigo_publico` | null | Lo que devuelve SIREB. Null mientras la venta está por enviar |
| `sireb_estado` | string(20) **null** | `EstadoLiquidacionSireb`: `por_enviar` / `registrada` / `anulada`. Null si nunca se vendió |
| `sireb_envio` | json **null** | Constancia: la clave, lo enviado, cuándo, y la respuesta o el error; al anular, el motivo; en `consulta`, el último estado que dio SIREB (08/10/2026). Las versiones anteriores quedan en `auditorias` |
| `sireb_historial` | json **null** | *(08/10/2026)* **Cada liquidación pedida**, desde que SIREB la registra: id, código, clave, ítems (tarifa, cantidad, precio), monto, `solicitada_en`, `vence_en`, `estado` (`registrada` / `vencida` / `anulada` / `pagada`), `cerrada_en` y `motivo`. La vigente es la última y coincide con las columnas `sireb_*`. Fuera de `auditorias` (`$noAuditable`): ya es el registro |
| `tipo_embarcacion` | string(120) | El renglón del talonario. **Obligatorio** |
| `estado` | string(20) | `EstadoAprovechamiento` |
| `fecha_solicitud` | date | El día que la persona lo pidió |
| `fecha_emision` | date **null** | El día que se aprobó (pago confirmado). NULL hasta aprobar |
| `fecha_vencimiento` | date | Vence con la gestión |

```
PENDIENTE ──(pagada en SIREB)──▶ APROBADO ──▶ AGOTADO
(borrador) │                        │            │
           │                        └─[revocar]──┴──▶ REVOCADO
           └──(vencida sin pago en SIREB)──▶ NO_PAGADO

            PENDIENTE   APROBADO   AGOTADO   REVOCADO   NO_PAGADO
  editar       ✔           ✘          ✘          ✘          ✘
  eliminar     ✔           ✘          ✘          ✘          ✘
  faenas       ✘           ✔ (en fecha) ✘        ✘          ✘
  revocar      ✘           ✔          ✔          ✘          ✘
  imprimir     ✘           ✔          ✔          ✘          ✘
```

**`revocado` (27/09/2026)** no agrega columnas: el motivo va a `auditorias`, como
en el carnet revocado. No autoriza carnets ni faenas, libera el lugar para
otorgar otra y deja **sin efecto** a sus carnets y faenas **sin reescribirlos**: su
vigencia mira a la autorización (`Carnet::estaVigente()`, `PermisoFaena::estaVigente()`
y los `scopeVigentes()`). Las reglas completas están en
[REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md#una-autorización-vigente-por-persona-y-la-revocación--27092026).

**Se llamaba `activo` y pasó a `aprobado` el 20/09/2026**, a pedido.

**Nace PENDIENTE y pasa a APROBADO cuando SIREB confirma el pago** (02/10/2026):
lo hace `ConfirmarPagoService`, que llama a `RevisarCupoService::aprobar()`. No
hay depósitos ni firma en Jichi.

**DOS FECHAS, Y NO UNA.** `fecha_solicitud` es el día que la persona presentó el
pedido y la escribe el alta; `fecha_emision` es el día que se aprobó y la
escribe `RevisarCupoService::aprobar()`. Estaban colapsadas en una sola columna,
escrita al crear: la ficha decía «Otorgado el 20/09» sobre un expediente que
nadie había aprobado —y que podía terminar rechazado—. Por eso `fecha_emision`
es **nullable**: en NULL significa «todavía no se otorgó», sin posibilidad de
contradicción, igual que las fechas de impreso y entregado del carnet.

**Y el vencimiento se recalcula al aprobar**, sobre la emisión: el cupo vale por
la GESTIÓN, así que un expediente pedido el 28/12 y aprobado en enero vence con
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
| `monto` | decimal(10,2) | **Copia congelada** del precio de SIREB al emitir (y al corregir el borrador). Es lo que lee `montoACobrar()`: un cambio de tarifa después no toca lo ya emitido (30/09/2026) |
| `sireb_tarifa_id` | uuid, nullable | La tarifa de SIREB de ese precio |
| `sireb_idempotency_key`, `sireb_liquidacion_id`, `sireb_codigo_publico`, `sireb_estado`, `sireb_envio`, `sireb_historial` | | Su liquidación en SIREB, igual que en `aprovechamientos_pesq` (02/10/2026) |
| `nro` | unsigned **null**, índice (no único) | El número de registro: correlativo **por gestión**, asignado AL APROBAR. Se imprime con seis dígitos (`registro_legible`). Ver abajo por qué no es único |
| `archivo_ci`, `archivo_asociacion` | string(255) **null** | Escaneos de la cédula y la carta del gremio. Nullable para cargar lo emitido en papel; el formulario sí los exige. Se suben con `StorageController` ANTES de la transacción |
| `estado` | string(20) | `EstadoCarnet`: `pendiente`, `aprobado`, `revocado` |
| `fecha_solicitud` | date | El día que se pidió |
| `fecha_emision` | date **null** | El día que se aprobó |
| `fecha_vencimiento` | date, índice | El 31/12 de la gestión de la aprobación. **Es la vigencia: no hay estado `vencido`** |

**EL CÓDIGO YA NO ES UNA COLUMNA DE ESTA TABLA.** Vivía en `codigo_carnet` y se
mudó a **`codigos`** el 22/09/2026, cuando los otros cuatro documentos pasaron a
necesitar lo mismo. Se lee con `$carnet->codigo_legible` igual que antes, pero
**toda consulta que lo muestre necesita `with('codigo')`**. Ver la ficha de
`codigos`, más abajo.

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

**`carnets.nro` lleva índice y NO único.** Es el número impreso en el plástico:
correlativo por gestión, compartido entre pescadores y comercializadores y
asignado AL APROBAR (un pendiente no ocupa número y uno rechazado no gasta uno).
El único sería «uno por año», y el año no es una columna —es el de
`fecha_emision`—: expresarlo pediría un índice funcional, que solo existe en
PostgreSQL. Lo garantiza `CorrelativoService`, que bloquea la fila del contador.

**Qué se imprime y qué no.** Se imprime lo que NO cambia después de que el
plástico sale de la impresora: el nombre, el tipo de actor, la asociación, el cupo
y las fechas. **El ESTADO no se imprime**: un carnet se revoca después de impreso
y la tarjeta no se entera.

**El carnet acepta un cupo PENDIENTE.** Lo único que necesita de él es el volumen
que va impreso, y eso ya está decidido al otorgarlo; el carnet también nace sin
pagar y los dos se cobran juntos en el mismo recibo. Por eso `EmitirCarnetService`
usa el scope `enCurso()` —pendiente o aprobado, en fecha— y no `vigentes()`.

**El estado pagado se llama `aprobado`, no `activo`** —cambiado el 25/09/2026,
igual que el cupo el 20/09—: lo que dice la columna es que SE PAGÓ Y SE APROBÓ.

**REVOCAR: solo el APROBADO, y sus faenas siguen** (25/09/2026). Es el camino de
la reposición por extravío: se revoca con motivo desde la ficha, el QR pasa a
«no vigente», y con el mismo cupo se emite el nuevo. Las faenas ya emitidas no
se tocan. Un pendiente se elimina — ver
`EstadoCarnet::permiteRevocacion()`.

---

### `aranceles_sireb` — los cobros que no cuelgan de un catálogo *(30/09/2026)*

| Columna | Tipo | Nota |
| --- | --- | --- |
| `concepto` | string(30), **único** | `ConceptoArancel`: hoy solo `faena`. Una fila por concepto |
| `servicio_sireb` / `tarifa_sireb` | uuid, null | Servicio y tarifa en SIREB; nacen en null hasta que alguien la elige |
| `sireb_historial` | json, null | Los pares anteriores: trait `HistorialSireb` |

Una tabla genérica y no `tarifa_faena`: un cobro fijo nuevo es un caso más del
enum y una fila, sin migración. Las filas las pone `CatalogoSeeder` (una por
caso del enum) y no se dan de baja, por eso el único es a secas y no parcial.
Se editan en Catálogos › Aranceles. La fila `faena` fija el precio de cada
permiso. **La guía no tiene fila**: cobra por kilo con la tarifa de cada
producto.

---

### `permisos_faena` — una salida

| Columna | Tipo | Nota |
| --- | --- | --- |
| `carnet_id` | FK RESTRICT | **La única**: de él cuelga la faena |
| `nro` | int | Correlativo **global y continuo**. Lo pone el sistema |
| `monto` | decimal(10,2) | Copia congelada del precio de SIREB al emitir |
| `sireb_tarifa_id` | uuid, null | La tarifa de SIREB de ese precio (30/09/2026) |
| `sireb_idempotency_key`, `sireb_liquidacion_id`, `sireb_codigo_publico`, `sireb_estado`, `sireb_envio`, `sireb_historial` | | Su liquidación en SIREB, igual que en `aprovechamientos_pesq` (02/10/2026) |
| `kilos_extraidos` | decimal(12,2) | |
| `embarcacion`, `propietario`, `comandante_barco` | string, **null** | Renglones del papel |
| `matricula_naval`, `nro_kardex` | string, **null** | Renglones del papel |
| `region_desde` / `region_hasta` | string(150), **null** | La región amparada |
| `fecha_solicitud` | date | El día que se pidió |
| `fecha_salida` | date, null | La escribe la APROBACIÓN: el día en que se aprobó |
| `fecha_desembarque` | date, null, index | La escribe la APROBACIÓN: salida + 30 días. Es el techo |
| `estado` | string(20) | `EstadoFaena`; nace `pendiente` |

**LA TABLA CALCA EL TALONARIO «PERMISO POR FAENA»** —completado el 21/09/2026—.
El núcleo del 18/09 se había quedado con el esqueleto y perdió los siete
renglones que el papel pide: embarcación, propietario, comandante, matrícula
naval, kardex y la región desde/hasta. Todos **nullable y texto libre**: el
formulario se llena a mano y llega incompleto, y no hay padrón de embarcaciones
ni de comandantes —un catálogo cerrado obligaría a dar de alta uno con el
pescador esperando en la ventanilla—.

**LAS FECHAS NO SE TIPEAN: LAS ESCRIBE LA APROBACIÓN** —25/09/2026, a pedido
del responsable—. La salida es el día de la aprobación y el desembarque, salida +
`PermisoFaena::DIAS_VIGENCIA` (30). Antes el operador tipeaba salida y
desembarque, y había además `fecha_limite` (el techo) y `fecha_emision` (la
firma): con las fechas fijadas por la aprobación, esas dos eran copias de las
otras y se sacaron. El desembarque ES el techo, y de él leen `estaVigente()`,
`estaCaducada()` y `scopeVigentes()`. Aprobar además exige que el cupo siga en
fecha, porque la salida es ese mismo día.

**`monto` es una COPIA CONGELADA**, como en `recibos`: una suba por resolución
no puede mover lo que dice un papel ya entregado. `PermisoFaena::montoACobrar()`
lee la columna. El precio lo pide `EmitirFaenaService` a SIREB al emitir, con la
fila `faena` de `aranceles_sireb` (30/09/2026; antes salía del `.env`).

**LA FAENA SE PAGA ANTES DE VALER, igual que el carnet y el cupo.** Antes nacía
ACTIVA y autorizaba en el acto. Hoy recorre el mismo circuito que los otros:

```
PENDIENTE ──(pagada en SIREB)──▶ APROBADO (autoriza la salida)
```

**El estado pagado se guarda como `aprobado`, no `activo`** —25/09/2026, igual
que el carnet y el cupo—.

**NO SE REGISTRA LA VUELTA** —retirado el 25/09/2026 a pedido—. La faena
aprobada queda así: los kilos autorizados cuentan como consumidos desde la aprobación.
`EstadoFaena::Completado` se quitó del enum el 08/10/2026: nada llevaba a ese estado.

**`revocado` es un estado HISTÓRICO de la faena.** Durante el 27/09/2026 lo
escribía en cascada la revocación de su autorización; ya nada lo escribe: una faena cuya autorización se revocó queda `aprobado` y figura
**«Sin efecto»** (`PermisoFaena::sinEfecto()`). Se conserva en el enum por si
quedó alguna fila así.

`PermisoFaena` usa el trait `LiquidableSireb`, el arancel sale de SIREB (fila
`faena` de `aranceles_sireb`; sin tarifa o sin SIREB no se emite) y el recibo se
emite al aprobarse. La aprobación vive en `RevisarFaenaService::aprobar()`.

**Los kilos se DESCUENTAN desde la aprobación y se RESERVAN desde el registro.**
`EstadoFaena::consumeCupo()` deja pasar solo a la **aprobada** y a la
**completada**: es lo que se resta del saldo y lo único que lleva el cupo a
`agotado`. `EstadoFaena::reservaCupo()` deja pasar a la **pendiente**: no resta
del saldo, pero apartan sus kilos, y en modo estricto
una faena nueva solo puede pedir lo libre —`AprovechamientoPesq::libreKg()` =
saldo − reservado—. El ejemplo está en
[REGLAS-NEGOCIO.md](REGLAS-NEGOCIO.md#paso-4--la-operativa-del-pescador-permisos-de-faena).

Historia: hasta el 21/09/2026 la pendiente descontaba; ese día se pasó a
descontar desde la aprobación, y el cupo se podía sobrecomprometer —tres solicitudes
por el volumen entero pasaban las tres y chocaban al aprobar la segunda—. El
27/09/2026 volvió la reserva, pero separada del descuento: el saldo sigue siendo
el volumen realmente aprobado y el sobrecompromiso deja de ser posible en modo
estricto. `RevisarFaenaService::aprobar()` vuelve a medir igual, por si la faena
nació en modo flexible.

No hay columna nueva: la reserva se calcula con `withSum('faenasQueReservan', …)`,
igual que el consumo.

⚠️ Cada lista de estados está escrita **dos veces** —`EstadoFaena::consumeCupo()`
/ `reservaCupo()` para el filtro en memoria y `AprovechamientoPesq::faenasQueConsumen()`
/ `faenasQueReservan()` para el filtro en SQL—. Si se separan, la ficha y el
listado muestran saldos distintos.

**El titular llega por un accesor.** El recibo sale a nombre de
`$tramite->beneficiario_id`, que la faena no guarda: lo resuelve
`PermisoFaena::beneficiarioId()` a través del carnet, para no duplicar la clave
en la tabla.

**CUELGA DEL CARNET Y DE NADA MÁS** —cambiado el 20/09/2026—. Tuvo también un
`aprovechamiento_id`, con el argumento de que el cupo es de dónde SALEN LOS KILOS
y el carnet QUIÉN LOS EXTRAE. El problema de guardar las dos es que nada las
obligaba a coincidir: una faena podía descontar de un cupo distinto del que
respalda su propio carnet, y ninguna restricción lo impedía.

Hoy la bolsa se alcanza por el camino que ya existe —`carnets.aprovechamiento_id`—
y por eso `AprovechamientoPesq::faenas()` es un **`hasManyThrough`** por
`carnets`. `withSum` y `withMax` siguen funcionando igual, así que el saldo se
sigue calculando con una sola consulta.

**EL NÚMERO ES UN CORRELATIVO GLOBAL Y CONTINUO, y lo pone el SISTEMA**
—cambiado el 21/09/2026—. Arranca en `000001`, no reinicia por gestión y su
único es `permisos_faena.nro` a secas.

Era correlativo DENTRO DEL CARNET y lo tipeaba el operador, con el argumento de
que cada carnet es un talonario. No lo es: el talonario de papel es **uno solo
para toda la unidad** —la hoja de la foto dice `N° 002190`— y numerar por carnet
daba una faena `000001` por cada pescador, así que el número dejaba de
identificar nada. Que lo escribiera el operador agregaba lo suyo: dos ventanillas
tipeando el mismo número, y un dígito de más emitía el permiso `000001` en el
lugar del `000010`.

Lo reserva `CorrelativoService::siguienteContinuo()` dentro de la transacción
del servicio, bajo la serie `PermisoFaena::SERIE`. **Continuo se implementa con
el año 0**, que ninguna gestión real ocupa: la tabla `correlativos` lleva
`(serie, anio)` y pasarle el año de verdad lo haría reiniciar cada enero.

`Carnet::siguienteNumeroFaena()` fue eliminado, y con él los
`withMax('faenas', 'nro')` que lo alimentaban.

**`fecha_desembarque` se guarda calculada** en vez de derivarla al leer: si
mañana la resolución cambia el plazo a quince días, los permisos ya emitidos
tienen que seguir venciendo cuando dice el papel que el pescador tiene en la mano.

**No se edita ni se borra: se vence o se completa.** El número sale de un talonario
de papel que el pescador se llevó. Borrar la fila deja un hueco en la serie que
nadie puede explicar y libera un número que el índice único volvería a aceptar.

**Una faena APROBADA ya consume cupo**, aunque no se haya descargado nada. Es lo
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
| `nro` | unsigned, único **global** | Correlativo **continuo**: `000308` |
| `monto` | decimal(10,2) | Total del cuadro D al emitir, con el descuento |
| `sireb_idempotency_key`, `sireb_liquidacion_id`, `sireb_codigo_publico`, `sireb_estado`, `sireb_envio`, `sireb_historial` | | Su liquidación en SIREB, un ítem por renglón del cuadro D (tarifa por kilo × kilos); igual que en `aprovechamientos_pesq` (02/10/2026) |
| `origen` / `destino` | string(160) | Bloque B del papel |
| `origen_*` / `destino_*` | string(100), nullable | Departamento, provincia, distrito o cuenca |
| `medio_transporte` | string(20), nullable | `MedioTransporte`: el casillero 10 |
| `tipo_transporte` | string(25), nullable | `TipoTransporte`: los renglones a/b/c del bloque C |
| `transporte_nombre` / `_placa` | string, nullable | El vehículo |
| `transporte_capacidad_kg` | decimal(12,2), nullable | Cap. máxima |
| `peso_total_kg` | decimal(12,2) | **La suma del cuadro D**, guardada |
| `es_piscicultura` | boolean | **50% de descuento** |
| `observaciones` | text, nullable | El recuadro del papel |
| `estado` | string(20) | `EstadoGuia`, nace `pendiente` |
| `fecha_solicitud` | date | El día que la persona vino al mostrador |
| `fecha_emision` / `fecha_vencimiento` | **dateTime**, nullable | Máximo **5 días**, los escribe la APROBACIÓN |

**LA GUÍA CALCA EL TALONARIO, BLOQUE POR BLOQUE** —rehecha el 22/09/2026—. Antes
guardaba cuatro datos sueltos: código tipeado, origen, destino y un peso. El
papel que hay que imprimir pide la ubicación con departamento, provincia y
cuenca de ida y de vuelta, el medio y el vehículo, y el cuadro de productos
especie por especie. Sin esas columnas el PDF salía con la mitad de los
renglones en blanco y había que completarlo a mano, que es lo que el sistema
viene a evitar.

**EL NÚMERO LO PONE EL SISTEMA, no el operador.** Era `codigo_guia`, tipeado a
mano y único global. Ahora es `guias_movimiento.nro`, correlativo **continuo** de seis
dígitos por `CorrelativoService::siguienteContinuo()` —el talonario del SEDAG va
en 000308 y no reinicia en enero—. Mismo tratamiento que
`permisos_faena.nro` y que `recibos.numero_recibo`.

**LAS DOS FECHAS DE VIGENCIA SON NULLABLE, y eso es el circuito.** La guía nace
PENDIENTE y hasta la aprobación no ampara nada, así que no hay nada que venza:
`RevisarGuiaService::aprobar()` escribe las dos. Contarlas desde que se cargó el
borrador le comería al camión los días que el expediente estuvo esperando en
ventanilla.

**`monto` es el TOTAL DEL CUADRO D al emitir** —kilos × precio de cada producto,
con el descuento de piscicultura ya aplicado— *(27/09/2026; antes era una tarifa
fija de 50 Bs en `config('jichi.guias.tarifa_base')`, retirada)*. Corregir el
borrador lo recalcula; cambiar el catálogo después no lo mueve. Por eso
`GuiaMovimiento::montoACobrar()` lee la columna.

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

**Las fechas de vigencia son `dateTime` y no `date`** porque los cinco días se
cuentan desde la HORA de emisión: una guía aprobada a las 18:00 del lunes vence a
las 18:00 del sábado, no a la medianoche del viernes. Con `date` se le regalaría
o se le quitaría al transportista casi un día.

> Por lo mismo, al mandarlas a React van con `toIso8601String()` —son MOMENTOS—
> mientras que `fecha_solicitud` va con `toDateString()`: esa guarda un DÍA.

**EL CIRCUITO ES EL MISMO DEL CARNET, EL CUPO Y LA FAENA** —desde el 22/09/2026—:

```
PENDIENTE ──(pagada en SIREB)──▶ APROBADA ──▶ vence a los 5 días
(en la base: `aprobado`)              └──[revocar]──▶ REVOCADA (`revocado`)
                                      └── acá sale el RECIBO
```

Lo decide `EstadoGuia`, no el controlador: `permiteEdicion()`,
`permiteEliminacion()`, `permiteRevocacion()` y `estaAbierto()`. Editar y
eliminar valen SOLO en pendiente, y anulan antes la liquidación en SIREB (si ya
tiene un pago allá, SIREB no deja y no se corrige).

> **REVOCAR Y ELIMINAR NO SON LO MISMO.** Eliminar es sobre el BORRADOR —la fila
> se dio de baja y nunca hubo papel—; revocar es sobre una guía YA APROBADA, cuyo
> papel está en la calle. Las dos queman el número del talonario igual. Hasta el
> 05/10/2026 se llamaba «anular» y el estado era `anulada`.

---

### `productos_hidrobiologicos` — el catálogo del cuadro D *(27/09/2026)*

| Columna | Tipo | Nota |
| --- | --- | --- |
| `nombre` | string(120) | «Surubí». Único entre los vivos (lo exige el Request) |
| `servicio_sireb` / `tarifa_sireb` | uuid, nullable | Servicio y tarifa **por kilo** de SIREB. **Ya no hay `precio_kg`** (30/09/2026). Nullable porque un producto puede no tener tarifa todavía en SIREB —y sin ella no entra en una guía—; `CatalogoSeeder` siembra solo los que ya tienen tarifa. Como en la escala, **la tarifa no se comparte** (lo controla el Request, sin índice): compartida, cambiar el precio de una especie arrastraría a la otra |
| `sireb_historial` | json, nullable | Los pares anteriores: trait `HistorialSireb` |
| `estado` | boolean | Inactivo: no se elige en una guía nueva |

**Existe para que la especie deje de ser texto libre**: «Surubí», «surubi» y
«SURUBI» eran tres especies distintas para cualquier reporte. Lo carga la
unidad desde Catálogos → Productos. Sin baja: uno usado en una guía se pone
inactivo. El seeder trae los 13 nombres de la tabla de tamaños mínimos del
talonario de la autorización, sin tarifa.

**EL PRECIO POR KILO LO PONE SIREB** *(30/09/2026)*: la guía cobra la suma de
kilos × precio de su cuadro D, con el descuento de piscicultura. Al emitir (y al
corregir) `EmitirGuiaService` pide el precio de cada producto a SIREB con
`PrecioSireb` y lo congela en `guia_detalles.precio_kg`; el total va a
`guias_movimiento.monto`.

---

### `guia_detalles` — el cuadro D, una fila por especie

| Columna | Tipo | Nota |
| --- | --- | --- |
| `guia_movimiento_id` | FK **CASCADE** | La excepción del dominio: ver abajo |
| `producto_id` | FK RESTRICT | El producto del catálogo |
| `especie` | string(120) | **Copia** del nombre del producto al emitir |
| `condicion` | string(30) | `CondicionProducto`: las DIEZ columnas de tilde |
| `cantidad_kg` | decimal(12,2) | CANT. ADQUIRIDA |
| `precio_kg` | decimal(12,2) | **Copia** del precio por kilo de SIREB al emitir |
| `sireb_tarifa_id` | uuid, nullable | La tarifa de SIREB de ese precio (30/09/2026) |
| `importe_total` | decimal(12,2) | `cantidad_kg × precio_kg`, guardado |

**EL NOMBRE Y EL PRECIO SE COPIAN, además de guardar `producto_id`.** El papel
entregado no puede cambiar si mañana se corrige el catálogo: la guía guarda lo
que decía el producto el día que se emitió. El formulario manda solo el producto,
la condición y los kilos; nombre, precio e importe los pone
`EmitirGuiaService::normalizarDetalle()`, que además rechaza un producto
inactivo — salvo al corregir una guía que ya lo tenía.

**UN SOLO ENUM Y NO DOS COLUMNAS.** El cuadro del papel tiene diez casillas de
tilde: «Fresco o Refrigerado» se subdivide en entero y eviscerado, «Congelado»
en entero, eviscerado y fileteado, y después vienen Seco, Sal Preso, Vivos, A
Granel y Otros sueltas. Partirlo en estado × presentación dejaría combinaciones
que en el talonario no existen —«seco fileteado»— así que va un enum de diez
casos que mapea 1:1 con las columnas impresas.

**La suma de `importe_total` ES lo que se cobra por la guía** —con el descuento
de piscicultura— y queda en `guias_movimiento.monto` *(27/09/2026)*.

**La FK es CASCADE y no RESTRICT, al revés que todo el resto del dominio**,
porque el detalle no tiene vida propia: es el cuerpo de la guía, no un documento
aparte. ⚠️ Eso **no se dispara con la baja lógica** —`delete()` sobre una tabla
con `SoftDeletes` es un UPDATE— así que `EmitirGuiaService::eliminar()` baja el
detalle a mano antes de bajar la guía. Y al corregir el borrador el detalle se
**reemplaza entero**: casar renglón por renglón sin un id estable del papel
inventa una identidad que el talonario no tiene.

---

### `recibos` — el comprobante de un documento pagado

*(02/10/2026: ya no cuelga de `pagos`, que se retiró; lo emite
`ConfirmarPagoService` cuando SIREB confirma el pago.)*

| Columna | Tipo | Nota |
| --- | --- | --- |
| `beneficiario_id` | FK RESTRICT | A nombre de quién sale |
| `recibible_type` + `recibible_id` | polimórfico, **únicos juntos** | El documento pagado: un documento, un recibo |
| `numero_recibo` | string(40), único | Correlativo **continuo**: `000016` |
| `monto_total` | decimal(12,2) | Lo pagado en SIREB, **congelado** |
| `concepto` | text | Tal como se imprime |
| `numero_boleta`, `entidad_bancaria`, `fecha_pago` | null | La boleta tal como la validó SIREB, copiada: es lo que el papel imprime |

**Por qué el recibo es una tabla y no se arma al vuelo.** Porque `numero_recibo`
es un CORRELATIVO DE CAJA, y un correlativo es justamente el dato que no se puede
derivar de otras tablas: no es el id de ningún trámite ni una cuenta de filas.
Contabilidad audita esa serie.

**EL NÚMERO ES CONTINUO Y SIN PREFIJO —cambiado el 21/09/2026—.** Se guarda
`000016`: seis dígitos, nada más. Era `REC-2026-0016` y reiniciaba en enero, lo
que daba dos recibos con el mismo número en gestiones distintas; el papel del
talonario no dice el año por ningún lado, así que en el archivo eran
indistinguibles. Mismo tratamiento que `permisos_faena.nro`.

Lo reserva `CorrelativoService::siguienteContinuo()` bajo la serie `REC`
(`ConfirmarPagoService`) —que es la CLAVE del contador, no lo que se imprime— y lo
rellena `CorrelativoService::rellenar()`. Continuo se implementa guardando la
serie bajo el **año 0**, que ninguna gestión real ocupa.

**String y no entero**: los ceros a la izquierda son parte del número impreso.
Con ancho fijo el orden alfabético ES el numérico, así que
`Recibo::scopeOrdenDeSerie()` sigue ordenando bien.

El **monto**, el **concepto** y la **boleta** sí son inmutables: se congelan al
emitir.

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

El titular es el del documento pagado (`titularSireb()`): un recibo es de un
documento y de una persona.

---

### `codigos` — la llave pública de los documentos

| Columna | Tipo | Nota |
| --- | --- | --- |
| `codigo` | string(16), **único global** | 16 al azar, **sin prefijo** |
| `codigable_type` + `codigable_id` | polimórfico, **únicos juntos** | Un documento, un código |

**Una tabla y no una columna por tabla.** El número tiene que ser único **entre
todos los documentos**, no dentro de cada uno. Partido en cinco columnas, dos papeles de tipo distinto podrían
llevar el mismo código y `/verificar` no sabría cuál mostrar. Con un solo índice
lo garantiza el motor, y por eso el código **no lleva prefijo**: no hace falta un
espacio de nombres si la unicidad es global.

El costo de ser polimórfica: **se pierde la clave foránea**. La
integridad la sostienen la aplicación y el índice único, no el motor.

**16 caracteres del alfabeto sin `I L O S 0 1 5`** —se confunden de a pares, y el
código se dicta por teléfono y se tipea de un plástico gastado—. Son
2,5 × 10²³ combinaciones: con un millón de documentos la probabilidad de
colisión es 2 × 10⁻¹², y al tope de 20 intentos por minuto de `/verificar`
recorrerlo entero llevaría 10⁹ años. Salen de `random_int()`, el generador
criptográfico: el azar es lo único que impide recorrer el padrón entero probando
códigos.

Se guarda sin separadores y se muestra de a cuatro —`EFGT-96R4-CJ42-AHYJ`— con
`codigo_legible`; lo que llega tipeado se limpia con `normalizarCodigo()`. Las
dos viven en `App\Traits\Codificable`.

> ⚠️ **`UNIQUE(codigo)` es COMPLETO, no parcial.** Un código que salió impreso
> queda **quemado para siempre**, aunque su documento se dé de baja: es papel
> entregado, y el criterio está en §3. Y el `morphTo` va con `withTrashed()`,
> porque un documento revocado tiene que contestar «fue revocado» y no
> «no existe» — si no, revocar vuelve el documento invisible en vez de inválido.

> ⚠️ **TODA consulta que muestre el código necesita `with('codigo')`.** Sin eso
> no falla: hace N+1 en silencio. Y en `carnets` el accesor va en `#[Appends]`,
> así que el N+1 aparece con solo serializar la fila.

**El código NO reemplaza a los correlativos** —`numero_recibo`, `permisos_faena.nro`,
`guias_movimiento.nro`, `aprovechamientos_pesq.nro`, `carnets.nro`—. Son dos números con trabajos opuestos: el correlativo es
consecutivo **a propósito**, porque Contabilidad audita sus huecos; el código es
imposible de adivinar **a propósito**, porque es la llave de una pantalla
pública. Un código al azar no tiene huecos que auditar, y un correlativo lo
adivina cualquiera probando el siguiente.

---

### Tablas de soporte — no son del dominio

| Tabla | Columnas | Nota |
| --- | --- | --- |
| `users` | `name`, `email` (único), `password`, `beneficiario_id`, `ci`, `mamore_id` (único), `activo`, `ultimo_acceso_at`, `debe_cambiar_password` + soft delete | **Con `beneficiario_id` = cuenta del portal**: nunca entra al panel ni lleva roles. Una por beneficiario: índice parcial `users_beneficiario_unico` (`WHERE deleted_at IS NULL`). `mamore_id` vincula con Ibare. Las columnas propias llegan en la migración `2026_09_01_100000`, la única «de parche»: `users` es la tabla de Laravel |
| `correlativos` | `serie`, `anio`, `ultimo_numero` | Único `(serie, anio)`. Las series continuas —recibo `REC`, `AUTORIZACION-PESCA`, `PERMISO-FAENA`, `GUIA-TRANSPORTE`— van bajo el **año 0**; el registro del carnet (`CARNET`), por gestión. `CorrelativoService` bloquea la fila |
| `configuraciones` | `clave` (única), `valor`, `tipo`, `grupo`, `etiqueta`, `descripcion`, `publico` | Lo que la unidad cambia sin tocar código: datos de la portada, firmante del carnet. Las siembra `ConfiguracionSeeder` |
| `auditorias` | `user_id`, `evento`, `auditable_type`/`_id`, `valores_anteriores`, `valores_nuevos`, `descripcion`, `ip`, `user_agent`, `url` | La escribe el trait `Auditable` (alta, cambio, baja). El motivo de una baja va en `descripcion` vía `motivoAuditoria` |
| `accesos` | `user_id`, `email`, `evento`, `ip`, `user_agent`, `session_id` | Bitácora de ingresos y salidas, también los fallidos |

Más las de Laravel (`cache`, `jobs`, `sessions`…) y las de `spatie/laravel-permission`.

## 3. Borrado lógico: todas las tablas del dominio lo tienen

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
| `productos_hidrobiologicos.nombre` | **parcial** (en el Request) | Ídem |
| `beneficiarios.ci` | **parcial** | Una ficha dada de baja libera la cédula |
| `codigos.codigo` | **global** | El código de un documento entregado ya salió impreso |
| `guias_movimiento (nro)` | **global** | La hoja del talonario se gastó |
| `recibos.numero_recibo` | **global** | Correlativo que Contabilidad audita: el hueco es lo que la hace auditable |
| `permisos_faena (nro)` | **global** | La hoja del talonario se gastó |
| `aprovechamientos_pesq.nro` | **global** | Ídem |
| `aprovechamientos_pesq.sireb_idempotency_key` | **global** | Ya viajó a SIREB: reusarla devolvería la venta vieja |

> El criterio en una línea: **el catálogo libera, el papel entregado no.**

**2. `unique()` con `deleted_at` adentro NO sirve**, y es la trampa que más se
repite: en SQL `NULL != NULL`, así que todas las filas vivas se considerarían
distintas entre sí. Va índice PARCIAL con `WHERE deleted_at IS NULL`. SQLite
entiende la misma sintaxis.

**3. Las reglas de unicidad de negocio siguen valiendo solas**, porque el scope
global excluye lo dado de baja. La de «una bolsa vigente por persona» es la que
importa acá: si NO excluyera las bajas, dar de baja un cupo dejaría a esa persona
sin poder recibir otro nunca más.

> ⚠️ **`guia_detalles.guia_movimiento_id` es `cascadeOnDelete`, y eso es del
> MOTOR: no se dispara con una baja lógica.** Dar de baja una guía dejaría sus
> renglones vivos: `EmitirGuiaService` los baja a mano.

---

## 4. Convenciones que valen para todo el esquema

| Regla | Por qué |
| --- | --- |
| **Enums en columnas `string`**, nunca ENUM nativo de PostgreSQL | Sumar un estado no exige `ALTER TYPE` ni bloquear la tabla. Los valores válidos los impone el enum de PHP y el cast del modelo |
| **Todas las tablas del dominio llevan `softDeletes()`** | Cada fila respalda un papel con el nombre de alguien: se da de baja, no se borra |
| **Índices únicos con borrado lógico: parciales en catálogos, globales en papeles** | Ver la sección 3 |
| **RESTRICT por defecto**, CASCADE solo en `guia_detalles → guias_movimiento` | Borrar no puede llevarse por delante un historial que respalda papeles entregados |
| **El orden del `Schema::create()` es siempre el mismo**: `id` → claves foráneas → datos → estado → fechas del negocio → índices → `timestamps()` → `softDeletes()` | Las diez tablas terminan iguales. No cambia el esquema: es convención de lectura |
| **Lo copiado se congela** (`volumen_total_kg`, `modalidad`, `monto`, `monto_total`, `concepto`, la boleta del recibo) | Los catálogos cambian por resolución; lo ya emitido no puede cambiar retroactivamente |
| **Lo calculable NO se guarda** (saldo en kg, saldo en Bs) | Una columna derivada se desfasa en cuanto alguien corrige un dato, y no avisa |

**Tablas de soporte**, fuera del dominio: `users`, `correlativos`,
`configuraciones`, `auditorias`, `accesos`. Más las de Laravel y las de
`spatie/laravel-permission`.

**`users` guarda también las cuentas del portal** (28/09/2026): con
`beneficiario_id` lleno la fila es de un beneficiario y solo entra a
`/mi-cuenta`. Tres decisiones:

- `beneficiario_id` **no tiene FK**: la columna está en la migración de campos
  institucionales, que corre antes de que exista `beneficiarios`. Lo cuida
  `CuentaPortalService`, y como `beneficiarios` nunca se borra de verdad, no hay
  fila que pueda quedar huérfana.
- Su único es **parcial** (`users_beneficiario_unico`, `WHERE deleted_at IS
  NULL`): una cuenta por beneficiario, y la baja lógica libera el lugar.
- `email` pasó a **nullable**: el beneficiario entra con su C.I. El único sigue
  valiendo, porque en SQL `NULL` no choca con `NULL`.
- `debe_cambiar_password` marca la clave temporal de ventanilla.

---

## 5. Dónde está cada cosa

| Qué | Dónde |
| --- | --- |
| El esquema | `database/migrations/2026_09_18_*` |
| Las reglas de negocio | `app/Services/` |
| Los estados y sus transiciones | `app/Enums/` |
| El esquema anterior | Borrado el 22/09/2026; está en el historial de git |
| El registro de cómo se llegó acá | [docs/sesiones/09-2026/2026-09-18.md](sesiones/09-2026/2026-09-18.md) |
