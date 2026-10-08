<?php

/*
| Comandos de consola
*/

use Illuminate\Support\Facades\Schedule;

// SIREB no avisa cuando se paga: Jichi pregunta. Ver VerificarPagosCommand.
// Con topes por pasada (150 consultas, 8 min) y sin insistir si SIREB no responde: no carga el servidor.
Schedule::command('jichi:verificar-pagos')->everyTenMinutes()->withoutOverlapping(15);
