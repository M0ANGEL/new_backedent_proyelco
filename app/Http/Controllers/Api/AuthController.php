<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HorarioAdicional;
use App\Models\HorarioDetalle;
use App\Models\TiposDocumento;
use App\Models\UsersDocumentos;
use App\Models\UserSessionLogs;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
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


            // Verificar si ya tiene una sesión activa 
            if (count($user->tokens) > 0 && in_array($user->rol, ["Encargado Obras"])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El Usuario ya tiene una sesión activa en otro dispositivo o navegador',
                ], 401);
            }

            // Crear token de acceso
            $token = $user->createToken('token')->plainTextToken;
            $cookie = cookie('cookie_token', $token, 60 * 24);

            // Registrar IP y ubicación
            $ipaddress = json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $request->header('Public')));
            Log::channel('user_login')->info("User_Id: $user->id | Username: $user->username | IP: " . $request->header('Public') . " | Ciudad: $ipaddress->geoplugin_city | Departamento: $ipaddress->geoplugin_region | Lat: $ipaddress->geoplugin_latitude | Lon: $ipaddress->geoplugin_longitude");

            // Registrar el inicio de sesión en la tabla de logs
            // $sessionLog = new UserSessionLogs();
            // $sessionLog->user_id = $user->id;
            // $sessionLog->start_session = now();
            // $sessionLog->action = 'inicio de sesion';
            // $sessionLog->save();

            return response(["token" => $token], Response::HTTP_OK)->withoutCookie($cookie);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales incorrectas',
            ], 404);
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {

            $userLog = Auth::user();

            // $user_session = UserSessionLogs::where('user_id', $user->id)
            //     ->whereNull('last_session')
            //     ->latest()
            //     ->first();

            // if ($user_session) {
            //     $user_session->last_session = now();
            //     $user_start_session = Carbon::parse($user_session->start_session);
            //     $time = $user_start_session->diffInMinutes($user_session->last_session);
            //     $user_session->action = $time;
            //     $user_session->save();

            //     $userLog->last_login = now();
            //     $userLog->save();
            // }

            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
        }

        return response('Logged out successfully', 200);
    }

    public function userProfile()
    {
        try {
            $user = auth()->user()->load([
                'empresa',
                'empresas',
                'perfiles',
                'perfiles.modulos.modulo',
                'perfiles.modulos.menu',
                'perfiles.modulos.submenu',
                'cargos',
                'horario.detalles'
            ]);

            $moderador = $user->moderador_tickets;

            $user->perfiles = collect($user->perfiles)->map(function ($perfil) use ($moderador) {
                $menu = collect($perfil->modulos)->groupBy('id_modulo')->map(function ($modulo) use ($moderador) {
                    $moduloInfo = $modulo[0]->modulo;
                    $modulo_name = strtolower(str_replace(' ', '', $moduloInfo->nom_modulo));

                    if ($modulo[0]->id_menu) {
                        $menus = collect($modulo)->groupBy('id_menu')->map(function ($menu) use ($modulo_name, $moduloInfo, $moderador) {
                            $menuInfo = $menu[0]->menu;

                            if ($moderador == 0 && $moduloInfo->nom_modulo == 'Tickets' && in_array($menuInfo->nom_menu, [
                                "Categorías",
                                "SubCategorías",
                                "Administracion Tickets",
                                "Preguntas Tickets",
                                "Preguntas dinamicas",
                                "Reporte Tickets",
                                "Tickets Generados"
                            ])) {
                                return null;
                            }

                            if ($menu[0]->id_submenu) {
                                $submenus = collect($menu)->groupBy('id_submenu')->map(function ($submenu) use ($modulo_name, $moduloInfo, $menuInfo) {
                                    $submenuInfo = $submenu[0]->submenu;
                                    return [
                                        'key' => "$modulo_name/{$menuInfo->link_menu}/{$submenuInfo->link_smenu}",
                                        'label' => $submenuInfo->nom_smenu,
                                        'title' => $submenuInfo->desc_smenu,
                                    ];
                                })->values();

                                return [
                                    'key' => "$modulo_name/{$menuInfo->link_menu}",
                                    'label' => $menuInfo->nom_menu,
                                    'title' => $menuInfo->desc_menu,
                                    'children' => $submenus
                                ];
                            } else {
                                return [
                                    'key' => "$modulo_name/{$menuInfo->link_menu}",
                                    'label' => $menuInfo->nom_menu,
                                    'title' => $menuInfo->desc_menu,
                                ];
                            }
                        })->filter()->values();

                        return [
                            'key' => $modulo_name,
                            'cod_modulo' => $moduloInfo->cod_modulo,
                            'label' => $moduloInfo->nom_modulo,
                            'title' => $moduloInfo->desc_modulo,
                            'order' => $moduloInfo->id,
                            'children' => $menus,
                        ];
                    } else {
                        return [
                            'key' => $modulo_name,
                            'cod_modulo' => $moduloInfo->cod_modulo,
                            'label' => $moduloInfo->nom_modulo,
                            'title' => $moduloInfo->desc_modulo,
                            'order' => $moduloInfo->id,
                        ];
                    }
                })->sortBy('order')->values();

                $perfil->menu = $menu;
                return $perfil;
            });

            // //  horario del usuario
            // $perfilHorario = $user->horario;
            // $diaActual = ucfirst(Carbon::now()->locale('es')->isoFormat('dddd'));
            // $horaActual = Carbon::now()->format('H:i:s');
            // $horarioValido = false;
            // $horarioAdicional = null;

            // if ($perfilHorario) {
            //     $horarioValido = HorarioDetalle::where('horario_id', $perfilHorario->id)
            //         ->where('dia', $diaActual)
            //         ->whereTime('hora_inicio', '<=', $horaActual)
            //         ->whereTime('hora_final', '>=', $horaActual)
            //         ->exists();
            // }

            // if (!$horarioValido) {
            //     // la fecha y hora actual
            //     $fechaHoraActual = Carbon::now();

            //     // si el usuario está dentro de un horario adicional
            //     $horarioAdicional = HorarioAdicional::where('estado', 1)
            //         ->where('fecha_inicio', '<=', $fechaHoraActual)
            //         ->where('fecha_final', '>=', $fechaHoraActual)
            //         ->whereJsonContains('usuarios_autorizados', strval($user->id))
            //         ->first();


            //     if (!$horarioValido && !$horarioAdicional) {
            //         return response()->json([
            //             'status' => 'error',
            //             'message' => 'No estás dentro de tu horario laboral, o tu horario adicional no está disponible. Comunícate con TI.',
            //         ], 403);
            //     }
            // }

            // $user->horario_adicional = $horarioValido ? $perfilHorario : $horarioAdicional;

            return response()->json([
                'status' => 'success',
                'userData' => $user,

            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function clearSessions(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $user->tokens()->delete();
            return $user;
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Credenciales incorrectas',
            ], 404);
        }
    }
}
