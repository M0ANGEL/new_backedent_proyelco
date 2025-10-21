<?php

namespace App\Http\Controllers\Api\TalentoHumano\PersonalProyelco;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\FichaObra;
use App\Models\Personal;
use App\Models\PersonalProyelco;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PersonalProyelcoController extends Controller
{
    public function index()
    {
        $Personales = DB::connection('mysql')
            ->table('empleados_proyelco_th')
            ->join('cargos_th', 'empleados_proyelco_th.cargo_id', 'cargos_th.id')
            ->select(
                'empleados_proyelco_th.*',
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
                'fecha_ingreso' => ['required', 'string'],
                'salario' => ['required', 'string'],
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


            //calcular valor de hora
            $nuevoValorHora = intval($request->salario / 220); //sin decimales


            $user = Auth::user();
            // Si la validación ok creamos la nueva categoría
            $Personal = new PersonalProyelco();
            $Personal->identificacion = $request->identificacion;
            $Personal->tipo_documento = $request->tipo_documento;
            $Personal->nombre_completo = $request->nombre_completo;
            $Personal->fecha_expedicion = Carbon::parse($request->fecha_expedicion)->format('Y-m-d');
            $Personal->estado_civil = $request->estado_civil;
            $Personal->ciuda_expedicion_id = $request->ciuda_expedicion_id;
            $Personal->fecha_nacimiento = Carbon::parse($request->fecha_nacimiento)->format('Y-m-d');
            $Personal->fecha_ingreso = Carbon::parse($request->fecha_ingreso)->format('Y-m-d');
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
            $Personal->user_id = $user->id;
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
        return response()->json(PersonalProyelco::find($id), 200);
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
                'fecha_ingreso' => ['required', 'string'],
                'salario' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }


            //validar que la cedula sea unica en personal_proyelco, excluyendo el registro actual
            $cedulaUnicaProyelco = PersonalProyelco::where('identificacion', $request->identificacion)
                ->where('id', '!=', $id) // ignorar el registro actual
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


            //calcular valor de hora
            $nuevoValorHora = intval($request->salario / 220); //sin decimales

            // Obtener la categoría actual
            $Personal = PersonalProyelco::findOrFail($id);
            $Personal->identificacion = $request->identificacion;
            $Personal->tipo_documento = $request->tipo_documento;
            $Personal->nombre_completo = $request->nombre_completo;
            $Personal->fecha_expedicion = Carbon::parse($request->fecha_expedicion)->format('Y-m-d');
            $Personal->estado_civil = $request->estado_civil;
            $Personal->ciuda_expedicion_id = $request->ciuda_expedicion_id;
            $Personal->fecha_nacimiento = Carbon::parse($request->fecha_nacimiento)->format('Y-m-d');
            $Personal->fecha_ingreso = Carbon::parse($request->fecha_ingreso)->format('Y-m-d');
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
        $Personal = PersonalProyelco::find($id);

        $Personal->estado = !$Personal->estado;
        $Personal->update();
    }


    public function paises()
    {
        $paises = DB::connection('mysql')
            ->table('pais_th')
            ->where('estado', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $paises
        ], 200);
    }


    public function ciudades($id)
    {
        $paises = DB::connection('mysql')
            ->table('ciudad_th')
            ->where('estado', 1)
            ->where('pais_id', $id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $paises
        ], 200);
    }

    public function cargos()
    {
        $cargos_th = DB::connection('mysql')
            ->table('cargos_th')
            ->where('estado', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cargos_th
        ], 200);
    }

    public function checkActivosPendientes($empleadoId)
    {
        try {
            // Verificar si el empleado existe
            $empleado = PersonalProyelco::find($empleadoId);

            if (!$empleado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            // Obtener el usuario por cédula
            $usuario = User::where('cedula', $empleado->identificacion)->first();


            if (!$usuario) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'tieneActivosPendientes' => false,
                        'totalActivosPendientes' => 0,
                        'activos' => []
                    ]
                ], 200);
            }

            // Consultar activos pendientes del empleado
            // Usando JSON_CONTAINS para buscar en el array JSON
            $activosPendientes = Activo::whereRaw("JSON_CONTAINS(usuarios_confirmaron, '\"{$usuario->id}\"')")
                ->where('estado', '1') // Asumiendo que tienes un campo estado
                ->get();

            $tieneActivosPendientes = $activosPendientes->count() > 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'tieneActivosPendientes' => $tieneActivosPendientes,
                    'totalActivosPendientes' => $activosPendientes->count(),
                    'activos' => $activosPendientes->map(function ($activo) {
                        return [
                            'id' => $activo->id,
                            'nombre' => $activo->descripcion,
                            'numero_activo' => $activo->numero_activo,
                        ];
                    })
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al verificar activos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Inactivar empleado (DeletePersonal)
     */
    public function inactivarPersonal(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            // Buscar el empleado
            $empleado = PersonalProyelco::find($id);

            if (!$empleado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            // Verificar si ya está inactivo
            if ($empleado->estado == 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El empleado ya se encuentra inactivo'
                ], 400);
            }

            // Obtener el motivo de la request
            $motivo = $request->motivo;

            // Inactivar el empleado - CORREGIDO el nombre del campo
            $empleado->update([
                'estado' => 0, // 0 = inactivo
                'fecha_terminacion' => now(),
                'motivo_retiro' => $motivo,
                'uuario_retira' => auth()->id() // CORREGIDO: era 'uuario_retira'
            ]);

            // Recargar el modelo para ver los cambios
            $empleado->refresh();

            //inactivar ficha igual

            $ficha = FichaObra::where('identificacion', $empleado->identificacion)->first();
            $ficha->update([
                'estado' => 2, // 2 = inactivo por retiro
            ]);


            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Empleado inactivado exitosamente',
                'data' => [
                    'empleado' => $empleado->nombre_completo,
                    'fecha_retiro' => now()->format('Y-m-d H:i:s'),
                    'motivo' => $motivo,
                    'usuario_retira' => auth()->id()
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error al inactivar empleado:', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al inactivar empleado: ' . $e->getMessage()
            ], 500);
        }
    }
}
