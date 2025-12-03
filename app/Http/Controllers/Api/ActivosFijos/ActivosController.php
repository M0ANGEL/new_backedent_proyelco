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
use Maatwebsite\Excel\Excel;

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
                    'activo.bodega_responsable',
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

    public function bodegaResponsable(Request $request)
    {
        $request->validate([
            'activo_id' => 'required|exists:activo,id',
            'bodega_id' => 'required' // Asumiendo que existe tabla bodegas
        ]);

        try {
            $activo = Activo::findOrFail($request->activo_id);

            $activo->update([
                'bodega_responsable' => $request->bodega_id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Bodega responsable asignada correctamente',
                'data' => $activo
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al asignar bodega responsable'
            ], 500);
        }
    }

    public function bodegaResponsableDelete($id)
    {
        try {
            $activo = Activo::findOrFail($id);

            $activo->update([
                'bodega_responsable' => null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Bodega responsable eliminada correctamente',
                'data' => $activo
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar bodega responsable'
            ], 500);
        }
    }



    // public function reporteActivo(Request $request)
    // {
    //     $request->validate([
    //         'fecha_inicio' => 'required|date',
    //         'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
    //     ]);

    //     $fechaInicio = $request->fecha_inicio;
    //     $fechaFin = $request->fecha_fin;

    //     // Validar que no sea más de 4 meses
    //     $start = Carbon::parse($fechaInicio);
    //     $end = Carbon::parse($fechaFin);

    //     if ($start->diffInMonths($end) > 4) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'El rango de fechas no puede ser mayor a 4 meses'
    //         ], 400);
    //     }

    //     $totalPersonalProyelco = Activo::where('estado', 1)->count();

    //     $asistencias = DB::connection('mysql')
    //         ->table('asistencias_th')
    //         //empleado
    //         ->leftJoin('empleados_proyelco_th as ep', function ($join) {
    //             $join->on('asistencias_th.empleado_id', '=', 'ep.id')
    //                 ->where('asistencias_th.tipo_empleado', 1);
    //         })
    //         ->leftJoin('empleados_th as et', function ($join) {
    //             $join->on('asistencias_th.empleado_id', '=', 'et.id')
    //                 ->where('asistencias_th.tipo_empleado', 2);
    //         })
    //         //proyecto - AHORA SOLO CON bodegas_area
    //         ->leftJoin('bodegas_area as ba', 'asistencias_th.obra_id', '=', 'ba.id')
    //         //cargo
    //         ->leftJoin('cargos_th as c', function ($join) {
    //             $join->on('ep.cargo_id', '=', 'c.id')
    //                 ->orOn('et.cargo_id', '=', 'c.id');
    //         })
    //         //contratista
    //         ->leftJoin('ficha_th as f', 'asistencias_th.identificacion', '=', 'f.identificacion')
    //         ->leftJoin('contratistas_th as cont', 'f.contratista_id', '=', 'cont.id')
    //         ->select(
    //             // Datos básicos de asistencias_th
    //             'asistencias_th.id',
    //             'asistencias_th.fecha_ingreso',
    //             'asistencias_th.hora_ingreso',
    //             'asistencias_th.fecha_salida',
    //             'asistencias_th.hora_salida',
    //             'asistencias_th.horas_laborales',
    //             'asistencias_th.tipo_empleado',

    //             // Datos del empleado
    //             DB::raw("
    //         CASE 
    //             WHEN asistencias_th.tipo_empleado = 1 THEN ep.nombre_completo
    //             WHEN asistencias_th.tipo_empleado = 2 THEN et.nombre_completo
    //         END as nombre_completo
    //     "),
    //             DB::raw("
    //         CASE 
    //             WHEN asistencias_th.tipo_empleado = 1 THEN ep.identificacion
    //             WHEN asistencias_th.tipo_empleado = 2 THEN et.identificacion
    //         END as identificacion
    //     "),
    //             DB::raw("
    //         CASE 
    //             WHEN asistencias_th.tipo_empleado = 1 THEN ep.tipo_documento
    //             WHEN asistencias_th.tipo_empleado = 2 THEN et.tipo_documento
    //         END as tipo_documento
    //     "),
    //             DB::raw("
    //         CASE 
    //             WHEN asistencias_th.tipo_empleado = 1 THEN ep.telefono_celular
    //             WHEN asistencias_th.tipo_empleado = 2 THEN et.telefono_celular
    //         END as telefono_celular
    //     "),

    //             // Información de la obra - AHORA SOLO DESDE bodegas_area
    //             'ba.nombre as nombre_obra',

    //             // Información del contratista
    //             'cont.contratista as nombre_contratista',
    //             'cont.nit as nit_contratista',

    //             // Cargo del empleado
    //             'c.cargo',

    //             // Tipo de empleado como texto
    //             DB::raw("
    //         CASE 
    //             WHEN asistencias_th.tipo_empleado = 1 THEN 'Empleado Proyelco'
    //             WHEN asistencias_th.tipo_empleado = 2 THEN 'Empleado No Proyelco'
    //             ELSE 'Desconocido'
    //         END as tipo_empleado_texto
    //     ")
    //         )
    //         ->whereBetween('asistencias_th.fecha_ingreso', [$fechaInicio, $fechaFin])
    //         ->orderBy('asistencias_th.fecha_ingreso', 'desc')
    //         ->orderBy('asistencias_th.hora_ingreso', 'desc')
    //         ->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $asistencias,
    //         'totalPersonalProyelco' => $totalPersonalProyelco
    //     ]);
    // }


    public function exportarActivosExcel(Request $request)
    {
        try {
            // Validar los filtros recibidos
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'responsable' => 'nullable|string|max:255',
                'estado' => 'nullable|string|in:1,2',
                'condicion' => 'nullable|string|in:1,2,3',
                'tipo_activo' => 'nullable|string|in:1,2',
                'categoria' => 'nullable|string|max:255',
                'aceptacion' => 'nullable|string|in:0,1,2,3,4',
                'bodega_responsable' => 'nullable',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            ]);

            // Preparar filtros para la consulta
            $filtros = $this->prepararFiltros($validated);

            // Obtener la consulta base con relaciones
            $query = ActivoFijo::with([
                'categoria' => function($q) {
                    $q->select('id', 'nombre');
                },
                'subcategoria' => function($q) {
                    $q->select('id', 'nombre');
                },
                'bodegaActual' => function($q) {
                    $q->select('id', 'nombre');
                },
                'bodegaResponsable' => function($q) {
                    $q->select('id', 'nombre');
                },
                'usuariosAsignados' => function($q) {
                    $q->with(['usuario' => function($q2) {
                        $q2->select('id', 'nombre');
                    }]);
                }
            ]);

            // Aplicar filtros
            $query = $this->aplicarFiltros($query, $filtros);

            // Contar total de registros filtrados
            $totalRegistros = $query->count();

            // Si no hay registros, retornar error
            if ($totalRegistros === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron activos con los filtros especificados'
                ], 404);
            }

            // Generar nombre del archivo
            $fecha = now()->format('Ymd_His');
            $nombreArchivo = "activos_filtrados_{$fecha}.xlsx";

            // Exportar a Excel
            return Excel::download(new ActivosFijosExport($query), $nombreArchivo);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al exportar activos a Excel: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al generar el reporte'
            ], 500);
        }
    }

    /**
     * Preparar los filtros para la consulta
     */
    private function prepararFiltros(array $validated): array
    {
        $filtros = [];

        // Búsqueda general
        if (!empty($validated['search'])) {
            $filtros['search'] = $validated['search'];
        }

        // Búsqueda por responsable
        if (!empty($validated['responsable'])) {
            $filtros['responsable'] = $validated['responsable'];
        }

        // Filtro por estado (1: Activo, 2: Inactivo)
        if (!empty($validated['estado'])) {
            $filtros['estado'] = $validated['estado'];
        }

        // Filtro por condición (1: Bueno, 2: Regular, 3: Malo)
        if (!empty($validated['condicion'])) {
            $filtros['condicion'] = $validated['condicion'];
        }

        // Filtro por tipo de activo (1: Mayores, 2: Menores)
        if (!empty($validated['tipo_activo'])) {
            $filtros['tipo_activo'] = $validated['tipo_activo'];
        }

        // Filtro por categoría (texto)
        if (!empty($validated['categoria'])) {
            $filtros['categoria'] = $validated['categoria'];
        }

        // Filtro por estado de traslado (0: Sin trasladar, 1: Pendiente, 2: Aceptado, 3: Sin trasladar, 4: Mantenimiento)
        if (!empty($validated['aceptacion'])) {
            $filtros['aceptacion'] = $validated['aceptacion'];
        }

        // Filtro por bodega responsable (puede ser array)
        if (!empty($validated['bodega_responsable'])) {
            $filtros['bodega_responsable'] = $validated['bodega_responsable'];
        }

        // Filtro por fechas (rango de creación)
        if (!empty($validated['fecha_inicio']) && !empty($validated['fecha_fin'])) {
            $filtros['fecha_inicio'] = $validated['fecha_inicio'];
            $filtros['fecha_fin'] = $validated['fecha_fin'];
        }

        return $filtros;
    }

    /**
     * Aplicar filtros a la consulta
     */
    private function aplicarFiltros($query, array $filtros)
    {
        // Búsqueda general en múltiples campos
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('numero_activo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('marca', 'like', "%{$search}%")
                  ->orWhere('serial', 'like', "%{$search}%")
                  ->orWhere('observacion', 'like', "%{$search}%");
            });
        }

        // Búsqueda por responsable (a través de la relación usuariosAsignados)
        if (!empty($filtros['responsable'])) {
            $responsable = $filtros['responsable'];
            $query->whereHas('usuariosAsignados.usuario', function($q) use ($responsable) {
                $q->where('nombre', 'like', "%{$responsable}%");
            });
        }

        // Filtro por estado
        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        // Filtro por condición
        if (!empty($filtros['condicion'])) {
            $query->where('condicion', $filtros['condicion']);
        }

        // Filtro por tipo de activo
        if (!empty($filtros['tipo_activo'])) {
            $query->where('tipo_activo', $filtros['tipo_activo']);
        }

        // Filtro por categoría
        if (!empty($filtros['categoria'])) {
            $query->whereHas('categoria', function($q) use ($filtros) {
                $q->where('nombre', 'like', "%{$filtros['categoria']}%");
            });
        }

        // Filtro por estado de traslado
        if (!empty($filtros['aceptacion'])) {
            $query->where('aceptacion', $filtros['aceptacion']);
        }

        // Filtro por bodega responsable (puede ser array de IDs)
        if (!empty($filtros['bodega_responsable'])) {
            if (is_array($filtros['bodega_responsable'])) {
                $query->whereIn('bodega_responsable', $filtros['bodega_responsable']);
            } else {
                $query->where('bodega_responsable', $filtros['bodega_responsable']);
            }
        }

        // Filtro por rango de fechas (fecha de creación)
        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [
                $filtros['fecha_inicio'] . ' 00:00:00',
                $filtros['fecha_fin'] . ' 23:59:59'
            ]);
        }

        // Ordenar por fecha de creación descendente
        $query->orderBy('created_at', 'desc');

        return $query;
    }
}
