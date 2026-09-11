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
│   ├── EstadoTramite.php       recibido → en_revision → aprobado → ...
│   ├── EstadoDocumento.php     vigente | vencido | anulado
│   ├── FormaPago.php           efectivo | qr | transferencia
│   ├── CategoriaDocumento.php  certificacion | credencial | permiso | licencia
│   ├── Periodicidad.php        unico | mensual | anual
│   └── RolSistema.php          los 4 roles y TODOS sus permisos
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/           Iniciar y cerrar sesión
│   │   ├── Panel/          ← ADMINISTRACIÓN (pide sesión)
│   │   │   ├── DashboardController.php
│   │   │   └── SolicitanteController.php    ← la plantilla a copiar
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
├── Services/
│   └── CorrelativoService.php   Numeración DOC-PESCA-2026-0001 sin repetidos
├── Support/
│   └── Sql.php             Lo que cambia entre PostgreSQL y SQLite
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
├── web.php        Solo la raíz + incluye a los otros tres. Laravel carga ESTE.
├── publico.php    /verificar          sin sesión
├── auth.php       /login  /logout
└── panel.php      /panel/...          con sesión y con permisos
```

Cada ruta del panel declara el permiso que exige:

```php
Route::get('/solicitantes', [SolicitanteController::class, 'index'])
    ->middleware('permiso:solicitantes.ver')
    ->name('solicitantes.index');
```

**El orden importa.** `/solicitantes/crear` tiene que ir antes de
`/solicitantes/{solicitante}`, o Laravel tomaría la palabra "crear" como si
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
│   │   └── solicitantes/
│   │       ├── index.tsx       listado
│   │       ├── crear.tsx       formulario de alta
│   │       ├── editar.tsx      formulario de edición
│   │       └── ver.tsx         ficha
│   └── publico/            ← VISTA PÚBLICA
│       └── verificar.tsx
│
├── layouts/                El marco que envuelve a las pantallas
│   ├── layout-panel.tsx        con barra lateral y menú
│   └── layout-publico.tsx      limpio, sin datos internos
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
│   │   ├── dashboard/              los 6 bloques del panel principal
│   │   └── solicitantes/           tabla, filtros, formulario
│   │
│   └── publico/                solo vista pública
│       ├── buscador-codigo.tsx
│       └── ficha-documento.tsx
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
    ├── solicitantes.ts         tipos del módulo Solicitantes
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
tabla-solicitantes.tsx      ✓
TablaSolicitantes.tsx       ✗
```

**2. Los componentes de `ui/` conservan su nombre en inglés.**

`Button`, `Card`, `Input`, `Label`, `Badge`, `Select`, `Textarea` son el
vocabulario estándar de React: cualquier tutorial que se busque los llama así.
Todo lo demás —lo que es propio de este sistema— va en español: `Campo`,
`Paginacion`, `EstadoVacio`, `ConfirmarAccion`, `TablaSolicitantes`.

**3. Las columnas nuevas de `solicitantes` van en camelCase.**

`primerNombre`, `segundoNombre`, `apellidoPaterno`, `apellidoMaterno`,
`apellidoCasada`, `fechaNacimiento`. Es la única tabla así, y conserva
`ci_nit` y `complemento` con guión bajo.

Consecuencia práctica: **en PostgreSQL esas columnas necesitan comillas dobles
en toda consulta escrita a mano.**

```sql
SELECT primerNombre FROM solicitantes;     -- ERROR: column "primernombre" does not exist
SELECT "primerNombre" FROM solicitantes;   -- así sí
```

Laravel y Eloquent no se ven afectados porque entrecomillan solos. El que lo
paga es quien abra pgAdmin.

---

## Base de datos (`database/`)

```
database/
├── migrations/     La estructura de las tablas, en orden cronológico
├── seeders/
│   ├── RolPermisoSeeder.php    los 4 roles con sus permisos
│   ├── ConfiguracionSeeder.php datos de la institución, editables desde el panel
│   ├── AreaSeeder.php          las 6 áreas con sus trámites y tarifas
│   ├── UsuarioSeeder.php       un usuario por rol
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

## Pruebas (`tests/`)

```
tests/Feature/
├── AccesoTest.php        login, logout, bitácora, verificación pública
└── SolicitanteTest.php   el módulo completo + los índices de la base
```

```sh
php artisan test
```

Corren sobre SQLite en memoria (ver `phpunit.xml`), así que no tocan la base de
desarrollo y tardan segundos.

---

## Documentación (`docs/`)

```
docs/
├── ESTRUCTURA.md   este archivo: dónde va cada cosa
├── GUIA-INERTIA.md cómo se conectan Laravel y React, paso a paso
├── INSTALACION.md  levantar el proyecto en una máquina nueva
├── PENDIENTES.md   qué falta y qué problemas siguen abiertos
└── sesiones/       bitácora de trabajo
    ├── _plantilla.md   el formato a copiar
    └── MM-AAAA/        una carpeta por mes
        └── AAAA-MM-DD.md   un archivo por día trabajado
```

**Toda sesión de trabajo se registra** en `sesiones/MM-AAAA/AAAA-MM-DD.md`,
copiando `_plantilla.md`. Cada trabajo lleva el problema, la tabla de archivos
modificados y la solución con su porqué.

El último bloque de cada archivo es el **informe para presentación**, y se
escribe distinto al resto: en lenguaje simple, sin términos técnicos, porque se
copia a Word y lo lee gente que no programa. No es un resumen del documento —es
la misma historia contada para otro lector.
