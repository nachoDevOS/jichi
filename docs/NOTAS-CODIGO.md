# Notas de código

> Lo que antes estaba escrito en comentarios largos dentro del código.
> Se movió acá el 20/09/2026 para que los archivos se lean de corrido: en el
> código quedó el primer párrafo de cada bloque —el porqué en dos líneas— y el
> desarrollo está abajo, buscable por archivo.

---


## `app/Enums/ConceptoRecibo.php`

### LAS CASILLAS DE «DESCRIPCIÓN» DEL RECIBO OFICIAL

   El talonario del SEDAG trae seis renglones con un paréntesis adelante, y el
   cajero marca el que corresponde:
   ( ) Permiso por Faena
   ( ) Solicitud de Importe de Alevines
   ( ) Autorización de Pesca para Aprovechamiento Pesquero
   ( ) Guía única de Transporte
   ( ) Cédulas
   ( ) Otros
   SE IMPRIMEN LOS SEIS, SIEMPRE
   Aunque el sistema solo sepa cobrar dos de ellos. El recibo tiene que salir
   IGUAL al papel: quien lo recibe está acostumbrado a esa lista, y una versión
   recortada se lee como si fuera otro documento. Lo que cambia es cuál queda
   marcado.
   LA CASILLA DICE QUÉ SE COBRÓ, NO QUÉ ACTIVIDAD HABILITA
   Y esta distinción costó un error. Las seis casillas son los SERVICIOS que
   cobra el SEDAG por ventanilla, cada uno con su propio trámite:
   Permiso por Faena         un permiso puntual de pesca
   Importe de Alevines       la solicitud de alevines
   Autorización de Pesca     el aprovechamiento pesquero
   Guía única de Transporte  la guía que acompaña un cargamento
   Cédulas                   ← LA CREDENCIAL. Esto es lo que emite Jichi
   Otros
   Este sistema emite **carnets**, que en el mostrador se llaman «cédula de
   pescador». Cobre lo que cobre —emisión inicial o actualización, Pescador o
   Comercializador— lo que el pescador se lleva es su cédula, y esa es la
   casilla que corresponde.
   EL RUBRO NO ENTRA EN ESTA DECISIÓN. «Pescador» y «Comercializador» son las
   actividades que el carnet HABILITA, no servicios distintos del talonario.
   Marcar «Guía única de Transporte» porque el rubro se llama Comercializador
   era confundir la actividad autorizada con el papel que se está cobrando: el
   pescador pagaba su carnet y el recibo decía que había pagado una guía.
   Qué rubro se habilitó sí se lee en el recibo, pero donde corresponde: en el
   renglón «Concepto», que dice «Comercializador — Carnet gestión 2026».

### QUÉ CASILLA VA MARCADA, SEGÚN LO QUE SE COBRÓ

   El `match` va sobre la CLASE del pagable y no sobre un texto: es el mismo
   dato, pero así el analizador avisa el día que se agregue un cobrable y
   este método se olvide.
   UN RECIBO PUEDE CUBRIR VARIAS COSAS —el carnet y el cupo en un mismo
   depósito— y el papel tiene UNA sola casilla. Se marca la del PRIMER pago,
   que es el concepto principal; el detalle completo va igual en el cuadro de
   importes, renglón por renglón. Marcar dos casillas sería inventar un papel
   que el talonario no tiene.

## `app/Enums/EstadoAprovechamiento.php`

### En qué situación está la BOLSA MADRE de un pescador.

   NACE PENDIENTE, Y ESO ES LO QUE LO HACE CORREGIBLE
   PENDIENTE ──[enviar, con el monto cubierto]──▶ EN REVISIÓN
   (borrador)                                         │
   ▲                              ┌──────────────┴──────────────┐
   └──────────[rechazar]──────────┤                             │
   [aprobar]                          │
   │                             │
   ACTIVO ──▶ AGOTADO | VENCIDO ────┘
   ENVIAR NO ES APROBAR, y son dos personas distintas. Ventanilla carga los
   depósitos y declara que el expediente está completo; quien firma mira las
   boletas contra el extracto y recién ahí el cupo queda habilitado.
   COBRAR YA NO ACTIVA SOLO. Antes, cubrir el monto pasaba el cupo a ACTIVO en el
   acto: la plata entraba y el pescador salía a pescar sin que nadie mirara las
   boletas. Hoy el cobro solo baja el saldo; el salto lo da una persona.
   Un cupo recién otorgado es un BORRADOR: el operador lo acaba de cargar contra
   el talonario y el pescador todavía está enfrente. Mientras nadie pagó nada,
   equivocarse de tramo o de embarcación se arregla corrigiendo la fila, y un
   cupo cargado por error se ELIMINA con el motivo escrito.
   En cuanto entra el primer boliviano eso deja de valer: hay un recibo numerado
   con el detalle impreso, y cambiar lo que dice ese papel por detrás no es una
   corrección sino otra cosa. Por eso editar y eliminar viven SOLO en pendiente,
   y de ahí en adelante el cupo no se toca más: si hacen falta más kilos,
   eso es un trámite nuevo.
   Y por eso un cupo pendiente TAMPOCO emite faenas: lo que autoriza a pescar es
   la concesión pagada, no el papel a medio llenar.
   TIENE DOS FORMAS DE MORIR, Y HAY QUE PODER DISTINGUIRLAS
   Un aprovechamiento es un cupo de kilos con fecha. Deja de servir por dos
   motivos distintos y la diferencia importa en ventanilla:
   - `Vencido`  — se le acabó el TIEMPO. Puede quedarle volumen sin usar, y
   ese volumen se pierde: no se arrastra a la gestión siguiente.
   - `Agotado`  — se le acabaron los KILOS. La fecha todavía no llegó, pero
   las faenas emitidas ya consumieron el volumen otorgado.
   Al pescador se le dice cosas distintas en cada caso: en uno renueva, en el
   otro tramita un cupo nuevo. Un solo estado «inactivo» obligaría a deducirlo
   comparando fechas y sumando faenas cada vez.
   IGUAL QUE EN CARNETS, ESTA COLUMNA PUEDE ESTAR DESFASADA
   `Agotado` lo escribe quien emite la última faena y `Vencido` un comando
   diario. Para decidir si hoy se puede emitir una faena se usa
   `AprovechamientoPesq::puedeEmitirFaena()`, que mira además la fecha y el
   saldo real en kilos.

### ¿Se pueden corregir sus datos?

   Solo el borrador. Con un pago encima existe un recibo numerado que dice
   qué se cobró y por qué: cambiar el tramo por detrás haría que el papel
   entregado dejara de coincidir con la fila, sin que nada lo delate.
   EN REVISIÓN tampoco: quien firma mira los papeles que se le presentaron, y
   moverlos mientras los está mirando es cambiarle el expediente de abajo.
   Lo que no sirve se RECHAZA, y el rechazo lo devuelve a pendiente.

## `app/Enums/EstadoAsociacion.php`

### Si una asociación se puede elegir hoy en un formulario.

   Una asociación se da de baja cambiando el estado, NUNCA borrando la fila:
   los carnets y las guías ya emitidas apuntan a ella y no pueden quedar
   huérfanos. Una asociación `Inactiva` desaparece de los desplegables, pero
   los documentos históricos la siguen mostrando — es lo correcto, porque la
   persona pertenecía a ella cuando se le emitió el carnet.

## `app/Enums/EstadoCarnet.php`

### En qué situación está una credencial.

   ESTA COLUMNA PUEDE MENTIR, Y ESTÁ BIEN QUE PUEDA
   `Vencido` no se escribe solo el día que corresponde: lo pone un comando
   programado que corre una vez por día. Entre corrida y corrida, un carnet que
   venció ayer sigue diciendo «activo» en la base.
   Por eso NINGUNA decisión se toma leyendo esta columna sola: para saber si un
   carnet vale HOY se mira además `fecha_vencimiento`, que no puede quedar
   desfasada. Ver Carnet::estaVigente().
   ¿Y entonces para qué está la columna? Para dos cosas que la fecha no puede
   dar: distinguir «venció» de «se revocó» —un carnet revocado en marzo tiene la
   fecha de vencimiento de diciembre y la fecha no lo delata— y filtrar o
   agrupar en los listados sin calcular una comparación por fila.

## `app/Enums/EstadoFaena.php`

### En qué situación está un permiso de faena — la autorización de UNA salida.

   «COMPLETADO» ES LO QUE CIERRA EL CIRCUITO DE LA BOLSA MADRE
   Una faena nace `Activo`: el pescador se llevó el papel y salió. Cuando vuelve
   y descarga, la faena pasa a `Completado` — y es en ese momento cuando sus
   `kilos_extraidos` cuentan definitivamente contra el cupo.
   `Vencido` es la faena que se pasó de `fecha_limite` sin cerrarse. NO se borra
   ni se reutiliza el número: el talonario ya gastó esa hoja.
   Los kilos de una faena activa o completada se descuentan igual del saldo —lo
   contrario dejaría emitir faenas infinitas mientras ninguna se cierre—; los de
   una vencida se liberan. Ver AprovechamientoPesq::kilosConsumidos().

## `app/Enums/EstadoGuia.php`

### En qué situación está una guía de movimiento — el amparo de UN traslado.

   POR QUÉ SE ANULA Y NO SE BORRA
   El número sale de un TALONARIO DE PAPEL que el comercializador se llevó.
   Emitida mal, esa hoja puede estar circulando en un camión. Borrar la fila
   deja un hueco en la serie que después nadie puede explicar, y peor: libera un
   `codigo_guia` que el índice único volvería a aceptar, así que dos traslados
   distintos podrían terminar diciendo ser el mismo papel.
   `Cerrada` es la que llegó a destino y se descargó; `Anulada` es la que se dio
   de baja con motivo. La diferencia importa en un control de ruta: una guía
   cerrada amparó un traslado real, una anulada nunca amparó nada.

## `app/Enums/EstadoValidacionPago.php`

### El estado del CONTROL de un depósito, que no es el estado del pago.

   PENDIENTE ──▶ VALIDADO    cuadra con el extracto del banco
   ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
   └──[corregir]──┘
   Un OBSERVADO sigue sumando en `montoPagado()`: la plata está, lo que se duda
   es si la boleta respalda lo que dice. Ver docs/MER.md.

## `app/Enums/ModalidadAprovechamiento.php`

### LAS DOS VERTIENTES DEL APROVECHAMIENTO PESQUERO

   No son dos tablas ni dos flujos: son la MISMA bolsa madre con dos reglas de
   recarga distintas. Lo que cambia entre una y otra es qué se puede hacer
   cuando el cupo se agota.
   POR QUÉ ES UN ENUM Y NO UNA COLUMNA BOOLEANA `es_paiche`
   Porque el criterio no es la especie sino el RÉGIMEN. Hoy la única especie
   especial es el paiche; mañana la resolución puede sumar otra, y con un
   booleano llamado por la especie habría que renombrar la columna —o peor,
   dejarla mintiendo—. Con el enum, agregar una especie es marcar su tramo de la
   escala con la modalidad que ya existe.
   Y POR QUÉ VIVE EN EL TRAMO DE LA ESCALA
   La modalidad la fija la resolución al definir el tramo —«1001 kg Hasta 2000 Kg
   PAICHE» ES el régimen especial—, no el operador al otorgar. Puesta en el
   formulario de otorgamiento sería una decisión de ventanilla, y entonces dos
   cupos del mismo tramo podrían tener reglas distintas.
   Se COPIA al aprovechamiento al otorgarlo, por lo mismo que el volumen: si
   alguien reclasifica el tramo en el catálogo, los cupos ya otorgados no pueden
   cambiar de régimen retroactivamente.

## `app/Enums/RolSistema.php`

### Los roles del sistema y qué puede hacer cada uno.

   POR AHORA HAY UN SOLO ROL, Y ES A PROPÓSITO
   El sistema arranca con `administrador` haciendo todo. Supervisor, operador de
   ventanilla y solo-lectura se agregarán cuando la unidad defina quién firma
   qué; mientras tanto, inventar roles que nadie usa solo obliga a mantenerlos.
   LO QUE SÍ QUEDA ARMADO es la lista de permisos, y las rutas los exigen uno por
   uno (ver el middleware `permiso:` en routes/panel.php). Esa parte no se saca
   aunque hoy el único rol los tenga todos: el día que aparezca el segundo rol,
   se agrega un `case` acá con su lista y las rutas ya están protegidas. Si en
   cambio se quitara el middleware «porque total el admin puede todo», habría que
   volver a repartir permisos ruta por ruta, que es justamente donde se olvida
   uno y queda un agujero.
   Este enum es la ÚNICA fuente de verdad. De acá los lee RolPermisoSeeder para
   crearlos en la base, y contra estos nombres comprueba el middleware.

### Permisos asignados al rol durante el seeding.

   LOS BLOQUES SIGUEN EL FLUJO DE TRABAJO, NO EL ABECEDARIO
   `lectura` es mirar. `operacion` es lo que hace ventanilla todos los días.
   `supervision` es lo que ROMPE algo ya emitido —anular, revocar— y por eso
   no puede estar en las mismas manos que emitir. `administracion` es el
   catálogo y las cuentas, que se tocan una vez cada tanto.
   Están separados aunque hoy el único rol se los lleve todos. Es lo que
   permite que agregar un rol mañana sea escribir una línea
   —`self::Operador => [...$lectura, ...$operacion]`— en vez de volver a
   clasificar veinte permisos sueltos.

### EMITIR FAENAS Y GUÍAS ES DE VENTANILLA, no de supervisión.

   Son papeles del talonario que se llenan en el mostrador y se
   entregan en el acto: no hay nada que firmar después. Pedir un
   permiso de supervisión los frenaría todos los días por algo que
   ya está autorizado — el carnet vigente ES la autorización.
   ANULARLOS sí es de supervisión: ver el bloque de abajo.

### CORREGIR UN DEPÓSITO ES DE VENTANILLA, y tiene que serlo.

   Es arreglar lo que se tipeó —un monto, un número de boleta, la
   foto equivocada— y además es la ÚNICA salida de una observación:
   un depósito observado no se valida, se corrige. Con este permiso
   en supervisión, el revisor tendría que arreglar él mismo lo que
   acaba de objetar, y el reparo no tendría a quién volver.
   No alcanza con tenerlo: el trámite tiene que estar abierto. Ver
   `AprovechamientoPesq::admiteCorreccionDePagos()`.

### ANULAR UNA GUÍA quema un número del talonario para siempre —no se

   NO HAY `faenas.anular`, y no es un olvido: `EstadoFaena` no tiene
   un estado anulado. Una faena emitida de más no se borra ni se
   anula — se deja VENCER, y al vencer libera su volumen sola. El
   número del talonario queda ocupado igual, que es lo que
   corresponde: la hoja se gastó.

### ANULAR UN COBRO no es corregirlo.

   Corregir deja la fila y su historial: se ve qué decía antes y
   quién lo cambió. Anular hace desaparecer dinero declarado de un
   recibo ya entregado, y lo único que queda es la línea de
   `auditorias`. Quien atiende el mostrador corrige lo que tipeó;
   esto es otra cosa.

### APROBAR Y RECHAZAR un aprovechamiento presentado.

   Es el control del circuito: quien firma mira las boletas contra el
   extracto del banco y recién ahí el cupo autoriza a pescar. Con el
   mismo permiso que enviar, la misma persona cargaría la plata y se
   la aprobaría, y el control no existiría.
   Uno solo para las dos acciones, como `carnets.revocar`: quien
   puede aprobar puede rechazar.

### CONTROLAR LAS BOLETAS —validar u observar— es del mismo lado que

   Es el trabajo de quien firma: comparar cada depósito contra el
   extracto del banco antes de habilitar a nadie. Dado a ventanilla,
   la misma persona cargaría la boleta y la daría por buena, y el
   control no existiría — sería un botón que solo cambia un color.
   Uno solo para las dos acciones, como `aprovechamientos.aprobar`:
   quien puede dar por buena una boleta puede objetarla.

### ELIMINAR UN CUPO ES DE SUPERVISIÓN, aunque solo se pueda sobre un

   Corregir deja la fila y su historial; borrar la hace desaparecer y
   lo único que queda es la línea de `auditorias`. Mismo criterio que
   `caja.anular`: quien atiende el mostrador arregla lo que tipeó,
   hacer desaparecer un registro es otra cosa.

## `app/Enums/TipoActor.php`

### Qué habilita una credencial: PESCAR o COMERCIALIZAR.

   POR QUÉ ESTÁ EN EL CARNET Y NO EN EL BENEFICIARIO
   `beneficiarios` es una tabla UNIFICADA de personas y no tiene columna de rol,
   a propósito: la misma persona pesca y además comercializa, y guardando el rol
   en la ficha habría que duplicarla para representar eso. El rol es del
   DOCUMENTO, no de la persona: quien hace las dos cosas saca dos carnets.
   DE ACÁ CUELGA QUÉ PUEDE EMITIR CADA CREDENCIAL
   pescador        ──< permisos_faena   (una por salida de extracción)
   comercializador ──< guias_movimiento (una por traslado de producto)
   Y también si el carnet lleva colgada una BOLSA MADRE: el cupo en kilos se
   autoriza por volumen extraído, así que solo el pescador tiene
   `aprovechamiento_id`. Por eso esa columna es nullable.

## `app/Exceptions/CarnetInvalidoException.php`

### Una regla de emisión de credenciales dijo que no.

   Mismo criterio que CupoInvalidoException: es una excepción y no un
   `return false` porque el servicio trabaja dentro de una transacción, y así
   `DB::transaction()` deshace todo solo.
   El mensaje sale tal cual en el aviso rojo de la pantalla, así que va en
   castellano de mostrador y DICE QUÉ HACER.

### UNA CREDENCIAL VIGENTE POR ACTIVIDAD Y POR PERSONA

   La regla es POR ACTIVIDAD, no por persona: quien pesca y además
   comercializa tiene DOS carnets vigentes al mismo tiempo, y eso es lo
   normal. Lo que no puede tener son dos de pescador.
   No la garantiza ningún índice de la base —«vigente» depende de la fecha
   de hoy— así que la sostiene el servicio con la fila del beneficiario
   bloqueada.

### Un carnet de pescador sin bolsa madre detrás.

   El plástico imprime el cupo en kilos, así que emitirlo sin cupo daría una
   credencial con un renglón vacío en el lugar donde un control espera un
   número. Y sin cupo tampoco se pueden emitir faenas, que es para lo único
   que sirve ese carnet.
   POR ESO EL CUPO ES EL PASO 2 Y EL CARNET EL 3, y no al revés.

## `app/Exceptions/CobroInvalidoException.php`

### Una regla de caja dijo que no.

   Mismo criterio que las otras: es una excepción y no un `return false` porque
   el cobro entero —el recibo y todos sus abonos— corre dentro de una
   transacción. Con la excepción, `DB::transaction()` deshace todo solo; con un
   booleano quedaría un recibo emitido sin la mitad de sus pagos, y su número ya
   gastado.

### NO SE COBRA MÁS DE LO QUE SE DEBE

   Y es una regla, no una comodidad. `Pagable::saldoPendiente()` se corta en
   cero: pagar de más NO genera saldo a favor, así que el excedente
   DESAPARECE — queda escrito en `pagos`, suma en la recaudación del día, y
   no se le acredita a nadie.
   Si entró dinero de más, no es un abono de este trámite y se resuelve por
   caja. Por eso acá se rechaza en vez de aceptarlo callado.

### El TRÁMITE no está en un estado que acepte depósitos.

   Separada de `noAdmitePagos()` porque el motivo es otro y el mensaje tiene
   que decir cuál: ahí el papel está anulado, acá el expediente se presentó o
   ya se aprobó. Con un solo texto, cargar un depósito sobre un cupo EN
   REVISIÓN contestaba «el papel está anulado», que manda a buscar una
   anulación que nunca existió.

## `app/Exceptions/CupoInvalidoException.php`

### Una regla del otorgamiento de cupo dijo que no.

   Mismo criterio que PermisoOperativoException: es una excepción y no un
   `return false` porque el servicio trabaja dentro de una transacción, y con la
   excepción `DB::transaction()` hace el rollback solo. Con un booleano, quien
   llama tiene que acordarse de deshacerla, y si se olvida queda media operación
   escrita sin que nadie lo note.
   El mensaje sale tal cual en el aviso rojo de la pantalla, así que se escribe
   en castellano de mostrador y DICE QUÉ HACER: quien llegó hasta acá tiene a
   alguien enfrente esperando.

### UNA PERSONA, UNA BOLSA MADRE VIGENTE A LA VEZ

   Es LA regla del módulo. Dos cupos vigentes al mismo tiempo son el doble
   de kilos de los que la escala le otorgó, y no hay forma de notarlo
   mirando: cada uno por separado se ve correcto, y `saldoKg()` de cada uno
   da un número razonable.
   No la garantiza ningún índice de la base —no se puede, porque «vigente»
   depende de la fecha de hoy— así que la sostiene el servicio, con la fila
   del beneficiario bloqueada para que dos ventanillas simultáneas no pasen
   las dos.
   El mensaje dice el saldo y la fecha porque son los dos datos con los que
   el operador decide qué hacer: esperar a que venza, o —si el cupo todavía
   es un borrador— corregirlo al tramo que corresponde.

## `app/Exceptions/PermisoOperativoException.php`

### Una regla de emisión dijo que no.

   POR QUÉ UNA EXCEPCIÓN Y NO UN `return false`
   Los servicios trabajan dentro de una transacción, y un `return false` a la
   mitad obliga a que quien llama se acuerde de deshacerla. Con una excepción,
   `DB::transaction()` hace el rollback solo — y si alguien olvida el `catch`,
   el error se ve, que es infinitamente mejor que una transacción a medias que
   nadie notó.
   EL MENSAJE LO LEE EL OPERADOR DE VENTANILLA
   Sale tal cual en el aviso rojo de la pantalla, así que se escribe en
   castellano de mostrador y DICE QUÉ HACER, no solo que no se puede: quien
   llegó hasta acá tiene a alguien enfrente esperando un papel.

### El cupo existe y está en fecha, pero todavía no se cobró.

   Es un mensaje propio y no `sinCupoVigente()` a propósito: ese texto manda
   a OTORGAR una bolsa madre, y acá la bolsa ya está otorgada. Lo que falta
   es plata, y el operador la puede cobrar en el acto — mandarlo a otorgar
   otra lo llevaría a una regla que va a rechazarlo, que es una vuelta
   perdida con el pescador enfrente.

### Una guía CERRADA ya no se anula.

   Cerrar significa que la carga llegó a destino: el traslado ocurrió y esta
   guía lo amparó. Anularla después sería declarar que nunca amparó nada, y
   eso deja un viaje real sin ningún papel que lo respalde — justo lo
   contrario de para qué existe la guía.
   Si el problema es que se emitió mal, lo que corresponde es dejar
   constancia por otro lado, no borrar el respaldo de un viaje que se hizo.

## `app/Http/Controllers/Panel/AprovechamientoController.php`

### APROVECHAMIENTOS — la BOLSA MADRE del pescador (paso 2 del flujo)

   beneficiario ──< aprovechamiento (500 kg, 2026) ──< faena (80 kg)
   ──< faena (120 kg)
   El cupo es el volumen anual que se le autoriza a una persona. Cada faena
   descuenta de él, y cuando el saldo llega a cero no se pueden emitir más.
   EL CONTROLADOR NO DECIDE NADA
   Todas las reglas —una bolsa vigente por persona, el volumen que sale del
   techo del tramo, el vencimiento con la gestión— viven en
   `OtorgarCupoService`. Acá solo se arman las pantallas y se traduce la
   excepción del servicio en un mensaje bajo el campo.
   Es lo que permite que una carga masiva por consola aplique exactamente las
   mismas reglas sin copiar una línea.

### OTORGAR TERMINA EN LA FICHA DEL CUPO

   Que es donde se cargan los depósitos: la tarjeta de Pagos tiene una
   sección por boleta y el saldo a la vista. Mandarlo a Caja —como hacía
   antes— lo sacaba de la ficha para hacer lo mismo desde otra pantalla, y
   perdiendo de vista cuánto falta.
   Caja sigue existiendo para lo suyo: cobrar varios trámites de una misma
   persona bajo un solo recibo.

### LOS DEPÓSITOS QUE PAGARON ESTE CUPO, CON SU BOLETA

   Un cupo de 412,50 Bs puede haberse pagado con DOS depósitos
   bancarios de 200 y 212,50, cada uno con su boleta. Sin esta lista,
   la ficha solo dice «debe 212,50» o «pagado» y no hay forma de ver
   de dónde salió esa plata sin ir a buscar recibo por recibo.
   Van de la más nueva a la más vieja, que es el orden en que se
   pregunta: «¿entró el último depósito?».

### FORMULARIO DE CORRECCIÓN — GET /panel/aprovechamientos/{id}/editar

   SE CORTA ACÁ SI EL CUPO YA NO ES BORRADOR
   El middleware revisa el PERMISO; esto revisa el ESTADO, que es otra cosa.
   Sin este corte, alguien con el permiso puesto podría abrir el formulario
   de un cupo ya cobrado, llenarlo y recién descubrir al guardar que no se
   podía — con el pescador enfrente y el trabajo tirado.

### ELIMINAR — DELETE /panel/aprovechamientos/{id}

   DESPUÉS DE ESTO NO HAY FICHA A LA QUE VOLVER
   La fila se borra de verdad, así que el redirect va al LISTADO. Y el
   motivo, que es lo único que sobrevive, ya quedó en `auditorias` — lo
   escribe el servicio antes de borrar, cuando el modelo todavía tiene id.

### CARGAR LOS DEPÓSITOS — POST /panel/aprovechamientos/{id}/pagos

   Acepta VARIOS de una vez, porque la pantalla es repetible: el operador
   agrega una sección por cada depósito que trajo la persona y los manda
   todos juntos.
   ACÁ NO SE EMITE NINGÚN RECIBO: `registrarDepositos()` escribe los pagos con
   `recibo_id` en NULL y el papel —uno solo, con el total— lo emite
   `RevisarCupoService::enviar()`. Emitiéndolo acá salían dos recibos por un
   mismo cupo.
   LOS ARCHIVOS SE SUBEN ANTES DE ABRIR NINGUNA TRANSACCIÓN
   Una transacción de base NO deshace escrituras en disco. Subiendo adentro,
   un cobro que falle —saldo movido por otra ventanilla, boleta repetida—
   dejaría archivos huérfanos para siempre. Se suben todos acá, y si algo
   falla el `catch` los borra TODOS, incluidos los de las líneas que sí
   habían pasado.

### REGISTRAR Y ENVIAR SON UN SOLO ACTO CUANDO EL MONTO QUEDA CUBIERTO

   El botón lo dice: «Registrar depósitos y enviar a revisión». Partirlo
   en dos clics obligaba al operador a apretar otro botón para declarar
   algo que la pantalla ya le había mostrado —que la suma alcanza—.
   PERO SE VUELVE A MIRAR EL SALDO, y no se confía en la intención: entre
   que se abrió el formulario y se guardó, otra ventanilla pudo dar de
   baja un pago. Si no quedó cubierto, los depósitos se registran igual
   —ya entraron— y el envío simplemente no ocurre. Nunca falla por esto:
   el operador ve cuánto falta y sigue.

### ENVIAR A REVISIÓN — POST /panel/aprovechamientos/{id}/enviar

   Ventanilla declara que el expediente está completo. Las dos condiciones
   —estado y monto cubierto— las vuelve a mirar el servicio con la fila
   bloqueada.
   Y ES ACÁ DONDE SALE EL RECIBO: uno solo, con el total de los depósitos.
   Por este camino va a nombre del beneficiario, porque no hay formulario que
   pregunte otra cosa; el de los depósitos sí lo pregunta y lo manda.

### Los tramos que el operador puede elegir, con sus consecuencias.

   LO USAN OTORGAR Y CORREGIR, Y TIENEN QUE VER LO MISMO
   Escrito dos veces, agregar un dato al desplegable de alta y olvidarse del
   de corrección dejaría a las dos pantallas mostrando cosas distintas para
   la misma decisión — y nadie lo notaría hasta que alguien comparara.
   Solo los tramos VIGENTES: uno derogado sigue en la tabla —los cupos ya
   otorgados apuntan a él— pero no se puede elegir.
   Se manda el techo del rango porque es el volumen que se va a otorgar, y el
   valor porque es lo que se va a cobrar: la pantalla los muestra al elegir,
   así el operador ve las dos consecuencias antes de guardar y no después.

### Los datos de un cupo que pintan el listado y la ficha.

   Todo lo CALCULADO —saldo, porcentaje, vigencia, saldo pendiente— se arma
   acá y no en React. No es comodidad: la vigencia mira el estado Y la fecha
   —la columna la escribe un comando diario y entre corrida y corrida
   miente— y el saldo se corta en cero porque pagar de más no da crédito.
   Son reglas, y deducirlas en la pantalla sería una segunda copia.

### EDITAR Y ELIMINAR LLEGAN RESUELTAS, y no se deducen de `estado`

   No son «el estado es pendiente»: son eso Y que no haya entrado
   plata, y en el caso de eliminar, Y que no tenga faenas. Escritas
   en la pantalla serían una segunda copia de las tres reglas, y la
   copia se queda vieja sin que nada falle.

## `app/Http/Controllers/Panel/AsociacionController.php`

### CATÁLOGO DE ASOCIACIONES — el gremio que certifica al beneficiario

   NO HAY `destroy()`, Y NO ES UN OLVIDO
   Los carnets y las guías ya emitidas apuntan acá. Borrar una asociación los
   dejaría huérfanos —o, con el RESTRICT de la clave foránea, fallaría con un
   error que el operador no puede interpretar—.
   Para sacarla de circulación se la pone en `inactivo`: desaparece de los
   desplegables de alta y los documentos históricos la siguen mostrando. Es lo
   correcto: la persona pertenecía a ella cuando se le emitió el carnet.
   UNA SOLA PANTALLA, CON EL FORMULARIO AL LADO DE LA TABLA
   Los tres catálogos tienen `index` + `store` + `update` y ninguna pantalla de
   alta o edición aparte. El motivo es el tamaño: son listas de pocas filas que
   se cargan una vez cuando sale la resolución. Navegar a otra pantalla para
   agregar una fila y volver hace perder de vista la lista, que es justamente
   contra lo que se compara al cargarla.
   Es lo contrario de Beneficiarios, que sí tiene pantallas aparte: ahí el
   formulario tiene veinte campos y una foto, y no entra al costado de nada.

## `app/Http/Controllers/Panel/BeneficiarioController.php`

### MÓDULO BENEFICIARIOS — controlador de ejemplo

   Este archivo es la PLANTILLA del sistema: los demás módulos siguen el mismo
   patrón. Está comentado paso a paso a propósito.
   CÓMO VIAJA LA INFORMACIÓN DE LARAVEL A REACT
   1. El navegador pide  GET /panel/beneficiarios
   2. routes/panel.php decide que ese pedido lo atiende el método index()
   3. index() consulta la base de datos con Eloquent
   4. index() devuelve  Inertia::render('panel/beneficiarios/index', [...datos...])
   5. Inertia busca  resources/js/pages/panel/beneficiarios/index.tsx
   6. Ese componente de React recibe los [...datos...] como PROPS
   Lo importante: NO hay una API REST de por medio, no se escribe fetch() ni
   axios en ninguna parte. El array que se pasa como segundo argumento de
   Inertia::render() ES el objeto de props que llega a React.
   Y DE REACT DE VUELTA A LARAVEL
   En React se usa router.post() / useForm().post() de Inertia. Eso hace un POST
   normal, con token CSRF, a una ruta normal. El controlador responde con un
   redirect() (nunca con JSON), e Inertia pinta la página destino sin recargar.

### La edad se calcula acá y NO se manda la fecha sola para que

   Podría hacerse en el navegador, pero entonces habría dos
   definiciones de «edad» en el sistema —la de PHP y la de
   JavaScript— y tarde o temprano difieren por un día en los
   bordes: el cumpleaños de hoy, los años bisiestos, la zona
   horaria del teléfono del operador. El servidor ya sabe la
   respuesta; que la mande.
   Ver Beneficiario::edad(), y por qué no hay columna `edad`.

### SUS CREDENCIALES — el paso 3 del flujo

   Es una LISTA porque una persona puede tener DOS carnets vigentes
   al mismo tiempo: quien pesca y además comercializa. El rol es del
   documento (`tipo_actor`), no de la ficha, y por eso acá no hay que
   elegir «cuál es el carnet» de nadie.
   OJO CON PEDIR COLUMNAS SUELTAS EN EL with(): `tipoCarnet` va
   ENTERO porque `Carnet::montoACobrar()` lee `precio_bs`, y si esa
   columna no viene el saldo sale mal sin ningún error.

### SUS BOLSAS MADRE — el paso 2, y el que explica las faenas

   Se manda el SALDO en kilos y no solo el volumen otorgado, porque
   es lo único accionable: «tiene 500 kg» no dice si puede salir a
   pescar mañana, y «le quedan 20» sí.
   `withSum` sobre las faenas que consumen cupo es lo que evita una
   consulta agregada por fila al calcular ese saldo.

### BAJA — DELETE /panel/beneficiarios/{beneficiario}

   Es un borrado LÓGICO: el modelo usa SoftDeletes, así que la fila no
   desaparece, solo se le pone fecha en `deleted_at`.
   ¿POR QUÉ NO SE BORRA DE VERDAD? Porque los carnets, trámites y pagos
   históricos siguen apuntando a esta persona y no pueden quedar huérfanos.
   `deleted_at` es el único estado que tiene una ficha: o está en el padrón,
   o está dada de baja. No hay una columna `activo` aparte —dos formas de
   decir lo mismo terminan contradiciéndose—.
   Que el historial no se rompa depende de `Carnet::beneficiario()`, que
   lleva withTrashed() justamente para esto.

### BUSCADOR PARA EL FORMULARIO DE SOLICITUD — GET /panel/beneficiarios/buscar

   Devuelve JSON y no una pantalla de Inertia: lo consume el autocompletado
   del formulario de trámite mientras el operador escribe, y ahí no se quiere
   navegar a ningún lado.

### Se traen los carnets VIGENTES con su tipo, todo en la misma tanda

   Sin esto, armar la situación de diez personas serían veintiuna
   consultas —el clásico N+1— y encima disparadas en cada tecleada
   del operador. Pasó de verdad: dieciocho consultas por tecla, con
   el `with()` escrito pero llamando después a un método del modelo
   que consultaba igual. Ver Beneficiario::carnetVigenteDe().

### EL CUPO VIAJA CON EL CARNET, y los dos agregados con él.

   `withSum` de las faenas da el saldo en kilos y `withMax` el último
   número del talonario. Los dos son subconsultas sobre la relación
   YA precargada, así que no agregan una consulta por fila: sin
   ellos, pintar diez resultados serían veinte consultas más, y
   disparadas en cada tecleada.
   Van acá y no en un endpoint propio del módulo de faenas porque la
   pregunta es la misma —«¿qué puede hacer esta persona hoy?»— y
   partirla en dos viajes se nota justo cuando el operador acaba de
   hacer clic.

### QUÉ PUEDE EMITIR ESTA PERSONA HOY, ya resuelto.

   Va en el mismo payload que la búsqueda y no en una segunda
   petición al elegir a la persona: son diez filas ya cargadas, y
   un viaje más al servidor justo cuando el operador acaba de
   hacer clic se nota.
   La pantalla NO lo deduce: recibe `puede_emitir_faenas` y
   `puede_emitir_guias` calculados por el modelo. Un `if` sobre
   el nombre del tipo de carnet en React sería una segunda copia
   de la regla, y se desincroniza en cuanto alguien renombre una
   fila del catálogo.

### Lo que el formulario de faena necesita para abrir con los

   El número es una PROPUESTA, no una imposición: sale de un
   papel que el operador tiene en la mano, y si no coincide
   hay algo que conviene mirar antes de seguir.

### Los datos de un carnet que pinta la ficha.

   LA ACTIVIDAD VA PRIMERO. Con dos carnets posibles por persona, sin
   `tipo_actor` los dos se ven idénticos en la lista y el operador no sabe
   cuál está mirando.
   Se manda `vigente` YA RESUELTO y no el estado a secas: la columna de
   estado puede estar desfasada —`vencido` lo escribe un comando diario— así
   que la pantalla no puede deducirlo comparando fechas por su cuenta. Es la
   misma razón por la que van `codigo` legible y `saldo_pendiente` armados
   desde acá.

### Las listas fijas que necesitan los dos formularios de beneficiario.

   Salen de config/jichi.php y no de acá porque las mismas provincias las
   pide más de una pantalla: escritas dos veces, tarde o temprano una se
   queda sin actualizar.

## `app/Http/Controllers/Panel/CajaController.php`

### CAJA — el circuito del dinero

   carnet | cupo | guía  ──▶  pagos (abonos)  ──▶  recibo numerado
   Es el tercer circuito del sistema y ATRAVIESA a los otros dos: no es un paso
   del flujo sino algo que puede pasar en cualquiera de ellos y varias veces.
   ESTA PANTALLA MUESTRA ABONOS, NO RECIBOS
   Son dos vistas distintas del mismo hecho y las dos hacen falta:
   - CAJA lista PAGOS: cada entrega de dinero, con su método y su trámite. Es
   lo que se mira para cuadrar los depósitos del día contra lo que hay en el
   cajón.
   - RECIBOS lista los PAPELES entregados, con su número correlativo. Es lo
   que audita Contabilidad.
   Un recibo agrupa varios pagos, así que las dos listas nunca tienen la misma
   cantidad de filas y ninguna reemplaza a la otra.

### LA BOLETA SE SUBE ANTES DE ABRIR LA TRANSACCIÓN

   Una transacción de base NO deshace escrituras en disco. Subiendo
   adentro, un cobro que falle —saldo movido por otra ventanilla, número
   de boleta repetido— dejaría el archivo huérfano para siempre, sin
   ninguna fila que lo nombre.
   Por eso se sube acá y el `catch` lo borra. Ver la misma maniobra en
   ArchivoTramiteService.

### Todo lo que esta persona debe hoy, listo para cobrar.

   SE JUNTAN LOS TRES TIPOS EN UNA SOLA LISTA
   Porque así es como llega la persona al mostrador: con lo que debe, no con
   «los carnets por un lado y los cupos por otro». Y porque un mismo recibo
   puede cubrir los tres, que es justamente lo que el polimorfismo permite.
   Los `withSum` evitan una consulta agregada por fila al calcular cada
   saldo, y los `with` de los catálogos hacen falta porque `montoACobrar()`
   lee el precio del tipo de carnet y el valor de la escala.

### YA NO SE REPARTE POR MÉTODO: todo pago es un depósito bancario, así

   - `created_at`      lo CARGADO hoy: cuadra el trabajo del día.
   - `fecha_deposito`  lo DEPOSITADO hoy: se cruza contra el extracto.
   Un depósito del viernes cargado el lunes entra en el primero y no en
   el segundo, y esa diferencia es justamente la que hay que ver.

## `app/Http/Controllers/Panel/CarnetController.php`

### CARNETS — la credencial anual (paso 3 del flujo)

   carnet (pescador)        ──< permisos_faena     (una por salida)
   carnet (comercializador) ──< guias_movimiento   (una por traslado)
   El carnet es la LLAVE ANUAL; con él solo no se sale a trabajar. De él cuelgan
   los permisos operativos que autorizan cada día.
   NO HAY `edit` NI `update`, Y ES LA REGLA
   Un carnet emitido no se corrige: el plástico ya salió de la impresora y está
   en manos de la persona. Editarlo dejaría al documento impreso diciendo una
   cosa y al sistema otra, sin que nada lo delate — y la verificación pública
   respondería por el dato nuevo mostrando lo que el inspector NO tiene delante.
   Lo que sí hay es REVOCAR, con motivo escrito, y emitir uno nuevo con su
   propio código.

### Los datos de un carnet que pintan el listado y la ficha.

   Todo lo CALCULADO se arma acá y no en React. `vigente` mira el estado Y la
   fecha —la columna la escribe un comando diario y entre corrida y corrida
   miente—, `cupo_kg` depende del tipo de actor y no del nombre del tipo de
   carnet, y `saldo_pendiente` se corta en cero. Son reglas, y deducirlas en
   la pantalla sería una segunda copia de cada una.

## `app/Http/Controllers/Panel/CarnetImpresionController.php`

### IMPRESIÓN DEL CARNET — la «cédula de pescador»

   Arma el PDF del plástico y lo manda al navegador. Es el documento que la
   persona se lleva al final del circuito, y es un CALCO de la cédula que la
   unidad venía mandando a imprimir: el mismo verde, el mismo encabezado, los
   mismos seis renglones y el mismo sello de agua. Quien la recibe —y sobre todo
   el inspector que la revisa en el río— la reconoce por su forma; una versión
   «mejorada» se lee como si fuera otro documento.
   POR QUÉ ES UN CONTROLADOR APARTE Y NO UN MÉTODO DE CarnetController
   Mismo criterio que ReciboController: aquel administra filas y devuelve
   pantallas de Inertia, este dibuja un documento y devuelve bytes. Son dos
   oficios distintos —uno cambia datos, el otro los maqueta— y mezclarlos dejaba
   un controlador donde la mitad de los `use` son de impresión.
   NO ESCRIBE NADA: NI EL PDF, NI EL ESTADO DEL CARNET
   El PDF no se guarda en disco: se deduce entero de la fila del carnet, así que
   el de mañana sale idéntico al de hoy. Guardarlo sería un archivo más que
   limpiar —y con el disco en s3, uno que no se puede borrar—. Por eso esto
   tampoco pasa por StorageController.
   Y no marca nada como impreso. Ver el documento en pantalla no es haberlo
   sacado en la impresora de credenciales: si esta ruta marcara, alcanzaría con
   que alguien abriera la vista previa —o con que el navegador precargara el
   enlace— para que el sistema declarara un plástico que nunca existió.
   QUÉ IMPRIME Y QUÉ NO
   EL CRITERIO ES QUÉ NO CAMBIA después de que el plástico sale de la impresora.
   VAN IMPRESOS la actividad y el cupo. La actividad (`tipo_actor`) es parte de
   lo que el carnet ES y no cambia nunca; sin ella, dos carnets de la misma
   persona serían plásticos idénticos. El cupo va porque es el número que un
   control contrasta contra una guía de transporte.
   NO VA EL ESTADO. Un carnet se revoca DESPUÉS de impreso y el plástico no se
   entera, así que si vale HOY se consulta con el código en la verificación
   pública. Imprimir un estado que puede quedar viejo es peor que no imprimirlo.
   TAMPOCO VA LA GESTIÓN, y es nuevo: el código ya la lleva adentro («PES26…»).
   Por eso mismo puede ser GET, igual que el recibo.

### EL TAMAÑO DEL PLÁSTICO — CR80, la medida de cualquier tarjeta.

   85,6 x 54 mm = 242,6 x 153,1 puntos, apaisado. Es lo que mide una cédula
   de identidad, una tarjeta de crédito y el carnet que la unidad venía
   mandando a imprimir, así que entra en las impresoras de credenciales y en
   las fundas que ya se compran.
   Vive acá y no en la plantilla porque es una decisión de IMPRESIÓN, no de
   diseño. Ojo: las coordenadas del Blade están calculadas para estos
   243 x 153 puntos y hay que revisarlas si esto cambia.

### UN CARNET REVOCADO NO SE IMPRIME.

   Se vuelve a comprobar acá aunque la pantalla ya esconda el botón:
   esconderlo en React es comodidad, no seguridad — la dirección se
   puede escribir a mano.
   Un carnet VENCIDO sí se imprime, y la diferencia importa: puede hacer
   falta reponer el plástico de una gestión cerrada para un trámite o un
   reclamo. Lo que el plástico nunca dice es si vale HOY; eso se consulta
   con el código.

### LAS IMÁGENES VAN EMBEBIDAS, Y EL FONDO VIENE HORNEADO

   En base64 y no como ruta por lo mismo que en el recibo: DomPDF
   resolvería `/image/...` contra el disco con las restricciones de
   `chroot` y en producción termina en un recuadro vacío.
   `carnet-fondo.png` trae el degradado verde Y el sello del SEDAG ya
   atenuado adentro, en un solo archivo. Son las dos cosas que DomPDF
   no hace bien: no entiende `linear-gradient` —dibujaría un
   rectángulo liso— y su `opacity` es tan poco confiable que el sello
   puede salir a pleno color tapando los datos. Horneadas en el PNG
   no pueden fallar.

### El escudo del encabezado, recortado del lockup de la

   Es una copia reducida por lo de siempre: el original mide 2362 px
   de lado y pesa 1,8 MB, y embebido en cada carnet el PDF salía
   inmanejable para una ventanilla que imprime decenas por día.

### LA FOTO DEL TITULAR, si la ficha tiene una cargada.

   Va por Archivos::contenido() y no por su URL porque el servidor
   tendría que salir a buscarse a sí mismo por HTTP para dibujarla
   —con su timeout— y eso falla en cualquier despliegue donde el
   bucket no sea público.
   Sin foto el carnet SALE IGUAL, con el recuadro vacío: es
   exactamente lo que hacía la unidad con la cédula de papel cuando
   la persona traía la foto después. Un error 500 acá dejaría a
   ventanilla sin poder imprimir nada.

### EL ANCHO ÚTIL DE LA TIRA DEL VALOR, en puntos.

   Es el ancho DECLARADO de la tira en el Blade (126) menos su relleno
   horizontal (2 + 2). Bajaron al corregir el
   desborde: la tira ocupa 130 de la columna, pero 126 son de caja y 4 de
   relleno. Ver el comentario de `.campo .valor` en la plantilla.
   Tiene que ser ese y no el de la columna entera: calculado sobre la columna
   —176 menos el rótulo— el sistema creía que entraban treinta caracteres más
   de los que entran, y los nombres largos salían cortados en vez de
   achicados, que es justo lo que este cálculo viene a evitar.

### EL ANCHO UTIL DE CADA MITAD DEL RENGLON PARTIDO, en puntos.

   El de REGISTRO + GESTION, que es el unico. Misma cuenta que la tira
   entera: el ancho declarado en el Blade (42) menos su relleno (2 + 2).
   Los dos valores que caen ahi son cortos y fijos -seis digitos y cuatro-,
   asi que nunca llegan a encogerse; la medida va igual porque el calculo de
   texto() la pide, y el dia que ese renglon lleve otra cosa tiene que
   medirse contra su mitad y no contra la tira completa.

### EL RENGLON PARTIDO EN TRES — REGISTRO + GESTION + CUPO.

   Los anchos UTILES de cada tira: el declarado en el Blade menos su relleno
   de 4 pt. En CSS el padding SUMA al width, y medir contra el declarado ya
   hizo que un rotulo se imprimiera encima de una tira.
   El reparto sale de lo que ocupa cada dato a 6,1 pt:
   «000011»    6 car. x 0,539 x 6,1 = 19,7   cabe en 20
   «2026»      4 car.               = 13,1   cabe en 15
   «1.200 KG»  8 car.               = 26,3   cabe en 31,5
   Al cupo se le da la tira mas ancha a proposito: es el unico de los tres
   que puede crecer —un cupo de cinco digitos con separador de miles— y el
   unico sin rotulo que lo anuncie, asi que conviene que no se encoja.

### Cuantos caracteres entran en el TITULO a cuerpo pleno.

   230 pt utiles / 6,55 pt por caracter (0,605 em de la negrita a 9,5 pt mas
   0,8 de interletrado) = 35. Se deja en 30 para no llegar al limite: el
   calculo es un promedio y un titulo de puras mayusculas anchas —«M», «W»—
   ocupa mas que el promedio.
   Pasado ese largo, titulo() devuelve la clase `largo` y la hoja de estilos
   baja cuerpo, interletrado y contorno JUNTOS. Ver `.titulo.largo`.

### EL ANCHO UTIL DE LA TIRA DE LA CEDULA, y su cuerpo, en puntos.

   43,6 es lo declarado en el Blade, que ya viene de restarle el relleno
   (2 + 2) a los 47,6 pt que mide el recuadro de la foto con su borde: la
   tira y la foto cierran contra la misma vertical.
   El cuerpo es mas grande que el de los renglones -5 contra 4,6- porque la
   cedula no tiene rotulo que la anuncie: se lee sola.

### A cuantos pixeles de lado se reduce la foto antes de embeberla.

   El recuadro mide unos 17 mm. A 300 dpi -lo que resuelve una impresora de
   credenciales- eso son 200 px; 300 deja margen para el recorte y para
   imprimir mas fino.
   SIN ESTO EL PDF SE VA DE LAS MANOS: la foto se guarda tal como la subio
   ventanilla, que es lo que salio de un telefono -dos, tres, cinco
   megapixeles-. Embebida entera hacia un carnet de 442 KB para dibujar un
   cuadradito de 17 mm, y la unidad imprime decenas por dia. Es el mismo
   problema que ya habian dado los PNG del panel en el recibo.

### LA JERARQUIA TIPOGRAFICA

   No es decoracion: es lo que separa una CREDENCIAL de la impresion de un
   formulario.
   En un documento de identidad lo primero que se lee es a QUIEN identifica.
   Por eso el nombre va al doble de cuerpo que el resto y arriba de todo, con
   la cedula abajo; los otros datos son secundarios -se leen recien cuando
   hacen falta- asi que van mas chicos y con el rotulo en un tono apagado.
   La primera version los ponia a todos del mismo tamano, cada uno en su caja
   blanca. Se leia como una planilla a medio llenar, y por un motivo
   concreto: una caja vacia a la derecha de un dato corto es exactamente lo
   que parece.

### El cuerpo y el alto de una tira que pasó a DOS líneas.

   Son fijos y no calculados, y es a propósito: el renglón siguiente está
   plantado 14 pt más abajo, así que la tira tiene un techo. Con 4,5 pt entran
   dos líneas holgadas en 12,6 — y a ese cuerpo, en dos líneas, entra un
   nombre de unos 75 caracteres, más largo que cualquiera del padrón.
   Calculado «el que haga falta» salían tiras de 15 pt que pisaban el renglón
   de abajo, o que el `overflow: hidden` cortaba por la mitad — que se ve peor
   que si el texto nunca hubiera entrado.

### Cuanto ocupa cada caracter, en fraccion del cuerpo.

   ESTE NUMERO VA ATADO AL GRUESO DE LA LETRA DE LA TIRA, y hay que moverlo
   si ese grueso cambia. Medido sobre las DejaVu Sans que embebe DomPDF, con
   los textos que salen de verdad en un carnet -nombres, direcciones, los
   moldes-: la REGULAR promedia 0,539 em por caracter y la NEGRITA 0,605.
   La tira paso de negrita verde a regular negra -como una cedula de
   identidad-, asi que esto bajo de 0,62 a 0,55. Dejado en 0,62 no rompia
   nada, pero sobreestimaba: creia que el texto ocupaba un 13% mas de lo que
   ocupa, y achicaba nombres que entraban enteros. En un documento que se
   lee en un control, un nombre mas chico de lo necesario es una perdida.
   El 0,55 conserva el mismo margen que tenia el 0,62 sobre su promedio
   -alrededor de un 2%-, y el margen importa: el peor caso medido es 0,67, o
   sea que un texto de puras mayusculas ocupa bastante mas que el promedio.
   Quedarse corto seria peor que pasarse, porque lo que no entra lo recorta
   el `overflow: hidden` de la tira y ahi se pierden apellidos.

### LOS DATOS DE LA TARJETA, ya resueltos aca.

   La plantilla no decide nada: recibe cada texto con el cuerpo en el que se
   va a dibujar. Son los mismos campos, en el mismo orden, que muestra la
   vista previa del panel -- si se agrega uno aca hay que agregarlo alla.

### LOS SEIS RENGLONES DEL PLASTICO

   SIEMPRE SEIS, para cualquier actividad, y eso costo llegar a tenerlo.
   Los dos datos que ya no estan aca explican por que:
   - EL RUBRO se fue al TITULO. Un renglon «RUBRO : Comercializador»
   con el titulo diciendo «CEDULA DE COMERCIALIZADOR» imprimia dos
   veces la misma palabra.
   - EL CUPO se fue a la columna de la FOTO, debajo de la cedula, donde
   habia 30 pt muertos. Como renglon obligaba a apretar el salto de
   14 a 12 pt para que entraran siete.
   Sacando esos dos se libero el lugar que permitio darle a CIUDAD y a
   PROVINCIA una tira entera cada una —compartian una— sin pasar de seis.
   EL ORDEN es el de la cedula de papel: nombre, asociacion, domicilio y
   el numero de registro al final.

### CIUDAD Y PROVINCIA VAN CADA UNA EN SU RENGLON, a pedido.

   Compartieron uno mientras la actividad ocupaba una tira: eran
   los dos valores mas cortos y mas repetidos del padron. Al irse
   la actividad al TITULO se libero el lugar.
   Y con eso vuelve «PROVINCIA» entera: se abreviaba a «PROV.»
   porque el rotulo del SEGUNDO par tiene una caja de 32 pt y a
   6,1 pt bold la palabra mide 33,2. El rotulo de un renglon
   entero tiene 44 pt y entra sin problema.

### EL CÓDIGO CIERRA LA LISTA, como en el plastico, Y LLEVA EL

   El codigo solo alcanza para identificar la credencial: es
   unico GLOBAL y lleva el año adentro, asi que no hace falta
   imprimir la gestion al lado como pasaba con el registro del
   modelo anterior.

### EL TITULO DE LA TARJETA, con la actividad adentro.

   DECIA «CEDULA» A SECAS, Y EL MOTIVO SE DIO VUELTA
   Con el modelo viejo el carnet era UNO para todas las actividades
   de una persona, asi que nombrar una en el titulo habria dicho algo
   que el documento no era. Hoy el carnet es de UNA actividad y esa
   actividad es parte de lo que el documento ES: el titulo puede
   decirlo, y conviene que lo diga — es lo que se lee de lejos, antes
   que cualquier renglon.

### LA CEDULA PASA POR EL MISMO CALCULO QUE LOS RENGLONES.

   Vive en una tira angosta -la de la foto- y el numero cambia de
   largo segun la expedicion y el complemento, asi que una cedula
   larga tiene que achicarse igual que un nombre largo. Recortada
   seria peor que en cualquier otro campo: un numero de documento al
   que le falta el final no identifica a nadie.

### EL TITULO DE LA TARJETA Y EL CUERPO EN EL QUE ENTRA

   «CEDULA DE PESCADOR», «CEDULA DE COMERCIALIZADOR».
   POR QUE DEVUELVE TAMBIEN UNA CLASE DE TAMANO
   Porque el titulo ya no es una palabra fija: su largo depende del catalogo,
   que edita la unidad. A 9,5 pt con 0,8 de interletrado cada caracter ocupa
   unos 6,55 pt, asi que en los 230 pt utiles de la tarjeta entran unos 35.
   CEDULA DE PESCADOR          18 car.  ~118 pt   entra holgado
   CEDULA DE COMERCIALIZADOR   25 car.  ~164 pt   entra
   una actividad de 30+ caracteres      se pasa   -> clase `largo`
   NO SE RECORTA, se achica: es la misma regla que los renglones —ver
   texto()—. Un titulo cortado en «CEDULA DE COMERCIALIZA» no identifica
   nada y queda peor que uno chico.
   EL CONTORNO ESCALA CON EL CUERPO, Y POR ESO ES UNA CLASE Y NO UN width
   El titulo va perfilado en dorado dibujandolo cinco veces, y el corrimiento
   de las cuatro copias tiene que bajar en la misma proporcion que la letra:
   medio punto sobre un cuerpo chico no perfila, engorda la letra hasta
   cerrarle los huecos. Por eso la variante vive en la hoja de estilos —donde
   el cuerpo, el interletrado y los cuatro corrimientos se mueven juntos— y
   acá solo se elige cual.

### EL CUPO, EN LA COLUMNA DE LA FOTO — o NADA, si la actividad no lleva

   POR QUE ABAJO DEL C.I. Y NO COMO UN RENGLON MAS
   Porque ahi hay lugar y en la columna de datos no. La foto termina en 112 y
   la tira del C.I. en 122,5; de ahi al borde de la tarjeta quedan 30 pt
   muertos, que es justo donde entra una tira mas.
   Y GANA LA COLUMNA DE LA DERECHA: con el cupo como renglon, la tarjeta de
   un pescador llegaba a SIETE y habia que apretar el salto de 14 a 12 pt
   para que entraran. Sacandolo de ahi, las dos actividades vuelven a seis
   renglones y al salto de siempre.
   Ademas queda al lado de la foto y de la cedula, que son los otros dos
   datos que un control mira primero: quien es, y cuanto tiene autorizado.
   NO TODAS LAS ACTIVIDADES LLEVAN CUPO
   La pesca se autoriza POR VOLUMEN —tantos kilos, contrastables contra una
   guia de transporte— y el plastico lo imprime. La comercializacion no:
   habilita a trasladar y vender, sin tope propio.
   Lo dice `TipoActor::requiereAprovechamiento()`, no una lista de nombres
   escrita aca ni el nombre del tipo de carnet: ese nombre es un catalogo que
   la unidad edita, y el mismo documento figura de dos formas distintas segun
   quien lo cargo.

### EL ULTIMO RENGLON — REGISTRO, GESTION Y, SI CORRESPONDE, EL CUPO

   POR QUE LOS TRES JUNTOS
   EL CODIGO SOLO ALCANZA. En el modelo anterior el renglon era REGISTRO +
   GESTION y los dos hacian falta juntos, porque el registro era el id del
   carnet y se reiniciaba con cada año. Hoy `codigo_carnet` es unico GLOBAL y
   lleva el año adentro, asi que la gestion seria el mismo dato dos veces.
   EL CUPO SE SUMO A ESE RENGLON en vez de ocupar uno propio. Como renglon la
   tarjeta llegaba a SIETE y habia que apretar el salto de 14 a 12 pt. Aca
   vuelve a la columna de datos —donde lo traia la cedula de papel— sin
   costar una linea.
   CUANDO LA ACTIVIDAD NO LLEVA CUPO el codigo se queda con la tira entera.
   No es un caso raro: la comercializacion no tiene tope propio.

### LA GESTIÓN YA NO SE IMPRIME, Y NO ES UN OLVIDO

   Con el modelo anterior el renglón era REGISTRO + GESTIÓN, y los dos
   hacían falta juntos: el registro era el id del carnet y se reiniciaba
   con cada año, así que el 000002 de 2026 y el de 2027 eran dos
   credenciales distintas con el mismo número impreso.
   Hoy el identificador es `codigo_carnet`, que es ÚNICO GLOBAL y LLEVA
   EL AÑO ADENTRO —«PES26…»—. Imprimir la gestión al lado sería escribir
   dos veces el mismo dato y gastar una tira que el cupo necesita.

### UN TEXTO QUE NO ENTRA SE ACHICA; NO SE CORTA

   Cortar con puntos suspensivos esta bien en una pantalla, donde el dato
   completo esta a un clic. En el PLASTICO no: "Maria Esperanza del Carmen
   Justiniano Vaca Guzman de Suarez Vilinga" cortado en "Maria Esperanza del
   Carmen" pierde los apellidos, que son justamente lo que identifica a la
   persona en un control.
   Asi que se calcula con que cuerpo entra, y solo cuando encogerlo lo
   volveria ilegible se pasa a dos lineas. Dos y no tres: una tercera
   invadiria el renglon de abajo.
   EL ANCHO UTIL DE DOS LINEAS NO ES EL DOBLE, y ese fue el error de la
   primera version: las palabras no se parten, asi que la primera linea corta
   donde termina la ultima palabra que entra y deja un sobrante de mas o
   menos el 15%.

### LA FOTO DEL TITULAR, RECORTADA A UN CUADRADO SIN DEFORMARSE

   Devuelve los datos de la imagen y el estilo con el que la plantilla la
   dibuja. NULL si no hay foto, o si no se pudo leer.
   POR QUÉ HACE FALTA CALCULAR UN ESTILO
   DomPDF NO TIENE `object-fit`. Poniéndole `width` y `height` a la imagen,
   un retrato vertical metido en el recuadro cuadrado sale APLASTADO: la cara
   más ancha de lo que es. En un documento de identidad eso no puede pasar —
   la foto es justamente lo que se compara contra la persona.
   Así que se hace a mano lo que haría `object-fit: cover`: la imagen se
   dibuja a su proporción REAL, desbordando el recuadro por el lado que
   sobra, y corrida con un margen negativo de la mitad de esa diferencia para
   que quede centrada. El `overflow: hidden` del recuadro recorta lo que
   asoma.
   Se recorta y no se encoge porque un retrato que entra completo deja dos
   franjas blancas al costado y la cara sale más chica todavía; recortado, la
   cara ocupa el cuadrado entero, que es lo que hace una foto de carnet.

### Reduce la foto al tamaño en que se va a dibujar.

   Devuelve los bytes, el tipo y las medidas resultantes — o los de entrada,
   tal cual, si no hizo falta reducir o si gd no pudo con el archivo.
   Sale siempre en PNG cuando se reduce: es sin pérdida, y una foto de
   carnet de 300 px pesa lo mismo en los dos formatos.

## `app/Http/Controllers/Panel/CategoriaAprovechamientoController.php`

### LA ESCALA OFICIAL DE APROVECHAMIENTO — paso 2 del flujo del pescador

   Es la tabla que convierte una decisión administrativa —«a esta persona le
   corresponde la escala 3»— en los dos números con los que trabaja el sistema:
   el volumen en kilos y lo que se cobra por él.
   LA PANTALLA MUESTRA LOS HUECOS, Y ESO ES LO MÁS ÚTIL QUE HACE
   Un hueco entre dos tramos no rompe nada visible: simplemente hay volúmenes
   que no caen en ninguna escala, `CategoriaAprovechamiento::paraVolumen()`
   devuelve null y el formulario de cupo no ofrece nada, sin ningún error que lo
   explique. Es el tipo de falla que se descubre en ventanilla con alguien
   enfrente.
   El SOLAPE lo rechaza el Request —es plata mal cobrada y no tiene lectura
   válida—. El HUECO no se puede rechazar ahí, porque cargar la escala de a un
   tramo por vez deja huecos transitorios: al guardar el primero todavía no
   existe el segundo. Así que se detecta acá, mirando la escala ENTERA, y se
   muestra como aviso.
   Igual que los otros dos catálogos, NO hay `destroy()`: los aprovechamientos
   otorgados apuntan acá para dejar constancia de bajo qué tramo se autorizaron.
   Una escala derogada se pone en `estado = false`.

### Los rangos de kilos que no caen en ningún tramo.

   SE RECORRE ORDENADO POR KILOS, NO POR NÚMERO DE ESCALA
   Los dos órdenes casi siempre coinciden, pero no tienen por qué: nada
   impide cargar la escala 7 con el rango más bajo. Recorriendo por
   `nro_escala` un catálogo así daría huecos y solapes inventados, y el
   aviso perdería toda credibilidad.
   El criterio de contigüidad es que cada tramo empiece donde termina el
   anterior MÁS UNO: el texto oficial dice «1 Kg Hasta 100 Kg» y el
   siguiente arranca en 101, no en 100,01. Por eso la comparación usa 1 y no
   un épsilon.

## `app/Http/Controllers/Panel/DashboardController.php`

### El tablero de la gestión en curso.

   QUÉ MIRA ESTE TABLERO, Y POR QUÉ CAMBIÓ
   La versión anterior contaba EXPEDIENTES: cuántos entraron, cuántos esperan
   firma, cuántos están listos para aprobar. Ese circuito ya no existe — hoy el
   documento se emite y se cobra, sin trámite en el medio.
   Lo que queda para mirar son las tres cosas que sí pueden salir mal en
   ventanilla, y por eso son los tres bloques del tablero:
   1. CUÁNTA GENTE ESTÁ HABILITADA HOY  → carnets vigentes
   2. QUÉ ESTÁ POR CADUCAR              → carnets, cupos, faenas y guías
   3. CUÁNTO ENTRÓ Y CUÁNTO FALTA COBRAR → recaudación y saldo pendiente
   POR QUÉ CADA BLOQUE VA ENVUELTO EN UN fn()
   Inertia evalúa las closures solo cuando la prop se va a enviar de verdad. En
   una visita parcial —cuando la pantalla pide refrescar únicamente el gráfico
   de recaudación, por ejemplo— las demás no se ejecutan, y esas consultas
   agregadas no se corren al pedo. Pasadas como valores sueltos se calcularían
   todas en cada refresco.

### Lo que falta cobrar, sumando los tres trámites que se cobran.

   LA RESTA SE HACE EN PHP A PROPÓSITO
   «Cuánto falta» no es `precio - pagado` a secas: se corta en cero, porque
   pagar de más no genera saldo a favor. Esa regla vive en
   `Pagable::saldoPendiente()` y no se duplica acá — escrita en SQL con un
   GREATEST habría dos versiones de la misma decisión, y además GREATEST se
   escribe distinto en PostgreSQL que en SQLite.
   EL withSum NO ES OPCIONAL. Sin él, cada `saldoPendiente()` cae en
   `$this->pagos()->sum(...)` y dispara UNA CONSULTA POR FILA, en la pantalla
   a la que cae todo el mundo al entrar. Con él, lo cobrado de todos viene en
   la misma consulta y el trait lo reusa.
   Los `with()` de los catálogos son por lo mismo: `montoACobrar()` lee el
   precio del tipo de carnet y el valor de la escala.

### Los últimos catorce días, jornada por jornada: cuántos documentos se

   PARA QUÉ, SI LOS NÚMEROS YA ESTÁN ARRIBA
   Alimenta las líneas chicas que van al pie de los indicadores. No son
   adorno: un número solo —«0 documentos hoy»— no dice si eso es lo normal
   de un martes o si la ventanilla se paró. La línea de atrás lo pone en
   contexto sin gastar una tarjeta entera en un gráfico aparte.
   CATORCE DÍAS, no treinta: el dibujo mide unos 60 px de alto y ahí adentro
   treinta puntos se pisan entre sí y quedan como una mancha. Dos semanas
   alcanzan para ver el ritmo y para que se distinga un lunes de un sábado.

### Agrupa una tabla por jornada. Cuenta filas, o suma una columna si se le

   Existe para no repetir cuatro veces el mismo group by: reducir un
   timestamp al día se escribe distinto en cada motor, y esa expresión vive
   en App\Support\Sql justamente para que no se copie por ahí.

### Cuántos carnets vigentes hay de cada tipo del catálogo.

   Se recorre el catálogo ENTERO y no solo lo que devolvió la consulta: un
   tipo con cero carnets también es información —dice que nadie lo pide— y
   si no aparece, el gráfico miente por omisión.

### Pescadores contra comercializadores, entre los carnets vigentes.

   Es el número que dice cómo se reparte el padrón habilitado entre las dos
   actividades. Sale del enum y no de la base para que los dos aparezcan
   aunque uno esté en cero.

### with() para no caer en N+1: sin esto, diez filas serían 31

   OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
   partes del nombre porque `nombreCompleto` las lee todas, y
   `tipoCarnet` va ENTERO —sin `:id,nombre`— porque
   `Carnet::montoACobrar()` lee `precio_bs`. Una columna que un
   método consulta y no está en el select vuelve null, y el método
   contesta cualquier cosa sin ningún error.

### Lo que está por caducar o ya caducó sin cerrarse.

   ES EL BLOQUE ACCIONABLE DEL TABLERO
   Las dos últimas cifras no son avisos de vencimiento sino de TRABAJO SIN
   CERRAR: una faena o una guía que se pasó de fecha y sigue en `activa` es
   un papel que alguien se llevó y del que nadie registró la vuelta. El
   comando diario las marca, pero entre corrida y corrida quedan acá a la
   vista.

## `app/Http/Controllers/Panel/FaenaController.php`

### PERMISOS DE FAENA — una salida de pesca (paso 4 del flujo)

   carnet (pescador) ──▶ faena ──▶ descuenta kilos de la bolsa madre
   NO HAY `edit`, NI `update`, NI `destroy`
   El número sale de un TALONARIO DE PAPEL que el pescador se llevó. Borrar la
   fila deja un hueco en la serie que nadie puede explicar y libera un número
   que el índice único volvería a aceptar, así que dos salidas distintas podrían
   terminar diciendo ser la misma hoja.
   Y TAMPOCO SE ANULA: `EstadoFaena` no tiene ese estado. Una faena emitida de
   más se deja VENCER, y al vencer libera su volumen sola. El número queda
   ocupado igual, que es lo correcto — la hoja se gastó.
   Lo único que se escribe después de emitir es COMPLETAR, que registra la
   vuelta y admite corregir los kilos contra la balanza.

### Un carnet como lo necesita el formulario de emisión.

   Es la MISMA forma que devuelve `BeneficiarioController::buscar()`, a
   propósito: la pantalla trata igual a la persona preseleccionada y a la que
   se busca a mano, así que hay un solo camino en el componente.

### Los datos de una faena que pintan el listado y la ficha.

   `vigente`, `consume_cupo` y `puede_completarse` llegan RESUELTOS: las tres
   son reglas —la primera mira el estado Y la fecha, la segunda sale del
   enum, la tercera exige que esté en curso— y deducirlas en la pantalla
   sería una segunda copia de cada una.

## `app/Http/Controllers/Panel/GuiaController.php`

### GUÍAS DE MOVIMIENTO — un traslado de producto (paso 4, rama comercializador)

   carnet (comercializador) ──▶ guía ──▶ ampara UN traslado, 5 días
   NO HAY `edit` NI `destroy`
   El código sale de un talonario de papel que viaja dentro del camión. Borrar
   la fila deja un hueco en la serie y libera un código que el índice único
   volvería a aceptar: dos traslados podrían terminar diciendo ser el mismo
   papel.
   Lo que se escribe después de emitir son dos cosas: CERRAR —la carga llegó, y
   ahí se corrige el peso contra la balanza del destino— y ANULAR, con motivo.

### LA TARIFA Y EL DESCUENTO SALEN DEL SERVIDOR, no escritos en React.

   El formulario muestra cuánto va a costar apenas se marca la casilla
   de piscicultura, y ese número tiene que ser EL MISMO que cobra
   `GuiaMovimiento::montoACobrar()`. Escrito en los dos lados, el día
   que la resolución cambie el 50% a 40% la pantalla seguiría
   prometiendo un precio que la caja no cobra.

### Los datos de una guía que pintan el listado y la ficha.

   `vigente`, `caducada`, `puede_cerrarse` y `puede_anularse` llegan
   RESUELTOS. Las cuatro son reglas —la primera mira el estado Y la hora, y
   la última además prohíbe anular una guía CERRADA, porque el traslado ya
   ocurrió— y deducirlas en la pantalla sería una segunda copia.

## `app/Http/Controllers/Panel/PagoController.php`

### El control de las boletas: validar, observar y corregir un depósito.

   Las tres viven en la FICHA del trámite, así que devuelven `back()`: el revisor
   está mirando la tarjeta de pagos y no hay por qué sacarlo de ahí.
   El controlador no decide nada: las reglas están en `Pago::admiteControl()` y
   en `ControlarPagoService`. Acá solo se sube el archivo y se traduce el error.

## `app/Http/Controllers/Panel/ReciboController.php`

### RECIBOS — los comprobantes entregados

   NO HAY `store`, NI `update`, NI `destroy`
   Un recibo NACE de un cobro: lo emite `CobrarService` junto con sus abonos, en
   la misma transacción. Un endpoint para crear uno suelto permitiría un
   comprobante numerado sin ningún pago detrás — un papel oficial que dice que
   entró plata que no entró.
   Y no se borra: `numero_recibo` es un correlativo que Contabilidad audita.
   Borrar una fila deja un hueco en la serie que nadie puede explicar.
   `monto_total` ESTÁ CONGELADO, Y LA PANTALLA MUESTRA SI DEJÓ DE CUADRAR
   La columna es lo que se IMPRIMIÓ; `Recibo::montoCalculado()` es lo que HAY
   hoy en el detalle. Si alguien corrigió un abono después de entregar el papel,
   los dos números se separan — y eso es justamente lo que un arqueo tiene que
   poder detectar, no algo que convenga tapar recalculando al leer.

### YA NO SE MANDA `esDeposito`, y no es un olvido.

   Valía SIEMPRE true: en esta unidad no se cobra en efectivo ni por
   QR, así que la casilla de efectivo del papel salía vacía en todos
   los recibos. Se sacó del documento —era una opción impresa que
   nunca se podía marcar— y la de depósito quedó fija en la plantilla.
   Es el mismo criterio que la columna `metodo_pago` que `pagos` no
   tiene: un dato con un solo valor posible no informa nada, e invita
   a suponer que alguna vez hubo otra cosa.

### LAS IMÁGENES SON COPIAS A MEDIDA Y VAN EMBEBIDAS EN BASE64.

   No son `icon.png` ni `sedag.png`: esos miden más de 2000 px de
   lado y embebidos hacían un PDF de 5,4 MB por recibo. Las copias de
   `recibo-*` están al tamaño en que se dibujan y pesan 59 KB juntas,
   con el sello ya PRE-ATENUADO en el archivo —`opacity` es de lo
   menos confiable que tiene DomPDF—.
   Y en base64 porque DomPDF no es un navegador: una ruta se resuelve
   contra el disco con las restricciones de `chroot` y en producción
   termina en un recuadro vacío.

### Sin subsetting, DomPDF mete las dos tipografías COMPLETAS en cada

   Se activa acá y no en `config/dompdf.php` a propósito: ese archivo
   lo publica el paquete y conviene dejarlo tal cual para poder
   compararlo cuando se actualice.

## `app/Http/Controllers/Panel/TipoCarnetController.php`

### CATÁLOGO DE TIPOS DE CARNET — cómo se llama cada credencial y cuánto sale

   ES EL CATÁLOGO, NO LA REGLA
   Qué habilita un carnet —si emite faenas o guías, si lleva cupo en kilos— lo
   dice `carnets.tipo_actor`, que es un enum de PHP. De ESTE nombre no cuelga
   ninguna decisión, y por eso se puede editar libremente desde acá: el mismo
   documento figura como «Carnet de Pescador» o «Pescador Artesanal» según quién
   lo cargó, y un `match` sobre ese texto se rompería en silencio.
   CAMBIAR EL PRECIO NO TOCA LO YA COBRADO
   `precio_bs` es el arancel de HOY, para armar un cobro nuevo. Lo que se cobró
   de verdad vive en `pagos` y no se recalcula nunca, así que un carnet emitido
   en marzo a 80 Bs sigue diciendo 80 Bs en agosto aunque el arancel haya subido.
   Lo que SÍ cambia al subir el precio es el saldo pendiente de los carnets que
   no estén cubiertos: `Carnet::montoACobrar()` lee esta columna. Es lo correcto
   —lo que se debe se debe a la tarifa vigente— pero conviene saberlo antes de
   tocar el número.
   Igual que en asociaciones, NO hay `destroy()`: los carnets emitidos apuntan
   acá. Un tipo que se deja de usar se pone en `estado = false`.

## `app/Http/Controllers/Publico/VerificacionController.php`

### VERIFICACIÓN PÚBLICA DE CARNETS — la única pantalla sin sesión

   Un pescador muestra su carnet, el inspector lee el código con el teléfono y
   cae en esta pantalla, que le dice si el documento es real, si está vigente y
   qué actividad autoriza. Por eso NO puede pedir login.
   HACE FALTA UN SOLO DATO: EL CÓDIGO DEL CARNET
   `carnets.codigo_carnet` es único GLOBAL —no por tipo— justamente para esto:
   un control en ruta lee un código y tiene que llegar a UN documento, sin
   preguntar antes de qué tipo es.
   LO QUE ESTO CUESTA, Y HAY QUE TENERLO PRESENTE: el código va IMPRESO en el
   plástico, así que quien tenga el carnet en la mano —o una foto— puede
   consultarlo. Se aceptó porque lo que se muestra acá es deliberadamente poco:
   nombre, cédula enmascarada, actividad y vigencia. Nada que no esté ya en la
   tarjeta que esa persona está mirando.
   Lo que sí protege del barrido automático es el `throttle` de la ruta. Un
   código corto y predecible sería adivinable, así que al generarlo conviene que
   lleve una parte al azar; eso es responsabilidad del módulo de carnets.
   REGLA DE ORO DE ESTA PARTE DEL SISTEMA
   Acá solo puede aparecer lo mínimo para constatar que un carnet es auténtico.
   Nunca la cédula completa, ni la dirección, ni el teléfono. Cada campo que se
   agregue queda expuesto a cualquiera. Ver datosPublicos().

### `vigente` NO es lo mismo que estado === 'activo'.

   Carnet::estaVigente() mira además la fecha, porque el estado lo
   escribe un comando programado y entre corrida y corrida un carnet
   vencido ayer sigue diciendo «activo» en la columna. Acá eso
   importaría de verdad: sería habilitar a alguien con un documento
   caído.

### LA ACTIVIDAD QUE ESTE CARNET AUTORIZA, Y SU CUPO.

   Es UNA, no una lista: cada actividad es un carnet propio.
   SE MANDA NULL Y NO EL NOMBRE cuando el carnet no está vigente, y
   no es un olvido: mostrar la actividad —aunque fuera marcada en
   rojo— arriesga que el inspector lea la fila y no el color. Lo que
   no habilita, no aparece.

## `app/Http/Controllers/StorageController.php`

### EL ÚNICO PUNTO DEL SISTEMA QUE ESCRIBE ARCHIVOS EN DISCO

   Ninguna otra clase llama a `Storage::put()`, `->store()` ni `->storeAs()`.
   Todo archivo que entra al sistema —la foto del beneficiario, la fotocopia del
   carnet, el certificado de la asociación, la boleta de cada depósito— pasa por
   `file()`.
   POR QUÉ UN EMBUDO Y NO CADA CONTROLADOR SUBIENDO LO SUYO
   Porque hay tres reglas que tienen que valer para TODOS los archivos, y una
   regla repartida por cinco controladores es una regla que tarde o temprano
   queda distinta en uno de ellos:
   1. EL LÍMITE DE PESO. Máximo 3 MB, sin excepción (ver `verificarPeso()`).
   2. EL NOMBRE. Nunca el que traía el archivo del usuario.
   3. EN QUÉ DISCO SE ESCRIBE. Local o s3, según la configuración.
   Con el embudo, agregar un módulo nuevo con adjuntos hereda las tres sin que
   nadie tenga que acordarse.
   EL NOMBRE DEL ARCHIVO SE INVENTA, NO SE CONSERVA
   `Str::random(20).time()` y la extensión, y nada más. El nombre original del
   usuario no se usa nunca, y eso NO es por estética:
   - un nombre como `../../.env.jpg` o con caracteres raros puede escaparse de
   la carpeta prevista según cómo lo trate el sistema de archivos;
   - dos personas suben `carnet.jpg` el mismo día y el segundo pisa al primero;
   - el nombre original suele traer datos personales («ci-juan-perez.jpg») que
   después quedan a la vista en la URL del archivo.
   Con un nombre aleatorio los tres problemas desaparecen de una vez.
   NO SE USA env() ACÁ, Y ES UNA REGLA DEL PROYECTO
   En producción se corre `php artisan config:cache`, y desde ese momento `env()`
   devuelve NULL en todo archivo que no esté en `config/`. El error es silencioso:
   el sistema creería que el disco no es s3 y escribiría los adjuntos en el
   servidor local sin avisar a nadie, hasta que alguien note que las boletas
   nuevas no aparecen en el bucket.
   Por eso todo sale de `config(...)`.

### GUARDA UN ARCHIVO Y DEVUELVE SIEMPRE UNA RUTA, NUNCA UNA URL

   'tramites/September2026/aB3x...1789.pdf'
   Lo mismo con el disco local y con s3. La dirección para abrirlo se arma
   AL LEER, con `App\Support\Archivos::url()`.
   POR QUÉ NO SE GUARDA LA URL COMPLETA, QUE ES LO QUE HACÍA ANTES
   Guardar la dirección parece más cómodo —ya está lista para poner en un
   enlace— y trae tres problemas, los tres reales:
   1. NO SE PUEDE BORRAR. Desde una dirección completa no hay forma de
   volver a la clave del objeto sin conocer el prefijo del bucket. Cada
   adjunto reemplazado quedaba ocupando lugar en s3 para siempre.
   2. SE CONGELA EL DOMINIO. La dirección queda escrita en la base el día
   de la carga. Si mañana cambia el bucket, el endpoint o el CDN, todos
   los enlaces viejos apuntan a donde ya no está el archivo, y hay que
   salir a reescribir filas.
   3. CONVIVEN DOS FORMAS EN LA MISMA COLUMNA. Según cómo estuviera
   configurado el sistema el día de la carga, la columna guarda una ruta
   o una dirección — y todo el que la lea tiene que acordarse de
   distinguirlas.
   Con la ruta sola, los tres desaparecen: se borra con la clave que se tiene,
   el dominio sale de la configuración actual, y hay una sola forma posible.
   EL PREFIJO DEL BUCKET NO SE ESCRIBE ACÁ
   El disco s3 se configura con `'root' => env('AWS_ROOT')` en
   config/filesystems.php, y Flysystem lo antepone solo en cada operación.
   Agregarlo a mano además —como se hacía— lo duplicaba: `dev/dev/tramites/`.

### EL LÍMITE DE 3 MB — la última línea de defensa

   Los formularios YA validan el peso, uno por uno, con la regla `max:` y un
   mensaje que le dice al operador cuánto pesa el archivo que eligió. Esta
   comprobación no reemplaza a aquella: normalmente no se dispara nunca.
   Está igual, y por un motivo concreto: la regla del formulario es una
   CONVENCIÓN —hay que acordarse de escribirla en cada Form Request nuevo—
   mientras que esto es ESTRUCTURAL. El día que alguien agregue un módulo con
   adjuntos y se olvide el `max:`, el archivo igual no entra: PHP acepta
   hasta `upload_max_filesize`, que en este servidor son 2 GB.
   Los kilobytes son de 1024 bytes, igual que los cuenta la regla `max` de
   Laravel y que los mide el navegador con `archivo.size`. Así un archivo que
   pasa el control del navegador pasa también acá, sin casos raros justo en
   el límite.
   Se lanza ValidationException y no una excepción cualquiera para que el
   error llegue como un mensaje bajo el formulario —y como un 422 si algún
   día esto se consume desde una API— en vez de como un error 500 que no le
   explica nada a nadie.

## `app/Http/Requests/Panel/AnularGuiaRequest.php`

### Reglas para anular una guía.

   UN SOLO CAMPO, Y ES EL MOTIVO
   El código sale de un talonario de papel que puede estar circulando dentro de
   un camión. Anular quema ese número para siempre —no se desanula— y deja un
   hueco en la serie que alguien va a tener que explicar dentro de seis meses.
   El mínimo de 10 caracteres está para que no se resuelva con «ok». No
   garantiza que el motivo sirva, pero sí que alguien haya tenido que escribir
   una frase.

## `app/Http/Requests/Panel/CobrarRequest.php`

### Reglas para emitir un cobro.

   ACÁ SOLO SE VALIDA LA FORMA. LOS SALDOS SE COMPRUEBAN EN EL SERVICIO
   Que el monto no exceda lo que se debe NO se comprueba acá, y no es un olvido:
   el saldo puede moverlo otra ventanilla en el mismo segundo, así que esa
   comparación tiene que correr DENTRO de la transacción y con la fila del
   trámite bloqueada. Ver CobrarService::resolver().

## `app/Http/Requests/Panel/CompletarFaenaRequest.php`

### Reglas para cerrar un permiso de faena.

   LOS KILOS SON OPCIONALES, Y ESE ES EL PUNTO
   Lo declarado al salir es una previsión; lo que se descargó lo dice la
   balanza. Casi siempre coinciden y el operador no escribe nada: la faena se
   cierra con lo que decía.
   Cuando NO coinciden, el campo permite corregirlo. Si la corrección es hacia
   arriba, el servicio vuelve a comprobar el saldo del cupo — de lo contrario
   cerrar una faena sería la forma de saltear el límite.

## `app/Http/Requests/Panel/EliminarCupoRequest.php`

### Reglas para ELIMINAR un aprovechamiento cargado por error.

   EL MOTIVO ES OBLIGATORIO, Y NO ES BUROCRACIA
   La fila se borra: después de esto no queda nada que mirar salvo la línea de
   `auditorias`. Si ahí no dice POR QUÉ, dentro de seis meses la única respuesta
   posible a «¿y el cupo de Fulano?» es «alguien lo borró».
   El mínimo de 10 caracteres es lo que separa una explicación de un «error».
   Coincide con el `minimo` que usa la ventana de confirmación del panel, y los
   dos tienen que moverse juntos o el botón se habilitaría antes de que el
   servidor acepte el texto.
   LO QUE NO SE VALIDA ACÁ
   Que el cupo esté pendiente, sin pagos y sin faenas. Esas tres corren DENTRO
   de la transacción y con la fila bloqueada: entre que el operador abre la
   ventana y confirma, otra ventanilla puede cobrarlo o emitirle una faena. Ver
   OtorgarCupoService::eliminar().

## `app/Http/Requests/Panel/EmitirCarnetRequest.php`

### Reglas para emitir una credencial.

   ACÁ SOLO SE VALIDA LA FORMA. LAS REGLAS VIVEN EN EL SERVICIO
   Que la persona no tenga ya un carnet vigente de esa actividad, y que un
   pescador tenga cupo, NO se comprueban acá. Las dos necesitan correr DENTRO de
   la transacción y con la fila del beneficiario bloqueada, o dos ventanillas
   simultáneas las pasan las dos. Un Request corre antes de todo eso.
   Ver EmitirCarnetService::emitir().

## `app/Http/Requests/Panel/EmitirFaenaRequest.php`

### Reglas para emitir un permiso de faena.

   ACÁ SOLO SE VALIDA LA FORMA. EL CUPO SE COMPRUEBA EN EL SERVICIO
   Que los kilos entren en el saldo NO se comprueba acá, y no es un olvido: el
   saldo puede moverlo otra ventanilla en el mismo segundo, así que esa
   comparación tiene que correr DENTRO de la transacción y con la fila del
   aprovechamiento bloqueada. Ver EmitirFaenaService::emitir().

## `app/Http/Requests/Panel/EmitirGuiaRequest.php`

### Reglas para emitir una guía de movimiento.

   ACÁ SOLO SE VALIDA LA FORMA. LAS REGLAS VIVEN EN EL SERVICIO
   Que el carnet HABILITE a emitir guías —que sea de comercializador y esté
   vigente— lo decide `EmitirGuiaService::emitir()`: son dos condiciones que
   dependen de la fecha de hoy y no se pueden expresar en una regla de
   validación.

## `app/Http/Requests/Panel/GuardarAsociacionRequest.php`

### DOS ASOCIACIONES NO PUEDEN LLAMARSE IGUAL.

   La regla replica el índice único PARCIAL de la base
   (`asociaciones_nombre_unico`), que solo mira las filas vivas. El
   `whereNull('deleted_at')` es lo que reproduce esa parcialidad:
   sin él, un nombre liberado por una baja seguiría bloqueado acá y
   la pantalla diría «ya existe» sobre algo que la base aceptaría.

## `app/Http/Requests/Panel/GuardarBeneficiarioRequest.php`

### Reglas de validación para crear y editar un beneficiario.

   ¿POR QUÉ UN FORM REQUEST Y NO VALIDAR EN EL CONTROLADOR?
   Porque crear y editar comparten exactamente las mismas reglas. Validando
   dentro del controlador habría que escribirlas dos veces, y el día que cambie
   una hay que acordarse de tocar los dos lados. Acá se escriben una sola vez.
   Laravel lo ejecuta ANTES de entrar al método del controlador. Si algo falla,
   el controlador nunca se ejecuta: Laravel redirige de vuelta al formulario con
   los errores, e Inertia los deja disponibles en React dentro de `errors`.

### Unicidad de la cédula.

   La regla replica el índice único PARCIAL de la base
   (`beneficiarios_ci_unico`): `ci` no puede repetirse entre
   registros VIVOS.
   VA SOBRE `ci` SOLA, sin el complemento, igual que el índice. El
   complemento es parte del MISMO documento, no de otro: metiéndolo
   en la comparación, cargar a la misma persona una vez con
   complemento y otra sin él pasaría los dos controles.
   whereNull('deleted_at') es lo que deja fuera a los dados de baja,
   y ->ignore() excluye al registro que se está editando.
   Que esté duplicada acá y en la base no es redundancia inútil: el
   índice garantiza, pero su error es ilegible; esta regla es la que
   pinta el mensaje bajo el campo.

## `app/Http/Requests/Panel/GuardarCategoriaAprovechamientoRequest.php`

### Reglas para crear y editar un tramo de la ESCALA OFICIAL.

   LA VALIDACIÓN QUE IMPORTA NO ES LA DE LOS CAMPOS: ES LA DE LOS TRAMOS
   Cada campo por separado puede estar perfecto y la escala quedar rota igual.
   Dos formas de romperla, y las dos son silenciosas:
   - SOLAPE. Si el tramo 3 llega a 500 y el 4 arranca en 400, un cupo de 450
   cae en los dos. `CategoriaAprovechamiento::paraVolumen()` devuelve el de
   `nro_escala` más bajo, así que el sistema cobraría siempre el más barato
   —y nadie lo notaría hasta un arqueo—.
   - HUECO. Si el 3 llega a 500 y el 4 arranca en 502, un cupo de 501 no cae
   en ninguno: `paraVolumen()` devuelve null y el formulario no ofrece
   ninguna escala, sin ningún error que lo explique.
   El SOLAPE se RECHAZA acá: es plata mal cobrada y no tiene lectura válida.
   El HUECO no se rechaza, y es deliberado: cargar la escala de a un tramo por
   vez deja huecos TRANSITORIOS —al guardar el tramo 1 todavía no existe el 2—
   y bloquearlos haría imposible cargarla. Se avisa desde la pantalla, que ve
   la escala entera de una vez. Ver el índice de la escala.

## `app/Http/Requests/Panel/GuardarTipoCarnetRequest.php`

### Reglas para crear y editar un tipo de carnet.

   EL PRECIO SE VALIDA COMO PLATA, NO COMO UN NÚMERO CUALQUIERA
   `decimal:0,2` rechaza «80.999», que en la base se guardaría redondeado a
   81.00 sin decirle nada a nadie. En un arancel esa diferencia se arrastra a
   cada carnet emitido.
   Se acepta el 0 —un carnet gratuito por resolución es posible— pero no un
   negativo, que no significa nada.

## `app/Http/Requests/Panel/GuardarUsuarioRequest.php`

### Reglas de validación para crear y editar un FUNCIONARIO del sistema.

   Es el hermano de GuardarBeneficiarioRequest, pero del otro lado del
   mostrador: aquel valida al ciudadano que viene a hacer un trámite, este
   valida a la persona de la Gobernación que lo atiende y que va a tener una
   cuenta con contraseña.
   ¿POR QUÉ LOS DOS CASOS —ALTA Y EDICIÓN— EN UN SOLO ARCHIVO?
   Porque comparten casi todo: el nombre, la cédula, el correo, el cargo y el
   rol se validan igual siempre. Lo ÚNICO que cambia es la contraseña: al dar
   de alta es obligatoria, al editar es opcional —dejar el campo vacío
   significa «no la toques»—. Esa diferencia se resuelve con una línea
   (`$esAlta`) en vez de con dos archivos que hay que mantener sincronizados.
   No hay auto-registro en este sistema: las cuentas las crea el administrador
   y ahí mismo se resetean las contraseñas (ver routes/panel.php y la nota de
   la migración de usuarios, que explica por qué no existe «olvidé mi
   contraseña»). Por eso este formulario es la única puerta por la que entra
   una cuenta nueva, y es donde tienen que estar todas las defensas.

### Cédula del funcionario.

   OJO: acá la regla es la ÚNICA defensa. La tabla `users` guarda
   `ci` sin índice único (ver la migración
   2026_09_01_100000_add_institutional_fields_to_users_table), así
   que dos peticiones simultáneas podrían colar la misma cédula.
   Con un solo administrador cargando usuarios a mano eso no pasa,
   pero conviene saberlo: el día que se agregue el índice, se copia
   el índice PARCIAL de beneficiarios (ver su migración),
   nunca uno que incluya `deleted_at`.
   whereNull('deleted_at') deja fuera a los funcionarios dados de
   baja: si alguien renunció, su cédula tiene que poder volver a
   usarse el día que lo recontraten.

### Correo institucional. ES EL USUARIO CON EL QUE SE INICIA SESIÓN

   ACÁ LA REGLA VA A PROPÓSITO SIN whereNull('deleted_at'), AL
   REVÉS QUE LA CÉDULA DE ARRIBA.
   El motivo es que `users.email` tiene un índice único COMPLETO,
   puesto por la migración original de Laravel, que no sabe nada de
   borrado lógico. Para la base de datos, el correo de un
   funcionario dado de baja sigue ocupado.
   Si acá se filtrara por deleted_at, la validación diría que el
   correo está libre, el controlador intentaría guardar, y
   PostgreSQL cortaría con un error 23505 —pantalla de error 500,
   sin mensaje útil para quien está cargando el usuario—. La regla
   de validación tiene que decir lo mismo que la base de datos, no
   lo que a uno le gustaría que dijera.
   Para que un correo se pueda reutilizar hay que cambiar primero
   el índice de la base por uno parcial, como se hizo con
   beneficiarios. Mientras tanto: restaurar al funcionario dado de
   baja en vez de crear uno nuevo.

### El rol decide TODO lo que la persona puede hacer: de él salen

   Rule::enum y no una lista escrita a mano porque los roles viven
   en App\Enums\RolSistema (regla 6 del proyecto: los enums mandan).
   Si mañana se agrega un rol, esta validación se entera sola.
   Es UN rol y no varios, aunque Spatie permita asignar muchos: los
   cuatro roles del sistema son escalones, no capacidades sueltas
   —supervisor ya incluye todo lo de operador—, así que acumular
   dos solo serviría para confundir a quien audite.

### Contraseña.

   En el ALTA es obligatoria. En la EDICIÓN es opcional: el campo
   vacío quiere decir «dejala como está», que es lo que espera
   quien entra solo a corregir un cargo mal escrito. Ese vacío lo
   convierte en null prepareForValidation(), y `nullable` hace que
   las reglas de fuerza ni se ejecuten.
   `confirmed` obliga a que venga también `password_confirmation`:
   como la escribe el administrador y no su dueño, un dedazo acá
   deja a alguien sin poder entrar y sin forma de recuperarla.

### NADIE SE CIERRA LA PUERTA A SÍ MISMO.

   Un administrador editando su propia ficha no puede
   desactivarse ni bajarse de rango: apretaría guardar y en el
   siguiente clic el sistema no lo dejaría volver a entrar para
   deshacerlo. Como no hay «olvidé mi contraseña» ni
   auto-registro, la única salida sería tocar la base a mano.

### Y EL SISTEMA NO SE QUEDA SIN ADMINISTRADOR.

   Distinto del caso de arriba: acá un administrador degrada o
   desactiva a OTRO, y resulta que ese otro era el último que
   quedaba en pie. El resultado sería un sistema donde ya nadie
   puede crear usuarios, cambiar tasas ni tocar la
   configuración.
   La consulta corre solo cuando el funcionario editado ES
   administrador y se le está sacando el rol o la cuenta, que
   es un puñado de veces al año: no hace falta optimizarla.

## `app/Http/Requests/Panel/ObservarPagoRequest.php`

### Reglas para OBSERVAR un depósito.

   El motivo es obligatorio: quien corrige es otra persona, y sin el texto
   «observado» es una marca que nadie sabe cómo levantar.
   El mínimo de 10 coincide con el de la ventana del panel, como en
   `RechazarCupoRequest`: si fuera menor, el botón se habilitaría de más.

## `app/Http/Requests/Panel/OtorgarCupoRequest.php`

### Reglas para otorgar una bolsa madre.

   ACÁ SOLO SE VALIDA LA FORMA. LA REGLA VIVE EN EL SERVICIO
   Que la persona no tenga ya un cupo vigente NO se comprueba acá, y no es un
   olvido: esa comprobación tiene que correr DENTRO de la transacción y con la
   fila del beneficiario bloqueada, o dos ventanillas simultáneas la pasan las
   dos. Un Request corre antes de todo eso.
   Lo que sí se hace acá es lo que un Request puede garantizar solo: que los ids
   existan y que las fechas tengan sentido. Ver OtorgarCupoService::otorgar().

### Solo tramos VIGENTES. Un tramo derogado sigue existiendo en la

   El servicio lo vuelve a comprobar dentro de la transacción, y no
   es redundancia inútil: entre que el formulario se abre y se
   guarda pueden pasar minutos, y esta regla mira el momento del
   envío, no el del guardado.

### NO SE PUEDE OTORGAR CON FECHA FUTURA.

   El cupo vence con la gestión, así que una fecha del año que viene
   daría un cupo que arranca vencido —o que vale dos años—. Y una
   fecha futura dentro del mismo año habilitaría faenas antes de que
   el papel exista.
   Sí se acepta una fecha pasada: se carga en el sistema lo que se
   autorizó en papel la semana anterior, que es lo normal al poner
   al día una unidad.

## `app/Http/Requests/Panel/RechazarCupoRequest.php`

### Reglas para RECHAZAR un aprovechamiento presentado a revisión.

   EL MOTIVO ES LO ÚNICO QUE EXPLICA LA DEVOLUCIÓN
   Rechazar es devolverle el expediente a ventanilla, y lo que sigue es que lo
   corrijan. Sin el texto escrito, quien lo recibe no sabe QUÉ corregir y el
   expediente rebota: se vuelve a presentar igual y se vuelve a rechazar.
   El estado no recuerda el rechazo —vuelve a PENDIENTE, a secas— así que la
   línea de `auditorias` es todo el rastro que queda.
   El mínimo de 10 caracteres coincide con el de la ventana de confirmación del
   panel: si acá fuera menor, el botón se habilitaría antes de que el servidor
   acepte el texto.

## `app/Http/Requests/Panel/RegistrarPagoCupoRequest.php`

### Reglas para cargar UNO O VARIOS depósitos contra un aprovechamiento.

   LLEGAN EN UN ARREGLO PORQUE LA PANTALLA ES REPETIBLE
   El operador agrega tantas secciones como depósitos trajo la persona, y las
   manda todas juntas. Cada una es un depósito completo: su monto, su número de
   boleta, su fecha y su archivo.
   LOS NÚMEROS DE BOLETA SE COMPARAN CONTRA LA BASE **Y ENTRE SÍ**
   `Rule::unique` mira la tabla, no el resto del formulario: dos secciones con el
   mismo número pasarían las dos y recién chocarían contra el índice, con un
   error de base en la cara del operador. Por eso va además `distinct`.
   LO QUE NO SE VALIDA ACÁ
   Que la suma no exceda el saldo. Esa comparación corre DENTRO de la
   transacción y con la fila del cupo bloqueada: entre que se abre el formulario
   y se guarda, otra ventanilla puede haber cobrado. Ver CobrarService::resolver().

### ¿EL OPERADOR PIDIÓ ENVIARLO A REVISIÓN EN EL MISMO ACTO?

   Viene del botón, que cambia de texto según si las secciones cubren
   el monto: «Registrar depósitos» cuando falta, «Registrar y enviar
   a revisión» cuando alcanza. Es una INTENCIÓN, no un permiso: si al
   guardar el saldo no quedó en cero —porque otra ventanilla movió
   algo— el controlador registra igual y no envía.

## `app/Http/Requests/Panel/RevocarCarnetRequest.php`

### Reglas para revocar una credencial.

   UN SOLO CAMPO, Y ES EL MOTIVO
   Revocar es una SANCIÓN y no se revierte: el plástico queda en la calle sin
   valer, y la verificación pública va a decir «REVOCADO» a quien lo consulte.
   Sin un motivo escrito, dentro de seis meses nadie puede explicar por qué esa
   persona perdió su credencial.
   El mínimo de 10 caracteres está para que no se resuelva con «ok». No
   garantiza que el motivo sirva, pero sí que alguien haya tenido que escribir
   una frase.

## `app/Models/AprovechamientoPesq.php`

### LA BOLSA MADRE del pescador: el cupo anual en kilos, con fecha.

   LA REGLA DEL MÓDULO ES UNA RESTA, Y VIVE EN saldoKg()
   volumen_total_kg − (kilos de las faenas que consumen cupo) = saldo
   Cuando el saldo llega a cero no se emiten más faenas. Esa resta NO se guarda
   en ninguna columna: una columna `saldo` hay que actualizarla en cada alta,
   cada anulación y cada corrección, y se olvida una sola vez para que el número
   quede mintiendo para siempre sin ningún error que lo delate.
   UNA FAENA ACTIVA YA CONSUME CUPO, AUNQUE NO SE HAYA DESCARGADO NADA
   Es lo contrario de lo que parece intuitivo, y es el punto del cupo: si solo
   contaran las completadas, un pescador podría tener diez faenas abiertas por
   el volumen entero cada una. Lo que libera el volumen es que la faena VENZA
   sin cerrarse — ahí la salida no ocurrió. Ver EstadoFaena::consumeCupo().

### Kilos ya comprometidos por las faenas.

   Igual que en `montoPagado()` del trait, la primera rama es lo que evita
   una consulta agregada por fila en un listado: quien arma la pantalla hace
   `withSum('faenasQueConsumen', 'kilos_extraidos')` y acá se reusa.
   Y por lo mismo se pregunta si la CLAVE EXISTE y no si el valor es
   distinto de null: `withSum` devuelve NULL sobre un conjunto vacío, así
   que un cupo recién otorgado —sin ninguna faena— se caería a la consulta
   suelta con el withSum puesto.

### LOS KILOS QUE SE PASARON DEL CUPO

   En modo ESTRICTO siempre es cero: la emisión no deja pasar una faena que
   no entre. Existe por el modo FLEXIBLE, donde el tope no se comprueba.
   Y es justamente lo que `saldoKg()` no puede decir: ese método se corta en
   cero —un cupo excedido no es un saldo negativo del que seguir restando—
   así que sin este número el exceso sería invisible y volver a encender el
   modo estricto dejaría gente por encima sin que nadie supiera cuánto.

### ¿EL TOPE DE LA BOLSA MADRE SE HACE CUMPLIR?

   Sale de `jichi.aprovechamiento.estricto`, que a su vez lee
   APROVECHAMIENTO_ESTRICTO del .env. Por defecto TRUE: un sistema que
   arranca sin control y hay que acordarse de encender no controla nada.
   Vive en el modelo y no repartido por los servicios y las pantallas porque
   la respuesta tiene que ser LA MISMA en los tres lugares donde se
   pregunta: el servicio que emite, el método que dice si se puede emitir, y
   el formulario que avisa antes. Leída tres veces con `config()` suelto,
   alcanza con que alguien cambie la clave en un lado.
   NUNCA con env() acá: con `config:cache` activo devuelve null fuera de
   config/, y el error sería silencioso — el sistema creería que el modo es
   flexible y dejaría de controlar el cupo sin avisar.

### ¿Se pueden corregir sus datos HOY?

   NO ALCANZA CON EL ESTADO: SE MIRA TAMBIÉN SI ENTRÓ PLATA
   El estado es la regla, pero puede quedar desfasado por abajo. Si alguien
   cargara un pago sin pasar por `CobrarService` —una corrección a mano en la
   base, una importación— el cupo seguiría diciendo `pendiente` con un recibo
   ya emitido detrás, y editarlo cambiaría lo que ese papel dice.
   Preguntar las dos cosas cuesta una consulta y cierra el agujero.

### ¿SE PUEDE MANDAR A QUE ALGUIEN LO FIRME?

   DOS condiciones, y la segunda es la que pidió la unidad: el estado tiene
   que ser PENDIENTE y los depósitos tienen que CUBRIR el monto.
   No alcanza con el estado porque un cupo a medio pagar sigue siendo
   pendiente, y presentarlo así obligaría a quien firma a devolverlo — que es
   trabajo de ida y vuelta por algo que la pantalla puede ver antes.
   Se compara con `>= 0` sobre el saldo y no con una igualdad: pagar de más
   no deja saldo negativo —`saldoPendiente()` se corta en cero— así que lo
   que se pregunta es si quedó algo sin cubrir.

### ¿ESTÁ DENTRO DE SU PERÍODO? — sin mirar el estado

   Es la mitad «calendario» de `estaVigente()`, y existe separada porque
   confundir las dos ya costó dos errores reales:
   - EMITIR UNA FAENA hacía lo mismo y devolvía «no tiene aprovechamiento
   vigente» sobre un cupo que existe y está en fecha, mandando al
   operador a otorgar uno nuevo, que la regla de una bolsa por
   persona iba a rechazar.
   La diferencia en una línea: un cupo agotado SÍ está en fecha —lo que se
   le acabó son los kilos, no el tiempo— así que lo que corresponde decir es
   «quedan 0 kg», no «no existe».

### LOS CUPOS QUE OCUPAN EL LUGAR DE UNA PERSONA HOY

   Es `vigentes()` MÁS los pendientes de pago, y la diferencia importa en dos
   lugares donde usar `vigentes()` estaría mal:
   - LA REGLA DE UNA BOLSA POR PERSONA. Un cupo sin cobrar ocupa el lugar
   igual: si no contara, alguien podría otorgar cinco cupos seguidos sin
   pagar ninguno y quedarse con el más conveniente.
   - EL CARNET DE PESCADOR, que imprime el volumen. Lo único que necesita
   del cupo es que exista y esté en fecha; el carnet también nace sin
   pagar, y los dos se cobran juntos en el mismo recibo. Exigiendo
   `vigentes()` no se podría emitir la credencial hasta cobrar el cupo, y
   la ventanilla no podría cobrar las dos cosas de una.
   EN REVISIÓN entra por los dos motivos a la vez: ocupa el lugar igual —si no
   contara, alguien podría recibir un segundo cupo mientras el primero está
   presentado— y el carnet tiene que poder emitirse mientras tanto, porque lo
   único que necesita del cupo es el volumen, que ya está decidido.
   NO incluye `agotado` —eso no cambió— ni, obviamente, los vencidos.

## `app/Models/Asociacion.php`

### UN default de la base NO llega al objeto que devuelve create().

   El INSERT lo aplica el motor y el modelo en memoria se queda con la
   columna en null hasta que alguien haga refresh(). Eso rompe lo obvio:
   crear una asociación y preguntarle el estado en la línea siguiente
   contesta null, con la fila ya escrita y correcta en la base.
   Va con `->value` y no con el enum: `$attributes` se llena ANTES de que
   corran los casts.

## `app/Models/Beneficiario.php`

### LA PERSONA, UNA SOLA VEZ. No hay columna de rol: quien pesca y además

   OJO: `nombreCompleto` NO va en Appends, aunque sea un accesor como los otros
   tres. La razón es el camelCase.
   Al serializar el modelo a array, Laravel busca el accesor por el nombre del
   método pasado a snake_case: para `nombreCompleto()` busca la clave
   `nombre_completo`. Si se lo agrega acá como `nombreCompleto`, no lo reconoce,
   cae al accesor de estilo viejo y revienta con «Call to undefined method
   getNombreCompletoAttribute()».
   No hace falta: `$beneficiario->nombreCompleto` funciona igual, y los
   controladores ya lo mandan a React con su nombre explícito.

### El nombre armado, en SQL.

   No existe columna `nombreCompleto`: el nombre se concatena cuando hace
   falta. Para BUSCAR y para ORDENAR eso tiene que ocurrir dentro de la
   consulta, porque la base no puede filtrar por algo que solo se calcula en
   PHP después de traer las filas.
   Detalles de la expresión:
   - COALESCE convierte los NULL en cadena vacía. Sin eso, concatenar un
   NULL en SQL da NULL: a quien no tiene segundo nombre se le borraría
   el nombre entero y no aparecería en ninguna búsqueda.
   - Las comillas dobles alrededor de cada columna son obligatorias por el
   camelCase: sin ellas PostgreSQL las pasa a minúscula y responde
   «column "primernombre" does not exist». SQLite también las acepta, así
   que la misma expresión sirve en los dos motores.
   - `||` es el concatenador estándar de SQL y funciona igual en los dos.

### Junta las cinco partes del nombre en el orden en que se lee una cédula.

   Es un accesor, no una columna: `$beneficiario->nombreCompleto` lo calcula
   en el momento. Así no puede quedar desfasado de sus partes —corregir un
   apellido cambia el nombre impreso en el acto—.
   El apellido de casada se guarda sin el «de» y se le agrega acá. Guardarlo
   con el «de» adentro rompería la búsqueda: quien escriba «Justiniano» en
   ventanilla no encontraría a la persona registrada como «de Justiniano».

### Los años cumplidos hoy. NULL si la ficha no tiene fecha de nacimiento.

   NO HAY COLUMNA `edad`, Y NO PUEDE HABERLA: la edad cambia sola. Guardada,
   haría falta un proceso que recorra el padrón todas las noches, y entre
   corrida y corrida el dato estaría mal para quien cumplió ese día.
   EL floor() NO SE PUEDE QUITAR. Carbon 3 devuelve un FLOAT en
   `diffInYears()`: para alguien nacido el 18 de julio de 2006 dice 20.15.
   Dejar que PHP lo convierta solo funciona —trunca hacia abajo— pero emite
   un «Deprecated: implicit conversion from float loses precision» en cada
   lectura, y en un listado de 30 filas eso son 30 líneas de ruido por carga.
   Con floor() explícito queda dicho además lo que se quiere: años CUMPLIDOS,
   no redondeados. Redondear haría figurar a alguien con un año de más
   durante seis meses.

### LA CREDENCIAL VIGENTE DE ESTA PERSONA PARA ESTA ACTIVIDAD, O NULL

   De lo que devuelva depende el resto: sin carnet hay que emitir uno; con
   carnet se reutiliza el que existe y lo que corresponde es renovarlo.
   RECIBE EL TIPO DE ACTOR Y ES OBLIGATORIO. Una persona puede tener dos
   credenciales al mismo tiempo, así que la pregunta sin el tipo no tiene
   una única respuesta: un método que devolviera «la primera» reutilizaría
   el carnet de Pescador para un trámite de Comercializador.
   Filtra por vigencia REAL —estado más fecha— y no solo por el estado,
   porque `estado` puede estar desfasado: `vencido` lo escribe un comando
   diario. Ver Carnet::estaVigente().
   SI LA RELACIÓN YA ESTÁ CARGADA, NO SE VUELVE A CONSULTAR.
   `$this->carnets()->where(...)` dispara una consulta SIEMPRE, aunque quien
   llamó haya hecho `with('carnets')` justamente para evitarlo: el `with()`
   queda escrito, se ve correcto, y el N+1 sigue ahí en silencio.

### Lo que debe en total, sumando lo pendiente de sus tres tipos de trámite.

   EL withSum ES LO QUE EVITA UNA CONSULTA POR TRÁMITE. Sin él, cada
   `saldoPendiente()` termina llamando a `$this->pagos()->sum(...)` y eso
   dispara una consulta agregada POR CADA carnet, cupo y guía de la persona.
   Con él, lo cobrado de todos viene en la MISMA consulta y el trait Pagable
   lo reusa —por eso `montoPagado()` pregunta primero por
   `pagos_sum_monto_parcial`—.
   LA RESTA SÍ SE HACE EN PHP, y es correcto: «cuánto falta» no es una resta
   a secas, se corta en cero porque pagar de más no genera saldo a favor.
   Esa regla vive en el trait y no se duplica acá.

### Búsqueda de ventanilla: CI, nombre, email o teléfono.

   El nombre se compara contra las cinco partes CONCATENADAS y no contra
   cada una por separado, porque el operador escribe «rosa antezana»: un
   nombre y un apellido pegados, que no coinciden con ninguna columna suelta.
   OJO CON EL COSTO: al no haber columna `nombreCompleto` guardada, ningún
   índice puede ayudar a esta comparación y la base recorre la tabla entera
   en cada búsqueda. Con el padrón actual es imperceptible; si algún día se
   vuelve lento, la salida es un índice funcional sobre esta misma expresión,
   no volver a guardar el nombre.

## `app/Models/Carnet.php`

### La credencial física que se entrega en ventanilla.

   EL CARNET ES LA LLAVE ANUAL; CON ÉL SOLO NO SE SALE A TRABAJAR
   carnet (pescador)        ──< permisos_faena     (una por salida)
   carnet (comercializador) ──< guias_movimiento   (una por traslado)
   Qué puede emitir lo dice `tipo_actor`, NUNCA el nombre del tipo de carnet:
   `tipos_carnet` es un catálogo que edita la unidad desde el panel, y el mismo
   documento figura como «Carnet de Pescador» o «Pescador Artesanal» según quién
   lo cargó. Ver TipoActor::emiteFaenas() y ::emiteGuias().

### ¿Vale HOY?

   EL ESTADO GUARDADO PUEDE MENTIR, Y POR ESO SE MIRA TAMBIÉN LA FECHA
   `vencido` lo escribe un comando programado que corre una vez al día.
   Entre corrida y corrida, un carnet que venció ayer sigue diciendo
   «activo» en la base. Ninguna decisión se toma leyendo la columna sola.

### El cupo que se imprime en el plástico, o null si no corresponde.

   NO SE DECIDE CON UN match SOBRE EL NOMBRE DEL TIPO DE CARNET. La pesca se
   autoriza por volumen —tantos kilos, contrastables contra una guía de
   transporte—; la comercialización no. De esa distinción cuelgan cuatro
   cosas: el formulario muestra u oculta el campo, la validación lo exige o
   lo PROHÍBE, la ficha lo muestra o no, y el plástico imprime el renglón
   CUPO o le da la tira entera al tipo de actor.

## `app/Models/CategoriaAprovechamiento.php`

### Un tramo de la ESCALA OFICIAL de aprovechamiento pesquero.

   Convierte una decisión administrativa —«a esta persona le corresponde la
   escala 3»— en los dos números con los que trabaja el sistema: el volumen en
   kilos y lo que se cobra por él.
   NO SE BORRA UNA ESCALA. Los aprovechamientos otorgados apuntan acá para
   dejar constancia de bajo qué tramo se autorizaron; una escala derogada se
   pone en `estado = false` y desaparece del formulario sin tocar lo histórico.

## `app/Models/GuiaMovimiento.php`

### El amparo de UN traslado de producto pesquero.

   Lo que la faena es para el pescador, la guía es para el comercializador: el
   carnet habilita el año, la guía habilita el viaje. Dice de dónde a dónde, con
   cuánta carga, y vale COMO MÁXIMO 5 DÍAS.
   `es_piscicultura` NO ES UN DATO DESCRIPTIVO: ES PLATA
   Marcado, el arancel se cobra al 50%. El pescado de criadero no sale del río,
   así que no consume el recurso que la tasa viene a proteger.
   El descuento se aplica en UN SOLO lugar —`factorArancel()`— y no se replica
   en el controlador ni en React. Escrito en tres lados, el día que la
   resolución cambie el 50% a 40% se corrige en dos y el tercero sigue cobrando
   mal sin que nadie lo note hasta el arqueo.
   LAS FECHAS SON MOMENTOS, NO DÍAS
   Cinco días se cuentan desde la HORA de emisión: una guía emitida a las 18:00
   del lunes vence a las 18:00 del sábado, no a la medianoche del viernes.
   Y por eso, al mandarlas a React van con `toIso8601String()` —son instantes—
   mientras que las de faenas y carnets van con `toDateString()`. Mandar un día
   como instante lo corre: en UTC-4, `2026-09-17T00:00:00+00:00` se muestra como
   16/09.

### EL ÚNICO LUGAR DONDE VIVE EL DESCUENTO DE PISCICULTURA

   Devuelve por cuánto se multiplica el arancel: 1.0 para producto de río,
   0.5 para producto de criadero.
   Se expone como factor y no como «monto con descuento» a secas porque el
   mismo número lo necesitan tres pantallas distintas —el cobro, la vista
   previa y el reporte de recaudación— y cada una parte de una tarifa
   distinta.

## `app/Models/Pago.php`

### Un abono: una entrega de dinero, contra un trámite y bajo un recibo.

   ES POLIMÓRFICA PORQUE EL NÚMERO DE RECIBO ES ÚNICO GLOBAL
   Se cobran tres cosas —la credencial, el cupo de pesca y la guía de traslado—
   y las tres se pagan igual. Una tabla de pagos por cada una obligaría a
   repetir el circuito de caja tres veces, y peor: el mismo papel podría amparar
   un carnet y una guía sin que nada lo impida.
   EL COSTO, Y HAY QUE TENERLO PRESENTE: SE PIERDE LA CLAVE FORÁNEA. El motor no
   puede exigir que `pagable_id` exista, porque no sabe en qué tabla buscarlo.
   La integridad la sostienen los RESTRICT de las otras tablas y la aplicación.
   `monto_parcial` SE LLAMA ASÍ PORQUE LA REGLA ES QUE PUEDE SER PARCIAL
   Un carnet de 80 Bs admite dos filas de 40, cada una con su recibo y su fecha.
   Lo que se DEBE no se guarda en ninguna columna: es el precio menos la suma de
   estas filas, y lo calcula el trait Pagable al leer. Guardado, quedaría
   desfasado en cuanto alguien corrija un abono.

### El trámite que este abono paga: un Carnet, un AprovechamientoPesq o una

   NO SE PRECARGA CON `with('pagable.beneficiario')`. Eloquent no sabe qué
   es `pagable` hasta que lee la fila, así que no puede resolver lo que
   cuelga de él: lo escrito así se IGNORA y el N+1 sigue ahí, sin ningún
   error. Va con morphWith, declarando qué traer para cada tipo:
   Pago::with(['pagable' => fn ($m) => $m->morphWith([
   Carnet::class              => ['beneficiario', 'tipoCarnet'],
   AprovechamientoPesq::class => ['beneficiario', 'categoria'],
   GuiaMovimiento::class      => ['comercializador'],
   ])])

## `app/Models/PermisoFaena.php`

### La autorización de UNA salida de pesca.

   APUNTA A DOS COSAS A LA VEZ, Y LAS DOS HACEN FALTA
   - `aprovechamiento` es DE DÓNDE SALEN LOS KILOS: la bolsa madre contra la
   que se descuenta.
   - `carnet` es QUIÉN LOS EXTRAE: la credencial que un control en el río va
   a pedir.
   Las dos apuntan a la misma persona, pero por caminos distintos y con vidas
   distintas —el cupo se renueva por resolución y el carnet por gestión, no
   siempre en la misma fecha—. Guardar solo una obligaría a deducir la otra, y
   la deducción falla justamente en el caso raro: dos cupos vigentes, o el
   carnet renovado a mitad de un cupo.
   NO SE EDITA NI SE BORRA
   El número sale de un talonario de papel que el pescador se llevó. Borrar la
   fila deja un hueco en la serie que nadie puede explicar y libera un número
   que el índice único volvería a aceptar, así que dos salidas distintas
   podrían terminar diciendo ser el mismo papel. Se completa o se vence.

## `app/Models/Recibo.php`

### La CABECERA del comprobante oficial de caja.

   UN RECIBO, VARIOS PAGOS, POSIBLEMENTE DE TRÁMITES DISTINTOS
   recibo 0016 (180 Bs)  ──< pago 80 Bs  → carnet
   ──< pago 100 Bs → aprovechamiento
   Esa es la unidad del comprobante: la persona entrega la plata UNA vez y se
   lleva UN papel, aunque adentro esté pagando dos cosas. Por eso el detalle es
   polimórfico y la cabecera no sabe a qué trámite pertenece — no pertenece a
   ninguno en particular.
   TODO LO IMPRESO SE COPIA, PORQUE UN COMPROBANTE ES INMUTABLE
   `nombre_factura`, `nit_ci_factura` y `monto_total` se guardan acá en vez de
   leerse del beneficiario y de la suma de los pagos. Armado al vuelo, corregir
   un apellido en la ficha cambiaría los comprobantes ya entregados y una
   reimpresión de marzo saldría distinta de la original.
   Y además el comprobante puede ir a nombre de un TERCERO —la empresa que paga
   por el pescador—, que no es ningún dato de la ficha.

### El detalle: los abonos que este papel ampara.

   OJO AL RECORRERLO PARA IMPRIMIR: `pagable` es una relación POLIMÓRFICA y
   NO se puede precargar con `with('pagos.pagable.beneficiario')`. Eloquent
   no sabe qué es `pagable` hasta que lee la fila, así que lo escrito así se
   ignora en silencio y el N+1 sigue ahí. Va con `morphWith`, declarando qué
   traer para cada tipo. Ver Pago::pagable().

## `app/Models/TipoCarnet.php`

### Una clase de credencial y su arancel: «Carnet de Pescador», 80 Bs.

   ES EL CATÁLOGO, NO LA REGLA. Qué habilita el documento —si emite faenas o
   guías, si lleva cupo— lo dice `carnets.tipo_actor`, que es un enum de PHP.
   De este nombre no cuelga NINGUNA decisión: el mismo documento figura como
   «Carnet de Pescador» o como «Pescador Artesanal» según quién lo cargó, y un
   match sobre el texto rompería en silencio el día que alguien lo edite.

## `app/Models/User.php`

### NO LLEVA HasFactory, y su UserFactory se borró.

   Las cuentas del sistema no se generan al azar: las crea el administrador
   desde el panel, y la única que se siembra —admin@admin.com— la escribe
   UsuarioSeeder con `updateOrCreate`. El único que usaba `User::factory()`
   era el juego de pruebas, que se eliminó el 14/09/2026.
   `Beneficiario` sí conserva la suya: DemoSeeder la usa para poblar el
   padrón de prueba.

## `app/Services/CobrarService.php`

### CAJA — cobrar uno o varios trámites bajo UN recibo

   recibo REC-2026-0016 (180 Bs)  ──< pago  80 Bs  → carnet
   ──< pago 100 Bs  → aprovechamiento
   Es el tercer circuito del sistema, y atraviesa a los otros dos: se cobran la
   credencial, el cupo de pesca y la guía de traslado, y los tres se pagan
   igual.
   DOS REGLAS QUE SE VEN EN CADA LÍNEA DEL FORMULARIO
   1. SE PUEDE PAGAR EN CUOTAS. Un carnet de 80 Bs admite dos abonos de 40,
   cada uno con su recibo y su fecha. Lo que se debe no está en ninguna
   columna: es el precio menos la suma de los abonos, calculada al leer.
   2. NO SE COBRA MÁS DE LO QUE SE DEBE. `saldoPendiente()` se corta en cero,
   así que un excedente no se acredita a nadie: queda escrito, suma en la
   recaudación del día y desaparece. Por eso se rechaza.
   UN RECIBO, VARIOS TRÁMITES — Y POR ESO LOS PAGOS SON POLIMÓRFICOS
   La persona entrega la plata UNA vez y se lleva UN papel, aunque adentro esté
   pagando el carnet y el cupo. Una tabla de pagos por cada cosa cobrable
   obligaría a repetir este circuito tres veces, y peor: el número de recibo
   dejaría de ser único global, así que el mismo papel podría amparar un carnet
   y una guía sin que nada lo impida.

### Qué se puede cobrar, y cómo lo nombra el formulario.

   ES UNA LISTA BLANCA, Y ESO NO ES DECORACIÓN
   `pagos.pagable_type` guarda un nombre de clase. Si el formulario lo
   mandara directo, cualquiera podría escribir otro en el navegador y el
   sistema crearía filas apuntando a tablas que no tienen nada que ver.
   Con esta tabla el formulario manda una palabra corta —`carnet`, `cupo`,
   `guia`— y el servidor decide a qué clase corresponde.

### EL NÚMERO SE RESERVA DENTRO DE LA MISMA TRANSACCIÓN.

   `CorrelativoService` bloquea la fila del contador con
   SELECT ... FOR UPDATE, así que dos ventanillas cobrando al mismo
   tiempo nunca reciben el mismo número. Y si el cobro falla más
   abajo, el rollback devuelve también el contador — sin eso, cada
   intento fallido quemaría un número y la serie saldría con huecos
   que nadie puede explicar.

### LA BOLETA SE REPITE EN CADA LÍNEA DEL MISMO COBRO, y es

   EL NÚMERO SE DESAMBIGUA CON UN SUFIJO cuando el mismo
   depósito cubre varias líneas. Es único global —el índice
   lo exige— y la columna ya no admite NULL, así que repetirlo
   tal cual chocaría.
   Queda «0012345678» para el caso normal de una sola línea, y
   «0012345678-2», «-3»… cuando un depósito paga el carnet y
   el cupo a la vez. Se sigue leyendo cuál es la boleta.

### Carga depósitos SIN emitir recibo — el circuito del aprovechamiento.

   PENDIENTE ──< depósito 330,00 ──< depósito 82,50   (sin recibo)
   │
   [enviar a revisión] ──▶ UN recibo de 412,50 con los dos adentro
   El papel lo emite `emitirRecibo()` desde `RevisarCupoService`. El saldo se
   descuenta entre depósitos: si no, tres boletas por el total pasarían las
   tres —ninguna está escrita cuando se valida la siguiente—.

### Convierte una línea del formulario en un trámite real, comprobado.

   LA FILA SE BLOQUEA, Y NO ES DE MÁS
   El saldo se calcula sumando los abonos que ya tiene. Dos ventanillas
   cobrando el mismo carnet a la vez leerían las dos el mismo saldo
   —ninguna ve el abono de la otra, que todavía no está escrito— y las dos
   pasarían el control de «no cobrar de más». La persona terminaría pagando
   el doble, con dos recibos válidos y sin nada que lo delate.

### COBRAR YA NO ACTIVA NADA, Y ES DELIBERADO

   Este servicio tuvo un `activarSiQuedoPagado()` que pasaba el cupo a ACTIVO
   en cuanto el saldo llegaba a cero. Se retiró el 19/09/2026 al aparecer el
   estado EN REVISIÓN: con él, la plata entraba y el pescador quedaba
   habilitado en el acto, sin que nadie mirara las boletas contra el extracto.
   Hoy el cobro solo baja el saldo. El salto PENDIENTE → EN REVISIÓN lo da
   una persona desde la ficha, y solo con el monto cubierto; y de ahí a
   ACTIVO lo da otra, la que firma. Ver RevisarCupoService.

### El texto que se imprime cuando el operador no escribe uno.

   Se arma con los nombres de los trámites cobrados, que es exactamente lo
   que el papel tiene que decir. Dejarlo vacío haría un comprobante que no
   explica por qué entró esa plata — y el recibo es justamente el respaldo
   de eso.

## `app/Services/ControlarPagoService.php`

### El control de las boletas: validar, observar y corregir.

   PENDIENTE ──▶ VALIDADO    cuadra con el extracto del banco
   ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
   └──[corregir]──┘
   Un observado NO se valida: se corrige. Validarlo sin tocar el dato sería dar
   por bueno lo que se marcó como malo. Ver docs/MER.md.

## `app/Services/CorrelativoService.php`

### Reserva el siguiente número y lo devuelve CRUDO, sin formatear.

   POR QUÉ EXISTEN LAS DOS FORMAS
   `siguiente()` devuelve el código completo —SERIE-2026-0001— que es lo que
   quiere quien necesita un identificador legible y único por sí solo.
   La otra la pedían los RECIBOS: el talonario de papel trae el número pelado
   arriba a la derecha —0016— y lo guardaban como entero para poder
   ordenarlo y sacar el último de la serie, cosa que con el código
   formateado no se puede.
   HOY ESTE SERVICIO ESTÁ ESCRITO Y SIN USAR — otra vez
   Ya le pasó una vez, cuando el carnet dejó de tener columna `codigo`.
   Volvió con los recibos, y se fue de nuevo al retirarse la tabla
   `recibos`: el comprobante se arma al vuelo y su número es el id del
   trámite. Ver App\Support\ReciboArmado::numeroImpreso().
   NO SE BORRA, y conviene saber por qué: un correlativo es justamente el
   dato que NO se puede derivar de otras tablas. El día que Contabilidad
   exija una serie sin huecos —hoy la del recibo los tiene, porque no todo
   trámite emite comprobante— la parte difícil ya está resuelta acá: la
   reserva con la fila del contador bloqueada, que es lo que impide que dos
   ventanillas saquen el mismo número.
   La reserva —ese bloqueo— es la misma para las dos formas, y vive acá
   adentro una sola vez.

## `app/Services/EmitirCarnetService.php`

### PASO 3 DEL FLUJO — emitir la CREDENCIAL

   beneficiario + asociación + tipo (+ cupo si es pescador) ──▶ carnet
   El carnet es la llave ANUAL. Con él solo no se sale a trabajar: de él cuelgan
   los permisos operativos —faenas y guías— que autorizan cada día.
   LAS TRES REGLAS QUE VIVEN ACÁ
   1. Una credencial vigente POR ACTIVIDAD y por persona. Quien pesca y
   además comercializa tiene dos; lo que no puede tener son dos iguales.
   2. Un carnet de PESCADOR exige una bolsa madre vigente. El plástico
   imprime el cupo, y sin cupo tampoco se pueden emitir faenas — que es
   para lo único que sirve ese carnet.
   3. Un carnet de COMERCIALIZADOR no lleva cupo, y la columna queda en NULL.
   No es que «no se cargó»: la comercialización no se autoriza por volumen.
   Ninguna la puede garantizar la base: la primera depende de la fecha de hoy y
   las otras dos son condicionales. Por eso van acá, con la fila del
   beneficiario bloqueada.

### El alfabeto del código impreso.

   FALTAN 0, O, 1, I, L, 5 Y S A PROPÓSITO
   El código se lee de un plástico gastado, a veces se dicta por teléfono y
   se tipea a mano en la verificación pública. Esos siete caracteres son los
   que se confunden entre sí en cualquier tipografía, y una sola letra mal
   leída devuelve «no existe» — que en el muelle se lee como «carnet falso».
   Sacarlos cuesta poco: quedan 29 símbolos, y con siete posiciones al azar
   son 17 billones de combinaciones.

### Emite la credencial.

   SE BLOQUEA AL BENEFICIARIO, IGUAL QUE AL OTORGAR EL CUPO
   Sin el candado, dos ventanillas atendiendo a la misma persona pasan las
   dos comprobaciones —ninguna ve el carnet de la otra, que todavía no está
   escrito— y la persona se va con dos plásticos de la misma actividad, cada
   uno con su código válido. Después no hay forma de saber cuál vale.

### Da de baja una credencial, con motivo.

   NO SE BORRA NI SE REVIERTE
   El plástico está en la calle. Borrar la fila liberaría un código que el
   índice único volvería a aceptar, así que dos credenciales distintas
   podrían terminar diciendo ser la misma — y la verificación pública
   respondería por la nueva mostrando el nombre de otra persona.
   Tampoco se «desrevoca»: si la persona vuelve a estar en regla, lo que
   corresponde es emitirle una nueva, con su propio código. La vieja pudo
   haber quedado en manos de cualquiera.

### Su bolsa madre de esta gestión, o null.

   ACEPTA UN CUPO PENDIENTE DE PAGO, Y TIENE QUE ACEPTARLO
   Lo único que el carnet necesita del cupo es el VOLUMEN que va impreso en
   el plástico, y eso ya está decidido desde que se otorgó. El carnet también
   nace sin pagar, y los dos se cobran juntos en el mismo recibo — así llega
   la persona al mostrador—.
   Con `vigentes()`, que exige el cupo ya cobrado, el circuito quedaba
   trabado: no se podía emitir la credencial hasta cobrar el cupo, y entonces
   la caja nunca podía cobrar las dos cosas de una.

### EL CÓDIGO IMPRESO EN EL PLÁSTICO

   Forma: PES + 26 + siete al azar  ->  «PES26K7RJ2M», que se muestra como
   «PES2 6K7R J2M».
   LLEVA UNA PARTE AL AZAR, Y NO ES ADORNO
   El código es la llave de la verificación pública, que es una pantalla SIN
   SESIÓN. Un código correlativo —PES26-0001, 0002…— se recorre entero
   probando de 1 en adelante, y cualquiera podría listar el padrón de
   pescadores del año con un script.
   El prefijo y el año SÍ son predecibles, y está bien: sirven para que una
   persona sepa de un vistazo qué credencial tiene en la mano. Lo que
   protege son las siete posiciones al azar.
   EL REINTENTO NO SOBRA
   La probabilidad de repetir es ínfima, pero «ínfima» no es «cero», y la
   columna tiene un índice único: sin reintentar, esa colisión sería un error
   de base de datos en la cara del operador, con alguien esperando el carnet.
   Se prueba varias veces y recién ahí se rinde.

## `app/Services/EmitirFaenaService.php`

### PASO 4 DEL FLUJO — el PERMISO DE FAENA, una salida de pesca

   carnet (pescador) ──▶ faena ──▶ descuenta kilos de la bolsa madre
   El carnet es la llave ANUAL; con él solo no se sale a trabajar. Cada salida
   se autoriza con una faena, que dice cuántos kilos se pueden extraer y hasta
   cuándo vale.
   LA REGLA CENTRAL: LOS KILOS SALEN DE UN POZO QUE SE VACÍA
   Emitir una faena resta del saldo de la bolsa madre, y cuando el saldo llega a
   cero no se emiten más. Esa resta NO está en ninguna columna: es
   `volumen_total_kg` menos los kilos de las faenas que consumen cupo, calculada
   al leer. Ver AprovechamientoPesq::saldoKg().
   …SALVO EN MODO FLEXIBLE, Y ESO LO DECIDE EL .env
   `APROVECHAMIENTO_ESTRICTO=false` apaga la comprobación del tope: las faenas se
   emiten aunque superen el volumen otorgado. Existe para poner al día un padrón
   donde el papel ya fue más allá del cupo, y para arrancar en una unidad que
   todavía no tiene la escala cargada del todo.
   LO QUE EL MODO FLEXIBLE NO AFLOJA:
   - la FECHA del cupo, que sigue mandando. Lo que se relaja es el tope en
   kilos, no el calendario: una faena colgada de un cupo del año pasado
   sería un permiso sin ninguna autorización detrás.
   - el CARNET, que tiene que seguir vigente y ser de pescador.
   - el NÚMERO del talonario, que no se puede repetir.
   - el DATO: el exceso se sigue midiendo en `kilosExcedidos()` y las
   pantallas lo muestran, así que al volver a estricto se sabe exactamente
   quién está por encima.
   SE BLOQUEA EL APROVECHAMIENTO, NO EL CARNET
   Es la fila que contiene el recurso escaso. Dos ventanillas emitiendo faenas
   al mismo pescador a la vez leerían las dos el mismo saldo —ninguna ve la
   faena de la otra, que todavía no está escrita— y las dos pasarían el control:
   el cupo terminaría excedido sin que nada lo delate, porque cada faena por
   separado se ve correcta.

### Emite el permiso de una salida.

   EL NÚMERO LO ESCRIBE EL OPERADOR, Y NO SE GENERA SOLO
   Sale de un TALONARIO DE PAPEL que el pescador se lleva. El sistema PROPONE
   el siguiente —para no hacer contar hojas— pero no lo impone: si la hoja
   que el operador tiene en la mano dice otro número, hay algo que conviene
   mirar antes de seguir, no autocorregir en silencio.
   Lo único que el sistema garantiza es que no se repita dentro del mismo
   cupo, y eso lo sostiene el índice único `(aprovechamiento_id,
   numero_faena)` además de esta comprobación.

### SE PREGUNTA POR LA FECHA, NO POR `estaVigente()`.

   Un cupo AGOTADO no está «vigente» —su estado no habilita— pero SÍ
   está en fecha, y lo que corresponde decirle al operador es «quedan
   0 kg, hay que tramitar otro cupo», no «no tiene aprovechamiento». Con
   `estaVigente()` el mensaje mandaba a otorgar un cupo nuevo, que es
   justo lo que la regla de una bolsa por persona iba a rechazar.
   El caso de verdad sin cupo utilizable —el vencido— sigue cayendo
   acá, y la comprobación del saldo de abajo da el mensaje exacto
   para el agotado.

### EL CUPO SIN COBRAR TIENE SU PROPIO MENSAJE, y hace falta.

   Cae acá aunque esté en fecha y con saldo entero, porque lo que
   autoriza a pescar es la concesión PAGADA. Con el mensaje genérico
   de «no tiene aprovechamiento vigente», el operador saldría a
   otorgar otro —y la regla de una bolsa por persona lo rechazaría—
   cuando lo único que falta es cobrar el que ya está cargado.
   Va DESPUÉS de la fecha: un cupo pendiente y además vencido ya no
   se arregla cobrándolo.

### SI ESTA FAENA DEJÓ EL CUPO EN CERO, EL CUPO PASA A `agotado`.

   El estado es redundante con el saldo —que se calcula— y aun así
   vale la pena: permite filtrar y contar en los listados sin
   recalcular una resta por fila, y distingue «se acabaron los kilos»
   de «se acabó el tiempo», que se resuelven distinto. Ver
   EstadoAprovechamiento.
   SE MARCA TAMBIÉN EN MODO FLEXIBLE, y es a propósito: que no queden
   kilos es un hecho, lo emita o no el sistema. En ese modo el estado
   ya no bloquea nada —`puedeEmitirFaena()` ni lo mira— pero deja el
   listado diciendo la verdad, que es lo que hace falta el día que se
   vuelva a estricto.

### Registra que el pescador volvió y descargó.

   COMPLETAR NO CAMBIA EL SALDO, Y ESO SORPRENDE
   Los kilos ya estaban descontados desde que la faena se emitió: una faena
   ACTIVA consume cupo aunque todavía no se haya descargado nada. Si solo
   contaran las completadas, un pescador podría tener diez faenas abiertas
   por el volumen entero cada una.
   Completar es el cierre del circuito: deja el volumen firme y saca la
   faena de la lista de papeles que andan dando vueltas sin registrar la
   vuelta.
   LOS KILOS SE PUEDEN CORREGIR AL CERRAR, Y HAY QUE PODER
   Lo declarado al salir es una previsión; lo que se descargó lo dice la
   balanza. Corregir hacia ARRIBA vuelve a comprobar el saldo —si no entra,
   se rechaza— porque de lo contrario cerrar una faena sería la forma de
   saltear el cupo.

## `app/Services/EmitirGuiaService.php`

### PASO 4 DEL FLUJO, RAMA COMERCIALIZADOR — la GUÍA DE MOVIMIENTO

   carnet (comercializador) ──▶ guía ──▶ ampara UN traslado
   Lo que la faena es para el pescador, la guía es para el comercializador: el
   carnet habilita el año, la guía habilita el viaje.
   TRES DIFERENCIAS CON LAS FAENAS, Y LAS TRES IMPORTAN
   1. NO TOCA NINGÚN CUPO. La comercialización no se autoriza por volumen, así
   que acá no hay recurso escaso que bloquear ni saldo que restar. Por eso
   este servicio no bloquea filas al emitir: no hay nada que dos ventanillas
   puedan gastar dos veces.
   2. LLEVA UN DESCUENTO. Si la carga es de piscicultura, el arancel se cobra
   al 50%: el pescado de criadero no sale del río y no consume el recurso
   que la tasa viene a proteger. La regla vive en
   `GuiaMovimiento::factorArancel()` y no se replica en ningún lado.
   3. SÍ SE ANULA. `EstadoGuia` tiene ese estado y `EstadoFaena` no: una faena
   de más se deja vencer y libera su volumen sola, pero una guía emitida mal
   ampara un camión que puede estar en la ruta, y hay que poder decir que
   ese papel no vale.
   LAS FECHAS SON MOMENTOS, NO DÍAS
   Cinco días se cuentan desde la HORA de emisión: una guía emitida a las 18:00
   del lunes vence a las 18:00 del sábado, no a la medianoche del viernes. Con
   fechas sin hora se le regalaría o se le quitaría casi un día al transportista.

### Emite el amparo de un traslado.

   EL CÓDIGO LO ESCRIBE EL OPERADOR, IGUAL QUE EL NÚMERO DE FAENA
   Sale de un TALONARIO DE PAPEL que viaja dentro del camión. El sistema no
   lo genera: si lo generara, el número del sistema y el del papel serían dos
   cosas distintas, y un control en ruta compara contra el papel.
   A diferencia del número de faena —que es correlativo DENTRO de un cupo—
   este es único GLOBAL: un control lee un código y tiene que llegar a UNA
   guía, sin preguntar antes de quién es.

### Registra que la carga llegó a destino.

   EL PESO SE PUEDE CORREGIR AL CERRAR, Y HAY QUE PODER
   Lo declarado al salir es lo que dijo la balanza del origen; al llegar se
   vuelve a pesar y casi nunca coincide al kilo. Corregirlo acá es lo que
   hace que el número que queda en el sistema sea el real y no el estimado.
   A diferencia de la faena, corregir hacia arriba NO tiene tope: no hay cupo
   que exceder. Lo único que cambia es el arancel si el peso entrara alguna
   vez en el cálculo — hoy no, porque la tarifa es plana.

### Da de baja una guía, con motivo.

   SE ANULA Y NO SE BORRA
   El código sale de un talonario de papel que puede estar circulando dentro
   de un camión. Borrar la fila deja un hueco en la serie que nadie puede
   explicar y —peor— libera un código que el índice único volvería a
   aceptar: dos traslados distintos podrían terminar diciendo ser el mismo
   papel.
   Y UNA GUÍA CERRADA NO SE ANULA
   Cerrar significa que la carga llegó: el traslado ocurrió y esta guía lo
   amparó. Anularla después sería declarar que nunca amparó nada, y deja un
   viaje real sin respaldo — justo lo contrario de para qué existe.

## `app/Services/OtorgarCupoService.php`

### PASO 2 DEL FLUJO DEL PESCADOR — otorgar la BOLSA MADRE

   beneficiario + escala  ──▶  aprovechamiento (volumen en kg, con fecha)
   De acá sale todo lo demás del módulo de pesca: el cupo que se imprime en el
   carnet y los kilos que descuentan las faenas.
   POR QUÉ ESTO ES UN SERVICIO Y NO CÓDIGO DEL CONTROLADOR
   Porque el mismo caso de uso lo necesitan el formulario del panel, un comando
   de consola —una carga masiva cuando sale la resolución— y cualquier prueba
   que se escriba. Puesto en el controlador, los otros dos lo copian, y las
   copias se quedan viejas.
   LO QUE SE COPIA, SE CONGELA
   `volumen_total_kg` se COPIA de la escala al otorgar. La escala cambia por
   resolución, y un cupo otorgado en marzo bajo un tramo de 500 kg no puede
   pasar a valer 800 en agosto porque alguien editó el catálogo. La
   `categoria_aprov_id` queda solo como referencia de bajo qué tramo se otorgó.
   El VALOR en bolivianos NO se copia, y es a propósito: lo que se debe se
   calcula contra la escala actual (`AprovechamientoPesq::montoACobrar()`), y lo
   que ya se pagó vive en `pagos`, que no se recalcula nunca.

### Otorga la bolsa madre a una persona.

   LA FILA DEL BENEFICIARIO SE BLOQUEA, Y NO ES PARANOIA
   La regla «una bolsa vigente por persona» no la puede garantizar ningún
   índice de la base: «vigente» depende de la fecha de hoy, y un índice
   único no sabe de fechas. Así que la comprueba este método.
   Sin el bloqueo, dos ventanillas atendiendo a la misma persona al mismo
   tiempo pasan las dos comprobaciones —ninguna ve el cupo de la otra, que
   todavía no está escrito— y la persona termina con el doble de kilos.
   Pasa poco, y cuando pasa no deja ningún rastro que lo explique.
   Se bloquea al BENEFICIARIO y no a los aprovechamientos porque es la fila
   que existe seguro: no se puede bloquear una fila que todavía no se creó.

### LA MODALIDAD TAMBIÉN SE COPIA, y por el mismo motivo que el

   Uno otorgado bajo escala general sigue siéndolo aunque su
   tramo pase después a especie especial.

### CORREGIR UN CUPO QUE TODAVÍA ES BORRADOR

   Acá no se le está dando más volumen a nadie: se está arreglando una carga
   equivocada antes de que exista ningún papel. El volumen y el monto se
   vuelven a copiar del tramo nuevo, igual que al otorgar.
   Solo corre en PENDIENTE y sin pagos. Con un abono encima hay un recibo
   numerado que dice qué se cobró: cambiar el tramo por detrás haría que el
   papel entregado dejara de coincidir con la fila, y nadie lo notaría.
   NO se toca `fecha_vencimiento` recalculándola desde cero por las dudas:
   se recalcula solo si cambió la fecha de emisión, que es de donde sale.

### ELIMINAR UN CUPO CARGADO POR ERROR

   ES UNA BAJA LÓGICA: la fila queda con `deleted_at` y desaparece de todas
   las consultas por el scope global de SoftDeletes. Eso incluye la regla de
   «una bolsa vigente por persona», que por lo tanto NO va a bloquear a nadie
   por un cupo dado de baja — que es lo único que había que cuidar acá.
   Se conserva y no se borra de verdad porque el cupo lleva el nombre de una
   persona y un volumen autorizado: aunque no haya llegado a cobrarse, que
   alguien haya cargado 2000 kg a nombre de Fulano y lo haya dado de baja
   cinco minutos después es exactamente el tipo de cosa que después hay que
   poder mirar.
   EL MOTIVO va en `motivoAuditoria`, que el trait Auditable lee dentro del
   evento `deleted` — el mismo que dispara la baja lógica.
   Las tres condiciones se comprueban con la fila bloqueada por lo mismo que
   en `editar()`: entre el clic y el borrado, otra ventanilla pudo cobrar el
   cupo o emitirle una faena.

### EL MOTIVO SE DEJA EN EL MODELO Y SE BORRA: no se llama a

   El trait Auditable ya engancha el evento `deleted` y escribe la
   fila con los valores que tenía la fila. Registrándola además acá
   salían DOS auditorías del mismo borrado —la mía con el motivo y la
   automática sin él—, y quien leyera el historial vería el hecho
   duplicado, una de las dos veces sin explicación.
   `$motivoAuditoria` es justamente el canal para esto: el trait lo
   lee dentro del evento.

### Hasta cuándo vale un cupo otorgado en esta fecha.

   VENCE CON LA GESTIÓN, NO AL AÑO DE OTORGADO
   Un cupo otorgado en octubre vence el 31 de diciembre, no el octubre
   siguiente. Es lo que hace que el volumen sin usar SE PIERDA al cerrar el
   año en vez de arrastrarse, y lo que permite que la unidad cuente cuántos
   kilos autorizó en una gestión sin tener que prorratear.
   Se guarda la fecha calculada en la fila en vez de derivarla al leer: si
   mañana una resolución cambia el criterio, los cupos ya otorgados tienen
   que seguir venciendo cuando dice el papel que la persona tiene en la mano.

## `app/Services/RevisarCupoService.php`

### EL CIRCUITO DE REVISIÓN DE UN APROVECHAMIENTO

   PENDIENTE ──[enviar, con el monto cubierto]──▶ EN REVISIÓN
   (borrador)                                         │
   ▲                              ┌──────────────┴──────────────┐
   └──────────[rechazar]──────────┤                             │
   [aprobar]                          │
   │                             │
   ACTIVO ──▶ recién acá emite faenas
   ENVIAR NO ES APROBAR, Y SON DOS PERSONAS DISTINTAS
   Ventanilla carga los depósitos y declara que el expediente está completo;
   quien firma mira las boletas contra el extracto del banco y recién ahí el cupo
   queda habilitado. Sin el paso del medio, la plata entraba y el pescador salía
   a pescar sin que nadie hubiera mirado nada — que es exactamente lo que este
   circuito viene a impedir.
   Por eso son PERMISOS distintos: `aprovechamientos.enviar` es de ventanilla y
   `aprovechamientos.aprobar` es de supervisión.
   RECHAZADO NO ES EL FINAL: VUELVE A PENDIENTE
   Rechazar es devolverle el expediente a ventanilla con el motivo escrito, y lo
   que sigue es que lo corrijan y lo vuelvan a presentar. Devolverlo a PENDIENTE
   es lo que permite eso: los pagos ya cargados SIGUEN AHÍ —cuelgan del cupo, no
   del envío— así que nadie tiene que volver a cargarlos.
   El rechazo queda en `auditorias` con su motivo; el estado no lo recuerda, y no
   hace falta que lo recuerde.

### PENDIENTE ──▶ EN REVISIÓN.

   Las dos condiciones se comprueban con la fila BLOQUEADA: entre que el
   operador ve el botón encendido y lo aprieta, otra ventanilla pudo anular
   un pago y dejar el cupo sin cubrir.
   ACÁ SE EMITE EL RECIBO DEL TRÁMITE, uno solo con el total. Va dentro de la
   misma transacción que el cambio de estado: o el cupo se presenta CON su
   papel o no se presenta.
   El NIT y el nombre son opcionales: el comprobante puede ir a nombre de un
   tercero. Sin ellos sale a nombre del beneficiario.

## `app/Support/Archivos.php`

### DÓNDE ESTÁ UN ARCHIVO GUARDADO, Y CÓMO SE ABRE

   `StorageController::file()` guarda siempre una RUTA:
   'tramites/September2026/aB3x...1789.pdf'
   La misma con el disco local y con s3. La dirección para abrirla se arma acá,
   AL LEER, con la configuración que el sistema tiene EN ESE MOMENTO.
   POR QUÉ LA URL SE ARMA AL LEER Y NO SE GUARDA
   Porque una dirección guardada queda congelada el día de la carga, y el lugar
   donde viven los archivos cambia: se pasa de local a s3, cambia el bucket,
   cambia el endpoint, se pone un CDN adelante. Cada uno de esos cambios dejaría
   rotos todos los enlaces viejos y obligaría a salir a reescribir filas.
   Armada al leer, un cambio de disco es cambiar el `.env`: las rutas guardadas
   siguen valiendo y los enlaces salen apuntando al lugar nuevo.
   Y sobre todo: **se puede borrar**. Desde una dirección completa no hay forma
   de volver a la clave del objeto sin conocer el prefijo del bucket, y por eso
   cada adjunto reemplazado quedaba ocupando lugar en s3 para siempre.

### FILAS VIEJAS: las que se cargaron cuando el sistema guardaba la

   Se devuelven tal cual. Pasarlas por `Storage::url()` las pegaría
   detrás del dominio local y saldría algo como
   `http://jichi.test/storage/https://gadbeni.sfo3...`, que no abre nada.
   Esta rama es compatibilidad hacia atrás y se puede sacar el día que no
   queden filas así en la base.

### EL CONTENIDO CRUDO de un archivo guardado. NULL si no hay nada, o si el

   Hace falta para IMPRIMIR. DomPDF corre del lado del servidor y no tiene
   navegador: una `<img src="/storage/...">` la resolvería contra el disco
   con las restricciones de `chroot` y en producción termina en un recuadro
   vacío. La foto del carnet va embebida en base64, y para eso hay que leer
   los bytes.
   NO SE LEE POR HTTP ni siquiera cuando el disco es s3: se pide por el
   disco de Flysystem, igual que se borra. Ir por la URL obligaría al
   servidor a salir a internet para dibujar un carnet —con su timeout y su
   proxy— y fallaría en cualquier despliegue donde el bucket no sea público.
   Las filas viejas que guardaron la dirección completa no se pueden leer
   por el mismo motivo por el que no se pueden borrar: desde una URL no hay
   forma de reconstruir la clave del objeto. Ahí se devuelve null y el
   documento sale sin foto, que es preferible a un error 500 que deje a
   ventanilla sin poder imprimir nada.

### LO VIEJO GUARDADO COMO DIRECCIÓN COMPLETA NO SE PUEDE BORRAR.

   Desde una URL no se puede reconstruir la clave del objeto con
   seguridad, y borrar la clave equivocada sería peor que no borrar. Se
   anota en el log para poder limpiarlo a mano y se sigue: el archivo de
   más no rompe nada, una excepción acá sí —este método se llama desde
   los `catch` de operaciones que ya fallaron—.

## `app/Support/CodigoQr.php`

### EL CÓDIGO QR DE LOS DOCUMENTOS IMPRESOS

   Devuelve un PNG en base64 listo para meter en un `<img>` de una plantilla que
   va a dibujar DomPDF.
   POR QUÉ NO SE USA simple-qrcode, QUE ESTÁ INSTALADO
   Porque su salida PNG necesita la extensión **imagick**, y este servidor no la
   tiene (`php -m` lista gd, no imagick). Con eso, `QrCode::format('png')` lanza
   «Extension 'Imagick' is required» y el carnet no sale.
   La otra salida que ofrece es SVG. DomPDF trae php-svg-lib y lo dibujaría,
   pero un QR es justamente el elemento donde no conviene depender de un
   renderizador aproximado: si los módulos salen medio píxel corridos la cámara
   deja de leerlo, y eso no se descubre hasta que alguien intenta verificar un
   carnet en la calle.
   Acá se usa directamente **BaconQrCode** —la librería que simple-qrcode trae
   adentro— para obtener la matriz de módulos, y se pinta con **gd**, cuadrito
   por cuadrito. Son treinta líneas, no dependen de ninguna extensión que no
   esté, y el resultado es un PNG de píxeles exactos: cada módulo mide un número
   entero de píxeles y no hay interpolación posible.
   POR QUÉ NO SE GUARDA EN DISCO
   Mismo criterio que el PDF del recibo: la imagen se deduce de la firma del
   carnet, así que el QR de mañana sale idéntico al de hoy. Guardarlo sería un
   archivo más que limpiar —y con el disco en s3, uno que no se puede borrar—.
   Por eso esto NO pasa por StorageController: no escribe nada.

### CORRECCIÓN DE ERRORES EN NIVEL «Q» —el 25% del código puede perderse

   El carnet es un plástico que vive en el bolsillo de alguien que
   trabaja en el río: se raya, se moja y se despinta. Subir el nivel
   agranda el código unos pocos módulos y compra que siga funcionando
   con el QR maltratado, que es la única condición en la que alguien lo
   va a escanear.

## `app/Support/Paginacion.php`

### Cuántas filas por página muestra un listado del panel.

   POR QUÉ LA LISTA ES CERRADA
   El número llega por la barra de direcciones (?por_pagina=30), así que
   cualquiera puede escribir lo que se le ocurra. Sin una lista cerrada, un
   ?por_pagina=500000 traería la tabla entera a memoria y voltearía el
   servidor —y no haría falta mala intención: alcanza con que alguien juegue
   con la dirección—.
   Cualquier valor que no esté en OPCIONES se ignora y se usa el de siempre.
   POR QUÉ VIVE ACÁ Y NO EN CADA CONTROLADOR
   Porque es una regla de seguridad, y una regla de seguridad escrita en cuatro
   lugares es una regla que tarde o temprano queda distinta en uno de ellos.
   Los listados de beneficiarios, trámites, carnets y pagos la usan; los de
   reportes la van a usar mañana.

### Lee el tamaño pedido y lo deja en un valor permitido.

   El valor por defecto sale de `config('jichi.por_pagina')`, que es el del
   sistema entero. Si alguien lo cambiara ahí por un número que no está
   entre las opciones, el selector quedaría marcando una opción que no es
   la que se ve; por eso en ese caso se cae a la primera de la lista, que
   siempre existe.

## `app/Support/ReciboImpreso.php`

### EL RECIBO, COMO LO NECESITA LA PLANTILLA IMPRESA

   Es un ADAPTADOR entre el modelo `Recibo` y `views/documentos/recibo-oficial`.
   La plantilla es una maqueta de coordenadas fijas, medida contra el talonario
   verde del SEDAG y afinada durante días —los contornos, el sello atenuado, los
   casilleros de la fecha—. Tocarla para que lea los nombres nuevos de las
   columnas sería rehacer ese trabajo para no ganar nada.
   Así que el modelo se adapta a la plantilla, y no al revés: esta clase expone
   exactamente lo que el Blade pide —`numeroImpreso()`, `montoEnLetras()`,
   `marca()`, `beneficiario_nombre`…— leyendo del modelo de hoy.
   A DIFERENCIA DEL ANTERIOR, ACÁ EL NÚMERO SÍ ESTÁ GUARDADO
   El `ReciboArmado` del modelo viejo tenía que usar el id del trámite como
   número, porque no existía la tabla `recibos` y un correlativo es justamente un
   dato que no se puede derivar. Hoy la tabla existe y `numero_recibo` es un
   correlativo de verdad —`REC-2026-0016`, reservado con la fila del contador
   bloqueada—, así que la serie NO tiene huecos y Contabilidad la puede auditar.

### EL MONTO EN LETRAS — el renglón «La suma de:»

   Sale así: «OCHENTA 00/100 BOLIVIANOS».
   El papel trae preimpreso un «-00/100» al final del renglón, que es la
   forma clásica de cerrar un importe escrito a mano para que nadie pueda
   agregarle centavos después. Se reproduce igual, con los centavos reales.
   `Number::spell()` usa la extensión intl, que ya es requisito del proyecto.
   Escribir a mano un conversor de número a palabras en castellano son
   doscientas líneas de casos especiales —«veintiuno», «quinientos», «un
   millón»— que ya están resueltas y probadas ahí.

## `app/Support/Sql.php`

### Expresión que reduce una columna de fecha al día 'YYYY-MM-DD', para

   No alcanza con `DATE($columna)`: en PostgreSQL eso devuelve un tipo date
   y en SQLite una cadena, así que la clave con la que vuelve el resultado
   cambia de forma según el motor y el `pluck` deja de encontrarla. Forzando
   el texto 'YYYY-MM-DD' en los dos, la clave es la misma.

## `app/Traits/Auditable.php`

### EL PORQUÉ DEL PRÓXIMO MOVIMIENTO

   Los eventos automáticos de abajo registran QUÉ cambió, pero no POR QUÉ. En
   casi todos los casos alcanza; en los que son decisiones —eliminar un
   expediente, anular un carnet— el motivo es lo único que sirve después.
   Quien va a hacer la operación deja el motivo acá antes:
   $tramite->motivoAuditoria = $motivo;
   $tramite->delete();
   y la fila de `auditorias` sale con su descripción. Sin esto habría que
   escribir una segunda fila a mano, y quedarían dos registros del mismo
   hecho: uno con el dato y otro con la explicación.
   ES ESPECIALMENTE ÚTIL AL BORRAR. Después del delete la fila ya no existe:
   si el motivo no viajó con el evento, no hay dónde colgarlo.

## `app/Traits/Pagable.php`

### Lo que sabe hacer un trámite que se cobra: carnet, aprovechamiento o guía.

   POR QUÉ ES UN TRAIT Y NO TRES COPIAS DEL MISMO CÓDIGO
   Las tres cosas que se cobran se pagan exactamente igual: en abonos, contra
   un recibo, y el saldo es el precio menos lo entregado. Escrita tres veces,
   esa resta se desincroniza sola —alguien corrige el corte en cero de un lado
   y los otros dos siguen devolviendo saldos negativos—.
   Lo único que cambia entre los tres es DE DÓNDE SALE EL PRECIO, y eso es
   justamente lo que el trait deja abierto en `montoACobrar()`.
   EL SALDO NO SE GUARDA EN NINGUNA COLUMNA, A PROPÓSITO
   Una columna `saldo` hay que actualizarla en cada alta, cada baja y cada
   corrección de un abono. Se olvida una y el número queda mintiendo para
   siempre, sin ningún error que lo delate. Calculado al leer no puede
   desfasarse: es siempre la resta de lo que hay hoy.

### Lo entregado hasta hoy.

   LA PRIMERA RAMA ES LO QUE EVITA UNA CONSULTA POR FILA
   `$this->pagos()->sum(...)` consulta SIEMPRE, aunque quien llamó haya
   hecho `withSum('pagos', 'monto_parcial')` justamente para evitarlo. En un
   listado de treinta carnets eso son treinta consultas agregadas, con el
   `withSum` escrito, viéndose correcto y sin ningún error.
   Cuando el atributo agregado vino en la consulta se usa ese; cuando la
   relación ya está cargada se suma en memoria; recién si no hay ninguno de
   los dos se consulta. Quien llama no tiene que saber en cuál de los tres
   casos está.
   OJO CON LA COMPROBACIÓN: se pregunta si la CLAVE EXISTE, no si el valor
   es distinto de null. `withSum` NO devuelve 0 cuando no hay filas: devuelve
   NULL, porque eso es lo que contesta `sum()` en SQL sobre un conjunto
   vacío. Comparando contra null, justamente el trámite SIN abonos —el que
   más aparece en un listado— se caía a la consulta suelta, y el withSum
   quedaba escrito, viéndose correcto, sin ahorrar nada.

### Los recibos bajo los que se cobró este trámite.

   Son VARIOS y no uno: pagar en dos cuotas son dos papeles distintos, cada
   uno con su número de caja. Por eso la relación no puede ser un belongsTo
   colgado del trámite.

## `database/migrations/0001_01_01_000000_create_users_table.php`

### NO HAY TABLA DE RECUPERACION DE CONTRASEÑA.

   Laravel la trae de fabrica —password_reset_tokens—, pero este sistema
   no tiene «olvide mi contraseña»: las cuentas las crea el
   administrador y ahi mismo se resetean. Ver routes/auth.php, que solo
   declara login y logout.
   Se retira porque una tabla que nadie escribe ni lee es una tabla que
   el proximo que lea el esquema va a tratar de entender. Si algun dia
   se agrega la recuperacion, vuelve con el flujo que la use.

## `database/migrations/2026_09_18_100300_create_beneficiarios_table.php`

### UNA PERSONA, UNA FICHA. Cargada dos veces, sacaría dos credenciales

   Índice PARCIAL y no `unique()` con `deleted_at` adentro: en SQL
   NULL != NULL y ese unique no bloquearía nada.
   Va sobre `ci` SOLO: el complemento es parte del mismo documento, y con
   él adentro la misma persona pasaría cargada una vez con y otra sin.

## `database/migrations/2026_09_18_100500_create_carnets_table.php`

### Único GLOBAL, y en los dos sentidos:

   - No por tipo: un control en ruta lee un código y tiene que
   llegar a UN documento sin preguntar de qué tipo es.
   - No parcial: a diferencia de los catálogos, un carnet dado de
   baja NO libera su código. El plástico ya salió de la impresora
   y está en la calle; reusar ese número haría que el mismo código
   llevara a dos documentos distintos.

## `database/migrations/2026_09_18_100600_create_permisos_faena_table.php`

### Dos hojas del talonario no pueden tener el mismo número DENTRO del

   Y NO es parcial: dar de baja una faena no libera su número. La hoja
   se gastó y el pescador se la llevó; reusar el número dejaría dos
   salidas distintas diciendo ser el mismo papel.

## `database/migrations/2026_09_18_100800_create_recibos_table.php`

### String y no entero: la serie lleva prefijo y año —REC-2026-0016— y

   Único GLOBAL y NO parcial: es un correlativo que Contabilidad
   audita. Un recibo dado de baja deja su número QUEMADO —el papel
   salió— y la serie conserva el hueco, que es justamente lo que la
   hace auditable.

### ÍNDICE, no columna: `created_at` ya la creó timestamps().

   Lo gana el ORDEN del listado de recibos, que hace
   `latest('created_at')` + paginado en cada carga: sin índice, cada
   página ordena la tabla entera para devolver quince filas. Ver
   ReciboController::index().
   Ese listado además filtra por rango con `whereDate()`, y eso NO
   usa este índice —envuelve la columna en una función—. Se arregla
   comparando contra instantes en vez de días; queda anotado.

## `database/migrations/2026_09_18_100900_create_pagos_table.php`

### NO HAY COLUMNA `metodo_pago`, Y NO ES UN OLVIDO

   En esta unidad NO se cobra en efectivo ni por QR: TODO pago es un
   depósito bancario. Una columna con un solo valor posible no informa
   nada —y peor, invita a suponer que algún día hubo otra cosa—.
   Por eso las tres columnas de abajo son OBLIGATORIAS: todo pago
   tiene su número de boleta, su fecha y su papel. Cuando existían el
   efectivo y el QR eran nullable, porque esos dos no traían boleta.

### LA FECHA QUE DICE LA BOLETA, que NO es cuándo se cargó.

   Un depósito hecho el viernes puede registrarse el lunes, y el
   arqueo tiene que poder mirar las dos cosas: `created_at` para
   cuadrar el trabajo del día, y esta para cruzar contra el extracto
   del banco.
   Es un DÍA y no un instante: va `date`, y a React con toDateString().

### LA FOTO O EL PDF DE LA BOLETA. Es una RUTA, no una dirección

   UNA POR PAGO y no por recibo: si la persona hizo dos depósitos,
   son dos boletas distintas y cada una respalda su monto. Guardada
   en el recibo, la segunda pisaría a la primera.
   La sube StorageController::file() —regla 11— que es el único que
   aplica el tope de 3 MB y el nombre al azar.

### LA MISMA BOLETA NO SE CARGA DOS VECES.

   Es la forma más fácil de que un trámite figure pagado sin que haya
   entrado la plata: cargar el mismo depósito contra dos cupos, o dos
   veces contra el mismo. El índice lo impide en la base, que es donde
   tiene que estar — dos ventanillas simultáneas pasarían cualquier
   comprobación de la aplicación.
   Va PARCIAL para dejar fuera las filas dadas de baja: un cobro anulado
   libera su boleta y se la puede volver a cargar bien. Ya no hace falta
   excluir los NULL —como antes, por el efectivo— porque la columna es
   obligatoria.

## `database/seeders/BeneficiarioSeeder.php`

### DATOS DE PRUEBA DEL PADRÓN — y son los ÚNICOS del sistema.

   POR QUÉ NO SE SIEMBRA NADA MÁS
   Los carnets, los cupos, las faenas y las guías se cargan a mano desde el
   panel, que es justamente lo que hay que probar. Sembrados, las pantallas se
   ven llenas sin que nadie haya recorrido el circuito, y el primer error real
   aparece en ventanilla.
   Lo que sí hace falta para que ese circuito arranque son los CATÁLOGOS
   —asociaciones, escala de aprovechamiento y tipos de carnet—, y no se siembran
   acá a propósito: no son datos de prueba sino datos oficiales, salen de una
   resolución y los carga la unidad desde el panel.
   LAS TRES PRIMERAS FICHAS SON FIJAS, NO ALEATORIAS
   Con todo al azar no se puede escribir un paso a paso —«abrí la ficha de
   1234567»— ni volver a la misma pantalla después de recargar la base. Estas
   tres tienen cédula conocida y cubren los tres casos que cambian cómo se arma
   y cómo se imprime el nombre.

### EL RESTO SE COMPLETA HASTA EL OBJETIVO, NO SE CREA DE NUEVO

   Las tres de arriba son idempotentes por el `updateOrCreate`, pero la
   factory NO: cada `create()` inserta filas nuevas. Escrito como
   `factory()->count(37)->create()` a secas, volver a correr el seeder
   —cosa que pasa sola al rearmar un entorno o al agregar un seeder
   nuevo— sumaba otras treinta y siete, y el padrón crecía de 40 a 77 a
   114 sin que nadie lo pidiera ni lo notara.
   Por eso se mira cuántas hay y se completa la diferencia. Corrido dos
   veces seguidas, la segunda no inserta nada.

## `database/seeders/CatalogoSeeder.php`

### Los tres CATÁLOGOS sin los que no se puede emitir nada.

   ⚠️  LOS NÚMEROS DE ACÁ SON UNA PLANTILLA, NO LA RESOLUCIÓN
   Los tramos de la escala, los precios y los nombres de las asociaciones salen
   de una resolución administrativa que no está cargada en el sistema. Lo que
   hay abajo se armó para que el circuito se pueda recorrer de punta a punta en
   desarrollo, y está marcado con `REVISAR` renglón por renglón.
   Antes de usar esto en la base real hay que reemplazar los valores.** Un
   carnet emitido con una tarifa inventada es plata mal cobrada, y el error no
   aparece hasta el arqueo.
   De dónde salen los números de la plantilla, para que se sepa qué se supuso:
   - Los DOS EXTREMOS de la escala son los únicos textos oficiales que había:
   «1 Kg Hasta 100 Kg» y «1001 kg Hasta 2000 Kg PAICHE».
   - Los cinco tramos del medio se repartieron a ojo entre esos dos extremos.
   - Los precios siguen una regla lineal de 55 Bs por cada 100 kg, que hace
   cerrar los dos valores conocidos de los tramos bajos (55 y 110). OJO: el
   tercer valor conocido —500 Bs— NO cae en esa recta, así que la escala
   real casi seguro NO es lineal. Es la señal más clara de que esto hay que
   confirmarlo.
   POR QUÉ firstOrCreate Y NO updateOrCreate
   Un catálogo lo edita la unidad desde el panel. Con `updateOrCreate`, volver a
   correr el seeder —cosa que pasa sola al rearmar un entorno— PISARÍA la
   tarifa que alguien ajustó, y nadie se enteraría hasta que un carnet saliera
   cobrando el número viejo.
   Con `firstOrCreate`, el seeder solo llena lo que falta. Para forzar la
   recarga de un valor hay que decidirlo a mano, que es lo correcto para un dato
   que sale de una resolución.

### La escala de aprovechamiento. REVISAR TODO: kilos, textos y precios.

   Los tramos tienen que ser CONTIGUOS y SIN HUECOS: el `kilos_min` de cada
   uno es el `kilos_max` del anterior más 1. Si queda un hueco,
   `CategoriaAprovechamiento::paraVolumen()` devuelve null para los
   volúmenes que caen adentro y el formulario no ofrece ninguna escala, sin
   ningún error que lo explique.

## `database/seeders/ConfiguracionSeeder.php`

### ¿QUIEN CARGA UN DEPÓSITO PUEDE VALIDARLO ÉL MISMO?

   La separación de funciones —«quien dice que entraron 150 Bs no
   puede además declarar que lo comprobó»— es lo correcto cuando hay
   dos personas. En una oficina de UNA sola deja el circuito trabado:
   el mismo usuario carga y por lo tanto no puede validar, y el
   trámite nunca se aprueba.
   Por eso es una configuración y no una regla escrita en el código:
   la unidad la enciende el día que haya un segundo usuario, sin que
   nadie tenga que tocar nada.
   ARRANCA APAGADA porque hoy hay un solo usuario. Lo que NO se
   pierde con eso es el registro: quién cargó, quién validó y cuándo
   se guarda igual, que es el dato que pidió la unidad.

## `database/seeders/DatabaseSeeder.php`

### El orden importa: RolPermisoSeeder tiene que correr antes que UsuarioSeeder

   WithoutModelEvents apaga los eventos de Eloquent durante el sembrado. Sin eso,
   el trait Auditable escribiría una fila en `auditorias` por cada uno de los
   registros de prueba, todas con usuario NULL porque no hay sesión: ruido que
   después hay que aprender a ignorar al mirar esa tabla.
   LOS ÚNICOS DATOS DE PRUEBA SON LOS DEL PADRÓN
   Los otros tres seeders no siembran datos de prueba sino lo que el sistema
   necesita para arrancar: los permisos, la configuración institucional y la
   cuenta con la que se entra.
   Los CATÁLOGOS —asociaciones, escala de aprovechamiento y tipos de carnet— van
   en `CatalogoSeeder`, y corren SOLO fuera de producción **mientras sus valores
   sean la plantilla**. No son datos de prueba —salen de una resolución— pero sin
   ellos no se puede emitir ni un carnet, así que en desarrollo hacen falta para
   recorrer el circuito.
   EN CUANTO ESOS NÚMEROS SEAN LOS DE LA RESOLUCIÓN, `CatalogoSeeder` SUBE al
   bloque de arriba, junto a los permisos y la configuración. Sembrado con
   valores inventados en la base real, el primer carnet emitido saldría cobrando
   una tarifa que nadie aprobó.

## `database/seeders/UsuarioSeeder.php`

### Crea la cuenta institucional del sistema.

   POR AHORA ES UNA SOLA, con el rol `administrador`, que tiene todos los
   permisos. Cuando la unidad defina quién firma qué se agregan los demás roles
   al enum RolSistema y acá sus cuentas.
   La contraseña sale de config/jichi.php, NO de env() directamente. Motivo: en
   producción se corre `php artisan config:cache`, y desde ese momento env()
   devuelve null fuera de los archivos de config/. Si acá se usara env(), el
   usuario quedaría con la contraseña por defecto 'password' sin que nadie se
   entere.

## `routes/panel.php`

### EL CIRCUITO DE REVISIÓN

   PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ ACTIVO
   ▲                       │
   └──────[rechazar]───────┘
   ENVIAR es de VENTANILLA: quien carga los depósitos declara que el
   expediente está completo. APROBAR y RECHAZAR son de SUPERVISIÓN: quien
   firma mira las boletas contra el extracto del banco.
   Son dos permisos distintos a propósito. Con uno solo, la misma persona
   cargaría la plata y se la aprobaría, y el control del medio no existiría.

### EL PLÁSTICO. Va ANTES de '{carnet}' aunque la URL sea más larga: el

   Es GET y devuelve bytes, no una pantalla de Inertia: el navegador lo abre
   en su visor de PDF, que es desde donde el operador aprieta imprimir.

### EL CONTROL DE LAS BOLETAS — la segunda mitad de la revisión.

   PENDIENTE ──▶ VALIDADO    cuadra con el extracto del banco
   ▲     └─▶ OBSERVADO   no cuadra, con el motivo escrito
   └──[corregir]──┘
   SON DOS PERMISOS DISTINTOS Y ESE ES EL PUNTO. Controlar es de supervisión
   —quien firma mira las boletas— y corregir es de ventanilla —quien las
   cargó arregla lo que tipeó—. Con uno solo, la misma persona objetaría y
   resolvería su propia objeción, y el circuito sería un adorno.
   Las tres cuelgan de /pagos/{pago} y no del trámite: el depósito es
   polimórfico y mañana se controla igual el de un carnet o el de una guía.
   Las pantallas las llaman desde la ficha del trámite y vuelven ahí.

## `routes/publico.php`

### throttle:60,1 = máximo 60 peticiones por minuto desde la misma IP.

   Es imprescindible en una ruta pública y sin sesión. Acá el motivo no es el
   costo de la consulta sino el ataque por fuerza bruta: cada visita es un
   intento de adivinar una firma. Con 16 caracteres alfanuméricos —unas 8 · 10^24
   combinaciones— y 60 intentos por minuto, acertar deja de ser una posibilidad
   práctica.

## `resources/js/app.tsx`

### PUNTO DE ENTRADA DE TODO EL FRONTEND

   Este es el primer archivo de JavaScript que corre en el navegador. Es corto
   a propósito: solo arranca Inertia y le explica dónde buscar las pantallas.
   EL RECORRIDO COMPLETO, DE PRINCIPIO A FIN
   1. El navegador pide /panel/beneficiarios
   2. Laravel atiende con BeneficiarioController@index
   3. El controlador devuelve Inertia::render('panel/beneficiarios/index', [...])
   4. Laravel pinta resources/views/app.blade.php, que carga ESTE archivo
   5. Inertia lee el nombre 'panel/beneficiarios/index' y llama a resolve()
   6. resolve() carga resources/js/pages/panel/beneficiarios/index.tsx
   7. React lo dibuja dentro del <div id="app"> del Blade
   A partir de ahí, al navegar dentro del sistema los pasos 4 a 7 se repiten
   SIN recargar el navegador: Inertia pide solo los datos nuevos y cambia el
   componente. Por eso se siente rápido aunque las rutas sean de Laravel.

### import.meta.glob es de Vite: registra de golpe TODOS los archivos .tsx que

   No los carga todos al arrancar: deja preparada una función por archivo y
   descarga cada pantalla recién cuando se visita. Eso se llama "code
   splitting" y es lo que evita que el primer ingreso al sistema tenga que
   bajar el código de los seis módulos de una vez.
   Consecuencia práctica: cada archivo nuevo en pages/ queda disponible solo,
   sin registrarlo en ninguna lista.

## `resources/js/components/comunes/codigo-qr.tsx`

### EL CÓDIGO QR QUE SE IMPRIME EN CADA DOCUMENTO

   Codifica la URL pública de verificación, que lleva la firma de validación:
   /verificar/{firma}. Es lo ÚNICO del carnet que la contiene —en el plástico se
   imprime el número de registro, que no abre nada—. El inspector lo escanea con
   cualquier lector del teléfono y cae directo en la pantalla que le dice si el
   carnet es auténtico y si está vigente.
   ¿POR QUÉ EL QR LLEVA UNA URL Y NO SOLO EL CÓDIGO?
   Porque un QR con el texto «4K7R-J2MX-P9TQ» adentro no hace nada: el lector
   muestra esa cadena y el inspector tendría que abrir el navegador, recordar la
   dirección del sistema y tipearla. Con la URL completa, escanear y verificar es
   un solo gesto.
   ¿Y POR QUÉ LA URL LA ARMA EL SERVIDOR Y SE RECIBE HECHA?
   Porque lleva la firma de validación, y porque el dominio público no es el de
   la red departamental: el ciudadano escanea desde su teléfono, fuera de la
   institución. Armarla acá con route() daría algo como http://jichi.test/... que
   no abre nada. Sale de config('jichi.url_verificacion'); ver
   CarnetController::urlVerificacion().
   ¿POR QUÉ SE GENERA EN EL NAVEGADOR Y NO EN PHP?
   Este componente es para las pantallas del panel, donde el QR se dibuja
   mientras el operador carga los datos. Generarlo en el servidor obligaría a
   una petición por cada tecla.
   El PDF que se imprime es otra historia: ahí el QR lo va a generar PHP con
   **simple-qrcode**, que ya está instalado, porque DomPDF no ejecuta
   JavaScript. Los dos codifican exactamente la misma URL.
   CORRECCIÓN DE ERRORES EN NIVEL ALTO ('H'). Permite reconstruir el código con
   hasta un 30% de la superficie dañada. No es un lujo: estos documentos viven
   doblados en el bolsillo de un pescador, se mojan y se despintan al sol.

## `resources/js/components/comunes/logo-jichi.tsx`

### Escudo del Gobierno Autónomo Departamental del Beni.

   Es el emblema institucional oficial, no una marca inventada para el sistema:
   el mismo que va impreso en la cédula de pescador y en los talonarios.
   OJO CON EL FONDO. El archivo trae el texto «GOBIERNO AUTÓNOMO DEPARTAMENTAL
   DEL BENI» en verde oscuro debajo del escudo. Sobre la barra lateral azul ese
   texto desaparece, por eso MarcaJichi lo apoya sobre un recuadro blanco. Si
   alguna vez hace falta el escudo suelto —sin la leyenda— conviene recortar una
   segunda versión del PNG en vez de escalar esta, que a tamaño chico se vuelve
   una mancha verde.

## `resources/js/components/comunes/retrato.tsx`

### La foto de una persona en una fila de tabla, o la silueta cuando no tiene.

   EL HUECO SE DIBUJA IGUAL, Y DEL MISMO TAMAÑO
   Sin él, las filas con y sin fotografía tendrían alturas distintas y la tabla
   quedaría dentada; peor todavía, la columna del nombre se correría de lugar
   entre una fila y otra, que es lo que más molesta al recorrer el padrón con la
   vista.
   POR QUÉ ESTÁ EN `comunes/` Y NO DENTRO DE UNA PANTALLA
   Porque lo usan dos listados que no se conocen entre sí —el padrón de
   beneficiarios y el de trámites— y los dos tienen que verse igual. Copiado en
   cada uno, alcanza con ajustar el tamaño en uno para que las dos tablas dejen
   de coincidir.

## `resources/js/components/panel/aprovechamientos/barra-saldo.tsx`

### Cuánto queda del cupo, en números y en barra.

   POR QUÉ ES UN COMPONENTE Y NO CÓDIGO DE LA PANTALLA
   Lo usan el listado y la ficha, y tiene que decir lo MISMO en los dos: el
   umbral de «le queda poco» pintado de un color en una pantalla y de otro en la
   siguiente es peor que no pintarlo, porque enseña a desconfiar del color.
   EL PORCENTAJE LLEGA CALCULADO DEL SERVIDOR
   No se divide acá. Un cupo de 0 kg no debería existir pero puede —un dato mal
   cargado— y esa división reventaría la fila entera. `porcentajeUsado()` del
   modelo devuelve 100 en ese caso y sigue.
   La barra se dibuja con un div de ancho porcentual y no con una librería: para
   un solo valor, traer recharts sería cargar 100 KB para pintar un rectángulo.

## `resources/js/components/panel/beneficiarios/formulario-beneficiario.tsx`

### EL FORMULARIO DE BENEFICIARIO

   ES UNO SOLO PARA CREAR Y PARA EDITAR, y no dos archivos casi iguales. Lo único
   que cambia entre una cosa y la otra es a qué URL se manda y si hay foto
   previa; los campos, las reglas y el orden son idénticos. Partido en dos, el
   día que se agregue un campo hay que acordarse de tocar los dos lados.
   POR QUÉ DOS COLUMNAS Y NO UNA PILA DE TARJETAS
   Porque cuatro secciones apiladas hacen una pantalla de tres pantallas de alto:
   la foto queda fuera de la vista, el botón de guardar también, y el operador
   pierde de vista lo que ya cargó apenas baja.
   A la izquierda van los campos, agrupados y numerados dentro de UNA SOLA
   tarjeta. A la derecha, FIJA mientras se hace scroll, la tarjeta de vista
   previa. Y abajo, también fija, la barra con el botón de guardar: siempre
   alcanzable, sin llegar al final.
   UNA TARJETA CON SEPARADORES, NO CUATRO TARJETAS
   Las cuatro secciones son partes de UN formulario, no cuatro formularios. En
   tarjetas separadas, el espacio entre ellas dice lo contrario —que son cosas
   independientes— y cada borde y cada sombra suma altura sin agregar
   información. Con una sola tarjeta y una línea entre secciones, la agrupación
   se sigue leyendo y la pantalla ocupa bastante menos.
   LA VISTA PREVIA NO ES DECORACIÓN
   Muestra las CUATRO COSAS QUE EL SISTEMA COMPONE y que el operador no puede
   ver de otro modo hasta después de guardar:
   - el nombre completo, armado a partir de cinco campos sueltos;
   - la cédula tal como se imprime, armada de tres;
   - la edad, calculada de la fecha de nacimiento;
   - la fotografía, recortada en círculo como va a salir en el carnet.
   Eso convierte errores que hoy se descubren tarde —el nombre quedó al revés, se
   tecleó 2090 en vez de 1990— en algo que se ve mientras se escribe.

### Un bloque del formulario: número, icono, título y los campos debajo.

   NO es una tarjeta: las cuatro secciones viven dentro de la MISMA tarjeta y se
   separan con una línea. Por eso el borde superior se pinta solo a partir de la
   segunda —`numero > 1`—: en la primera quedaría una raya pegada al borde de la
   tarjeta, que se lee como un error de dibujo.
   El número no es decoración: le dice al operador cuántos pasos le faltan y en
   qué orden conviene cargarlos, que es lo primero que se pregunta quien abre un
   formulario largo por primera vez.

### LAS TRES COMPOSICIONES DE LA VISTA PREVIA

   OJO: estas funciones REPITEN en TypeScript lo que ya hacen
   Beneficiario::nombreCompleto(), ::documentoIdentidad() y ::edad() en PHP, y
   eso normalmente sería un error —dos definiciones de la misma regla terminan
   diciendo cosas distintas—.
   Acá está aceptado, con una condición: lo de acá es SOLO UNA VISTA PREVIA. El
   valor que se guarda y el que se imprime en el carnet salen siempre del
   servidor; esto no viaja a ninguna parte. La alternativa sería pedirle al
   servidor que componga el nombre en cada tecla, que es una petición por letra
   para mostrar algo que se descarta al guardar.
   Si alguna de las tres reglas cambia en PHP, hay que cambiarla acá también. Por
   eso están juntas, al final del archivo y con este cartel, en vez de repartidas
   dentro del componente.

### La edad NO se calcula acá: vive en `lib/utils.ts`, junto a `fecha()`.

   El motivo es concreto: las dos tienen que interpretar igual una cadena
   `AAAA-MM-DD`. `new Date('1986-03-12')` da medianoche UTC, que en Bolivia
   (UTC-4) es todavía el 11 de marzo —y entonces la fecha se muestra un día
   antes y la edad se equivoca el día del cumpleaños—. Con las dos funciones en
   el mismo archivo comparten esa corrección en vez de arrastrar cada una la
   suya. Ver `aFechaLocal()`.

## `resources/js/components/panel/comunes/buscador-beneficiario.tsx`

### AUTOCOMPLETADO DE BENEFICIARIOS

   Lo usan todos los formularios que arrancan eligiendo a una persona: otorgar
   un cupo, emitir un carnet, emitir una faena o una guía.
   Vive en `comunes/` y no dentro de un módulo porque es exactamente el mismo
   problema en los cuatro. Copiado en cada uno, alcanzaría con que alguien
   arreglara el retardo en uno para que los otros tres siguieran castigando al
   servidor.
   EL RETARDO NO ES UN DETALLE DE PULIDO
   La búsqueda del servidor compara contra las cinco partes del nombre
   CONCATENADAS, y eso no lo puede resolver ningún índice: recorre la tabla
   entera. Sin retardo, escribir «antezana» son ocho recorridos completos del
   padrón, siete de los cuales se descartan antes de dibujarse.
   300 ms es el umbral en que una pausa al tipear deja de sentirse como lentitud
   y empieza a leerse como «está buscando».
   LA PETICIÓN VIEJA SE CANCELA, Y SIN ESO LA LISTA MIENTE
   Dos búsquedas en vuelo pueden volver en cualquier orden. Si la de «ant»
   tarda más que la de «antezana», llega después y PISA los resultados buenos:
   la pantalla termina mostrando coincidencias de un texto que ya no está en la
   caja. `AbortController` corta la anterior en cada tecleo.

## `resources/js/components/panel/dashboard/grafico-carnets-por-tipo.tsx`

### Cuántos carnets vigentes hay de cada tipo del catálogo.

   Se cuentan solo los VIGENTES y no todos los emitidos, porque la pregunta del
   tablero es «cuánta gente está habilitada hoy», y un carnet revocado o vencido
   no habilita a nadie.
   LAS BARRAS VAN HORIZONTALES (`layout="vertical"`) porque los nombres del
   catálogo son largos —«Carnet Comercializador»— y en barras verticales
   quedarían inclinados o cortados. Horizontal, el nombre entra entero sobre el
   eje.
   OJO: ResponsiveContainer mide a su padre, así que el padre necesita una
   altura concreta. Por eso el CardContent lleva `h-72`. Sin esa altura el
   gráfico se calcula con altura cero y no se ve nada.

## `resources/js/components/panel/dashboard/grafico-recaudacion-mensual.tsx`

### LA RECAUDACIÓN DE LOS ÚLTIMOS DOCE MESES

   Los gráficos los dibuja `recharts`, una librería de React. Se arma como si
   fuera HTML: cada pieza del gráfico es una etiqueta.
   <ResponsiveContainer>  se estira al tamaño del contenedor padre
   <AreaChart>          el gráfico y sus datos
   <CartesianGrid>    la cuadrícula de fondo
   <XAxis> <YAxis>    los ejes
   <Tooltip>          el cuadrito al pasar el mouse
   <Area>             la curva con el relleno debajo
   OJO: ResponsiveContainer mide a su padre, así que el padre necesita una
   altura concreta. Por eso el div que lo envuelve lleva `h-64`. Sin esa altura
   el gráfico se calcula con altura cero y no se ve nada.
   POR QUÉ ÁREA Y NO LÍNEA
   Lo que se pregunta de la recaudación no es «cuánto entró en abril» —para eso
   está el globito— sino «cómo viene el año». Eso es el BULTO bajo la curva, y
   una línea sola no lo dibuja: hay que reconstruirlo con la vista. El relleno
   lo muestra directamente.
   POR QUÉ EL PANEL VA PINTADO Y NO BLANCO
   Es la pieza más grande del tablero y la que resume el año. Sobre blanco, con
   el resto de las tarjetas también blancas, queda al mismo nivel que el listado
   de los últimos trámites. Pintado, hace de ancla: es lo que el ojo agarra
   después de los cuatro números de arriba.
   Reusa el tono 1 de los indicadores —no un azul escrito acá— porque ese par de
   tokens ya tiene medido el contraste del texto encima, en modo claro y en
   oscuro. Los colores de recharts NO son clases de Tailwind sino atributos SVG,
   así que van como `var(--widget-1-fg)` y no como `text-widget-1-fg`.

## `resources/js/components/panel/dashboard/mini-grafico.tsx`

### LAS LÍNEAS CHICAS DEL PIE DE LOS INDICADORES

   POR QUÉ ESTO NO USA RECHARTS, que ya está en el proyecto.
   Un dibujo de estos son catorce puntos unidos, sin ejes, sin cuadrícula, sin
   leyenda y sin globito al pasar el mouse. Recharts pesa más de 100 kB y trae
   todo eso; acá se usaría el 2%. Y no es un costo cualquiera: los cuatro
   indicadores son lo PRIMERO que se mira al entrar al sistema, la parte que el
   tablero carga aparte justamente para que aparezca de inmediato (ver el
   comentario de `lazy()` en pages/panel/dashboard.tsx). Meterles una librería
   pesada adentro desarma esa decisión.
   Un SVG a mano son treinta líneas y se dibuja en el primer cuadro.
   LOS DOS DETALLES QUE HACEN FALTA PARA QUE UN SVG ESTIRADO NO SE VEA MAL
   1. `preserveAspectRatio="none"` es lo que permite que el mismo dibujo llene
   una tarjeta angosta y una ancha. Sin eso el SVG conserva su proporción y
   deja aire a los costados.
   2. Estirar el dibujo estira TAMBIÉN el grosor del trazo, y de forma despareja
   —mucho a lo ancho, poco a lo alto—, así que la línea sale como una cuña.
   `vector-effect="non-scaling-stroke"` le dice al navegador que dibuje el
   trazo con el grosor pedido sin importar cuánto se haya estirado la caja.
   El color no se elige acá: lo pone quien lo usa, porque estos dibujos van
   sobre las tarjetas de color entero y tienen que salir del color del texto de
   ESA tarjeta. `currentColor` los hereda solo.

## `resources/js/components/panel/dashboard/panel-avisos.tsx`

### Lo que está por caducar y lo que ya caducó sin cerrarse.

   SON DOS COSAS DISTINTAS Y VAN SEPARADAS A PROPÓSITO
   Arriba, lo que VA A VENCER: carnets y cupos dentro del plazo de aviso. Es
   trabajo que se puede anticipar —avisarle a la gente que renueve— y no hay
   nada mal todavía.
   Abajo, lo que YA SE PASÓ DE FECHA y sigue abierto: una faena o una guía
   vencida sin cerrar es un papel que alguien se llevó y del que nadie registró
   la vuelta. Eso no es una previsión, es algo que hay que ir a buscar.
   Mezclados en una sola lista, lo segundo se pierde entre lo primero, que
   siempre es más numeroso.

## `resources/js/components/panel/dashboard/tabla-ultimos-carnets.tsx`

### `min-w-0` NO ES DECORACIÓN, y sin él el `overflow-x-auto` de abajo no

   Un elemento dentro de una grilla arranca con `min-width: auto`, que
   significa «no te encojas por debajo de tu contenido». El contenido acá
   es una tabla de seis columnas que mide unos 675 px, así que la tarjeta
   se estira a 675 px aunque la pantalla tenga 375: el que termina con
   barra de desplazamiento es el DOCUMENTO ENTERO, y en un celular se
   corre de costado la pantalla completa —menú, encabezado y todo— para
   leer una columna.
   `min-w-0` devuelve el permiso de encogerse. Recién entonces la
   tarjeta se queda en 375, la tabla desborda DENTRO suyo y el
   `overflow-x-auto` la hace desplazable sola, que era la intención.
   NO SE NOTA EN EL ESCRITORIO, que es donde se prueba: aparece solo al
   angostar la ventana.

## `resources/js/components/panel/dashboard/widget-estadistica.tsx`

### UNO DE LOS CUATRO NÚMEROS DE ARRIBA DEL TABLERO

   Es el mismo componente repetido cuatro veces con datos distintos. Ese es el
   sentido de un componente: se escribe el diseño UNA vez y se reutiliza
   cambiándole las props.
   VA PINTADO DE COLOR ENTERO y no como tarjeta blanca con un icono de color. La
   diferencia no es estética: estos cuatro son el resumen del día y compiten por
   la atención con dos gráficos, una tabla y un aviso de vencimiento. Siendo
   blancos, pesan lo mismo que todo lo demás y hay que buscarlos; pintados, el
   ojo cae ahí primero y recién después recorre el resto.
   El pie es una franja translúcida que se apoya en el borde de abajo, y ahí va
   el contexto: la línea de los últimos catorce días, o el desglose del número.
   Puede no haber ninguno —no todo número tiene una serie detrás— y entonces la
   tarjeta termina en la cifra.

### Las clases van ESCRITAS ENTERAS, no armadas con `bg-widget-${tono}`.

   Es la trampa clásica de Tailwind, la misma que está documentada en
   components/ui/badge.tsx: solo llegan a la hoja de estilos final las clases
   que Tailwind puede leer literalmente en el código. Armada juntando textos, la
   clase no existe, la tarjeta sale transparente y no hay ningún error que lo
   explique.
   Fondo y color de texto van SIEMPRE juntos: el dorado necesita texto oscuro y
   los tres azules texto claro (ver el comentario de los tokens en app.css), así
   que separarlos permitiría combinar un par ilegible.

### La franja de abajo cuando el número se puede PARTIR en pedazos.

   «12 trámites sin resolver» no dice si son doce recién llegados o doce trabados
   en revisión desde hace una semana, y esas dos situaciones piden cosas
   distintas. La barra parte el número en sus pedazos reales y los rotula.
   LOS PEDAZOS TIENEN QUE SER EXCLUYENTES entre sí: la barra los dibuja uno al
   lado del otro sumando el total, así que dos categorías que se pisan —un mismo
   expediente contado en las dos— dibujan una barra que miente sobre el total.

## `resources/js/components/panel/layout/barra-lateral.tsx`

### Barra lateral azul institucional: la marca arriba y el menú debajo.

   TRES ESTADOS, NO DOS
   En pantallas grandes está siempre visible y puede estar ANCHA —icono más
   texto— o ANGOSTA —solo los iconos—. En celular no existe ninguno de los dos:
   está fuera de la pantalla y entra deslizándose, y ahí siempre va ancha,
   porque un menú de iconos sueltos en un teléfono no se entiende.
   Por eso recibe `abierto` (celular) y `angosto` (escritorio) por separado, y
   los dos los guarda el layout: el botón que los cambia vive en el encabezado,
   que es otro componente.

### Pie de la barra: el botón que la angosta, y nada más.

   QUIÉN ESTÁ CONECTADO NO VA ACÁ, va en el encabezado. Es a propósito: angosta
   la barra mide 64 px y ahí no entra un nombre, así que el dato desaparecería
   justo en la configuración en la que el operador ya no ve los rótulos del menú
   y más necesita saber con qué sesión está trabajando. El encabezado, en
   cambio, mide siempre lo mismo.

## `resources/js/components/panel/layout/barra-superior.tsx`

### EL ENCABEZADO DEL PANEL, EN DOS FRANJAS

   Arriba la BARRA DE SESIÓN: el botón del menú, quién está conectado, el
   selector de tema y la salida. Es lo que no cambia nunca, esté donde esté el
   usuario.
   Abajo la BANDA DE PÁGINA: las migas de pan, el título, su línea de apoyo y
   los botones de la pantalla («Nuevo beneficiario», «Exportar»).
   ESTÁN SEPARADAS PORQUE SE MIRAN EN MOMENTOS DISTINTOS. La de sesión se mira
   una vez al entrar y después se ignora; la de página se lee en cada pantalla.
   Mezcladas en una sola fila, el título compite por el ojo con un botón de
   salir que nadie estaba buscando, y los botones de acción —que son lo que el
   operador vino a apretar— quedan a la misma altura que el cambio de tema.
   `sticky top-0` deja las dos pegadas arriba al desplazar la página, para que
   el título y los botones sigan a mano en los listados largos.

### Las migas de pan: «Inicio / Trámites / Nuevo trámite».

   El módulo del medio NO se escribe en cada pantalla: sale de la URL, con el
   mismo cálculo que usa la barra lateral para saber qué renglón resaltar (ver
   `moduloActual`). Escrito a mano, cada página tendría que acordarse de pasarlo
   y las trece se irían desincronizando de a una.
   El último tramo es el `titulo` que ya recibe el layout, así que tampoco hay
   un dato nuevo que mantener. Cuando el título ES el del módulo —el listado de
   trámites de carnet se llama «Trámites de carnet»— el tramo del medio se
   saltea, o la miga diría «Inicio / Trámites de carnet / Trámites de carnet».
   SE USA `tituloCompleto` Y NO `titulo`: en la barra lateral los ítems de un
   grupo van con el nombre corto —«De faena», porque el grupo ya dice
   «Trámites»— y acá esa palabra suelta no se entiende.

## `resources/js/components/panel/layout/navegacion.ts`

### El nombre ENTERO, para las migas de pan y para el globito de la barra

   Existe porque el renglón de la barra mide 256 px y dentro de un grupo el
   rótulo se escribe corto: bajo «CATÁLOGOS» alcanza con «Escala». Pero esa
   palabra sola no sirve fuera del grupo —una miga que dijera
   «Inicio / Escala / Editar» no se entiende— así que ahí va «Escala de
   aprovechamiento».
   Si se omite, se usa `titulo`, que es lo que pasa con los ítems sueltos.

### Encabezado de sección bajo el que se agrupa el ítem.

   Es puramente visual: la barra lateral dibuja el rótulo la primera vez que
   aparece un grupo nuevo. No se declara una lista de grupos aparte porque
   entonces habría DOS lugares que mantener y se podrían contradecir —un
   grupo declarado sin ítems, o un ítem apuntando a un grupo que ya no
   existe—. Acá el orden de esta lista es el orden de la pantalla.
   Sin `grupo` el ítem va suelto arriba de todo, antes del primer rótulo.

### EL MENÚ DEL PANEL DE ADMINISTRACIÓN.

   Está en su propio archivo (y no dentro de la barra lateral) porque es la
   lista que más se toca: cada módulo nuevo agrega una línea acá y nada más.
   DOS FILTROS SE APLICAN SOBRE ESTA LISTA:
   1. Por PERMISO. Si el usuario no tiene el permiso indicado, el ítem ni
   siquiera se dibuja. Los permisos salen del enum App\Enums\RolSistema
   y llegan a React dentro de auth.user.permisos (ver el middleware
   HandleInertiaRequests).
   2. Por RUTA EXISTENTE. Si la ruta todavía no está declarada en
   routes/panel.php, el ítem se muestra en gris y no se puede pinchar.
   Así el menú refleja el sistema completo desde el primer día sin que
   nada explote al hacer clic.
   IMPORTANTE: la seguridad real la aplica el middleware de Laravel en
   routes/panel.php. Esconder un botón en React es comodidad para el usuario,
   NO protección: cualquiera puede escribir la URL a mano. Los dos filtros
   tienen que existir.
   EL ORDEN ES EL DEL FLUJO DE TRABAJO, NO EL ABECEDARIO
   VENTANILLA está en el orden EXACTO en que ocurren las cosas en el mostrador,
   y esa es toda la idea del menú:
   1. Beneficiarios  la persona se registra UNA vez
   2. Cupos de pesca se le asigna la bolsa madre según la escala oficial
   3. Carnets        se emite la credencial (y recién acá se puede imprimir)
   4. Faenas         cada salida, que descuenta kilos del cupo
   5. Guías          cada traslado del comercializador
   Leído de arriba hacia abajo, el menú ES el procedimiento. Un operador nuevo
   no tiene que aprenderse el orden: lo tiene delante.
   LO QUE ESTE ORDEN NO MUESTRA, Y SE ACEPTÓ A PROPÓSITO
   Que los pasos 4 y 5 se BIFURCAN: las faenas son del pescador y las guías del
   comercializador, y nadie recorre los cinco renglones seguidos. Poner dos
   grupos —«Pescador» y «Comercializador»— lo mostraría, pero obligaría a
   repetir Carnets en los dos, porque la credencial es la misma tabla y la misma
   pantalla.
   Se eligió la lista única porque la bifurcación se explica sola apenas se
   entra: los dos formularios abren con un buscador de carnet que solo ofrece
   los que pueden emitir ESE papel —lo dice `tipo_actor`—, así que quien tiene
   carnet de pescador no encuentra a nadie en la lista de guías.
   POR QUÉ CAJA VA DESPUÉS Y NO INTERCALADA
   Porque el cobro NO es un paso del flujo: es algo que puede pasar en
   cualquiera de ellos y varias veces. Un carnet, un cupo o una guía se pagan en
   cuotas, y un mismo recibo puede cubrir dos trámites distintos. Metido como
   «paso 6» daría a entender que se cobra al final, que es justamente lo que el
   pago fraccionado contradice.
   Y POR QUÉ CATÁLOGOS VA AL FINAL SI EL FLUJO EMPIEZA POR AHÍ
   La escala de aprovechamiento es el paso 2 del diagrama —de ella sale el cupo
   y el precio— así que por dependencia debería ir primero. Va al final porque
   el menú se ordena por lo que se HACE, no por lo que se necesita: los tres
   catálogos se cargan una vez, cuando sale la resolución, y no se vuelven a
   abrir en meses. Arriba le robarían el primer lugar al trabajo diario.

### En qué módulo está parado el usuario, según la URL.

   POR QUÉ ESTÁ ACÁ Y NO ADENTRO DE LA BARRA LATERAL
   Lo necesitan DOS componentes: la barra lateral, para saber qué renglón
   resaltar, y las migas de pan, para escribir «Inicio / Carnets / ...». Si cada
   uno lo calculara por su cuenta, alcanzaría con que alguien tocara una de las
   dos copias para que el menú marque un módulo y las migas digan otro —y eso no
   rompe nada, así que nadie se entera hasta que lo nota un usuario—.
   Devuelve UNO SOLO, no una lista: con `find`, dos módulos no pueden quedar
   marcados a la vez. Se compara contra el prefijo del nombre de la ruta
   ('beneficiarios.index' -> 'beneficiarios') porque una ficha o un formulario
   —/panel/beneficiarios/7/editar— pertenecen al mismo módulo que el listado y
   tienen que resaltarlo igual.
   OJO CON LOS PREFIJOS QUE SE CONTIENEN ENTRE SÍ. Devuelve el PRIMERO que
   coincide, así que el orden de NAVEGACION decide los empates. Hoy no hay
   ninguno —'tipos-carnet' no contiene 'carnets', y 'categorias-aprovechamiento'
   no contiene 'aprovechamientos'— pero es lo que hay que revisar al agregar un
   módulo con nombre parecido a otro.

### El nombre de un ítem fuera de la barra lateral.

   Dentro de un grupo el rótulo va corto —«Escala», porque arriba ya dice
   «CATÁLOGOS»— y esa palabra sola no se entiende en una miga de pan ni en el
   globito de la barra angosta. Los ítems sueltos no declaran `tituloCompleto` y
   caen al `titulo`, que ya es el nombre entero.
   Vive acá y no en cada componente porque lo usan los dos, y escrito dos veces
   alcanzaría con tocar uno para que la miga y el globito dijeran cosas
   distintas.

## `resources/js/components/panel/pagos/dialogo-corregir-pago.tsx`

### Corregir un depósito: lo único que levanta una observación.

   Vive en la ficha y no en «editar», que en revisión no abre — y observar solo
   pasa en revisión. Guardar sin cambiar nada también sirve: vuelve a quedar sin
   validar y queda en la auditoría quién lo hizo.
   La boleta es OPCIONAL: sin elegir otra se conserva la que está.

## `resources/js/components/publico/buscador-codigo.tsx`

### Caja para escribir a mano el código del carnet.

   Lo normal es llegar acá escaneando el QR, que ya lo trae en la URL. Este
   formulario es el plan B: cuando el QR está borroso, mojado o el teléfono no
   tiene cámara.
   UN SOLO CAMPO, Y ES LA LLAVE ENTERA
   El carnet se identifica por su `codigo_carnet`, que es único global y va
   impreso en el plástico. Es lo único que hay que saber para consultarlo.
   Que esté impreso significa que no es un secreto, así que lo que protege del
   barrido automático NO es el código sino el límite de intentos por minuto de
   la ruta. Por eso conviene que el código lleve una parte al azar al generarse:
   uno correlativo se recorre entero probando de 1 en adelante.
   Al enviar hace POST a /verificar y el controlador redirige a /verificar/{codigo}.
   Podría haber sido un GET, pero con POST el dato no queda en el historial del
   navegador de una computadora compartida.

## `resources/js/components/publico/ficha-carnet.tsx`

### EL ACTA DE VERIFICACIÓN DE UN CARNET

   Lo que ve el inspector cuando escanea el QR. Va sobre la hoja blanca con
   membrete, por lo explicado en `hoja-oficial.tsx`: el ciudadano tiene el papel
   en la mano y compara, y si la pantalla se parece a otro papel oficial la
   comparación la hace cualquiera sin que le expliquen.
   EL SELLO ES LO PRIMERO Y LO MÁS GRANDE
   La escena es un muelle, con sol, y el inspector mira el teléfono dos segundos.
   Todo lo demás —nombre, gestión, actividad— es la letra chica que se lee si hace
   falta; lo que tiene que entenderse de un vistazo es si el carnet vale o no.
   Por eso el estado va arriba, en un sello grande y con color propio, y no como
   una etiqueta más en una lista de datos.
   LA ACTIVIDAD SOLO APARECE SI EL CARNET ESTÁ VIGENTE
   Un carnet vencido o revocado no habilita nada, así que mostrar su actividad
   —aunque fuera en gris— es pedirle al inspector que lea el sello y la línea al
   mismo tiempo y saque la conclusión correcta. Con un carnet caído, el servidor
   manda `actividad` en null y el bloque directamente no está.

### LA ACTIVIDAD QUE EL CARNET AUTORIZA. Es UNA, no una lista: cada

   Solo se muestra con el carnet VIGENTE, y el servidor ya manda
   `actividad` en null cuando no lo está. Es deliberado: enseñar la
   actividad de un carnet vencido o revocado —aunque fuera tachada—
   arriesga que el inspector lea la línea y no la advertencia.

### REVOCADO TIENE SELLO PROPIO, y no es un detalle estético.

   Sin este caso el sello caería en el genérico de abajo y un carnet
   revocado se anunciaría como VENCIDO. El inspector leería dos cosas
   distintas en la misma pantalla, y se resuelven distinto: un vencimiento
   se arregla emitiendo el carnet del año siguiente, una revocación es una
   decisión de la unidad.
   Va en rojo y no en ámbar porque, a diferencia del vencimiento, es una
   SANCIÓN vigente: el documento no caducó solo, alguien lo cortó.
   EL CASO `suspendido` SE FUE con el núcleo nuevo: `EstadoCarnet` pasó a
   activo/revocado/vencido, y una suspensión temporal ya no existe.

## `resources/js/components/publico/hoja-oficial.tsx`

### LA HOJA

   El papel sobre el que se escribe todo lo que ve el ciudadano: el membrete
   con el escudo, la guarda del borde y el pie. Adentro se le cambia el
   contenido —el acta de un documento, el aviso de código inexistente o el
   formulario para tipear el código—, pero el papel es siempre el mismo.
   POR QUÉ EL DISEÑO IMITA UN DOCUMENTO IMPRESO
   La escena real es esta: el inspector escanea el QR y le gira el teléfono al
   pescador, que tiene en la mano el papel original. Los dos miran las dos
   cosas a la vez.
   Si la pantalla se parece a una aplicación —tarjetas de colores, botones
   redondeados, fondo degradado—, las dos cosas no se pueden comparar: una es
   un papel y la otra es «internet». Si en cambio la pantalla es OTRO PAPEL,
   con el mismo escudo y el mismo membrete, la comparación es inmediata y
   cualquiera de los dos puede hacerla sin que le expliquen nada.
   Por eso: fondo blanco, tipografía con serifas para los títulos, guarda doble
   en el borde y texto justificado en el cuerpo. Nada de esto es decoración: es
   lo que hace que un documento parezca un documento.
   UNA ACLARACIÓN QUE NO SE PUEDE OMITIR
   Esta hoja se parece mucho a un documento oficial, y de hecho se puede
   imprimir. Por eso el pie dice, siempre y sin excepción, que la constancia NO
   reemplaza al documento: es la prueba de que se consultó el sistema, no el
   permiso en sí. Sin esa línea, alguien podría imprimir esta pantalla y
   presentarla como si fuera la credencial.

### ¿La hoja está dejando constancia de algo, o es solo el formulario para

   Cambia una sola línea del pie, pero importa: la aclaración de que esto
   «no reemplaza al documento» no tiene sentido en una pantalla donde
   todavía no se verificó nada, y una advertencia que aparece cuando no
   corresponde es una que después nadie lee cuando sí corresponde.

## `resources/js/components/publico/splash-verificacion.tsx`

### LA PANTALLA DE "VERIFICANDO..."

   Aparece un segundo y medio al abrir un código escaneado, y después se
   desvanece dejando ver el resultado.
   ¿PARA QUÉ, SI EL DATO YA VINO CON LA PÁGINA?
   No es para disimular una espera: cuando esto se muestra, la respuesta ya
   está. Es para que la verificación se SIENTA como un acto, y no como abrir
   una página web cualquiera.
   El caso de uso real: el inspector escanea el QR delante del pescador y le
   gira el teléfono para mostrárselo. Que aparezca el escudo con una línea de
   escaneo y la palabra «Verificando» hace que los dos entiendan que el sistema
   consultó algo. Si el resultado apareciera de golpe, parecería una imagen
   guardada en el teléfono —justo lo que un documento falsificado querría
   simular—.
   SE RESPETA `prefers-reduced-motion`: quien pidió menos animaciones en su
   dispositivo va directo al resultado, sin splash y sin transición.

## `resources/js/components/ui/badge.tsx`

### Los colores llegan desde los enums de PHP (EstadoTramite::color(),

   Es la trampa clásica de Tailwind: un `bg-${color}-100` armado juntando textos
   nunca llega a la hoja de estilos, porque Tailwind solo incluye las clases que
   puede leer literalmente en el código. El badge saldría sin fondo y sin ningún
   error que lo explique.
   Si se agrega un color a un enum de PHP, hay que agregarlo también acá.

## `resources/js/components/ui/button.tsx`

### LA ESCALA DE LOS BOTONES DE TODO EL SISTEMA

   Cambiar estas clases cambia TODOS los botones, porque no hay ningún botón con
   tamaño propio: las pantallas usan `<Button>` o, cuando el botón es un enlace,
   `buttonVariants()` sobre un `<a>`.
   POR QUÉ SON CHICOS
   Esto es un panel de trabajo, no una página de inicio: la ficha de un trámite
   llega a mostrar seis acciones en la misma barra —corregir, aprobar, rechazar,
   recibo, imprimir, entregar—. Con botones altos esa fila se parte en dos
   renglones y empuja el contenido hacia abajo.
   32 px de alto (`h-8`) es la medida cómoda para un sistema que se usa con mouse
   todo el día. NO bajar de ahí: más chico empieza a costar acertarle, y el
   operador de ventanilla hace esto cientos de veces por jornada.

## `resources/js/components/ui/campo.tsx`

### Envoltorio de un campo de formulario: etiqueta + control + error + ayuda.

   ¿PARA QUÉ SIRVE?
   Sin esto, cada campo de cada formulario del sistema hay que escribirlo así:
   <div className="space-y-2">
   <Label htmlFor="telefono">Teléfono</Label>
   <Input id="telefono" value={...} onChange={...} aria-invalid={...} />
   {errors.telefono && <p className="text-sm text-destructive">{errors.telefono}</p>}
   </div>
   Son cinco líneas repetidas veinte veces por formulario, y basta olvidarse
   una para que un error de validación no se muestre y el usuario no entienda
   por qué no puede guardar. Con <Campo> queda:
   <Campo etiqueta="Teléfono" htmlFor="telefono" error={errors.telefono}>
   <Input id="telefono" ... />
   </Campo>
   `children` es el hueco donde va el control (Input, Select, Textarea...).
   Es una idea central de React: un componente puede recibir otros componentes
   como contenido, igual que una etiqueta HTML envuelve a otras.

## `resources/js/components/ui/confirmar-accion.tsx`

### Ventana de confirmación para acciones que no se pueden deshacer:

   POR QUÉ NO SE USA window.confirm()
   El confirm() del navegador congela toda la pestaña, no se puede estilar, y
   en varios navegadores móviles ni siquiera aparece. Para una acción que borra
   datos de un sistema departamental conviene algo que se vea claramente y que diga
   exactamente qué se va a hacer.
   CÓMO SE USA
   const [confirmar, setConfirmar] = useState(false);
   <Button onClick={() => setConfirmar(true)}>Dar de baja</Button>
   <ConfirmarAccion
   abierto={confirmar}
   titulo="¿Dar de baja al beneficiario?"
   descripcion="Dejará de aparecer en los listados."
   onCancelar={() => setConfirmar(false)}
   onConfirmar={() => router.delete(route('beneficiarios.destroy', id))}
   />

### LA CASILLA DE CONSENTIMIENTO

   Una frase que hay que MARCAR antes de poder confirmar. El botón queda apagado
   hasta entonces.
   PARA QUÉ SIRVE SI YA HAY QUE APRETAR «CONFIRMAR»
   Porque un botón se aprieta de memoria. Después de la décima vez, «¿está
   seguro?» ya no se lee: la mano va sola al mismo lugar de la pantalla. La
   casilla rompe eso porque está en OTRO lado y exige un acto distinto.
   Y sobre todo, DICE QUÉ SE ESTÁ AFIRMANDO. En validar un depósito no es una
   traba: es la declaración misma —«comparé la boleta con el extracto»— y esa
   frase es lo que después respalda la firma de quien revisó.
   Se usa solo donde hace falta: lo irreversible y lo que es una declaración. En
   todo lo demás estorba, y una casilla que se marca sin leer no protege nada.
   Se limpia al cerrar la ventana, o la segunda vez aparecería ya marcada y no
   serviría para nada.

## `resources/js/components/ui/confirmar-con-motivo.tsx`

### VENTANA DE CONFIRMACIÓN QUE EXIGE ESCRIBIR UN MOTIVO

   Para las decisiones FINALES que hay que poder explicar después: rechazar un
   trámite, eliminarlo. En las dos, alguien va a volver a ventanilla a preguntar
   por qué, y sin el texto guardado nadie puede responderle.
   POR QUÉ UNA VENTANA Y NO UN RECUADRO MÁS EN LA PANTALLA
   Porque tapa todo lo demás y obliga a detenerse. Un formulario entre los otros
   de la página se completa de pasada; estas dos operaciones no se deshacen.
   Es hermana de `ConfirmarAccion`, que es la versión sin motivo —para lo que
   solo hay que confirmar—. Se mantienen separadas porque la de acá tiene un
   campo obligatorio, un mínimo y un contador, y meter todo eso en la otra la
   volvería un componente con dos modos.
   NO SE CIERRA AL HACER CLIC EN EL FONDO
   A diferencia de otras ventanas: acá hay texto escrito a mano y un clic al
   costado lo perdería entero. Se sale con «Cancelar» o con Escape, las dos
   deliberadas.

### LA CASILLA DE CONSENTIMIENTO

   Una frase que hay que MARCAR antes de poder confirmar. El botón queda apagado
   hasta entonces.
   PARA QUÉ SIRVE SI YA HAY QUE APRETAR «CONFIRMAR»
   Porque un botón se aprieta de memoria. Después de la décima vez, «¿está
   seguro?» ya no se lee: la mano va sola al mismo lugar de la pantalla. La
   casilla rompe eso porque está en OTRO lado y exige un acto distinto.
   Y sobre todo, DICE QUÉ SE ESTÁ AFIRMANDO. En validar un depósito no es una
   traba: es la declaración misma —«comparé la boleta con el extracto»— y esa
   frase es lo que después respalda la firma de quien revisó.
   Se usa solo donde hace falta: lo irreversible y lo que es una declaración. En
   todo lo demás estorba, y una casilla que se marca sin leer no protege nada.
   Se limpia al cerrar la ventana, o la segunda vez aparecería ya marcada y no
   serviría para nada.

## `resources/js/components/ui/paginacion.tsx`

### Barra de paginación para cualquier listado del panel.

   DE DÓNDE SALEN ESTOS DATOS
   En el controlador se escribió `->paginate(15)`. Laravel no devuelve un array
   pelado, sino un objeto con las filas MÁS la información de navegación:
   {
   data:         [ ...las 15 filas de esta página... ],
   current_page: 2,
   last_page:    4,
   from: 16, to: 30, total: 48,
   links: [ { url, label, active }, ... ]   <- los botones ya calculados
   }
   Ese objeto llega tal cual a React. Acá solo hay que pintarlo.
   POR QUÉ <Link> Y NO <a>
   <Link> es de Inertia. Hace la petición por detrás y reemplaza solo el
   contenido de la página, sin recargar el navegador: no parpadea, no se
   vuelven a descargar los estilos ni el JavaScript. Un <a> normal recargaría
   todo. Regla simple: dentro del sistema, siempre <Link>.

## `resources/js/components/ui/selector-archivo.tsx`

### EL CAMPO PARA ADJUNTAR UN ARCHIVO

   Reemplaza al `<input type="file">` pelado, que en Chrome se dibuja como un
   botón gris con la leyenda «Sin archivos seleccionados» al lado.
   POR QUÉ HACÍA FALTA
   El control del navegador no sirve para este formulario por dos motivos:
   1. NO SE VE SI QUEDÓ CARGADO. El nombre del archivo sale en gris chico, al
   lado del botón, con el mismo peso visual que tenía el «Sin archivos
   seleccionados». El operador carga tres adjuntos seguidos y no puede decir
   de un vistazo cuál le falta — y si manda el formulario incompleto, se
   entera después de que el servidor lo rechace.
   2. NO SE PUEDE QUITAR LO ELEGIDO. Una vez seleccionado un archivo, el
   control no ofrece forma de volver atrás sin recargar la pantalla.
   Acá, apenas se elige, el recuadro pasa a VERDE con una tilde, el nombre del
   archivo y cuánto pesa. Si es una imagen, además muestra la miniatura: es la
   única forma de darse cuenta en el momento de que se adjuntó el escaneo
   equivocado.
   VALIDA ANTES DE ACEPTAR
   Tipo y peso, con las mismas reglas que el servidor —salen de
   `useArchivos()`, que las recibe de config/jichi.php—. Un archivo que no pasa
   no se guarda en el formulario: se muestra el motivo y el campo queda vacío,
   para que no se pueda enviar algo que va a rebotar.
   Eso NO reemplaza la validación del servidor, que sigue siendo la que manda.
   Ver `RegistrarSolicitudRequest` y `StorageController::verificarPeso()`.

## `resources/js/hooks/use-archivos.ts`

### EL LÍMITE DE LOS ARCHIVOS, UNO SOLO PARA TODO EL SISTEMA

   ¿POR QUÉ VALIDAR EL PESO EN EL NAVEGADOR SI EL SERVIDOR YA LO VALIDA?
   Por lo mismo que se esconde un botón por permiso: no es seguridad, es no
   hacerle perder el tiempo al operador. Sin esto, adjuntar un escaneo de 8 MB
   significa esperar a que suba entero por la conexión de la Gobernación —que no
   es rápida— para que recién ahí el servidor lo rechace. Con el control acá, el
   aviso es instantáneo y no se sube ni un byte.
   La regla de verdad sigue estando en el servidor. Ver
   `RegistrarSolicitudRequest` y `GuardarBeneficiarioRequest`.
   EL NÚMERO NO ESTÁ ESCRITO ACÁ
   Sale de `config/jichi.php` y llega por Inertia, igual que el símbolo de la
   moneda. Escrito a mano en este archivo, el día que el límite cambie en el
   servidor esta pantalla seguiría diciendo el número viejo: el operador leería
   «hasta 4 MB» y el sistema le rechazaría un archivo de 3,5 MB sin que nadie
   entienda por qué.

## `resources/js/hooks/use-permisos.ts`

### Saber qué puede hacer el usuario conectado.

   Los permisos los calcula spatie/laravel-permission en PHP y los manda en
   cada página el middleware HandleInertiaRequests, dentro de
   auth.user.permisos. Este hook solo los envuelve para no repetir
   `usePage().props.auth.user?.permisos?.includes(...)` en cada archivo.
   CÓMO SE USA
   const { puede } = usePermisos();
   {puede('beneficiarios.crear') && <Button>Nuevo beneficiario</Button>}
   ADVERTENCIA IMPORTANTE
   Esto sirve para NO MOSTRAR botones que el usuario no puede usar. No es
   seguridad: el navegador es del usuario y cualquiera puede escribir la URL a
   mano o modificar el JavaScript. Quien realmente bloquea el acceso es el
   middleware 'permiso:...' declarado en routes/panel.php. Los dos tienen que
   estar: el de React por comodidad, el de Laravel por seguridad.

## `resources/js/layouts/layout-panel.tsx`

### EL MARCO DE TODAS LAS PANTALLAS DEL PANEL

   Un "layout" es el envoltorio que se repite en cada página: la barra lateral,
   el encabezado y el aviso de mensajes. Cada pantalla se escribe pensando solo
   en su contenido y se mete acá adentro:
   export default function Beneficiarios() {
   return (
   <LayoutPanel titulo="Beneficiarios">
   ...aquí va SOLO el contenido de la pantalla...
   </LayoutPanel>
   );
   }
   `children` es justamente ese contenido. React lo pasa automáticamente:
   es todo lo que está escrito entre <LayoutPanel> y </LayoutPanel>.
   Este archivo quedó a propósito muy corto. Las tres partes que lo componen
   viven en components/panel/layout/ y se pueden leer una por una:
   barra-lateral.tsx   el menú azul de la izquierda
   barra-superior.tsx  el encabezado: sesión arriba, título y migas abajo
   navegacion.ts       la lista de módulos del menú

### useState guarda un dato que, al cambiar, hace que React vuelva a pintar.

   menuAbierto  -> en CELULAR: si la barra está desplegada encima
   menuAngosto  -> en ESCRITORIO: si la barra quedó reducida a iconos
   Son dos y no uno porque responden a cosas distintas: el primero se apaga
   solo al navegar, el segundo es una preferencia que tiene que sobrevivir a
   la recarga.
   Viven en el layout, y no dentro de la barra lateral, porque DOS
   componentes distintos los necesitan: la barra (para dibujarse) y el
   encabezado (para los botones que los cambian). Cuando dos componentes
   comparten un dato, este sube al padre común. En React eso se llama
   "levantar el estado".

## `resources/js/layouts/layout-publico.tsx`

### EL MARCO DE LAS PANTALLAS PÚBLICAS

   Hay DOS layouts en el sistema y no se mezclan nunca:
   layout-panel.tsx    -> el panel de administración. Barra lateral con el
   menú, avisos flotantes, datos del funcionario.
   Sirve a quien tiene sesión iniciada.
   layout-publico.tsx  -> ESTE. Lo ve cualquier ciudadano desde la calle, sin
   login. Sin menú, sin nombres de usuario, sin nada
   interno: solo la marca de la institución.
   Por qué separarlos: la pantalla pública se abre escaneando un QR desde un
   teléfono, muchas veces con mala señal. Tiene que cargar poco y no mostrar ni
   una pista de la estructura interna del sistema.
   QUÉ HACE ESTE MARCO Y QUÉ NO
   Es el ESCRITORIO sobre el que se apoya la hoja, y nada más: el fondo verde
   oscuro, la franja tricolor arriba y abajo, y la marca de agua del escudo.
   El membrete —escudo, nombre de la institución, pie legal— NO está acá: va
   dentro de la hoja, en `components/publico/hoja-oficial.tsx`. Es a propósito.
   Un membrete flotando sobre el fondo pertenece a una página web; impreso
   sobre el papel pertenece a un documento, que es justo lo que esta pantalla
   tiene que parecer.
   El fondo es oscuro porque su único trabajo es hacer resaltar el papel
   blanco. Al imprimir desaparece (ver los estilos `@media print` de app.css,
   que enganchan con la clase `fondo-escritorio`).
   POR QUÉ ESTE MARCO NO SIGUE EL TEMA CLARO/OSCURO
   El resto del sistema cambia con el tema del dispositivo. Acá no, y es a
   propósito: esta pantalla es un ACTO DE VERIFICACIÓN OFICIAL. El inspector la
   abre delante del pescador y los dos miran el teléfono. Que se vea siempre
   igual —hoja blanca, escudo, franja tricolor— es parte de lo que la hace
   creíble. Una pantalla que a veces es blanca y a veces negra parece una
   página cualquiera, no el respaldo de la Gobernación.

## `resources/js/lib/utils.ts`

### UNA FECHA SUELTA NO ES UN INSTANTE, Y CONFUNDIRLOS RESTA UN DÍA

   `new Date('2026-12-31')` NO da el 31 de diciembre en Bolivia. El estándar
   manda interpretar una cadena `AAAA-MM-DD` como medianoche UTC, y Bolivia está
   en UTC-4: esa medianoche es todavía el 30 de diciembre a las 20:00 hora local.
   Al formatear en horario local, la pantalla muestra **30/12/2026**.
   No es un detalle cosmético. Con esa resta:
   - el vencimiento de TODOS los carnets se mostraba un día antes,
   - una fecha de nacimiento del 1 de enero saltaba al año anterior,
   - la edad calculada se equivocaba el día del cumpleaños.
   La distinción que hay que hacer es entre dos cosas distintas:
   FECHA SUELTA      '2026-12-31'                 -> un día del calendario.
   No tiene hora ni zona: el
   31 de diciembre es el 31
   en todo el mundo.
   INSTANTE          '2026-12-31T14:30:00-04:00'  -> un momento exacto. Sí
   tiene zona, y convertirlo
   a hora local es correcto.
   Por eso la cadena de solo fecha se arma con `new Date(año, mes, día)`, que
   construye el día en horario LOCAL y no se corre. Lo que trae hora se deja
   pasar tal cual, porque ahí la conversión sí corresponde.

### La misma fecha, pero como la quiere un `<input type="date">`: AAAA-MM-DD.

   Es la inversa de `fecha()` y hace falta cada vez que un formulario abre con
   un valor que ya existe —corregir un depósito, por ejemplo—.
   NO ES `valor.slice(0, 10)`, y por eso está acá. Lo que manda el servidor es
   un INSTANTE en UTC (`2026-09-16T02:00:00+00:00`); cortarle los diez primeros
   caracteres devuelve el día en UTC, que en Bolivia —UTC-4— puede ser el
   SIGUIENTE al que la pantalla venía mostrando. El operador abriría el
   formulario y vería una fecha distinta de la que dice la fila de al lado, sin
   haber tocado nada.
   `en-CA` se usa por su formato, no por el idioma: es el que da AAAA-MM-DD. El
   cálculo del día se hace en horario local, igual que `fecha()`.

### Años CUMPLIDOS a partir de una fecha de nacimiento.

   Vive acá y no dentro de un componente porque la usa el formulario para la
   vista previa y podría usarla cualquier otra pantalla. OJO: es un ESPEJO de
   `Beneficiario::edad()` en PHP, y existe solo para poder mostrar la edad
   mientras el operador escribe, sin una petición al servidor por cada tecla. El
   valor que se guarda y el que se imprime salen siempre de PHP.
   No se hace una resta de años a secas —`hoy.getFullYear() - nacio.getFullYear()`—
   porque eso le da un año de más a todo el que todavía no cumplió: en junio,
   quien nació en diciembre figuraría con la edad que va a tener recién a fin de
   año.

### CUÁNTO HACE — «hace 3 minutos», «ayer», «hace 2 meses»

   Acompaña a la fecha exacta, nunca la reemplaza. Las dos contestan preguntas
   distintas y las dos hacen falta: la fecha sirve para buscar el papel en el
   archivo, y el «hace tanto» para saber de un vistazo si esto entró recién o
   está esperando desde la semana pasada.
   SE USA Intl.RelativeTimeFormat Y NO UNA CADENA ARMADA A MANO
   Porque el castellano no es «hace N <unidad>» a secas: es «hace 1 minuto» y
   «hace 2 minutos», y con `numeric: 'auto'` un día atrás sale «ayer» en vez de
   «hace 1 día», que es como se dice. Armarlo con `if` es reescribir mal lo que
   el navegador ya sabe.
   POR QUÉ NO SE ACTUALIZA SOLO
   Se calcula al pintar y se queda quieto: un trámite cargado hace tres minutos
   va a seguir diciendo «hace 3 minutos» hasta que se recargue la pantalla. Un
   temporizador por fila obligaría a volver a pintar la tabla entera cada minuto
   para un dato que nadie mira fijo. Al navegar o filtrar se recalcula solo.
   El RELOJ DEL NAVEGADOR puede estar adelantado respecto del servidor, y ahí un
   instante recién guardado da negativo. Por eso todo lo que caiga dentro del
   minuto —incluido el futuro— se muestra como «recién».

### DOS FORMATOS, Y NO ES CAPRICHO.

   `numeric: 'auto'` reemplaza el número por la palabra cuando existe, y
   para lo cercano eso es exactamente como se habla: un día atrás sale
   «ayer» y no «hace 1 día».
   De meses para arriba se da vuelta y ESTORBA: cuarenta días atrás salía
   «el mes pasado», que en un calendario puede ser dos meses atrás, y
   cuatrocientos días salían como «el año pasado». Ahí se prefiere el
   número: «hace 1 mes», «hace 1 año».

## `resources/js/pages/panel/aprovechamientos/crear.tsx`

### OTORGAR UNA BOLSA MADRE — paso 2 del flujo del pescador

   Son tres datos: a quién, qué tramo de la escala y desde cuándo. De ahí salen
   solos el volumen en kilos y lo que hay que cobrar.
   EL VOLUMEN NO SE ESCRIBE: SALE DEL TRAMO
   La escala dice «201 kg Hasta 500 Kg», y lo que se autoriza es el TECHO. No
   hay campo de kilos porque dejarlo elegir convertiría la escala en una
   sugerencia: se podría cobrar el tramo 3 otorgando el volumen del 5, y nada lo
   marcaría como raro.
   Lo que sí hace la pantalla es MOSTRAR las dos consecuencias —cuántos kilos y
   cuánto se cobra— apenas se elige el tramo, para que se vean antes de guardar
   y no después.
   LO QUE ESTA PANTALLA NO COMPRUEBA
   Que la persona no tenga ya un cupo vigente. Esa regla necesita la fila del
   beneficiario bloqueada dentro de una transacción —dos ventanillas
   simultáneas la pasarían las dos— así que vive en OtorgarCupoService y su
   mensaje vuelve como error del campo de la persona.

### EL RENGLÓN QUE FALTABA DEL TALONARIO.

   Va con sugerencias en un `datalist` y no en un
   desplegable cerrado: en el Beni se escribe «canoa»,
   «peque-peque», «bote», «chalana» y variantes, y un
   catálogo obligaría a dar de alta un tipo nuevo con el
   pescador esperando enfrente.

## `resources/js/pages/panel/aprovechamientos/editar.tsx`

### CORREGIR UN APROVECHAMIENTO QUE TODAVÍA ES BORRADOR

   Solo se llega acá con el cupo PENDIENTE DE PAGO y sin ningún abono encima. El
   servidor lo comprueba dos veces —al abrir la pantalla y al guardar, esta
   última con la fila bloqueada— porque entre una cosa y la otra otra ventanilla
   puede cobrarlo.
   EL TITULAR NO SE CAMBIA, Y NO ES UN OLVIDO
   Corregir es arreglar una carga equivocada; mover la autorización de una
   persona a otra es otra cosa, y dejarlo hacer desde acá la volvería invisible:
   la fila quedaría igual, con otro nombre, sin nada que lo delate. Si el cupo se
   cargó a quien no era, se elimina —con el motivo escrito— y se otorga de nuevo.
   Por eso la persona se muestra fija arriba, no en el buscador.
   ES EL MISMO FORMULARIO QUE EL ALTA MENOS ESE CAMPO
   Se mantiene como pantalla aparte —y no como un modo de `crear.tsx`— porque lo
   que cambia no es un campo sino el significado: el alta elige a quién y esta
   no, el alta manda a la caja y esta vuelve a la ficha. Un solo componente con
   dos modos tendría un `if` en cada una de esas decisiones.

## `resources/js/pages/panel/aprovechamientos/index.tsx`

### LISTADO DE CUPOS DE PESCA

   LA COLUMNA QUE IMPORTA ES EL SALDO, NO EL VOLUMEN OTORGADO
   «Tiene 500 kg» no dice si esa persona puede salir a pescar mañana. «Le quedan
   20» sí. Por eso la barra de consumo va en la fila y no escondida en la ficha:
   quien mira este listado está buscando a quién le queda poco.

### LOS TRES BOTONES VAN CON COLOR, y cada uno con

   Tres iconos del mismo gris obligan a leer el
   dibujo antes de cada clic, y con el rojo al lado
   del ámbar eso se paga caro.
   El fondo va tenue y el color fuerte en el icono:
   tres botones sólidos en cada fila competirían
   con el dato de la tabla, que es lo que el
   operador vino a mirar.
   EDITAR Y ELIMINAR SOLO APARECEN SOBRE UN
   BORRADOR, y las dos banderas llegan resueltas
   del servidor.
   Ninguna es «el estado es pendiente»:
   `puede_editarse` es eso Y que no haya entrado
   plata; `puede_eliminarse` suma además que no
   tenga faenas. Van junto con el permiso, porque
   esconder un botón es comodidad, no seguridad —
   la ruta lo exige igual, y el servicio lo vuelve
   a comprobar con la fila bloqueada.

### LA MISMA VENTANA QUE EN LA FICHA: motivo obligatorio Y casilla de

   El motivo es lo único que sobrevive —la fila se da de baja y solo
   queda la línea de auditoría— y la casilla frena el clic
   automático: escribir un motivo es una tarea, marcar «entiendo que
   esto no se deshace» es una decisión.
   Se dibuja UNA sola vez fuera de la tabla, no una por fila: con
   cincuenta cupos habría cincuenta ventanas ocultas en el árbol.

### EL AVISO DE MODO FLEXIBLE.

   Solo aparece con APROVECHAMIENTO_ESTRICTO=false, y tiene que aparecer: con la
   validación apagada el sistema deja emitir faenas por encima del volumen
   otorgado, así que un saldo en cero deja de ser un freno. Quien mira este
   listado buscando a quién le queda poco necesita saber que nadie va a ser
   frenado por eso.

## `resources/js/pages/panel/aprovechamientos/ver.tsx`

### LA FICHA DE UN CUPO

   Arriba el saldo, abajo las faenas que lo explican. Ese orden es el punto:
   «le quedan 20 kg» es un número que hay que creer hasta que se ve de dónde
   sale.
   LAS FAENAS VENCIDAS SE MARCAN APARTE
   Una faena vencida LIBERA su volumen: la salida no ocurrió. Sin marcarlas, la
   suma de la lista no cuadra con el saldo de arriba y parece un error del
   sistema. Por eso van tachadas y con la aclaración al lado.

### EL FORMULARIO ES UNA LISTA DE SECCIONES, NO UN PAGO

   Cada clic en «Agregar pago» suma una sección, y cada una es un depósito
   completo: monto, número de boleta, fecha y archivo. Se mandan todas
   juntas y quedan cargadas SIN recibo: el papel es uno para todo el trámite
   y sale al enviarlo a revisión, con el total de los depósitos.
   Por qué una lista y no un pago por vez: la persona llega al mostrador con
   las dos boletas en la mano. Cargarlas de a una obligaba a guardar, esperar
   la recarga y volver a abrir el formulario.

### NO HAY BOTÓN «COBRAR» ACÁ, y es deliberado: los depósitos

   Caja sigue existiendo para lo suyo: cobrar varios trámites
   de una persona en un mismo recibo.

### SE COBRA DESDE ACÁ Y NO SOLO DESDE CAJA, porque el

   Es el mismo cobro —sale con su recibo numerado y entra
   al arqueo—, así que pide el permiso de caja.

### ELIMINAR PIDE MOTIVO **Y** CASILLA DE CONSENTIMIENTO

   Las dos cosas, y cada una tapa algo distinto:
   - EL MOTIVO es lo único que sobrevive. La fila se borra de
   verdad, así que dentro de seis meses la única respuesta
   posible a «¿y el cupo de Fulano?» es la línea de auditoría.
   Sin texto ahí, esa respuesta es «alguien lo borró».
   - LA CASILLA frena el clic automático. Escribir un motivo es
   una tarea; marcar «entiendo que esto no se deshace» es una
   decisión, y son dos actos distintos a propósito.
   El mínimo de 10 caracteres es el mismo que exige
   EliminarCupoRequest: si acá fuera menor, el botón se habilitaría
   y el servidor rechazaría igual.

## `resources/js/pages/panel/beneficiarios/crear.tsx`

### EL ANCHO ES EL MISMO EN TODOS LOS FORMULARIOS DEL PANEL: max-w-7xl.

   El formulario es de DOS columnas —campos a la izquierda, vista
   previa fija a la derecha— y cuanto más angosto, peor: con 4xl la
   columna de la derecha quedaba tan apretada que el nombre completo
   se partía en tres renglones.
   7xl y no «sin tope»: en un monitor muy ancho, un formulario sin
   límite estira los renglones hasta que leerlos obliga a barrer la
   cabeza de lado a lado.

## `resources/js/pages/panel/beneficiarios/editar.tsx`

### Edición de un beneficiario.

   Usa el MISMO componente de formulario que el alta. La diferencia la marca
   `beneficiario`: cuando llega con id, el formulario manda a la ruta de
   actualización y agrega el campo oculto `_method=put`.
   ¿POR QUÉ EL CAMPO OCULTO Y NO router.put()? Porque el formulario lleva una
   foto, y los formularios HTML solo saben mandar archivos por POST. Laravel
   interpreta `_method` y trata la petición como PUT.

## `resources/js/pages/panel/beneficiarios/index.tsx`

### LA BARRA DE ARRIBA DE LA TABLA: «Mostrar N» a la izquierda y

   Las dos cosas van al SERVIDOR, no se resuelven en el
   navegador. Es la diferencia que importa: filtrar en React
   exigiría traer el padrón entero en cada carga, y con miles de
   personas eso es un JSON enorme por pantalla. Acá cada cambio
   dispara una petición y Laravel devuelve solo la página pedida
   (ver Beneficiario::scopeBuscar y App\Support\Paginacion).

## `resources/js/pages/panel/beneficiarios/ver.tsx`

### La ficha del beneficiario.

   LA FICHA MUESTRA EL FLUJO DE ESTA PERSONA, EN ORDEN
   Arriba lo que debe, después sus CREDENCIALES —paso 3— y abajo sus CUPOS DE
   PESCA —paso 2—. Está al revés del flujo a propósito: el carnet es por lo que
   pregunta la gente en el mostrador, y el cupo es el detalle que se mira
   después, cuando alguien viene a sacar una faena.
   CADA FILA ENLAZA A SU FICHA, Y ESO TIENE UNA CONDICIÓN
   `route()` de Ziggy REVIENTA si se le pide una ruta que no está declarada: no
   devuelve null ni una cadena vacía, tira una excepción y la pantalla entera
   queda en blanco. Así que un enlace a un módulo que todavía no existe no es
   «un enlace roto», es esta pantalla caída.
   Carnets y Aprovechamientos ya están, así que enlazan. Al agregar el enlace a
   Faenas o a Guías hay que declarar su ruta PRIMERO.

### QUÉ PUEDE HACER ESTA PERSONA HOY.

   EL CARTEL MIRA LA VIGENCIA, NO LA CANTIDAD
   Tener carnets no es lo mismo que estar habilitado: uno vencido o revocado
   sigue en la lista de abajo —el historial no se borra— pero no autoriza nada.
   Por eso el cartel cuenta solo los `vigente`, que llegan ya resueltos del
   servidor contra el estado Y la fecha.
   Y nombra la ACTIVIDAD y no el tipo de carnet, porque es lo que decide qué
   puede emitir: un pescador saca faenas, un comercializador saca guías. Quien
   hace las dos cosas tiene dos carnets, y acá se ven los dos.

## `resources/js/pages/panel/caja/cobrar.tsx`

### COBRAR — el formulario de caja

   UN RECIBO PUEDE CUBRIR VARIOS TRÁMITES, Y POR ESO SON CASILLAS
   La persona llega con lo que debe —el carnet, el cupo, una guía— y entrega la
   plata UNA vez. Un formulario que cobrara de a un trámite obligaría a emitir
   tres papeles por una sola entrega, y a la persona a guardar tres.
   Por eso las deudas vienen juntas en una lista con casillas: se tilda lo que
   se cobra y sale un solo comprobante numerado.
   CADA LÍNEA ARRANCA CON EL SALDO COMPLETO, PERO ES EDITABLE
   Lo normal es pagar todo, así que ese es el valor por defecto. Bajarlo es lo
   que hace un pago en cuotas: 40 hoy y 40 la semana que viene, cada uno con su
   recibo.
   Lo que NO se puede es subirlo por encima del saldo. El servidor lo rechaza, y
   la pantalla lo avisa antes: pagar de más no genera saldo a favor —el saldo se
   corta en cero— así que el excedente se perdería.

## `resources/js/pages/panel/caja/index.tsx`

### CAJA — el listado de ABONOS

   ESTA PANTALLA MUESTRA ABONOS, NO RECIBOS, Y NO ES LO MISMO
   Un recibo agrupa varios abonos, así que las dos listas nunca tienen la misma
   cantidad de filas. Acá se mira el DINERO —cada depósito, con su boleta— que es
   lo que se cuadra contra el cajón. En «Recibos» se miran los PAPELES, con su
   correlativo, que es lo que audita Contabilidad.
   EL ARQUEO ES SIEMPRE DE HOY, AUNQUE SE ESTÉ FILTRANDO OTRO MES
   Es deliberado. Lo que se cuadra contra el extracto del banco antes de cerrar
   es lo de hoy, y esa pregunta no cambia porque alguien esté mirando marzo. Un
   total que siguiera al filtro invitaría a cuadrar la caja contra el número
   equivocado.

### LAS DOS FECHAS NO SON LA MISMA PREGUNTA, y por eso

   - CARGADO HOY  cuadra el trabajo del día.
   - DEPOSITADO   se cruza contra el extracto.
   Un depósito del viernes registrado el lunes entra
   en el primero y no en el segundo. Antes acá iba el
   reparto por método de pago, que desapareció: todo
   pago es un depósito bancario.

## `resources/js/pages/panel/carnets/crear.tsx`

### EMITIR UNA CREDENCIAL — paso 3 del flujo

   LA ACTIVIDAD Y EL TIPO DE CARNET SON DOS COSAS DISTINTAS
   Es el punto que más confunde, y por eso están separados en el formulario con
   una explicación al lado:
   - LA ACTIVIDAD (`tipo_actor`) es la REGLA. Decide qué puede emitir la
   credencial —faenas o guías— y si lleva cupo en kilos. Es un enum del
   servidor y no se puede inventar.
   - EL TIPO DE CARNET es el CATÁLOGO: cómo se llama el documento y cuánto
   sale. La unidad lo edita, y el mismo documento puede figurar como «Carnet
   de Pescador» o «Pescador Artesanal» sin que cambie nada de lo anterior.
   Juntarlos en un solo campo parecería más simple y sería una trampa: bastaría
   con renombrar una fila del catálogo para cambiar lo que un carnet habilita.
   EL AVISO DEL CUPO EVITA EL VIAJE EN FALSO
   Un carnet de pescador sin bolsa madre lo rechaza el servidor, porque el
   plástico imprime el cupo. La pantalla lo avisa apenas se eligen la persona y
   la actividad, para que el operador vaya a otorgar el cupo antes de llenar el
   resto del formulario y perderlo.

## `resources/js/pages/panel/carnets/index.tsx`

### LISTADO DE CARNETS

   EL BUSCADOR ACEPTA EL CÓDIGO CON ESPACIOS
   El código va impreso en grupos de cuatro, así que quien lo copia del plástico
   escribe «PES2 6K7R J2M». El servidor lo normaliza antes de comparar; si no,
   la búsqueda que hace todo el mundo no devolvería nunca nada.

## `resources/js/pages/panel/carnets/ver.tsx`

### LA FICHA DE UN CARNET

   LO PRIMERO ES QUÉ HABILITA HOY, NO LOS DATOS
   Quien abre esta pantalla casi siempre viene con una pregunta concreta:
   «¿puedo emitirle una faena a esta persona?». Los datos del titular ya los
   tiene delante, en el plástico. Por eso el bloque de arriba responde eso, con
   la respuesta YA RESUELTA por el servidor —`puede_emitir_faenas` exige carnet
   vigente Y cupo con saldo, dos cosas que la pantalla no puede juntar sola—.

## `resources/js/pages/panel/catalogos/asociaciones.tsx`

### CATÁLOGO DE ASOCIACIONES — y el patrón de las tres pantallas de catálogo

   LA TABLA OCUPA EL ANCHO ENTERO Y EL FORMULARIO SE ABRE ARRIBA
   El formulario sigue en la MISMA pantalla que la lista —navegar a un alta y
   volver hace perder de vista aquello contra lo que se compara mientras se
   carga: «¿ya está ASOPESCA?», «¿este tramo se pisa con el anterior?»— pero va
   ARRIBA y no en una columna al costado.
   Dos motivos. Uno: la tabla necesita el ancho, igual que la del padrón — con
   un tercio de la pantalla robado, las columnas se aprietan y la de «en uso» se
   corta. Dos: a lo ancho los campos entran en UNA fila en vez de apilarse en
   una columna angosta, así que el formulario ocupa menos alto del que ocupaba
   al costado.
   Es lo contrario de Beneficiarios, que sí tiene pantallas aparte: ahí el
   formulario tiene veinte campos y una foto, y no entra en una fila.
   UN SOLO ESTADO DECIDE TODO: `editando`
   null      -> el formulario está cerrado
   'nueva'   -> alta
   <fila>    -> edición de esa fila
   Con tres booleanos sueltos —`abierto`, `esAlta`, `filaActual`— se pueden
   combinar en estados imposibles: abierto sin fila y sin ser alta. Un único
   valor no lo permite.
   NO HAY BOTÓN DE BORRAR, Y ES LA REGLA
   Los carnets y las guías emitidas apuntan acá. Una asociación que se deja de
   usar se pone INACTIVA: desaparece de los desplegables de alta y los
   documentos históricos la siguen mostrando.
   La columna «En uso» está para que eso se entienda solo: con 12 carnets
   colgando, que no haya papelera deja de parecer un descuido.

## `resources/js/pages/panel/catalogos/escala.tsx`

### LA ESCALA OFICIAL DE APROVECHAMIENTO

   De esta tabla salen los dos números con los que trabaja el módulo de pesca:
   cuántos kilos se autorizan y cuánto se cobra por ellos.
   El patrón de la pantalla —tabla a ancho completo, formulario arriba, un solo
   estado `editando`, sin botón de borrar— está explicado en `asociaciones.tsx`.
   LO PROPIO DE ESTA PANTALLA: EL AVISO DE HUECOS
   Un hueco entre dos tramos no rompe nada visible. Simplemente hay volúmenes
   que no caen en ninguna escala: el servidor devuelve null al buscar el tramo y
   el formulario de cupo no ofrece nada, sin ningún error que lo explique. Es el
   tipo de falla que se descubre en ventanilla, con alguien enfrente.
   El SOLAPE, en cambio, lo rechaza el servidor al guardar: es plata mal cobrada
   —un cupo que cae en dos tramos se cobra con el más barato— y no tiene lectura
   válida.
   Los huecos los calcula el SERVIDOR y llegan en `huecos`. La pantalla no los
   deduce recorriendo la tabla: ordenar por kilos y comparar extremos es la misma
   regla, y escrita en los dos lados se desincroniza.

## `resources/js/pages/panel/catalogos/tipos-carnet.tsx`

### CATÁLOGO DE TIPOS DE CARNET

   Cómo se llama cada credencial y cuánto sale.
   El patrón de la pantalla —tabla a ancho completo, formulario arriba, un solo
   estado `editando`, sin botón de borrar— está explicado en `asociaciones.tsx`.
   ES EL CATÁLOGO, NO LA REGLA — y por eso el nombre se puede editar tranquilo
   Qué habilita un carnet —si emite faenas o guías, si lleva cupo en kilos— lo
   dice `carnets.tipo_actor`, que es un enum del servidor. De este nombre no
   cuelga ninguna decisión: el mismo documento puede figurar como «Carnet de
   Pescador» o «Pescador Artesanal» y el sistema se comporta igual.
   CAMBIAR EL PRECIO NO TOCA LO YA COBRADO, PERO SÍ LO QUE SE DEBE
   Lo cobrado vive en `pagos` y no se recalcula nunca: un carnet emitido a 80 Bs
   sigue diciendo 80 aunque el arancel suba. Lo que SÍ se mueve es el saldo
   pendiente de los carnets que todavía no están cubiertos, porque ese saldo se
   calcula contra esta columna.
   Es lo correcto —lo que se debe se debe a la tarifa vigente— pero conviene
   saberlo antes de tocar el número, y por eso está escrito bajo el campo.

## `resources/js/pages/panel/dashboard.tsx`

### LOS DOS GRÁFICOS GRANDES SE CARGAN APARTE, Y DESPUÉS

   `lazy()` le dice a Vite que ponga cada uno en su propio archivo y que lo baje
   recién cuando haga falta dibujarlo, en vez de meterlo dentro del archivo del
   tablero.
   El motivo es concreto y se medía: los dos gráficos usan `recharts`, y por
   arrastrarla el tablero pesaba **373 kB** —más que React entero—. Es la
   pantalla a la que cae TODO el mundo apenas entra, así que ese peso lo pagaba
   cada persona en cada ingreso, antes de ver un solo número.
   Y los números son lo accionable: «cuánta gente está habilitada», «cuánto se
   recaudó hoy». Los gráficos son contexto. Separándolos, lo importante aparece
   de inmediato y lo demás llega un instante después, solo.
   LAS LÍNEAS CHICAS DE LOS CUATRO INDICADORES NO PASAN POR ACÁ: están dibujadas
   a mano en SVG justamente para no volver a meter recharts arriba de todo y
   desarmar esta decisión. Ver components/panel/dashboard/mini-grafico.tsx.
   `Suspense` es lo que React necesita para saber qué dibujar mientras tanto:
   sin él, un componente `lazy()` que todavía no llegó revienta la pantalla.
   El `fallback` es un recuadro de la MISMA altura —ver GraficoCargando—, así
   la página no salta cuando el gráfico aparece.

### El hueco del gráfico mientras se está bajando.

   Ocupa lo mismo que el gráfico terminado, y por eso el alto es una prop y no
   un número fijo: los dos gráficos miden distinto. Si el hueco midiera otra
   cosa, al llegar el gráfico la página daría un salto y lo que el operador
   estaba por tocar se le correría de lugar.
   `animate-pulse` es de Tailwind y hace el latido gris de «esto está por
   llegar».

## `resources/js/pages/panel/faenas/crear.tsx`

### EMITIR UN PERMISO DE FAENA — paso 4 del flujo

   SE ELIGE UNA PERSONA Y DESPUÉS UN CARNET, NO AL REVÉS
   En el mostrador la persona se identifica con su cédula, no con el código del
   plástico: llega, lo deja sobre el escritorio y el operador tipea el nombre.
   Por eso el formulario arranca con el buscador de beneficiarios y recién
   después muestra qué credenciales tiene.
   Y las muestra TODAS las vigentes, no solo las que sirven. Un carnet de
   comercializador aparece deshabilitado y con el motivo al lado, en vez de
   desaparecer: si no está, el operador cree que la persona no tiene carnet y
   va a emitirle otro.
   EL NÚMERO SE PROPONE, NO SE IMPONE
   Sale de un talonario de PAPEL que el pescador se lleva. El sistema sugiere el
   siguiente para no hacer contar hojas, pero el campo es editable: si la hoja
   que el operador tiene en la mano dice otro número, hay algo que conviene
   mirar antes de seguir, no autocorregir en silencio.

### Lo que dice APROVECHAMIENTO_ESTRICTO en el servidor, y ES LO QUE DECIDE

   Con la validación apagada el servidor acepta la faena igual, así que
   frenar acá sería la pantalla inventando una regla que el sistema no
   tiene — y el operador se quedaría sin poder emitir algo perfectamente
   válido, sin ningún mensaje que lo explique.

### Los carnets vigentes de la persona, con el que no sirve deshabilitado.

   NO SE FILTRAN LOS QUE NO PUEDEN: aparecen en gris y con el motivo al lado. Un
   carnet que desaparece de la lista le dice al operador «esta persona no tiene
   carnet», que es falso y lo manda a emitir otro.
   `puede_emitir_faenas` llega RESUELTO del servidor: exige carnet vigente, de
   pescador Y con cupo con saldo. Son tres condiciones que la pantalla no puede
   juntar sola sin copiar la regla.

## `resources/js/pages/panel/faenas/index.tsx`

### LISTADO DE PERMISOS DE FAENA

   LAS CADUCADAS SE MARCAN, Y ES LO MÁS ÚTIL DE ESTA PANTALLA
   Una faena que se pasó de fecha y sigue ACTIVA es un papel que alguien se
   llevó y del que nadie registró la vuelta. El comando diario la marca como
   vencida, pero entre corrida y corrida queda acá a la vista — y el dato es
   accionable: hay que ir a buscarla, no esperar.
   El aviso sale de `caducada`, que llega resuelto del servidor: la pantalla no
   compara fechas, porque `new Date('2026-12-31')` en JavaScript se interpreta
   como medianoche UTC y en UTC-4 devuelve el día anterior.

## `resources/js/pages/panel/faenas/ver.tsx`

### LA FICHA DE UNA FAENA

   CERRAR LA FAENA ES LA ACCIÓN, Y ESTÁ ARRIBA DE TODO
   Quien abre esta pantalla casi siempre viene porque el pescador volvió. Por
   eso el formulario de cierre es lo primero, con los kilos declarados ya
   puestos: en la mayoría de los casos coinciden con la balanza y alcanza con
   confirmar.
   COMPLETAR NO CAMBIA EL SALDO, Y ESO SORPRENDE
   Los kilos ya estaban descontados desde que la faena se emitió: una faena
   ACTIVA consume cupo aunque no se haya descargado nada. Si solo contaran las
   completadas, un pescador podría tener diez faenas abiertas por el volumen
   entero cada una.
   Lo que sí mueve el saldo es CORREGIR los kilos al cerrar.

## `resources/js/pages/panel/guias/crear.tsx`

### EMITIR UNA GUÍA DE MOVIMIENTO — paso 4, rama comercializador

   EL PRECIO SE MUESTRA ANTES DE GUARDAR, Y SALE DEL SERVIDOR
   La casilla de piscicultura no es un dato descriptivo: marcarla cobra la
   MITAD. Por eso el monto aparece al costado y cambia al instante — el operador
   ve la consecuencia mientras decide, no después en la caja.
   La tarifa y el descuento llegan como props y no escritos acá: tienen que ser
   los MISMOS que cobra `GuiaMovimiento::montoACobrar()`. Escritos en los dos
   lados, el día que la resolución cambie el 50% a 40% la pantalla seguiría
   prometiendo un precio que la caja no cobra.

## `resources/js/pages/panel/guias/index.tsx`

### LISTADO DE GUÍAS DE MOVIMIENTO

   SE BUSCA POR ORIGEN Y DESTINO, NO SOLO POR PERSONA
   Es la pregunta que trae a alguien a esta pantalla: «¿qué salió para Santa
   Cruz esta semana?». Un buscador que solo mire el nombre del comercializador
   obligaría a saber de antemano a quién buscar, que es justo lo que no se sabe.
   Las fechas se muestran con `fechaHora()` y no con `fecha()`: los cinco días
   se cuentan desde el instante de emisión, así que la hora es el dato que
   decide la vigencia.

## `resources/js/pages/panel/guias/ver.tsx`

### LA FICHA DE UNA GUÍA

   CERRAR Y ANULAR NO SON LO MISMO, Y LA PANTALLA LO DICE
   CERRAR es registrar que la carga llegó: el traslado ocurrió y esta guía lo
   amparó. ANULAR es decir que el papel nunca valió.
   Por eso una guía CERRADA ya no se puede anular —lo dice `puede_anularse`, que
   llega resuelto del servidor—: anularla declararía que nunca amparó nada y
   dejaría un viaje real sin ningún respaldo.
   Las fechas van con `fechaHora()`: los cinco días se cuentan desde el instante
   de emisión, así que la hora es el dato que decide la vigencia.

## `resources/js/pages/panel/recibos/index.tsx`

### RECIBOS — los comprobantes entregados

   Es la vista de los PAPELES, con su número correlativo: lo que audita
   Contabilidad. La otra mitad —cada entrega de dinero— está en «Caja».
   LA COLUMNA QUE MÁS IMPORTA ES «CUADRA»
   `monto_total` es lo que se IMPRIMIÓ, congelado al emitir. `monto_actual` es
   lo que HAY hoy en el detalle. Si alguien corrigió un abono después de
   entregar el papel, los dos números se separan.
   Eso NO se tapa recalculando al leer: el papel entregado no puede cambiar
   porque después se corrija algo. Se muestra, que es justamente lo que un
   arqueo tiene que poder detectar.

## `resources/js/pages/panel/recibos/ver.tsx`

### LA FICHA DE UN RECIBO

   Es el detalle del papel entregado: qué trámites cubrió y por cuánto cada uno.
   NO HAY BOTONES DE EDITAR NI DE BORRAR, Y ES LA REGLA
   `numero_recibo` es un correlativo que Contabilidad audita: borrar una fila
   deja un hueco en la serie que nadie puede explicar. Y el recibo NACE del
   cobro, en la misma transacción que sus abonos — no existe una forma de
   crearlo suelto, porque sería un comprobante numerado diciendo que entró plata
   que no entró.

## `resources/js/pages/publico/verificar.tsx`

### VERIFICACIÓN PÚBLICA DE CARNETS

   La ÚNICA pantalla del sistema que se ve sin iniciar sesión, y la más
   importante de todas: es la que sostiene el valor de cada carnet que emite la
   Gobernación.
   Es la dirección codificada dentro del QR impreso. Un inspector escanea el QR
   de un pescador con su teléfono, en el muelle, y cae acá.
   UN SOLO DATO: EL CÓDIGO DEL CARNET
   El carnet se identifica por `codigo_carnet`, que es único GLOBAL —no por
   tipo— justamente para esto: un control en ruta lee un código y tiene que
   llegar a UN documento, sin preguntar antes de qué tipo es.
   ESE CÓDIGO VA IMPRESO EN EL PLÁSTICO, así que quien tenga el carnet en la
   mano puede consultarlo. Se aceptó porque lo que se muestra acá es
   deliberadamente poco: nada que no esté ya en la tarjeta que esa persona está
   mirando. Del barrido automático protege el límite de intentos por minuto de
   la ruta.
   EL DISEÑO: UN ACTA, NO UNA PANTALLA
   Todo lo que se muestra va sobre una hoja blanca con membrete y guarda. El
   motivo está explicado largo en `hoja-oficial.tsx`, pero en corto: el ciudadano
   tiene el papel en la mano y compara. Si la pantalla se parece a una
   aplicación, no hay nada que comparar; si se parece a otro papel oficial, la
   comparación la hace cualquiera sin que le expliquen.
   LOS CUATRO RESULTADOS POSIBLES
   VIGENTE   verde  — auténtico y habilita las actividades que lista
   VENCIDO   ámbar  — auténtico pero cerró la gestión: NO habilita
   ANULADO   rojo   — la institución lo dio de baja
   NO EXISTE gris   — ningún carnet responde a ese código
   «Vigente» lo decide `Carnet::estaVigente()` en PHP, que mira el estado Y la
   fecha: el estado lo escribe un comando programado que corre una vez al día, y
   entre corrida y corrida un carnet que venció ayer sigue diciendo «vigente» en
   la columna. Acá eso sería habilitar a alguien con un documento caído.
   LO QUE ESTA PANTALLA NO MUESTRA
   Cualquiera que levante un carnet del suelo puede abrirla, así que solo aparece
   lo mínimo para constatar autenticidad: nunca el CI completo (llega enmascarado
   desde PHP), ni la dirección, ni el teléfono del titular. Ver
   `VerificacionController::datosPublicos()`.

### Acta de carnet no hallado.

   Sale en la misma hoja que las demás, y no en una pantalla de error. Es
   deliberado: que no figure un carnet es un RESULTADO de la consulta, tan válido
   como los otros tres, y merece la misma constancia. Una pantalla de error haría
   dudar de si el sistema falló o si el documento es falso.
   EL TEXTO NO ACUSA A NADIE, y eso importa más de lo que parece. Puede ser un
   carnet falso, pero también un código mal tipeado, un QR borroso o un 0 leído
   como O. Acusar de falsificación a quien se equivocó en una letra sería un
   problema real en una ventanilla pública. Se informa el hecho y se dice qué
   hacer.

## `resources/js/types/aprovechamientos.ts`

### Tipos del módulo Aprovechamientos — la BOLSA MADRE del pescador.

   Describen, campo por campo, lo que arma
   App\Http\Controllers\Panel\AprovechamientoController. Si allá se renombra una
   clave y acá no, el editor lo marca en rojo al instante en vez de descubrirlo
   con una pantalla en blanco.
   TODO LO CALCULADO LLEGA RESUELTO DEL SERVIDOR
   `saldo_kg`, `vigente`, `puede_emitir_faena`, `saldo_pendiente`: ninguno se
   deduce en la pantalla, y no es comodidad. Son REGLAS:
   - `vigente` mira el estado Y la fecha, porque la columna de estado la
   escribe un comando que corre una vez al día y entre corrida y corrida
   miente.
   - `saldo_kg` se corta en cero: un cupo excedido no es un saldo negativo.
   - `saldo_pendiente` también, porque pagar de más no genera saldo a favor.
   Deducirlas en React sería una segunda copia de cada una, y las copias se
   desincronizan sin que nada falle.

### EL CONTROL DE LA BOLETA, QUE NO ES EL ESTADO DEL PAGO

   Que el dinero entró ya lo dice que la fila exista. Esto contesta si
   alguien comparó la boleta contra el extracto del banco y qué encontró:
   `pendiente` nadie la miró, `validado` cuadra, `observado` no cuadra.
   Un observado SIGUE SUMANDO en el saldo: lo que está en duda es si la
   boleta respalda lo que dice.

## `resources/js/types/beneficiarios.ts`

### El resumen de un carnet que muestra la ficha del beneficiario.

   LA ACTIVIDAD VA PRIMERO, Y NO ES UN DETALLE DE ORDEN
   Una persona puede tener DOS carnets vigentes a la vez —quien pesca y además
   comercializa—, así que sin `tipo_actor` las dos filas se ven idénticas y el
   operador no sabe cuál está mirando.
   `vigente` llega YA RESUELTO del servidor y la pantalla no lo deduce: la
   columna `estado` puede estar desfasada, porque «vencido» lo escribe un
   comando que corre una vez al día. Ver Carnet::estaVigente().

## `resources/js/types/caja.ts`

### Tipos del módulo Caja y Recibos — el circuito del dinero.

   Describen lo que arman `CajaController` y `ReciboController`.
   DOS VISTAS DEL MISMO HECHO, Y NINGUNA REEMPLAZA A LA OTRA
   - `PagoFila` es un ABONO: cada entrega de dinero, con su método y su
   trámite. Es lo que se cuadra contra el efectivo del cajón al cerrar.
   - `ReciboFila` es el PAPEL entregado, con su correlativo. Es lo que audita
   Contabilidad.
   Un recibo agrupa varios abonos, así que las dos listas nunca tienen la misma
   cantidad de filas.

### Lo cobrado hoy, repartido por método.

   Es SIEMPRE del día de hoy, no del rango filtrado: es lo que se compara contra
   el efectivo del cajón antes de cerrar, y esa pregunta no cambia porque
   alguien esté mirando marzo.
   El reparto por método tampoco es decorativo: lo que hay que cuadrar contra el
   cajón es el EFECTIVO, y una transferencia no está ahí adentro.

## `resources/js/types/carnets.ts`

### Tipos del módulo Carnets — la credencial anual.

   Describen, campo por campo, lo que arma
   App\Http\Controllers\Panel\CarnetController.
   LAS TRES BANDERAS LLEGAN RESUELTAS, Y NINGUNA SE DEDUCE EN LA PANTALLA
   - `vigente` mira el estado Y la fecha, porque la columna de estado la
   escribe un comando diario y entre corrida y corrida miente.
   - `cupo_kg` depende del TIPO DE ACTOR, que es un enum del servidor, nunca
   del nombre del tipo de carnet — ese nombre es un catálogo editable.
   - `puede_emitir_faenas` exige además una bolsa madre con saldo.
   Un `if` sobre el nombre del tipo en React sería una segunda copia de esas
   reglas, y se rompería en silencio en cuanto alguien renombre una fila.

## `resources/js/types/catalogos.ts`

### Tipos de los tres CATÁLOGOS: asociaciones, escala de aprovechamiento y tipos

   Cada interfaz describe, campo por campo, lo que arman
   App\Http\Controllers\Panel\{Asociacion,CategoriaAprovechamiento,TipoCarnet}Controller.
   Si allá se renombra una clave y acá no, el editor lo marca en rojo al
   instante en vez de descubrirlo con una pantalla en blanco.
   LOS TRES TRAEN UN CONTADOR DE USO, Y NO ES DECORACIÓN
   `carnets_count`, `guias_count`, `aprovechamientos_count`: es lo que explica
   por qué una fila no se puede borrar. Sin el número, «solo se puede desactivar»
   suena a capricho del sistema; con él se entiende que hay documentos emitidos
   colgando de esa fila.

### Un rango de kilos que no cae en ningún tramo.

   Lo calcula el servidor mirando la escala ENTERA y ordenando por KILOS, no por
   número de escala: nada impide cargar la escala 7 con el rango más bajo, y
   recorriendo por número un catálogo así daría huecos inventados.
   El hueco no rompe nada visible —simplemente hay volúmenes que no caen en
   ninguna escala y el formulario de cupo no ofrece nada— así que sin este aviso
   se descubre en ventanilla, con alguien enfrente.

## `resources/js/types/dashboard.ts`

### Tipos del panel principal.

   Cada uno describe, campo por campo, lo que arma
   App\Http\Controllers\Panel\DashboardController. Si allá se agrega o se
   renombra una clave, hay que reflejarlo acá: TypeScript avisa en el editor y
   no hay que esperar a que reviente en el navegador.
   CONVENCIÓN DEL PROYECTO
   - tipos compartidos por todo el sistema  -> types/index.d.ts
   - tipos de un módulo concreto            -> types/<modulo>.ts  (este archivo)

## `resources/js/types/faenas.ts`

### Tipos del módulo Faenas — el permiso de UNA salida de pesca.

   Describen, campo por campo, lo que arma
   App\Http\Controllers\Panel\FaenaController.
   TRES BANDERAS LLEGAN RESUELTAS, Y NINGUNA SE DEDUCE EN LA PANTALLA
   - `vigente` mira el estado Y la fecha límite. La columna de estado la
   escribe un comando diario y entre corrida y corrida miente.
   - `consume_cupo` sale del enum: una faena VENCIDA libera su volumen, porque
   la salida no ocurrió.
   - `puede_completarse` exige que esté EN CURSO. Sobre una vencida no se
   puede: al vencer ya devolvió los kilos, y completarla los volvería a
   descontar de un cupo que se repuso.

## `resources/js/types/guias.ts`

### Tipos del módulo Guías — el amparo de UN traslado de producto.

   Describen, campo por campo, lo que arma
   App\Http\Controllers\Panel\GuiaController.
   LAS FECHAS SON MOMENTOS, NO DÍAS
   Llegan en ISO 8601 CON hora, y hay que mostrarlas con `fechaHora()`, no con
   `fecha()`. Los cinco días de validez se cuentan desde el instante de emisión
   —una guía de las 18:00 del lunes vence a las 18:00 del sábado— así que la
   hora ES el dato que decide la vigencia.
   Es al revés que en carnets, cupos y faenas, donde las columnas guardan un DÍA
   y viajan con `toDateString()`. Mezclarlos corre la fecha: en UTC-4, un día
   mandado como instante se muestra con 24 horas menos.

## `resources/js/types/index.d.ts`

### TIPOS COMPARTIDOS POR TODO EL SISTEMA

   Acá va SOLO lo que usan varias pantallas a la vez. Lo que pertenece a un
   módulo concreto vive en su propio archivo:
   types/index.d.ts        <- este archivo: lo común
   types/dashboard.ts      <- tipos del panel principal
   types/beneficiarios.ts  <- tipos del módulo Beneficiarios
   types/tramites.ts       <- tipos del módulo Trámites
   types/carnets.ts        <- tipos del módulo Carnets
   types/rubros.ts         <- tipos del catálogo de rubros
   types/pagos.ts          <- tipos del libro de caja
   ¿Para qué sirven los tipos? Describen la forma exacta de los datos que
   manda Laravel. Si en PHP se renombra una clave y acá no, el editor lo marca
   en rojo al instante, en vez de descubrirlo con una pantalla en blanco.

### Lo que devuelve ->paginate() de Laravel, ya convertido a JSON.

   <T> es un "genérico": el tipo de las filas se indica al usarlo.
   Paginado<BeneficiarioFila>  -> data es BeneficiarioFila[]
   Paginado<PagoFila>          -> data es PagoFila[]
   Así el componente de paginación sirve para cualquier listado sin perder la
   verificación de tipos de las filas.

### Espejo de App\Enums\EstadoTramite.

   pendiente ──▶ en_revision ──▶ aprobado
   │              │
   └──────────────┴─────────▶ rechazado
   Son CUATRO y no seis: «generado» y «entregado» no son estados sino hechos con
   fecha, y viven en las columnas `fecha_generacion` y `fecha_entrega`. Un estado
   obliga a mantener sincronizadas dos cosas que pueden discrepar; una fecha en
   NULL dice «todavía no pasó» sin posibilidad de contradicción.
   Qué salto vale desde dónde NO se decide acá: lo dice el enum de PHP, y llega a
   la pantalla como los campos `puede_*` de la ficha.

### Espejo de App\Enums\EstadoAprovechamiento.

   PENDIENTE ──[enviar, con el monto cubierto]──▶ EN REVISIÓN
   (borrador)                                         │
   ▲                              ┌──────────────┴──────────────┐
   └──────────[rechazar]──────────┤                             │
   [aprobar]                          │
   │                             │
   ACTIVO ──▶ AGOTADO | VENCIDO ────┘
   `pendiente` es el BORRADOR: otorgado y sin cobrar del todo. Es el único estado
   en que el cupo se edita, se elimina y admite depósitos.
   `en_revision` es el expediente PRESENTADO: la plata está y falta que alguien
   firme. Tampoco autoriza a pescar — recién lo hace al aprobarse.
   `vencido` y `agotado` son distintos a propósito: se le acabó el tiempo o se
   le acabaron los kilos, y al pescador se le explica distinto aunque los dos
   terminen en un trámite nuevo.

### Espejo de App\Enums\ModalidadAprovechamiento.

   El RÉGIMEN bajo el que se autoriza el cupo:
   - `escala_general`   — tramo de la escala progresiva: a más kilos, más valor.
   - `especie_especial` — paiche y lo que la resolución sume, con tasación fija.
   Vive en el TRAMO de la escala —la fija la resolución, no el operador— y se
   COPIA al cupo al otorgarlo, por lo mismo que el volumen: reclasificar el
   tramo no puede cambiarle la clasificación a lo ya otorgado.

### Espejo de App\Enums\EstadoAsociacion.

   Una asociación NO se borra: se pone inactiva. Los carnets y las guías ya
   emitidas apuntan a ella, y una asociación inactiva desaparece de los
   desplegables de alta pero los documentos históricos la siguen mostrando — que
   es lo correcto, porque la persona pertenecía a ella cuando se le emitió el
   carnet.

## `resources/js/types/publico.ts`

### Tipos de la parte pública (verificación de carnets por QR).

   Lo arma App\Http\Controllers\Publico\VerificacionController.
   REGLA DE ORO DE ESTA PARTE DEL SISTEMA: acá solo puede aparecer lo mínimo
   para constatar que un carnet es auténtico. Nunca el CI completo, ni la
   dirección, ni el teléfono del titular. Cualquiera que levante un carnet del
   suelo puede ver esta pantalla, así que cada campo que se agregue queda
   expuesto al público.

### La actividad que el carnet autoriza: «Pescador» o «Comercializador».

   Es UNA, no una lista: cada actividad es un carnet propio, y quien hace
   las dos tiene dos credenciales con dos códigos distintos.
   Viene en `null` cuando el carnet no está vigente, y no es un olvido:
   mostrar la actividad —aunque fuera marcada en rojo— arriesga que el
   inspector lea la fila y no el sello. Lo que no habilita, no aparece.

## `resources/views/documentos/carnet-pescador.blade.php`

### CÉDULA DE PESCADOR — calco del carnet plastificado del SEDAG

   Reproduce la credencial que la unidad venía mandando a imprimir: el mismo verde
   con el sello de agua, el mismo encabezado con el escudo y el bloque del SEDAG,
   el mismo título en rojo perfilado de dorado, la foto a la izquierda con la
   cédula debajo y los mismos seis renglones sobre sus tiras claras.
   Quien la recibe está acostumbrado a ese formato, y el inspector que la revisa
   en el río la reconoce de lejos por su forma. Una versión «mejorada» se lee
   como si fuera otro documento — se probó una, con el nombre grande y sin tiras,
   y se descartó por eso mismo.
   LO QUE SE APARTA DEL PLÁSTICO DE PAPEL, Y POR QUÉ
   NO LLEVA QR, y se sacó a pedido después de haberlo tenido. Conviene saber qué
   se perdió con eso: la verificación pública sigue existiendo —la pantalla, la
   firma de validación, todo— pero **desde el plástico ya no hay forma de
   llegar a ella**. Quien tenga el carnet en la mano no puede comprobar si es
   real, ni ver qué rubros habilita; eso ahora solo se consulta desde el panel.
   Es la misma limitación que tenía la credencial de papel, que era su problema
   central: quien la miraba tenía que creerle.
   AHORA SÍ VAN EL RUBRO Y EL CUPO, y este comentario decía lo contrario hasta
   el cambio de modelo. Conviene entender por qué, porque el motivo viejo era
   bueno y lo que cambió no fue la opinión sino el sistema.
   El carnet era UNO por persona y gestión, con los rubros colgados en una tabla
   aparte: quien era pescador en febrero podía sumar comercializador en octubre
   sin que el plástico cambiara. Impresa, la lista de rubros quedaba vieja ese
   mismo día, y el documento pasaba a decir MENOS de lo que la persona estaba
   autorizada a hacer — que es peor que no decir nada. Con el cupo pasaba algo
   parecido: era un tope POR ACTIVIDAD, así que con dos rubros había dos cupos y
   un solo renglón donde ponerlos.
   Hoy el carnet es de UN rubro y ese rubro es parte de la llave que lo
   identifica: no cambia nunca, y sumar una actividad emite otro carnet con su
   propio plástico. No queda nada que pueda dejar vieja la impresión, y el cupo
   es uno solo. Los dos comparten el segundo renglón.
   Y es más que una posibilidad: es necesario. Dos carnets de la misma persona en
   la misma gestión son dos plásticos con el mismo nombre, la misma foto y el
   mismo domicilio. Sin el rubro impreso, nada los distingue a simple vista.
   TAMPOCO VA LA FECHA DE VENCIMIENTO. Todos los carnets de una gestión vencen el
   mismo día, así que el año del registro ya lo dice; y si la pregunta es si HOY
   vale, la fecha impresa nunca fue la respuesta, porque un carnet puede estar
   anulado o suspendido con su fecha intacta. Como la tarjeta tampoco lleva QR,
   eso solo se consulta desde el panel.
   ESPEJO DE LA VISTA PREVIA DEL PANEL
   `resources/js/components/panel/tramites/vista-previa-carnet.tsx` dibuja este
   mismo molde en pantalla, mientras el operador carga el trámite. **Si se toca
   una, se toca la otra**, o la vista previa pasa a prometer una tarjeta que el
   PDF no entrega.
   POR QUÉ TODO ESTÁ POSICIONADO EN ABSOLUTO
   Porque esto no es una página web que se acomoda al ancho del que mira: es una
   tarjeta de medida fija que tiene que salir SIEMPRE igual. Con el flujo normal
   del documento, un nombre más largo que otro corre todo lo que viene abajo y
   dos carnets salen distintos — y el troquel de la impresora de credenciales no
   perdona medio milímetro.
   Además lo dibuja DomPDF, que no es un navegador: no entiende flexbox, ni grid,
   ni variables CSS, ni `linear-gradient`. Lo que sí entiende bien es
   `position: absolute`, las tablas y los bordes.
   EL SISTEMA DE COORDENADAS
   La tarjeta es de 243 x 153 puntos —CR80 apaisada, 85,6 x 54 mm— y todos los
   `top` y `left` son puntos DENTRO de esa caja. No hay margen de página: el
   verde llega hasta el borde, como en el plástico.
   margen               =   8,5
   columna izquierda    =    46   ->  cédula y foto
   calle                =   5,5
   columna de datos     =   176   ->  hasta el margen derecho
   rótulos              =    41   ->  los valores arrancan todos parejos
   El tamaño NO está acá sino en CarnetImpresionController: es una decisión de
   impresión, no de diseño. Si cambia, estas coordenadas hay que revisarlas.

### EL FONDO es una imagen y no un `background` con degradado porque

   Se declara PRIMERO: DomPDF respeta el apilado por orden de
   declaración, así que lo que viene detrás queda arriba.

### EL ENCABEZADO, EN DOS PISOS

   Arriba el lockup de la Gobernación —escudo y texto, como grupo
   centrado— y DEBAJO el bloque de la Secretaría a todo el ancho, también
   centrado. No van uno al lado del otro con un filete en el medio: así
   es la credencial que se tomó de modelo.
   Y APILADOS ENTRAN MUCHO MÁS GRANDES, que es la ventaja de fondo. Al
   costado, el bloque del SEDAG tenía 112 pt y sus dos líneas largas
   estaban topadas —«SECRETARÍA DPTAL. DE DESARROLLO PRODUCTIVO,» ocupaba
   102,2 de esos 112—. A todo el ancho tiene 226, su tope se va a 7,74 pt
   y vuelve a entrar de a UNA línea, sin los cortes forzados que la
   versión al costado necesitaba.
   al costado   tres renglones de 4,8 pt, partidos a la fuerza
   apilado      dos renglones de 6 pt, cortados donde corta la frase
   EL LOCKUP VA CENTRADO COMO GRUPO, no cada parte por su lado: el
   escudo mide 20,7 pt de ancho, el texto 62 —lo marca «GOBERNACIÓN», que
   es la línea más larga— y con el filete y sus dos calles el grupo suma
   89. En una carilla de 243 eso arranca en 77. Mover cualquiera de las
   tres partes obliga a rehacer esa cuenta.
   EL COSTO ES VERTICAL, y es lo que hay que vigilar acá: dos pisos
   ocupan lo que antes ocupaba uno al lado del otro. El encabezado cierra
   en 52 y empuja todo lo de abajo, así que estas coordenadas se mueven
   todas juntas:
   lockup       2  ->  28,5
   cuadro SEDAG 29 ->  52     (bordeado, dentro del margen)
   título      53  ->  64
   renglones   66  ->  149      (seis, cada 14)
   margen                        4  hasta los 153 de la carilla
   CADA RENGLÓN DEL ENCABEZADO LLEVA SU ALTO ESCRITO, y no se lo deja al
   `line-height`. La caja de línea que DomPDF le da a DejaVu Sans es
   bastante más alta que el cuerpo —del orden de 1,17 em—, así que con
   `line-height: 1.05` el bloque medía más de lo que la cuenta decía y
   «BENI» terminaba encima de la primera línea del SEDAG. Con el alto
   escrito, apilar dos bloques vuelve a ser sumar.
   Los altos son el cuerpo por 1,15: deja lugar a la tilde de «Ó» sin
   abrir el interlineado.

### EL FILETE entre el escudo y el texto, como en el lockup oficial: un

   OJO CON LAS CUENTAS AL TOCARLO. El grupo entero va centrado en la
   carilla, así que el filete no se puede correr solo: sus 0,7 pt más las
   dos calles de 2,8 forman parte del ancho del grupo, y moverlo obliga a
   recalcular dónde arranca el escudo y dónde el texto.
   escudo   77    -> 97,7    (24 pt de alto, 20,7 de ancho)
   filete   100,5 -> 101,2
   texto    104   -> 166
   grupo    89 pt de ancho, centrado en 243 -> arranca en 77

### EL LOCKUP VA EN BLANCO PERFILADO, a pedido, y solo él: el bloque del

   Usa el mismo mecanismo que los rótulos —cinco copias, ver
   `partes/texto-perfilado`— porque el problema es el mismo: sobre este
   verde, un texto claro sin borde se desdibuja donde pasa el sello.
   El contorno es el `.perfil` de 0,2 pt, que se calibró para los
   rótulos de 4,6. Acá va sobre 7 y 4,5, así que rinde todavía más fino
   en proporción — y eso es lo que se busca: que se lean BLANCAS, con el
   borde apenas recortándolas.
   Cada línea necesita `position: relative` propio: las cinco copias son
   `absolute` y sin contenedor posicionado se medirían contra la carilla.

### EL BLOQUE DEL SEDAG VA SOBRE UNA BANDA BLANCA, con la letra negra, a

   Queda además coherente con el resto de la tarjeta, que ya se había
   ordenado con esa misma regla: lo que es DATO va negro sobre blanco, lo
   que es andamio va sobre el verde. Y resuelve de paso el contraste —era
   lo único que seguía en verde oscuro sobre un fondo que bajó dos
   escalones.
   ES UN CUADRO CON BORDE Y ESQUINAS REDONDEADAS, dentro del margen —no
   una faja que cruza de borde a borde—. El radio de 9 pt sobre una caja
   de unos 22 lo deja casi como una pastilla, que es la forma que tiene
   en la credencial de referencia.
   `border-radius` lo dibuja DomPDF desde la 2.x y acá corre la 3.1.6,
   así que se puede usar. Es una de las poquísimas propiedades modernas
   que entiende: sigue sin haber flex, ni grid, ni degradados.
   El ancho es 224,6 y no 226 porque el borde SUMA: 224,6 más las dos
   líneas de 0,7 dan los 226 que van de margen a margen. Y el relleno
   bajó a 0,5 por lo mismo, que el borde también suma al alto y el
   encabezado no tenía de dónde sacarlo.
   EL TEXTO VA CON SERIFAS, también como la credencial de referencia, y
   es lo único de la tarjeta que no usa la DejaVu Sans. DejaVu Serif es
   la única serif que DomPDF trae con los acentos completos — con Times
   el «Í» de «SECRETARÍA» es una apuesta según la codificación.
   Ojo: la serif es un 5% más ancha que la sans al mismo cuerpo. A 5,5 pt
   la línea larga mide 169,5 de los 235 útiles, así que entra; el tope
   real es 7,63.
   OJO: EL RELLENO SUMA AL ALTO DEL ENCABEZADO, que es la medida más
   ajustada de la tarjeta. Los 2 pt de relleno se compensaron sacándole
   la separación que tenía «SEDAG - BENI» y subiendo el lockup medio
   punto; sin eso, la banda se comía el título.

### VERDE MUY CLARO Y SEMITRANSPARENTE, no blanco: el sello de agua

   ESTE ES EL ÚNICO `rgba()` DE LA TARJETA, y va contra lo que el
   proyecto tiene anotado —«el soporte de rgba en DomPDF depende de
   la versión»—. Se comprobó midiendo el PDF en vez de suponer: con
   la 3.1.6 que corre acá el relleno sale en 173,194,129, que es la
   mezcla real contra el verde; si saliera opaco daría 232,238,210.
   Por eso mismo queda ATADO A LA VERSIÓN. En un DomPDF viejo esto
   se dibuja opaco —no revienta, simplemente pierde la
   transparencia—, y el reemplazo es un sólido ya mezclado, como el
   que usa el resto del carnet.
   El 0,65 no es al gusto: es lo más transparente que aguanta el
   texto negro encima. A 0,55 el engranaje del sello se le mete por
   detrás a «SEDAG - BENI» y compite con la lectura.

### EL TÍTULO, ROJO PERFILADO DE DORADO

   En el plástico las letras van rojas con un contorno dorado. DomPDF no
   tiene `-webkit-text-stroke` ni `text-shadow`, así que el contorno se
   hace a mano: el mismo texto se dibuja CINCO veces —cuatro en dorado,
   corridas medio punto hacia cada esquina, y la quinta en rojo encima—.
   Parece un truco y lo es, pero es el único que sale igual en todas las
   versiones de DomPDF. Con `opacity` o filtros la tarjeta salía distinta
   según el servidor.
   AHORA DICE «CÉDULA DE PESCADOR», con el rubro adentro.
   Decía «CÉDULA» a secas, y el motivo se dio vuelta con el cambio de
   modelo: el carnet era UNO para todas las actividades de una persona,
   así que nombrar una en el título habría dicho algo que el documento
   no era. Hoy el carnet es de UN rubro que no cambia nunca, y el título
   es lo que se lee de lejos —antes que cualquier renglón—.
   El texto y su clase de tamaño los arma
   CarnetImpresionController::titulo(); sin rubro cargado vuelve a
   «CÉDULA» a secas.

### MÁS CHICO QUE EL TÍTULO DEL PLÁSTICO DE PAPEL, a pedido, y en

   ACÁ SE FRENA. La palabra tiene que seguir siendo lo más grande de
   la carilla: es lo que hace que la credencial se reconozca de lejos
   —el inspector la identifica por su forma antes de leer nada—, y por
   debajo de este cuerpo deja de pesar más que el encabezado.
   El corrimiento del contorno y el interletrado bajaron en la misma
   proporción: el contorno es un ~4,5% del cuerpo y el título entero
   tiene que escalar junto o se deforma.

### EL TÍTULO LARGO — para un rubro que no entra a cuerpo pleno

   Desde que el título lleva el rubro adentro, su largo lo decide el
   catálogo. «CÉDULA DE COMERCIALIZADOR» entra holgado; un rubro de
   más de treinta caracteres se pasaría de los 243 pt de la tarjeta.
   BAJAN LAS TRES MEDIDAS JUNTAS, y eso es lo importante: el cuerpo,
   el interletrado y el corrimiento del contorno. Achicar solo la
   letra dejaría un borde de 0,45 pt sobre un cuerpo de 7,6 — un 6%
   pasa a ser un 9% y el contorno engorda la letra hasta cerrarle los
   huecos, que es justo lo que el perfilado viene a evitar.
   Quién la pone: CarnetImpresionController::titulo().

### LA CÉDULA, EN SU PROPIA TIRA BLANCA

   Va DEBAJO de la foto, a pedido. En el plástico de papel iba arriba,
   sobre el borde.
   Y va sobre tira blanca con letra negra, como los demás valores: es un
   DATO de la persona, no un rótulo, y era el único que quedaba suelto
   sobre el verde. Suelto obligaba además a perfilarlo para que se leyera
   sobre el sello; dentro de su caja, el problema no existe.
   EL ANCHO ESTÁ ATADO AL DE LA FOTO, no elegido: 43,6 de caja más 4 de
   relleno son los 47,6 pt que mide el recuadro de la foto con su borde,
   así que los dos cierran contra la misma vertical. Y TIENE que estar
   atado: la columna de datos arranca en 60 pt, y la caja anterior —80 pt
   de ancho desde el margen— llegaba hasta 88,5. Mientras el bloque era
   texto suelto sobre verde eso no se notaba porque la cédula es corta;
   con fondo blanco, la caja se habría metido por debajo del renglón de
   PROVINCIA.

### EL RENGLÓN PARTIDO EN TRES — REGISTRO + GESTIÓN + CUPO

   Solo lo usa el último renglón, y solo cuando la actividad lleva
   cupo. Sin cupo el renglón vuelve al reparto de dos de arriba.
   El reparto, medido sobre los 176 pt de la tira de datos:
   rótulo REGISTRO   0 -> 44      valor  47,5 -> 75,5
   rótulo GESTIÓN   78 -> 108     valor 111,5 -> 134,5
   valor  CUPO                          137   -> 176,5
   LA CAJA DEL RÓTULO «GESTIÓN» ES DE 30 pt Y NO DE 27, y costó una
   vuelta: con 27 la palabra se montaba sobre la tira del año y en el
   PDF salía «GESTIÓN2026» pegado. El cálculo de encogido de `texto()`
   protege a los VALORES; los rótulos son constantes y nadie los mide,
   así que uno largo se desborda en silencio. Es la misma trampa que ya
   había aparecido con «PROVINCIA».
   EL CUPO NO LLEVA RÓTULO —«800 KG» se lee solo— y por eso se queda
   con la tira más ancha de las tres: es el único que puede crecer, con
   un cupo de cinco dígitos y separador de miles.
   OJO CON EL RELLENO: `.campo .valor` lleva `padding: 1pt 2pt`, y en
   CSS eso SUMA al ancho declarado. Los anchos ÚTILES que salen de acá
   —24-4, 20-4 y 37,5-4— son los que el controlador tiene escritos en
   ANCHO_TRIPLE_*. Si se toca una medida hay que tocar la otra, o el
   cálculo de encogido mide contra una caja que no existe.

### El recuadro va SIEMPRE, con foto o sin ella. Es lo que hacía la unidad

   `overflow: hidden` porque DomPDF NO TIENE `object-fit`: un retrato
   metido a la fuerza en el recuadro saldría aplastado, así que la imagen
   se dibuja a su proporción real desbordando, y acá se la recorta. El
   estilo lo calcula CarnetImpresionController::fotoEmbebida().

### LA FOTO ARRANCA MÁS ABAJO QUE LOS RENGLONES, a pedido.

   Estaba en 66, la misma altura que NOMBRE, y la columna izquierda
   terminaba 23 pt antes que la derecha: la foto y la cédula cerraban en
   122,5 y el último renglón en 145,5. Bajándola 10 pt las dos columnas
   quedan parejas a la vista.
   EL RECUADRO ES CUADRADO Y TIENE QUE SEGUIR SIÉNDOLO: 46 × 46, más
   0,8 de borde por lado. En DomPDF el borde SUMA al ancho declarado
   —como el padding— así que ocupa 47,6 × 47,6: sigue cuadrado porque
   los dos lados crecen igual. Tocar uno solo lo deforma.
   La foto de adentro NO se estira para llenarlo: se dibuja a su
   proporción real desbordando el recuadro y este la recorta con
   `overflow: hidden`. Ver fotoEmbebida(), que calcula el corrimiento.

### LOS SEIS RENGLONES

   Cada uno es un bloque plantado en su coordenada, y no filas de una
   tabla, por lo mismo que todo lo demás: una dirección de dos líneas no
   puede empujar al renglón de abajo.
   El valor va sobre su TIRA CLARA, como en el plástico —ahí eran las
   líneas blancas sobre las que se escribía a máquina—. El tono es un
   color sólido y no `rgba(255,255,255,.85)`: el soporte de rgba en
   DomPDF depende de la versión.

### LOS RÓTULOS: BLANCOS, SIN CONTORNO Y MÁS GRUESOS

   El blanco es lo que separa el andamio del dato. «NOMBRE» no es
   información: es el cartelito que dice qué se está leyendo. En el verde
   oscuro del valor pesaba lo mismo que el nombre de la persona y el ojo
   tenía que descartarlo en cada renglón.
   TUVIERON CONTORNO Y SE LES SACÓ. Se les había puesto uno oscuro de
   0,2 pt porque a 4,6 el blanco se desdibujaba donde pasa el sello de
   agua. Lo que resolvió el problema de verdad fue AGRANDARLOS: sin el
   borde se leen francamente blancos en vez de blancos-con-suciedad.
   Menos capas y mejor resultado. El `:` va igual: también es andamio.
   EL BLANCO ES `#ffffff` PURO, Y AUN ASÍ PUEDE VERSE GRIS. No es un
   problema de color —medido sobre el PDF, el núcleo del glifo da 255—
   sino de GROSOR: a un cuerpo chico el trazo es tan fino que el ojo lo
   promedia con el verde de atrás. La única palanca real es el cuerpo, y
   por eso este renglón se fue agrandando: 4,6 -> 5,2 -> 6,1.
   EL LÍMITE LO MARCA «ASOCIACIÓN», que es el rótulo más largo: a 6,1 pt
   mide 42,8 de los 44 de su caja, y su tope es 6,27. Para pasar de ahí
   habría que sacarle ancho a la tira del valor, que es lo que no
   conviene. Medido con la DejaVu Sans Bold que embebe DomPDF.

### EL CUERPO LO MANDA EL CONTROLADOR, renglón por renglón: un valor que

   `overflow: hidden` queda como último tope para lo que ni apretado
   entre: DomPDF no tiene `text-overflow`.

### LA TIRA VA BLANCA Y LA LETRA NEGRA FINA, como una cédula de identidad.

   Blanco puro y no el crema `#f6faee` de antes: sobre el verde oscuro,
   el crema se leía como un papel viejo, y lo que la tarjeta imita es un
   documento de identidad, donde el dato va sobre blanco.
   Y la letra en NEGRO REGULAR, no en verde oscuro negrita. La negrita
   tenía sentido cuando la tira era clara sobre un verde claro y todo
   competía; sobre blanco puro no hace falta gritar, y a 4,6 pt la
   negrita empasta las letras entre sí. El negro fino es el de cualquier
   cédula, y es el que aguanta mejor la impresora de credenciales.

### EL RELLENO SUMA AL ANCHO, y acá estaba mal declarado: la tira tiene

   Se veía poco porque las seis tiras desbordaban lo mismo. Saltó al
   agrandar los rótulos: «GESTIÓN» se subía encima de la tira del
   registro, que terminaba cuatro puntos más allá de donde la cuenta
   decía.

### EL SEGUNDO PAR DEL RENGLÓN — hoy solo GESTIÓN, junto al REGISTRO

   Van juntos porque se leen juntos: el registro identifica el carnet y
   la gestión dice de qué año es, y un mismo número se repite cada año
   —el registro arranca de nuevo con cada gestión—. Separados, el número
   solo no alcanza para saber de qué carnet se está hablando.
   Comparten renglón y no ocupan uno propio porque en una CR80 no entran
   siete líneas sueltas sin apretar todo lo demás. El reparto: el
   registro son seis dígitos fijos y no necesita la tira entera, así que
   le cede la mitad derecha al año.
   Las coordenadas cierran contra los 176 pt de la columna, contando el
   relleno de cada tira:
   valor registro  47,5 -> 93,5   rótulo 96 -> 128   valor 131,5 -> 176,5

### Los renglones. El `top` se calcula acá y no en la hoja de estilos porque

   SIEMPRE SON SEIS, para cualquier actividad: el rubro lo dice el título
   y el cupo va en la columna de la foto. Por eso el salto volvió a ser
   fijo — con siete renglones había que apretarlo a 12 pt.

## `resources/views/documentos/partes/texto-perfilado.blade.php`

### UN TEXTO CON CONTORNO, PARA DomPDF

   DomPDF NO TIENE `-webkit-text-stroke` NI `text-shadow`. Las dos son la forma
   normal de perfilar un texto en un navegador, y acá no existe ninguna: lo que
   se escriba con ellas se dibuja sin contorno y sin avisar.
   Así que el contorno se hace a mano. El mismo texto se dibuja CINCO veces:
   cuatro copias del color del borde corridas hacia cada esquina, y la quinta
   —la cara— encima, sin corrimiento. El ojo lee el relleno de la última y el
   asomo de las otras cuatro como un perfilado.
   Parece un truco y lo es, pero es el único que sale igual en todas las
   versiones de DomPDF: con `opacity` o con filtros la tarjeta salía distinta
   según el servidor.
   QUÉ PONE ESTA PARTE Y QUÉ PONE QUIEN LA INCLUYE
   Acá está SOLO el andamio: las cinco copias y sus clases. El color, el cuerpo,
   el ancho y cuánto se corren las esquinas los pone la hoja de estilos del
   bloque que la incluye, porque no son los mismos en los dos usos —el título va
   perfilado en dorado a 11 pt y corrido 0,5; los rótulos van perfilados en verde
   oscuro a 4,6 y corridos 0,3—.
   El contenedor tiene que ser `position: absolute` o `relative`: las cinco
   copias se miden contra ÉL, y sin contenedor posicionado aterrizan contra la
   página.
   EL CORRIMIENTO SE ELIGE CONTRA EL CUERPO, NO CONTRA EL GUSTO
   Un contorno de medio punto sobre un texto de 4,6 pt no perfila: engorda la
   letra hasta cerrarle los huecos —la «O» se llena, la «E» se vuelve una
   mancha— y a ese tamaño el rótulo deja de leerse. La proporción que funciona
   es de más o menos un 6% del cuerpo.

## `resources/views/documentos/recibo-oficial.blade.php`

### RECIBO OFICIAL — calco del talonario verde del SEDAG

   Reproduce el formulario impreso renglón por renglón: el mismo encabezado, los
   mismos campos, las mismas seis casillas de DESCRIPCIÓN, las dos firmas, el pie
   de tres copias y la nota legal. Quien recibe el papel está acostumbrado a ese
   formato; una versión «mejorada» se lee como si fuera otro documento.
   POR QUÉ TODO ESTÁ POSICIONADO EN ABSOLUTO
   Porque esto no es una página web que se acomoda al ancho del que mira: es un
   papel de medida fija que tiene que salir SIEMPRE igual, y cada bloque va donde
   está en el talonario. Con el flujo normal del documento, un nombre más largo
   que otro corre todo lo que viene abajo y dos recibos salen distintos.
   Además lo dibuja DomPDF, que no es un navegador: no entiende flexbox ni grid
   ni variables CSS. Lo que sí entiende bien es `position: absolute`, tablas y
   bordes. Queda anticuado en el código y exacto en el papel, que es lo que se
   busca acá.
   EL SISTEMA DE COORDENADAS
   La hoja es de 612 x 396 puntos —media carta apaisada, el tamaño del
   talonario— y el marco vive a 10pt de cada borde, o sea 592 x 376 útiles.
   Todos los `top` y `left` de abajo son puntos DENTRO de ese marco.
   El tamaño del papel NO está acá sino en ReciboController: es una decisión de
   impresión, no de diseño. Si la unidad manda a hacer el talonario en otro
   formato se cambia un número allá — pero entonces estas coordenadas hay que
   revisarlas, porque están calculadas para estos 592 x 376.

### EL SELLO DE AGUA

   Va declarado ANTES que el marco y con z-index negativo. DomPDF
   respeta el apilado solo si lo de atrás se declara primero;
   invertirlo tapa el recibo con el sello.
   NO LLEVA `opacity`: el archivo `recibo-sello.png` ya viene atenuado.
   Es a propósito — `opacity` es de lo menos confiable que tiene DomPDF y
   cuando lo ignora el sello sale a pleno color tapando el texto. Ver el
   comentario de ReciboController::imprimir().
   CÓMO SE REGENERA, porque no se toca con CSS. El PNG sale de mezclar
   `public/image/sedag.png` contra BLANCO con un factor:
   255 - factor * (255 - canal)    por cada canal, pixel a pixel
   El factor es la única palanca de visibilidad:
   0,08  el primero. Casi invisible: la tinta más oscura daba
   luminancia 234 sobre 255, un 8% de contraste
   0,25  el actual. La tinta llega a 192 y el sello se lee
   sin tapar el texto negro que le pasa por encima
   Dos cosas que hay que respetar al rehacerlo:
   - GUARDARLO EN PALETA (`imagetruecolortopalette`). En truecolor el
   archivo pasa de 10 KB a 40, y este PNG va EMBEBIDO en base64 en
   cada recibo: el peso se paga en cada impresión.
   - MEDIR EL PDF DESPUÉS. Subir el factor oscurece el fondo por donde
   cruzan «Nombre y Apellido», «La suma de» y «Concepto»; impreso con
   poco tóner, un sello muy marcado se come esos renglones.
   Centrado sobre el marco: (592-190)/2 = 201, más los 10pt del
   margen = 211. Lo mismo en vertical.

### EL ESCUDO CRECIÓ DE 50 A 62 pt, y eso obligó a mover dos cosas más.

   No es un `width` suelto: el encabezado son tres bloques plantados con
   coordenadas fijas y el escudo los toca a los dos.
   - A LA DERECHA está el bloque de la entidad. Arrancaba en 62 pt, que
   es justo donde terminaba el escudo viejo (8 + 54). Con 62 pt de
   escudo la caja llega a 74, así que la entidad se corrió allí y se le
   descontó el ancho para no pasarse del borde: 530 - 74 = 456.
   - ABAJO está el título «RECIBO OFICIAL», que empezaba en 60 pt cuando
   el escudo terminaba en 56. Ahora el escudo baja hasta 65, así que el
   título se corrió a 70 pt. Tiene lugar de sobra: el renglón de «Lugar
   y Fecha» recién empieza en 98.
   Si se vuelve a tocar el tamaño, hay que rehacer esas dos cuentas.

### EL CUADRO DE IMPORTES — UNO SOLO, QUE CRECE HACIA ABAJO

   Un renglón por cobro, en el orden en que entraron, y el TOTAL al
   pie. Con dos depósitos el cuadro se estira; con uno solo se
   completa con renglones en blanco para conservar el alto del
   talonario. Ver ReciboController::RENGLONES_MINIMOS.
   DOS COLUMNAS: la descripción del cobro y el MONTO, entero en una
   sola celda. La fila del pie repite esa división: TOTAL a la
   izquierda, la cifra a la derecha.
   LA CIFRA NO SE PARTE. Se probaron las dos formas anteriores y las
   dos se leían mal: un dígito por casillero —«1|2|0|00»— parecía
   12000, y separar bolivianos de centavos —«120|00»— obligaba al ojo
   a juntar dos cifras para entender una. El formato —coma decimal y
   punto de miles— lo pone ReciboController::imprimir() al armar los
   renglones.

### derecha, más o menos al nivel del final de la lista de DESCRIPCIÓN.

   CORRIDAS 20 pt A LA IZQUIERDA respecto del cuadro de importes, a pedido:
   apoyadas en 378 quedaban demasiado pegadas al borde derecho.
   358 ES EL PISO. La lista de DESCRIPCIÓN, a esta misma altura, ocupa de
   14 a 344; con 14 pt de aire entre las dos. Correrlas más las mete abajo

## `app/Enums/EstadoAprovechamiento.php`

### Los depósitos están cargados y cubren el monto; falta que alguien firme.

   NO autoriza a pescar todavía, y esa es la razón de que el estado exista:
   entre «la plata entró» y «la unidad lo aprobó» hay un control, y sin un
   estado propio ese control no tendría dónde ocurrir.

### ¿Según el estado, todavía se le pueden colgar faenas?

   PENDIENTE no habilita, y no es un descuido: lo que autoriza a pescar es
   la concesión PAGADA. Un cupo cargado y sin cobrar es un papel a medio
   llenar, y emitir faenas contra él dejaría al pescador trabajando sobre
   una autorización que la unidad todavía no entregó.

### ¿Se pueden CARGAR depósitos contra él?

   En pendiente sí: es justamente lo que hay que hacer. En revisión no —el
   monto ya está cubierto y el expediente presentado— y después tampoco:
   un cupo aprobado está pagado por definición, y la plata que entre de más
   no es de este trámite.

### ¿Se puede mandar a que alguien lo firme?

   El estado es solo una de las dos condiciones. La otra —que los depósitos
   cubran el monto— la mira el servicio, porque depende de la suma de los
   pagos y no del estado. Ver AprovechamientoPesq::puedeEnviarseARevision().

### ¿Se puede borrar la fila entera?

   Mismo criterio que la edición, y con más razón: un cupo con plata encima
   no se elimina —se arregla por caja—, porque borrarlo dejaría pagos
   colgando de algo que ya no existe.

## `app/Enums/EstadoCarnet.php`

### Dado de baja por decisión de la unidad, antes de su vencimiento.

   El documento sigue existiendo y su historial queda legible —un inspector
   necesita saber que la persona estuvo autorizada hasta tal fecha— pero hoy
   no habilita a trabajar ni a emitir faenas o guías.

## `app/Enums/EstadoFaena.php`

### ¿Sus kilos pesan contra el cupo de la bolsa madre?

   Activo SÍ, aunque todavía no se haya descargado nada: el volumen está
   comprometido y comprometerlo dos veces es justamente lo que el cupo viene
   a impedir. Vencido NO: la salida no ocurrió y el volumen vuelve.

## `app/Enums/ModalidadAprovechamiento.php`

### ESPECIES ESPECIALES — el cupo de gran porte, con tasación fija.

   Paiche y lo que la resolución sume después. Es una autorización específica
   sobre la cuota de la especie, con TASACIÓN FIJA: el valor no sale de una
   progresión por kilos como en los tramos menores, lo fija la resolución
   para esa especie.

### Nombre del color del badge.

   Devuelve el NOMBRE y no las clases armadas con texto: Tailwind solo
   incluye en el CSS final las que puede leer literalmente. Un color nuevo
   acá va también al mapa de resources/js/components/ui/badge.tsx.

## `app/Enums/RolSistema.php`

### CORREGIR EL BORRADOR TAMBIÉN ES DE VENTANILLA.

   Solo corre mientras el cupo está PENDIENTE DE PAGO: es arreglar
   una carga equivocada con el pescador todavía enfrente, no cambiar
   una autorización entregada. En cuanto entra plata el permiso deja
   de alcanzar, porque el estado ya no lo permite.

### Catálogo completo de permisos del sistema.

   Sale del rol administrador porque es el que los tiene todos: escribir la
   lista otra vez acá sería una segunda copia que puede quedar corta.

## `app/Enums/TipoActor.php`

### ¿Esta credencial lleva colgada una bolsa madre de aprovechamiento?

   Se pregunta acá y NUNCA con un match sobre el nombre del tipo de carnet:
   `tipos_carnet` es un catálogo que edita la unidad desde el panel y el
   mismo documento figura como «Carnet de Pescador» o como «Pescador
   Artesanal» según quién lo cargó.

## `app/Exceptions/CarnetInvalidoException.php`

### Se eligió una asociación o un tipo que ya no se puede usar.

   Puede pasar entre que el operador abre el formulario y aprieta guardar:
   la lista se armó con lo vigente en ese momento y en el medio alguien lo
   desactivó desde el catálogo.

### No se revoca dos veces.

   Y tampoco se «desrevoca»: la revocación es definitiva. Si la persona
   vuelve a estar en regla, lo que corresponde es emitirle un carnet nuevo,
   con su propio código — el plástico viejo puede estar circulando.

## `app/Exceptions/CobroInvalidoException.php`

### No se puede validar ni observar este depósito.

   Son dos motivos distintos y el mensaje los separa, porque lo que hay que
   hacer después no es lo mismo: si el trámite no está en revisión hay que
   presentarlo o dejar de discutir una firma puesta; si el depósito ya está
   observado, lo que sigue es CORREGIRLO.

## `app/Exceptions/CupoInvalidoException.php`

### Se eligió un tramo de la escala que ya no está vigente.

   Puede pasar entre que el operador abre el formulario y aprieta guardar:
   la lista se armó con los tramos de ese momento y en el medio alguien
   derogó uno desde el catálogo.

### Se quiso presentar o aprobar un cupo con saldo sin cubrir.

   Dice CUÁNTO falta y no solo «falta plata», porque es el número con el que
   el operador decide qué hacer: cargar otro depósito, o revisar si el que
   cargó salió por menos.

### Se quiso firmar con boletas sin controlar.

   Dice CUÁNTAS quedan, que es lo que el revisor necesita para saber si le
   falta mirar una o diez. El detalle —cuál y por qué— está en la tarjeta de
   pagos de la ficha, que es donde se resuelve.

### No se amplía un cupo que ya no corre.

   Sumarle kilos a un cupo vencido daría volumen que no se puede usar —las
   faenas miran la fecha— así que sería puro ruido en la ficha. Lo que
   corresponde es otorgar el de la gestión nueva.

## `app/Exceptions/PermisoOperativoException.php`

### La credencial no habilita ESTE papel.

   Quién puede emitir qué lo dice `TipoActor`, NUNCA el nombre del tipo de
   carnet: `tipos_carnet` es un catálogo que edita la unidad y el mismo
   documento figura como «Carnet de Pescador» o «Pescador Artesanal» según
   quién lo cargó.

### El cupo está presentado y esperando una firma.

   Mensaje propio y no el de «pendiente de pago»: acá la plata YA entró, y
   decirle al operador que cobre lo mandaría a buscar un depósito que no
   existe. Lo que falta es una firma, y eso no se resuelve en la ventanilla.

### La faena pedida no entra en lo que queda del cupo.

   Se dicen los DOS números y no solo «no alcanza», porque lo que el
   operador necesita decidir enfrente del pescador es por cuánto sí puede
   emitirla.

### El número del talonario ya está usado.

   No se ofrece «usar el siguiente» automáticamente a propósito: el número
   sale de un papel que el operador tiene en la mano, y si no coincide con
   lo que el sistema propone hay algo mal que conviene mirar.

### Solo se completa una faena que está EN CURSO.

   Completar es registrar que el pescador volvió y descargó. Sobre una ya
   completada no hay nada que registrar; sobre una VENCIDA tampoco, y ahí el
   matiz importa: al vencer, la faena liberó su volumen, así que completarla
   lo volvería a descontar de un cupo que ya se repuso.

### No se anula dos veces, y no se desanula.

   Es específico de las guías y no genérico a propósito: las faenas NO se
   anulan —`EstadoFaena` no tiene ese estado— así que un método que dijera
   «{X} ya está anulado» tendría que resolver el género del sujeto para un
   solo caso. Escrito derecho, se lee derecho.

### Solo se cierra una guía que está EN CURSO.

   Cerrar es registrar que la carga llegó. Sobre una anulada no hay nada que
   cerrar —ese papel no amparó ningún traslado— y sobre una ya cerrada
   tampoco.

## `app/Http/Controllers/Panel/AprovechamientoController.php`

### EL MODO SE MANDA A LA PANTALLA, y no es un detalle informativo.

   En modo flexible las faenas se emiten por encima del cupo, así
   que un listado que mostrara los saldos sin decir en qué modo está
   el sistema haría leer «0 kg» como un bloqueo que no existe.

### FORMULARIO — GET /panel/aprovechamientos/crear

   Acepta `?beneficiario=7` para llegar desde la ficha de la persona con el
   buscador ya resuelto: quien viene de ahí ya eligió a quién, y volver a
   pedírselo es hacerle repetir un paso que acaba de dar.

### LAS TRES DEL CIRCUITO DE REVISIÓN, resueltas en el servidor.

   `puede_enviarse` no es «el estado es pendiente»: es eso Y que los
   depósitos cubran el monto. Deducirlo en React sería una segunda
   copia de la regla, y encima con el saldo que la pantalla conoce,
   que puede estar viejo.

## `app/Http/Controllers/Panel/BeneficiarioController.php`

### GUARDAR EL ALTA — POST /panel/beneficiarios

   Fíjate en el tipo del parámetro: GuardarBeneficiarioRequest, no Request.
   Con eso Laravel valida ANTES de entrar acá; si la validación falla, este
   método nunca llega a ejecutarse.

### FICHA — GET /panel/beneficiarios/{beneficiario}

   El parámetro se declara como Beneficiario y Laravel busca el registro por
   id automáticamente. Si no existe, responde 404 sin ejecutar el método. Eso
   se llama «route model binding».

### Guarda la foto y devuelve su ruta.

   Todo archivo que sube al sistema pasa por StorageController, que es el
   único que decide en qué disco se escribe. Para que la imagen sea visible
   desde el navegador tiene que existir el enlace simbólico que crea
   `php artisan storage:link`.

## `app/Http/Controllers/Panel/CajaController.php`

### UNA RELACIÓN POLIMÓRFICA NO SE PRECARGA CON `with('pagable.x')`.

   Eloquent no sabe qué es `pagable` hasta que lee la fila, así que
   lo escrito así se IGNORA en silencio y el N+1 sigue ahí. Va con
   morphWith, declarando qué traer para cada tipo.

### EL ARQUEO DEL DÍA, siempre del día de HOY y no del rango filtrado.

   Es lo que se cuadra contra el extracto del banco antes de cerrar,
   y esa pregunta no cambia porque alguien esté mirando marzo. Un
   total que siguiera al filtro invitaría a cuadrar la caja contra el
   número equivocado.

## `app/Http/Controllers/Panel/CarnetController.php`

### EL CUPO VIGENTE DE ESA PERSONA, si la hay.

   La pantalla lo necesita para avisar ANTES de guardar que un carnet
   de pescador sin cupo va a ser rechazado. El servidor lo comprueba
   igual dentro de la transacción; esto evita el viaje en falso.

## `app/Http/Controllers/Panel/CarnetImpresionController.php`

### Hasta donde se puede achicar un texto que no entra, en fraccion de su

   Por debajo del 70% deja de leerse en una tarjeta de 85 mm que alguien mira
   en un control, y un apellido que no se puede leer es lo mismo que no
   imprimirlo: ahi el texto pasa a dos lineas en vez de seguir encogiendo.

### LA ACTIVIDAD SALE DEL ENUM Y NO DEL NOMBRE DEL TIPO DE CARNET.

   `tipos_carnet` es un catálogo que la unidad edita: el mismo documento
   figura como «Carnet de Pescador» o «Pescador Artesanal» según quién lo
   cargó, y el título impreso no puede depender de eso. `tipo_actor` es
   la regla, y no cambia.

## `app/Http/Controllers/Panel/DashboardController.php`

### Con cuántos días de anticipación se avisa que algo está por caducar.

   Vive acá y no repartido por los métodos porque el número tiene que decir
   lo MISMO en el conteo y en el texto que lo explica: con dos constantes,
   alcanza con cambiar una para que el tablero diga «12 por vencer en los
   próximos 30 días» contando en realidad 45.

### CUÁNTA GENTE ESTÁ HABILITADA HOY.

   `vigentes()` mira el estado Y la fecha, y esa segunda mitad no es
   de más: `vencido` lo escribe un comando que corre una vez al día,
   así que contar solo por estado mostraría como habilitada a gente
   cuyo carnet venció anoche.

### LA RECAUDACIÓN SALE DE `created_at`, NO DE UNA COLUMNA DE FECHA.

   `pagos` no tiene `fecha_pago`: un abono se registra cuando entra
   la plata, así que el momento de la fila ES el momento del cobro.
   Una columna aparte solo agregaría la posibilidad de que las dos
   se contradigan.

## `app/Http/Controllers/Panel/FaenaController.php`

### EL CUPO DEL QUE SALIERON LOS KILOS.

   Va en la ficha porque es la pregunta que sigue: «¿le queda
   para otra salida?». Sin esto habría que ir al módulo de cupos
   a buscarlo.

## `app/Http/Controllers/Panel/GuiaController.php`

### Son MOMENTOS, no días: van con toIso8601String().

   Los cinco días se cuentan desde la HORA de emisión —una guía de
   las 18:00 del lunes vence a las 18:00 del sábado— así que mandarlas
   como día perdería justamente el dato que decide la vigencia.

## `app/Http/Controllers/Panel/PagoController.php`

### CORREGIR — POST /panel/pagos/{pago}/corregir

   POST y no PATCH porque puede traer un ARCHIVO: un multipart no viaja en un
   PATCH. Y se sube ANTES de la transacción, que no deshace escrituras en
   disco; el `catch` lo borra. La boleta vieja la borra el servicio.

## `app/Http/Controllers/Panel/ReciboController.php`

### Cuántos renglones tiene el cuadro de importes como mínimo.

   El talonario de papel trae tres rayas impresas: con un solo cobro, el
   cuadro quedaría alto y vacío, y con menos de tres el recibo dejaría de
   parecerse al papel que la gente conoce.

### Qué trámite concreto pagó este abono.

   El `match` va sobre la CLASE y no sobre el texto de `pagable_type`: es el
   mismo dato, pero así el analizador avisa cuando se agrega un cobrable y
   este método se olvida.

### IMPRIMIR — GET /panel/recibos/{recibo}/imprimir

   Dibuja el talonario verde del SEDAG en media carta apaisada. La maqueta
   está en `views/documentos/recibo-oficial.blade.php` y se adapta con
   App\Support\ReciboImpreso, que expone lo que ese Blade pide sin obligar a
   reescribirlo: es una maqueta de coordenadas fijas, medida contra el papel.

## `app/Http/Controllers/Publico/VerificacionController.php`

### Largo mínimo aceptado antes de ir a la base.

   No es una regla de negocio sino un filtro barato: un código de dos letras
   no puede existir, y cortando acá una URL manipulada ni siquiera llega a
   consultar.

### El código escrito a mano, cuando el QR no se deja escanear.

   Se normaliza ANTES de validar, y eso no es un detalle: el código se
   imprime en grupos de cuatro y la gente lo copia con los espacios. Sin
   normalizar primero, la validación rechazaría lo que el operador ve
   escrito en la tarjeta.

### Busca el carnet por su código.

   La comparación la hace el ÍNDICE ÚNICO de la base, que responde en el
   mismo tiempo encuentre o no. Comparar en PHP obligaría a traer filas y a
   cuidarse del ataque por tiempo —una comparación normal corta en el primer
   carácter distinto—; acá no hay nada que filtrar.

### La cédula va ENMASCARADA: solo los últimos tres dígitos.

   Alcanza para que el inspector confirme contra el documento que la
   persona le está mostrando, y no alcanza para que alguien que
   encuentre un carnet tirado se haga con el número completo.

## `app/Http/Requests/Panel/CerrarGuiaRequest.php`

### Reglas para cerrar una guía.

   El peso es OPCIONAL: lo declarado al salir es lo que dijo la balanza del
   origen, y al llegar se vuelve a pesar. Casi nunca coincide al kilo, así que
   el campo permite corregirlo — y a diferencia de la faena, acá no hay tope:
   no existe ningún cupo que exceder.

## `app/Http/Requests/Panel/CobrarRequest.php`

### LA BOLETA ES SIEMPRE OBLIGATORIA

   En esta unidad no se cobra en efectivo ni por QR: todo pago es un
   depósito bancario, y sin la boleta lo único que respalda el cobro
   es que alguien lo tipeó — eso no se puede cruzar contra el
   extracto del banco.

### ÚNICO entre los pagos VIVOS. Es lo que impide cargar la misma

   El índice de la base lo vuelve a exigir: dos ventanillas
   simultáneas pasarían esta comprobación las dos.

## `app/Http/Requests/Panel/CorregirPagoRequest.php`

### Reglas para CORREGIR un depósito, con dos diferencias contra la carga:

   1. La boleta se compara contra la tabla IGNORÁNDOSE a sí misma, o guardar
   sin tocar el número chocaría contra su propia fila.
   2. El archivo es OPCIONAL: ya hay una boleta guardada.

## `app/Http/Requests/Panel/EmitirCarnetRequest.php`

### LA ACTIVIDAD ES UN ENUM Y NO UN CATÁLOGO, y por eso se valida

   De ella cuelga lógica —qué puede emitir la credencial, si lleva
   cupo— así que no puede ser una fila editable: cambiarle el nombre
   a un tipo de carnet no debe cambiar lo que ese carnet habilita.

## `app/Http/Requests/Panel/EmitirGuiaRequest.php`

### EL CÓDIGO DEL TALONARIO, único GLOBAL.

   La regla replica el índice único de la base. Que esté duplicada
   acá y en el servicio no es redundancia inútil: esta pinta el
   mensaje bajo el campo, la del servicio cubre la carrera entre dos
   ventanillas, y el índice es la red que garantiza.

### El peso declarado al salir. `gt:0` porque una guía de cero kilos

   Al CERRAR se puede corregir contra la balanza del destino, que es
   donde el número se vuelve real.

### LA MARCA QUE VALE PLATA: con ella el arancel se cobra al 50%.

   Va como booleano y no como un catálogo de «tipo de producto»
   porque la resolución solo distingue dos casos, y un catálogo
   abriría la puerta a que alguien agregue una fila con descuento sin
   que haya resolución detrás.

## `app/Http/Requests/Panel/GuardarAsociacionRequest.php`

### Reglas para crear y editar una asociación.

   Mismo criterio que GuardarBeneficiarioRequest: crear y editar comparten las
   reglas, así que se escriben una sola vez y el día que cambien no hay dos
   lados que sincronizar.

## `app/Http/Requests/Panel/GuardarBeneficiarioRequest.php`

### El nombre, partido como viene en la cédula.

   El segundo nombre y el apellido materno no son obligatorios porque
   mucha gente no los tiene, y exigirlos dejaría a esa gente fuera del
   sistema. El apellido paterno sí: es NOT NULL en la base.

### Normaliza los datos ANTES de validar.

   Ventanilla escribe con espacios de más y en minúsculas; acá se limpia una
   sola vez para que la base guarde siempre el mismo formato. Un espacio al
   final no se ve en pantalla pero viaja a `nombreCompleto` y ensucia el
   nombre impreso en el carnet.

## `app/Http/Requests/Panel/GuardarCategoriaAprovechamientoRequest.php`

### NO lleva `max:7` aunque hoy la escala tenga siete tramos.

   El número de tramos lo fija una resolución y puede cambiar; un
   tope escrito en código convertiría ese cambio en un despliegue,
   que es exactamente lo que esta tabla vino a evitar.

### El TEXTO OFICIAL, y no se deduce de los kilos.

   El tramo más alto dice «1001 kg Hasta 2000 Kg PAICHE», y ese
   «PAICHE» no está en ningún número. El documento impreso tiene que
   decir lo que dice la resolución.

### El control que mira la escala ENTERA, no el tramo suelto.

   Corre después de las reglas de campo —si los kilos ni siquiera son
   números, no tiene sentido buscar solapes— y por eso va en `after()` y no
   dentro de `rules()`.

## `app/Http/Requests/Panel/GuardarTipoCarnetRequest.php`

### Dos tipos no pueden llamarse igual: en un desplegable serían

   Esta tabla NO tiene borrado lógico, así que el unique va simple
   —sin el whereNull de las asociaciones—.

## `app/Http/Requests/Panel/GuardarUsuarioRequest.php`

### Exige el dominio de la Gobernación, si está configurado.

   Vive en su propio método y no incrustado en rules() para que el `if` no
   ensucie el listado de reglas, que se lee de un vistazo.

## `app/Models/AprovechamientoPesq.php`

### Lo que sale este cupo: el valor de la escala con la que se otorgó.

   Exigido por el trait Pagable. Si la categoría no está cargada se consulta
   —no se puede devolver 0 y seguir, porque eso daría un saldo de 0 y el
   sistema creería que el cupo está pagado—.

### ¿Se puede borrar la fila entera?

   Mismo criterio que la edición MÁS las faenas: un cupo sin pagos puede
   igual tener permisos emitidos encima —el talonario de faenas es papel y se
   llena antes de cobrar— y borrarlo dejaría esas salidas sin la bolsa madre
   que las respalda.

### ¿Se le pueden cargar depósitos hoy?

   Solo en pendiente: en revisión el monto ya está cubierto y el expediente
   presentado, y después de aprobado la plata que entre de más no es de este
   trámite.

### ¿Vale HOY?

   Mira el estado Y la fecha. El estado solo no alcanza: `vencido` lo
   escribe un comando que corre una vez al día, así que entre corrida y
   corrida un cupo que venció ayer sigue diciendo «activo» en la base.

## `app/Models/Beneficiario.php`

### La dirección para mostrar la foto. NULL si la ficha no tiene.

   Pasa por Archivos::url porque la columna guarda una ruta cuando el disco
   es local y una dirección completa cuando es s3, y las dos formas pueden
   convivir en la misma tabla.

### Las guías que emitió COMO COMERCIALIZADOR.

   La clave foránea va explícita porque no sigue la convención: la columna
   se llama `beneficiario_com_id` justamente para que nadie la confunda con
   el pescador que extrajo la carga, que es otra persona.

### Su bolsa madre utilizable HOY, o null.

   Es lo que el formulario de faenas necesita: sin ella no hay de dónde
   descontar kilos y no se puede emitir el permiso.
   Mismo cuidado con `relationLoaded()` que arriba, y por el mismo motivo.

## `app/Models/Carnet.php`

### El código en grupos de cuatro: «PES2 6000 0017».

   Se guarda SIN separadores y se muestra con ellos. Un código de catorce
   caracteres seguidos es imposible de dictar por teléfono o de tipear de un
   plástico gastado, y los separadores guardados romperían la búsqueda de
   quien lo escriba sin ellos.

### Lo que sale esta credencial: el precio de su tipo.

   Exigido por el trait Pagable. Si la relación no está cargada la consulta
   sale igual —devolver 0 daría saldo 0 y el sistema creería que está
   pagado—.

### ¿Puede emitir permisos de faena?

   Las tres condiciones son necesarias: que sea de pescador, que el carnet
   valga hoy, y que tenga una bolsa madre con saldo. Sin la tercera se
   emitirían faenas sin cupo del que descontarlas.

## `app/Models/CategoriaAprovechamiento.php`

### Cómo se lee en un desplegable: «3 · 201 kg Hasta 500 Kg — 110,00 Bs».

   Se usa `descripcion_kg` y no los dos decimales, porque el texto oficial
   no siempre es la lectura literal del rango: el tramo más alto dice
   «PAICHE» y eso no está en ningún número.

### ¿Este volumen cae dentro del tramo?

   Los dos extremos entran. Es lo que dice el texto oficial —«1 Kg Hasta 100
   Kg»— y además, con uno de los dos abierto, un cupo de exactamente 100 kg
   no caería en ninguna escala.

### El tramo que corresponde a este volumen, o null si se pasa de la escala.

   Devuelve null en vez de caer al tramo más alto a propósito: un pedido de
   5000 kg cuando la escala llega a 2000 no es «el tramo 7», es un pedido
   que necesita resolución aparte. Silenciarlo cobraría de menos.

## `app/Models/GuiaMovimiento.php`

### El vencimiento que corresponde a una emisión.

   Se calcula y se GUARDA, no se deriva al leer: si la resolución cambia el
   plazo, las guías ya emitidas tienen que seguir venciendo cuando dice el
   papel que va dentro del camión.

### Lo que se cobra por esta guía. Exigido por el trait Pagable.

   La tarifa base sale de configuración y no de una tabla propia: hoy es un
   único valor para todas las guías. El día que se vuelva una escala por
   destino o por volumen, esto pasa a leer una tabla y el resto del circuito
   de cobro no se entera.

## `app/Models/Pago.php`

### La dirección completa de la boleta, o null si no hay.

   La columna guarda una RUTA; quién la convierte en dirección depende del
   disco activo, y eso lo sabe App\Support\Archivos —el mismo que la escribe
   y la borra—. Armada acá a mano, escribir y leer podrían mirar discos
   distintos.

### Cómo se nombra el trámite pagado en el detalle del recibo.

   El `match` va sobre la CLASE y no sobre el texto de `pagable_type`, que
   es el mismo dato pero sin que el analizador pueda avisar cuando se agrega
   un tipo nuevo y este método se olvida.

### Los abonos de un trámite concreto.

   Recibe el modelo y no el par (tipo, id) a mano: escrito a mano, el tipo
   se copia como texto y el día que una clase se renombre o se mueva de
   namespace la consulta deja de encontrar nada, en silencio.

### Los depósitos hechos en una fecha, según lo que dice la BOLETA.

   Es otra pregunta que `delDia()`, que mira `created_at`: un depósito del
   viernes cargado el lunes entra en uno y no en el otro. El primero cuadra
   el trabajo del día; este se cruza contra el extracto del banco.

## `app/Models/PermisoFaena.php`

### VIGENCIA MÁXIMA DE UNA FAENA, en días.

   Está acá y no escrito a mano en el controlador porque es una regla de la
   resolución, no un detalle del formulario: la usan el alta, la validación
   y la vista previa del papel. Escrita en tres lados, cambiarla se hace en
   dos y el tercero sigue emitiendo con el plazo viejo.

### La fecha límite que corresponde a una salida.

   Se CALCULA acá y se GUARDA en la fila, en vez de derivarse al leer: si
   mañana la resolución baja el plazo a quince días, los permisos ya
   emitidos tienen que seguir venciendo cuando dice el papel que el pescador
   tiene en la mano.

## `app/Models/Recibo.php`

### La suma de lo que HOY cuelga de este recibo.

   NO es lo mismo que `monto_total`, y la diferencia es el punto: la columna
   es lo que se IMPRIMIÓ y este método es lo que HAY. Si alguien corrigió un
   abono después de emitir el papel, los dos números se separan — y eso es
   justamente lo que un arqueo tiene que poder detectar.

### Recalcula y guarda el total a partir del detalle.

   Se llama al CERRAR el recibo, antes de imprimirlo — nunca al leerlo. Una
   vez que el papel salió, este método no se vuelve a tocar: para eso está
   `cuadra()`, que informa la diferencia en vez de taparla.

## `app/Models/TipoCarnet.php`

### El precio de HOY, para armar un cobro nuevo.

   No sirve para leer lo que salió un carnet ya emitido: si el arancel
   cambió, esta columna ya dice otra cosa. Lo cobrado de verdad está en
   `pagos`, que no se recalcula nunca.

## `app/Models/User.php`

### Los depósitos que esta persona cargó en ventanilla.

   OJO: apuntaba a `user_id`, UNA COLUMNA QUE NUNCA EXISTIÓ en `pagos`. La
   relación estaba rota desde el primer día y no se notaba porque nadie la
   llamaba —Eloquent no valida el nombre de la columna hasta que se ejecuta
   la consulta—. Se arregló al agregar `registrado_por`.

## `app/Services/CobrarService.php`

### La serie del correlativo de caja.

   Vive acá y no escrita en cada llamada porque `CorrelativoService` entrega
   números POR SERIE: dos cadenas distintas son dos contadores distintos, y
   un error de tipeo abriría una serie paralela que nadie pidió, con su
   propio 0001.

### El total se CONGELA acá, con los abonos que acaban de entrar.

   Recalcularlo al leer haría que el papel entregado cambiara si
   después se corrige un abono, y lo que se imprimió es lo que la
   persona pagó. Ver Recibo::cuadra(), que compara lo impreso con lo
   que hay hoy en vez de taparlo.

### Cómo se nombra un trámite en el recibo y en los mensajes de error.

   El `match` va sobre la CLASE y no sobre el texto de `pagable_type`: es el
   mismo dato, pero así el analizador avisa cuando se agrega un cobrable y
   este método se olvida.

## `app/Services/ControlarPagoService.php`

### Corregir el depósito: única salida de una observación.

   Vuelve SIEMPRE a PENDIENTE, aunque no se cambie nada: el dato se volvió a
   declarar y nadie lo miró desde entonces.

## `app/Services/CorrelativoService.php`

### Entrega números correlativos por serie y gestión: DOC-PESCA-2026-0001.

   El contador se bloquea con SELECT ... FOR UPDATE dentro de una transacción,
   de modo que dos ventanillas cobrando al mismo tiempo nunca reciben el mismo
   número. El contador se reinicia solo al cambiar de año porque la unicidad
   de la fila es (serie, anio).

## `app/Services/EmitirCarnetService.php`

### Los catálogos se releen DENTRO de la transacción.

   No es redundancia con el Request: entre que el operador abrió el
   formulario y apretó guardar pueden pasar minutos, y en el medio alguien
   pudo desactivar la asociación o el tipo desde el catálogo. El Request
   mira el momento del envío; esto, el del guardado.

### Su credencial vigente de ESTA actividad, o null.

   Se consulta siempre contra la base y no se reutiliza ninguna relación
   cargada: corre dentro del candado, y todo el punto es ver lo último
   escrito, incluido lo que otra ventanilla acaba de crear.

## `app/Services/EmitirFaenaService.php`

### Y EL OTRO ESTADO QUE NO HABILITA: presentado y sin firmar.

   Va aparte del anterior porque lo que falta es distinto —ahí plata,
   acá una firma— y mandar al operador a cobrar un cupo ya cubierto
   lo haría buscar un depósito que no existe.

### EL TOPE SOLO SE HACE CUMPLIR EN MODO ESTRICTO.

   La comprobación va acá adentro —con la fila del cupo bloqueada— y
   no en el Request, porque el saldo puede moverlo otra ventanilla en
   el mismo segundo.

### Pone el cupo en `agotado` o lo devuelve a `activo` según su saldo real.

   Vive acá y no en el modelo porque es una ESCRITURA, y el modelo solo
   calcula. Se llama después de cualquier movimiento de kilos: emitir,
   corregir al cerrar, o vencer una faena desde el comando diario.

## `app/Services/EmitirGuiaService.php`

### LA ASOCIACIÓN SE COPIA DEL CARNET, no se pregunta de nuevo.

   Es la que certificó a la persona al emitirle la credencial, y
   preguntarla otra vez abriría la puerta a que una guía diga un
   gremio y el carnet que la respalda diga otro.

## `app/Services/OtorgarCupoService.php`

### EL VOLUMEN SALE DEL TECHO DEL TRAMO.

   La escala dice «201 kg Hasta 500 Kg»: lo que se autoriza es el
   máximo del rango, no un número que el operador elija adentro.
   Dejarlo elegir convertiría la escala en una sugerencia y
   abriría la puerta a cobrar el tramo 3 otorgando el volumen del 5.

### NACE PENDIENTE, y de ahí sale solo al cobrarse.

   Es lo que lo hace corregible: mientras no entró plata, el cupo
   es un borrador que el operador puede arreglar o borrar con el
   pescador todavía enfrente. Lo activa `CobrarService`.

### SE COMPRUEBA CON LA COPIA BLOQUEADA, no con la que llegó.

   Entre que el operador abrió el formulario y apretó guardar, otra
   ventanilla pudo cobrar este mismo cupo. Preguntándole al modelo en
   memoria, la edición pasaría sobre un cupo ya pagado.

### Su bolsa madre utilizable hoy, o null.

   Se consulta SIEMPRE contra la base y no se reutiliza la relación cargada:
   este método corre dentro del candado, y todo el punto es ver lo último que
   hay escrito, incluido lo que otra ventanilla acaba de crear.

## `app/Services/RevisarCupoService.php`

### SE VUELVE A MIRAR EL MONTO, aunque el envío ya lo había mirado.

   No es redundancia: entre el envío y la firma pueden pasar días, y
   en el medio alguien pudo dar de baja un pago. Aprobar un cupo que
   dejó de estar cubierto lo habilitaría para pescar sin la plata.

## `app/Support/Archivos.php`

### El disco donde el sistema guarda y busca los adjuntos.

   Sale de `FILESYSTEM_DISK`, igual que para StorageController. Vive acá —en
   un solo método— para que escribir, leer y borrar no puedan terminar
   mirando discos distintos.

### Borra un archivo guardado.

   Con la ruta —que es lo que hoy se guarda— se borra del disco activo, sea
   local o s3. No hay nada especial que hacer: Flysystem resuelve el prefijo
   del bucket solo, porque el disco lleva `'root' => env('AWS_ROOT')`.

## `app/Support/CodigoQr.php`

### Cuántos píxeles mide cada módulo (cada cuadradito) del código.

   Con 8 px por módulo un QR de versión 3 sale de unos 300 px de lado: más
   que suficiente para dibujarlo a 60 pt en el carnet sin que la impresora
   tenga que inventar nada, y liviano (unos 2 KB en PNG).

### El margen blanco alrededor, en módulos.

   NO ES DECORACIÓN: la norma del QR lo llama «zona tranquila» y pide cuatro
   módulos. Sin ella, el fondo verde del carnet toca los cuadritos del borde
   y muchos lectores no encuentran dónde empieza el código.

### El PNG del QR como data URI, listo para el `src` de un `<img>`.

   Va embebido y no como ruta por lo mismo que las imágenes del recibo:
   DomPDF resolvería una ruta contra el disco con las restricciones de
   `chroot` y en producción terminaría en un recuadro vacío.

## `app/Support/Paginacion.php`

### Los tamaños de página que la pantalla puede pedir.

   El primero es el que se usa cuando no se pide nada, y es también el que
   el selector muestra marcado al entrar.

## `app/Support/ReciboImpreso.php`

### ARMA EL RECIBO IMPRESO A PARTIR DEL MODELO

   Los pagos tienen que venir cargados con su `pagable`, y con `morphWith`:
   una relación polimórfica NO se precarga con `with('pagable.beneficiario')`
   —eso se ignora en silencio— y acá cada renglón necesita saber qué trámite
   pagó.

### Todas las boletas del recibo. El papel es UNO por trámite, así que tiene

   Se corta en seis porque el renglón va de 198 a 226 pt —ahí empieza
   DESCRIPCIÓN— y DomPDF no recorta, desborda.

### El número que va en el recuadro N° del papel: 0016.

   Se imprime SOLO LA PARTE NUMÉRICA de `REC-2026-0016`, porque el recuadro
   del talonario es angosto y porque el prefijo y el año ya están impresos
   alrededor. El número completo sigue en la base y en la pantalla.

### Los tres recuadros DIA | MES | AÑO del encabezado.

   Salen de `created_at` —cuándo se emitió el recibo— y no de hoy: una
   reimpresión de marzo tiene que seguir diciendo marzo.

### Los renglones del cuadro «IMPORTE A PAGAR Bs.».

   Uno por cobro, en el orden en que entraron. Un pescador que pagó en dos
   depósitos ve los dos escritos, igual que en el talonario de papel.

## `app/Support/Sql.php`

### Fragmentos de SQL que cambian entre motores.

   El sistema corre sobre PostgreSQL en producción, pero en desarrollo puede
   usarse SQLite. Todo lo que no sea SQL estándar pasa por acá para que no
   haya consultas que funcionen en un motor y revienten en el otro.

## `app/Traits/Auditable.php`

### Registra en la tabla `auditorias` cada alta, cambio y baja del modelo,

   Los modelos pueden declarar `$noAuditable` para excluir columnas del
   registro (por ejemplo campos calculados o rutas de archivos temporales).

## `app/Traits/Pagable.php`

### Cuánto cuesta este trámite. Lo define cada modelo:

   - Carnet              → el precio de su tipo de carnet
   - AprovechamientoPesq → el valor de la escala con la que se otorgó
   - GuiaMovimiento      → el arancel, con el 50% de descuento si es piscicultura

### Cuánto falta para cubrirlo.

   Se corta en cero: pagar de más NO genera saldo a favor. Si entró dinero
   de más, no es un abono de este trámite y se resuelve por caja — dejarlo
   en negativo lo mostraría como un crédito que el sistema no sabe aplicar.

### ¿Se pueden CONTROLAR sus depósitos? Solo donde hay circuito de revisión;

   `false` acá y no un `method_exists` en quien pregunta: ese truco deja el
   control apagado en silencio si alguien nombra el método distinto.

## `database/factories/BeneficiarioFactory.php`

### La cédula se arma con unique() y no con un número al azar.

   La tabla tiene un índice único parcial sobre `ci`: dos números
   repetidos en una tanda de cuarenta harían fallar el seeder con un
   error de base de datos, y con números al azar de 7 dígitos la
   repetición es más probable de lo que parece.

### Una mujer casada, con el apellido del esposo.

   Existe como estado propio porque el apellido de casada cambia cómo se arma
   `nombreCompleto` —le agrega el «de»— y hay que poder ver esa variante en
   las pantallas sin depender de la suerte.

### Sin segundo nombre ni apellido materno: el caso mínimo.

   Es el que más rompe maquetas —el nombre queda corto y los cálculos de
   encogido del carnet no se disparan— así que conviene tenerlo siempre en
   los datos de prueba en vez de esperar que salga por azar.

## `database/migrations/2026_09_18_100000_create_asociaciones_table.php`

### Asociaciones — el gremio al que pertenece la persona.

   Catálogo: lo edita la unidad desde el panel y NUNCA se borra una fila. Para
   sacar una de circulación se pone `estado = inactivo`.
   Ver docs/MER.md.

### Dos asociaciones no pueden llamarse igual.

   Índice PARCIAL y no `unique()` con `deleted_at` adentro: en SQL
   NULL != NULL, así que ese unique no bloquearía nada. Con el WHERE, el
   nombre queda libre recién al dar la fila de baja.

## `database/migrations/2026_09_18_100100_create_categorias_aprovechamiento_table.php`

### Categorías de aprovechamiento — la ESCALA OFICIAL del SEDAG.

   Los tramos con los que se cobra el cupo de pesca: a tantos kilos autorizados,
   tantos bolivianos. Es una tabla y no un match() en código porque la fija una
   resolución: así actualizarla es una pantalla y no un despliegue.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100200_create_tipos_carnet_table.php`

### Tipos de carnet — el catálogo de credenciales y su arancel.

   `precio_bs` es el precio de HOY, para armar un cobro nuevo. Lo cobrado de
   verdad queda en `pagos` y no se recalcula: un carnet emitido a 80 Bs sigue
   diciendo 80 aunque el arancel suba.
   NO confundir con `carnets.tipo_actor`: eso es la REGLA —qué habilita el
   documento— y vive en un enum. Esto es el CATÁLOGO —cómo se llama y cuánto
   sale— y nunca se decide nada con un match sobre este nombre.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100300_create_beneficiarios_table.php`

### Beneficiarios — la persona, UNA SOLA VEZ.

   Tabla unificada y SIN columna de rol: quien pesca y además comercializa es una
   persona con dos credenciales, no dos fichas. El rol vive en
   `carnets.tipo_actor`, que es del documento.
   De `primerNombre` en adelante van en camelCase, así que en SQL escrito a mano
   hay que entrecomillar: SELECT "primerNombre". Sin comillas, PostgreSQL pasa el
   nombre a minúscula y responde «column "primernombre" does not exist». Eloquent
   entrecomilla solo; el problema aparece con whereRaw / orderByRaw.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100400_create_aprovechamientos_pesq_table.php`

### Aprovechamientos pesqueros — la BOLSA MADRE del pescador.

   El cupo anual en kilos, con fecha. Cada faena le descuenta; cuando el saldo
   llega a cero no se emiten más. Esa resta vive en AprovechamientoPesq::saldoKg()
   y no en ninguna columna.
   PENDIENTE ──[se cobra entero]──▶ ACTIVO ──▶ AGOTADO | VENCIDO
   Cuelga del BENEFICIARIO y no del carnet porque el cupo se define primero: el
   plástico necesita saber qué volumen imprimir. Por eso la referencia va al
   revés, en `carnets.aprovechamiento_id`.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100500_create_carnets_table.php`

### Carnets — la credencial física que se entrega en ventanilla.

   El mismo plástico para las dos actividades; lo que cambia es `tipo_actor`:
   carnet (pescador)        ──< permisos_faena     (una por salida)
   carnet (comercializador) ──< guias_movimiento   (una por traslado)
   Se imprime lo que NO cambia después de salir de la impresora. El ESTADO no se
   imprime: un carnet se revoca después y la tarjeta no se entera.
   Ver docs/MER.md.

### NULLABLE PORQUE SOLO EL PESCADOR LLEVA CUPO, y eso lo dice

   `nullOnDelete` y no restrict: sin el cupo el carnet sigue siendo un
   documento válido, se emitió y se entregó.

## `database/migrations/2026_09_18_100600_create_permisos_faena_table.php`

### Permisos de faena — la autorización de UNA salida de pesca.

   El carnet es la llave anual; con él solo no se sale a trabajar. Cada salida se
   autoriza con una faena: cuántos kilos y hasta cuándo. Vigencia máxima, un mes.
   Apunta a DOS cosas a la vez y hacen falta las dos: `aprovechamiento_id` es de
   dónde salen los kilos, `carnet_id` es quién los extrae. Son la misma persona
   por caminos distintos, y se renuevan en fechas distintas.
   NO se edita ni se borra: se vence o se completa. El número sale de un talonario
   de papel que el pescador se llevó.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100700_create_guias_movimiento_table.php`

### Guías de movimiento — el amparo de UN traslado de producto.

   Lo que la faena es para el pescador, la guía es para el comercializador: el
   carnet habilita el año, la guía habilita el viaje. Vale como máximo 5 días.
   `es_piscicultura` NO es descriptivo: es plata. Marcado, el arancel se cobra al
   50% —el pescado de criadero no sale del río—. El descuento se aplica en UN
   solo lugar: GuiaMovimiento::factorArancel().
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100800_create_recibos_table.php`

### Recibos — la CABECERA del comprobante oficial de caja.

   El papel numerado que la persona se lleva. Agrupa uno o varios `pagos`, que
   pueden ser de trámites distintos:
   recibo 0016 (180 Bs)  ──< pago  80 Bs → carnet
   ──< pago 100 Bs → aprovechamiento
   Es una tabla y no se arma al vuelo porque `numero_recibo` es un CORRELATIVO DE
   CAJA —el dato que no se puede derivar de otras tablas— y porque el comprobante
   tiene que ser INMUTABLE: por eso el nombre, el NIT y el total se COPIAN acá al
   emitir.
   Ver docs/MER.md.

## `database/migrations/2026_09_18_100900_create_pagos_table.php`

### Pagos — el DETALLE de lo que se cobró, depósito por depósito.

   TODO pago es un DEPÓSITO BANCARIO: no hay efectivo ni QR en esta unidad. Por
   eso cada fila lleva sí o sí su número de boleta, su fecha y su archivo.
   Es POLIMÓRFICA porque se cobran tres cosas —carnet, cupo y guía— y las tres se
   pagan igual. Partida en tres tablas, `numero_recibo` dejaría de ser único
   global.
   EL COSTO: se pierde la clave foránea. El motor no puede exigir que
   `pagable_id` exista, porque no sabe en qué tabla buscarlo. La integridad la
   sostienen los RESTRICT de las otras tablas y la aplicación.
   Y no se precarga con `with('pagable.beneficiario')`: eso se IGNORA en silencio
   y el N+1 sigue ahí. Va con `morphWith`.
   Ver docs/MER.md.

### NULLABLE: el depósito nace antes que el recibo. En el

   CASCADE y no RESTRICT: un pago sin recibo no se imprime ni entra
   en ningún arqueo.

### EL NÚMERO DEL DEPÓSITO, ÚNICO GLOBAL.

   Es lo que impide cargar la misma boleta dos veces —contra el mismo
   trámite o contra otro—, que es la forma más fácil de que un cupo
   figure pagado sin que haya entrado la plata. Único global y no por
   trámite: la boleta es una sola en el banco.

## `resources/js/components/panel/aprovechamientos/barra-saldo.tsx`

### Se avisa en ámbar por debajo del 20%.

   Es el umbral en que conviene que el pescador se entere ANTES de salir:
   descubrir que no alcanza cuando vuelve con la bodega llena no sirve de
   nada, porque el producto ya se extrajo.

## `resources/js/components/panel/beneficiarios/formulario-beneficiario.tsx`

### BARRA DE ACCIONES FIJA AL PIE.

   Con el formulario apilado, el botón de guardar quedaba al final de
   tres pantallas de alto: había que bajar hasta el fondo para
   usarlo, y para corregir un campo de arriba había que volver a
   bajar. Fija, está siempre a un clic.

### La fotografía, recortada en círculo como sale en el carnet.

   Se muestra así y no como un rectángulo porque es como se va a imprimir: una
   foto que se ve bien cuadrada puede quedar con la cabeza cortada al recortarla,
   y descubrirlo recién al imprimir el carnet significa volver a llamar a la
   persona.

## `resources/js/components/panel/dashboard/mini-grafico.tsx`

### Convierte la serie a coordenadas.

   El máximo se fuerza a 1 como mínimo para que una serie de puros ceros —un
   feriado, una ventanilla recién instalada— no divida por cero: sale una línea
   apoyada en el piso, que es exactamente lo que pasó.

### La serie como barras sueltas.

   Se usa para los conteos: «entraron 3 trámites» es una cantidad discreta, y
   una línea que sube y baja entre enteros sugiere una continuidad que no
   existe —no hubo medio trámite a las once de la mañana—.

## `resources/js/components/panel/dashboard/tabla-ultimos-carnets.tsx`

### Las últimas diez credenciales emitidas.

   Se muestra el SALDO y no el precio: lo que le interesa a quien mira el
   tablero es qué falta cobrar, no cuánto salía el carnet. Uno cubierto se ve de
   un vistazo porque dice «Pagado» en verde.

## `resources/js/components/panel/dashboard/widget-estadistica.tsx`

### Qué color de los cuatro usa la tarjeta.

   Es un número y no un nombre de color a propósito: los tonos están definidos
   en app.css como una escala institucional, y nombrarlos «azul» o «dorado» acá
   ataría el tablero a un color concreto. El día que la paleta cambie se toca el
   CSS y este archivo no se entera.

## `resources/js/components/panel/layout/barra-lateral.tsx`

### El rótulo de una sección: «Ventanilla», «Registro», «Administración».

   Angosta la barra, el texto no entra, pero el grupo sigue existiendo: se
   reemplaza por una línea divisoria para que los iconos no queden como una
   columna continua sin ninguna agrupación.

### Un renglón del menú. Si el módulo todavía no existe se dibuja apagado.

   LA FILA VA A TODO EL ANCHO y sin esquinas redondeadas, y el ítem activo se
   marca con una barra dorada pegada al borde izquierdo. Es lo que hace que la
   columna se lea como una lista y no como una pila de botones sueltos: la marca
   está siempre en la misma coordenada, así que el ojo la encuentra sin buscar.

## `resources/js/components/publico/ficha-carnet.tsx`

### El sello de estado.

   Los colores NO se arman juntando textos (`bg-${color}-50`): Tailwind solo
   incluye en el CSS final las clases que puede leer literalmente en el código, y
   una clase compuesta nunca llega a la hoja de estilos. El sello saldría sin
   fondo y sin ningún error que lo explique.

## `resources/js/components/publico/hoja-oficial.tsx`

### El membrete: escudo arriba y el nombre de la institución en tres renglones,

   El nombre del sistema va último y en cuerpo chico a propósito. Al ciudadano
   le importa qué institución responde, no cómo se llama el programa.

## `resources/js/components/ui/confirmar-accion.tsx`

### useEffect ejecuta código "por fuera" del pintado: acá, escuchar la tecla

   El `return` de adentro es la LIMPIEZA: React lo llama al cerrarse la
   ventana. Sin eso, cada apertura dejaría un listener más pegado al
   documento y se irían acumulando.

## `resources/js/components/ui/estado-vacio.tsx`

### Lo que se muestra cuando una lista no tiene nada que mostrar.

   Una tabla vacía sin explicación parece un error del sistema. Este bloque
   aclara si no hay datos todavía o si el filtro no encontró nada, y ofrece la
   acción que corresponda.

## `resources/js/components/ui/select.tsx`

### Lista desplegable. Es el <select> de HTML de toda la vida, con los estilos

   No usa librerías externas a propósito: para elegir entre pocas opciones el
   <select> nativo funciona mejor en celular (abre el selector del sistema
   operativo) y ya viene accesible con teclado sin escribir nada.

## `resources/js/components/ui/selector-archivo.tsx`

### LA MINIATURA HAY QUE LIBERARLA A MANO.

   `URL.createObjectURL()` deja el archivo retenido en memoria hasta que
   alguien llame a `revokeObjectURL`. Sin esto, cargar y cambiar adjuntos
   varias veces en la misma pantalla va dejando copias sin liberar.

## `resources/js/hooks/use-archivos.ts`

### Devuelve el mensaje de error, o NULL si el archivo sirve.

   Se comprueban las dos cosas que el servidor va a comprobar: el tipo y el
   peso. El tipo también, porque el atributo `accept` del input es una
   sugerencia —el usuario puede elegir «Todos los archivos» en el diálogo
   del sistema y mandar lo que quiera—.

## `resources/js/layouts/layout-panel.tsx`

### Lee la preferencia guardada del menú.

   Va envuelto en try/catch porque `localStorage` LANZA —no devuelve null— en
   una ventana de incógnito o con las cookies bloqueadas por política del
   equipo, que es un escenario real en una oficina pública. Sin el catch, el
   panel entero queda en blanco por recordar el ancho de una barra.

## `resources/js/lib/graficos.ts`

### Ajustes compartidos por todos los gráficos del sistema.

   Están acá y no dentro de cada gráfico para que el día que cambie la paleta
   institucional se toque UN archivo y no seis. Los valores no son colores
   escritos a mano: son variables CSS definidas en resources/css/app.css, así
   que los gráficos cambian solos entre el modo claro y el oscuro.

### Serie de colores para categorías.

   NO se exporta: quien necesite un color pide `colorSerie(i)`, que además
   resuelve qué pasa cuando hay más categorías que colores. Exportada, cada
   gráfico podría indexarla por su cuenta y salirse del arreglo.

## `resources/js/lib/utils.ts`

### Fecha y hora de un INSTANTE.

   Recibe siempre ISO 8601 con zona —lo que devuelve `toIso8601String()` de
   PHP—, así que convertir a horario local es lo correcto y no hace falta el
   cuidado de `fecha()`: ahí el problema es al revés, una cadena sin zona a la
   que no hay que aplicarle ninguna.

### Solo la HORA de un instante: «14:17».

   Aparte de `fechaHora()` porque en una tabla las dos partes van en renglones
   distintos —la fecha arriba, la hora abajo— y juntas en una sola línea obligan
   a ensanchar la columna.

## `resources/js/pages/panel/aprovechamientos/crear.tsx`

### Sugerencias del campo de embarcación, NO una lista cerrada.

   Es lo que más se escribe en ventanilla, puesto ahí para ahorrar tecleo y para
   que el dato salga escrito igual la mayoría de las veces. El operador puede
   escribir cualquier otra cosa: un `datalist` sugiere, no restringe.

## `resources/js/pages/panel/aprovechamientos/index.tsx`

### Lo que dice APROVECHAMIENTO_ESTRICTO en el servidor.

   Va en la pantalla porque cambia qué significa un saldo en cero: con la
   validación encendida es un bloqueo, y con ella apagada es un dato. Sin
   este aviso, el listado se leería mal justo en el caso raro.

## `resources/js/pages/panel/aprovechamientos/ver.tsx`

### LA INTENCIÓN DE ENVIAR, que viaja con los depósitos.

   Se llena al enviar con lo que el botón estaba diciendo, para que el
   servidor haga exactamente lo que el operador leyó. Es una intención y
   no un permiso: si al guardar el saldo no quedó en cero, el servidor
   registra igual y no envía.

### ¿CON ESTO ALCANZA? Es lo que decide qué dice el botón y qué hace.

   Con `puede('aprovechamientos.enviar')` adentro: sin ese permiso el botón
   solo registra, y ofrecerle enviar a quien no puede sería prometer algo que
   el servidor va a ignorar.

### EDITAR Y ELIMINAR SOLO SOBRE EL BORRADOR.

   Las dos banderas llegan resueltas del servidor: no son
   «el estado es pendiente» sino eso Y que no haya entrado
   plata —y para eliminar, además, que no tenga faenas—.
   Deducirlas acá sería una segunda copia de tres reglas.

## `resources/js/pages/panel/beneficiarios/crear.tsx`

### Alta de un beneficiario.

   La pantalla es casi solo el envoltorio: el formulario vive en su propio
   componente porque es el MISMO que usa la edición. Ver
   components/panel/beneficiarios/formulario-beneficiario.tsx.

## `resources/js/pages/panel/beneficiarios/index.tsx`

### El padrón de beneficiarios.

   Los datos llegan como PROPS desde BeneficiarioController::index(). No hay
   fetch() ni axios: el array que ese método pasa a Inertia::render() es
   exactamente este objeto.

## `resources/js/pages/panel/caja/cobrar.tsx`

### LA BOLETA DEL DEPÓSITO, SIEMPRE

   No hay efectivo ni QR: todo pago es un depósito
   bancario. Sin la boleta, lo único que respalda
   el cobro es que alguien lo tipeó, y eso no se
   puede cruzar contra el extracto del banco.

## `resources/js/pages/panel/carnets/ver.tsx`

### Por qué el carnet no habilita, cuando no habilita.

   Se arma de las banderas que ya llegaron resueltas, en orden de precedencia:
   primero lo que bloquea el documento entero y después lo que bloquea solo al
   cupo. Decir «no puede» sin decir por qué manda al operador a adivinar.

## `resources/js/pages/panel/catalogos/asociaciones.tsx`

### El formulario de alta y edición.

   Es el MISMO para los dos casos, igual que el Request del servidor comparte
   las reglas: escrito dos veces, alcanza con tocar uno para que crear y editar
   acepten cosas distintas.

## `resources/js/pages/panel/catalogos/escala.tsx`

### El aviso de rangos sin cubrir.

   Va ARRIBA DE TODO y en ámbar porque es lo único de esta pantalla que puede
   romper el trabajo de mañana, y porque no se deduce mirando la tabla: los
   tramos se ven correctos uno por uno.

## `resources/js/pages/panel/dashboard.tsx`

### El tablero de la gestión en curso.

   Los siete bloques llegan como props desde DashboardController, cada uno
   calculado en su propia consulta. Todo lo que se muestra acá es de solo
   lectura: el tablero informa, no permite hacer nada.

### Pescadores contra comercializadores, entre los carnets vigentes.

   Va como dos cifras y no como gráfico porque son dos valores: una torta con
   dos porciones no agrega nada que el número no diga, y costaría traer
   recharts a la parte de arriba de la pantalla —que es justo lo que la carga
   diferida vino a evitar—.

## `resources/js/pages/panel/faenas/ver.tsx`

### En qué situación está la salida, en una frase.

   Los tres casos se resuelven con banderas que ya llegaron del servidor. El que
   importa es el del medio: una faena que se pasó de fecha y sigue activa es un
   papel que alguien se llevó y del que nadie registró la vuelta — no es una
   previsión, es algo que hay que ir a buscar.

## `resources/js/pages/panel/guias/crear.tsx`

### Los carnets vigentes, con el que no sirve deshabilitado.

   NO SE FILTRAN los que no pueden: aparecen en gris con el motivo al lado. Un
   carnet que desaparece le dice al operador «esta persona no tiene carnet», que
   es falso y lo manda a emitir otro.

## `resources/js/pages/panel/guias/ver.tsx`

### En qué situación está el traslado, en una frase.

   El caso que importa es el del medio: una guía que se pasó de hora y sigue
   activa es un camión en la ruta con un papel que ya no vale. No es una
   previsión — es algo que hay que resolver ahora.

## `resources/js/types/aprovechamientos.ts`

### Los kilos OTORGADOS, copiados del techo del tramo al otorgar.

   Están congelados a propósito: la escala cambia por resolución, y un cupo
   dado en marzo bajo un tramo de 500 kg no puede pasar a valer 800 porque
   alguien editó el catálogo. La única cosa que los mueve es una AMPLIACIÓN.

### Lo que el pescador declaró que navega: «canoa», «peque-peque», «bote»…

   Es el renglón «Tipo de Embarcación» del talonario verde, y va en texto
   libre porque no hay padrón de embarcaciones ni nomenclatura fija. NULL
   cuando no se declaró —que el papel también admite—, y por eso la pantalla
   distingue «no declarada» de una cadena vacía.

### Los kilos que se PASARON del volumen otorgado.

   En modo estricto siempre es 0 —la emisión no deja pasar una faena que no
   entre—, así que solo aparece en pantalla cuando hay algo que mostrar.
   `saldo_kg` no puede decirlo: se corta en cero.

### Si todavía se puede corregir, y si se puede borrar la fila entera.

   NO son «el estado es pendiente»: son eso Y que no haya entrado plata —y
   para eliminar, además, que no tenga faenas emitidas—. Llegan resueltas
   del servidor porque deducirlas acá sería una segunda copia de tres reglas.

### Las tres del circuito de revisión, resueltas en el servidor.

   `puede_enviarse` NO es «el estado es pendiente»: es eso Y que los
   depósitos cubran el monto entero. Deducirlo acá sería una segunda copia
   de la regla, y con un saldo que la pantalla puede tener viejo.

### Cuántas boletas quedan sin dar por buenas —sin validar u observadas—.

   Es lo que frena la aprobación: `puede_aprobarse` NO es «el estado es en
   revisión», es eso Y que este número esté en cero. Sin esa condición,
   validar sería decorativo.

### Un depósito que pagó este cupo, en la ficha.

   Son VARIOS a propósito: un cupo se puede pagar en cuotas, y cada depósito
   bancario llega con su propia boleta. No hay efectivo ni QR, así que las tres
   columnas de la boleta están siempre.

### EL RECIBO DEL TRÁMITE: uno solo, con todos los depósitos adentro.

   No va por depósito, y ese es el punto: el aprovechamiento es un trámite, la
   persona entrega sus boletas —una o cinco— y se lleva UN papel con el total.
   Se emite al enviar a revisión, así que mientras el cupo está pendiente esto
   llega en `null`.

### Si sus kilos pesan contra el saldo.

   Una faena VENCIDA libera su volumen —la salida no ocurrió— así que la
   pantalla la marca aparte: sin eso, la suma de la lista no cuadra con el
   saldo y parece un error del sistema.

### El techo del rango, que es EL VOLUMEN QUE SE VA A OTORGAR.

   La escala dice «201 kg Hasta 500 Kg»: lo que se autoriza es el máximo, no
   un número que el operador elija adentro. Por eso el formulario lo muestra
   al elegir el tramo: las dos consecuencias —kilos y precio— se ven antes
   de guardar, no después.

## `resources/js/types/beneficiarios.ts`

### Tipos del módulo Beneficiarios.

   Cada interfaz describe, campo por campo, lo que arma
   App\Http\Controllers\Panel\BeneficiarioController. Si allá se renombra una
   clave y acá no, el editor lo marca en rojo al instante en vez de descubrirlo
   con una pantalla en blanco.

### Una fila de la tabla del padrón.

   Los campos están agrupados como los pinta la tabla: identificación, la persona
   y sus datos. No es casualidad —el controlador los arma en ese mismo orden—
   para que agregar una columna sea encontrar el grupo al que pertenece.

### Años CUMPLIDOS, calculados por el servidor.

   Llega hecha y no se calcula en el navegador a propósito: con dos
   definiciones de «edad» —la de PHP y la de JavaScript— tarde o temprano
   difieren por un día en los bordes (el cumpleaños de hoy, los bisiestos, la
   zona horaria del teléfono del operador). Ver Beneficiario::edad().

### La ficha completa.

   `nombreCompleto` va en camelCase porque así llega de PHP: el modelo lo manda
   con ese nombre explícito. Ver el comentario de App\Models\Beneficiario sobre
   por qué ese accesor no puede ir en #[Appends].

### Los kilos impresos en el plástico, o null si es comercializador.

   Lo decide `TipoActor::requiereAprovechamiento()` en el servidor, NUNCA un
   `if` sobre el nombre del tipo de carnet: ese nombre es un catálogo que la
   unidad edita, y el mismo documento figura de dos formas distintas según
   quién lo cargó.

### Una BOLSA MADRE de la persona: el cupo anual en kilos.

   Se manda el SALDO y no solo el volumen otorgado porque es lo único
   accionable: «tiene 500 kg» no dice si puede salir a pescar mañana, y «le
   quedan 20» sí.

### Un carnet vigente, tal como lo devuelve el autocompletado.

   LAS DOS BANDERAS LLEGAN CALCULADAS y la pantalla no las deduce. Un `if` sobre
   el tipo en React sería una segunda copia de la regla, y se desincroniza en
   cuanto alguien renombre una fila del catálogo o cambie la vigencia del cupo.
   Ver Carnet::puedeEmitirFaenas() y ::puedeEmitirGuias().

### Kilos que quedan en la bolsa madre. Null si el carnet no lleva cupo.

   Viene con el resultado de la búsqueda y no en un segundo viaje: el
   formulario de faena lo necesita apenas se elige el carnet, y pedirlo
   aparte se nota justo cuando el operador acaba de hacer clic.

### El número de talonario que el sistema PROPONE. Null si no lleva cupo.

   Es una propuesta y no una imposición: el número sale de la hoja que el
   operador tiene en la mano, y si no coincide hay algo que conviene mirar
   antes de seguir, no autocorregir en silencio.

## `resources/js/types/caja.ts`

### NULL mientras el depósito no tiene papel.

   Pasa con el aprovechamiento: sus depósitos se cargan mientras el trámite
   está pendiente y el recibo —uno solo, con el total— se emite recién al
   enviarlo a revisión. La plata ya entró, así que la fila está y suma en el
   arqueo; lo que falta es el comprobante.

### La boleta del banco. Nunca faltan: TODO pago es un depósito bancario —no

   Cuando un mismo depósito cubre varias líneas, la segunda en adelante
   llevan el número con un sufijo («0012345678-2»), porque es único global.

### Una deuda de la persona, lista para cobrar.

   `tipo` es una palabra corta —`carnet`, `cupo`, `guia`— y no un nombre de
   clase: el servidor la traduce con una lista blanca. Mandando la clase
   directo, cualquiera podría escribir otra en el navegador y el sistema crearía
   pagos apuntando a cualquier tabla.

## `resources/js/types/carnets.ts`

### La bolsa madre que respalda el cupo impreso. Null en un comercializador.

   Trae el SALDO y no solo el volumen porque es lo que decide si hoy se le
   puede emitir una faena, que es la pregunta que trae a alguien a esta
   ficha.

### El cupo vigente de la persona elegida, si lo tiene.

   La pantalla lo usa para avisar ANTES de guardar que un carnet de pescador sin
   cupo va a ser rechazado. El servidor lo comprueba igual dentro de la
   transacción; esto evita el viaje en falso.

## `resources/js/types/catalogos.ts`

### El RÉGIMEN del tramo, y de él depende si el cupo se va a poder ampliar.

   Se declara acá, en el catálogo, y no al otorgar: la fija la resolución al
   definir el tramo. Puesta en el otorgamiento, dos cupos del mismo tramo
   podrían terminar con reglas distintas.

### El texto literal de la resolución.

   Se guarda aparte de los kilos porque no siempre es su lectura: el tramo
   más alto dice «1001 kg Hasta 2000 Kg PAICHE», y ese «PAICHE» no está en
   ningún número.

### El arancel de HOY, para armar un cobro nuevo.

   NO sirve para leer lo que salió un carnet ya emitido: lo cobrado de
   verdad está en `pagos` y no se recalcula. Lo que SÍ cambia al subir este
   número es el saldo pendiente de los carnets que todavía no están
   cubiertos.

## `resources/js/types/dashboard.ts`

### Una jornada de la serie de los últimos catorce días.

   Es lo que dibujan las líneas chicas al pie de los indicadores. Cada fila trae
   los DOS valores del día porque las dos series salen del mismo recorrido en
   PHP: separarlas obligaría a mandar el calendario dos veces.

### Pescadores contra comercializadores, entre los carnets vigentes.

   Sale del enum y no de la base para que los dos aparezcan aunque uno esté en
   cero: un valor que no vuelve en la consulta haría que el bloque mienta por
   omisión.

### Lo que está por caducar o ya caducó sin cerrarse.

   Las dos últimas cifras no son avisos de vencimiento sino de TRABAJO SIN
   CERRAR: una faena o una guía que se pasó de fecha y sigue activa es un papel
   que alguien se llevó y del que nadie registró la vuelta.

## `resources/js/types/guias.ts`

### Por cuánto se multiplicó el arancel: 1 o 0.5.

   Viene explícito para que la ficha pueda decir «se cobró al 50%» sin
   recalcularlo. La regla vive en `GuiaMovimiento::factorArancel()`, en un
   solo lado.

## `resources/js/types/index.d.ts`

### Qué archivos acepta el sistema y hasta cuánto pesan.

   Sale de `config/jichi.php`, no de un número escrito en React: es el mismo que
   usan las reglas de validación del servidor. Se lee con el hook
   `useArchivos()`.

### ESTADOS DEL DOMINIO

   Copian exactamente los enums de PHP en app/Enums/. Si allá se agrega un
   estado nuevo, hay que agregarlo acá también: son las dos mitades de la
   misma definición.

### Espejo de App\Enums\EstadoCarnet.

   `vencido` lo escribe un comando que corre una vez al día, así que esta
   columna puede estar desfasada: para saber si un carnet vale HOY, el servidor
   mira además `fecha_vencimiento`. La pantalla recibe la respuesta ya
   calculada y no la vuelve a deducir.

### Espejo de App\Enums\TipoActor.

   Es del DOCUMENTO, no de la persona: quien pesca y además comercializa tiene
   una ficha y dos carnets. De acá cuelga qué puede emitir cada credencial
   —faenas o guías— y si lleva cupo en kilos.

### route() convierte el nombre de una ruta de Laravel en su URL:

   route('beneficiarios.show', 42)  ->  '/panel/beneficiarios/42'
   No hace falta importarla: la inyecta la directiva @routes de Ziggy en
   resources/views/app.blade.php, y está disponible en cualquier archivo.

## `resources/views/documentos/recibo-oficial.blade.php`

### DESCRIPCIÓN — las seis casillas del talonario

   Se dibujan SIEMPRE las seis, aunque el sistema solo sepa cobrar dos:
   el recibo tiene que salir igual al papel. Cuál queda marcada lo
   decide App\Enums\ConceptoRecibo.

### PIE — las tres copias y la nota legal

   El talonario de papel es autocopiativo y cada hoja dice a quién le
   toca. El PDF sale de a una, pero la leyenda se conserva: es lo que
   Contabilidad y Archivo buscan cuando reciben su copia impresa.

## `routes/auth.php`

### Inicio y cierre de sesión

   No hay registro público ni verificación por correo: las cuentas del sistema
   las crea el administrador desde el panel. Por eso este archivo solo tiene
   login y logout, y no las rutas de "recuperar contraseña" que trae Laravel.
   Cada intento (exitoso, fallido o bloqueado) queda registrado en la tabla
   `accesos`. Ver App\Http\Requests\Auth\LoginRequest.

## `routes/console.php`

### Comandos de consola

   Acá se declaran los comandos de Artisan propios del sistema. Hoy no hay
   ninguno: el archivo existe porque `bootstrap/app.php` lo declara en
   `withRouting(commands: ...)` y borrarlo rompería el arranque.
   EL PRIMERO QUE VA A VIVIR ACÁ es el que marca los carnets como vencidos.
   `EstadoCarnet::Vencido` no lo escribe nadie todavía, y por eso el filtro por
   estado del listado muestra como «vigentes» carnets de gestiones cerradas. Ver
   el problema 2 de docs/PENDIENTES.md.
   Mientras tanto el sistema no miente, porque `Carnet::estaVigente()` compara
   además contra `fecha_vencimiento`.

## `routes/panel.php`

### Panel de administración — requiere sesión iniciada

   Todo lo de este archivo está dentro del middleware 'auth': si no hay sesión,
   Laravel redirige al login antes de ejecutar nada.
   Además cada ruta declara QUÉ PERMISO exige, con el middleware 'permiso'. Ese
   alias apunta a spatie/laravel-permission y está registrado en
   bootstrap/app.php. Los permisos salen del enum App\Enums\RolSistema, que es la
   única fuente de verdad.
   'permiso:beneficiarios.crear'  -> el usuario debe tener ese permiso
   HOY EL ÚNICO ROL ES `administrador` Y LOS TIENE TODOS. El middleware igual va
   en cada ruta, y no es trabajo de más: el día que exista el rol de ventanilla,
   se agrega su lista al enum y las rutas ya están protegidas. Al revés —quitarlo
   ahora «porque total el admin puede todo» y volver a ponerlo después— es donde
   se olvida uno y queda un agujero.
   ESCONDER UN BOTÓN EN REACT NO ES SEGURIDAD. usePermisos() sirve para que la
   pantalla no ofrezca lo que no se puede hacer; quien realmente bloquea es este
   middleware. Van siempre los dos.
   Todas las URLs cuelgan de /panel. La parte pública vive fuera de ese prefijo
   (routes/publico.php), así queda claro de un vistazo qué es administración y
   qué ve el ciudadano.
   No hay auto-registro de usuarios: las cuentas las crea el administrador.
   EL ORDEN DE LOS BLOQUES ES EL DEL FLUJO DE TRABAJO
   1. Beneficiarios      la persona, una sola vez
   2. Aprovechamientos   la bolsa madre: el cupo en kilos
   3. Carnets            la credencial anual, y su impresión
   4. Faenas / Guías     los permisos operativos que cuelgan del carnet
   5. Caja               recibos y pagos
   Los módulos que todavía no existen NO tienen rutas declaradas, y eso es
   deliberado: una ruta declarada convierte el renglón del menú en un enlace
   pinchable (ver barra-lateral.tsx, que pregunta por la ruta antes de enlazar).
   Sin ella, el renglón se dibuja en gris y se lee como «todavía no» en vez de
   llevar a un 500.
   Del modelo anterior no quedó nada: Trámites y Rubros no tienen equivalente y
   se retiraron enteros. Están en git, en el commit `8d48422`.

### 1. Beneficiarios — la persona, UNA SOLA VEZ

   Es el primer paso del flujo, y el único módulo que sobrevivió al cambio de
   núcleo casi intacto: la persona no cambió, lo que cambió es lo que le
   cuelga.
   OJO CON EL ORDEN DE LAS RUTAS. Laravel las evalúa de arriba hacia abajo y
   se queda con la primera que coincide. Por eso 'beneficiarios/crear' tiene
   que ir ANTES que 'beneficiarios/{beneficiario}': si estuviera después,
   Laravel tomaría la palabra «crear» como si fuera el id, no encontraría
   ningún registro y respondería 404.

### 2. Aprovechamientos — la BOLSA MADRE del pescador

   El cupo anual en kilos. Va ANTES del carnet en el flujo porque el plástico
   necesita saber qué cupo imprimir: al revés habría que emitir el carnet y
   corregirlo después, y en el medio existiría una credencial impresa sin
   cupo.
   `edit`, `update` y `destroy` EXISTEN, PERO SOLO SOBRE EL BORRADOR.
   Un cupo nace PENDIENTE DE PAGO, y mientras nadie pagó nada es eso: un
   borrador que el operador acaba de cargar contra el talonario, con el
   pescador enfrente. Equivocarse de tramo se arregla corrigiendo la fila, y
   uno cargado por error se borra con el motivo escrito.
   En cuanto entra el primer boliviano las tres se cierran solas —lo decide
   `EstadoAprovechamiento::permiteEdicion()`, no el middleware—: hay un recibo
   numerado con el detalle impreso, y cambiar lo que ese papel dice por detrás
   no es una corrección.
   NO HAY «AMPLIAR». Un cupo cobrado es lo que dice el recibo, y si al pescador
   le hacen falta más kilos, eso es un trámite nuevo: elegir el tramo, cobrarlo
   y emitir otro recibo. Esa vuelta completa ES el control.
   Un cupo editable SIEMPRE dejaría de ser un límite: alcanzaría con subirle
   el tramo para saltear la escala, sin que quedara constancia de quién lo
   decidió.
   Mismo cuidado con el orden que en beneficiarios: 'crear' va ANTES de
   '{aprovechamiento}' o Laravel toma esa palabra como si fuera el id.

### CARGAR LOS DEPÓSITOS DESDE LA FICHA DEL CUPO.

   El permiso es el de CAJA y no uno de aprovechamientos, porque esto es un
   cobro: sale con recibo numerado y entra al arqueo del día. Que la pantalla
   sea otra no cambia quién puede hacerlo.

### 3. Carnets — la credencial anual

   La LLAVE del año. De ella cuelgan los permisos operativos: faenas si es de
   pescador, guías si es de comercializador.
   NO HAY `edit` NI `update`. Un carnet emitido no se corrige: el plástico ya
   salió de la impresora y está en manos de la persona, así que editarlo
   dejaría al documento diciendo una cosa y al sistema otra —y la
   verificación pública respondería por el dato nuevo, que el inspector NO
   tiene delante—. Lo que hay es REVOCAR, con motivo, y emitir uno nuevo.
   Mismo cuidado con el orden: 'crear' va ANTES de '{carnet}'.

### 4a. Permisos de faena — una salida de pesca

   Cuelgan del carnet de PESCADOR y descuentan kilos de la bolsa madre. El
   carnet es la llave anual; con él solo no se sale a trabajar.
   NO HAY `edit`, NI `update`, NI `destroy`, NI `anular`. El número sale de un
   talonario de papel que el pescador se llevó: borrar la fila deja un hueco
   en la serie que nadie puede explicar y libera un número que el índice único
   volvería a aceptar.
   Y `EstadoFaena` no tiene un estado anulado: una faena emitida de más se
   deja VENCER, y al vencer libera su volumen sola. Lo único que se escribe
   después de emitir es COMPLETAR, que registra la vuelta.

### 4b. Guías de movimiento — un traslado de producto

   La rama del COMERCIALIZADOR. Lo que la faena es para el pescador, la guía
   es para él: el carnet habilita el año, la guía habilita el viaje.
   TRES DIFERENCIAS CON LAS FAENAS:
   - No toca ningún cupo: la comercialización no se autoriza por volumen.
   - Lleva descuento: piscicultura paga el 50% del arancel.
   - SÍ SE ANULA. `EstadoGuia` tiene ese estado y `EstadoFaena` no, porque
   una guía emitida mal ampara un camión que puede estar en la ruta.
   Igual que las faenas, NO hay `edit` ni `destroy`: el código sale de un
   talonario de papel que viaja dentro del camión.

### 5. Caja — el circuito del dinero

   Atraviesa a todos los anteriores: se cobran la credencial, el cupo y la
   guía, y los tres se pagan igual. NO es un paso del flujo —es algo que
   puede pasar en cualquiera de ellos y varias veces— y por eso va aparte y
   no intercalado.
   DOS LISTADOS QUE NO SE REEMPLAZAN:
   /caja     los ABONOS, uno por entrega de dinero. Es lo que se cuadra
   contra el efectivo del cajón al cerrar el día.
   /recibos  los PAPELES entregados, con su correlativo. Es lo que audita
   Contabilidad.
   Un recibo agrupa varios abonos, así que las dos listas nunca tienen la
   misma cantidad de filas.
   LOS RECIBOS NO TIENEN `store`: nacen del cobro, en la misma transacción.
   Un endpoint para crear uno suelto permitiría un comprobante numerado sin
   ningún pago detrás — un papel oficial que dice que entró plata que no
   entró.

### IMPRIMIR va ANTES de '/recibos/{recibo}'… no: van los dos con parámetro,

   Entregar el papel numerado es un acto distinto de consultarlo: quien
   audita la serie puede necesitar verla sin poder emitir comprobantes.

### Catálogos — lo que sale de una resolución y casi no se toca

   Son tres listas chicas: los gremios, la escala oficial de kilos y precios,
   y los tipos de credencial con su arancel. De ellas dependen los dos pasos
   siguientes del flujo —el cupo y el carnet— así que sin cargarlas no se
   puede emitir nada.
   CUELGAN DE /panel/catalogos/ Y NO DE LA RAÍZ del panel a propósito: son
   mantenimiento, no trabajo de mostrador, y la URL lo dice sin que haga
   falta explicarlo.
   LOS TRES TIENEN index + store + update, Y NINGUNO TIENE destroy. No es un
   olvido: los carnets, las guías y los aprovechamientos ya emitidos apuntan
   a estas filas. Una entrada que se deja de usar se pone inactiva, y así los
   documentos históricos la siguen mostrando —que es lo correcto: la persona
   pertenecía a esa asociación cuando se le emitió el carnet—.
   Tampoco tienen pantalla de alta ni de edición aparte: el formulario vive
   al lado de la tabla, porque son listas de pocas filas que se comparan
   entre sí mientras se cargan. Ver AsociacionController.
   VER es de lectura y GESTIONAR es de administración, y por eso son dos
   permisos: cualquiera que emita un carnet necesita LEER el catálogo —el
   desplegable sale de acá— pero tocar una tarifa es otra cosa.

### Módulos por construir

   Aprovechamientos, Carnets, Faenas, Guías, Caja, Reportes y Configuración.
   Todos aparecen en el menú lateral en gris, porque barra-lateral.tsx
   comprueba si la ruta está declarada antes de convertir el renglón en
   enlace.
   Para construir cualquiera: copiar el patrón de Beneficiarios —controlador,
   Request, tipos de TypeScript y pantallas—, que es la plantilla del sistema
   y está comentado paso a paso a propósito.

## `routes/publico.php`

### Rutas públicas — sin autenticación

   Acá va lo único que el sistema expone al ciudadano: la verificación de
   autenticidad de un carnet. Es la URL codificada dentro del código QR impreso
   en cada documento.
   Un pescador muestra su carnet, el inspector escanea el QR con su teléfono y
   cae en esta pantalla, que le dice si el documento es real, si está vigente y
   para qué rubros habilita. Por eso NO puede pedir login.
   HACE FALTA UN SOLO DATO: la firma de validación. El carnet no tiene número —se
   retiró la columna `codigo`— y se identifica por esos dieciséis caracteres, que
   están impresos en el plástico y dentro del QR. Ver VerificacionController.

## `routes/web.php`

### Mapa de rutas de Jichi

   Laravel carga automáticamente SOLO este archivo (así está declarado en
   bootstrap/app.php). Desde acá se incluyen los demás, agrupados por área,
   para que no termine todo amontonado en un archivo de 300 líneas.
   routes/publico.php  -> lo que ve cualquier ciudadano, sin iniciar sesión
   routes/auth.php     -> iniciar y cerrar sesión
   routes/panel.php    -> el panel de administración (requiere estar logueado)
