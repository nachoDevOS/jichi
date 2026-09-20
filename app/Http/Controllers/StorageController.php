<?php

namespace App\Http\Controllers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 *  EL ÚNICO PUNTO DEL SISTEMA QUE ESCRIBE ARCHIVOS EN DISCO
 */
class StorageController extends Controller
{
    /**
     *  GUARDA UN ARCHIVO Y DEVUELVE SIEMPRE UNA RUTA, NUNCA UNA URL
     *
     * @param  string  $folder  Carpeta lógica: 'beneficiarios', 'tramites', 'pagos'.
     *
     * @throws ValidationException si el archivo supera el límite del sistema.
     */
    public function file(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        $this->verificarPeso($file);

        $ext = $file->getClientOriginalExtension();
        $newFileName = Str::random(20).time().'.'.$ext;

        // Se agrupa por mes ('September2026') para que ninguna carpeta junte
        // decenas de miles de archivos: listarla se vuelve lentísimo, y en s3
        // los prefijos muy poblados también se degradan.
        $directory = $folder.'/'.date('FY');
        $path = $directory.'/'.$newFileName;

        // Cuál es el disco activo lo decide App\Support\Archivos, que es el
        // mismo que después arma la URL y borra: si lo decidiera cada uno por su
        // cuenta, escribir y leer podrían terminar mirando discos distintos.
        $activeDisk = config('filesystems.default') === 's3' ? 's3' : $disk;

        Storage::disk($activeDisk)->makeDirectory($directory);

        /*
         * file_get_contents() carga el archivo entero en memoria antes de
         * escribirlo. Con un tope de 3 MB eso es inofensivo —y es justamente el
         * tope lo que lo vuelve inofensivo—: sin el límite de arriba, veinte
         * cargas simultáneas de 500 MB voltearían el servidor de PHP antes de
         * llegar a tocar el disco.
         */
        Storage::disk($activeDisk)->put($path, file_get_contents($file), 'public');

        return $path;
    }

    /**
     *  EL LÍMITE DE 3 MB — la última línea de defensa
     *
     * @throws ValidationException
     */
    private function verificarPeso(UploadedFile $file): void
    {
        $maxKb = (int) config('jichi.archivos.max_kb');
        $maxBytes = $maxKb * 1024;

        if ($file->getSize() <= $maxBytes) {
            return;
        }

        $maxMb = round($maxKb / 1024, 1);
        $pesaMb = round($file->getSize() / 1024 / 1024, 1);

        throw ValidationException::withMessages([
            'archivo' => "El archivo pesa {$pesaMb} MB y el máximo permitido es {$maxMb} MB.",
        ]);
    }
}
