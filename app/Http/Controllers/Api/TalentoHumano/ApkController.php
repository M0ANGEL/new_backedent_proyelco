<?php

namespace App\Http\Controllers\Api\TalentoHumano;

use App\Http\Controllers\Controller;
use App\Models\LinkDescargaAPK;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApkController extends Controller
{
    public function index(){


         $data = LinkDescargaAPK::where('estado', 1)->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    //desacrgue un acrhivo apk que esta en storage/app/public/apk/apk asistencias 4-nov-2025.apk
    public function descargarAPK()
    {
        $path = 'public/apk/apk asistencias 4-nov-2025.apk';

        if (!Storage::exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El archivo no existe.'
            ], 404);
        }

        $filePath = Storage::path($path);

        return response()->download($filePath, 'asistencias.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
