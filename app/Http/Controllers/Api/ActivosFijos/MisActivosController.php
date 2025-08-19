<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MisActivosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $activosPorConfirmar = DB::connection('mysql')
            ->table('kadex_activos')
            ->join('users', 'kadex_activos.user_id', '=', 'users.id')
            ->join('activo', 'kadex_activos.activo_id', '=', 'activo.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area', 'kadex_activos.ubicacion_id', '=', 'bodegas_area.id')
            ->select(
                'kadex_activos.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodegas_area.nombre as ubicacion'
            )
            ->where(function ($query) {
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(kadex_activos.usuarios_asignados, '\"$userId\"')");
            })
            // ->where('proyecto.estado', 1)
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $activosPorConfirmar
        ]);
    }


    public function misActivos() {
         //consulta a la bd los clientes
        $activosPorConfirmar = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.categoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area', 'activo.ubicacion_id', '=', 'bodegas_area.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodegas_area.nombre as ubicacion'
            )
            ->where(function ($query) {
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(activo.usuarios_asignados, '\"$userId\"')");
            })
            // ->where('proyecto.estado', 1)
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $activosPorConfirmar
        ]);
    }
}
