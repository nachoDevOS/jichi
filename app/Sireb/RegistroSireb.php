<?php

namespace App\Sireb;

use SplFileObject;

/**
 * Lee los archivos storage/logs/sireb-AAAA-MM-DD.log que escribe
 * SirebService::registrar(). Solo lectura: la pantalla no toca los archivos.
 */
class RegistroSireb
{
    public const RESULTADOS = [
        'ok' => ['Bien', 'emerald'],
        'rechazado' => ['Rechazado', 'amber'],
        'sin_respuesta' => ['Sin respuesta', 'rose'],
        'aviso' => ['Aviso', 'slate'],
    ];

    /**
     * Los días con archivo, el más nuevo primero.
     *
     * @return list<array{dia: string, bytes: int}>
     */
    public function dias(): array
    {
        $dias = [];

        foreach (glob(storage_path('logs/sireb-*.log')) ?: [] as $archivo) {
            if (preg_match('/sireb-(\d{4}-\d{2}-\d{2})\.log$/', $archivo, $m)) {
                $dias[] = ['dia' => $m[1], 'bytes' => (int) filesize($archivo)];
            }
        }

        usort($dias, fn (array $a, array $b): int => strcmp($b['dia'], $a['dia']));

        return $dias;
    }

    /** La ruta del archivo de ese día, o null si no existe. El patrón impide salir de storage/logs. */
    public function archivo(string $dia): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
            return null;
        }

        $ruta = storage_path("logs/sireb-{$dia}.log");

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Las entradas del día, la más nueva primero.
     *
     * @return list<array<string, mixed>>
     */
    public function entradas(string $dia): array
    {
        $ruta = $this->archivo($dia);

        if ($ruta === null) {
            return [];
        }

        $entradas = [];
        $archivo = new SplFileObject($ruta);

        // Línea a línea: un día con el comando cada 10 minutos no se carga entero en memoria de golpe.
        while (! $archivo->eof()) {
            $linea = rtrim((string) $archivo->fgets());

            if ($linea !== '' && ($entrada = $this->leer($linea, count($entradas))) !== null) {
                $entradas[] = $entrada;
            }
        }

        return array_reverse($entradas);
    }

    /**
     * Una línea del log en partes. Formato de Laravel:
     * «[2026-10-09 11:50:08] local.WARNING: ERROR POST url → 401 en 202 ms {contexto}».
     *
     * @return array<string, mixed>|null
     */
    private function leer(string $linea, int $numero): ?array
    {
        if (! preg_match('/^\[(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})\] [\w-]+\.(\w+): (.*)$/su', $linea, $m)) {
            return null;
        }

        [$mensaje, $contexto] = $this->separar($m[4]);
        $pedido = preg_match('/^(OK|ERROR|SIN RESPUESTA) (\w+) (\S+)(?: → (\d{3}))? en (\d+) ms$/u', $mensaje, $p) === 1;

        $resultado = match (true) {
            ! $pedido => 'aviso',
            $p[1] === 'OK' => 'ok',
            $p[1] === 'SIN RESPUESTA' => 'sin_respuesta',
            default => 'rechazado',
        };

        return [
            'id' => $numero,
            'hora' => $m[2],
            'resultado' => $resultado,
            'resultado_etiqueta' => self::RESULTADOS[$resultado][0],
            'resultado_color' => self::RESULTADOS[$resultado][1],
            'metodo' => $pedido ? $p[2] : null,
            'ruta' => $pedido ? (parse_url($p[3], PHP_URL_PATH) ?: $p[3]) : null,
            'url' => $pedido ? $p[3] : null,
            'codigo' => $pedido && $p[4] !== '' ? (int) $p[4] : null,
            'ms' => $pedido ? (int) $p[5] : null,
            'mensaje' => $mensaje,
            'quien' => $contexto['quien'] ?? null,
            'enviado' => $contexto['enviado'] ?? null,
            'respuesta' => $contexto['respuesta'] ?? null,
            'error' => $contexto['error'] ?? null,
            // Los avisos de los servicios traen su propio contexto (documento, motivo, tramo…).
            'contexto' => $pedido ? null : $contexto,
            'texto' => $linea,
        ];
    }

    /**
     * El mensaje y el JSON de contexto que Laravel pega detrás.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function separar(string $resto): array
    {
        // Si el formateador agrega el «extra» vacío al final, se descarta.
        $resto = preg_replace('/ \[\]$/', '', $resto) ?? $resto;
        $corte = strpos($resto, ' {');

        if ($corte !== false) {
            $contexto = json_decode(substr($resto, $corte + 1), true);

            if (is_array($contexto)) {
                return [trim(substr($resto, 0, $corte)), $contexto];
            }
        }

        return [trim($resto), []];
    }
}
