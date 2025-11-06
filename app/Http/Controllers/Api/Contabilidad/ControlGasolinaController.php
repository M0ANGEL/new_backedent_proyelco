<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\ControlGasolina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControlGasolinaController extends Controller
{
    public function placas()
    {
        $data = ControlGasolina::select('id', 'placa')
            ->groupBy('placa', 'id')
            ->get()
            ->unique('placa')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }



    public function conductores()
    {
        //tabla a usar empleados_proyelco_th los que tengan cargo CONDUCTOR - OPERARIO
        $data = DB::connection('mysql')
            ->table('empleados_proyelco_th')
            ->join('cargos_th', 'cargos_th.id', 'empleados_proyelco_th.cargo_id')
            ->where('cargos_th.cargo', '=', 'CONDUCTOR - OPERARIO')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //retonor de informacion con sus filtros

    // public function dataControlGasolina(Request $request)
    // {
    //     $query = ControlGasolina::with(['empleado', 'user']);

    //     // Filtrar por tipo de filtro
    //     if ($request->has('tipoFiltro')) {
    //         $tipoFiltro = $request->tipoFiltro;

    //         // Filtro por rango de fechas
    //         if ($tipoFiltro == '1' && $request->has('fechaInicio') && $request->has('fechaFin')) {
    //             $fechaInicio = $request->fechaInicio;
    //             $fechaFin = $request->fechaFin;
    //             $query->whereBetween('fecha_factura', [$fechaInicio, $fechaFin]);
    //         }

    //         // Filtro por corte
    //         if ($tipoFiltro == '2' && $request->has('corte') && $request->has('mes')) {
    //             $mes = $request->mes;
    //             $corte = $request->corte;

    //             // Determinar el año actual
    //             $year = date('Y');

    //             if ($corte == '1') {
    //                 // Corte del 1 al 15
    //                 $fechaInicio = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-01';
    //                 $fechaFin = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-15';
    //             } else {
    //                 // Corte del 16 a fin de mes
    //                 $fechaInicio = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-16';
    //                 $fechaFin = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($fechaInicio));
    //             }

    //             $query->whereBetween('fecha_factura', [$fechaInicio, $fechaFin]);
    //         }
    //     }

    //     // Filtrar por placas
    //     if ($request->has('placas') && is_array($request->placas) && count($request->placas) > 0) {
    //         $query->whereIn('placa', $request->placas);
    //     }

    //     // Filtrar por conductores (empleado_id)
    //     if ($request->has('conductores') && is_array($request->conductores) && count($request->conductores) > 0) {
    //         $query->whereIn('empleado_id', $request->conductores);
    //     }

    //     $data = $query->get();

    //     // Formatear la respuesta para el frontend
    //     $formattedData = $data->map(function ($item) {
    //         return [
    //             'proyecto' => $item->placa, // Usar placa como proyecto
    //             'cliente' => $item->empleado ? $item->empleado->nombre_completo : 'N/A', // Usar nombre del empleado como cliente
    //             'cantidad' => floatval($item->dinero), // Usar el campo dinero como cantidad
    //             'volumen' => $item->volumen,
    //             'km' => $item->km,
    //             'fecha' => $item->fecha_factura,
    //             'combustible' => $item->combustible,
    //             'comprobante' => $item->comprobante
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $formattedData
    //     ]);
    // }

    public function dataControlGasolina(Request $request)
    {
        $query = ControlGasolina::with(['empleado', 'user']);

        // Filtrar por tipo de filtro
        if ($request->has('tipoFiltro')) {
            $tipoFiltro = $request->tipoFiltro;

            // Filtro por rango de fechas
            if ($tipoFiltro == '1' && $request->has('fechaInicio') && $request->has('fechaFin')) {
                $fechaInicio = $request->fechaInicio;
                $fechaFin = $request->fechaFin;
                $query->whereBetween('fecha_factura', [$fechaInicio, $fechaFin]);
            }

            // Filtro por corte
            if ($tipoFiltro == '2' && $request->has('corte') && $request->has('mes')) {
                $mes = $request->mes;
                $corte = $request->corte;

                // Determinar el año actual
                $year = date('Y');

                if ($corte == '1') {
                    // Corte del 1 al 15
                    $fechaInicio = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-01';
                    $fechaFin = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-15';
                } else {
                    // Corte del 16 a fin de mes
                    $fechaInicio = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-16';
                    $fechaFin = $year . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($fechaInicio));
                }

                $query->whereBetween('fecha_factura', [$fechaInicio, $fechaFin]);
            }
        }

        // Filtrar por placas
        if ($request->has('placas') && is_array($request->placas) && count($request->placas) > 0) {
            $query->whereIn('placa', $request->placas);
        }

        // Filtrar por conductores (empleado_id)
        if ($request->has('conductores') && is_array($request->conductores) && count($request->conductores) > 0) {
            $query->whereIn('empleado_id', $request->conductores);
        }

        $data = $query->orderBy('placa')->orderBy('fecha_factura')->get();

        // Agrupar por placa y calcular métricas
        $groupedData = [];

        foreach ($data->groupBy('placa') as $placa => $registros) {
            if ($registros->count() < 2) {
                // Si hay menos de 2 registros, no se puede calcular el kilometraje
                continue;
            }

            // Ordenar registros por fecha
            $registrosOrdenados = $registros->sortBy('fecha_factura');

            // Tomar el primer y último registro
            $primerRegistro = $registrosOrdenados->first();
            $ultimoRegistro = $registrosOrdenados->last();

            // Calcular kilómetros recorridos
            $kmInicial = floatval($primerRegistro->km);
            $kmFinal = floatval($ultimoRegistro->km);
            $kmRecorridos = $kmFinal - $kmInicial;

            // Sumar volumen total (convertir a galones si es necesario)
            $volumenTotalLitros = $registros->sum('volumen');
            $volumenTotalGalones = $volumenTotalLitros / 3.78541; // Convertir litros a galones

            // Calcular km/gal
            $kmPorGalon = $volumenTotalGalones > 0 ? $kmRecorridos / $volumenTotalGalones : 0;

            // Sumar total dinero
            $totalDinero = $registros->sum('dinero');

            $groupedData[] = [
                'placa' => $placa,
                'conductor' => $primerRegistro->empleado ? $primerRegistro->empleado->nombre_completo : 'N/A',
                'km_inicial' => $kmInicial,
                'km_final' => $kmFinal,
                'km_recorridos' => $kmRecorridos,
                'volumen_litros' => $volumenTotalLitros,
                'volumen_galones' => round($volumenTotalGalones, 2),
                'km_por_galon' => round($kmPorGalon, 2),
                'total_dinero' => $totalDinero,
                'cantidad_registros' => $registros->count(),
                'fecha_inicio' => $primerRegistro->fecha_factura,
                'fecha_fin' => $ultimoRegistro->fecha_factura
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $groupedData
        ]);
    }
}
