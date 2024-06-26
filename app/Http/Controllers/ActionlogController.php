<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Actionlog;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
class ActionlogController extends Controller
{
    public function displaySig($filename)
    {
        // PHP doesn't let you handle file not found errors well with 
        // file_get_contents, so we set the error reporting for just this class
        error_reporting(0);

        $disk = config('filesystems.default');
        switch (config("filesystems.disks.$disk.driver")) {
            case 's3':
                $file = 'private_uploads/signatures/'.$filename;
                return redirect()->away(Storage::disk($disk)->temporaryUrl($file, now()->addMinutes(5)));
            default:
                $this->authorize('view', \App\Models\Asset::class);
                $file = config('app.private_uploads').'/signatures/'.$filename;
                $filetype = Helper::checkUploadIsImage($file);

                $contents = file_get_contents($file, false, stream_context_create(['http' => ['ignore_errors' => true]]));
                if ($contents === false) {
                    Log::warning('File '.$file.' not found');
                    return false;
                } else {
                    return Response::make($contents)->header('Content-Type', $filetype);
                }
        }
    }

    public function getStoredEula($filename){
        $this->authorize('view', \App\Models\Asset::class);
        $file = config('app.private_uploads').'/eula-pdfs/'.$filename;

        return Response::download($file);
    }
}
