<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Http\Controllers\Controller;
use App\Models\ProyectoCasa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionProyectosCasasController extends Controller
{
    //
    // public function indexProgresoCasa(Request $request)
    // {
    //     // 1. CONFIGURACIÓN DE PROCESOS
    //     $procesosConfig = DB::table('proyectos_casas_detalle')
    //         ->join('cambio_procesos_x_proyecto', function ($join) {
    //             $join->on('cambio_procesos_x_proyecto.proyecto_id', '=', 'proyectos_casas_detalle.proyecto_casa_id')
    //                 ->on('cambio_procesos_x_proyecto.proceso', '=', 'proyectos_casas_detalle.procesos_proyectos_id');
    //         })
    //         ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
    //         ->select(
    //             'proyectos_casas_detalle.orden_proceso',
    //             'proyectos_casas_detalle.procesos_proyectos_id',
    //             'proyectos_casas_detalle.proyecto_casa_id'
    //             // ,'cambio_procesos_x_proyecto.numero as pisos_requeridos'
    //         )
    //         ->get()
    //         ->keyBy('orden_proceso');

    //     // 2. NOMBRES DE MANZANAS
    //     $manzanaConNombre = DB::table('nombrexmanzana')
    //         ->where('proyectos_casas_id', $request->id)
    //         ->pluck('nombre_manzana', 'manzana')
    //         ->toArray();

    //     // 3. DETALLE DEL PROYECTO
    //     $proyectosDetalle = DB::connection('mysql')
    //         ->table('proyectos_casas_detalle')
    //         ->leftJoin('users', 'proyectos_casas_detalle.user_id', '=', 'users.id')
    //         ->leftJoin('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
    //         ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
    //         ->select(
    //             'proyectos_casas_detalle.manzana',
    //             'proyectos_casas_detalle.casa',
    //             'proyectos_casas_detalle.id',
    //             'proyectos_casas_detalle.validacion',
    //             'proyectos_casas_detalle.estado_validacion',
    //             'proyectos_casas_detalle.consecutivo_casa',
    //             'proyectos_casas_detalle.orden_proceso',
    //             'proyectos_casas_detalle.piso',
    //             'proyectos_casas_detalle.text_validacion',
    //             'proyectos_casas_detalle.estado',
    //             'procesos_proyectos.nombre_proceso',
    //             'users.nombre as nombre'
    //         )
    //         ->orderBy('proyectos_casas_detalle.orden_proceso')
    //         ->orderBy('proyectos_casas_detalle.piso')
    //         ->orderBy('proyectos_casas_detalle.casa')
    //         ->get();

    //     $resultado = [];
    //     $manzanaResumen = [];

    //     // 4. PROCESAR REGISTROS
    //     foreach ($proyectosDetalle as $item) {
    //         $manzana = $item->manzana;
    //         $orden_proceso = $item->orden_proceso;
    //         $piso = $item->piso;

    //         // Inicializar estructuras si no existen
    //         if (!isset($resultado[$manzana])) {
    //             $resultado[$manzana] = [];
    //         }
    //         if (!isset($manzanaResumen[$manzana])) {
    //             $manzanaResumen[$manzana] = [
    //                 'nombre_manzana' => $manzanaConNombre[$manzana] ?? $manzana,
    //                 'total_atraso' => 0,
    //                 'total_realizados' => 0,
    //                 'porcentaje_atraso' => 0,
    //                 'porcentaje_avance' => 0,
    //                 'serial_avance' => '0/0',
    //                 'pisos_unicos' => []
    //             ];
    //         }

    //         // Registrar pisos únicos por manzana
    //         if (!in_array($piso, $manzanaResumen[$manzana]['pisos_unicos'])) {
    //             $manzanaResumen[$manzana]['pisos_unicos'][] = $piso;
    //         }

    //         // Inicializar proceso si no existe
    //         if (!isset($resultado[$manzana][$orden_proceso])) {
    //             $resultado[$manzana][$orden_proceso] = [
    //                 'nombre_proceso' => $item->nombre_proceso,
    //                 'text_validacion' => $item->text_validacion,
    //                 'estado_validacion' => $item->estado_validacion,
    //                 'validacion' => $item->validacion,
    //                 'pisos' => [],
    //                 'total_casas' => 0,
    //                 'casas_atraso' => 0,
    //                 'casas_realizadas' => 0,
    //                 'porcentaje_atraso' => 0,
    //                 'porcentaje_avance' => 0,
    //                 'pisos_completados' => 0,
    //                 'pisos_requeridos' => $procesosConfig[$orden_proceso]->pisos_requeridos ?? 0
    //             ];
    //         }

    //         // Inicializar piso si no existe
    //         if (!isset($resultado[$manzana][$orden_proceso]['pisos'][$piso])) {
    //             $resultado[$manzana][$orden_proceso]['pisos'][$piso] = [];
    //         }



    //         // 10. Retornar
    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $resultado,
    //             'manzanaResumen' => $manzanaResumen
    //         ]);
    //     }
    // }




    public function infoProyectoCasa($id)
    {
        $info = ProyectoCasa::find($id);

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }


 public function indexProgresoCasa(Request $request)
{
    // 1. CONFIGURACIÓN DE PROCESOS
    $procesosConfig = DB::table('proyectos_casas_detalle')
        ->join('cambio_procesos_x_proyecto', function ($join) {
            $join->on('cambio_procesos_x_proyecto.proyecto_id', '=', 'proyectos_casas_detalle.proyecto_casa_id')
                ->on('cambio_procesos_x_proyecto.proceso', '=', 'proyectos_casas_detalle.procesos_proyectos_id');
        })
        ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
        ->select(
            'proyectos_casas_detalle.orden_proceso',
            'proyectos_casas_detalle.procesos_proyectos_id',
            'proyectos_casas_detalle.proyecto_casa_id',
            'cambio_procesos_x_proyecto.numero as pisos_requeridos'
        )
        ->get()
        ->keyBy('orden_proceso');

    // 2. NOMBRES DE MANZANAS
    $manzanaConNombre = DB::table('nombrexmanzana')
        ->where('proyectos_casas_id', $request->id)
        ->pluck('nombre_manzana', 'manzana')
        ->toArray();

    // 3. DETALLE DEL PROYECTO
    $proyectosDetalle = DB::connection('mysql')
        ->table('proyectos_casas_detalle')
        ->leftJoin('users', 'proyectos_casas_detalle.user_id', '=', 'users.id')
        ->leftJoin('procesos_proyectos', 'proyectos_casas_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
        ->where('proyectos_casas_detalle.proyecto_casa_id', $request->id)
        ->select(
            'proyectos_casas_detalle.manzana',
            'proyectos_casas_detalle.casa',
            'proyectos_casas_detalle.id',
            'proyectos_casas_detalle.validacion',
            'proyectos_casas_detalle.estado_validacion',
            'proyectos_casas_detalle.consecutivo_casa',
            'proyectos_casas_detalle.orden_proceso',
            'proyectos_casas_detalle.piso',
            'proyectos_casas_detalle.text_validacion',
            'proyectos_casas_detalle.estado',
            'procesos_proyectos.nombre_proceso',
            'users.nombre as nombre'
        )
        ->orderBy('proyectos_casas_detalle.manzana')
        ->orderBy('proyectos_casas_detalle.casa')
        ->orderBy('proyectos_casas_detalle.piso')
        ->orderBy('proyectos_casas_detalle.orden_proceso')
        ->get();

    $resultado = [];
    $manzanaResumen = [];

    foreach ($proyectosDetalle as $item) {
        $manzana = $item->manzana;
        $casa = $item->casa;
        $piso = $item->piso;
        $orden_proceso = $item->orden_proceso;

        // Inicializar manzana
        if (!isset($resultado[$manzana])) {
            $resultado[$manzana] = [];
        }

        // Resumen por manzana
        if (!isset($manzanaResumen[$manzana])) {
            $manzanaResumen[$manzana] = [
                'nombre_manzana' => $manzanaConNombre[$manzana] ?? $manzana,
                'total_atraso' => 0,
                'total_realizados' => 0,
                'porcentaje_atraso' => 0,
                'porcentaje_avance' => 0,
                'serial_avance' => '0/0',
            ];
        }

        // Inicializar casa
        if (!isset($resultado[$manzana][$casa])) {
            $resultado[$manzana][$casa] = [
                'consecutivo' => $item->consecutivo_casa,
                'pisos' => []
            ];
        }

        // Inicializar piso
        if (!isset($resultado[$manzana][$casa]['pisos'][$piso])) {
            $resultado[$manzana][$casa]['pisos'][$piso] = [];
        }

        // Inicializar proceso
        if (!isset($resultado[$manzana][$casa]['pisos'][$piso][$orden_proceso])) {
            $resultado[$manzana][$casa]['pisos'][$piso][$orden_proceso] = [
                'nombre_proceso' => $item->nombre_proceso,
                'estado' => $item->estado,
                'validacion' => $item->validacion,
                'estado_validacion' => $item->estado_validacion,
                'text_validacion' => $item->text_validacion,
                'usuario' => $item->nombre,
            ];
        }

        // Contadores para resumen
        if ($item->estado == 2) {
            $manzanaResumen[$manzana]['total_realizados']++;
        } elseif ($item->estado == 0) {
            $manzanaResumen[$manzana]['total_atraso']++;
        }
    }

    // Calcular % por manzana
    foreach ($manzanaResumen as $manzana => &$resumen) {
        $totalCasas = $resumen['total_atraso'] + $resumen['total_realizados'];
        if ($totalCasas > 0) {
            $resumen['porcentaje_avance'] =
                round(($resumen['total_realizados'] / $totalCasas) * 100, 2);
            $resumen['porcentaje_atraso'] =
                round(($resumen['total_atraso'] / $totalCasas) * 100, 2);
            $resumen['serial_avance'] =
                "{$resumen['total_realizados']}/{$totalCasas}";
        }
    }

    return response()->json([
        'status' => 'success',
        'data' => $resultado,       // manzana → casas → pisos → procesos
        'manzanaResumen' => $manzanaResumen
    ]);
}


}



