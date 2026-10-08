# Jichi — Instalación en desarrollo

Sistema de recaudación, certificación y credenciales del Gobierno Autónomo
Departamental del Beni.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13.17 · PHP 8.3 |
| Frontend | React 19 · Inertia 2 · TypeScript · Tailwind 4 |
| Base de datos | PostgreSQL 18 |
| Roles y permisos | spatie/laravel-permission |
| PDF | barryvdh/laravel-dompdf |
| QR | bacon/bacon-qr-code (pintado con gd) |
| Rutas en JS | tightenco/ziggy |
| Gráficos | recharts |
| Toasts | sonner |

## 1. PHP: driver de PostgreSQL

Ya está habilitado en `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`
(`extension=pdo_pgsql` y `extension=pgsql`). El respaldo del archivo original
quedó como `php.ini.bak-jichi`.

Verificar:

```sh
php -r "echo implode(', ', PDO::getAvailableDrivers());"
# mysql, pgsql, sqlite, sqlsrv
```

## 2. Base de datos

### Estado actual: PostgreSQL 18

El entorno corre sobre la base `jichi` en el PostgreSQL local, ya migrada
y sembrada. Configuración en `.env`:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jichi
DB_USERNAME=postgres
DB_PASSWORD=<contraseña local>
```

Si hay que recrear la base desde cero:

```sh
"C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 jichi
php artisan migrate:fresh --seed
```

### Volver a SQLite

Comentar el bloque `pgsql` en `.env` y dejar `DB_CONNECTION=sqlite`, luego
`php artisan migrate:fresh --seed`. No hay que tocar código: lo específico de
cada motor está aislado en `app/Support/Sql.php` (operador `ILIKE` vs `LIKE`,
agrupación por mes).

## 3. Migrar y sembrar

```sh
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

El seeder crea el rol `administrador` con todos sus permisos, la configuración
institucional y la cuenta `admin@admin.com`.

Fuera de producción además siembra los **catálogos** (`CatalogoSeeder`:
asociaciones, la escala oficial, los tipos de carnet, los productos y los
aranceles, con sus tarifas de SIREB), **vacía el dominio** y carga un **padrón de
prueba** (`BeneficiarioSeeder`): unas fichas armadas a mano para los casos
difíciles —el nombre más largo, la apellidada de casada— y el resto con la
factory. No se siembran autorizaciones, carnets, faenas ni guías: se registran
desde las pantallas, porque cada una necesita su precio y su liquidación en
SIREB.

> Para registrar trámites hace falta que SIREB responda: sin precio no se emite.
> `php artisan jichi:sireb` prueba la conexión. Ver
> [modulos/SIREB.md](modulos/SIREB.md).

### Usuarios sembrados

Contraseña común: **`password`**. Es el valor por defecto de
`jichi.password_semilla`, y **no está en `.env`**: si hace falta otra, se agrega
ahí la línea `JICHI_SEED_PASSWORD` y la toma. **Cambiar antes de cualquier
despliegue.**

| Rol | Correo |
| --- | --- |
| Administrador | admin@admin.com |

Por ahora hay una sola cuenta y un solo rol. Supervisor, operador de ventanilla y
solo-lectura se agregarán cuando la unidad defina quién hace qué; los permisos
que van a usar ya están repartidos por bloque en `app/Enums/RolSistema.php`.

## 4. Levantar

```sh
composer run dev     # servidor + cola + vite + programador (verifica pagos cada 10 min)
# o por separado:
php artisan serve
php artisan queue:listen --tries=1
php artisan schedule:work
npm run dev
```

> Sin el programador, ningún pago se aprueba solo: hay que apretar «Verificar
> pago» en cada ficha. `php artisan dev:list` muestra qué levanta `composer run dev`.

### En producción (Coolify): una tarea programada que no puede faltar

Los pagos se verifican solos con **`php artisan schedule:run` cada minuto**. En
Coolify va como **Scheduled Task** de la aplicación (comando `php artisan
schedule:run`, frecuencia `* * * * *`): ver [DOCKER-TECNICO.md](DOCKER-TECNICO.md#para-coolify).
En un servidor sin Coolify, la misma línea en el cron:

```cron
* * * * * cd /ruta/a/jichi && php artisan schedule:run >> /dev/null 2>&1
```

Corre `jichi:verificar-pagos` cada 10 minutos, con topes para no cargar el
servidor: 150 consultas u 8 minutos por pasada, no repite lo que el portal
consultó hace menos de 2 minutos, y si SIREB no responde corta la pasada y espera
2 minutos. **No hace falta trabajador de cola:** lo que pide el portal corre
después de mandar la página (`dispatchAfterResponse`). Sin trámites pendientes no
hace ninguna consulta.

## Estructura del proyecto

El mapa completo de carpetas —qué hay en cada una y dónde va un archivo nuevo—
está en [ESTRUCTURA.md](ESTRUCTURA.md).

Para entender cómo Laravel se comunica con React en este sistema, empezar por
[GUIA-INERTIA.md](GUIA-INERTIA.md).

## Qué falta

Los módulos **Reportes** y **Configuración** todavía no están construidos: el
primero aparece en gris en el menú lateral. Los usuarios se crean por consola.
El detalle está en [PENDIENTES.md](PENDIENTES.md).

El módulo **Beneficiarios** sirve de plantilla comentada para cualquier módulo
nuevo; ver [GUIA-INERTIA.md](GUIA-INERTIA.md).
