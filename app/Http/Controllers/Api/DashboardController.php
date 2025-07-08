<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // funciones de proyectos
    public function dashboardsProyectos()
    {
        $proyectosTotales = Proyectos::count();

        $porTorre = ProyectosDetalle::select('torre', DB::raw('count(*) as total'))
            ->groupBy('torre')
            ->get();

        $porEstado = ProyectosDetalle::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get();

        return response()->json([
            'proyectos_totales' => $proyectosTotales,
            'torres' => $porTorre,
            'estados' => $porEstado,
        ]);
    }


}
