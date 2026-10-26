<?php

use App\Sireb\SirebService;
use Illuminate\Contracts\Console\Kernel;

// ¿SIREB (Paititi) ya dio por pagada la liquidación de este trámite? Solo LEE: no aprueba.
// Lo usa grabar-ser-pescador.cjs mientras espera que alguien valide el pago allá.
// Uso: php docs/manual/pago-validado.php AprovechamientoPesq 12   → imprime pagada | pendiente | ...

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$clase = 'App\\Models\\'.$argv[1];
$doc = $clase::findOrFail($argv[2]);

// Si el programador ya lo aprobó, para la grabación cuenta como pagado. Por valor: en la
// guía el caso se llama `Aprobada`, en los otros tres `Aprobado`.
if ($doc->estado === $doc->estado::from('aprobado')) {
    echo 'pagada';
    exit;
}

$liq = app(SirebService::class)->liquidacion($doc->sireb_liquidacion_id);
echo $liq['estado'] ?? 'desconocido';
