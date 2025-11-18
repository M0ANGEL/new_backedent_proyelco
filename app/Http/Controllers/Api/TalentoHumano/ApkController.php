<?php

namespace App\Http\Controllers\Api\TalentoHumano;

use App\Http\Controllers\Controller;
use App\Models\LinkDescargaAPK;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ApkController extends Controller
{
    public function index()
    {


        $data = LinkDescargaAPK::where('estado', 1)->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    //desacrgue un acrhivo apk que esta en storage/app/public/apk/apk asistencias 4-nov-2025.apk
    public function linkDescargaAPK(Request $request)
    {
        // Verificamos el token del usuario (si usas Sanctum o JWT)
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        // Generamos un enlace temporal firmado válido por 2 minutos
        $url = URL::temporarySignedRoute(
            'descargar.apk.firmado',
            now()->addMinutes(2)
        );

        return response()->json([
            'status' => 'success',
            'url' => $url
        ]);
    }

    public function descargarAPKFirmado(Request $request)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Enlace inválido o expirado'], 403);
        }

        $path = 'public/apk/apk-4-nov-2025.apk';
        if (!Storage::exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        $filePath = Storage::path($path);
        return response()->download($filePath, 'asistencias.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
