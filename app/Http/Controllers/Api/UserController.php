<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\BodegaxUsu;
use App\Models\CargoUsuario;
use App\Models\BodegaxUsuLogs;
use App\Models\Cargo;
use App\Models\CargoUsuarioLog;
use App\Models\Empresa;
use App\Models\EmpxUsu;
use App\Models\EmpxUsuLog;
use App\Models\FuentesxUsuario;
use App\Models\User;
use App\Models\UserAliado;
use App\Models\UserLog;
use App\Models\UserPerfilLog;
use App\Models\UsersDocumentos;
use App\Models\UsersPerfiles;
use App\Rules\UniqueUserName;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with('empresa')->get();

            return response()->json([
                'status' => 'success',
                'data' => $users,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo cargar los datos de los usuarios.',
            ], 500);
        }
    }


    public function validarEmpleado($cedula)
    {
        if ($cedula == '12345678') {
            return true;
        }
        return DB::connection('mysql')->table('empleados')
            ->where('cedula', $cedula)
            ->exists();
    }

    public function register(Request $request)
    {
        DB::beginTransaction();
        try {

            $validatedData = $request->validate([
                'username' => ['required', 'string', 'max:100', 'unique:users,username'],
                'password' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{6,8}$/'],
                'cargos'   => ['required'],
                'perfiles' => ['required'],
                'correo' => ['nullable', 'email', 'max:255'],
                'can_config_telefono' => ['integer'],
            ]);

            $cargoName = Cargo::where('id',$request->cargos)->first();

            $data = $request->all();
            $user = new User();
            $user->cedula = $data['cedula'];
            $user->nombre = $data['nombre'];
            $user->telefono = $data['telefono'];
            $user->cargo = $cargoName->nombre;
            $user->username =  $data['username'];
            $user->password = bcrypt($data['password']);
            $user->rol = $data['rol'];
            $user->image = 'img/default.png';
            $user->last_login = now();
            $user->correo = $data['correo'];
             $user->can_config_telefono =  $data['can_config_telefono'];
            $user->save();


            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();

                if ($extension != 'png' && $extension != 'jpg' && $extension != 'jpeg') {
                    DB::rollBack();
                    return response()->json(['error' => 'El formato de imagen no es válido. Los formatos permitidos son: png, jpg, jpeg'], 400);
                } else {
                    $filename = $user->id . '.' . $extension;
                    $path = $file->storeAs('public/images', $filename);
                    $user->image = $path;
                    $user->save();
                }
            } else {
                $user->image = 'img/default.png';
                $user->save();
            }


            // registra las empresas

            $empresaUsu = new EmpxUsu();
            $empresaUsu->id_empresa = 1;
            $empresaUsu->id_user = $user->id; // Asignamos el ID del usuario a las empresas
            $empresaUsu->save();


            // Registra los perfiles por usuario
            $userPerfil = new UsersPerfiles();
            $userPerfil->id_perfil = $data['perfiles'];
            $userPerfil->id_user = $user->id;
            $userPerfil->save();

            // registrar acción en user_perfile_Logs
            $log = new UserPerfilLog();
            $log->id_user = Auth::user()->id;
            $log->id_perfil = $data['perfiles'];
            $log->accion = 'se creo perfil-usu con id ' . $userPerfil->id;
            $log->data = $userPerfil;
            $log->old = 'Registro nuevo sin data anterior';
            $log->save();

            // Registra los cargos por usuario

            $userCargo = new CargoUsuario();
            $userCargo->id_cargo = $data['cargos'];
            $userCargo->id_user = $user->id;
            $userCargo->estado = '1';
            $userCargo->save();

            // registrar acción en user_cargo_Logs
            $log = new CargoUsuarioLog();
            $log->id_user = Auth::user()->id;
            $log->id_cargo = $data['cargos'];
            $log->accion = 'se creo cargo-usuario con id ' . $userCargo->id;
            $log->data = $userCargo;
            $log->old = 'Registro nuevo sin data anterior';
            $log->save();



            // registrar acción en user_logs
            $log = new UserLog();
            $log->id_operario = Auth::user()->id; // id del usuario autenticado "quien lo creo"
            $log->id_user = $user->id; // id del usuario "recien creado"
            $log->accion = 'Creó un nuevo usuario con id ' . $user->id;
            $log->data = $user;
            $log->old = 'Registro nuevo sin data anterior'; //$data
            $log->save();

            DB::commit();
            return response()->json([
                'user' => $user,
                'empresas' => $empresaUsu,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'No se pudo registrar el usuario.',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        $empresas = EmpxUsu::with('empresa')->where('id_user', $id)->get();
        $perfiles = UsersPerfiles::with('perfil', 'perfil.empresa')->where('id_user', $id)->get();
        $cargos = CargoUsuario::with('cargo', 'cargo.empresas')->where('id_user', $id)->get();
        $user->bodegas_habilitadas = json_decode($user->bodegas_habilitadas);

        return response()->json([
            'user' => $user,
            'empresas' => $empresas,
            'perfiles' => $perfiles,
            'cargos' => $cargos,
        ]);
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validate([
                'cedula' => ['sometimes', 'string', 'max:20'],
                'nombre' => ['sometimes', 'string', 'max:100'],
                'telefono' => ['sometimes', 'string', 'max:20'],
                'cargo' => ['sometimes', 'string', 'max:50'],
                'rol' => ['sometimes', 'string', 'max:50'],
                'last_login' => ['sometimes', 'date'],
                'created_at' => ['sometimes', 'date'],
                'updated_at' => ['sometimes', 'date'],
                'correo' => ['nullable', 'email', 'max:255'],
                'can_config_telefono' => ['integer'],
            ]);

            // Verificar que no se actualice el estado o el username
            if (isset($validatedData['estado'])) {
                unset($validatedData['estado']);
            }
            if (isset($validatedData['username'])) {
                unset($validatedData['username']);
            }

            if (isset($request['password']) && $request['password'] != "") {
                $validatedData['password'] = bcrypt($request['password']);
            } else {
                unset($validatedData['password']);
            }

            $cargoName = Cargo::where('id',$request->cargos)->first();

            $data = User::findOrFail($id);
            $user = clone $data;
            $validatedData['cargo'] = $cargoName->nombre;
            $user->update($validatedData);



            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();

                if ($extension != 'png' && $extension != 'jpg' && $extension != 'jpeg') {
                    return response()->json(['error' => 'El formato de imagen no es válido. Los formatos permitidos son: png, jpg, jpeg'], 400);
                } else {
                    $filename = $user->id . '.' . $extension;
                    $file->storeAs('public/images', $filename);
                    $user->image = 'images/' . $filename;
                    $user->save();
                }
            }

            // actualiza las empresas
            $empUsuario = EmpxUsu::where([['id_empresa', '=', $request->empresas], ['id_user', '=', $user->id]])->get();
            $data = clone $empUsuario;
            if (count($empUsuario) == 0) {
                $empresaUsu = new EmpxUsu();
                $empresaUsu->id_empresa = $request->empresas;
                $empresaUsu->id_user = $user->id; // Asignamos el ID del usuario a las empresas
                $empresaUsu->save();

                // registrar acción en empresas_usu_Logs
                $log = new EmpxUsuLog();
                $log->id_user = Auth::user()->id;
                $log->id_emp_x_usu = $empresaUsu->id;
                $log->accion = 'se creo empresa-usu con id ' . $empresaUsu->id;
                $log->data = $empresaUsu;
                $log->old = $empUsuario;
                $log->save();
            }



            // Actualiza perfiles del usuario
            if ($user->id && $request->perfiles) {
                UsersPerfiles::where('id_user', $user->id)->delete();

                $userPerfil = new UsersPerfiles();
                $userPerfil->id_perfil = $request->perfiles;
                $userPerfil->id_user = $user->id;
                $userPerfil->save();

                // registrar acción en user_perfile_Logs
                $log = new UserPerfilLog();
                $log->id_user = Auth::user()->id;
                $log->id_perfil = $request->perfiles;
                $log->accion = 'se creo perfil-usu con id ' . $userPerfil->id;
                $log->data = $userPerfil;
                $log->old = "";
                $log->save();
            }

            // Actualiza cargos del usuario
            if ($user->id && $request->cargos) {
                $data = CargoUsuario::where('id_user', $user->id)->delete();
                $userCargo = new CargoUsuario();
                $userCargo->id_cargo = $request->cargos;
                $userCargo->id_user = $user->id;
                $userCargo->save();
            }

            $newdata = $user;
            // registrar acción en user_logs
            $log = new UserLog();
            $log->id_operario = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
            $log->id_user = $user->id; // id del usuario "recien modificado"
            $log->accion = 'Modificó el usuario con id ' . $user->id;
            $log->data = $newdata;
            $log->old = $data;
            $log->save();

            DB::commit();
            return response()->json(['message' => 'El usuario se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'No se pudo actualizar el usuario.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = User::findOrFail($id);
            $user = clone $data;
            $user->estado = $user->estado == 1 ? 0 : 1;
            $user->save();

            $accion = $user->estado == 1 ? 'Se activo' : 'Se desactivo';

            // registrar acción en user_logs
            $log = new UserLog();
            $log->id_operario = Auth::user()->id;
            $log->id_user = $user->id;
            $log->accion = $accion . ' el usuario con id ' . $user->id;
            $log->data = $user;
            $log->old = $data;
            $log->save();

            return response()->json(['message' => 'El estado del usuario se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el estado del usuario.'], 500);
        }
    }

    function usernameUnique($username)
    {
        $user = User::where('username', $username)->first();

        if ($user) {
            return response()->json($user);
        }
        // Si no se encuentra un usuario
        return response()->json($user);
    }

    function cambioImagen(Request $request)
    {
        try {

            $id = Auth::user()->id;
            $user = User::findOrFail($id);

            if ($request->hasFile('image')) {

                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();

                if ($extension != 'png' && $extension != 'jpg' && $extension != 'jpeg') {
                    return response()->json(['error' => 'El formato de imagen no es válido. Los formatos permitidos son: png, jpg, jpeg'], 400);
                } else {
                    $filename = $user->id . '.' . $extension;
                    $path = $file->storeAs('public/images', $filename);
                    $user->image = 'images/' . $filename;
                    $user->save();
                }
            } else {
                $user->image = 'img/default.png';
                $user->save();
            }

            return response()->json([
                'message' => 'La imagen se actualizó correctamente.',
                'data' => $user->image
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar la imagen.'], 500);
        }
    }

    public function cambioContrasena(Request $request)
    {
        $user = Auth::user();

        // Validar que la contraseña actual sea correcta
        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['error' => 'La contraseña actual es incorrecta'], 400);
        }

        // Validar que las contraseñas sean iguales
        if ($request->input('password') !== $request->input('password_confirmation')) {
            return response()->json(['error' => 'Las contraseñas no coinciden'], 400);
        }

        // Validar que la contraseña nueva no sea igual a la vieja
        if (Hash::check($request->input('password'), $user->password)) {
            return response()->json(['error' => 'La contraseña nueva no puede ser igual a la contraseña actual'], 400);
        }


        // Validar que la contraseña nueva cumpla con la expresión regular
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'regex:/^[a-zA-Z0-9]{6,8}$/'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'La contraseña nueva no cumple con los requisitos'], 400);
        }

        // Actualizar la contraseña
        $user->password = Hash::make($request->input('password'));
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente'], 200);
    }

    // public function removerItem(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         switch ($request->ctrl) {
    //             case 'empresa':
    //                 $empresaUsu = EmpxUsu::with('empresa')->findOrFail($request->id);
    //                 $empresaUsu->delete();
    //                 // Registrar acción en empresas_usu_Logs
    //                 $log = new EmpxUsuLog();
    //                 $log->id_user = Auth::user()->id;
    //                 $log->id_emp_x_usu = $empresaUsu->id;
    //                 $log->accion = 'Se elimina empresa-usu con id ' . $empresaUsu->id . ' correspondiente a la EMPRESA: ' . $empresaUsu->empresa->emp_nombre;
    //                 $log->data = 'Registro eliminado';
    //                 $log->old = $empresaUsu;
    //                 $log->save();
    //                 foreach ($request->bodegas as $bodegaUsu_id) {
    //                     $bodegaUsu = BodegaxUsu::with('bodega')->findOrFail($bodegaUsu_id);
    //                     $bodegaUsu->delete();
    //                     // Registrar acción en bodegas_usu_Logs
    //                     $log = new BodegaxUsuLogs();
    //                     $log->id_user = Auth::user()->id;
    //                     $log->id_bodega_x_usu = $bodegaUsu->id;
    //                     $log->accion = 'Se eliminó bodega-usu con id ' . $bodegaUsu->id . ' correspondiente a la BODEGA: ' . $bodegaUsu->bodega->bod_nombre;
    //                     $log->data = 'Registro eliminado';
    //                     $log->old = $bodegaUsu;
    //                     $log->save();
    //                 }
    //                 break;
    //             case 'bodega':
    //                 $bodegaUsu = BodegaxUsu::with('bodega')->findOrFail($request->id);
    //                 $bodegaUsu->delete();
    //                 // Registrar acción en bodegas_usu_Logs
    //                 $log = new BodegaxUsuLogs();
    //                 $log->id_user = Auth::user()->id;
    //                 $log->id_bodega_x_usu = $bodegaUsu->id;
    //                 $log->accion = 'Se eliminó bodega-usu con id ' . $bodegaUsu->id . ' correspondiente a la BODEGA: ' . $bodegaUsu->bodega->bod_nombre;
    //                 $log->data = 'Registro eliminado';
    //                 $log->old = $bodegaUsu;
    //                 $log->save();
    //                 break;
    //         }

    //         DB::commit();
    //         return response()->json([
    //             'message' => ucfirst($request->ctrl) . " eliminada con éxito!",
    //         ], Response::HTTP_ACCEPTED);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //         ], 500);
    //     }
    // }

    public function getListUsersByRoles(Request $request)
    {
        DB::beginTransaction();
        try {
            $usuarios = User::whereIn('rol', $request->roles)->get();

            DB::commit();
            return response()->json([
                'data' => $usuarios,
            ], Response::HTTP_ACCEPTED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
