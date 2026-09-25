<?php

namespace App\AuthIbare;

use App\Models\User;
use Illuminate\Console\Command;

// Jichi no tiene módulo de Usuarios todavía: el vínculo con Ibare se carga desde acá.
class VincularIbareCommand extends Command
{
    protected $signature = 'jichi:vincular-ibare {email} {mamore_id?} {--quitar}';

    protected $description = 'Vincula un usuario de Jichi con su funcionario de Ibare (mamore_id)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $mamoreId = $this->argument('mamore_id');

        $usuario = User::where('email', $email)->first();

        if (! $usuario) {
            $this->error("No hay ningún usuario con el correo {$email}.");

            return self::FAILURE;
        }

        if ($this->option('quitar')) {
            $usuario->update(['mamore_id' => null]);
            $this->info("{$usuario->name} ya no entra por Ibare.");

            return self::SUCCESS;
        }

        if ($mamoreId === null) {
            $this->error('Falta el mamore_id (o use --quitar).');

            return self::FAILURE;
        }

        $ocupado = User::where('mamore_id', $mamoreId)->whereKeyNot($usuario->id)->first();

        if ($ocupado) {
            $this->error("El mamore_id {$mamoreId} ya está vinculado a {$ocupado->email}.");

            return self::FAILURE;
        }

        $usuario->update(['mamore_id' => $mamoreId]);
        $this->info("{$usuario->name} queda vinculado al funcionario {$mamoreId} de Ibare.");

        return self::SUCCESS;
    }
}
