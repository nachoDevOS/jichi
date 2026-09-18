# Faenas y guías — los permisos operativos

> **El carnet es la llave anual; con él solo no se sale a trabajar.**

De cada carnet cuelgan los papeles con los que la persona trabaja de verdad, y
son muchos por gestión:

```
beneficiario ──< carnet (Pescador, 2026)        ──< faena  (una por salida)
             ──< carnet (Comercializador, 2026) ──< guia   (una por traslado)
                                                       └──< guia_detalle
```

| | FAENA | GUÍA ÚNICA DE TRANSPORTE |
| --- | --- | --- |
| Autoriza | UNA salida de pesca | UN traslado de carga |
| Dice | Embarcación, comandante, de tal día a tal día, tantos kilos | De dónde a dónde, en qué vehículo, con qué carga |
| Sale del carnet de | **Pescador** (`rubros.emite_faenas`) | **Comercializador** (`rubros.emite_guias`) |
| Se cobra | Tarifa fija por salida (`faenas.monto`, hoy 15 Bs) | Sobre el valor de la carga: suma de `guia_detalles` |
| Tiene detalle | No | Sí, una fila por especie |

---

## 1. Las tres reglas, y dónde vive cada una

| Regla | Quién la hace cumplir |
| --- | --- |
| El rubro del carnet emite ESE papel | `FaenaService` / `GuiaService` |
| El carnet está VIGENTE | Ídem (`Carnet::estaVigente()`, que mira estado **y** fecha) |
| El número del talonario no se repite | El índice único de la tabla; el servicio da el mensaje |

**Ninguna está en el controlador ni en React.** Los modelos contestan
`Carnet::puedeEmitirFaenas()` —sí o no, para mostrar u ocultar el botón—; los
servicios IMPIDEN y además explican cuál de las condiciones falló, porque del
otro lado del mostrador hay alguien esperando un papel.

> **El error más probable del módulo es elegir el carnet equivocado.** La misma
> persona tiene normalmente los DOS carnets. Por eso el buscador
> (`GET /panel/carnets/buscar?permiso=faenas`) **filtra en el servidor**: la
> lista solo trae carnets vigentes que puedan emitir ese papel, así que elegir
> mal no es posible. El servicio igual lo vuelve a comprobar — el filtro es
> comodidad, no seguridad.

---

## 2. El número sale del talonario de papel

No lo genera el sistema: **lo tipea el operador**, copiándolo del formulario
preimpreso. Por eso es `varchar` y no entero —viene con prefijo y ceros a la
izquierda— y es **único en todo el sistema**: dos faenas con el mismo número
serían dos papeles que dicen ser el mismo, y en un control nadie sabría cuál
vale.

De ahí salen las dos ausencias del módulo:

- **NO SE EDITA.** El papel ya está en manos de la persona. Cambiar el sistema
  sin poder cambiar el papel deja a los dos diciendo cosas distintas, y nadie lo
  nota hasta un control.
- **NO SE BORRA.** El número ya se gastó, un hueco en la serie no se puede
  explicar después, y borrar lo dejaría libre para que el índice único lo
  aceptara de nuevo.

Lo que sí hay es **ANULAR**, con motivo obligatorio. El motivo se antepone a
`observaciones` —«ANULADA: …»— y es lo único que va a explicar después por qué
ese número dejó de valer. Anular no se revierte: se emite otro papel.

**La única excepción es el DETALLE de la guía**, que sí se corrige mientras la
guía valga. El peso real se conoce recién en la balanza, y ajustar la grilla
antes de que la carga salga es parte del trabajo normal. La cabecera —quién,
desde dónde, hasta dónde— no cambia nunca.

---

## 3. La carga de la guía

Vive en `guia_detalles`, una fila por especie, y va en tabla aparte por un
motivo concreto: es lo que permite preguntarle a la base **cuántos kilos de
surubí salieron del Beni este año**. Con un `jsonb` o con columnas numeradas
—especie_1, especie_2…— ese reporte se vuelve imposible.

| Columna | Qué guarda |
| --- | --- |
| `especie` | **Texto libre.** No hay padrón escrito de las especies del Beni, y una lista cerrada incompleta impediría emitir la guía |
| `condicion` | Lista CERRADA: fresco, congelado, seco, salado. Cambia el control sanitario y el valor |
| `cantidad_kg` | Los kilos de esa línea |
| `precio_unitario` | Opcional: hay guías que solo declaran volumen |
| `imponible` | **Lo que dice el papel. NO se recalcula** |

> **`imponible` parece redundante —cantidad × precio— y no lo es.** Es la base de
> cálculo que la unidad escribió en el papel, y cuando aplicó una rebaja o
> redondeó, no coincide con la multiplicación. Si el sistema lo recalculara,
> estaría contradiciendo una guía firmada. Por eso `GuiaDetalle::importe()`
> prefiere `imponible` y solo cae a la multiplicación cuando no está — y el
> formulario muestra el cálculo como **placeholder**, nunca como valor.

Es además **la única tabla del módulo que se borra en cascada**: el formulario
reescribe la grilla entera, así que borrar es parte de su uso normal.

---

## 4. El cobro

Los tres —trámite, faena y guía— se pagan con la **misma tabla `pagos`**, que
por eso es polimórfica (`pagable_type` + `pagable_id`). El motivo de fondo es el
índice único de `nro_transaccion`: partido en tres tablas dejaría de ser único, y
la misma boleta podría pagar un trámite y una faena.

El libro de caja (`/panel/pagos`) los lista juntos con una columna **Concepto**
que dice de cuál viene cada fila. Tiene que ser así: lo que se cuadra contra el
extracto del banco es todo lo que entró, no una parte.

> **El ALTA de pagos de faenas y guías todavía no tiene pantalla.**
> `PagoTramiteService` solo sabe de trámites, y su nombre lo dice. Las fichas
> muestran «Sin depósitos registrados» en vez de un botón que no existe. Ver
> [PENDIENTES.md](../PENDIENTES.md).

---

## 5. Pantallas y rutas

| Ruta | Permiso | Qué hace |
| --- | --- | --- |
| `GET /panel/faenas` | `faenas.ver` | Listado, con filtro por estado y por fechas de salida |
| `GET /panel/faenas/crear` | `faenas.crear` | Formulario. Acepta `?carnet=` para llegar con el carnet elegido |
| `POST /panel/faenas` | `faenas.crear` | Alta |
| `GET /panel/faenas/{faena}` | `faenas.ver` | Ficha |
| `PATCH /panel/faenas/{faena}/anular` | `faenas.anular` | Baja con motivo |
| `GET /panel/guias` … | `guias.*` | Lo mismo para guías |
| `PUT /panel/guias/{guia}/detalle` | `guias.crear` | Reemplaza la grilla de carga |
| `GET /panel/carnets/buscar` | `carnets.ver` | Autocompletado de los dos formularios. Devuelve JSON |

En el menú los tres trámites van juntos, bajo un mismo grupo:

```
TRÁMITES
  De carnet     → /panel/tramites
  De faena      → /panel/faenas
  De guía       → /panel/guias
```

«Trámites» es el título del GRUPO y no el de una opción, porque nombra el acto y
el acto es el mismo en los tres. Cada ítem lleva además un `tituloCompleto`
—«Trámites de faena»— para las migas de pan y el globito de la barra angosta,
donde el rótulo del grupo no se ve.

**Emitir es de VENTANILLA; anular es de SUPERVISIÓN.** Son papeles que se llenan
en el mostrador y se entregan en el acto: no hay nada que firmar después, y el
carnet vigente ya es la autorización. Anular, en cambio, quema un número del
talonario para siempre.

**Anular va por PATCH y no por GET**, igual que los pasos del trámite: un verbo
de lectura que escribe se dispara solo con que el navegador precargue el enlace.

---

## 6. Lo que hay que saber antes de tocar el módulo

- **`emite_faenas` / `emite_guias` son DOS columnas y no un `tipo_permiso`**,
  porque no son excluyentes: una actividad piscícola necesitaría faena para la
  cosecha y guía para trasladarla.
- **Nunca preguntar por el nombre del rubro.** El catálogo lo edita la unidad
  desde el panel y el mismo rubro figura como «Pescador» o como «Faena» según
  quién lo cargó. Es la misma decisión que `requiere_capacidad`.
- **Al pedir columnas de `rubros` con `with('rubro:id,nombre')`, incluir las dos
  banderas.** `puedeEmitirFaenas()` las lee, y si no vinieron en el select
  devuelven null —no un error—: el carnet se descarta en silencio y el formulario
  abre vacío sin que nada lo explique. Ya pasó.
- **`Faena` y `Guia` declaran sus valores por defecto en `protected $attributes`
  además de en la base.** Un `default` de la columna lo aplica el INSERT y NO
  llega al objeto que devuelve `create()`: emitir una faena y preguntarle
  `estaEmitida()` en la línea siguiente contestaba que no.
- **El cupo del carnet y la cantidad de la faena son DOS topes distintos.** El
  primero es anual, el segundo es de ese viaje. Se controlan en momentos
  distintos y el formulario lo aclara, porque es la confusión más frecuente.
- **`Faena::estaVigente()` mira TRES cosas**: el estado, la ventana de fechas y
  el CARNET. La tercera es la que se olvida — un carnet suspendido en marzo no
  deja vigentes las faenas de febrero.

---

Ver también: [ARQUITECTURA.md](../ARQUITECTURA.md) · [MER.md](../MER.md) ·
[CARNETS.md](CARNETS.md)
