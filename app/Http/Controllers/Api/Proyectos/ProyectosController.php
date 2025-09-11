<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\CambioProcesoProyectos;
use App\Models\Clientes;
use App\Models\Festivos;
use App\Models\NombreTorres;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProyectosController extends Controller
{

    public function index()
    {
        // Traer proyectos con joins básicos
        $proyectos = DB::table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->select(
                'proyecto.*',
                'tipos_de_proyectos.nombre_tipo',
                'clientes.emp_nombre'
            )
            ->get();

        // 1️⃣ Recolectar todos los IDs de encargados e ingenieros
        $encargadoIdsGlobal = [];
        $ingenieroIdsGlobal = [];

        foreach ($proyectos as $proyecto) {
            $encargadoIdsGlobal = array_merge($encargadoIdsGlobal, json_decode($proyecto->encargado_id, true) ?? []);
            $ingenieroIdsGlobal = array_merge($ingenieroIdsGlobal, json_decode($proyecto->ingeniero_id, true) ?? []);
        }

        // 2️⃣ Obtener todos los usuarios de una sola consulta
        $usuarios = DB::table('users')
            ->whereIn('id', array_unique(array_merge($encargadoIdsGlobal, $ingenieroIdsGlobal)))
            ->pluck('nombre', 'id'); // => [id => nombre]

        // 3️⃣ Obtener todos los detalles de los proyectos en una sola consulta
        $detalles = DB::table('proyecto_detalle')
            ->whereIn('proyecto_id', $proyectos->pluck('id'))
            ->get()
            ->groupBy('proyecto_id');

        // 4️⃣ Asignar nombres y cálculos a cada proyecto
        foreach ($proyectos as $proyecto) {
            // Encargados
            $encargadoIds = json_decode($proyecto->encargado_id, true) ?? [];
            $proyecto->nombresEncargados = collect($encargadoIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Ingenieros
            $ingenieroIds = json_decode($proyecto->ingeniero_id, true) ?? [];
            $proyecto->nombresIngenieros = collect($ingenieroIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Detalles del proyecto
            $detalleProyecto = $detalles[$proyecto->id] ?? collect();

            // Cálculo de atraso
            $ejecutando = $detalleProyecto->where('estado', 1)->count();
            $terminado = $detalleProyecto->where('estado', 2)->count();
            $total = $ejecutando + $terminado;

            $proyecto->porcentaje = $total > 0 ? round(($ejecutando / $total) * 100, 2) : 0;

            // Cálculo de avance
            $totalApartamentos = $detalleProyecto->count();
            $apartamentosRealizados = $terminado;
            $proyecto->avance = $totalApartamentos > 0 ? round(($apartamentosRealizados / $totalApartamentos) * 100, 2) : 0;
        }

        // 5️⃣ Ordenar por atraso (porcentaje) de mayor a menor
        $proyectos = $proyectos->sortByDesc('porcentaje')->values();

        return response()->json([
            'status' => 'success',
            'data' => $proyectos
        ]);
    }

    public function usuariosProyectos()
    {
        //consulta a la bd los proyectos
        $proyectosDetalle = DB::connection('mysql')
            ->table('users')
            ->where('estado', 1)
            ->where('rol', 'Encargado Obras')
            ->select(
                'nombre',
                'id',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proyectosDetalle,
        ]);
    }

    public function ingenierosProyectos()
    {
        $proyectosDetalle = DB::connection('mysql')
            ->table('users')
            ->where('estado', 1)
            ->where('rol', 'Ingeniero Obra')
            ->select(
                'nombre',
                'id',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proyectosDetalle,
        ]);
    }

    public function usuariosCorreos()
    {
        $proyectosDetalle = DB::connection('mysql')
            ->table('users')
            ->where('estado', 1)
            // ->where('cargo', 'Ingeniero')
            ->select(
                'nombre',
                'id',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $proyectosDetalle,
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'estado' => ['required'],
                'bloques' => ['array'],
                'codigo_proyecto' => ['required', 'string'],
                'fecha_inicio' => ['required', 'string'],
                'tipoProyecto_id' => ['required'],
                'encargado_id' => ['required', 'array'],
                'ingeniero_id' => ['required', 'array'],
                'torres' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'minimoApt' => ['required', 'string'],
                'tipo_obra' => ['required'],
                'cant_pisos' /* => ['string','null'] */,
                'apt' /* => ['string','null'] */,
                'activador_pordia_apt' => ['required', 'string'],
                'procesos' => ['required', 'array'], //tiene numero de numCambioProceso vacio de error
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // 🚨 Validación personalizada de numCambioProceso
            foreach ($request->procesos as $index => $proceso) {
                if ($index > 0 && (empty($proceso['numCambioProceso']) || !is_numeric($proceso['numCambioProceso']))) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Error: El proceso '{$proceso['label']}' debe tener un número de cambio de piso válido.",
                    ], 422);
                }
            }

            $proyectoUnico = Proyectos::where('codigo_proyecto', $request->codigo_proyecto)
                ->select('descripcion_proyecto')
                ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este codigo  no esta disponible. esta en uso por el proyecto:   ' .  $proyectoUnico->descripcion_proyecto,
                ], 404);
            }

            $cliente = Clientes::where('nit', $request->nit)->first();
            if (!$cliente) {
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }


            // se valida que los cambios de piso no sea mayor a la cantidad de piso de pende el tipo del proyecto
            if ($request->tipo_obra == 1) {

                foreach ($request->procesos as $proceso) {
                    if (!isset($proceso['numCambioProceso'])) {
                        continue;
                    }

                    $numCambio = (int) $proceso['numCambioProceso'];

                    foreach ($request->bloques as $bloque) {
                        $pisosBloque = (int) $bloque['pisos'];

                        if ($numCambio > $pisosBloque) {
                            return response()->json([
                                'status' => 'error',
                                'message' => "Error: El proceso '{$proceso['label']}' tiene una cantidad de cambios de piso ({$numCambio}) mayor a la cantidad de pisos de la torre: '{$bloque['nombre']}' ({$pisosBloque})",
                            ], 422);
                        }
                    }
                }
            } else {
                foreach ($request->procesos as $proceso) {
                    if (isset($proceso['numCambioProceso']) && $proceso['numCambioProceso'] > $request->cant_pisos) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Error: El proceso '{$proceso['label']}' tiene una cantidad de cambios de piso ({$proceso['numCambioProceso']}) mayor a la cantidad total de pisos ({$request->cant_pisos})",
                        ], 404);
                    }
                }
            }



            // Datos base
            $cant_pisos = (int)$request->cant_pisos;
            $total_apt = (int)$request->apt;

            if ((int)$request->tipo_obra === 1 && !empty($request->bloques)) {
                $cant_pisos = 0;
                $total_apt = 0;
                foreach ($request->bloques as $bloque) {
                    if (!empty($bloque['apartamentosPorPiso'])) {
                        $cant_pisos = max($cant_pisos, count($bloque['apartamentosPorPiso']));
                        foreach ($bloque['apartamentosPorPiso'] as $aptPorPiso) {
                            $total_apt += (int)$aptPorPiso;
                        }
                    }
                }
            }

            $proyecto = new Proyectos();
            $proyecto->tipoProyecto_id = $request->tipoProyecto_id;
            $proyecto->cliente_id = $cliente->id;
            $proyecto->usuario_crea_id = Auth::id();
            $proyecto->descripcion_proyecto = $request->descripcion_proyecto;
            $proyecto->fecha_inicio = Carbon::parse($request->fecha_inicio);
            $proyecto->codigo_proyecto = $request->codigo_proyecto;
            $proyecto->torres = (int)$request->torres ?? count($request->bloques);
            $proyecto->cant_pisos = $cant_pisos;
            $proyecto->apt = $total_apt;
            $proyecto->minimoApt = $request->minimoApt;
            $proyecto->pisosCambiarProceso = 2; //se va a borrar despues
            $proyecto->activador_pordia_apt = $request->activador_pordia_apt;

            // Registrar usuarios de notificación solo si vienen en la solicitud
            $proyecto->ingeniero_id = $request->filled('ingeniero_id') ? json_encode($request->ingeniero_id) : null;
            $proyecto->encargado_id = $request->filled('encargado_id') ? json_encode($request->encargado_id) : null;
            $proyecto->usuarios_notificacion = $request->filled('usuarios_notificacion') ? json_encode($request->usuarios_notificacion) : null;
            $proyecto->save();


            // === SIMÉTRICA ===
            if ((int)$request->tipo_obra === 0) {
                $torres = (int)$request->torres;
                $pisos = (int)$request->cant_pisos;
                $aptXPiso = (int)$request->apt;

                for ($torre = 1; $torre <= $torres; $torre++) {
                    for ($piso = 1; $piso <= $pisos; $piso++) {
                        for ($apt = 1; $apt <= $aptXPiso; $apt++) {
                            $consecutivo = $piso * 100 + $apt;

                            foreach ($request->procesos as $index => $proceso) {
                                $detalle = new ProyectosDetalle();
                                $detalle->proyecto_id = $proyecto->id;
                                $detalle->torre = $torre;
                                $detalle->piso = $piso;
                                $detalle->apartamento = $apt;
                                $detalle->consecutivo = $consecutivo;
                                $detalle->orden_proceso = $index + 1;
                                $detalle->procesos_proyectos_id = $proceso['value'];
                                $detalle->validacion = $proceso['requiereValidacion'] === 'si' ? 1 : 0;
                                $detalle->text_validacion = $proceso['requiereValidacion'] === 'si' ? $proceso['valor'] : null;
                                $detalle->save();
                            }
                        }
                    }
                }
                if (isset($request->procesos) && is_array($request->procesos)) {
                    foreach ($request->procesos as $index => $proceso) {
                        if (isset($proceso['numCambioProceso'])) {
                            $cambioPisosNuevo = new CambioProcesoProyectos();
                            $cambioPisosNuevo->proyecto_id = $proyecto->id;
                            $cambioPisosNuevo->proceso = $proceso['value']; // Esto mantiene el orden
                            $cambioPisosNuevo->numero = $proceso['numCambioProceso'];
                            $cambioPisosNuevo->save();
                        }
                    }
                }

                // nombre de torres
                if (isset($request->torreNombres) && is_array($request->torreNombres)) {
                    foreach ($request->torreNombres as $index => $nombreTorre) {
                        if (!empty($nombreTorre)) {
                            $cambioPisosNuevo = new NombreTorres();
                            $cambioPisosNuevo->proyecto_id = $proyecto->id;
                            $cambioPisosNuevo->nombre_torre = $nombreTorre;
                            $cambioPisosNuevo->torre = $index + 1; // Mantener el orden
                            $cambioPisosNuevo->save();
                        }
                    }
                }



                // === PERSONALIZADA ===
            } elseif ((int)$request->tipo_obra === 1 && !empty($request->bloques)) {
                foreach ($request->bloques as $torreIndex => $bloque) {
                    $piso = 1;
                    foreach ($bloque['apartamentosPorPiso'] as $aptCount) {
                        for ($apt = 1; $apt <= (int)$aptCount; $apt++) {
                            // Generar el número consecutivo 
                            $consecutivo = $piso * 100 + $apt;

                            foreach ($request->procesos as $index => $proceso) {
                                $detalle = new ProyectosDetalle();
                                $detalle->proyecto_id = $proyecto->id;
                                $detalle->torre = $torreIndex + 1;
                                $detalle->piso = $piso;
                                $detalle->apartamento = $apt;
                                $detalle->consecutivo = $consecutivo;
                                $detalle->orden_proceso = $index + 1;
                                $detalle->procesos_proyectos_id = $proceso['value'];
                                $detalle->validacion = $proceso['requiereValidacion'] === 'si' ? 1 : 0;
                                $detalle->text_validacion = $proceso['requiereValidacion'] === 'si' ? $proceso['valor'] : null;
                                $detalle->save();
                            }
                        }
                        $piso++;
                    }
                }

                if (isset($request->procesos) && is_array($request->procesos)) {
                    foreach ($request->procesos as $index => $proceso) {
                        if (isset($proceso['numCambioProceso'])) {
                            $cambioPisosNuevo = new CambioProcesoProyectos();
                            $cambioPisosNuevo->proyecto_id = $proyecto->id;
                            $cambioPisosNuevo->proceso = $proceso['value']; // Esto mantiene el orden
                            $cambioPisosNuevo->numero = $proceso['numCambioProceso'];
                            $cambioPisosNuevo->save();
                        }
                    }
                }

                // nombre de torres personalizado
                if (isset($request->bloques) && is_array($request->bloques)) {
                    foreach ($request->bloques as $index => $bloque) {
                        if (!empty($bloque['nombre'])) {
                            $nombreTorre = new NombreTorres();
                            $nombreTorre->proyecto_id = $proyecto->id;
                            $nombreTorre->nombre_torre = $bloque['nombre'];
                            $nombreTorre->torre = $index + 1; // posición/orden
                            $nombreTorre->save();
                        }
                    }
                }
            }

            DB::commit(); // Confirmamos los cambios

            return response()->json([
                'status' => 'success',
                'data' => $proyecto
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack(); //  Revertimos si hubo error

            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $proyecto = DB::connection('mysql')
            ->table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->where('proyecto.id', $id)
            ->select(
                'proyecto.*',
                'clientes.emp_nombre',
                'clientes.nit'
            )
            ->first();

        // Ahora traemos todos los procesos relacionados con el proyecto
        $procesos = DB::connection('mysql')
            ->table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'cambio_procesos_x_proyecto.proceso', '=', 'procesos_proyectos.id')
            ->where('proyecto_id', $id)
            ->select(
                'cambio_procesos_x_proyecto.proyecto_id',
                'cambio_procesos_x_proyecto.numero',
                'cambio_procesos_x_proyecto.proceso',
                'procesos_proyectos.nombre_proceso'
            )
            ->get();

        // Unimos todo en la respuesta
        return response()->json([
            'proyecto' => $proyecto,
            'procesos' => $procesos
        ], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'encargado_id' => ['required', 'array'],
                'ingeniero_id' => ['required', 'array'],
                'emp_nombre' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'descripcion_proyecto' => ['required', 'string'],
                'fecha_inicio' => ['required', 'string'],
                'codigo_proyecto' => ['required', 'string'],
                'activador_pordia_apt' => ['required', 'string'],
                'minimoApt' => ['required', 'string'],
                'procesos' => ['required', 'array'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // validar que el codigo del proyecto no este usado por otro
            $proyectoUnico = Proyectos::where('codigo_proyecto', $request->codigo_proyecto)
                ->select('descripcion_proyecto')
                ->where('id', '!=', $id)
                ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este codigo  no esta disponible. esta en uso por el proyecto:   ' .  $proyectoUnico->descripcion_proyecto,
                ], 404);
            }

            $cliente = Clientes::where('nit', $request->nit)->first();
            if (!$cliente) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Cliente no encontrado',
                ], 404);
            }

            // actualizar el proyecto actual
            $updateProyecto = Proyectos::findOrFail($id);
            $updateProyecto->cliente_id = $cliente->id;
            $updateProyecto->descripcion_proyecto = $request->descripcion_proyecto;
            $updateProyecto->fecha_inicio = Carbon::parse($request->fecha_inicio);
            $updateProyecto->codigo_proyecto = $request->codigo_proyecto;
            $updateProyecto->activador_pordia_apt = $request->activador_pordia_apt;
            $updateProyecto->minimoApt = $request->minimoApt;

            $updateProyecto->usuarios_notificacion = $request->filled('usuarios_notificacion') ? json_encode($request->usuarios_notificacion) : null;
            $updateProyecto->encargado_id = $request->filled('encargado_id') ? json_encode($request->encargado_id) : null;
            $updateProyecto->ingeniero_id = $request->filled('ingeniero_id') ? json_encode($request->ingeniero_id) : null;
            $updateProyecto->save();

            //actualizar procesos
            // $datos es el array que recibes (el que tiene proceso y numero)
            foreach ($request->procesos as $dato) {
                CambioProcesoProyectos::where('proyecto_id', $updateProyecto->id)
                    ->where('proceso', $dato['proceso'])
                    ->update(['numero' => $dato['numero']]);
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
        $categoria = Proyectos::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }

    public function infoCard()
    {
        $usuario = Auth::user();
        // $activos = Activo::where(function ($query) {
        //     $userId = Auth::id();
        //     $query->whereRaw("JSON_CONTAINS(activo.usuarios_asignados, '\"$userId\"')");
        // })
        //     ->where('estado', 1)
        //     ->where('aceptacion', 1)
        //     ->count();

        switch ($usuario->rol) {
            case 'Administrador':
                $proyectos = Proyectos::all();

                // Contar cuántos proyectos hay por estado
                $proyectosActivos = $proyectos->where('estado', '1')->count();
                $proyectosInactivos = $proyectos->where('estado', '0')->count();
                $proyectosTerminados = $proyectos->where('estado', '2')->count();

                $clientes = Clientes::all();

                // Contar cuántos clientes hay por estado
                $clientesActivos = $clientes->where('estado', '1')->count();
                $clientesInactivos = $clientes->where('estado', '0')->count();

                return response()->json([
                    'status' => 'success',
                    'data'  => [
                        // 'activos_pendinetes' => $activos,
                        'proyectosActivos' => $proyectosActivos,
                        'proyectosInactivos' => $proyectosInactivos,
                        'proyectosTerminados' => $proyectosTerminados,
                        'clientesActivos' => $clientesActivos,
                        'clientesInactivos' => $clientesInactivos,
                    ],

                ], 200);
                break;
            case 'Directora Proyectos':
                $proyectos = Proyectos::all();

                // Contar cuántos proyectos hay por estado
                $proyectosActivos = $proyectos->where('estado', '1')->count();
                $proyectosInactivos = $proyectos->where('estado', '0')->count();
                $proyectosTerminados = $proyectos->where('estado', '2')->count();

                $clientes = Clientes::all();

                // Contar cuántos clientes hay por estado
                $clientesActivos = $clientes->where('estado', '1')->count();
                $clientesInactivos = $clientes->where('estado', '0')->count();

                return response()->json([
                    'status' => 'success',
                    'data'  => [
                        // 'activos_pendinetes' => $activos,
                        'proyectosActivos' => $proyectosActivos,
                        'proyectosInactivos' => $proyectosInactivos,
                        'proyectosTerminados' => $proyectosTerminados,
                        'clientesActivos' => $clientesActivos,
                        'clientesInactivos' => $clientesInactivos,
                    ],

                ], 200);
                break;

            case 'Ingeniero Obra':

                $proyectos = DB::connection('mysql')
                    ->table('proyecto')
                    ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
                    ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
                    ->join('users', 'proyecto.usuario_crea_id', '=', 'users.id')
                    ->select(
                        'proyecto.*',
                        'tipos_de_proyectos.nombre_tipo',
                        'users.nombre as nombre',
                        'clientes.emp_nombre',
                    )
                    ->where(function ($query) {
                        $userId = Auth::id();
                        $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')")
                            ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
                    })
                    ->where('proyecto.estado', 1)
                    ->get();

                // Contar cuántos proyectos hay por estado
                $proyectosActivos = $proyectos->where('estado', '1')->count();




                return response()->json([
                    'status' => 'success',
                    'data'  => [
                        // 'activos_pendinetes' => $activos,
                        'proyectosActivos' => $proyectosActivos,
                    ],

                ], 200);
                break;


            case 'Encargado Obras':

                $proyectos = DB::connection('mysql')
                    ->table('proyecto')
                    ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
                    ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
                    ->join('users', 'proyecto.usuario_crea_id', '=', 'users.id')
                    ->select(
                        'proyecto.*',
                        'tipos_de_proyectos.nombre_tipo',
                        'users.nombre as nombre',
                        'clientes.emp_nombre',
                    )
                    ->where(function ($query) {
                        $userId = Auth::id();
                        $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')")
                            ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
                    })
                    ->where('proyecto.estado', 1)
                    ->get();

                // Contar cuántos proyectos hay por estado
                $proyectosActivos = $proyectos->where('estado', '1')->count();




                return response()->json([
                    'status' => 'success',
                    'data'  => [
                        // 'activos_pendinetes' => $activos,
                        'proyectosActivos' => $proyectosActivos,
                    ],

                ], 200);

            default:
                return response()->json([
                    'status' => 'success',
                    'mensagge' => 'ROl no encontrados',

                ], 200);
                break;
        }
    }

    public function PorcentajeDetalles(Request $request)
    {
        // Consultar todos los datos del proyecto, excluyendo el proceso 1
        $detalles = DB::connection('mysql')
            ->table('proyecto_detalle')
            ->where('proyecto_id', $request->id)
            ->where('orden_proceso', '!=', 1) // Excluir proceso 1
            ->get();

        if ($detalles->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron registros para este proyecto.',
            ], 404);
        }

        // Porcentaje a nivel de Proyecto
        $totalEjecutando = $detalles->where('estado', 1)->count();
        $totalTerminado = $detalles->where('estado', 2)->count();
        $total = $totalEjecutando + $totalTerminado;

        $porcentajeXProyecto = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;

        // Porcentaje por Torre
        $torres = $detalles->groupBy('torre');
        $porcentajeXTorre = [];

        foreach ($torres as $torre => $items) {
            $ejecutando = $items->where('estado', 1)->count();
            $terminado = $items->where('estado', 2)->count();
            $totalTorre = $ejecutando + $terminado;

            $porcentaje = $totalTorre > 0 ? ($ejecutando / $totalTorre) * 100 : 0;

            $porcentajeXTorre[] = [
                'torre' => $torre,
                'ejecutando' => $ejecutando,
                'terminado' => $terminado,
                'porcentaje' => round($porcentaje, 2),
            ];
        }

        // Porcentaje por Proceso agrupado por Torre
        $porcentajeXProceso = [];

        foreach ($torres as $torre => $items) {
            $procesos = $items->groupBy('orden_proceso');

            foreach ($procesos as $proceso => $apartamentos) {
                // Aquí ya no necesitas excluir porque ya lo filtraste desde la consulta
                $ejecutando = $apartamentos->where('estado', 1)->count();
                $terminado = $apartamentos->where('estado', 2)->count();
                $totalProceso = $ejecutando + $terminado;

                $porcentaje = $totalProceso > 0 ? ($ejecutando / $totalProceso) * 100 : 0;

                $porcentajeXProceso[] = [
                    'torre' => $torre,
                    'proceso' => $proceso,
                    'ejecutando' => $ejecutando,
                    'terminado' => $terminado,
                    'porcentaje' => round($porcentaje, 2),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data'  => [
                'porcentajeXProyecto' => round($porcentajeXProyecto, 2),
                'porcentajeXTorre' => $porcentajeXTorre,
                'porcentajeXProceso' => $porcentajeXProceso,
            ],
        ], 200);
    }

    /* si hay estos apartamenots 201 202 enotnces siginifica que hay 2, estos dos los debes poner en apt_piso */
    public function nomenclaturas($id)
    {
        $data = ProyectosDetalle::where('proyecto_id', $id)
            ->where('orden_proceso', 1)
            ->select(
                'id',
                'proyecto_id',
                'torre',
                'piso',
                'apartamento',
                'consecutivo'
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //actualizar la nomenclaturas

    public function ActualizarNomenclaturas(Request $request)
    {
        $desde = (int) $request->input('apt_inicio');
        $hasta = (int) $request->input('apt_fin');
        $nuevoInicio = (int) $request->input('nuevo_inicio');
        $proyecto = (int) $request->input('id');
        $torre = $request->input('torre');
        $piso = $request->input('piso');
        $usuario = Auth::id();


        $consecutivos = DB::table('proyecto_detalle')
            ->select('consecutivo')
            ->whereBetween('consecutivo', [$desde, $hasta])
            ->where('proyecto_id', $proyecto)
            ->where('torre', $torre)
            ->where('piso', $piso)
            ->distinct()
            ->orderBy('consecutivo')
            ->pluck('consecutivo');



        if ($consecutivos->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rango no válido',
            ], 500);
        }


        Log::channel('consecutivos')->info("Usuario-modifica: $usuario Torre $torre Piso $piso Actualizando consecutivos de $desde a $hasta → empezando en $nuevoInicio");




        foreach ($consecutivos as $index => $original) {
            $nuevo = $nuevoInicio + $index;

            DB::table('proyecto_detalle')
                ->where('consecutivo', $original)
                ->where('proyecto_id', $proyecto)
                ->where('torre', $torre)
                ->where('piso', $piso)
                ->update(['consecutivo' => $nuevo]);

            info("✔️  $original → $nuevo");
        }

        return response()->json([
            'status' => 'success',
            'message' => '¡Consecutivos actualizados correctamente!',
        ]);
    }

    // Alertar de proyectos sin movimientos
    public function obrasSinMovimientos()
    {
        $festivos = Festivos::pluck('festivo_fecha')->toArray();
        $hoy = Carbon::today();

        $resultado = [];

        // Obtener todos los proyectos activos
        $proyectos = Proyectos::where('estado', 1)->get();

        foreach ($proyectos as $proyecto) {
            $ultimoDetalle = $proyecto->detalles()
                ->whereNotNull('fecha_fin')
                ->orderByDesc('fecha_fin')
                ->first();

            if (!$ultimoDetalle) {
                continue;
            }

            $ultimaFechaFin = Carbon::parse($ultimoDetalle->fecha_fin)->startOfDay();
            $diasHabiles = 0;
            $fechaTemp = $ultimaFechaFin->copy();

            // Contar días hábiles desde la última fecha_fin hasta hoy
            while ($fechaTemp->lt($hoy)) {
                $fechaTemp->addDay();

                // ✅ Omitir domingos y festivos
                if (
                    !$fechaTemp->isSunday() &&
                    !in_array($fechaTemp->format('Y-m-d'), $festivos)
                ) {
                    $diasHabiles++;
                }
            }

            // Si tiene 2 o más días hábiles de inactividad, lo agregamos
            if ($diasHabiles >= 2) {
                $resultado[] = [
                    'proyecto_id'   => $proyecto->id,
                    'descripcion'   => $proyecto->descripcion_proyecto,
                    'ultima_fecha'  => $ultimaFechaFin->format('Y-m-d'),
                    'dias_inactivo' => $diasHabiles,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $resultado
        ]);
    }

    // Alertar de proyectos sin movimientos ing
    public function obrasSinMovimientosIng()
    {
        $festivos = Festivos::pluck('festivo_fecha')->toArray();
        $hoy = Carbon::today();

        $resultado = [];
        $usuario = Auth::user();

        // 🔹 Filtrar proyectos según el rol
        $proyectos = Proyectos::where('estado', 1)
            ->where(function ($query) use ($usuario) {
                $userId = Auth::id();
                if ($usuario->rol == "Ingeniero Obra") {
                    $query->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')");
                } else {
                    $query->whereRaw("JSON_CONTAINS(encargado_id, '\"$userId\"')");
                }
            })
            ->get();

        foreach ($proyectos as $proyecto) {
            $ultimoDetalle = $proyecto->detalles()
                ->whereNotNull('fecha_fin')
                ->orderByDesc('fecha_fin')
                ->first();

                info($proyecto);

            if (!$ultimoDetalle) {
                continue;
            }

            $ultimaFechaFin = Carbon::parse($ultimoDetalle->fecha_fin)->startOfDay();
            $diasHabiles = 0;
            $fechaTemp = $ultimaFechaFin->copy();

            // Contar días hábiles desde la última fecha_fin hasta hoy
            while ($fechaTemp->lt($hoy)) {
                $fechaTemp->addDay();

                // ✅ Omitir domingos y festivos
                if (
                    !$fechaTemp->isSunday() &&
                    !in_array($fechaTemp->format('Y-m-d'), $festivos)
                ) {
                    $diasHabiles++;
                }
            }

            // Si tiene 2 o más días hábiles de inactividad, lo agregamos
            if ($diasHabiles >= 2) {
                $resultado[] = [
                    'proyecto_id'   => $proyecto->id,
                    'descripcion'   => $proyecto->descripcion_proyecto,
                    'ultima_fecha'  => $ultimaFechaFin->format('Y-m-d'),
                    'dias_inactivo' => $diasHabiles,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $resultado
        ]);
    }
}
