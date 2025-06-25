<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\CambioProcesoProyectos;
use App\Models\Clientes;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProyectosController extends Controller
{
    public function index()
    {
        // Consulta a la bd los proyectos
        $proyectos = DB::connection('mysql')
            ->table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id') // relación con los tipos de proyectos
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id') // relación con los clientes
            ->join('users', 'proyecto.encargado_id', '=', 'users.id') // usuario que crea el proyecto
            ->join('users as ing', 'proyecto.ingeniero_id', '=', 'ing.id') // ingenieros 
            ->select(
                'proyecto.*',
                'tipos_de_proyectos.nombre_tipo',
                'users.nombre as nombreEncargado',
                'ing.nombre as nombreIngeniero',
                'clientes.emp_nombre',
            )
            ->get();

        // Calcular el porcentaje de atraso y avance para cada proyecto
        foreach ($proyectos as $proyecto) {
            $detalles = DB::connection('mysql')
                ->table('proyecto_detalle')
                ->where('proyecto_id', $proyecto->id)
                ->where('orden_proceso','!=', 1)
                ->get();

            // Cálculo de atraso
            $totalEjecutando = $detalles->where('estado', 1)->count();
            $totalTerminado = $detalles->where('estado', 2)->count();
            $total = $totalEjecutando + $totalTerminado;

            $porcentaje = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;
            $proyecto->porcentaje = round($porcentaje, 2);

            // Cálculo de avance
            $totalApartamentos = $detalles->count();
            $apartamentosRealizados = $totalTerminado;

            $avance = $totalApartamentos > 0 ? ($apartamentosRealizados / $totalApartamentos) * 100 : 0;
            $proyecto->avance = round($avance, 2);
        }

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
            // ->where('rol', 'Encargado Obras')
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
                'encargado_id' => ['required'],
                'ingeniero_id' => ['required'],
                'torres' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'tipo_obra' => ['required'],
                'cant_pisos' /* => ['string','null'] */,
                'apt' /* => ['string','null'] */,
                'activador_pordia_apt' => ['required', 'string'],
                'procesos' => ['required', 'array'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $proyectoUnico = Proyectos::where('codigo_proyecto', $request->codigo_proyecto)
            ->select('descripcion_proyecto')
            ->first();
            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Codigo de proyecto amarrado al proyecto: ' . $proyectoUnico->descripcion_proyecto,
                ], 404);
            }

            $cliente = Clientes::where('nit', $request->nit)->first();
            if (!$cliente) {
                return response()->json(['error' => 'Cliente no encontrado'], 404);
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
            $proyecto->pisosCambiarProceso = 2; //se va a borrar despues
            $proyecto->encargado_id = $request->encargado_id;
            $proyecto->ingeniero_id = $request->ingeniero_id;
            $proyecto->activador_pordia_apt = $request->activador_pordia_apt;

            // Registrar usuarios de notificación solo si vienen en la solicitud
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
                            $cambioPisosNuevo->proceso = $index + 1; // Esto mantiene el orden
                            $cambioPisosNuevo->numero = $proceso['numCambioProceso'];
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
                            $cambioPisosNuevo->proceso = $index + 1; // Esto mantiene el orden
                            $cambioPisosNuevo->numero = $proceso['numCambioProceso'];
                            $cambioPisosNuevo->save();
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

        $proyectos = DB::connection('mysql')
            ->table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id') //relacion con los tipos de proyectos
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id') //relacion con los clientes
            ->where('proyecto.id', $id)
            ->select(
                'proyecto.*',
                'clientes.emp_nombre',
                'clientes.nit',
            )
            ->first();


        return response()->json($proyectos, 200);
    }


    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'encargado_id' => ['required', 'string'],
                'ingeniero_id' => ['required', 'string'],
                'emp_nombre' => ['required', 'string'],
                'nit' => ['required', 'string'],
                'descripcion_proyecto' => ['required', 'string'],
                'fecha_inicio' => ['required', 'string'],
                'codigo_proyecto' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // validar que el codigo del proyecto no este usado por otro
            $proyectoUnico = Proyectos::where('codigo_proyecto', $request->codigo_proyecto)
                ->where('id', '!=', $id)
                ->first();

            if ($proyectoUnico) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error: Este codigo de proyecto esta amarrado a otro proyecto',
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
            $updateProyecto->encargado_id = $request->encargado_id;
            $updateProyecto->ingeniero_id = $request->ingeniero_id;
            $updateProyecto->cliente_id = $cliente->id;
            $updateProyecto->descripcion_proyecto = $request->descripcion_proyecto;
            $updateProyecto->fecha_inicio = Carbon::parse($request->fecha_inicio);
            $updateProyecto->codigo_proyecto = $request->codigo_proyecto;

            $updateProyecto->usuarios_notificacion = $request->filled('usuarios_notificacion') ? json_encode($request->usuarios_notificacion) : null;
            $updateProyecto->save();

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
                'proyectosActivos' => $proyectosActivos,
                'proyectosInactivos' => $proyectosInactivos,
                'proyectosTerminados' => $proyectosTerminados,
                'clientesActivos' => $clientesActivos,
                'clientesInactivos' => $clientesInactivos,
            ],

        ], 200);
    }

    // public function PorcentajeDetalles(Request $request)
    // {
    //     // Consultar todos los datos del proyecto
    //     $detalles = DB::connection('mysql')
    //         ->table('proyecto_detalle')
    //         ->where('proyecto_id', $request->id)
    //         ->get();

    //     if ($detalles->isEmpty()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'No se encontraron registros para este proyecto.',
    //         ], 404);
    //     }

    //     // Porcentaje a nivel de Proyecto
    //     $totalEjecutando = $detalles->where('estado', 1)->count();
    //     $totalTerminado = $detalles->where('estado', 2)->count();
    //     $total = $totalEjecutando + $totalTerminado;

    //     $porcentajeXProyecto = $total > 0 ? ($totalEjecutando / $total) * 100 : 0;

    //     // Porcentaje por Torre
    //     $torres = $detalles->groupBy('torre');
    //     $porcentajeXTorre = [];

    //     foreach ($torres as $torre => $items) {
    //         $ejecutando = $items->where('estado', 1)->count();
    //         $terminado = $items->where('estado', 2)->count();
    //         $totalTorre = $ejecutando + $terminado;

    //         $porcentaje = $totalTorre > 0 ? ($ejecutando / $totalTorre) * 100 : 0;

    //         $porcentajeXTorre[] = [
    //             'torre' => $torre,
    //             'ejecutando' => $ejecutando,
    //             'terminado' => $terminado,
    //             'porcentaje' => round($porcentaje, 2),
    //         ];
    //     }

    //     // Porcentaje por Proceso agrupado por Torre
    //     $porcentajeXProceso = [];

    //     foreach ($torres as $torre => $items) {
    //         $procesos = $items->groupBy('orden_proceso');

    //         foreach ($procesos as $proceso => $apartamentos) {
    //             $ejecutando = $apartamentos->where('estado', 1)->count();
    //             $terminado = $apartamentos->where('estado', 2)->count();
    //             $totalProceso = $ejecutando + $terminado;

    //             $porcentaje = $totalProceso > 0 ? ($ejecutando / $totalProceso) * 100 : 0;

    //             $porcentajeXProceso[] = [
    //                 'torre' => $torre,
    //                 'proceso' => $proceso,
    //                 'ejecutando' => $ejecutando,
    //                 'terminado' => $terminado,
    //                 'porcentaje' => round($porcentaje, 2),
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'status' => 'success',
    //         'data'  => [
    //             'porcentajeXProyecto' => round($porcentajeXProyecto, 2),
    //             'porcentajeXTorre' => $porcentajeXTorre,
    //             'porcentajeXProceso' => $porcentajeXProceso,
    //         ],
    //     ], 200);
    // }

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

}
