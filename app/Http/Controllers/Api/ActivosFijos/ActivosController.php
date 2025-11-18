<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ActivosController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 50);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            $responsable = $request->get('responsable', ''); // ✅ NUEVO PARÁMETRO

            // Consulta principal
            $query = DB::connection('mysql')
                ->table('activo')
                ->select([
                    'activo.id',
                    'activo.numero_activo',
                    'activo.descripcion',
                    'activo.valor',
                    'activo.condicion',
                    'activo.estado',
                    'activo.tipo_activo',
                    'activo.aceptacion',
                    'activo.usuarios_asignados',
                    'activo.created_at',
                    'activo.updated_at',
                    'activo.marca',
                    'activo.serial',
                    'categoria_activos.nombre as categoria',
                    'subcategoria_activos.nombre as subcategoria',
                    'bodegas_area.nombre as bodega_actual'
                ])
                ->leftJoin('users', 'activo.user_id', '=', 'users.id')
                ->leftJoin('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
                ->leftJoin('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
                ->leftJoin('bodegas_area', 'activo.ubicacion_actual_id', '=', 'bodegas_area.id')
                ->where('activo.estado', 1);

            // Búsqueda global
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('activo.numero_activo', 'LIKE', "%{$search}%")
                        ->orWhere('activo.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('categoria_activos.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('subcategoria_activos.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('activo.marca', 'LIKE', "%{$search}%")
                        ->orWhere('activo.serial', 'LIKE', "%{$search}%");
                });
            }

            // ✅ BÚSQUEDA POR RESPONSABLE
            if (!empty($responsable)) {
                // Obtenemos los IDs de usuarios que coincidan con el nombre
                $userIds = DB::table('users')
                    ->where('nombre', 'LIKE', "%{$responsable}%")
                    ->pluck('id')
                    ->toArray();

                if (!empty($userIds)) {
                    $query->where(function ($q) use ($userIds) {
                        foreach ($userIds as $userId) {
                            $q->orWhereJsonContains('activo.usuarios_asignados', (string)$userId);
                        }
                    });
                }
            }

            // Cache y paginación
            $cacheKey = 'activos_page_' . $page . '_' . $perPage . '_' . md5($search . $responsable);
            $activos = Cache::remember($cacheKey, 300, function () use ($query, $perPage, $page) {
                return $query->paginate($perPage, ['*'], 'page', $page);
            });

            // Procesamiento de usuarios asignados
            $allUserIds = collect($activos->items())
                ->pluck('usuarios_asignados')
                ->filter()
                ->map(function ($ids) {
                    if (is_string($ids)) {
                        return json_decode($ids, true) ?? [];
                    }
                    return is_array($ids) ? $ids : [];
                })
                ->flatten()
                ->unique()
                ->values();

            $usuariosMap = [];
            if ($allUserIds->isNotEmpty()) {
                $usuariosMap = DB::table('users')
                    ->whereIn('id', $allUserIds)
                    ->pluck('nombre', 'id')
                    ->toArray();
            }

            // Transformación de datos
            $activos->getCollection()->transform(function ($activo) use ($usuariosMap) {
                $encargadoIds = [];

                if (is_string($activo->usuarios_asignados)) {
                    $encargadoIds = json_decode($activo->usuarios_asignados, true) ?? [];
                } elseif (is_array($activo->usuarios_asignados)) {
                    $encargadoIds = $activo->usuarios_asignados;
                }

                $activo->usuariosAsignados = collect($encargadoIds)
                    ->map(function ($id) use ($usuariosMap) {
                        return $usuariosMap[$id] ?? null;
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                return $activo;
            });

            return response()->json([
                'status' => 'success',
                'data' => $activos->items(),
                'pagination' => [
                    'current_page' => $activos->currentPage(),
                    'per_page' => $activos->perPage(),
                    'total' => $activos->total(),
                    'last_page' => $activos->lastPage(),
                    'from' => $activos->firstItem(),
                    'to' => $activos->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading activos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cargar los datos: ' . $e->getMessage()
            ], 500);
        }
    }


    public function indexActivosBaja()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area', 'activo.ubicacion_actual_id', '=', 'bodegas_area.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodegas_area.nombre as bodega_actual'
            )
            ->where('activo.estado', 0)
            ->get();

        foreach ($clientes as $proyecto) {
            $encargadoIds = json_decode($proyecto->usuarios_asignados, true) ?? [];
            $proyecto->usuariosAsignados = DB::table('users')
                ->whereIn('id', $encargadoIds)
                ->pluck('nombre');
        }

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'categoria_id' => ['required'],
                'subcategoria_id' => ['required'],
                'numero_activo' => ['required', 'string'],
                'valor' => ['required', 'string'],
                'condicion' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            //validacion si el prefijo existe
            $existePrefijo = Activo::where('numero_activo', $request->numero_activo)->exists();

            if ($existePrefijo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Numero de activo en uso',
                ], 500);
            }

            $user = Auth::user();

            $cliente = new Activo();
            $cliente->tipo_activo = $request->tipo_activo;
            $cliente->origen_activo = $request->origen_activo;
            $cliente->proveedor_activo = $request->proveedor;
            $cliente->numero_activo = $request->numero_activo;
            $cliente->categoria_id = $request->categoria_id;
            $cliente->subcategoria_id = $request->subcategoria_id;
            $cliente->user_id = $user->id;
            $cliente->descripcion = $request->descripcion ? $request->descripcion : "..";
            $cliente->valor = $request->valor;
            $cliente->fecha_aquiler = $request->origen_activo == 1  ? null : Carbon::parse($request->fecha_aquiler)->format('Y-m-d');
            $cliente->fecha_compra = $request->origen_activo == 1  ? Carbon::parse($request->fecha_compra)->format('Y-m-d')  : null;
            $cliente->condicion = $request->condicion;
            $cliente->marca = $request->marca ? $request->marca : null;
            $cliente->serial = $request->serial ? $request->serial : null;
            // $cliente->ubicacion_actual_id = $request->ubicacion_actual;
            $cliente->ubicacion_actual_id = 1;
            $cliente->save(); // se guarda para obtener el ID



            // Si hay archivo, lo guardamos usando el ID como nombre
            if ($request->hasFile('file')) {
                // Validar que solo sean jpg o png
                $request->validate([
                    'file' => 'mimes:jpg,jpeg,png|max:2048' // máximo 2 MB 
                ]);

                $extension = $request->file('file')->getClientOriginalExtension();

                // Forzar que solo guarde como .jpg o .png
                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png'])) {
                    $request->file('file')->storeAs(
                        'public/activos',
                        $cliente->id . '.' . $extension
                    );
                }
            }


            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Activo::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'categoria_id' => ['required'],
                'subcategoria_id' => ['required'],
                'numero_activo' => ['required', 'string'],
                'valor' => ['required', 'string'],
                'condicion' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Validar que el numero de activo sea único
            $proyectoUnico = Activo::where('numero_activo', $request->numero_activo)
                ->where('id', '!=', $id)
                ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este numero de activo ya está registrado',
                ], 404);
            }

            // Obtener el registro existente
            $cliente = Activo::findOrFail($id);

            // Actualizar campos
            $cliente->numero_activo = $request->numero_activo;
            $cliente->categoria_id = $request->categoria_id;
            $cliente->subcategoria_id = $request->subcategoria_id;
            $cliente->descripcion = $request->descripcion ?: "..";
            $cliente->valor = $request->valor;
            $cliente->fecha_compra = $request->origen_activo == 1
                ? Carbon::parse($request->fecha_compra)->format('Y-m-d')
                : null;
            $cliente->fecha_aquiler = $request->origen_activo == 1
                ? null
                : Carbon::parse($request->fecha_aquiler)->format('Y-m-d');
            $cliente->condicion = $request->condicion;
            $cliente->marca = $request->marca ?: null;
            $cliente->serial = $request->serial ?: null;
            $cliente->save();

            // Manejo de imagen
            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => 'mimes:jpg,jpeg,png|max:2048'
                ]);

                // Borrar imagen anterior
                $oldFiles = glob(storage_path("app/public/activos/{$cliente->id}.*"));
                foreach ($oldFiles as $oldFile) {
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                // Guardar nueva imagen
                $extension = strtolower($request->file('file')->getClientOriginalExtension());
                $request->file('file')->storeAs(
                    'public/activos',
                    $cliente->id . '.' . $extension
                );
            }

            return response()->json([
                'status' => 'success',
                'data' => $cliente
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
        $categoria = Activo::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }

    public function usuariosAsignacion()
    {
        $datos = User::select('id', 'nombre')->where('estado', 1)->get();

        return response()->json([
            'status' => 'success',
            'data' => $datos
        ]);
    }

    // ActivoController.php
    public function verActivoQR($id)
    {
        $activo = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.categoria_id', '=', 'subcategoria_activos.id')
            ->leftJoin('bodegas_area', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'bodegas_area.id')
                    ->where('activo.tipo_ubicacion', 1); // solo si es bodega
            })
            ->leftJoin('proyecto', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'proyecto.id')
                    ->where('activo.tipo_ubicacion', 2); // solo si es proyecto
            })
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                DB::raw("
                CASE 
                    WHEN activo.tipo_ubicacion = 1 THEN bodegas_area.nombre
                    WHEN activo.tipo_ubicacion = 2 THEN proyecto.descripcion_proyecto
                END as ubicacion
            "),
            )
            ->where('activo.id', $id)
            ->first($id);
        return view('activos.qr', compact('activo'));
    }
}
