<?php

namespace App\Http\Controllers\Api\TalentoHumano\FichaObra;

use App\Http\Controllers\Controller;
use App\Models\FichaObra;
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
        $Personales = DB::connection('mysql')
            ->table('activo')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->leftJoin('bodegas_area', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'bodegas_area.id')
                    ->where('activo.tipo_ubicacion', 1); // solo si es bodega
            })
            ->leftJoin('proyecto', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'proyecto.id')
                    ->where('activo.tipo_ubicacion', 2); // solo si es proyecto
            })
            ->leftJoin('solicitudes_activos', 'activo.id', '=', 'solicitudes_activos.activo_id') // 👈 cruce con solicitudes
            ->select(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                DB::raw("
                CASE 
                    WHEN activo.tipo_ubicacion = 1 THEN bodegas_area.nombre
                    WHEN activo.tipo_ubicacion = 2 THEN proyecto.descripcion_proyecto
                END as bodega_actual
            "),
                DB::raw("
                MAX(CASE WHEN solicitudes_activos.estado = 0 THEN 1 ELSE 0 END) as solicitud
            ")
            )
            ->where('activo.aceptacion', '!=', 1)
            ->groupBy(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'categoria_activos.nombre',
                'subcategoria_activos.nombre',
                'bodega_actual',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $Personales
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

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
                'correo' => ['required', 'string', 'email'],
                'cargo_id' => ['required', 'string'],
                'contratista_id' => ['required'],
                'salario' => ['required', 'numeric'],
                'eps' => ['required', 'string'],
                'pension' => ['required', 'string'],
                'tipo_sangre' => ['required', 'string'],
                'numero_hijos' => ['required', 'integer', 'min:0', 'max:20'],
                'fotos' => ['sometimes', 'array'], // Para múltiples fotos
                'fotos.*' => ['image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Crear el nuevo empleado
            $personal = new FichaObra();
            $personal->identificacion = $request->identificacion;
            $personal->contratista_id = $request->contratista_id;
            $personal->eps = $request->eps;
            $personal->afp = $request->pension;
            $personal->rh = $request->tipo_sangre;
            $personal->hijos = $request->numero_hijos;
            // $personal->user_id = Auth::id();

            $personal->save();

            // Procesar múltiples fotos si existen
            if ($request->hasFile('fotos')) {
                $fotosGuardadas = [];

                foreach ($request->file('fotos') as $index => $foto) {
                    $extension = $foto->getClientOriginalExtension();

                    // Nombre del archivo: id_numero.extension
                    $nombreArchivo = $personal->id . '_' . ($index + 1) . '.' . $extension;

                    // Ruta donde se guardará
                    $ruta = 'SST/' . $nombreArchivo;

                    // Guardar la foto
                    Storage::disk('public')->put($ruta, file_get_contents($foto));

                    $fotosGuardadas[] = $ruta;
                }

                // Guardar las rutas de las fotos (puedes guardarlas como JSON o en una tabla separada)
                $personal->fotos = json_encode($fotosGuardadas);
                $personal->save();
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

    public function show($id)
    {
        return response()->json(FichaObra::find($id), 200);
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


            // Obtener la categoría actual
            $Personal = FichaObra::findOrFail($id);
            $Personal->identificacion = $request->identificacion;
            $Personal->tipo_documento = $request->tipo_documento;
            $Personal->nombre_completo = $request->nombre_completo;
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
        $Personal = FichaObra::find($id);

        $Personal->estado = !$Personal->estado;
        $Personal->update();
    }
}
