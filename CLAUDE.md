# Jichi — guía para agentes de IA

Sistema de carnets, autorizaciones de pesca, permisos de faena y guías de
transporte del Gobierno Autónomo Departamental del Beni (SEDAG), Bolivia.

**Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript · Tailwind 4 ·
PostgreSQL 18** (corre también en SQLite).

> **El modelo anterior —`rubros`, `tramites`, `faenas`, `guias`, el recibo
> armado al vuelo— ya no existe**, ni en la base ni en el código: se rehízo el
> 18/09/2026 y el panel quedó portado el 27/09/2026. Si aparece en un `.md`, esa
> parte está vieja. Si hace falta leerlo, está en el historial de git.

---

## Antes de tocar nada — y antes de leer código

> **NO leas el sistema entero para entenderlo.** Está documentado a propósito
> para que no haga falta:
>
> | Leé esto | Para |
> | --- | --- |
> | [docs/REGLAS-NEGOCIO.md](docs/REGLAS-NEGOCIO.md) | **LA ESPECIFICACIÓN**, definida por el responsable. Cuando el código no coincida, manda ella |
> | [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Entender el sistema. **Empezá acá siempre** |
> | [docs/MAPA-ARCHIVOS.md](docs/MAPA-ARCHIVOS.md) | Qué hace cada archivo y qué tiene de no obvio |
> | [docs/MER.md](docs/MER.md) | Las tablas, sus relaciones y qué regla impone cada restricción |
> | [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Dónde va un archivo **nuevo** |
> | [docs/modulos/](docs/modulos/) | Un módulo en profundidad |
> | [docs/PENDIENTES.md](docs/PENDIENTES.md) | Qué falta y qué está roto |
> | [docs/NOTAS-CODIGO.md](docs/NOTAS-CODIGO.md) | El porqué largo de una decisión puntual, por archivo |
>
> Recién después abrí código, y abrí **el archivo que vas a cambiar**, no el
> resto. Si al terminar sabés algo que esos `.md` no decían, agregalo ahí.

---

## El dominio, en corto

El detalle está en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) y las reglas,
con ejemplos, en [docs/REGLAS-NEGOCIO.md](docs/REGLAS-NEGOCIO.md). Lo mínimo
para no equivocarse:

```
                          BENEFICIARIO (C.I. único)
              pescador ┌──────────┴──────────┐ comercializador
                       ▼                     ▼
 AUTORIZACIÓN DE PESCA PARA            CARNET (comercializador)
 APROVECHAMIENTO PESQUERO                    │  sin autorización
 (la bolsa madre: un cupo en kg)             ▼
                 │                     GUÍA ÚNICA DE TRANSPORTE
                 ▼                     (una por traslado, 5 días)
        CARNET (pescador)                    └──< detalle por producto
                 │
                 ▼
        PERMISO DE FAENA (una por salida, 30 días;
        reserva kilos al registrarse, los descuenta al aprobarse)

 recibo ──< pago ──(polimórfico)──▶ Autorización | Carnet | Faena | Guía
```

- **El carnet es la llave anual; con él solo no se sale a trabajar.** Lo que
  autoriza el trabajo de cada día son la faena y la guía.
- **Qué emite cada carnet lo dice `carnets.tipo_actor`** (`TipoActor::emiteFaenas()`
  / `emiteGuias()`), NUNCA el nombre del tipo de carnet: ese catálogo lo edita la
  unidad desde el panel.
- **Una autorización vigente por persona y un carnet vigente por actividad.** El
  pescador no saca carnet sin autorización; el comercializador nunca lleva una.
- **El circuito es el mismo para los cuatro documentos**, y lo dictan los enums
  (`EstadoAprovechamiento`, `EstadoCarnet`, `EstadoFaena`, `EstadoGuia`):

  ```
  PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ vencido / revocado / agotado
  (borrador)  ▲                │   └── al enviar sale el RECIBO
     │        └──[rechazar]────┘
     └──[eliminar, con motivo]
  ```

  Enviar exige el monto cubierto; aprobar exige todas las boletas validadas.
  Rechazar devuelve a pendiente, no es un estado final.
- **Revocar la autorización NO reescribe sus carnets ni sus faenas: los deja
  «sin efecto»**, porque su vigencia se calcula mirando al padre. Ver la trampa
  de la vigencia, más abajo.
- **Lo que se entregó, se congela**: el recibo copia monto y concepto, la guía
  copia el precio de cada producto. **No hay columnas de saldo**: lo pagado es la
  suma de `pagos` y el saldo de kilos se calcula sobre las faenas.

Todo eso vive en `app/Services/` (`OtorgarCupoService`, `Emitir*Service`,
`Revisar*Service`, `CobrarService`, `ControlarPagoService`), en transacciones con
la fila que contiene el recurso escaso bloqueada. **No se replica en el
controlador ni en React.**

---

## Reglas que no se rompen

1. **Todo en español.** Nombres de archivo, variables, métodos, comentarios,
   mensajes al usuario, textos de la interfaz. La única excepción son los
   componentes de `resources/js/components/ui/` (`Button`, `Card`, `Input`,
   `Label`, `Badge`, `Select`, `Textarea`), que conservan el vocabulario
   estándar de React.

2. **Comentar el porqué, no el qué — y CORTO: de una a tres líneas.**

   Un comentario que repite lo que dice el código sobra; uno que explica por qué
   se eligió ese camino vale oro. Pero **el porqué entra en dos renglones**.

   **NADA de bloques con banners de `====`, títulos en mayúscula ni ensayos de
   treinta líneas.** El código llegó a tener un 38% de comentarios —12.695 líneas
   sobre 33.383— y dejó de leerse de corrido: había que desplazar media pantalla
   para ver la línea siguiente. El 20/09/2026 se podaron a la mitad.

   **Lo que no entra en tres líneas va a un `.md`**, que es donde alguien lo va a
   buscar: el esquema a [docs/MER.md](docs/MER.md), el módulo a
   [docs/modulos/](docs/modulos/), y el desarrollo largo de una decisión puntual
   a [docs/NOTAS-CODIGO.md](docs/NOTAS-CODIGO.md) —ahí está lo que se podó, por
   archivo—. En el código queda el resumen y, si hace falta, «ver X».

3. **Panel, público y portal no se mezclan.** El sistema tiene tres mitades
   separadas en carpetas paralelas, en el backend y en el frontend:

   | | Panel (funcionarios) | Público (sin sesión) | Portal (beneficiario) |
   | --- | --- | --- | --- |
   | Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` | `routes/portal.php`, prefijo `/mi-cuenta` |
   | Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` | `Http/Controllers/Portal/` |
   | Pantallas | `resources/js/pages/panel/` | `resources/js/pages/publico/` | `resources/js/pages/portal/` |
   | Componentes | `components/panel/` | `components/publico/` | `components/portal/` |
   | Layout | `layouts/layout-panel.tsx` | `layouts/layout-publico.tsx` | `layouts/layout-portal.tsx` |

   **Una cuenta de `users` con `beneficiario_id` es del portal y NUNCA entra al
   panel.** Lo sostienen los middleware `funcionario` y `beneficiario` en el
   grupo entero de rutas, y que esas cuentas no lleven roles. Ver
   [docs/modulos/PORTAL.md](docs/modulos/PORTAL.md).

   La vista pública no puede exponer datos personales completos ni pistas de la
   estructura interna. Ver `VerificacionController::datosPublicos()`.

4. **`env()` solo dentro de `config/`.** En el resto del código,
   `config('jichi.lo_que_sea')`. Con `config:cache` activo, `env()` devuelve
   `null` fuera de `config/` y el error es silencioso.

5. **Los permisos se declaran en las rutas.** El middleware `permiso:` es la
   seguridad real. Esconder un botón en React (`usePermisos()`) es solo
   comodidad: siempre van los dos. Hoy el único rol es `administrador` y los
   tiene todos, pero el middleware va igual en cada ruta.

6. **Los enums mandan.** Estados, tipos, roles y permisos viven en `app/Enums/`.
   No escribir esos valores como texto suelto en el código.

   Eso incluye las TRANSICIONES: qué salto de estado vale desde dónde lo dice
   `siguientes()` / `permite*()` de cada enum de estado (`EstadoAprovechamiento`,
   `EstadoCarnet`, `EstadoFaena`, `EstadoGuia`), y nada más. El servicio
   pregunta, el controlador no decide y React recibe la respuesta ya calculada en
   los campos `puede_*` de la ficha. Un `if ($carnet->estado === ...)` suelto en un
   controlador es la señal de que la regla se está duplicando.

7. **Enums en columnas `string`**, nunca tipos ENUM nativos de PostgreSQL: así
   agregar un estado no exige `ALTER TYPE` ni bloquear la tabla.

8. **SQL específico de motor va en `app/Support/Sql.php`.** El sistema tiene que
   correr igual en PostgreSQL y en SQLite.

9. **Scopes con `qualifyColumn()`.** `aprovechamientos_pesq`, `carnets`,
   `permisos_faena` y `guias_movimiento` tienen todas una columna `estado`, y las
   consultas las cruzan con `join`: un `where('estado', ...)` sin calificar
   responde «column reference is ambiguous».

10. **Las reglas de negocio van en `app/Services/`, no en el controlador.** El
    mismo caso de uso lo necesitan el formulario del panel y un comando de
    consola. Escrito en el controlador, el otro lo copia —y la copia se queda
    vieja—.

11. **TODO archivo se sube con `StorageController::file()`.** Nunca `->store()`,
    `->storeAs()` ni `Storage::put()` en otra clase. Es el único lugar que aplica
    las tres reglas que valen para todos los adjuntos: el tope de **3 MB**, el
    nombre aleatorio —el del usuario no se conserva nunca— y en qué disco se
    escribe. Nada lo controla automáticamente: al revisar un cambio, buscar
    `->store(`, `->storeAs(` y `Storage::put(` fuera de `StorageController`.

12. **MIENTRAS EL NÚCLEO SE ESTÉ ARMANDO, NO SE AGREGAN MIGRACIONES DE
    PARCHE.** Una columna nueva va DENTRO de la migración que crea su tabla, no
    en un `add_x_to_y` aparte.

    El motivo es práctico: el responsable del proyecto rearma la base con
    `migrate:fresh` cada vez que el esquema cambia, así que un archivo de parche
    solo agrega ruido a un esquema que igual se va a construir de cero. Y de paso
    el comentario de la columna queda al lado del resto de la tabla, que es donde
    alguien lo va a buscar.

    **Al editar una migración ya corrida hay que avisar que hay que volver a
    migrar, y el aviso NO alcanza:** el esquema real queda viejo hasta que
    alguien corra el comando, y el síntoma aparece recién en ventanilla como
    `column "x" does not exist`. Si la tabla afectada está VACÍA, se puede poner
    al día sin rearmar todo: `Schema::dropIfExists()` y llamar al `up()` de esa
    migración —`require database_path('migrations/...')`— deja el esquema
    **idéntico**, orden de columnas incluido. Un `ALTER TABLE ADD COLUMN` NO
    sirve para eso: PostgreSQL agrega siempre al final y el orden deja de
    coincidir con la migración.

    Y verificarlo antes: se arma el esquema completo en una base
    descartable —`DB_CONNECTION=sqlite DB_DATABASE=<archivo> php artisan
    migrate:fresh --seed`— y recién ahí se dice que funciona. Nunca sobre la base
    de trabajo.

    Esto deja de valer el día que el sistema esté en producción con datos reales:
    ahí una migración editada es una migración que nadie va a volver a correr.

    **Y la migración se mantiene CORTA.** El porqué de cada decisión de esquema
    va en [docs/MER.md](docs/MER.md), tabla por tabla; en la migración queda el
    encabezado de cuatro líneas y, a lo sumo, un renglón por columna que no se
    explica sola. Una migración con ensayos de treinta líneas no la lee nadie, y
    lo que hay que consultar de verdad —«¿por qué este índice es parcial?»— queda
    enterrado entre las diez tablas en vez de estar junto a las otras nueve.

    **El orden dentro del `Schema::create()` es siempre el mismo**, y
    `timestamps()` + `softDeletes()` van **al final de todo**, después de los
    índices:

    ```
    id → claves foráneas → datos → estado → fechas del negocio
       → índices compuestos → timestamps() → softDeletes()
    ```

    El orden de esas llamadas no cambia el esquema —los índices se crean después
    de las columnas igual—, así que es una convención de lectura: las once tablas
    terminan iguales y se sabe de memoria dónde mirar.

    **UN ÍNDICE O UN ÚNICO DE UNA SOLA COLUMNA VA INLINE**, pegado a la columna
    —`->unique()`, `->index()`— y NO en una línea aparte más abajo: leer la
    columna tiene que alcanzar para saber cómo está indexada. En el bloque de
    abajo quedan solo los COMPUESTOS, que no se pueden declarar de otra forma.

    ⚠️ **Tres que NO pueden ir inline, y hay que saberlas:**

    - **Un índice PARCIAL** —`WHERE deleted_at IS NULL`— va con `DB::statement()`
      después del `create()`. Es lo que se usa donde la baja lógica libera el
      valor; ver [docs/MER.md](docs/MER.md).
    - **`->index()` después de `constrained()` NO HACE NADA**, y no avisa: lo que
      devuelve `constrained()` es un `ForeignKeyDefinition`, así que el `index()`
      se lo come él y la columna queda sin índice. Va **antes**:
      `foreignId('x')->index()->constrained(...)`. PostgreSQL no indexa solo las
      claves foráneas —MySQL sí—, así que la FK que se filtra necesita el suyo.
    - **Un compuesto no se puede colapsar a inline.** `index(['estado','nombre'])`
      no es `->index()` sobre `estado`: el segundo ordena dentro del primero.

    **Al tocar una migración ya escrita, se comprueba que no se perdió ningún
    índice**, y se comprueba MIDIENDO: se arma el esquema viejo y el nuevo en dos
    SQLite descartables y se comparan los índices reales. Las dos trampas de
    arriba se descubrieron así, no leyendo.

    ```sh
    DB_CONNECTION=sqlite DB_DATABASE=<archivo> php artisan migrate --force
    # y después, sobre cada una:
    # select name, tbl_name from sqlite_master where type='index'
    ```

    **Y `softDeletes()` va en TODAS las tablas del dominio**, con el trait
    `SoftDeletes` en su modelo. Nada del dominio se borra de verdad: cada fila
    lleva el nombre de una persona y respalda un papel. Al sumar una tabla nueva
    hay que decidir además de qué lado caen sus índices únicos —**el catálogo
    libera el valor, el papel entregado lo deja quemado**—; está tabulado en
    [docs/MER.md](docs/MER.md#3-borrado-lógico-las-diez-tablas-lo-tienen).

13. **Toda sesión de trabajo se registra.** Al terminar de trabajar hay que
    dejar el registro en `docs/sesiones/MM-AAAA/AAAA-MM-DD.md`, copiando
    [docs/sesiones/_plantilla.md](docs/sesiones/_plantilla.md). Un archivo por
    día. Cada trabajo lleva su problema, la tabla de archivos modificados y la
    solución con el porqué; al final va el **informe para presentación**, que se
    escribe en lenguaje simple —sin términos técnicos— porque se copia a Word y
    lo lee gente que no programa.


---

## El patrón a copiar

El módulo **Beneficiarios** es la plantilla del sistema. Está comentado paso a
paso a propósito. Para agregar un módulo nuevo, copiar:

```
app/Http/Controllers/Panel/BeneficiarioController.php
app/Http/Requests/Panel/GuardarBeneficiarioRequest.php
routes/panel.php                                   (el bloque de beneficiarios)
resources/js/pages/panel/beneficiarios/*.tsx
resources/js/components/panel/beneficiarios/*.tsx
resources/js/types/beneficiarios.ts
```

Si el módulo tiene reglas de negocio de verdad —no solo un CRUD—, copiar además
el par `EmitirFaenaService` + `PermisoOperativoException`.

El procedimiento detallado está en
[docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md#7-agregar-un-módulo-nuevo-paso-a-paso).


---

## Verificar antes de dar algo por terminado

```sh
npx tsc --noEmit        # tipos de TypeScript
./vendor/bin/pint       # formato del PHP
npm run build           # que el frontend compile
```

> **No hay pruebas automáticas**: se retiraron el 27/09/2026 a pedido del
> responsable (están en el historial de git, commit `7dc0ac6`). Los tres
> comandos revisan tipos, formato y compilación; de las reglas de negocio no
> dicen nada. **Todo cambio se verifica abriendo la pantalla y probando el caso
> a mano**, incluidos los bordes: aprobar sin cobrar, pasarse del cupo, una
> fecha en el límite.

Los tres tienen que pasar.

---

## Trampas conocidas de este proyecto

- **Una transacción de base de datos NO deshace escrituras en disco.** Si se
  sube un archivo dentro de la transacción y algo falla, el rollback borra las
  filas pero el archivo queda huérfano para siempre. Por eso TODOS los adjuntos
  —la cédula y el aval del carnet, la boleta de cada pago— se suben ANTES de
  abrir la transacción, y el `catch` los borra. Ver `CarnetController::store()` y
  `PagoController`.
- **`env()` en `StorageController` devolvía null con `config:cache`.** El error
  era silencioso: el sistema creía que el disco no era s3 y escribía los adjuntos
  en el servidor local sin avisar. Ahora todo sale de `config(...)`, y la
  carpeta raíz del bucket la pone el disco (`AWS_ROOT`).
- **Orden de rutas:** `/beneficiarios/crear` y `/beneficiarios/buscar` van ANTES
  de `/beneficiarios/{beneficiario}`, o esas palabras se toman como id.
- **`cascadeOnDelete` NO se dispara con una baja lógica.** Es una restricción
  del MOTOR y solo corre en un DELETE de verdad; `$modelo->delete()` sobre una
  tabla con `SoftDeletes` es un UPDATE. `pagos.recibo_id` es CASCADE, así que
  dar de baja un recibo dejaría sus pagos vivos y visibles en caja, colgando de
  un comprobante que ya no está. Hoy nada da de baja recibos; el día que algo lo
  haga, la baja tiene que arrastrar el detalle a mano.
- **Índices únicos con borrado lógico:** nunca incluir `deleted_at` en un
  `unique()`. En SQL `NULL != NULL`, así que el índice no bloquea nada. Usar
  índice parcial `WHERE deleted_at IS NULL` (ver la migración de
  `beneficiarios`).
- **`update()` descarta en silencio lo que no esté en `#[Fillable]`.** No lanza
  error: simplemente no escribe la columna. Fue el motivo de que
  `fecha_aprobacion` quedara en NULL al aprobar. Si un servicio escribe una
  columna, esa columna va en la lista.
- **Releer con `lockForUpdate()` devuelve OTRA instancia del mismo registro.**
  Escribir sobre esa copia deja la instancia de quien llamó con el estado viejo
  en memoria, y cualquier comprobación posterior responde como si el cambio no
  hubiera ocurrido. La convención del servicio: se escribe sobre la copia
  bloqueada y se devuelve `$modelo->refresh()` —la original—. Ver
  `EmitirFaenaService::corregir()`.
- **El estado guardado de un documento puede mentir.** `vencido` lo tendría que
  escribir un comando diario que **todavía no existe** (ver PENDIENTES), así que
  un carnet del año pasado sigue diciendo `aprobado`. Para saber si vale HOY se
  mira además la fecha: `Carnet::estaVigente()` y el scope `vigentes()`.
- **EL CÓDIGO DE UN DOCUMENTO NO ES UNA COLUMNA SUYA: vive en `codigos`.**
  Desde el 22/09/2026 los cinco documentos que se entregan —carnet,
  aprovechamiento, faena, guía y recibo— comparten una tabla polimórfica con un
  único índice global, y por eso el código **no lleva prefijo**. Se lee con
  `$doc->codigo_legible` y se asigna con `$doc->asignarCodigo()`; las dos las
  pone `App\Traits\Codificable`. **Toda consulta que lo muestre necesita
  `with('codigo')`** o hace N+1 en silencio — y en `carnets` el accesor va en
  `#[Appends]`, así que basta con serializar la fila.
- **El código de verificación NO reemplaza al correlativo, y al revés tampoco.**
  `numero_recibo`, `numero_faena` y `nro_registro` son consecutivos **a
  propósito**: Contabilidad audita sus huecos. El código es imposible de
  adivinar **a propósito**: es la llave de una pantalla pública, y un `000002`
  lo prueba cualquiera. Un número al azar no tiene huecos que auditar. Conviven,
  no compiten.
- **Dos botones en la misma posición de un ternario necesitan `key` distinto.**
  React los reconcilia como el MISMO `<button>` y solo le cambia el atributo
  `type`; si uno es `type="button"` y el otro `type="submit"`, el cambio ocurre
  mientras el clic se está procesando y el formulario se envía solo. En un
  formulario por pasos eso registraba la solicitud al pasar del paso 2 al 3, con
  los adjuntos vacíos.
- **Pedir columnas sueltas en un `with()` rompe los métodos del modelo, y no
  avisa.** Un método que lee una columna que no vino en el select la ve en null
  y contesta mal: `Carnet::puedeEmitirFaenas()` sin `tipo_actor` o sin
  `aprovechamiento_id` devuelve false, y el formulario de faenas abre vacío
  descartando en silencio un carnet válido. Vale para CUALQUIER columna que un
  método lea, empezando por las cinco del nombre del beneficiario: si el modelo
  la consulta, va en el select. Ver `FaenaController::create()`.

  **Volvió a morder con la CÉDULA, y con el mismo silencio:**
  `documento_identidad` arma «1234567-1A BN» leyendo `ci`, `complemento` y
  `expedido`, y los `with('beneficiario:id,ci,…')` traían solo `ci`. Los
  listados de aprovechamientos, carnets, faenas y caja mostraban el número
  pelado —sin complemento ni expedido— y nadie lo notó porque la ficha, que
  carga el modelo entero, lo mostraba bien. **Si el accesor concatena N
  columnas, las N van en el select.**
- **Un `default` de la base NO llega al objeto que devuelve `create()`.** El
  INSERT lo aplica el motor, y el modelo en memoria se queda con la columna en
  `null` hasta que alguien haga `refresh()`. Eso rompe lo obvio: emitir una
  faena y preguntarle su estado en la línea siguiente contestaba null, con la
  fila ya escrita y correcta en la base. Si una columna tiene valor por defecto
  y el código lo lee, va **también** en `protected $attributes` del modelo, con
  el `->value` del enum, porque `$attributes` se llena antes de que corran los
  casts. Pasó TRES veces en un día —el estado y el monto de la faena y
  `pagos.estado_validacion`— y las tres las descubrió una prueba que preguntaba
  por el estado justo después de `create()`. Ver `PermisoFaena` y `Pago`.
- **Una relación polimórfica NO se puede precargar con `with('pagable.carnet')`.**
  Eloquent no sabe qué es `pagable` hasta que lee la fila, así que no puede
  resolver lo que cuelga de él: lo que se escribe así se ignora y el N+1 sigue
  ahí, sin ningún error. Va con `morphWith`, declarando qué traer para cada
  tipo. Ver `CajaController::index()` y `ReciboController`.
- **Una variable CSS declarada en `:root` NO crea una utilidad de Tailwind.**
  `--institucional-azul` estaba escrita desde el principio, pero
  `bg-institucional-azul` no existía: Tailwind 4 solo genera la utilidad si el
  token está además registrado en el bloque `@theme inline`. La clase se escribe,
  se compila sin un solo error y el elemento sale **transparente**. Es lo mismo
  que ya avisaba el comentario de `--color-panel-fondo` en `app.css`, y vale para
  cualquier color nuevo.
- **Una prop de página con el nombre de una prop COMPARTIDA la tapa, sin avisar.**
  `HandleInertiaRequests` comparte `institucion`, así que un
  `Inertia::render(..., ['institucion' => ...])` deja a los componentes de esa
  pantalla leyendo otra cosa con `usePage()`. Antes de elegir el nombre de una
  prop, mirar la lista de `share()`: hoy son `auth`, `institucion`, `archivos`,
  `flash`, `ziggy` y `apariencia`.
- **Clases de Tailwind armadas juntando textos no funcionan.** Tailwind solo
  incluye en el CSS final las que puede leer literalmente en el código. Si se
  agrega un color a un enum de PHP, hay que agregarlo también al mapa de
  `components/ui/badge.tsx`.
- **Los gráficos de recharts necesitan que el contenedor padre tenga altura**
  (`h-72`), o se calculan con altura cero y no se ven.
- **Un `overflow-x-auto` adentro de una grilla NO desplaza nada sin `min-w-0`.**
  Un elemento de grilla arranca con `min-width: auto`, que significa «no te
  encojas por debajo de tu contenido». Con una tabla de seis columnas adentro,
  la tarjeta se estira a los 675 px que mide la tabla aunque la pantalla tenga
  375, y el que queda con barra de desplazamiento es **el documento entero**: en
  el celular se corre de costado la pantalla completa —menú, encabezado y todo—
  para leer una columna. `min-w-0` en el elemento de grilla devuelve el permiso
  de encogerse, y recién ahí el `overflow-x-auto` hace su trabajo. Lo mismo vale
  para un elemento `flex`. Ver `tabla-ultimos-carnets.tsx`. **No se nota en el
  escritorio**, que es donde se prueba: aparece solo al angostar la ventana.
- **LA APLICACIÓN CORRE EN HORA DE BOLIVIA, NO EN UTC** (27/09/2026).
  `config/app.php` traía `'timezone' => 'UTC'`, y en UTC-4 eso significa que
  de 20:00 a medianoche `now()` ya es el día siguiente: un carnet aprobado a
  las 21:00 salía emitido «mañana», la faena salía con fecha de mañana y el
  arqueo de «hoy» en Caja y en el tablero no veía lo cobrado esa noche. Nadie lo
  notó porque se prueba de día. Hoy es `America/La_Paz` (`APP_TIMEZONE`).
  **Ojo con los datos cargados antes del cambio**: sus `created_at` se
  guardaron en hora UTC sin zona, así que ahora se leen 4 horas corridos. En
  desarrollo alcanza con `migrate:fresh --seed`; en producción el sistema ya
  arranca con la zona correcta.
- **`new Date('2026-12-31')` en JavaScript NO da el 31 de diciembre.** El
  estándar interpreta una cadena `AAAA-MM-DD` como medianoche UTC, y Bolivia está
  en UTC-4: al formatear en horario local sale **30/12**. Afectaba al vencimiento
  de todos los carnets y a las fechas de nacimiento. Toda fecha se muestra con
  `fecha()` de `lib/utils.ts`, que distingue una fecha suelta de un instante; ver
  `aFechaLocal()`. Nunca `new Date(cadena)` directo en un componente.

  **Y `fecha()` no alcanza si el SERVIDOR manda la forma equivocada.** Es la
  otra mitad de la misma trampa y mordió con `pagos.fecha_pago`: la columna es un
  timestamp, pero lo que guarda es el DÍA que dice la boleta. Mandada con
  `toIso8601String()` llegaba como `2026-09-17T00:00:00+00:00` —un instante— y
  `fecha()` hacía lo correcto con él: pasarlo a horario local, que en UTC-4 es el
  16 a las 20:00. **Un depósito del 17 se mostraba como 16/09**, y nadie lo notó
  hasta que un formulario tuvo que leer esa fecha de vuelta. La regla: si la
  columna guarda un DÍA, va con `toDateString()`; si guarda un MOMENTO —cuándo se
  validó, cuándo se cargó— va con `toIso8601String()`. Y para rellenar un
  `<input type="date">` va `fechaInput()`, nunca `slice(0, 10)`.
- **`pluck()` sobre una columna que no existe NO FALLA: devuelve nulls.** Es el
  mismo silencio de `update()` con algo fuera de `#[Fillable]`, y costó
  archivos: con el modelo anterior, las boletas se juntaban con
  `pluck('comprobante')` —la columna es `urlFile`; `comprobante_url` es el
  accesor con la dirección completa— así que **cada expediente eliminado dejaba
  todas sus boletas tiradas en el disco**, para siempre y sin ningún error. Al
  escribir un `pluck()`, un `where()` o un `select()` a mano contra un nombre de
  columna, confirmarlo en el `#[Fillable]` del modelo o en la migración: los
  accesores `#[Appends]` se parecen a columnas y no lo son.
- **`beneficiarios` usa camelCase de `primerNombre` en adelante.** En PostgreSQL
  eso obliga a entrecomillar: `SELECT "primerNombre" ...`. Sin comillas el motor
  pasa el nombre a minúscula y responde `column "primernombre" does not exist`.
  Laravel entrecomilla solo; el problema aparece al escribir SQL a mano o al
  usar `whereRaw` / `orderByRaw` (ver `Beneficiario::SQL_NOMBRE`).
- **Un accesor camelCase NO puede ir en `#[Appends]`.** Laravel busca el accesor
  por el nombre del método pasado a snake_case, así que `nombreCompleto` no lo
  encuentra, cae al accesor de estilo viejo y revienta con
  «Call to undefined method getNombreCompletoAttribute()». Acceder directo
  (`$beneficiario->nombreCompleto`) sí funciona. Ver `app/Models/Beneficiario.php`.
- **`attach()` no dispara eventos de Eloquent**, así que el trait `Auditable` no
  registra nada. Un pivote que tenga que quedar en `auditorias` va como modelo
  propio y se crea con `create()`.
- **`$modelo->relacion()->where(...)` consulta SIEMPRE**, aunque quien llamó haya
  hecho `with('relacion')` justamente para evitarlo. El `with()` queda escrito,
  se ve correcto, y el N+1 sigue ahí en silencio —el autocompletado de
  beneficiarios hacía 18 consultas por tecleada con el eager loading puesto—. Un
  método del modelo que lo use debe preguntar antes con `relationLoaded()`; ver
  `AprovechamientoPesq::kilosConsumidos()`.
- **`php artisan serve` LEE EL `.env` UNA SOLA VEZ, y REINICIAR EL SERVIDOR NO
  ES LO QUE PARECE.** Es la trampa más cara de este proyecto hasta ahora: costó
  dos diagnósticos equivocados.

  El proceso es un árbol de tres:

  ```
  php artisan serve          ← LEE el .env, una vez, al arrancar
    └── cmd.exe
          └── php -S ...     ← el que atiende, hereda el entorno YA congelado
  ```

  `artisan serve` **vigila el `.env` y reinicia solo al hijo** cuando cambia.
  Entonces el hijo aparece con hora de recién —parece reiniciado, y hasta
  coincide al segundo con la hora del archivo— pero recibe las variables del
  padre, que son las del arranque. Como Dotenv **no pisa** variables que ya
  existen en el entorno, cada request vuelve a leer el valor viejo. Puede seguir
  así durante días.

  El síntoma no delata nada de esto: sale como `relation "..." does not exist`
  sobre una tabla que uno acaba de migrar y puede ver en el gestor.

  **Antes de dudar del esquema, mirar qué base dice el error**: el mensaje trae
  `(Connection: pgsql, ..., Database: X)`. Y para saber quién quedó viejo, mirar
  la hora de arranque del **padre**, no la del que escucha el puerto:

  ```sh
  # PowerShell: todos los php con su hora de arranque y su línea de comando
  Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | ForEach-Object {
      $p = Get-Process -Id $_.ProcessId; "$($_.ProcessId)  $($p.StartTime)  $($_.CommandLine)"
  }
  ```

  Acá el padre de todo es `composer run dev` → `artisan dev`, que levanta
  `serve`, `queue:listen` y Vite: **hay que cortar ESO**, no el servidor solo.
  `queue:listen` arrastra el mismo entorno viejo.
- **`withSum()` devuelve NULL cuando no hay filas, no cero.** Es lo que
  contesta `sum()` en SQL sobre un conjunto vacío, y rompe el patrón de
  «reusar el agregado si vino en la consulta»: escrito como
  `if ($this->pagos_sum_monto === null) { consultar }`, justamente la fila SIN
  pagos —la que más aparece en un listado— se cae a la consulta agregada suelta,
  con el `withSum` puesto, viéndose correcto y sin ningún error. El N+1 sigue
  ahí para la mitad de las filas. Se pregunta si la CLAVE EXISTE:
  `array_key_exists('pagos_sum_monto_parcial', $this->getAttributes())`. Ver
  `App\Traits\Pagable::montoPagado()` y
  `AprovechamientoPesq::kilosConsumidos()`.
- **El trait `Auditable` YA registra el borrado: escribir la auditoría a mano
  deja DOS filas.** Engancha `created`, `updated` y `deleted`, así que un
  servicio que además llame a `registrarAuditoria('eliminado', …)` duplica el
  hecho — y la copia automática va SIN motivo, porque el motivo viaja por
  `$modelo->motivoAuditoria`. El historial muestra el mismo borrado dos veces,
  una de ellas sin ninguna explicación. La forma correcta es dejar el motivo y
  borrar:

  ```php
  $modelo->motivoAuditoria = $motivo;
  $modelo->delete();
  ```

  **Y el ensayo tampoco lo delata si busca con `first()`:** devuelve la fila
  correcta y da verde con la duplicada al lado. Al probar una auditoría, contar
  las filas, no leer la primera. Se descubrió mirando la tabla en el navegador.
- **Agregar un estado a un enum rompe cosas que no se ven, y el compilador no
  avisa de ninguna.** Pasó TRES veces con `EstadoAprovechamiento` —al sumar
  `pendiente` y al sumar `en_revision`— y siempre en el mismo lugar: el scope
  `enCurso()` y los servicios que enumeran estados a mano. **Al sumar un estado,
  la lista de lugares a revisar es fija:** los scopes del modelo, los
  `match`/`if` de los servicios que comparan contra un caso concreto, y las
  banderas `puede_*` que el controlador manda a la pantalla. Al sumar `pendiente` a `EstadoAprovechamiento` los `match`
  sí fallaron —eso sí lo marca PHP—, pero lo caro fue lo otro: cada lugar que
  preguntaba `vigentes()` pasó a contestar «no» para el estado nuevo, en
  silencio. Dos ejemplos reales, los dos encontrados por un ensayo y no leyendo:
  el carnet de pescador dejó de poder emitirse —exigía un cupo ya cobrado— y la
  regla de «una bolsa por persona» dejó de contar los cupos sin pagar, así que se
  podían otorgar cinco y quedarse con el mejor. **Al sumar un estado, buscar
  todos los scopes y helpers que enumeran estados y decidir uno por uno de qué
  lado cae el nuevo.**

  **Cuarta vez, con `revocado` (27/09/2026), y los dos agujeros estaban donde no
  se mira el estado:** `puedeEmitirFaena()` en modo FLEXIBLE devuelve true sin
  preguntar el estado —una revocada seguía emitiendo faenas— y
  `sincronizarEstadoPorSaldo()` la habría REVIVIDO a `aprobado` al anular una
  faena. Además de los scopes, revisar **todo método que salte el estado a
  propósito** y todo `update` a un estado fijo.
- **La vigencia de un carnet o una faena de pescador MIRA A SU AUTORIZACIÓN**
  (27/09/2026). Revocar la autorización no reescribe a los hijos: quedan
  `aprobado` pero «sin efecto». Por eso `estaVigente()`, `sinEfecto()` y
  `etiquetaEstado()` leen `aprovechamiento.estado`, y **toda consulta que los use
  precarga la autorización** —en la faena, `carnet.aprovechamiento` con
  `aprovechamiento_id` en el select del carnet—, o dan falso en silencio. Y un
  `where('estado', 'aprobado')` suelto NO es «vigente»: se usa el scope
  `vigentes()`, que excluye a los que quedaron sin efecto.

  **Y la faena y la guía miran además a su CARNET** (27/09/2026): valen solo si
  el titular tiene un carnet vigente de la actividad —el suyo o el que lo
  reemplazó—, ver `Carnet::amparaSusPapeles()`. Por eso todo `with('carnet:…')`
  de faenas o guías lleva `estado`, `fecha_vencimiento`, `beneficiario_id`,
  `tipo_actor` y `aprovechamiento_id`; sin `estado`, el carnet devuelve null y
  `estaVigente()` revienta.
- **Un método que «revive» un registro puede activar lo que nunca se autorizó.**
  El viejo `ampliar()` —retirado el 19/09/2026— escribía `estado = Activo` a
  secas para revivir un cupo agotado; cuando apareció `pendiente`, ampliar pasó a
  habilitar para pescar un cupo sin cobrar, que era la puerta de atrás del cobro.
  Un `update` de estado a un valor fijo hay que mirarlo de nuevo cada vez que se
  suma un estado del que ese valor no debería alcanzarse.
- **`validated()` devuelve SOLO las claves que vinieron en la petición.** Un
  campo `nullable` que el formulario no manda no existe en el arreglo, así que
  `$datos['campo']` revienta con «Undefined array key» y un 500 — no devuelve
  null. Va `$datos['campo'] ?? null`. Pasó con `nit_ci_factura` al cobrar desde
  la ficha del cupo, y **ningún ensayo lo detecta**: llamando al servicio
  directamente se pasan todos los argumentos, y el agujero solo aparece cuando
  lo llena un formulario de verdad.
- **Para borrar un método de PHP, NO se usa una expresión regular multilínea.**
  Las llaves anidadas no se pueden expresar con una regex, así que lo que sobra o
  falta no se nota hasta que el archivo ya está escrito: al sacar `ampliar()` de
  `OtorgarCupoService`, una regex «prudente» se llevó también `otorgar()`,
  `editar()` y `eliminar()` —de 397 líneas a 92—. Va por NÚMERO DE LÍNEA, con un
  `assert` sobre el contenido de cada borde antes de cortar. Y conviene mirar si
  el archivo está commiteado antes de empezar: `git checkout HEAD -- <archivo>`
  fue lo que lo salvó.
- **Probar una pantalla con `curl` y la cabecera `X-Inertia` devuelve 409, no
  la página.** Inertia compara la versión del manifiesto de assets y responde
  `409 Conflict` con `X-Inertia-Location` cuando no coincide —que es siempre, si
  el número se inventa—. **Se pide sin ninguna cabecera de Inertia:** las props
  viajan igual, en el HTML, y se leen con un `grep` del nombre de la clave.
  **Con Inertia 2 NO van en el atributo `data-page`**: van en
  `<script data-page="app" type="application/json">`. Un parser que busque el
  atributo lee «app» y revienta con «is not valid JSON». Un 409 acá NO es un error de la pantalla.
- **Una bandera de configuración que apaga una validación tiene que llegar a la
  PANTALLA, o la pantalla miente.** Con `APROVECHAMIENTO_ESTRICTO=false` el
  servidor acepta una faena que se pasa del cupo, y el formulario la seguía
  frenando con «no entra en el cupo»: una regla inventada en React sobre algo
  que el sistema permite, sin ningún mensaje que lo explicara. El patrón que
  quedó es separar el HECHO de la CONSECUENCIA —`excede` y `bloquea`— y mandar
  el modo como prop. Ver `FaenaController::create()` y `faenas/crear.tsx`.
- **`estaVigente()` mezcla estado y fecha, y eso esconde botones.** Un cupo
  AGOTADO no está vigente —su estado no habilita— y es exactamente el que hay
  que poder tocar. Mordió diciendo «no tiene un aprovechamiento vigente» al
  emitir una faena sobre un cupo agotado pero en fecha, lo que mandaba al
  operador a otorgar uno nuevo que la regla de una bolsa por persona iba a
  rechazar. **Antes de usar `estaVigente()` como permiso, preguntarse qué se está
  preguntando de verdad**: hay `estaEnFecha()`, `puedeEditarse()`,
  `puedeEliminarse()` y `puedeEmitirFaena()`, y cada una mira cosas distintas.
- **EL PRECIO DE LA AUTORIZACIÓN NO LO PONE JICHI: LO PONE SIREB** (29/09/2026).
  La escala guarda `servicio_sireb` y `tarifa_sireb` (uuid completos), no un
  precio; al otorgar o corregir, `OtorgarCupoService` pide el monto a
  Recaudaciones y lo congela en `aprovechamientos_pesq.monto`. **Sin SIREB no se
  otorga**: es a propósito, ninguna tarifa se escribe a mano. Las pantallas que
  solo MUESTRAN precio usan `SirebService::serviciosSiResponde()` y abren igual
  con SIREB caído. **`servicios()` va en caché 10 minutos**: un precio recién
  cambiado en SIREB tarda eso en llegar; `servicio($id)` no, da el de ahora.
  Ver [docs/modulos/SIREB.md](docs/modulos/SIREB.md).
- **Un id de SIREB cortado no falla: deja de coincidir.** `servicio_sireb` era
  `string(30)` y los ids de SIREB miden 36; se guardaban recortados y en
  mayúscula, y ninguna búsqueda los encontraba, sin ningún error. Hoy son `uuid`
  (30/09/2026). Un id de otro sistema va con su largo real, y se prueba buscándolo.
- **Llamar a un servicio DENTRO de un `foreach` parte en pedazos lo que ese
  servicio construye como una unidad, y no falla nada.** `AprovechamientoController::pagar()`
  cobraba llamando a `CobrarService` una vez por depósito, así que dos boletas
  del mismo cupo salían como **000001 y 000002**: dos papeles donde
  va uno, dos números gastados de una serie que Contabilidad audita y dos cobros
  en el arqueo del día donde hubo uno. Lo caro fue lo otro: el control de «no
  cobrar de más» también corría por llamada —cada una leía el saldo antes de que
  las anteriores estuvieran escritas— así que tres depósitos por el total cada
  uno pasaban los tres. **Antes de escribir un servicio adentro de un bucle,
  preguntarse qué crea de una sola pieza** —un recibo, un correlativo, un
  expediente— y si sus controles miran el conjunto o solo la llamada.
- **EL RECIBO DEL APROVECHAMIENTO ES UNO POR TRÁMITE, y se emite AL ENVIAR A
  REVISIÓN.** No es uno por depósito: la persona entrega sus boletas —una o
  cinco— y se lleva un papel con el total, igual que el recibo oficial del modelo
  anterior. Por eso `pagos.recibo_id` es **nullable**: el depósito se carga
  mientras el trámite está PENDIENTE y todavía no hay papel que ponerle.
  `CobrarService::registrarDepositos()` lo deja en NULL y
  `CobrarService::emitirRecibo()` lo llena desde `RevisarCupoService::enviar()`,
  **dentro de su misma transacción** —un recibo emitido sobre un cupo que se
  quedó en pendiente sería un papel por un expediente que nadie presentó—. Un
  reenvío no emite un segundo papel: `emitirRecibo()` solo toma los pagos
  sueltos. En **Caja** sigue saliendo en el acto, que es lo correcto ahí: se
  cobra y se entrega en el mismo movimiento.
- **`method_exists($enum, 'admitePagos')` deja pasar todo cuando el enum llama a
  ese método de otra forma.** `EstadoGuia` declara `admitePagos()` y
  `EstadoAprovechamiento` declara `permitePagos()`: la comprobación de
  `CobrarService::resolver()` contesta «no existe» para el cupo y **no valida
  ningún estado**, en silencio. Un `method_exists` sobre un nombre que solo
  algunas clases usan no es una comprobación: es un `if` apagado. Va contra el
  MODELO, que sí expone `admitePagos()` en los tres. **La forma correcta cuando
  solo algunas clases contestan que sí**: declarar el método en el trait
  compartido con un `false` por defecto y sobreescribirlo donde aplique — ver
  `Pagable::admiteControlDePagos()`.
- **`pagos.estado_validacion` NO es el estado del pago: es el de su CONTROL.**
  El dinero entró o no entró, y eso lo dice que la fila exista; esto dice si
  alguien MIRÓ la boleta contra el extracto del banco. Tres consecuencias que se
  olvidan: un **OBSERVADO sigue sumando** en `montoPagado()` —sacarlo dejaría al
  trámite sin cubrir por una observación que puede estar equivocada—; **un
  observado no se valida, se corrige** —`admiteControl()` solo deja pasar lo que
  nadie miró, así que el botón «Validar» no existe sobre él—; y al corregir se
  borra el control ENTERO, `validado_por` y `validado_en` incluidos, porque quien
  validó lo hizo sobre otros números. El control es parte de la REVISIÓN: solo
  corre EN REVISIÓN, y `RevisarCupoService::aprobar()` exige que no quede ninguna
  sin validar — sin eso, validar sería decorativo.
- **Un `LIKE` sobre una columna JSON no encuentra nada con tildes.** Laravel
  guarda el JSON con `json_encode` por defecto, que escapa los acentos a
  `\uXXXX` y las barras a `\/`: buscar «Pérez» no encuentra `P\u00e9rez`, y
  falla EN SILENCIO —devuelve cero filas, como si no existiera—. Justo con los
  apellidos de acá. El término se escapa igual antes de comparar:
  `trim(json_encode($termino), '"')`. Ver `AsociacionController::comoEnElJson()`.
- **`->withQueryString()`** en todo paginador con filtros, o al cambiar de página
  se pierden.
- **NADA AVISA SI FALTA CORRER UNA MIGRACIÓN O UN SEEDER.** Una tabla, un
  permiso o una clave de configuración nuevos existen en el código pero no en la
  base de trabajo hasta que alguien corre el comando. **Al agregar una
  migración, un permiso al enum `RolSistema` o una clave a
  `ConfiguracionSeeder`, hay que correr a mano:**

  ```sh
  php artisan migrate
  php artisan db:seed --class=RolPermisoSeeder    # si tocaste RolSistema
  ```

  Y después abrir la pantalla en el navegador. Pasó con la tabla `recibos`: la
  pantalla reventaba con `relation "recibos" does not exist` con el código ya
  escrito y correcto.
- **DomPDF no es un navegador.** No entiende flexbox, grid ni variables CSS, y no
  ejecuta JavaScript: los documentos se maquetan con tablas y `position:
  absolute`. Además su `opacity` es poco confiable —según la versión lo ignora y
  un sello de agua sale a pleno color tapando el texto—, así que la atenuación va
  horneada en el PNG. Y las imágenes van **embebidas en base64**: una ruta se
  resuelve contra el disco con las restricciones de `chroot` y en producción
  termina en un recuadro vacío. Ojo con el peso: embeber los PNG del panel hacía
  un PDF de 5,4 MB por recibo; hay copias a medida en `public/image/recibo-*.png`.
- **`line-height` NO manda sobre el alto de una línea en DomPDF.** Se arma con
  las métricas de la fuente, y la diferencia es grande: dos líneas de título a
  7,6 pt con `line-height: 1.15` —8,7 pt cada una por la hoja de estilos—
  **medidas en el PDF ocupaban 12,6**, así que el bloque terminaba 8 pt más
  abajo de lo calculado y se comía el renglón siguiente. En una maqueta de
  coordenadas fijas el alto va **declarado** con `height`. Y se comprueba
  midiendo el PDF, no mirándolo: `page.get_text('blocks')` de PyMuPDF da la caja
  de cada bloque en puntos. Ver el dorso del carnet.
- **Blade escapa las entidades HTML de su interpolación de dos llaves.** Un
  `&nbsp;` puesto ahí se imprime como texto literal `&nbsp;` en el PDF. Se
  resuelve con un elemento de ancho fijo, no con la entidad.
- **`simplesoftwareio/simple-qrcode` NO PUEDE generar PNG acá.** Su salida PNG
  exige la extensión `imagick`, que no está instalada (`php -m` lista `gd`), y
  revienta con «Extension 'Imagick' is required». Solo le queda SVG, y un QR es
  justamente donde no conviene depender de un renderizador aproximado: medio
  punto de corrimiento y la cámara deja de leerlo, cosa que no se descubre hasta
  que alguien intenta verificar un carnet en la calle. El QR se arma con
  `App\Support\CodigoQr`, que pide la matriz a BaconQrCode —la librería que ese
  paquete trae adentro— y la pinta con `gd`.
- **El atributo `width` de un `<img>` se mide en PÍXELES, no en puntos.** En
  una maqueta donde todo lo demás va en `pt`, un `width="44"` sale de **33 pt**
  —un 25% más chico— y no lo marca nada. Mordió con el QR de los documentos: el
  módulo quedó en 0,31 mm cuando se había calculado 0,38, al borde de lo que una
  cámara engancha. La medida va en `style="width: 44pt; height: 44pt"`. Y se
  comprueba midiendo el PDF: `page.get_image_info()` de PyMuPDF da la caja real
  de cada imagen en puntos.
- **`route()` ABSOLUTA usa el host de LA PETICIÓN, no `APP_URL`.** Es lo que
  hay que tener presente en cualquier URL que quede IMPRESA: un operador que
  entre al panel por `http://192.168.1.50:8000` imprimiría carnets con el QR
  apuntando a esa IP, muerto para cualquier teléfono fuera de la red — y eso se
  descubre recién en la calle. Se arma la ruta RELATIVA y se le pega el dominio
  configurado: `rtrim(config('app.url'), '/').route('x', $p, false)`. Ver
  `App\Support\QrVerificacion`.
- **Un QR que se DIBUJA no es un QR que se LEE, y sacarlo del PDF con el xref
  miente.** Extraída así —`fitz.Pixmap(doc, xref)` de PyMuPDF— la imagen vuelve
  reescalada y suavizada, y **no decodifica aunque el documento esté
  perfecto**: costó un diagnóstico equivocado en el dorso del carnet. Se
  rasteriza la PÁGINA a 300 dpi, se recorta el QR y se decodifica eso, que es lo
  que ve la cámara. Y conviene medir hasta dónde aguanta —desenfoque y
  reducción— en vez de darlo por bueno porque se ve bien.
- **Un PNG de PALETA devuelve ÍNDICES, no colores.** `imagecolorat()` sobre una
  imagen de paleta no da el RGB sino la posición en la tabla, así que medir
  brillo o transparencia con esos números da resultados absurdos —el sello del
  recibo daba «luminancia 27, oscuro» cuando en realidad era casi blanco—. Hay
  que pasar por `imagecolorsforindex()`, o convertir primero con
  `imagepalettetotruecolor()`. `imageistruecolor()` dice cuál de los dos es.
- **El sello de agua del recibo se aclara u oscurece REGENERANDO EL PNG**, no con
  CSS: `opacity` no es confiable en DomPDF. Se mezcla `sedag.png` contra blanco
  con un factor —0,25 hoy— y se guarda **en paleta**, o el archivo se cuadruplica
  y va embebido en cada recibo. La receta está en el comentario de `.sello` de
  `recibo-oficial.blade.php`.
- **`rgba()` en DomPDF: la 3.1.6 SÍ lo respeta, comprobado.** La regla vieja
  —«depende de la versión»— sigue valiendo como advertencia, pero no como
  prohibición: se midió el PDF y el relleno del cuadro del SEDAG sale mezclado
  con el verde de abajo, no opaco. Antes de descartar una propiedad moderna de
  CSS conviene **probarla y medir el archivo**, que sale más barato que la
  maniobra para evitarla. Lo mismo pasó con `border-radius`, que la 3.1.6 dibuja
  bien.
- **DomPDF no dibuja degradados ni contornos de texto.** En el carnet, el degradado verde y el sello van HORNEADOS
  en `carnet-fondo.png`, y el blanco al 85% de la pantalla va como color sólido
  ya mezclado sobre el verde.

  Para recolorear el verde, el PNG **no se regenera** —habría que rehacer la
  mezcla del sello—: se le aplica un ajuste en HSV al archivo entero.
  Multiplicar el RGB a secas apaga el verde hacia el oliva.
- **DomPDF no dibuja contornos de texto.** No tiene `-webkit-text-stroke` ni
  `text-shadow`, y lo escrito con ellas se dibuja sin contorno y sin avisar. El
  perfilado se hace a mano dibujando el texto **cinco veces** —cuatro copias del
  color del borde corridas hacia cada esquina, y la cara encima—. Vive en
  `views/documentos/partes/texto-perfilado.blade.php`, que pone el andamio; el
  color, el cuerpo y el corrimiento los pone la hoja de estilos de quien la
  incluye. En el navegador alcanza con `text-shadow` en cuatro direcciones.

  **El corrimiento se elige contra el CUERPO, no contra el gusto: más o menos un
  6%.** El título del carnet va a 11 pt corrido 0,5; los rótulos, a 4,6 corridos
  0,3. Medio punto sobre 4,6 pt no perfila —engorda la letra hasta cerrarle los
  huecos, la «O» se llena y la «E» se vuelve una mancha— y el texto deja de
  leerse, que es lo contrario de lo que el contorno viene a hacer. Y en el
  navegador tampoco sirve `-webkit-text-stroke`: su trazo va CENTRADO sobre el
  contorno, así que la mitad se come el relleno.
- **En el carnet, el RÓTULO del segundo par también tiene un ancho fijo, y nadie
  lo mide.** El cálculo de encogido de `texto()` protege a los VALORES: si un
  nombre no entra, se achica. Los rótulos no pasan por ahí —son constantes— así
  que uno largo se desborda en silencio sobre lo que tenga al lado. Pasó con
  «PROVINCIA»: a 6,1 pt bold mide 33,2 pt en una caja de 32, y en el PDF salía
  «PROVINCIACercado» pegado. Se abrevia a «PROV.». Antes de agregar un rótulo al
  segundo par, medirlo: **caracteres × 0,605 × cuerpo**.
- **`ANCHO_POR_CARACTER` del carnet está atado al GRUESO de la letra de la
  tira.** Es el número con el que `CarnetImpresionController::texto()` decide si
  un nombre entra o hay que achicarlo, y hay que moverlo si ese grueso cambia.
  Medido sobre las DejaVu Sans que embebe DomPDF: la **regular** promedia 0,539
  em por carácter, la **negrita** 0,605, y el peor caso —puras mayúsculas— 0,67.
  Se deja un ~2% de margen sobre el promedio a propósito: quedarse corto es peor
  que pasarse, porque lo que no entra lo recorta el `overflow: hidden` de la tira
  y ahí se pierden apellidos.
- **Un correlativo por AÑO no sirve para un talonario de papel.**
  `CorrelativoService` lleva `(serie, anio)` y reinicia cada enero, que es lo
  incorrecto para una hoja preimpresa: el talonario del SEDAG va en `002190` y
  no volvió a 1. Se resuelve guardando la serie bajo el **año 0**, que ninguna
  gestión real ocupa; ver `CorrelativoService::siguienteContinuo()`. Hoy los dos
  documentos que se imprimen —recibo y permiso de faena— son continuos y de seis
  dígitos; lo único que sigue contando por gestión es el número de registro del
  carnet, que no va en ningún papel. Antes de elegir la serie, preguntarse si
  ese número lo reinicia alguien de verdad.
- **DomPDF no rota texto: no tiene `transform` ni `writing-mode`.** Lo escrito
  con ellas sale horizontal y sin avisar, y en una columna de 16 pt eso se
  desborda sobre la vecina. Los rótulos rotados del cuadro D de la guía se
  dibujan girados con GD y entran como PNG, igual que el QR. Ver
  `App\Support\TextoVertical`.
- **En CSS el `padding` SUMA al `width`**, y en una maqueta de coordenadas fijas
  eso descoloca sin avisar. **En una GRILLA se paga por columna, y ahí el error
  se multiplica:** el cuadro D de la guía tiene quince, así que sus 2 pt de cada
  lado son 60 pt —el cuadro declaraba 544 y cerraba en 603, 25 fuera del papel—.
  Se mide con `page.get_text('blocks')` de PyMuPDF: el borde derecho de la hoja
  es un número, no una impresión. **Volvió a morder al partir un renglón en dos:** la
  tira del rubro se declaró de 71 pt pensando que cerraba en 118,5, y con sus
  4 pt de relleno cerraba en 122,5 — así que el rótulo «CUPO», plantado en 121,
  salió impreso ENCIMA de la tira blanca. Al plantar una caja con coordenadas,
  el ancho declarado es el que se ocupa menos el relleno. Las tiras del carnet declaraban los 130 pt que tenían
  que ocupar MÁS 4 de relleno, así que terminaban 4 pt más allá de su columna:
  la tarjeta quedaba a 2,4 pt del borde derecho y a 8,5 del izquierdo. Nadie lo
  vio durante seis versiones porque las seis tiras desbordaban lo mismo —un error
  parejo se lee como un diseño—; saltó recién cuando un rótulo nuevo se les
  encimó. **Al plantar una caja con coordenadas, el ancho declarado es el que se
  ocupa menos el relleno.**
- **Al medir el contraste de un texto en un PDF, los píxeles del BORDE del
  glifo se cuentan como fondo y arruinan la cuenta.** El antialias deja una orla
  de tonos intermedios alrededor de cada letra; tomada como «fondo», su
  percentil 99 da un valor claro que **no existe en ninguna parte de la imagen**.
  Pasó midiendo el dorso del carnet: el peor caso daba 3,8:1 y no se movía por
  más que se oscureciera el fondo —porque lo que medía eran las letras—. El
  fondo real daba 5,3:1. Se dilata la máscara del texto y se mide **lejos** de
  ella: `MaxFilter(9)` de Pillow sobre la máscara, y fondo = lo que quede.
- **Un texto blanco puro puede verse GRIS, y no es un problema de color.** Es de
  grosor: a un cuerpo chico el trazo es tan fino que el ojo lo promedia con el
  fondo. Medirlo lo confirma —el núcleo del glifo da 255— así que subirle el
  blanco no toca la causa; **contra eso la única palanca es el cuerpo**. Los
  rótulos del carnet fueron 4,6 → 5,2 → 6,1 pt por eso.
- **Pero el cuerpo solo no alcanza sobre el verde del carnet: el contorno NO es
  opcional.** El fondo no es liso —abajo corre el sello del SEDAG, que le cambia
  el tono al texto según por dónde pase—, así que agrandar la letra sube el
  contraste medio y deja igual el peor caso. Se creyó que con cuerpo bastaba y
  se les sacó el borde a los rótulos; medido después, en blanco puro daban
  **3,3:1** contra los tramos claros del sello, y así desaparecen impresos con
  poco tóner. Con el contorno, el anillo oscuro que rodea cada glifo da
  **13,6:1** y ese número no depende del fondo. **Las dos palancas son
  distintas y hacen falta las dos**; la tercera, cuando el bloque es de texto
  corrido y no lleva tiras blancas encima, es un velo `rgba` sobre el fondo
  —ver el dorso—.
- **DomPDF no tiene `object-fit`.** Una foto vertical metida en un recuadro
  cuadrado con `width` y `height` fijos sale APLASTADA, y en un documento de
  identidad eso es justamente lo que no puede pasar. El `cover` se hace a mano:
  la imagen se dibuja a su proporción real desbordando el recuadro, corrida con
  un margen negativo, y el contenedor con `overflow: hidden` la recorta. En un
  retrato el corte va a un TERCIO y no a la mitad: la cara está arriba.
- **La foto del beneficiario se REDUCE antes de embeberla.** Sale del teléfono
  de ventanilla con varios megapíxeles, y embebida entera hacía un carnet de
  442 KB para dibujar un cuadrito de 17 mm. Ver
  `CarnetImpresionController::reducir()`.
- **`iframe.onLoad` NO dispara con un PDF.** Medido: con el visor de PDF del
  navegador el evento no llega nunca, así que un «cargando…» que dependa solo de
  él se queda colgado. Y `iframe.contentWindow.print()` sobre un PDF lo ignora o
  lo bloquea según la versión — para imprimir se usa la barra del propio visor o
  una pestaña aparte.
- **En un documento impreso, un texto que no entra se ACHICA; no se corta.** El
  `truncate` de la pantalla acá pierde datos: un nombre recortado se queda sin
  los apellidos, que es lo que identifica a la persona en un control. Ver
  `CarnetImpresionController::texto()`. Tres cosas que costaron una vuelta cada
  una: el ancho contra el que se mide es el de la CAJA menos su relleno, no el
  de la columna —medido sobre la columna entran treinta caracteres menos de los
  que el cálculo cree—; al pasar a dos líneas hay que descontar un ~15%, porque
  las palabras no se parten y la primera línea deja sobrante; y el ALTO de la
  caja tiene que crecer con las líneas, porque con alto fijo la segunda sale
  cortada por la mitad, que se ve peor que si nunca hubiera entrado.
- **Un `position: absolute` de una segunda página se dibuja sobre la primera**
  si su contenedor no es `position: relative`: sin contenedor posicionado se mide
  contra la página y aterriza encima de lo anterior. Por eso la carilla del
  carnet es un `.carilla` relativo aunque hoy haya una sola.
- **Lo que se imprime en un documento tiene que ser lo que NO cambia.** Es el
  criterio, y conviene ver cómo se aplicó en los dos sentidos, porque la
  respuesta se dio vuelta con el cambio de modelo.

  Con el carnet viejo —uno por persona, con los rubros colgados— el plástico NO
  llevaba ni los rubros ni el cupo: una adición posterior dejaba vieja la lista
  impresa y el documento pasaba a decir MENOS de lo que la persona podía hacer.
  Hoy el carnet es de UNA actividad que no cambia nunca, así que **la actividad
  y el cupo van impresos** — y hacen falta, porque sin la actividad dos carnets
  de la misma persona son plásticos idénticos.

  Lo que sigue sin imprimirse es el ESTADO: un carnet se revoca o queda sin
  efecto después de impreso y la tarjeta no se entera. Mismo criterio que la
  copia congelada del recibo, mirado desde el otro lado.

  **La lección no es «imprimir todo» ni «imprimir poco»: es preguntarse qué
  puede cambiar después de que el plástico salga de la impresora.**

---

## Lo que falta

Tres módulos: Reportes, Configuración y Usuarios (hoy se crean por consola).
Los dos primeros aparecen en gris en el menú lateral. La lista completa, con los
problemas conocidos que siguen abiertos, está en
[docs/PENDIENTES.md](docs/PENDIENTES.md).

---

## Al terminar, actualizá la documentación

Estos archivos existen para que nadie —persona o agente— tenga que leer el
sistema entero para entenderlo. Eso solo se sostiene si se mantienen:

| Cambiaste... | Actualizá |
| --- | --- |
| Una regla de negocio, el esquema, un flujo | `docs/ARQUITECTURA.md` |
| Agregaste o cambiaste un archivo a fondo | `docs/MAPA-ARCHIVOS.md` |
| Un módulo entero | `docs/modulos/<MODULO>.md` |
| Faenas o guías | `docs/modulos/PERMISOS-OPERATIVOS.md` |
| Pagos o su control | `docs/modulos/PAGOS.md` |
| Resolviste o encontraste un problema | `docs/PENDIENTES.md` |
| Cualquier cosa | `docs/sesiones/MM-AAAA/AAAA-MM-DD.md` |

Y si tropezaste con algo que te hizo perder tiempo y no estaba anotado, va a
«Trampas conocidas» de este archivo. Es la sección que más tiempo ahorra.
