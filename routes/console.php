<?php

/*
|--------------------------------------------------------------------------
| Comandos de consola
|--------------------------------------------------------------------------
|
| Acá se declaran los comandos de Artisan propios del sistema. Hoy no hay
| ninguno: el archivo existe porque `bootstrap/app.php` lo declara en
| `withRouting(commands: ...)` y borrarlo rompería el arranque.
|
| EL PRIMERO QUE VA A VIVIR ACÁ es el que marca los carnets como vencidos.
| `EstadoCarnet::Vencido` no lo escribe nadie todavía, y por eso el filtro por
| estado del listado muestra como «vigentes» carnets de gestiones cerradas. Ver
| el problema 2 de docs/PENDIENTES.md.
|
| Mientras tanto el sistema no miente, porque `Carnet::estaVigente()` compara
| además contra `fecha_vencimiento`.
|
*/
