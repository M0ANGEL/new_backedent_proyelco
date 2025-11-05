<?php

namespace App\Http\Controllers;

use App\Models\MaTelefono;
use App\Models\UbicacionObraTh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mockery\CountValidator\Exception;
use Symfony\Component\HttpFoundation\Response;

class AuthMarcacionController extends Controller
{

    public function Appupdate()
    {

        $update = DB::connection('mysql')
            ->table('link_descarga')
            ->where('estado', 1)
            ->first();

        if (!$update) {
            return response()->json([
                'status' => 'error',
                'message' => 'No hay una versión disponible de la app',
            ], 404);
        }

        return response()->json([
            'latest_version' => $update->version,
            'download_url' => $update->link_descarga
        ]);
    }

    public function loginMarcacion(Request $request)
    {
        try {
            // Validar los campos requeridos
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'serialTelefono' => 'required|string',
            ]);

            // Verificar si el teléfono está registrado
            $telefono = MaTelefono::where('serial_email', $request->serialTelefono)->where('estado', 1)->first();

            if (!$telefono) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El teléfono no está registrado o inactivo, comunícate con TI',
                ], 404);
            }

            // Extraer solo username y password para autenticación
            $credentials = $request->only('username', 'password');

            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Credenciales incorrectas',
                ], 401);
            }

            // Obtener el usuario autenticado
            $user = Auth::user();

            // Verificar si el usuario está activo
            if ($user->estado != 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario inactivo, por favor contacta con el personal de TI',
                ], 403);
            }

            // Crear token de acceso
            $token = $user->createToken('token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token' => $token,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en el servidor: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function validarTelefono(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'serialTelefono' => ['required', 'string']
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Buscar el teléfono
            $telefono = MaTelefono::where('serial_email', $request->serialTelefono)
                ->where('estado', 1)
                ->first();

            if (!$telefono) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El serial no está registrado, comunícate con TI',
                ], 404);
            }

            $user = Auth::user();
            $userId = $user->id;
            $esAdmin = in_array($user->rol, ['Administradorsds']);

            // ======================================
            // 🔹 1. Consultar obras con permisos
            // ======================================
            $obrasQuery = DB::table('ubicacion_obras_th')
                ->join('bodegas_area', 'bodegas_area.id', '=', 'ubicacion_obras_th.obra_id')
                ->select(
                    'bodegas_area.id',
                    'bodegas_area.nombre',
                    'ubicacion_obras_th.id as ubicacion_id',
                    'ubicacion_obras_th.latitud',
                    'ubicacion_obras_th.longitud',
                    'ubicacion_obras_th.rango'
                )
                ->distinct();

            if (!$esAdmin) {
                $obrasQuery->whereRaw("JSON_CONTAINS(ubicacion_obras_th.usuarios_permisos, '\"$userId\"')");
            }

            $obras = $obrasQuery->get();

            // ======================================
            // 🔹 2. Validar si hay resultados
            // ======================================
            if ($obras->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $esAdmin
                        ? 'No hay obras registradas en el sistema.'
                        : 'No se encontraron obras asignadas a este usuario.',
                ], 404);
            }


            // ======================================
            // 🔹 3. Respuesta final
            // ======================================
            return response()->json([
                'status' => 'success',
                'rol' => $user->rol,
                'obras' => $obras
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loginMarcacionConfi(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Validar si el usuario está activo
            if ($user->estado != 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario inactivo, por favor contacta con el personal de TI',
                ], 403);
            }

            if ($user->can_config_telefono !== '1') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no administrador, comunicate con TI',
                ], 403);
            }


            // Crear token de acceso
            $token = $user->createToken('token')->plainTextToken;
            $cookie = cookie('cookie_token', $token, 60 * 24);

            return response(["token" => $token], Response::HTTP_OK)->withoutCookie($cookie);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales incorrectas',
            ], 404);
        }
    }

    public function registrarTelefono(Request $request)
    {
        $request->validate([
            'serial_email' => 'required|string',
            'marca' => 'required|string',
            'activo' => 'required|string',

        ]);

        $telefono = MaTelefono::create([
            'serial_email' => $request->serial_email,
            'marca' => $request->marca,
            'activo' => $request->activo,
        ]);

        return response()->json([
            'message' => 'Teléfono registrado exitosamente.',
            'telefono' => $telefono
        ], 201);
    }

    // public function registarUbicacionObra(Request $request)
    // {
    //     $request->validate([
    //         'serial' => 'required|string',
    //         'bodega_id' => 'required',
    //         'latitude' => 'required',
    //         'longitude' => 'required',
    //     ]);

    //     // Obtener el ID del teléfono a partir del serial
    //     $telefono = MaTelefono::where('serial_email', $request->serial)->first();

    //     if (!$telefono) {
    //         return response()->json([
    //             'message' => 'El teléfono no está registrado.',
    //         ], 404);
    //     }

    //     // Verificar si ya existe una ubicación para esta obra
    //     $ubicacionExistente = UbicacionObraTh::where('obra_id', $request->bodega_id)->first();

    //     if ($ubicacionExistente) {
    //         // Si ya existe, retornar éxito sin mensaje
    //         return response()->json([
    //             'message' => 'OK',
    //         ], 200);
    //     }

    //     // Solo crear nueva ubicación si no existe
    //     $sede = UbicacionObraTh::create([
    //         'latitud' => $request->latitude,
    //         'longitud' => $request->longitude,
    //         'serial' => $request->serial,
    //         'obra_id' => $request->bodega_id,
    //         'user_id' => Auth::id()
    //     ]);

    //     return response()->json([
    //         'message' => 'Ubicacion registrada exitosamente.',
    //         'sede' => $sede
    //     ], 201);
    // }

    public function registarUbicacionObra(Request $request)
    {
        $request->validate([
            'serial' => 'required|string',
            'bodega_id' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $telefono = MaTelefono::where('serial_email', $request->serial)->first();

        if (!$telefono) {
            return response()->json([
                'message' => 'El teléfono no está registrado.',
            ], 404);
        }

        $ubicacionExistente = UbicacionObraTh::where('obra_id', $request->bodega_id)->first();
        $userId = (string) Auth::id(); // convertir siempre a string

        if ($ubicacionExistente) {
            // Decodificar permisos existentes
            $permisos = json_decode($ubicacionExistente->usuarios_permisos, true) ?? [];

            // Agregar el ID solo si no existe (en formato string)
            if (!in_array($userId, $permisos)) {
                $permisos[] = $userId;
                $ubicacionExistente->usuarios_permisos = json_encode($permisos, JSON_UNESCAPED_UNICODE);
                $ubicacionExistente->save();
            }

            return response()->json([
                'message' => 'OK',
            ], 200);
        }

        // Crear nueva sede con el ID actual en formato string
        $sede = UbicacionObraTh::create([
            'latitud' => $request->latitude,
            'longitud' => $request->longitude,
            'serial' => $request->serial,
            'obra_id' => $request->bodega_id,
            'user_id' => Auth::id(),
            'usuarios_permisos' => json_encode([$userId], JSON_UNESCAPED_UNICODE),
        ]);

        return response()->json([
            'message' => 'Ubicación registrada exitosamente.',
            'sede' => $sede
        ], 201);
    }



    public function obrasApp()
    {
        $apartamento = DB::connection('mysql')
            ->table('bodegas_area')
            ->select('id', 'nombre')
            ->where('estado', 1)
            ->get();


        return response()->json([
            'status' => 'success',
            'apartamentos' => $apartamento
        ]);
    }
}
