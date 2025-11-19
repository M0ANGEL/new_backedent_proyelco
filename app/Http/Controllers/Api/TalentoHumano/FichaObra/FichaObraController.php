<?php

namespace App\Http\Controllers\Api\TalentoHumano\FichaObra;

use App\Http\Controllers\Controller;
use App\Models\FichaObra;
use App\Models\Personal;
use App\Models\PersonalProyelco;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class FichaObraController extends Controller
{


    public function index()
    {
        $personales = DB::connection('mysql')
            ->table('ficha_th')
            ->leftJoin('empleados_proyelco_th as ep', function ($join) {
                $join->on('ficha_th.empleado_id', '=', 'ep.id')
                    ->where('ficha_th.tipo_empleado', 1);
            })
            ->leftJoin('empleados_th as et', function ($join) {
                $join->on('ficha_th.empleado_id', '=', 'et.id')
                    ->where('ficha_th.tipo_empleado', 2);
            })
            ->leftJoin('cargos_th as c', function ($join) {
                $join->on('ep.cargo_id', '=', 'c.id')
                    ->orOn('et.cargo_id', '=', 'c.id');
            })
            ->leftJoin('ciudad_th as ci_exp', function ($join) {
                $join->on('ep.ciuda_expedicion_id', '=', 'ci_exp.id')
                    ->orOn('et.ciuda_expedicion_id', '=', 'ci_exp.id');
            })
            ->leftJoin('ciudad_th as ci_res', function ($join) {
                $join->on('ep.ciudad_resudencia_id', '=', 'ci_res.id')
                    ->orOn('et.ciudad_resudencia_id', '=', 'ci_res.id');
            })
            ->leftJoin('pais_th as p_res', function ($join) {
                $join->on('ep.pais_residencia_id', '=', 'p_res.id')
                    ->orOn('et.pais_residencia_id', '=', 'p_res.id');
            })
            ->leftJoin('contratistas_th as cont', 'ficha_th.contratista_id', '=', 'cont.id')
            ->select(
                // Datos básicos de ficha_th
                'ficha_th.id',
                'ficha_th.tipo_empleado',
                'ficha_th.empleado_id',
                'ficha_th.identificacion',
                'ficha_th.rh',
                'ficha_th.hijos',
                'ficha_th.eps',
                'ficha_th.afp',
                'ficha_th.contratista_id',
                'ficha_th.estado',
                'ficha_th.created_at',
                'ficha_th.updated_at',

                // Datos del empleado (según tipo)
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.nombre_completo
                    WHEN ficha_th.tipo_empleado = 2 THEN et.nombre_completo
                END as nombre_completo
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.tipo_documento
                    WHEN ficha_th.tipo_empleado = 2 THEN et.tipo_documento
                END as tipo_documento
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.fecha_expedicion
                    WHEN ficha_th.tipo_empleado = 2 THEN et.fecha_expedicion
                END as fecha_expedicion
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.estado_civil
                    WHEN ficha_th.tipo_empleado = 2 THEN et.estado_civil
                END as estado_civil
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.fecha_nacimiento
                    WHEN ficha_th.tipo_empleado = 2 THEN et.fecha_nacimiento
                END as fecha_nacimiento
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.genero
                    WHEN ficha_th.tipo_empleado = 2 THEN et.genero
                END as genero
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.telefono_fijo
                    WHEN ficha_th.tipo_empleado = 2 THEN et.telefono_fijo
                END as telefono_fijo
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.telefono_celular
                    WHEN ficha_th.tipo_empleado = 2 THEN et.telefono_celular
                END as telefono_celular
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.direccion
                    WHEN ficha_th.tipo_empleado = 2 THEN et.direccion
                END as direccion
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.correo
                    WHEN ficha_th.tipo_empleado = 2 THEN et.correo
                END as correo
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.salario
                    WHEN ficha_th.tipo_empleado = 2 THEN et.salario
                END as salario
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.valor_hora
                    WHEN ficha_th.tipo_empleado = 2 THEN et.valor_hora
                END as valor_hora
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 2 THEN et.minimo
                    ELSE NULL
                END as minimo
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.fecha_terminacion
                    ELSE NULL
                END as fecha_terminacion
            "),
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN ep.motivo_retiro
                    ELSE NULL
                END as motivo_retiro
            "),

                // Información de tablas relacionadas
                'c.cargo',
                'ci_exp.ciudad as ciudad_expedicion',
                'ci_res.ciudad as ciudad_residencia',
                'p_res.pais as pais_residencia',
                'cont.contratista as nombre_contratista',

                // Tipo de empleado como texto
                DB::raw("
                CASE 
                    WHEN ficha_th.tipo_empleado = 1 THEN 'Empleado Proyelco'
                    WHEN ficha_th.tipo_empleado = 2 THEN 'Empleado No Proyelco'
                    ELSE 'Desconocido'
                END as tipo_empleado_texto
            ")
            )
            ->orderBy('ficha_th.created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $personales
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'identificacion' => ['required', 'string', 'unique:ficha_th,identificacion'],
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
                'cargo_id' => ['required', 'string'],
                'contratista_id' => ['required'],
                'salario' => ['required', 'numeric'],
                'eps' => ['required', 'string'],
                'pension' => ['required', 'string'],
                'tipo_sangre' => ['required', 'string'],
                'numero_hijos' => ['required', 'integer', 'min:0', 'max:20'],
                'foto' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $car = 0;
            // Buscamos el usuario para saber su tipo
            $proyelco = PersonalProyelco::where('identificacion', $request->identificacion)->first();
            if ($proyelco == null) {
                $car = 1;
                $empleado = Personal::where('identificacion', $request->identificacion)->first();

                // Verificar si se encontró el empleado
                if (!$empleado) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No se encontró el empleado con la identificación proporcionada',
                    ], 404);
                }
            }

            $datosDelEmpelado = $car == 0 ? $proyelco : $empleado;

            // Crear el nuevo empleado
            $personal = new FichaObra();
            $personal->tipo_empleado = $car == 0 ? 1 : 2;
            $personal->identificacion = $datosDelEmpelado->identificacion;
            $personal->empleado_id = $datosDelEmpelado->id;
            $personal->rh = $request->tipo_sangre;
            $personal->hijos = $request->numero_hijos;
            $personal->eps = $request->eps;
            $personal->afp = $request->pension;
            $personal->contratista_id = $request->contratista_id;

            $personal->save();

            // Procesar la foto si existe
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');

                // Generar nombre único para el archivo
                $nombreArchivo = 'empleado_' . $personal->id  .  '.' . $foto->getClientOriginalExtension();

                // Ruta donde se guardará (sin la carpeta public)
                $ruta = 'SST/' . $nombreArchivo;

                // Usar storeAs para guardar el archivo
                $rutaGuardada = $foto->storeAs('SST', $nombreArchivo, 'public');
            } else {
                info('No se recibió archivo de foto');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Empleado creado exitosamente',
                'data' => $personal
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el empleado: ' . $e->getMessage(),
            ], 500);
        }
    }

    // public function show($id)
    // {
    //     return response()->json(FichaObra::find($id), 200);
    // }

    public function show($id)
    {
        $empleado = FichaObra::find($id);

        if (!$empleado) {
            return response()->json([
                'status' => 'error',
                'message' => 'Empleado no encontrado'
            ], 404);
        }

        // Buscar foto del empleado con formato id.extension
        $fotoUrl = $this->buscarFotoEmpleado($id);

        $responseData = [
            'empleado' => $empleado,
            'foto_url' => $fotoUrl,
            'tiene_foto' => !is_null($fotoUrl)
        ];

        return response()->json($responseData, 200);
    }

    private function buscarFotoEmpleado($empleadoId)
    {
        $directorio = storage_path('app/public/SST');

        // Verificar si existe el directorio
        if (!file_exists($directorio)) {
            return null;
        }

        // Extensiones de imagen permitidas
        $extensiones = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'];

        foreach ($extensiones as $extension) {
            $nombreArchivo = 'empleado_' . $empleadoId . '.' . $extension;
            $rutaCompleta = $directorio . '/' . $nombreArchivo;

            if (file_exists($rutaCompleta)) {
                return asset('storage/SST/' . $nombreArchivo);
            }
        }

        return null;
    }



    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                // ... tus validaciones existentes ...
                'foto' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Buscar el empleado existente
            $personal = FichaObra::findOrFail($id);

            // Actualizar datos
            $personal->rh = $request->tipo_sangre;
            $personal->hijos = $request->numero_hijos;
            $personal->eps = $request->eps;
            $personal->afp = $request->pension;
            $personal->contratista_id = $request->contratista_id;

            // Procesar la foto si existe
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto');

                // Eliminar foto anterior si existe
                if ($personal->foto && Storage::disk('public')->exists($personal->foto)) {
                    Storage::disk('public')->delete($personal->foto);
                }

                // Generar nombre único para el archivo
                $nombreArchivo = 'empleado_' . $personal->id . '.' . $foto->getClientOriginalExtension();

                // Guardar nueva foto
                $rutaGuardada = $foto->storeAs('SST', $nombreArchivo, 'public');

                // 🔹 GUARDAR LA RUTA EN EL MODELO

            }

            $personal->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Empleado actualizado exitosamente',
                'data' => $personal
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el empleado: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $Personal = FichaObra::find($id);

        $Personal->estado = !$Personal->estado;
        $Personal->update();
    }
}
