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

    // public function validarTelefono(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'serialTelefono' => ['required', 'string']
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['errors' => $validator->errors()], 400);
    //         }

    //         // Buscar el teléfono en la base de datos principal
    //         $telefono = MaTelefono::where('serial_email', $request->serialTelefono)->where('estado', 1)->first();

    //         if (!$telefono) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'El serial no está registrado, comunícate con TI',
    //             ], 404);
    //         }

    //         //consulta de usuario y sus obras
    //         $apartamentos = DB::table('proyecto')
    //             ->where(function ($query) {
    //                 $userId = Auth::id();
    //                 $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')");
    //                 // ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
    //             })
    //             ->select(
    //                 'id',
    //                 'descripcion',
    //                 'tipoProyecto_id',
    //             )
    //             ->get();

    //         $casas = DB::table('proyectos_casas')
    //             ->where(function ($query) {
    //                 $userId = Auth::id();
    //                 $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')");
    //                 // ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
    //             })
    //             ->select(
    //                 'id',
    //                 'descripcion',
    //                 'tipoProyecto_id',
    //             )
    //             ->get();

    //             //ya teniendo los proyectos de casas y apartamentos,
    //             $ubicacion = DB::table('ubicacione_obras_th')
    //             ->where('tipo_obra')
    //             ->where('obra_id')
    //             ->select(
    //                 'id',
    //                 'descripcion',
    //                 'tipoProyecto_id',
    //             )
    //             ->get();


    //         // if ($sedes->isEmpty()) {
    //         //     return response()->json([
    //         //         'status' => 'error',
    //         //         'message' => 'No se encontró información de la sede',
    //         //     ], 404);
    //         // }

    //         // return response()->json([
    //         //     'status' => 'success',
    //         //     'sedes' => $sedes
    //         // ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function validarTelefono(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'serialTelefono' => ['required', 'string']
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['errors' => $validator->errors()], 400);
    //         }

    //         // Buscar el teléfono
    //         $telefono = MaTelefono::where('serial_email', $request->serialTelefono)
    //             ->where('estado', 1)
    //             ->first();

    //         if (!$telefono) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'El serial no está registrado, comunícate con TI',
    //             ], 404);
    //         }

    //         $userId = Auth::id();

    //         // 🔹 1. Buscar apartamentos del usuario
    //         $apartamentos = DB::table('proyecto')
    //             ->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')")
    //             ->select('id', 'descripcion_proyecto', 'tipoProyecto_id')
    //             ->get();

    //         // 🔹 2. Buscar casas del usuario
    //         $casas = DB::table('proyectos_casas')
    //             ->whereRaw("JSON_CONTAINS(proyectos_casas.encargado_id, '\"$userId\"')")
    //             ->select('id', 'descripcion_proyecto', 'tipoProyecto_id')
    //             ->get();

    //         // 🔹 3. Unir IDs de todas las obras
    //         $obrasIds = collect($apartamentos)->pluck('id')
    //             ->merge(collect($casas)->pluck('id'))
    //             ->toArray();

    //         if (empty($obrasIds)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No se encontraron obras asignadas para este usuario',
    //             ], 404);
    //         }

    //         // 🔹 4. Buscar ubicaciones y filtrar solo las obras que tienen
    //         $ubicaciones = DB::table('ubicacion_obras_th')
    //             ->whereIn('obra_id', $obrasIds)
    //             ->select('id', 'obra_id', 'tipo_obra', 'latitud', 'longitud')
    //             ->get();

    //         // 🔹 5. Filtrar las obras que sí tengan ubicación
    //         $obrasConUbicacionIds = $ubicaciones->pluck('obra_id')->toArray();

    //         $apartamentosConUbicacion = $apartamentos->filter(fn($a) => in_array($a->id, $obrasConUbicacionIds))->values();
    //         $casasConUbicacion = $casas->filter(fn($c) => in_array($c->id, $obrasConUbicacionIds))->values();

    //         // 🔹 6. Responder solo las que tienen ubicación
    //         return response()->json([
    //             'status' => 'success',
    //             'apartamentos' => $apartamentosConUbicacion,
    //             'casas' => $casasConUbicacion,
    //             'ubicaciones' => $ubicaciones
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }

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

        $userId = Auth::id();

        // 🔹 1. Buscar apartamentos del usuario CON ubicación
        $apartamentosConUbicacion = DB::table('proyecto')
            ->join('ubicacion_obras_th', function($join) {
                $join->on('proyecto.id', '=', 'ubicacion_obras_th.obra_id')
                     ->where('ubicacion_obras_th.tipo_obra', '=', 1);
            })
            ->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')")
            ->select('proyecto.id', 'proyecto.descripcion_proyecto', 'proyecto.tipoProyecto_id')
            ->distinct()
            ->get();

        // 🔹 2. Buscar casas del usuario CON ubicación
        $casasConUbicacion = DB::table('proyectos_casas')
            ->join('ubicacion_obras_th', function($join) {
                $join->on('proyectos_casas.id', '=', 'ubicacion_obras_th.obra_id')
                     ->where('ubicacion_obras_th.tipo_obra', '=', 2);
            })
            ->whereRaw("JSON_CONTAINS(proyectos_casas.encargado_id, '\"$userId\"')")
            ->select('proyectos_casas.id', 'proyectos_casas.descripcion_proyecto', 'proyectos_casas.tipoProyecto_id')
            ->distinct()
            ->get();

        // 🔹 3. Buscar todas las ubicaciones para estas obras
        $obrasIds = $apartamentosConUbicacion->pluck('id')
            ->merge($casasConUbicacion->pluck('id'))
            ->toArray();

        $ubicaciones = DB::table('ubicacion_obras_th')
            ->whereIn('obra_id', $obrasIds)
            ->select('id', 'obra_id', 'tipo_obra', 'latitud', 'longitud')
            ->get();

        

        if ($apartamentosConUbicacion->isEmpty() && $casasConUbicacion->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron obras con ubicación asignadas para este usuario',
            ], 404);
        }

        // 🔹 5. Responder solo las que tienen ubicación
        return response()->json([
            'status' => 'success',
            'apartamentos' => $apartamentosConUbicacion,
            'casas' => $casasConUbicacion,
            'ubicaciones' => $ubicaciones
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

    public function registarUbicacionObra(Request $request)
    {
        $request->validate([
            'serial' => 'required|string',
            'bodega_id' => 'required',
            'tipo_proyecto' => 'required|string',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        // Obtener el ID del teléfono a partir del serial
        $telefono = MaTelefono::where('serial_email', $request->serial)->first();

        if (!$telefono) {
            return response()->json([
                'message' => 'El teléfono no está registrado.',
            ], 404);
        }


        $sede = UbicacionObraTh::create([
            'latitud' => $request->latitude,
            'longitud' => $request->longitude,
            'serial' => $request->serial,
            'tipo_obra' => $request->tipo_proyecto == "apartamento" ? 1 : 2,
            'obra_id' => $request->bodega_id,
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Ubicacion registrada exitosamente.',
            'sede' => $sede
        ], 201);
    }

    public function obrasApp()
    {
        $apartamento = DB::connection('mysql')
            ->table('proyecto')
            ->select('id', 'tipoProyecto_id', 'descripcion_proyecto')
            ->where('estado', 1)
            ->get();


        $casas = DB::connection('mysql')
            ->table('proyectos_casas')
            ->select('id', 'tipoProyecto_id', 'descripcion_proyecto')
            ->where('estado', 1)
            ->get();


        return response()->json([
            'status' => 'success',
            'apartamentos' => $apartamento,
            'casas' => $casas
        ]);
    }
}
