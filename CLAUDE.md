# Jichi — guía para agentes de IA

Sistema de recaudación, certificación y credenciales del Gobierno Autónomo
Departamental del Beni, Bolivia.

**Laravel 13 · PHP 8.3 · Inertia 2 · React 19 · TypeScript · Tailwind 4 ·
PostgreSQL 18** (corre también en SQLite; las pruebas usan SQLite en memoria).

Antes de tocar nada, leer [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md).

---

## Reglas que no se rompen

1. **Todo en español.** Nombres de archivo, variables, métodos, comentarios,
   mensajes al usuario, textos de la interfaz. La única excepción son los
   componentes de `resources/js/components/ui/` (`Button`, `Card`, `Input`,
   `Label`, `Badge`, `Select`, `Textarea`), que conservan el vocabulario
   estándar de React.

2. **Comentar el porqué, no el qué.** Este proyecto lo mantiene alguien que está
   aprendiendo React. Un comentario que repite lo que dice el código sobra; uno
   que explica por qué se eligió ese camino vale oro. Ejemplos del estilo
   esperado: `CorrelativoService`, `Documento::registrarVerificacion()`,
   `app/Support/Sql.php`, la migración `2026_09_08_100000`.

3. **Panel y público no se mezclan.** El sistema tiene dos mitades separadas en
   carpetas paralelas, en el backend y en el frontend:

   | | Panel (con sesión) | Público (sin sesión) |
   | --- | --- | --- |
   | Rutas | `routes/panel.php`, prefijo `/panel` | `routes/publico.php` |
   | Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` |
   | Pantallas | `resources/js/pages/panel/` | `resources/js/pages/publico/` |
   | Componentes | `components/panel/` | `components/publico/` |
   | Layout | `layouts/layout-panel.tsx` | `layouts/layout-publico.tsx` |

   La vista pública no puede exponer datos personales completos ni pistas de la
   estructura interna. Ver `VerificacionController::datosPublicos()`.

4. **`env()` solo dentro de `config/`.** En el resto del código,
   `config('jichi.lo_que_sea')`. Con `config:cache` activo, `env()` devuelve
   `null` fuera de `config/` y el error es silencioso.

5. **Los permisos se declaran en las rutas.** El middleware `permiso:` es la
   seguridad real. Esconder un botón en React (`usePermisos()`) es solo
   comodidad: siempre van los dos.

6. **Los enums mandan.** Estados, roles, permisos y categorías viven en
   `app/Enums/`. No escribir esos valores como texto suelto en el código.
   (Pendiente: `Pago.estado` todavía usa textos — ver `docs/PENDIENTES.md`.)

7. **Enums en columnas `string`**, nunca tipos ENUM nativos de PostgreSQL: así
   agregar un estado no exige `ALTER TYPE` ni bloquear la tabla.

8. **SQL específico de motor va en `app/Support/Sql.php`.** El sistema tiene que
   correr igual en PostgreSQL y en SQLite.

9. **Scopes con `qualifyColumn()`.** `tramites`, `pagos` y `documentos` tienen
   todas una columna `estado`, y los reportes las cruzan con `join`.

10. **Toda sesión de trabajo se registra.** Al terminar de trabajar hay que
    dejar el registro en `docs/sesiones/MM-AAAA/AAAA-MM-DD.md`, copiando
    [docs/sesiones/_plantilla.md](docs/sesiones/_plantilla.md). Un archivo por
    día. Cada trabajo lleva su problema, la tabla de archivos modificados y la
    solución con el porqué; al final va el **informe para presentación**, que se
    escribe en lenguaje simple —sin términos técnicos— porque se copia a Word y
    lo lee gente que no programa.

---

## El patrón a copiar

El módulo **Solicitantes** es la plantilla del sistema. Está comentado paso a
paso a propósito. Para agregar un módulo nuevo, copiar:

```
app/Http/Controllers/Panel/SolicitanteController.php
app/Http/Requests/Panel/GuardarSolicitanteRequest.php
routes/panel.php                                   (el bloque de solicitantes)
resources/js/pages/panel/solicitantes/*.tsx
resources/js/components/panel/solicitantes/*.tsx
resources/js/types/solicitantes.ts
tests/Feature/SolicitanteTest.php
```

El procedimiento detallado está en
[docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md#7-agregar-un-módulo-nuevo-paso-a-paso).

---

## Verificar antes de dar algo por terminado

```sh
php artisan test        # las pruebas
npx tsc --noEmit        # tipos de TypeScript
./vendor/bin/pint       # formato del PHP
npm run build           # que el frontend compile
```

Los cuatro tienen que pasar.

---

## Trampas conocidas de este proyecto

- **Orden de rutas:** `/solicitantes/crear` va ANTES de
  `/solicitantes/{solicitante}`, o "crear" se toma como id.
- **Índices únicos con borrado lógico:** nunca incluir `deleted_at` en un
  `unique()`. En SQL `NULL != NULL`, así que el índice no bloquea nada. Usar
  índice parcial `WHERE deleted_at IS NULL` (ver migración `2026_09_08_100000`).
- **`increment()` dispara eventos de modelo**, y varios modelos usan el trait
  `Auditable`. En rutas públicas eso llena la tabla de auditoría con ruido
  anónimo. Ver `Documento::registrarVerificacion()`.
- **Clases de Tailwind armadas juntando textos no funcionan.** Tailwind solo
  incluye en el CSS final las que puede leer literalmente en el código.
- **Los gráficos de recharts necesitan que el contenedor padre tenga altura**
  (`h-72`), o se calculan con altura cero y no se ven.
- **`solicitantes` usa camelCase de `primerNombre` en adelante.** En PostgreSQL
  eso obliga a entrecomillar: `SELECT "primerNombre" ...`. Sin comillas el motor
  pasa el nombre a minúscula y responde `column "primernombre" does not exist`.
  Laravel entrecomilla solo; el problema aparece al escribir SQL a mano o al
  usar `whereRaw` / `orderByRaw` (ver `Solicitante::SQL_NOMBRE`).
- **Un accesor camelCase NO puede ir en `#[Appends]`.** Laravel busca el accesor
  por el nombre del método pasado a snake_case, así que `nombreCompleto` no lo
  encuentra, cae al accesor de estilo viejo y revienta con
  «Call to undefined method getNombreCompletoAttribute()». Acceder directo
  (`$solicitante->nombreCompleto`) sí funciona. Ver `app/Models/Solicitante.php`.
- **`->withQueryString()`** en todo paginador con filtros, o al cambiar de página
  se pierden.

---

## Lo que falta

Cuatro módulos: Trámites, Documentos, Reportes y Configuración. Aparecen en
gris en el menú lateral. La lista completa, con los problemas conocidos que
siguen abiertos, está en [docs/PENDIENTES.md](docs/PENDIENTES.md).
