# Módulo Carnets — la impresión de la credencial

El plástico que la persona se lleva al final del circuito. Reemplaza a la
credencial que la unidad venía mandando a imprimir por fuera, una por una.

> Este documento cubre **la impresión**. Cómo NACE un carnet —la Regla A, el
> índice único por persona y gestión— está en
> [ARQUITECTURA.md](../ARQUITECTURA.md) y en `SolicitudCarnetService`.

---

## 1. El diseño: un calco de la cédula de papel

La tarjeta reproduce la credencial que el SEDAG viene entregando: el mismo verde
con el sello de agua, el mismo encabezado, el mismo título en rojo perfilado de
dorado, la foto a la izquierda con la cédula debajo y los seis renglones sobre
sus tiras blancas.

```
┌───────────────────────────────────────────────────────────────┐
│ [escudo] GOBERNACIÓN        │ SECRETARÍA DPTAL. DE …          │
│          GOB. AUT. DEPT. DEL│ RECURSOS NATURALES Y …          │
│          BENI               │      SEDAG - BENI               │
│                     C É D U L A                                │
│ ┌────────┐  NOMBRE     : ▓ Jesús Acosta Cervantes ▓            │
│ │        │  ASOCIACIÓN : ▓ Pacusito                ▓            │
│ │  FOTO  │  CIUDAD     : ▓ Riberalta               ▓            │
│ │        │  PROVINCIA  : ▓ Yacuma                  ▓            │
│ └────────┘  DIRECCIÓN  : ▓ Puerto Almacén          ▓            │
│ ▓C.I. 3277571▓ REGISTRO: ▓ 000002 ▓ GESTIÓN : ▓ 2026 ▓         │
└───────────────────────────────────────────────────────────────┘

   ▓ = tira BLANCA con la letra negra fina; los rótulos, blancos
       perfilados, van directo sobre el verde.
```

> **Se probó una versión «mejorada»** —el nombre grande y sin rótulo, los datos
> sueltos sobre el verde en vez de sobre tiras, dos filetes separando bloques— y
> se descartó. Quien recibe la credencial está acostumbrado a ese formato, y el
> inspector que la revisa en el río la reconoce de lejos por su forma: una
> tarjeta rediseñada se lee como si fuera otro documento.

### Espejo de la vista previa del panel

`resources/js/components/panel/tramites/vista-previa-carnet.tsx` dibuja este
mismo molde en pantalla, en el recuadro «ASÍ VA A SALIR EL CARNET» que el
operador mira mientras carga el trámite.

**Si se toca una, se toca la otra.** Si se separan, la vista previa pasa a ser
una promesa que el PDF no cumple, y el operador se entera con el pescador ya en
la ventanilla — que es justo el problema que ese recuadro viene a evitar.

Lo que está fijado de a pares:

| | En el PDF | En la vista previa |
| --- | --- | --- |
| Fondo | `carnet-fondo.png` | `linear-gradient(180deg, #719327, #5c8b18 55%, #518411)` |
| Sello de agua | horneado en ese PNG, al 30% | `sedag.png` al 46% de ancho y `opacity-30` |
| Encabezado | `carnet-escudo.png` + los tres bloques | `icon.png` + los mismos |
| Título | cinco copias del texto, a 9,5 pt | una a `0.82rem`, con `text-shadow` en cuatro direcciones |
| Renglones | Nombre, Asociación, Ciudad, Provincia, Dirección, Registro | los mismos |

> Los colores van escritos fijos y no salen de los tokens del tema: esto no es
> una pantalla que cambia con el modo claro y oscuro, es la representación de una
> tarjeta IMPRESA, y el verde institucional tiene que verse igual siempre porque
> el operador la compara contra la credencial que tiene en la mano.

---

## 2. Cuándo se puede imprimir

```
PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
   │                              │
   │                              └── el carnet habilita ──▶ ACÁ se puede imprimir
   └── el carnet YA EXISTE, pero no autoriza a nada
```

**El carnet existe desde PENDIENTE y no se puede imprimir hasta APROBADO.** La
fila de `carnets` nace junto con el expediente —la crea
`SolicitudCarnetService::registrar()` cuando la persona no tenía uno de ese rubro
en esta gestión— pero no habilita hasta que alguien firma. Entre uno y otro
momento el plástico saldría con todos sus datos y sin autorizar nada, y encima
puede no estar pagado todavía.

Lo dice `Carnet::puedeImprimirse()`, con dos condiciones:

| Condición | Por qué |
| --- | --- |
| Al menos un trámite APROBADO | Sin eso el carnet no autoriza a nada. Antes se contaban las filas de `carnet_rubro`; al desaparecer el pivote, la pregunta equivalente es si algún expediente del carnet llegó a aprobarse |
| El carnet no está anulado | Anular es una sanción: reimprimirlo devolvería a la calle un documento que el sistema ya desconoció |

**Un carnet VENCIDO sí se imprime.** Es la reimpresión de un documento que
existió: el plástico dice su gestión, y la ficha del panel dice si sigue
valiendo. Negarla obligaría a explicar a mano por qué el sistema no puede
mostrar lo que emitió el año pasado.

**Reimprimir no consume nada ni cambia nada.** El registro es el mismo y el PDF
sale idéntico. Es lo contrario del recibo, donde la idempotencia hubo que
construirla: acá no hay número que gastar. Por eso el botón no se esconde
después de la primera impresión — el carnet se pierde, se moja y se rompe, y esa
es justamente la vez que hace falta.

---

## 3. Imprimir NO es marcar impreso

Son dos actos y dos rutas:

| | Ruta | Qué hace |
| --- | --- | --- |
| Ver el PDF | `GET /panel/carnets/{carnet}/imprimir` | Dibuja el documento. **No escribe nada** |
| Declararlo impreso | `PATCH /panel/tramites/{tramite}/generar` | Escribe `tramites.fecha_generacion` |

Abrir la vista previa no es haber sacado el plástico en la impresora de
credenciales. Si la primera ruta marcara, alcanzaría con que alguien mirara el
documento —o con que el navegador precargara el enlace— para que el expediente
quedara declarando un carnet que nunca existió.

Es por eso que la de impresión **puede ser GET**, igual que la del recibo y a
diferencia del resto del circuito: no cambia ningún dato.

El permiso es `carnets.generar` —el de ventanilla, el mismo que marca impreso— y
no `carnets.ver`: consultar un carnet en pantalla y sacar el documento con
validez no son la misma atribución.

---

## 4. Qué dice la tarjeta, y qué no

| Elemento | De dónde sale |
| --- | --- |
| Encabezado | Escudo + «GOBERNACIÓN / GOBIERNO AUTÓNOMO DEPARTAMENTAL DEL / BENI», filete, y el bloque de la Secretaría con «SEDAG - BENI» |
| Título | «CÉDULA», fijo |
| C.I. (debajo de la foto, en su propia tira) | `Beneficiario::documento_identidad` |
| Foto | `beneficiarios.foto`, cuadrada, embebida en base64 |
| NOMBRE | `Beneficiario::nombreCompleto` |
| CUPO | `Carnet::capacidadLegible()`. **Tercer valor del último renglón, SIN rótulo**, y solo si la actividad lo lleva |
| ASOCIACIÓN | `carnets.asociacion` — la copia que se congeló al emitir |
| CIUDAD | La ficha del beneficiario, en su propio renglón |
| PROVINCIA | Ídem. Entera, ya no abreviada |
| DIRECCIÓN | La ficha del beneficiario |
| REGISTRO | `Carnet::registro()` — el id con ceros: `000013` |
| GESTIÓN | `carnets.gestion` — comparte renglón con el registro |

**Son SEIS renglones, siempre, para cualquier actividad.** Uno solo comparte un
segundo par: `REGISTRO + GESTIÓN`, partido por la mitad —38 pt útiles cada uno—,
que es lo que necesitan seis dígitos y un año. El salto entre renglones es fijo:
14 pt desde los 66.

> **LLEGAR A SEIS COSTÓ SACAR DOS DATOS DE LA COLUMNA.** Los dos estaban de más
> ahí, y conviene saber adónde fueron:
>
> | Dato | Dónde está ahora | Por qué salió |
> | --- | --- | --- |
> | RUBRO | En el **título** | Con «CÉDULA DE COMERCIALIZADOR» arriba, el renglón imprimía dos veces la misma palabra |
> | CUPO | En la **columna de la foto**, bajo el C.I. | Como renglón, un pescador llegaba a SIETE y había que apretar el salto de 14 a 12 pt |
>
> Sacarlos liberó el lugar que permitió darle a CIUDAD y a PROVINCIA una tira
> entera cada una —compartían una— y, de paso, **«PROVINCIA» volvió entera**: se
> abreviaba a «PROV.» porque el rótulo de un segundo par tiene una caja de 32 pt
> y la palabra mide 33,2 a 6,1 pt bold. El rótulo de un renglón entero tiene 44.

> **EL ÚLTIMO RENGLÓN LLEVA DOS O TRES DATOS.** `REGISTRO + GESTIÓN` siempre, y
> el `CUPO` cuando la actividad se autoriza por volumen. Con cupo el renglón usa
> el reparto `triple`:
>
> | | Rótulo | Valor |
> | --- | --- | --- |
> | REGISTRO | 0 → 44 | 47,5 → 75,5 |
> | GESTIÓN | 78 → 108 | 111,5 → 134,5 |
> | CUPO | *(sin rótulo)* | 137 → 176,5 |
>
> **El cupo va SIN rótulo**, a pedido: «800 KG» se lee solo, la unidad hace de
> etiqueta. Y es lo único que cabe — los 176 pt ya están repartidos entre dos
> rótulos y tres valores. Se queda con la tira más ancha porque es el único que
> puede crecer, con un cupo de cinco dígitos y separador de miles.
>
> **SOLO SALE SI LA ACTIVIDAD LO LLEVA.** Sin cupo el renglón vuelve al reparto
> de dos, con las tiras anchas de siempre. Lo decide `rubros.requiere_capacidad`,
> no una lista de nombres. Ver `CarnetImpresionController::renglonRegistro()`.
>
> Antes probó dos lugares más: un renglón propio —que llevaba la tarjeta a siete
> y obligaba a apretar el salto— y una tira suelta bajo la cédula, que lo dejaba
> lejos del resto de los datos. Acá vuelve a la columna donde lo traía la cédula
> de papel, sin costar una línea.

> **OJO CON LOS RÓTULOS DE UN SEGUNDO PAR.** El cálculo de encogido de `texto()`
> protege a los VALORES: si un nombre no entra, se achica. Los rótulos son
> constantes y nadie los mide, así que uno largo se desborda en silencio sobre
> lo que tenga al lado. Pasó con «PROVINCIA» cuando compartía renglón: a 6,1 pt
> bold mide 33,2 en una caja de 32, y en el PDF salía «PROVINCIACercado» pegado.
>
> Volvió a pasar con «GESTIÓN» al partir el renglón en tres: con una caja de
> 27 pt salía «GESTIÓN2026» pegado, y hubo que abrirla a 30.
>
> Antes de poner un rótulo en un renglón partido, medirlo:
> **caracteres × 0,605 × cuerpo**, y dejarle un par de puntos de margen.

### El registro y la gestión comparten renglón

Es el único de los seis que lleva dos pares, y van juntos porque **el número solo
no alcanza**: el registro es el id del carnet y los carnets son por año, así que
se reinicia con cada gestión. El `000002` de 2026 y el `000002` de 2027 son dos
credenciales distintas con el mismo número impreso, y quien lee el plástico en un
control necesita los dos datos a la vez para saber de cuál está hablando.

Comparten renglón y no ocupan uno propio porque en una CR80 no entran siete
líneas sueltas sin apretar todo lo demás; el registro son seis dígitos fijos y le
sobra media tira. El corte va a la mitad: 46 pt para cada valor, con el rótulo
«GESTIÓN» en el medio. En la vista previa lo mismo se pide con dos `flex-1`.

### Los rótulos van en blanco grueso, sin contorno

El blanco es lo que separa el andamio del dato. «NOMBRE» no es información: es el
cartelito que dice qué se está leyendo. En el verde oscuro del valor pesaba lo
mismo que el nombre de la persona y el ojo tenía que descartarlo en cada renglón.

**Tuvieron un contorno oscuro y se les sacó.** Se les había puesto uno de 0,2 pt
porque a 4,6 pt el blanco se desdibujaba donde pasa el sello de agua. Lo que
resolvió el problema de verdad fue **agrandarlos**; sin el borde se leen
francamente blancos en vez de blancos-con-suciedad. El `:` va igual, que también
es andamio.

> ⚠️ **EL BLANCO ES `#ffffff` PURO, Y AUN ASÍ PUEDE VERSE GRIS.** No es un
> problema de color: medido sobre el PDF, el núcleo del glifo da 255. Es de
> **grosor** — a un cuerpo chico el trazo es tan fino que el ojo lo promedia con
> el verde de atrás, y el remedio intuitivo (subirle el blanco, ponerle borde) no
> toca la causa. La única palanca real es el cuerpo, y por eso este renglón se
> fue agrandando: **4,6 → 5,2 → 6,1**. A 6,1 la superficie blanca del rótulo casi
> se duplicó respecto de 4,6.

> **El límite lo marca «ASOCIACIÓN»**, el rótulo más largo: a 6,1 pt mide 42,8 de
> los 44 de su caja, y su tope es 6,27. Los 3 pt que la caja creció salieron de
> plantar la columna en 58 en vez de 60 — la foto y la tira de la cédula cierran
> en 56,1, así que 58 es lo más a la izquierda posible. **Para pasar de ahí habría
> que sacarle ancho a la tira del valor**, que es lo que no conviene.

El perfilado de cinco copias sigue existiendo —lo usa el lockup del encabezado— y
su regla es genérica: la aplica cualquier bloque con clase `perfil`. Ver
[`documentos/partes/texto-perfilado.blade.php`](../../resources/views/documentos/partes/texto-perfilado.blade.php).

### El relleno de la tira suma al ancho, y estaba mal declarado

Las seis tiras tienen que ir de 46 a 176 —los 130 pt que quedan de la columna—
pero se les escribían **130 de ancho más 4 de relleno**, así que la caja terminaba
en 180. Con el campo plantado en 60, eso son 240 de una carilla de 243: **la tira
quedaba a 2,4 pt del borde mientras la foto respeta 8,5 del otro lado**, y la
tarjeta salía descentrada.

Se veía poco porque las seis desbordaban lo mismo. Saltó al agrandar los rótulos:
«GESTIÓN» se subía encima de la tira del registro, que terminaba cuatro puntos
más allá de donde la cuenta decía.

| | Declarado | Útil (`ANCHO_VALOR*`) |
| --- | ---: | ---: |
| Tira entera | 126 | 122 |
| Mitad del renglón partido | 42 | 38 |

### El registro y la gestión comparten renglón

Es el único de los seis que lleva dos pares, y van juntos porque **el número solo
no alcanza**: el registro es el id del carnet y los carnets son por año, así que
se reinicia con cada gestión. El `000002` de 2026 y el `000002` de 2027 son dos
credenciales distintas con el mismo número impreso, y quien lee el plástico en un
control necesita los dos datos a la vez para saber de cuál está hablando.

Comparten renglón y no ocupan uno propio porque en una CR80 no entran siete
líneas sueltas sin apretar todo lo demás; el registro son seis dígitos fijos y le
sobra media tira. El corte va a la mitad: 46 pt para cada valor, con el rótulo
«GESTIÓN» en el medio. En la vista previa lo mismo se pide con dos `flex-1`.

### Los rótulos van en blanco perfilado, y los valores en verde oscuro

El blanco es lo que separa el andamio del dato. «NOMBRE» no es información: es el
cartelito que dice qué se está leyendo. En el verde oscuro del valor pesaba lo
mismo que el nombre de la persona y el ojo tenía que descartarlo en cada renglón.

**Pero el blanco solo no alcanza sobre este fondo.** El verde del plástico es
claro, y no es liso: abajo corre el sello de agua del SEDAG, que le cambia el
tono al rótulo según por dónde pase. A 4,6 pt, el blanco puro se desdibuja en los
tramos claros del sello y desaparece del todo en un carnet impreso con poco
tóner. Así que van **perfilados en verde oscuro** —el `:` también, que es andamio
igual que el rótulo—, y se leen recortados en cualquier tramo del fondo.

El contorno es el mismo truco del título, y vive en una sola parte:
[`documentos/partes/texto-perfilado.blade.php`](../../resources/views/documentos/partes/texto-perfilado.blade.php).
DomPDF no tiene `-webkit-text-stroke` ni `text-shadow`, así que el texto se
dibuja **cinco veces**: cuatro copias del color del borde corridas hacia cada
esquina y la cara encima. En pantalla alcanza con `text-shadow` en cuatro
direcciones.

> **El corrimiento se elige contra el cuerpo, no contra el gusto.** El título va
> corrido 0,5 pt a 11 pt; los rótulos, 0,3 pt a 4,6 — más o menos un 6% del
> cuerpo en los dos casos. Medio punto sobre 4,6 pt no perfila: engorda la letra
> hasta cerrarle los huecos —la «O» se llena, la «E» se vuelve una mancha— y el
> rótulo deja de leerse, que es lo contrario de lo que el contorno viene a hacer.

### El encabezado va en DOS PISOS, no en dos columnas

Arriba el lockup de la Gobernación —escudo, filete y texto, centrados **como
grupo**— y debajo el bloque de la Secretaría a todo el ancho, también centrado.
Es la credencial que se tomó de modelo.

> **El filete va DENTRO del lockup**, entre el escudo y el texto, y es blanco
> porque acompaña al texto. No es el de la versión anterior, que separaba dos
> columnas: ese desapareció al apilar.
>
> Sus 0,7 pt y sus dos calles de 2,8 **forman parte del ancho del grupo que se
> centra**, así que no se lo puede correr solo:
>
> ```
> escudo   77    -> 97,7      (24 pt de alto, 20,7 de ancho)
> filete   100,5 -> 101,2
> texto    104   -> 166
> grupo    89 pt, centrado en 243 -> arranca en 77
> ```

**Y apilados entran más grandes**, que es la ventaja de fondo. Al costado, el
bloque del SEDAG tenía 112 pt de ancho y sus dos líneas largas estaban topadas
—«SECRETARÍA DPTAL. DE DESARROLLO PRODUCTIVO,» ocupaba 102,2 de esos 112, tope
3,83 pt—. A todo el ancho tiene 226, el tope se va a 7,74 y vuelven a entrar de a
**una** línea, sin los cortes forzados que la versión al costado necesitaba.

**Los dos pisos se pintan al revés uno del otro, y eso cierra la regla de toda la
tarjeta.** El lockup va en blanco perfilado directo sobre el verde; el bloque del
SEDAG va en negro dentro de su cuadro claro.

Es la misma regla que ordena el resto de la carilla: **lo que es dato va negro
sobre blanco, lo que es andamio va claro sobre el verde.** Y resuelve el
contraste, que era el problema pendiente: el bloque del SEDAG era lo último que
seguía en `#14350f` sobre un fondo que ya había bajado dos escalones.

El lockup usa el mismo `texto-perfilado` de los rótulos porque el problema es el
mismo —sobre este verde, un texto claro sin borde se desdibuja donde pasa el
sello— y el contorno de 0,2 pt rinde todavía más fino sobre 7 pt que sobre los
4,6 para los que se calibró, que es justo lo que se busca: que se lean blancas.

**Es un cuadro con borde y esquinas redondeadas**, dentro del margen: relleno
`rgba(232, 238, 210, 0.65)`, borde `#3d6b17` de 0,7 pt y radio de 9 sobre una
caja de 22, que lo deja casi como una pastilla — la forma que tiene en la
credencial de referencia.

**El relleno es semitransparente**, así que el sello de agua se ve pasar por
debajo y el cuadro pertenece al fondo en vez de recortarse contra él. En blanco
opaco competía con las tiras de los datos, que sí lo son.

> ⚠️ **ES EL ÚNICO `rgba()` DE LA TARJETA**, y va contra lo que el proyecto tiene
> anotado: «el soporte de rgba en DomPDF depende de la versión». Se comprobó
> midiendo el PDF en vez de suponer — con la **3.1.6** que corre acá el relleno
> sale en `173,194,129`, que es la mezcla real contra el verde; si saliera opaco
> daría `232,238,210`.
>
> Por eso mismo **queda atado a la versión**. En un DomPDF viejo se dibuja opaco
> —no revienta, solo pierde la transparencia— y el reemplazo es un sólido ya
> mezclado, como el que usa el resto del carnet.
>
> El 0,65 tampoco es al gusto: es lo más transparente que aguanta el texto negro
> encima. A 0,55 el engranaje del sello se le mete por detrás a «SEDAG - BENI».

> `border-radius` lo dibuja DomPDF desde la 2.x y el proyecto corre la 3.1.6, así
> que se puede usar. Es de las poquísimas propiedades modernas que entiende:
> sigue sin haber flex, ni grid, ni degradados.
>
> **El borde SUMA a la medida.** El ancho declarado es 224,6 y no 226 porque con
> sus dos líneas de 0,7 da los 226 que van de margen a margen; y el relleno bajó
> a 0,5 por lo mismo, que también suma al alto. A propósito: una franja que cruza entera lee como una **faja**
que separa los dos pisos del encabezado, mientras que la misma franja con
márgenes lee como una caja de dato más — son dos cosas distintas y conviene que
se vean distintas.

**Y su texto va con serifas**, como la credencial de referencia: es lo único de
la carilla que no usa DejaVu Sans. Se eligió DejaVu Serif y no Times porque es la
única serif que DomPDF trae con los acentos completos — con Times, el «Í» de
«SECRETARÍA» depende de la codificación. Ojo: **la serif es un 5% más ancha** que
la sans al mismo cuerpo; a 5,5 pt la línea larga mide 169,5 de los 235 útiles, y
su tope real es 7,63.

> ⚠️ **El relleno de la banda suma al alto del encabezado**, que es la medida más
> ajustada de la tarjeta. Sus 2 pt se compensaron sacándole la separación que
> tenía «SEDAG - BENI» y subiendo el lockup medio punto; sin eso, la banda se
> comía el título.

«GOBERNACIÓN», «BENI» y «SEDAG - BENI» van los tres al mismo cuerpo (7 pt), a
pedido. Las dos líneas de la Secretaría van a 5,5 justamente para que ese tercero
entre a 7 sin empujar el título.

> ⚠️ **EL COSTO ES VERTICAL, y ata todas las coordenadas de la carilla.** Dos
> pisos ocupan lo que antes ocupaba uno al lado del otro, así que el encabezado
> cierra en 52 y no en 31, y empuja todo lo de abajo:
>
> | | de | a |
> | --- | ---: | ---: |
> | lockup | 2 | 28,5 |
> | banda del SEDAG | 29 | 51,7 |
> | título | 53 | 64 |
> | renglones | 66 | 149 (seis, cada 14) |
> | margen | | 4 hasta los 153 de la carilla |
>
> Quedan 4 pt de aire abajo. **Si el encabezado vuelve a cambiar de alto, estas
> cinco medidas se mueven juntas** — no hay lugar para que una crezca sola.

> **Cada renglón del encabezado lleva su alto escrito**, y no se lo deja al
> `line-height`. La caja de línea que DomPDF le da a DejaVu Sans es bastante más
> alta que el cuerpo —del orden de 1,17 em—, así que con `line-height: 1.05` el
> bloque medía más de lo que la cuenta decía y «BENI» terminaba dibujado encima
> de la primera línea del SEDAG. Con el alto escrito, apilar dos bloques vuelve a
> ser sumar.

### El título bajó de 15 pt a 9,5, en dos pasos

En el plástico de papel ocupaba casi un tercio de la tarjeta. Acá los seis
renglones empiezan más arriba, así que a ese cuerpo se comía el aire que separa
el encabezado de los datos.

**Y acá se frena.** La palabra tiene que seguir siendo lo más grande de la
carilla: es lo que hace que la credencial se reconozca de lejos —el inspector la
identifica por su forma antes de leer nada—, y por debajo de este cuerpo deja de
pesar más que el encabezado.

> El corrimiento del contorno (0,45 pt) y el interletrado bajaron en la misma
> proporción que el cuerpo. El contorno es un ~4,5% del cuerpo y **el título
> entero tiene que escalar junto**, o al achicarse se deforma: el mismo borde
> sobre una letra más chica la engorda, que es la trampa que ya había aparecido
> con los rótulos.

### El título dice «CÉDULA DE <RUBRO>»

**Ya no.** Hoy dice «CÉDULA DE PESCADOR», con el rubro adentro, igual que el
plástico de papel.

Decía «CÉDULA» a secas mientras el carnet era UNO para todas las actividades de
una persona: nombrar una habría dicho algo que el documento no era. Con un carnet
por rubro, el título es lo que se lee de lejos —antes que cualquier renglón— y
puede decirlo.

**El largo lo decide el catálogo**, así que el título se achica si no entra, igual
que los renglones. A 9,5 pt con 0,8 de interletrado cada carácter ocupa ~6,55 pt
y en los 230 pt útiles entran unos 35:

| Título | Caracteres | Ancho | Resultado |
| --- | --- | --- | --- |
| `CÉDULA DE PESCADOR` | 18 | ~118 pt | entra holgado |
| `CÉDULA DE COMERCIALIZADOR` | 25 | ~164 pt | entra |
| más de 30 | — | — | clase `.titulo.largo`: 7,6 pt |

> **La variante baja las TRES medidas juntas** —cuerpo, interletrado y
> corrimiento del contorno—. Achicar solo la letra dejaría un borde de 0,45 pt
> sobre un cuerpo de 7,6: pasa de ser un 6% a un 9% y engorda la letra hasta
> cerrarle los huecos, que es justo lo que el perfilado viene a evitar.

Lo arma `CarnetImpresionController::titulo()`, y `vista-previa-carnet.tsx` repite
el mismo umbral. Los dos tienen que moverse juntos.

### El rubro y el cupo SÍ van; el vencimiento no

Esta sección decía lo contrario, y vale la pena leer por qué cambió: **el motivo
viejo era bueno, y lo que cambió no fue la opinión sino el sistema.**

Con el modelo anterior —un carnet por persona, con los rubros colgados en
`carnet_rubro`— el plástico no llevaba ni la lista de rubros ni el cupo:

- quien era pescador podía sumar comercializador en octubre con una adición, y el
  plástico **no cambiaba**: mismo carnet, mismo registro. Impresa, la lista
  quedaba vieja ese mismo día y el documento pasaba a decir MENOS de lo que la
  persona estaba autorizada a hacer — peor que no decir nada;
- el cupo era un tope POR ACTIVIDAD, así que con dos rubros había dos cupos y un
  único renglón donde ponerlos.

Hoy el carnet es de **un** rubro, y ese rubro es parte de la llave que lo
identifica: no cambia nunca. No queda nada que pueda dejar vieja la impresión, y
el cupo es uno solo.

**Y es más que una posibilidad: es necesario.** Dos carnets de la misma persona
en la misma gestión son dos plásticos con el mismo nombre, la misma foto y el
mismo domicilio. Sin el rubro impreso, nada los distingue a simple vista — y el
número de registro no ayuda, porque nadie compara seis dígitos en un control.

Lo que sigue sin imprimirse:

- **El ESTADO.** Un carnet se suspende o se anula DESPUÉS de impreso y la tarjeta
  no se entera. Es el mismo criterio de siempre —en un documento impreso va lo
  que no cambia— aplicado a lo que de verdad cambia.
- **La fecha de vencimiento.** Todos los carnets de una gestión vencen el
  mismo día —el 31 de diciembre, ver `Carnet::vencimientoDeGestion()`— así que
  **con la gestión impresa la fecha no agrega nada**: 2026 ya dice 31/12/2026.
  Y si la pregunta es si HOY vale, la fecha impresa nunca fue la respuesta: un
  carnet puede estar anulado con su fecha intacta.

  > Antes de que la gestión se imprimiera, esta ausencia se justificaba diciendo
  > que «el año del registro ya lo dice». **No lo decía**: el registro es
  > `000013`, seis dígitos sin año. El dato faltaba de verdad, y el renglón
  > compartido es lo que lo tapó.

El estado y la fecha se consultan en la ficha del carnet, en el panel.

### Las tiras van blancas y la letra negra fina, como una cédula de identidad

El primer diseño las pintaba de crema (`#f6faee`) con la letra en verde oscuro
negrita. Con el verde del fondo ya bajado al del plástico, el crema se leía como
un papel viejo; y la negrita tenía sentido cuando todo competía sobre un verde
claro, pero sobre blanco puro no hace falta gritar — a 4,6 pt sólo empasta las
letras entre sí.

Queda entonces el reparto que tiene cualquier documento de identidad: **el dato
en negro fino sobre blanco, y el andamio —rótulos y `:`— en blanco perfilado
sobre el verde**. Lo que se lee primero es el dato.

> ⚠️ **`ANCHO_POR_CARACTER` está atado a ese grueso de letra**, y hay que moverlo
> si el grueso cambia. Medido sobre las DejaVu Sans que embebe DomPDF con los
> textos que salen de verdad en un carnet: la **regular** promedia 0,539 em por
> carácter y la **negrita** 0,605. Al pasar la tira a regular, la constante bajó
> de 0,62 a 0,55 — dejada en 0,62 no rompía nada, pero creía que el texto ocupaba
> un 13% más de lo que ocupa y achicaba nombres que entraban enteros.
>
> El margen que queda sobre el promedio (~2%) **es a propósito**: el peor caso
> medido es 0,67 —un texto de puras mayúsculas ocupa bastante más que el
> promedio— y quedarse corto sería peor que pasarse, porque lo que no entra lo
> recorta el `overflow: hidden` de la tira y ahí se pierden apellidos.

### La cédula tiene su propia tira, atada al ancho de la foto

Es un DATO de la persona, no un rótulo, así que va sobre blanco como los demás;
era el único que quedaba suelto sobre el verde.

**El ancho no se eligió: está atado al de la foto.** 43,6 pt de caja más 4 de
relleno son los 47,6 que mide el recuadro de la foto con su borde, así que los
dos cierran contra la misma vertical. Y tiene que estar atado: la columna de
datos arranca en 60 pt, y la caja anterior —80 pt desde el margen— llegaba hasta
88,5. Mientras fue texto suelto sobre verde eso no se notaba, porque la cédula es
corta; **con fondo blanco, la caja se habría metido por debajo del renglón de
PROVINCIA**.

Pasa además por el mismo `texto()` que los renglones: una cédula larga —con
expedición y complemento— se dibuja más chica antes que salir recortada. Un
número de documento al que le falta el final no identifica a nadie.

### Los renglones sin dato no salen en blanco

Salen con su texto atenuado diciendo qué falta: «Sin cargar en la ficha», «Sin
asociación declarada». Una tira vacía en una credencial se lee como un error del
sistema; el molde le dice al operador exactamente dónde ir a completarlo.

### Un valor largo se achica, no se corta

La vista previa corta con `truncate`, y en pantalla está bien: es una maqueta y
el dato completo está en la ficha. **En el plástico no.** «María Esperanza del
Carmen Justiniano Vaca Guzmán de Suárez Vilinga» cortado en «María Esperanza del
Carmen» pierde los apellidos, que son justamente lo que identifica a la persona
en un control.

Lo resuelve `CarnetImpresionController::texto()`:

| Intento | Cuerpo | Líneas | Alto de la tira |
| --- | --- | --- | --- |
| 1 | 6 pt | 1 | 9,5 pt |
| 2 | el que haga falta, hasta 4,2 pt | 1 | 9,5 pt |
| 3 | 4,5 pt | 2 | 12,6 pt |

Dos líneas y no tres: el renglón siguiente está plantado 14 pt más abajo.

> **Dos cosas que costaron una vuelta cada una.**
>
> El ancho contra el que se mide es el de la TIRA menos su relleno (126 pt), no
> el de la columna (176 − 41). Calculado sobre la columna, el sistema creía que
> entraban treinta caracteres más de los que entran y los nombres largos salían
> cortados — justo lo que este cálculo viene a evitar.
>
> Y el alto de la tira lo manda el controlador. Con un alto fijo de un renglón,
> el valor que pasaba a dos líneas se dibujaba igual y la segunda quedaba cortada
> por la mitad, que se ve peor que si nunca hubiera entrado.

---

## 5. La tarjeta no lleva QR, y eso tiene un costo

**Lo tuvo y se sacó a pedido.** Conviene tener presente qué se perdió:

La verificación pública sigue existiendo —la pantalla, la firma de validación,
`VerificacionController::datosPublicos()`, todo— pero **desde el plástico ya no
hay forma de llegar a ella**. Quien tenga el carnet en la mano no puede
comprobar si es real ni ver qué rubros habilita; eso ahora solo se consulta
desde el panel.

Es la misma limitación que tenía la credencial de papel, y era su problema
central: quien la miraba tenía que creerle.

Lo que sí se imprime es el **número de registro** —`000013`, el id del carnet—:
corto, dictable por teléfono y, sobre todo, inofensivo, porque no abre nada. La
**firma de validación** no se imprime en ningún lado del plástico.

> `App\Support\CodigoQr` **queda escrito y sin usar**, igual que quedó
> `CorrelativoService` en su momento: el día que el QR vuelva, la parte difícil
> —que la salida PNG no depende de `imagick`, que no está en este servidor— ya
> está resuelta. Ver §8.

---

## 6. El PDF

### No se guarda en disco

Se arma al vuelo. Se deduce entero de la fila del carnet, así que el de mañana
sale idéntico al de hoy; guardarlo sería un archivo más que limpiar —y con el
disco en s3, uno que no se puede borrar—. Por eso `CarnetImpresionController`
**no pasa por `StorageController`**: no escribe nada.

Mismo criterio que el recibo.

### La maqueta

`resources/views/documentos/carnet-pescador.blade.php`.

**CR80: 243 × 153 puntos** (85,6 × 54 mm), apaisado, fijado en
`CarnetImpresionController` y no en la plantilla — es una decisión de impresión.
Es lo que mide una cédula de identidad o una tarjeta de crédito, así que entra en
las impresoras de credenciales y en las fundas que ya se compran.

```
margen               =   8,5
columna izquierda    =    46   ->  cédula y foto
calle                =   5,5
columna de datos     =   176   ->  hasta el margen derecho
rótulos              =    41   ->  los valores arrancan todos parejos
```

> Si se cambia el tamaño en el controlador hay que revisar las coordenadas del
> Blade: están calculadas para estos 243 × 153.

**Todo va en `position: absolute`**, por las dos razones de siempre: es una
tarjeta de medida fija que tiene que salir SIEMPRE igual —el troquel no perdona
medio milímetro— y lo dibuja DomPDF, que no es un navegador.

### El fondo viene horneado en un PNG

`carnet-fondo.png` trae el degradado verde **y** el sello del SEDAG ya atenuado,
en un solo archivo de 674 × 425 px. Son las dos cosas que DomPDF no hace bien:

- no entiende `linear-gradient` — dibujaría un rectángulo de color liso;
- su `opacity` es de lo menos confiable que tiene, y según la versión la ignora:
  el sello saldría a pleno color tapando los datos.

Horneadas en el archivo no pueden fallar. Es la misma decisión que
`recibo-sello.png`, llevada al fondo entero.

#### El verde es el del plástico, y se oscureció contra una foto del carnet real

El primero salió de la muestra en pantalla y quedó **demasiado claro**: al ponerlo
al lado de una cédula plastificada de verdad, la impresa se veía lavada. El verde
se bajó hasta el del plástico.

| | Antes | Ahora |
| --- | --- | --- |
| Arriba | `#a9d152` | `#719327` |
| Medio (55%) | `#8ec63f` | `#5c8b18` |
| Abajo | `#7fbb34` | `#518411` |

> **El PNG no se volvió a generar: se recoloreó.** Reconstruirlo obligaba a
> rehacer la mezcla del sello contra el degradado, y esa mezcla ya estaba bien.
> Se le aplicó un ajuste **en HSV** sobre el archivo entero, en dos pasos
> —`V × 0,82 · S × 1,14`, y después `V × 0,86 · S × 1,06`— que baja el brillo sin mover el tono y deja el sello con el mismo
> contraste relativo contra el fondo. Multiplicar el RGB a secas, que es lo
> primero que uno prueba, apaga el verde hacia el oliva: el plástico es un verde
> **vivo**, no un verde sucio. Son los mismos números que llevan los tres topes
> del degradado de la vista previa, así que para volver a moverlo alcanza con
> aplicar el mismo ajuste a las dos mitades.

> ⚠️ **El fondo se toca en DOS lugares.** El PNG es solo la mitad del PDF: la
> vista previa dibuja su verde con un `linear-gradient` de CSS, porque en
> pantalla sí existe. Cambiar uno y no el otro es exactamente lo que el recuadro
> «así va a salir el carnet» viene a evitar.

El generador reproduce la fórmula de CSS para que el verde del papel sea
exactamente el de la pantalla.

### El contorno dorado del título son cinco copias

En el plástico las letras van rojas con un contorno dorado. DomPDF no tiene
`-webkit-text-stroke` ni `text-shadow`, así que el contorno se hace a mano: el
mismo texto se dibuja **cinco veces** —cuatro en dorado, corridas 0,7 pt hacia
cada esquina, y la quinta en rojo encima—.

Parece un truco y lo es, pero es el único que sale igual en todas las versiones
de DomPDF. En la vista previa, que sí corre en un navegador, alcanza con
`text-shadow` en cuatro direcciones.

### El peso

Unos **156 KB por carnet**. El fondo es casi todo: 99 KB del PNG.

### Trampas de DomPDF que ya estaban resueltas en el recibo

| Trampa | Solución |
| --- | --- |
| No entiende flexbox, grid ni variables CSS | `position: absolute` y tablas |
| Una ruta `/image/...` se resuelve con las restricciones de `chroot` y en producción sale un recuadro vacío | Todo embebido en base64 |
| Solo DejaVu Sans trae acentos y «ñ» completos | `font-family: 'DejaVu Sans'` |
| Embebe las fuentes COMPLETAS en cada PDF (~760 KB) | `enable_font_subsetting` |
| `rgba()` depende de la versión | Todo va en sólido, salvo el relleno del cuadro del SEDAG — ahí se midió que la 3.1.6 sí lo respeta |

---

## 7. La foto del titular

> **ARRANCA EN 76 pt, NO EN 66.** Estaba a la misma altura que el renglón
> NOMBRE, y la columna izquierda terminaba 23 pt antes que la derecha —foto y
> cédula cerraban en 122,5 y el último renglón en 145,5—. Bajada 10 pt, las dos
> columnas quedan parejas a la vista. La tira del C.I. la acompaña: 115 → 125.
>
> **EL RECUADRO ES CUADRADO Y TIENE QUE SEGUIRLO SIENDO:** 46 × 46 pt más 0,8 de
> borde por lado. En DomPDF el borde SUMA al ancho declarado —igual que el
> padding— así que ocupa 47,6 × 47,6: sigue cuadrado porque los dos lados crecen
> igual. Tocar uno solo lo deforma.
>
> Y OJO: el ancho está atado a dos cosas más. La tira de la cédula mide 43,6
> —los 47,6 del recuadro menos su relleno— y la columna de datos arranca en 60.
> Cambiar el ancho de la foto obliga a mover las dos.

**Es un cuadrado de 46 × 46 pt (16 mm)**, un retrato tipo carnet como el de la
credencial de papel.

### Se recorta, no se aplasta

**DomPDF no tiene `object-fit`.** Poniéndole `width` y `height` a la imagen, un
retrato vertical metido en el cuadrado sale APLASTADO: la cara más ancha de lo
que es. En un documento de identidad eso es justamente lo que no puede pasar,
porque la foto es lo que se compara contra la persona.

Así que `CarnetImpresionController::fotoEmbebida()` hace a mano lo que haría
`object-fit: cover`: la imagen se dibuja a su proporción REAL, desbordando el
recuadro por el lado que sobra, corrida con un margen negativo; el
`overflow: hidden` del recuadro recorta lo que asoma.

> **En un retrato el corte va a un TERCIO, no a la mitad.** La cara está en el
> tercio superior de la foto: partiendo al medio se come la frente y sobra torso.

### Se reduce antes de embeberla

A **300 px de lado**. La foto se guarda tal como la subió ventanilla, que es lo
que salió de un teléfono; embebida entera hacía un carnet de **442 KB** para
dibujar un cuadradito de 16 mm, y la unidad imprime decenas por día.

### De dónde se leen los bytes

Va por `Archivos::contenido()` y no por su URL: el servidor tendría que salir a
buscarse a sí mismo por HTTP para dibujarla —con su timeout y su proxy— y eso
falla en cualquier despliegue donde el bucket no sea público.

El tipo de imagen sale de los **bytes** y no de la extensión del nombre: el
archivo se guardó con un nombre al azar (ver `StorageController`) y la extensión
que mandó el navegador no es prueba de nada.

**Sin foto el carnet sale igual, con el recuadro vacío.** Es lo mismo que hacía
la unidad con la cédula de papel cuando la persona traía la foto después, y la
vista previa lo avisa antes de llegar a la impresora: «La ficha no tiene
fotografía». Un error 500 acá dejaría a ventanilla sin poder imprimir nada.

La foto **no se carga en el formulario del trámite**: es un dato del padrón, se
carga en la ficha del beneficiario.

---

## 8. `App\Support\CodigoQr` — escrito y sin usar

Hoy la tarjeta no lleva QR (§5), así que esta clase no la llama nadie. Se
conserva porque la parte difícil ya está resuelta:

**`simplesoftwareio/simple-qrcode` no sirve en este servidor.** Su salida PNG
necesita la extensión `imagick`, que no está instalada (`php -m` lista `gd`), y
revienta con «Extension 'Imagick' is required». La otra salida que ofrece es
SVG, y DomPDF trae `php-svg-lib` para dibujarlo — pero un QR es justamente el
elemento donde no conviene depender de un renderizador aproximado: si los módulos
salen medio punto corridos la cámara deja de leerlo, y eso no se descubre hasta
que alguien intenta verificar un carnet en la calle.

`CodigoQr` usa **BaconQrCode** directamente —la librería que ese paquete trae
adentro— para obtener la matriz, y la pinta con `gd` cuadrito por cuadrito: cada
módulo mide un número entero de píxeles, así que no hay interpolación posible.
Corrección de errores en nivel **Q** (25%), 8 px por módulo y 4 módulos de zona
tranquila.

> Se verificó módulo por módulo contra la matriz de la librería: 0 desajustes y
> la zona tranquila limpia.

---

## 9. En la pantalla

El botón **«Imprimir carnet»** aparece en dos lugares:

- la ficha del trámite (`pages/panel/tramites/ver.tsx`), junto a los del
  circuito;
- la ficha del carnet (`pages/panel/carnets/ver.tsx`), al lado de «Anular».

En los dos casos lo gobierna la prop `puede_imprimirse`, que **calcula el
servidor**.

### El botón NO abre el PDF: abre la vista previa

`DialogoImprimirCarnet` muestra el carnet en pantalla y recién después se manda
a la impresora. Lo que se imprime es un plástico que se troquela y se lamina: no
se corrige, así que conviene mirarlo antes — sobre todo para descubrir la ficha
sin foto.

**Lo que muestra es el PDF DE VERDAD, no una maqueta.** El `iframe` apunta a la
misma dirección que imprime, así que lo que se ve es exactamente el archivo que
va a salir. Podría haberse dibujado con `VistaPreviaCarnet` y habría sido más
rápido, pero una maqueta es una PROMESA de cómo va a salir algo, y para decidir
si mandar a imprimir hace falta ver la cosa. Si algún día la maqueta y el PDF se
separan, este diálogo es donde se tiene que notar.

El PDF se pide **recién al abrir**: el `iframe` se monta con el diálogo. Montado
siempre, cada visita a una ficha pediría un PDF que nadie va a mirar.

> **No hay un botón que llame a `print()`.** Con un PDF el visor del navegador lo
> ignora o lo bloquea según la versión, y un botón que a veces no hace nada es
> peor que no tenerlo. Se imprime desde la barra del propio visor o desde la
> pestaña aparte, que es lo que ofrece «Abrir en pestaña nueva» — y ese sí va
> como `<a target="_blank">`, porque una navegación de Inertia no sabe qué hacer
> con un archivo.
>
> Por lo mismo, el «Generando el carnet…» **no puede depender de `onLoad`**:
> medido, con un PDF ese evento no llega nunca en algunos navegadores.

---

## 10. Archivos del módulo

| Archivo | Qué es |
| --- | --- |
| `app/Http/Controllers/Panel/CarnetImpresionController.php` | Arma el PDF y resuelve los renglones |
| `resources/views/documentos/partes/texto-perfilado.blade.php` | Las cinco copias del texto con contorno, para el título y los rótulos |
| `resources/views/documentos/carnet-pescador.blade.php` | La maqueta impresa |
| `resources/js/components/panel/tramites/vista-previa-carnet.tsx` | El mismo molde en pantalla. **Espejo del Blade** |
| `resources/js/components/panel/carnets/dialogo-imprimir-carnet.tsx` | La vista previa antes de imprimir |
| `public/image/carnet-fondo.png` | El verde con el sello horneado |
| `public/image/carnet-escudo.png` | El escudo del encabezado, recortado de `recibo-escudo.png` |
| `app/Support/CodigoQr.php` | El QR. **Escrito y sin usar** — ver §8 |

Tocados: `Carnet` (`puedeImprimirse()`, `urlVerificacion()`), `Archivos`
(`contenido()`), `CarnetController` (se le quitó `urlVerificacion()`),
`TramiteController::show()`, `routes/panel.php`,
`resources/js/pages/panel/{carnets,tramites}/ver.tsx`,
`resources/js/types/{carnets,tramites}.ts`.

---

## 11. Lo que quedó afuera

- **El QR.** Se sacó a pedido; ver §5 y §8 por lo que eso implica y por lo que
  quedó listo para cuando vuelva.
- **Impresión por lotes.** Sacar de una todos los carnets aprobados y sin
  imprimir de una gestión. `PENDIENTES.md` ya pedía el padrón para eso.
- **Marcar impreso automáticamente** al abrir el PDF. Se decidió que no: ver §3.
- **El reverso.** Existió —titular, vigencia y leyenda legal— y se sacó: el
  plástico se imprime de una sola cara. Si vuelve, el `.carilla` relativo y un
  `page-break-before` son lo único que hace falta.
- **Una silueta en el recuadro de la foto vacío**, como la que dibuja la vista
  previa. En pantalla explica que falta cargarla; impresa en un plástico que se
  entrega, sería un dibujo raro en el lugar de la cara.
