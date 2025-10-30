<?php

namespace App\Actions;

use Illuminate\Support\Facades\Storage;

class DeleteFile
{
    public static function run($path, $disk = 'public')
    {
        // @todo: add try / catch and log if exception thrown
        Storage::disk($disk)->delete($path);
    }
}
