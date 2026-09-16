# Cómo Laravel habla con React en este proyecto

Guía para quien sabe Laravel y Blade pero recién arranca con React.

Todo lo que se explica acá está aplicado en el módulo **Beneficiarios**, que es
la plantilla del sistema. Conviene leer esta guía con esos archivos al lado.

---

## 1. Qué cambia respecto de Blade

Con Blade el controlador pasa variables a una vista `.blade.php`:

```php
return view('beneficiarios.index', ['beneficiarios' => $beneficiarios]);
```

Con Inertia el controlador pasa variables a un componente `.tsx`:

```php
return Inertia::render('panel/beneficiarios/index', ['beneficiarios' => $beneficiarios]);
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

Sigamos qué pasa cuando alguien entra a `/panel/beneficiarios`.

```
 NAVEGADOR                LARAVEL                        REACT
     │
     │  GET /panel/beneficiarios
     ├───────────────────────►
     │                    routes/panel.php
     │                    encuentra la ruta
     │                         │
     │                    BeneficiarioController@index
     │                    consulta la base con Eloquent
     │                         │
     │                    Inertia::render(
     │                      'panel/beneficiarios/index',
     │                      ['beneficiarios' => ...]
     │                    )
     │                         │
     │  ◄──────────────────────┤
     │   nombre de pantalla + datos
     │                                          resources/js/pages/
     │                                          panel/beneficiarios/index.tsx
     │                                          recibe los datos como props
     │                                                  │
     │  ◄───────────────────────────────────────────────┤
     │                 pantalla dibujada
```

**El paso clave es la traducción del nombre:**

```
Inertia::render('panel/beneficiarios/index')
                 └──────────┬───────────┘
                            ▼
    resources/js/pages/panel/beneficiarios/index.tsx
```

El texto es la ruta del archivo dentro de `resources/js/pages/`, sin `.tsx`.
Nada más. No hay ninguna lista donde registrarlo: `app.tsx` los descubre todos
automáticamente con `import.meta.glob`.

---

## 3. De Laravel a React: las props

El array que se pasa como segundo argumento **es** el objeto de props que llega
a React. Clave por clave, con el mismo nombre.

**En PHP** (`app/Http/Controllers/Panel/BeneficiarioController.php`):

```php
return Inertia::render('panel/beneficiarios/index', [
    'beneficiarios' => $beneficiarios,
    'filtros'      => $filtros,
    'tipos'        => EstadoRubro::opciones(),
]);
```

**En React** (`resources/js/pages/panel/beneficiarios/index.tsx`):

```tsx
export default function IndiceBeneficiarios({ beneficiarios, filtros, tipos }: Props) {
    // ...
}
```

Las llaves `{ }` alrededor de los nombres son **desestructuración**: en vez de
recibir un objeto `props` y escribir `props.beneficiarios`, se sacan las claves
directo por su nombre. Es equivalente a esto, pero más corto:

```tsx
export default function IndiceBeneficiarios(props: Props) {
    const beneficiarios = props.beneficiarios;
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
    form.post(route('beneficiarios.store'));
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

1. El formulario hace POST a `/panel/beneficiarios`
2. Laravel ejecuta `GuardarBeneficiarioRequest` antes del controlador
3. Si falla, Laravel redirige de vuelta con los errores en la sesión
4. Inertia los detecta y los deja en `form.errors`
5. React los muestra

Es exactamente el `$errors` de Blade, con otro nombre.

### Por qué el controlador devuelve `redirect()` y no JSON

```php
return redirect()
    ->route('beneficiarios.show', $beneficiario)
    ->with('exito', 'Beneficiario registrado correctamente.');
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
form.post(route('beneficiarios.update', id), { forceFormData: true });
```

Está aplicado en `components/panel/beneficiarios/formulario-beneficiario.tsx`.

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
@foreach ($beneficiarios as $s)
    <tr><td>{{ $s->nombre_completo }}</td></tr>
@endforeach
```

React:

```tsx
{beneficiarios.map((s) => (
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

Ese ejemplo es el buscador de beneficiarios: cada tecla cancela el temporizador
anterior y arranca uno nuevo, así que solo sobrevive el último. Sin eso, escribir
"perez" dispararía cinco consultas a la base en vez de una.

**Olvidarse de la limpieza es la fuga de memoria más común en React.**

---

## 6. Navegar entre pantallas

Dentro del sistema **siempre** se usa `<Link>` de Inertia, nunca `<a>`:

```tsx
import { Link } from '@inertiajs/react';

<Link href={route('beneficiarios.show', 42)}>Ver ficha</Link>
```

`<Link>` pide solo los datos nuevos y cambia el contenido sin recargar: no
parpadea ni vuelve a descargar los estilos. Un `<a>` recargaría todo.

Para acciones sin formulario se usa `router`:

```tsx
import { router } from '@inertiajs/react';

router.delete(route('beneficiarios.destroy', 42));
router.post(route('logout'));
```

### `route()` es de Ziggy

Convierte el nombre de la ruta de Laravel en su URL:

```tsx
route('beneficiarios.show', 42)   // '/panel/beneficiarios/42'
```

Es la misma función `route()` de Blade. La inyecta la directiva `@routes` en
`resources/views/app.blade.php` y está disponible en cualquier archivo, sin
importarla.

**Nunca escribir URLs a mano.** Si mañana cambia el prefijo en
`routes/panel.php`, con `route()` no hay que tocar nada.

---

## 7. Agregar un módulo nuevo, paso a paso

Ejemplo: el módulo de **Reportes**. Es exactamente el patrón de Beneficiarios.

### Backend

**1. El controlador** — `app/Http/Controllers/Panel/ReporteController.php`

Copiar `BeneficiarioController.php` y adaptar. Los métodos son siempre los mismos:
`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`.

**2. La validación** — `app/Http/Requests/Panel/GuardarReporteRequest.php`

**3. Las rutas** — en `routes/panel.php`, dentro del grupo que ya existe:

```php
Route::middleware('permiso:reportes.ver')->group(function () {
    Route::get('/reportes', [ReporteController::class, 'index'])
        ->name('reportes.index');
});
```

> Acordate del orden: `/reportes/crear` ANTES que `/reportes/{reporte}`.

Los permisos (`reportes.ver`, `reportes.exportar`, ...) ya existen en
`app/Enums/RolSistema.php`. No hay que crearlos.

### ¿Y si el módulo tiene reglas de negocio de verdad?

Entonces NO van en el controlador, ni siquiera «por ahora». Van en un servicio
de `app/Services/`, y el controlador se limita a traducir la petición, llamarlo
y convertir el resultado en un `redirect()`.

El ejemplo completo es `SolicitudCarnetService`: decide el tipo de trámite,
crea el carnet si hace falta, registra el expediente y sus pagos, todo dentro de
una transacción. El controlador que lo usa —`TramiteController::store()`— tiene
quince líneas, y esa es la señal de que está bien repartido.

El motivo es concreto: el mismo caso de uso lo necesitan el formulario del
panel, un comando de consola y las pruebas automáticas. Escrito adentro del
controlador, los otros dos tienen que copiarlo, y las copias se quedan viejas.

### Frontend

**4. Los tipos** — `resources/js/types/reportes.ts`

**5. Las pantallas** — `resources/js/pages/panel/reportes/`

```
index.tsx    crear.tsx    editar.tsx    ver.tsx
```

**6. Los componentes** — `resources/js/components/panel/reportes/`

```
tabla-reportes.tsx    filtros-reportes.tsx    formulario-reporte.tsx
```

### Y ya está

El ítem "Reportes" del menú lateral **se enciende solo**. Ya está declarado en
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
| Página en blanco al navegar | El nombre de `Inertia::render()` no coincide con la ruta del archivo | Revisar que `'panel/beneficiarios/index'` apunte a `pages/panel/beneficiarios/index.tsx` |
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
