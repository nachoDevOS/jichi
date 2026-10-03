<?php

/*
| Comandos de consola
*/

use Illuminate\Support\Facades\Schedule;

// SIREB no avisa cuando se paga: Jichi pregunta. Ver VerificarPagosCommand.
Schedule::command('jichi:verificar-pagos')->everyTenMinutes()->withoutOverlapping();
