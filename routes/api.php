<?php

use App\Http\Controllers\Sireb\AvisoSirebController;
use Illuminate\Support\Facades\Route;

/*
| Lo que llaman OTROS SISTEMAS, no personas: sin sesión ni CSRF (grupo `api`,
| prefijo /api). Cada uno se autentica a su manera; el de SIREB, con la firma.
*/

// Webhook de SIREB/Paititi: la dirección que se le da a Recaudaciones es {APP_URL}/api/sireb/aviso.
Route::post('/sireb/aviso', AvisoSirebController::class)
    ->middleware('throttle:120,1')
    ->name('sireb.aviso');

// La app BeniDigital (/api/v1/portal) vive aparte, para ver de un vistazo qué es de la app.
require __DIR__.'/api-app.php';
