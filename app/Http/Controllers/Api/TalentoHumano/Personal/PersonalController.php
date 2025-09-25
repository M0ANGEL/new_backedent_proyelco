<?php

namespace App\Http\Controllers\Api\TalentoHumano\Personal;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PersonalController extends Controller
{
    public function index()
    {
        $Personales = DB::connection('mysql')
            ->table('personal')
            // ->join('users', 'Personales.id_user', '=', 'users.id') // Se especifica users.user_id
            // ->select('Personales.*', 'users.nombre as nombre') // Seleccionamos todas las columnas de tk_categorias y el nombre del usuario
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $Personales
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identificacion' => ['required', 'string', 'unique:personal,identificacion'],
                'tipo_documento' => ['required', 'string'],
                'nombre_completo' => ['required', 'string'],
                'fecha_expedicion' => ['required', 'string'],
                'estado_civil' => ['required', 'string'],
                'ciuda_expedicion_id' => ['required', 'string'],
                'fecha_nacimiento' => ['required', 'string'],
                'pais_residencia_id' => ['required', 'string'],
                'ciudad_resudencia_id' => ['required', 'string'],
                'genero' => ['required', 'string'],
                'telefono_fijo' => ['required', 'string'],
                'telefono_celular' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'correo' => ['required', 'string'],
                'cargo_id' => ['required', 'string'],
                'salario' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            //calcular valor de hora
            $nuevoValorHora = $request->salario / 220;

            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $Personal = new Personal();
            $Personal->identificacion = $request->identificacion;
            $Personal->tipo_documento = $request->tipo_documento;
            $Personal->nombre_completo = $request->nombre_completo;
            $Personal->fecha_expedicion = Carbon::parse($request->fecha_expedicion)->format('Y-m-d');
            $Personal->estado_civil = $request->estado_civil;
            $Personal->ciuda_expedicion_id = $request->ciuda_expedicion_id;
            $Personal->fecha_nacimiento = Carbon::parse($request->fecha_nacimiento)->format('Y-m-d');
            $Personal->pais_residencia_id = $request->pais_residencia_id;
            $Personal->ciudad_resudencia_id = $request->ciudad_resudencia_id;
            $Personal->genero = $request->genero;
            $Personal->telefono_fijo = $request->telefono_fijo ? $request->telefono_fijo : null;
            $Personal->telefono_celular = $request->telefono_celular ? $request->telefono_celular : null;
            $Personal->direccion = $request->direccion ? $request->direccion : null;
            $Personal->correo = $request->correo ? $request->correo : null;
            $Personal->cargo_id = $request->cargo_id;
            $Personal->salario = $request->salario;
            $Personal->valor_hora = $nuevoValorHora;
            $Personal->id_user = $user->id;
            $Personal->save();

            return response()->json([
                'status' => 'success',
                'data' => $Personal
            ], 200);
        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Personal::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombres' => ['required', 'string'],
                'apellidos' => ['required', 'string'],
                'cedula' => ['required', 'string'],
                'telefono' => ['required', 'string'],
                'cargo_id' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $Personal = Personal::findOrFail($id);
            $Personal->nombres = $request->nombres;
            $Personal->apellidos = $request->apellidos;
            $Personal->cedula = $request->cedula;
            $Personal->telefono = $request->telefono;
            $Personal->cargo_id = $request->cargo_id;
            // $Personal->id_user = Auth::id();
            $Personal->save();

            return response()->json([
                'status' => 'success',
                'data' => $Personal
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
                'code' => $e->getCode()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $Personal = Personal::find($id);

        $Personal->estado = !$Personal->estado;
        $Personal->update();
    }
}
