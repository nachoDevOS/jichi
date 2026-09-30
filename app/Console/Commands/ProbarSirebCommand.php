<?php

namespace App\Console\Commands;

use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Console\Command;

/**
 * Prueba la conexión con Recaudaciones: pide un token nuevo a Ibare y muestra
 * el catálogo del SEDAG tal como lo manda SIREB. Ver docs/modulos/SIREB.md.
 */
class ProbarSirebCommand extends Command
{
    protected $signature = 'jichi:sireb';

    protected $description = 'Prueba la conexión con SIREB: token de Ibare y catálogo del SEDAG tal cual llega';

    public function handle(SirebService $sireb): int
    {
        $this->line('Ibare: '.config('jichi.sireb.ibare_url').'  ·  SIREB: '.config('jichi.sireb.url'));

        try {
            $token = $sireb->token(refrescar: true);
            $this->info('Token de Ibare: OK (client_credentials, cliente «'.config('jichi.sireb.client_id').'»).');
            $this->line($token);

            // Ibare emite un JWT: el `exp` dice hasta cuándo sirve. Passport lo manda con decimales.
            $exp = json_decode(base64_decode(strtr(explode('.', $token)[1] ?? '', '-_', '+/')), true)['exp'] ?? null;

            if (is_numeric($exp)) {
                $this->line('Vence: '.now()->setTimestamp((int) $exp)->format('d/m/Y H:i:s'));
            }

            $pagina = 1;

            do {
                $cuerpo = $sireb->catalogoCrudo($pagina);
                $this->info("GET /api/v1/catalogo/servicios?pagina={$pagina}");
                $this->line(json_encode($cuerpo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $pagina++;
            } while ($pagina <= (int) ($cuerpo['meta']['total_paginas'] ?? 1));
        } catch (SirebException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
