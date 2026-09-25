<?php

use App\AuthIbare\IbareController;
use Illuminate\Support\Facades\Route;

/*
| Login con Ibare. Las carga AuthIbareServiceProvider con los middleware
| 'web' + 'guest'; las dos dan 404 si IBARE_ACTIVO=false.
*/

Route::get('auth/ibare', [IbareController::class, 'redirigir'])
    ->middleware('throttle:10,1')
    ->name('ibare.redirigir');

Route::get('auth/ibare/callback', [IbareController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('ibare.callback');
