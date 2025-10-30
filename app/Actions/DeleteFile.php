<?php

namespace App\Actions;

use Illuminate\Support\Facades\Storage;

class DeleteFile
{
    public static function run($path, $disk = 'public')
    {
        Storage::disk($disk)->delete($path);
    }
}
