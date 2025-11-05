<?php

namespace App\Actions;

use Illuminate\Support\Facades\Storage;

class DeleteFile
{
    public static function run($path, $disk = null)
    {
        // @todo: add try / catch and log if exception thrown

        // $disk defaulting to null uses the
        // default disk from config/filesystems.php
        Storage::disk($disk)->delete($path);
    }
}
