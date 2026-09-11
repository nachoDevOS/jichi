# Cómo Laravel habla con React en este proyecto

Guía para quien sabe Laravel y Blade pero recién arranca con React.

Todo lo que se explica acá está aplicado en el módulo **Solicitantes**, que es
la plantilla del sistema. Conviene leer esta guía con esos archivos al lado.

---

## 1. Qué cambia respecto de Blade

Con Blade el controlador pasa variables a una vista `.blade.php`:

```php
return view('solicitantes.index', ['solicitantes' => $solicitantes]);
```

Con Inertia el controlador pasa variables a un componente `.tsx`:

```php
return Inertia::render('panel/solicitantes/index', ['solicitantes' => $solicitantes]);
```

**Es la misma idea.** Cambia el motor que dibuja el HTML, nada más.

Lo que **no** cambia:

- Las rutas siguen en `routes/`
- Los controladores siguen siendo controladores de Laravel
- La validación sigue siendo un Form Request
- Eloquent sigue igual
- Los permisos, el middleware, los mensajes flash: todo igual

Lo que **sí** cambia:

- El archivo de la vista es `.tsx` en vez de `.blade.php`
- No hay `@foreach` ni `@if`: se usa JavaScript
- Al navegar no se recarga el navegador

**Lo que NO hay, y conviene saberlo desde el principio:** no hay una API REST de
por medio. No se escribe `fetch()` ni `axios` en ninguna parte del sistema. Ese
es justamente el sentido de Inertia.

---

## 2. El viaje completo de una petición

Sigamos qué pasa cuando alguien entra a `/panel/solicitantes`.

```
 NAVEGADOR                LARAVEL                        REACT
     │
     │  GET /panel/solicitantes
     ├───────────────────────►
     │                    routes/panel.php
     │                    encuentra la ruta
     │                         │
     │                    SolicitanteController@index
     │                    consulta la base con Eloquent
     │                         │
     │                    Inertia::render(
     │                      'panel/solicitantes/index',
     │                      ['solicitantes' => ...]
     │                    )
     │                         │
     │  ◄──────────────────────┤
     │   nombre de pantalla + datos
     │                                          resources/js/pages/
     │                                          panel/solicitantes/index.tsx
     │                                          recibe los datos como props
     │                                                  │
     │  ◄───────────────────────────────────────────────┤
     │                 pantalla dibujada
```

**El paso clave es la traducción del nombre:**

```
Inertia::render('panel/solicitantes/index')
                 └──────────┬───────────┘
                            ▼
    resources/js/pages/panel/solicitantes/index.tsx
```

El texto es la ruta del archivo dentro de `resources/js/pages/`, sin `.tsx`.
Nada más. No hay ninguna lista donde registrarlo: `app.tsx` los descubre todos
automáticamente con `import.meta.glob`.

---

## 3. De Laravel a React: las props

El array que se pasa como segundo argumento **es** el objeto de props que llega
a React. Clave por clave, con el mismo nombre.

**En PHP** (`app/Http/Controllers/Panel/SolicitanteController.php`):

```php
return Inertia::render('panel/solicitantes/index', [
    'solicitantes' => $solicitantes,
    'filtros'      => $filtros,
    'tipos'        => TipoSolicitante::opciones(),
]);
```

**En React** (`resources/js/pages/panel/solicitantes/index.tsx`):

```tsx
export default function IndiceSolicitantes({ solicitantes, filtros, tipos }: Props) {
    // ...
}
```

Las llaves `{ }` alrededor de los nombres son **desestructuración**: en vez de
recibir un objeto `props` y escribir `props.solicitantes`, se sacan las claves
directo por su nombre. Es equivalente a esto, pero más corto:

```tsx
export default function IndiceSolicitantes(props: Props) {
    const solicitantes = props.solicitantes;
    const filtros = props.filtros;
}
```

### Las props compartidas

Hay datos que hacen falta en TODAS las pantallas: quién está conectado, el
nombre de la institución, los mensajes flash. Pasarlos en cada controlador sería
repetir lo mismo cien veces.

Para eso está `app/Http/Middleware/HandleInertiaRequests.php`. Lo que devuelve
su método `share()` llega a todas las pantallas sin declararlo:

```tsx
import { usePage } from '@inertiajs/react';

const { auth, institucion } = usePage<PageProps>().props;
```

`usePage()` funciona desde cualquier componente, por profundo que esté. No hay
que ir pasando las props de padre a hijo.

---

## 4. De React a Laravel: los formularios

Acá va el ejemplo completo, con lo mínimo indispensable:

```tsx
import { useForm } from '@inertiajs/react';

const form = useForm({ nombres: '', apellidos: '' });

function enviar(e) {
    e.preventDefault();                    // sin esto el navegador recarga
    form.post(route('solicitantes.store'));
}

<form onSubmit={enviar}>
    <input
        value={form.data.nombres}
        onChange={(e) => form.setData('nombres', e.target.value)}
    />

    {form.errors.nombres && <p>{form.errors.nombres}</p>}

    <button disabled={form.processing}>Guardar</button>
</form>
```

Qué da `useForm`:

| | Para qué |
| --- | --- |
| `form.data` | Los valores actuales de los campos |
| `form.setData(campo, valor)` | Cambiar un campo |
| `form.post(url)` | Enviarlo (también `.put`, `.delete`) |
| `form.processing` | `true` mientras viaja. Sirve para bloquear el botón |
| `form.errors` | Los errores de validación de Laravel |

### La parte más importante: `form.errors` se llena solo

No hay que escribir NADA para conectar la validación:

1. El formulario hace POST a `/panel/solicitantes`
2. Laravel ejecuta `GuardarSolicitanteRequest` antes del controlador
3. Si falla, Laravel redirige de vuelta con los errores en la sesión
4. Inertia los detecta y los deja en `form.errors`
5. React los muestra

Es exactamente el `$errors` de Blade, con otro nombre.

### Por qué el controlador devuelve `redirect()` y no JSON

```php
return redirect()
    ->route('solicitantes.show', $solicitante)
    ->with('exito', 'Solicitante registrado correctamente.');
```

Inertia espera un redirect, igual que un formulario de Blade. Devolver JSON
rompería el flujo. Y ese `->with('exito', ...)` lo levanta el hook `useFlash()`
del layout y lo muestra como aviso flotante, sin escribir una línea más.

### Enviar archivos

PHP no sabe leer archivos en una petición `PUT`: solo los procesa en `POST`. Por
eso, para actualizar con foto, se manda un POST con un campo `_method` que
Laravel interpreta como PUT:

```tsx
form.transform((datos) => ({ ...datos, _method: 'put' }));
form.post(route('solicitantes.update', id), { forceFormData: true });
```

Está aplicado en `components/panel/solicitantes/formulario-solicitante.tsx`.

---

## 5. Los conceptos de React que hacen falta

Con estos cinco alcanza para todo lo que hay en este sistema.

### 5.1 Un componente es una función que devuelve HTML

```tsx
function Saludo({ nombre }) {
    return <p>Hola, {nombre}</p>;
}

<Saludo nombre="Rosa" />     // <p>Hola, Rosa</p>
```

Las llaves `{ }` dentro del HTML son "acá va JavaScript". Es el `{{ }}` de Blade.

**El nombre tiene que empezar con mayúscula.** En minúscula, React piensa que es
una etiqueta HTML común y no dibuja nada.

### 5.2 Listas: `.map()` en vez de `@foreach`

Blade:

```blade
@foreach ($solicitantes as $s)
    <tr><td>{{ $s->nombre_completo }}</td></tr>
@endforeach
```

React:

```tsx
{solicitantes.map((s) => (
    <tr key={s.id}><td>{s.nombre_completo}</td></tr>
))}
```

**`key` es obligatoria** y tiene que ser única y estable. Se usa el id de la base
de datos, **nunca** la posición del array: al filtrar u ordenar las posiciones
cambian y React confunde una fila con otra.

### 5.3 Condicionales: `&&` y `? :` en vez de `@if`

```tsx
{/* Mostrar solo si hay error */}
{errors.nombres && <p>{errors.nombres}</p>}

{/* Uno u otro */}
{activo ? <span>Activo</span> : <span>Inactivo</span>}
```

Lo que da `false`, `null` o `undefined` simplemente no se dibuja.

> **Cuidado con los números.** `{cantidad && <p>...</p>}` cuando `cantidad` es
> `0` imprime un `0` suelto en la pantalla, porque el cero no es `false` para
> React. Se escribe `{cantidad > 0 && <p>...</p>}`.

### 5.4 Estado: `useState`

Un dato que, al cambiar, hace que React vuelva a dibujar la pantalla.

```tsx
const [abierto, setAbierto] = useState(false);
//     ▲         ▲                     ▲
//     │         │                     └── valor inicial
//     │         └── la función para cambiarlo
//     └── el valor actual

<button onClick={() => setAbierto(true)}>Abrir</button>
```

**Solo para lo que es del navegador**: si un menú está abierto, qué pestaña se
está viendo, qué se escribió en un buscador. Los datos del negocio vienen de
Laravel como props y no se guardan en estado.

**Si dos componentes necesitan el mismo dato, ese dato sube al padre común.**
Se llama "levantar el estado" y está aplicado en `layouts/layout-panel.tsx`: el
menú abierto lo necesitan la barra lateral y el botón del encabezado, así que
vive en el layout, que es el padre de los dos.

### 5.5 Efectos: `useEffect`

Ejecutar algo "por fuera" del dibujado: un temporizador, escuchar el teclado,
liberar memoria.

```tsx
useEffect(() => {
    const t = setTimeout(() => buscar(texto), 350);

    return () => clearTimeout(t);   // ← LIMPIEZA
}, [texto]);
```

Se lee así:

- **El cuerpo** corre después de dibujar.
- **El array `[texto]`** dice cuándo volver a correrlo: cada vez que `texto`
  cambie.
- **El `return`** es la limpieza. React lo llama ANTES de volver a correr el
  efecto y al desmontar el componente.

Ese ejemplo es el buscador de solicitantes: cada tecla cancela el temporizador
anterior y arranca uno nuevo, así que solo sobrevive el último. Sin eso, escribir
"perez" dispararía cinco consultas a la base en vez de una.

**Olvidarse de la limpieza es la fuga de memoria más común en React.**

---

## 6. Navegar entre pantallas

Dentro del sistema **siempre** se usa `<Link>` de Inertia, nunca `<a>`:

```tsx
import { Link } from '@inertiajs/react';

<Link href={route('solicitantes.show', 42)}>Ver ficha</Link>
```

`<Link>` pide solo los datos nuevos y cambia el contenido sin recargar: no
parpadea ni vuelve a descargar los estilos. Un `<a>` recargaría todo.

Para acciones sin formulario se usa `router`:

```tsx
import { router } from '@inertiajs/react';

router.delete(route('solicitantes.destroy', 42));
router.post(route('logout'));
```

### `route()` es de Ziggy

Convierte el nombre de la ruta de Laravel en su URL:

```tsx
route('solicitantes.show', 42)   // '/panel/solicitantes/42'
```

Es la misma función `route()` de Blade. La inyecta la directiva `@routes` en
`resources/views/app.blade.php` y está disponible en cualquier archivo, sin
importarla.

**Nunca escribir URLs a mano.** Si mañana cambia el prefijo en
`routes/panel.php`, con `route()` no hay que tocar nada.

---

## 7. Agregar un módulo nuevo, paso a paso

Ejemplo: el módulo de **Trámites**. Es exactamente el patrón de Solicitantes.

### Backend

**1. El controlador** — `app/Http/Controllers/Panel/TramiteController.php`

Copiar `SolicitanteController.php` y adaptar. Los métodos son siempre los mismos:
`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`.

**2. La validación** — `app/Http/Requests/Panel/GuardarTramiteRequest.php`

**3. Las rutas** — en `routes/panel.php`, dentro del grupo que ya existe:

```php
Route::middleware('permiso:tramites.ver')->group(function () {
    Route::get('/tramites', [TramiteController::class, 'index'])
        ->name('tramites.index');
});
```

> Acordate del orden: `/tramites/crear` ANTES que `/tramites/{tramite}`.

Los permisos (`tramites.ver`, `tramites.crear`, ...) ya existen en
`app/Enums/RolSistema.php`. No hay que crearlos.

### Frontend

**4. Los tipos** — `resources/js/types/tramites.ts`

**5. Las pantallas** — `resources/js/pages/panel/tramites/`

```
index.tsx    crear.tsx    editar.tsx    ver.tsx
```

**6. Los componentes** — `resources/js/components/panel/tramites/`

```
tabla-tramites.tsx    filtros-tramites.tsx    formulario-tramite.tsx
```

### Y ya está

El ítem "Trámites" del menú lateral **se enciende solo**. Ya está declarado en
`components/panel/layout/navegacion.ts` y se muestra en gris únicamente porque
la ruta todavía no existe. En cuanto se declare, pasa a ser un enlace.

### Lo específico de Trámites

Además del CRUD, ese módulo tiene una máquina de estados que ya está escrita en
`app/Enums/EstadoTramite.php` pero que todavía nadie usa:

```php
if (! $tramite->estado->puedePasarA($nuevoEstado)) {
    abort(422, 'Transición de estado no permitida.');
}
```

Hay que llamarla antes de cambiar el estado de cualquier trámite.

---

## 8. Errores comunes

| Síntoma | Causa | Solución |
| --- | --- | --- |
| Página en blanco al navegar | El nombre de `Inertia::render()` no coincide con la ruta del archivo | Revisar que `'panel/solicitantes/index'` apunte a `pages/panel/solicitantes/index.tsx` |
| "A component is changing an uncontrolled input to be controlled" | Un campo del formulario empezó en `undefined` | Declarar TODOS los campos en `useForm({...})`, aunque sean `''` |
| Warning `Each child should have a unique "key"` | Falta `key` en un `.map()` | Agregar `key={item.id}` |
| Aparece un `0` suelto en la pantalla | `{cantidad && <p>}` con `cantidad = 0` | Escribir `{cantidad > 0 && <p>}` |
| Un color de Tailwind no se aplica | La clase se armó juntando textos | Escribir la clase completa. Tailwind solo incluye las que puede leer literalmente |
| Los errores de validación no aparecen | Se está usando `axios`/`fetch` en vez de `useForm` | Usar `useForm` o `router` de Inertia |
| Un gráfico no se ve | Su contenedor no tiene altura | Poner `h-72` (o la que sea) en el elemento padre |
| Se pierden los filtros al pasar de página | Falta `->withQueryString()` en el paginador | Agregarlo en el controlador |

---

## 9. Comandos del día a día

```sh
composer run dev       # levanta todo junto: servidor, vite, cola y logs

npx tsc --noEmit       # revisa los tipos SIN compilar. Rápido, usarlo seguido
npm run build          # compila para producción
php artisan test       # las pruebas automáticas
./vendor/bin/pint      # formatea el PHP
```

Con `composer run dev` corriendo, al guardar un `.tsx` el navegador se actualiza
solo. No hace falta recompilar nada a mano.
