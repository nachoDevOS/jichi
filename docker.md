# Levantar Jichi con Docker — paso a paso

Guía para prender el sistema en **cualquier computadora** con Docker, sin
instalar PHP, Node ni PostgreSQL. Todo eso viene adentro de los contenedores.

Al terminar, el sistema queda en **http://localhost:8090**.

> El porqué de cada decisión técnica está en [docs/DOCKER-TECNICO.md](docs/DOCKER-TECNICO.md). Esta
> guía es solo para levantarlo.

---

## Resumen rápido (si ya lo hiciste antes)

```sh
cd ~/Escritorio/GOBE/jichi
docker compose up -d
```

Esperar unos 20 segundos y abrir **http://localhost:8090**.

---

## 1. Lo que hay que tener instalado

| Programa | Para qué | Dónde se baja |
| --- | --- | --- |
| **Docker Desktop** | Corre los contenedores | https://www.docker.com/products/docker-desktop/ |
| **Git** | Bajar el código | https://git-scm.com/downloads |

No hace falta nada más: ni PHP, ni Composer, ni Node, ni PostgreSQL.

### Docker Desktop en Linux (Ubuntu)

1. Bajar el `.deb` desde la página de Docker Desktop.
2. Instalarlo:
   ```sh
   sudo apt install ./docker-desktop-amd64.deb
   ```
3. Prenderlo:
   ```sh
   systemctl --user start docker-desktop
   ```
4. **Solo en Linux**, decirle a la terminal que use Docker Desktop. Sin esto
   sale `permission denied ... /var/run/docker.sock`:
   ```sh
   docker context use desktop-linux
   ```
   Se hace **una sola vez** por computadora.

### Docker Desktop en Windows o Mac

Instalar el programa, abrirlo y listo. El paso del `context` no hace falta.
En Windows los comandos de esta guía se escriben igual en **PowerShell**.

### Comprobar que Docker anda

Abrir Docker Desktop y esperar a que abajo a la izquierda diga
**Engine running** en verde. Después, en la terminal:

```sh
docker info
```

Tiene que mostrar un montón de datos sin ningún error. Si sale
`500 Internal Server Error`, ver [Problemas comunes](#7-problemas-comunes).

---

## 2. Bajar el código

```sh
cd ~/Escritorio
mkdir -p GOBE && cd GOBE
git clone https://github.com/nachoDevOS/jichi.git
cd jichi
git checkout nachoDev
```

> ⚠️ **Los archivos de Docker tienen que estar subidos a GitHub.** `Dockerfile`,
> `.dockerignore`, `docker-compose.yml` y esta guía se crearon en la PC de
> desarrollo. Si al clonar no aparecen, primero hay que hacer commit y push
> desde esa PC.
>
> Para comprobarlo, dentro de la carpeta `jichi`:
> ```sh
> ls Dockerfile docker-compose.yml
> ```

---

## 3. Crear el archivo `.env`

El `.env` **no está en GitHub** (tiene claves), así que en una PC nueva no
existe. Docker necesita solo **una** línea de él: la `APP_KEY`, la clave con la
que el sistema cifra las sesiones.

### Opción A — Copiarlo de la PC de desarrollo (recomendada)

Copiar el archivo `.env` de la otra PC a la carpeta `jichi` por pendrive, correo
o como sea. Con la misma `APP_KEY` las dos PCs quedan compatibles.

### Opción B — Generar uno nuevo

Esto genera una clave usando Docker (no hace falta tener PHP):

```sh
docker run --rm serversideup/php:8.3-cli php -r "echo 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;" > .env
```

Comprobar que quedó bien:

```sh
cat .env
```

Tiene que decir algo así: `APP_KEY=base64:Xy7...=`

### Variables opcionales del `.env`

Se pueden agregar debajo de la `APP_KEY`:

| Variable | Qué hace | Si no se pone |
| --- | --- | --- |
| `DOCKER_APP_PORT=8000` | Cambia el puerto de la página | `8090` |
| `JICHI_SEED_PASSWORD=miClave` | Contraseña del administrador que se crea en el paso 5 | `password` |
| `DOCKER_APP_DEBUG=false` | Oculta el detalle técnico de los errores | `true` (muestra el detalle) |

---

## 4. Construir y levantar

Dentro de la carpeta `jichi`:

```sh
docker compose up -d --build
```

- **La primera vez tarda** varios minutos: baja las imágenes base, instala las
  dependencias y compila la interfaz. Las siguientes veces tarda segundos.
- Al terminar tiene que decir:
  ```
  ✔ Container jichi-db-1   Healthy
  ✔ Container jichi-app-1  Started
  ```
- Al arrancar, el sistema **crea las tablas solo** (migraciones).

Esperar unos **20 segundos** y comprobar que está sano:

```sh
docker compose ps
```

La columna `STATUS` de `jichi-app-1` tiene que decir **(healthy)**. Si dice
`(health: starting)`, esperar un poco más.

---

## 5. Cargar los datos iniciales (solo la primera vez)

La base arranca vacía. Esto crea los roles, la configuración y el usuario
administrador:

```sh
docker compose exec app php artisan db:seed --force
```

Y esto carga los catálogos (asociaciones, tipos de carnet, productos,
categorías):

```sh
docker compose exec app php artisan db:seed --class=CatalogoSeeder --force
```

> Los catálogos salen con valores de **plantilla**. Antes de emitir papeles
> reales hay que corregirlos desde el panel con los de la resolución.

**No repetir este paso** cada vez que se prende: los datos quedan guardados.
Solo hace falta de nuevo si se borró la base (ver
[Borrar todo y empezar de cero](#borrar-todo-y-empezar-de-cero)).

---

## 6. Entrar

Abrir **http://localhost:8090** (o el puerto que se haya puesto en
`DOCKER_APP_PORT`).

| Usuario | Contraseña |
| --- | --- |
| `admin@admin.com` | `password` (o la de `JICHI_SEED_PASSWORD`) |

> ⚠️ **No es `127.0.0.1:8000`.** Esa era la dirección de `composer run dev`.
> Con Docker es **localhost:8090**.

---

## Uso diario

Todos los comandos se escriben **dentro de la carpeta `jichi`**.

| Quiero... | Comando |
| --- | --- |
| Prender | `docker compose up -d` |
| Apagar | `docker compose down` |
| Aplicar cambios del código (después de `git pull`) | `docker compose up -d --build` |
| Ver si está prendido | `docker compose ps` |
| Ver los mensajes del sistema | `docker compose logs -f app` (salir con `Ctrl+C`) |
| Correr un comando de Laravel | `docker compose exec app php artisan <comando>` |
| Entrar a la base con un gestor (DBeaver, pgAdmin) | host `localhost`, puerto `5433`, base/usuario/clave `jichi` |

**Apagar la PC no borra nada.** Al volver, prender Docker Desktop, esperar el
**Engine running** y correr `docker compose up -d`.

### Borrar todo y empezar de cero

> ⚠️ **Esto borra la base de datos y todos los archivos subidos.** No se
> puede deshacer.

```sh
docker compose down -v
docker compose up -d --build
```

Y repetir el [paso 5](#5-cargar-los-datos-iniciales-solo-la-primera-vez).

### Pasar los datos de una PC a otra

En la PC de origen, sacar una copia de la base:

```sh
docker compose exec -T db pg_dump -U jichi jichi > respaldo.sql
```

Llevar `respaldo.sql` a la PC nueva y, con el sistema ya levantado (pasos 1 a 4,
**sin** el paso 5), cargarla:

```sh
docker compose exec -T db psql -U jichi jichi < respaldo.sql
```

Los archivos subidos (fotos, boletas) **no** viajan en ese respaldo: están en el
volumen `jichi_adjuntos`.

---

## 7. Problemas comunes

### «No se puede acceder a este sitio — 127.0.0.1 rechazó la conexión»

La dirección está mal. Es **http://localhost:8090**, no `127.0.0.1:8000`.

### «Se ha restablecido la conexión» (`ERR_CONNECTION_RESET`)

Una de dos:

1. **El sistema está arrancando.** Esperar 20 segundos y recargar.
2. **El motor de Docker Desktop se colgó.** Se comprueba con:
   ```sh
   docker info
   ```
   Si sale `500 Internal Server Error`, reiniciar Docker Desktop:
   ```sh
   systemctl --user restart docker-desktop     # Linux
   ```
   En Windows o Mac: clic derecho al ícono de la ballena → **Restart**.
   Esperar el **Engine running** en verde y volver a correr
   `docker compose up -d`. **No se pierde nada**: los datos siguen ahí.

### `permission denied while trying to connect to the docker API at unix:///var/run/docker.sock`

Solo en Linux. Falta el paso del contexto:

```sh
docker context use desktop-linux
```

### `Falta APP_KEY en el .env`

No existe el `.env` o no tiene la `APP_KEY`. Ver el
[paso 3](#3-crear-el-archivo-env).

### `ports are not available ... address already in use`

El puerto 8090 (o el 5433) lo está usando otro programa. Cambiar el de la
página agregando al `.env`:

```
DOCKER_APP_PORT=8095
```

Si el que choca es el **5433** (la base), editar en `docker-compose.yml` la
línea `"5433:5432"` por otro número, por ejemplo `"5434:5432"`.

### El build falla en `npm run build` con `getaddrinfo`

Se cortó internet mientras bajaba las fuentes de la interfaz. Volver a correr:

```sh
docker compose up -d --build
```

### Entra a la página pero el login no acepta `admin@admin.com`

Falta cargar los datos iniciales: [paso 5](#5-cargar-los-datos-iniciales-solo-la-primera-vez).

### Hice cambios en el código y no se ven

Con Docker los cambios **no** se ven al instante, como con `composer run dev`.
Hay que reconstruir:

```sh
docker compose up -d --build
```

### Algo más raro

Mirar qué dice el sistema:

```sh
docker compose logs --tail=50 app
```

Las líneas con `ERROR` o `Exception` dicen qué falló.
