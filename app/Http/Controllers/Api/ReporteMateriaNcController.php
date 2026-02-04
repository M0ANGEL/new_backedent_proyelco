<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\FlareClient\Report;

class ReporteMateriaNcController extends Controller
{
    public function index(Request $request)
    {
        $data = DB::table('reporte_material_nc')
            ->select('reporte_material_nc.*', 'proveedores.nombre as proveedor', 'users.nombre as nombre_usuario', 'proyecto.descripcion_proyecto')
            ->leftJoin('proveedores', 'reporte_material_nc.proveedor_id', '=', 'proveedores.id')
            ->leftJoin('users', 'reporte_material_nc.id_user', '=', 'users.id')
            ->leftJoin('proyecto', 'reporte_material_nc.codigo_proyecto', '=', 'proyecto.codigo_proyecto')
            ->orderBy('reporte_material_nc.created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'codigo_proyecto' => 'required|string|max:20|unique:reporte_material_nc,codigo_proyecto',
            'tipo_reporte' => 'required|integer',
            'insumo' => 'required|string',
            'codigo_insumo' => 'required|string|max:100',
            'factura' => 'required|string|max:100',
            'cantidad_reportada' => 'required|string|max:50',
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
            'descripcion_nc' => 'required|string|max:500',
            'id_user' => 'required|integer|exists:users,id',
        ]);

        $reporte = DB::table('reporte_material_nc')->insert($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Reporte creado exitosamente',
            'data' => $reporte
        ], 201);
    }
}
