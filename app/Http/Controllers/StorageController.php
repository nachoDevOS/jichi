<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageController extends Controller
{
    public function file(UploadedFile $file, string $folder, string $disk = 'public'): string
    {
        $ext         = $file->getClientOriginalExtension();
        $newFileName = Str::random(20) . time() . '.' . $ext;
        $directory   = $folder . '/' . date('FY');
        $path        = $directory . '/' . $newFileName;

        $isS3        = env('FILESYSTEM_DISK') == 's3';
        $activeDisk  = $isS3 ? 's3' : $disk;

        Storage::disk($activeDisk)->makeDirectory($directory);
        Storage::disk($activeDisk)->put($path, file_get_contents($file), 'public');

        if ($isS3) {
            $root = env('AWS_ROOT') ? env('AWS_ROOT') . '/' : '';
            return env('AWS_ENDPOINT') . '/' . env('AWS_BUCKET') . '/' . $root . $path;
        }

        return $path;
    }


}
