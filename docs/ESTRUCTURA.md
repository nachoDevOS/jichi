# Estructura del proyecto

Mapa de dónde está cada cosa y, sobre todo, **dónde va un archivo nuevo**.

---

## La regla que ordena todo

El sistema tiene dos mitades que no se mezclan:

- **Panel** — administración interna, requiere sesión. Todo lo que sea del panel
  vive en una carpeta `panel/`.
- **Público** — lo que ve el ciudadano sin login. Todo va en una carpeta
  `publico/`.

Esa división se repite igual en el backend y en el frontend. Si sabés de qué
mitad es lo que estás escribiendo, ya sabés dónde ponerlo.

---

## Backend (`app/`)

```
app/
├── Enums/                  Los valores fijos del negocio
│   ├── TipoTramite.php          emision_inicial | actualizacion
│   ├── EstadoTramite.php        pendiente → en_revision → aprobado | rechazado
│   │                            (y las transiciones que valen desde cada uno)
│   ├── EstadoCarnet.php         vigente | vencido | anulado
│   ├── EstadoRubro.php          activo | inactivo
│   ├── FormaPago.php            deposito | efectivo  (casillas del recibo)
│   ├── ConceptoRecibo.php       las 6 casillas de DESCRIPCIÓN del recibo
│   └── RolSistema.php           los roles y TODOS sus permisos
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/           Iniciar y cerrar sesión
│   │   ├── Panel/          ← ADMINISTRACIÓN (pide sesión)
│   │   │   ├── DashboardController.php
│   │   │   ├── BeneficiarioController.php   ← la plantilla a copiar
│   │   │   ├── TramiteController.php        el circuito del expediente
│   │   │   ├── PagoController.php           libro de caja + alta de depósito
│   │   │   ├── CarnetController.php         consulta, suspensión, anulación
│   │   │   ├── RubroController.php          catálogo de actividades
│   │   │   └── ReciboController.php         el PDF del talonario del SEDAG
│   │   └── Publico/        ← VISTA PÚBLICA (sin sesión)
│   │       └── VerificacionController.php
│   │
│   ├── Requests/           Las reglas de validación de cada formulario
│   │   ├── Auth/
│   │   └── Panel/
│   │
│   └── Middleware/
│       └── HandleInertiaRequests.php   Lo que se manda a TODAS las pantallas
│
├── Models/                 Una clase por tabla de la base de datos
│
├── Exceptions/
│   └── SolicitudInvalidaException.php  Una regla de negocio dijo que no.
│                                       Sus mensajes salen tal cual en pantalla.
├── Services/               ← ACÁ VIVEN LAS REGLAS, no en los controladores
│   ├── SolicitudCarnetService.php  EL CASO DE USO CENTRAL: Reglas A, B y C
│   ├── PagoTramiteService.php      pagos parciales (1 a N) de un trámite
│   ├── ArchivoTramiteService.php   subir/descartar adjuntos alrededor de una
│   │                               transacción (el disco no hace rollback)
│   ├── ReciboTramiteService.php    emite el RECIBO OFICIAL al pasar a revisión
│   └── CorrelativoService.php      Numeración sin repetidos. Lo usa el recibo
├── Support/
│   ├── Sql.php             Lo que cambia entre PostgreSQL y SQLite
│   ├── Archivos.php        Armar el enlace de un adjunto (ruta local o s3)
│   ├── SituacionCarnet.php Qué tiene esta persona en esta gestión
│   └── Paginacion.php      Los tamaños de página permitidos
└── Traits/
    └── Auditable.php       Bitácora automática de altas, cambios y bajas
```

### Dónde va un archivo nuevo del backend

| Estoy escribiendo... | Va en |
| --- | --- |
| Una pantalla del panel | `app/Http/Controllers/Panel/` |
| Algo que ve el ciudadano | `app/Http/Controllers/Publico/` |
| Las reglas de un formulario | `app/Http/Requests/Panel/` |
| Una lista de valores fijos | `app/Enums/` |
| Lógica que usan varios controladores | `app/Services/` |

---

## Rutas (`routes/`)

```
routes/
├── web.php        Solo incluye a los otros tres. Laravel carga ESTE.
├── publico.php    /  y  /verificar    sin sesión
├── auth.php       /login  /logout
└── panel.php      /panel/...          con sesión y con permisos
```

Cada ruta del panel declara el permiso que exige:

```php
Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
    ->middleware('permiso:beneficiarios.ver')
    ->name('beneficiarios.index');
```

**El orden importa.** `/beneficiarios/crear` tiene que ir antes de
`/beneficiarios/{beneficiario}`, o Laravel tomaría la palabra "crear" como si
fuera un id.

---

## Frontend (`resources/js/`)

```
resources/js/
├── app.tsx                 Punto de entrada. Arranca Inertia y React.
│
├── pages/                  UNA PANTALLA = UN ARCHIVO
│   ├── auth/
│   │   └── login.tsx
│   ├── panel/              ← ADMINISTRACIÓN
│   │   ├── dashboard.tsx
│   │   ├── beneficiarios/      index · crear · editar · ver
│   │   ├── tramites/           index · crear · ver · editar
│   │   ├── carnets/            index · ver
│   │   ├── pagos/              index (libro de caja)
│   │   └── rubros/             index · crear · editar
│   └── publico/            ← VISTA PÚBLICA
│       ├── inicio.tsx          la portada institucional (/)
│       └── verificar.tsx       el acta de verificación por QR
│
├── layouts/                El marco que envuelve a las pantallas
│   ├── layout-panel.tsx        con barra lateral y menú
│   ├── layout-publico.tsx      el acta: angosta, verde, imprimible
│   └── layout-institucional.tsx  la portada: ancha, azul, con nav y pie
│
├── components/             Piezas reutilizables
│   ├── ui/                     genéricas, sirven en cualquier lado
│   │   ├── button.tsx  card.tsx  input.tsx  label.tsx  select.tsx
│   │   ├── textarea.tsx  badge.tsx
│   │   ├── campo.tsx           etiqueta + control + mensaje de error
│   │   ├── paginacion.tsx      la barra de páginas de cualquier listado
│   │   ├── estado-vacio.tsx    "todavía no hay nada acá"
│   │   └── confirmar-accion.tsx  ventana de "¿seguro?"
│   │
│   ├── comunes/                usadas por el panel Y por lo público
│   │   ├── logo-jichi.tsx
│   │   └── toggle-apariencia.tsx
│   │
│   ├── panel/                  solo administración
│   │   ├── layout/                 barra-lateral, barra-superior, navegacion
│   │   ├── dashboard/              los bloques del panel principal
│   │   ├── beneficiarios/          formulario compartido por alta y edición
│   │   ├── tramites/               buscador de beneficiario, campo de pagos
│   │   └── rubros/                 formulario compartido por alta y edición
│   │
│   └── publico/                solo vista pública
│       ├── hoja-oficial.tsx        el papel con membrete
│       ├── buscador-codigo.tsx     código + firma escritos a mano
│       ├── ficha-carnet.tsx        el acta de verificación
│       ├── splash-verificacion.tsx
│       └── institucional/         las piezas de la PORTADA (/)
│           ├── cabecera.tsx           escudo, anclas y acceso al panel
│           ├── pie.tsx                contacto y enlaces
│           ├── franja-tricolor.tsx    la franja de los documentos oficiales
│           ├── seccion.tsx            el envoltorio de cada bloque
│           ├── hero.tsx               portada
│           ├── servicios.tsx          los cuatro trámites
│           ├── pasos.tsx              el circuito del beneficiario
│           ├── verificacion.tsx       reusa buscador-codigo.tsx
│           ├── preguntas.tsx          acordeón
│           └── contacto.tsx           dónde se atiende
│
├── hooks/                  Lógica reutilizable de React
│   ├── use-apariencia.ts       modo claro / oscuro
│   ├── use-flash.ts            mensajes de Laravel → avisos flotantes
│   └── use-permisos.ts         ¿el usuario puede hacer esto?
│
├── lib/                    Funciones sueltas, sin React
│   ├── utils.ts                bs(), fecha(), fechaHora(), iniciales(), cn()
│   └── graficos.ts             paleta y estilos de los gráficos
│
└── types/                  La forma de los datos que manda Laravel
    ├── index.d.ts              lo compartido por todo el sistema
    ├── dashboard.ts            tipos del panel principal
    ├── beneficiarios.ts        tipos del módulo Beneficiarios
    ├── tramites.ts             tipos del módulo Trámites
    ├── carnets.ts              tipos del módulo Carnets
    ├── rubros.ts               tipos del catálogo de rubros
    ├── pagos.ts                tipos del libro de caja
    └── publico.ts              tipos de la vista pública
```

### Dónde va un archivo nuevo del frontend

| Estoy escribiendo... | Va en |
| --- | --- |
| Una pantalla nueva del panel | `pages/panel/<modulo>/` |
| Una pantalla pública | `pages/publico/` |
| Un botón, input o tarjeta genérico | `components/ui/` |
| Una tabla o formulario de un módulo | `components/panel/<modulo>/` |
| Algo que usan el panel Y lo público | `components/comunes/` |
| Los tipos de un módulo | `types/<modulo>.ts` |

---

## Tres convenciones de nombres

**1. Los archivos van en español, en minúsculas y con guiones.**

```
formulario-beneficiario.tsx      ✓
FormularioBeneficiario.tsx       ✗
```

**2. Los componentes de `ui/` conservan su nombre en inglés.**

`Button`, `Card`, `Input`, `Label`, `Badge`, `Select`, `Textarea` son el
vocabulario estándar de React: cualquier tutorial que se busque los llama así.
Todo lo demás —lo que es propio de este sistema— va en español: `Campo`,
`Paginacion`, `EstadoVacio`, `ConfirmarAccion`, `BuscadorBeneficiario`.

**3. Las columnas nuevas de `beneficiarios` van en camelCase.**

`primerNombre`, `segundoNombre`, `apellidoPaterno`, `apellidoMaterno`,
`apellidoCasado`, `fechaNacimiento`. Es la única tabla así, y conserva
`ci_nit` y `complemento` con guión bajo.

Consecuencia práctica: **en PostgreSQL esas columnas necesitan comillas dobles
en toda consulta escrita a mano.**

```sql
SELECT primerNombre FROM beneficiarios;     -- ERROR: column "primernombre" does not exist
SELECT "primerNombre" FROM beneficiarios;   -- así sí
```

Laravel y Eloquent no se ven afectados porque entrecomillan solos. El que lo
paga es quien abra pgAdmin.

---

## Base de datos (`database/`)

> **Una tabla nueva del dominio toca SIETE lugares.** Se ve con el módulo de
> faenas y guías, que se agregó entero el 16/09/2026:
>
> | Dónde | Qué |
> | --- | --- |
> | `database/migrations/` | La tabla, **muy** comentada: es el mejor lugar para explicar el esquema |
> | `app/Enums/` | Sus estados y dominios cerrados, en columnas `string` |
> | `app/Models/` | El modelo, sus relaciones y las preguntas que sabe contestar |
> | `app/Services/` | Las reglas de negocio — **nunca en el controlador** |
> | `app/Http/Controllers/Panel/` + `Requests/` | La pantalla y su validación |
> | `resources/js/pages/panel/` + `types/` | El frontend |
> | `docs/MER.md` + `docs/ARQUITECTURA.md` + esta guía | La documentación |
>
> Y si la tabla cambia una existente —como `pagos` al volverse polimórfica—,
> hay que barrer lo que la usaba: relaciones, eager loading, tipos de
> TypeScript y componentes.


```
database/
├── migrations/     La estructura de las tablas, en orden cronológico
├── seeders/
│   ├── RolPermisoSeeder.php    el rol administrador con TODOS los permisos
│   ├── ConfiguracionSeeder.php datos de la institución, editables desde el panel
│   ├── RubroSeeder.php         el catálogo de actividades con sus tarifas
│   ├── UsuarioSeeder.php       la cuenta admin@admin.com
│   └── DemoSeeder.php          datos de prueba (NO corre en producción)
└── factories/      Generadores de datos falsos para las pruebas
```

---

## Configuración (`config/`)

Casi todos los archivos de `config/` son los que trae Laravel. Los propios son:

- **`config/jichi.php`** — los ajustes del sistema. Todo valor que venga del
  `.env` tiene que pasar por acá.

  > **Regla que no se rompe nunca:** `env()` solo dentro de `config/`. En el
  > resto del código, `config('jichi.lo_que_sea')`. Con `config:cache`
  > activo (que es lo normal en producción), `env()` devuelve `null` fuera de
  > `config/` y el error es silencioso.

- **`config/permission.php`** y **`config/dompdf.php`** — publicados por sus
  paquetes. Se dejan tal cual vienen, con sus comentarios originales: modificar
  un config publicado hace mucho más difícil comparar contra la versión nueva
  cuando el paquete se actualice.

---

## Verificación (`tests/`)

```
tests/
└── TestCase.php     la clase base. Feature/ quedó vacío
```

**Ya no hay pruebas automáticas.** `tests/Feature/` se vació el 14/09/2026 por
pedido del responsable del proyecto; eran 143 y cubrían el backend entero.

Lo que queda para verificar antes de dar algo por terminado:

```sh
npx tsc --noEmit        # tipos
./vendor/bin/pint       # formato
npm run build           # que compile
```

> ⚠️ Los tres revisan **tipos y formato**. De la lógica de negocio no dicen nada:
> que no se apruebe un trámite sin cobrar, que no se emitan dos carnets por
> gestión, que un recibo reimpreso conserve su número — eso hoy **no lo comprueba
> nada**. Todo cambio se verifica **abriendo la pantalla y probando el caso a
> mano**, incluidos los bordes.
>
> El andamiaje quedó (`phpunit.xml`, `TestCase.php`, PHPUnit instalado), así que
> volver a escribir una prueba es crear un archivo. Ver el punto 10 de
> [PENDIENTES.md](PENDIENTES.md).

---

## Documentación (`docs/`)

```
docs/
├── ARQUITECTURA.md  ← EMPEZAR ACÁ. Reemplaza a leer el código
├── MAPA-ARCHIVOS.md    qué hace cada archivo y qué tiene de no obvio
├── ESTRUCTURA.md       este archivo: dónde va un archivo NUEVO
├── GUIA-INERTIA.md     cómo se conectan Laravel y React, paso a paso
├── INSTALACION.md      levantar el proyecto en una máquina nueva
├── PENDIENTES.md       qué falta y qué problemas siguen abiertos
├── modulos/            un módulo en profundidad
│   └── RECIBOS.md          el RECIBO OFICIAL del SEDAG
└── sesiones/           bitácora de trabajo
    ├── _plantilla.md   el formato a copiar
    └── MM-AAAA/        una carpeta por mes
        └── AAAA-MM-DD.md   un archivo por día trabajado
```

**Los dos primeros existen para no tener que leer el sistema entero.** Si al
terminar un trabajo sabés algo que no está ahí, agregalo: es lo que hace que el
próximo no tenga que redescubrirlo.

**Toda sesión de trabajo se registra** en `sesiones/MM-AAAA/AAAA-MM-DD.md`,
copiando `_plantilla.md`. Cada trabajo lleva el problema, la tabla de archivos
modificados y la solución con su porqué.

El último bloque de cada archivo es el **informe para presentación**, y se
escribe distinto al resto: en lenguaje simple, sin términos técnicos, porque se
copia a Word y lo lee gente que no programa. No es un resumen del documento —es
la misma historia contada para otro lector.
