<?php

namespace App\Http\Controllers\Api\TalentoHumano\Personal;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\PersonalProyelco;
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
            ->table('empleados_th')
            ->join('cargos_th', 'empleados_th.cargo_id', 'cargos_th.id')
            ->select(
                'empleados_th.*',
                'cargos_th.cargo'
            )
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
                'identificacion' => ['required', 'string'],
                'tipo_documento' => ['required', 'string'],
                'nombre_completo' => ['required', 'string'],
                'fecha_expedicion' => ['required', 'string'],
                'estado_civil' => ['required', 'string'],
                'ciuda_expedicion_id' => ['required'],
                'fecha_nacimiento' => ['required', 'string'],
                'pais_residencia_id' => ['required'],
                'ciudad_resudencia_id' => ['required'],
                'genero' => ['required', 'string'],
                'telefono_fijo' => ['required', 'string'],
                'telefono_celular' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'correo' => ['required', 'string'],
                'cargo_id' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            //validar que la cedula sea unica
            $cedulaUnicaProyelco = PersonalProyelco::where('identificacion', $request->identificacion)
                ->select('nombre_completo')
                ->first();
            if ($cedulaUnicaProyelco) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este cedula esta registrada a personal de proyelco, nombre: ' .  $cedulaUnicaProyelco->nombre_completo,
                ], 404);
            }

            //validar que la cedula sea unica
            $cedulaUnica = Personal::where('identificacion', $request->identificacion)
                ->select('nombre_completo')
                ->first();
            if ($cedulaUnica) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este cedula esta registrada a personal no proyelco, nombre: ' .  $cedulaUnicaProyelco->nombre_completo,
                ], 404);
            }

            $salario = $request->salarioMinimo == "SI" ?  1423500 : $request->salario;


            //calcular valor de hora
            $nuevoValorHora = intval($salario / 220); //sin decimales


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
            $Personal->minimo = $request->salarioMinimo;
            $Personal->salario = $salario;
            $Personal->valor_hora = $nuevoValorHora;
            $Personal->user_id = Auth::id();
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
                'identificacion' => ['required', 'string'],
                'tipo_documento' => ['required', 'string'],
                'nombre_completo' => ['required', 'string'],
                'fecha_expedicion' => ['required', 'string'],
                'estado_civil' => ['required', 'string'],
                'ciuda_expedicion_id' => ['required'],
                'fecha_nacimiento' => ['required', 'string'],
                'pais_residencia_id' => ['required'],
                'ciudad_resudencia_id' => ['required'],
                'genero' => ['required', 'string'],
                'telefono_fijo' => ['required', 'string'],
                'telefono_celular' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'correo' => ['required', 'string'],
                'cargo_id' => ['required', 'string'],
                'salario' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }


            //validar que la cedula sea unica en personal_proyelco, excluyendo el registro actual
            $cedulaUnicaProyelco = PersonalProyelco::where('identificacion', $request->identificacion)
                // ->where('id', '!=', $id) // ignorar el registro actual
                ->select('nombre_completo')
                ->first();

            if ($cedulaUnicaProyelco) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Esta cédula está registrada a personal de proyelco, nombre: ' . $cedulaUnicaProyelco->nombre_completo,
                ], 404);
            }

            //validar que la cedula sea unica en personal, sin importar proyelco
            $cedulaUnica = Personal::where('identificacion', $request->identificacion)
                ->select('nombre_completo')
                ->first();

            if ($cedulaUnica) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Esta cédula está registrada a personal no proyelco, nombre: ' . $cedulaUnica->nombre_completo,
                ], 404);
            }



            $salario = $request->salarioMinimo == "SI" ?  1423500 : $request->salario;


            //calcular valor de hora
            $nuevoValorHora = intval($salario / 220); //sin decimales

            // Obtener la categoría actual
            $Personal = Personal::findOrFail($id);
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
            $Personal->minimo = $request->salarioMinimo;
            $Personal->salario = $salario;
            $Personal->valor_hora = $nuevoValorHora;
            $Personal->user_id = Auth::id();
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


    /* FICHA DE TRABAJADOR */

    public function usuarioCedulaFicha($cedula)
    {
        $Personal = DB::connection('mysql')
            ->table('empleados_th')
            ->where('identificacion', $cedula)
            ->first();

        if ($Personal == null) {
            $Personal = DB::connection('mysql')
                ->table('empleados_proyelco_th')
                ->where('identificacion', $cedula)
                ->first();
        }



        return response()->json($Personal);
    }
}
