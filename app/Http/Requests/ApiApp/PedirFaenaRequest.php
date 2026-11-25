<?php

namespace App\Http\Requests\ApiApp;

use App\Http\Requests\Panel\ActualizarFaenaRequest;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * El permiso de faena pedido por el propio pescador (portal web y app): los siete
 * renglones del talonario, con las MISMAS reglas y mensajes que ventanilla
 * (ActualizarFaenaRequest: sin carnet_id). El carnet no viene del formulario: es
 * el suyo vigente, de la cuenta con sesión.
 */
class PedirFaenaRequest extends ActualizarFaenaRequest {}
