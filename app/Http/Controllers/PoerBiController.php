<?php

namespace App\Http\Controllers;

use App\Models\PowerBiModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PoerBiController extends Controller
{

    public function index()
    {
        $data = PowerBiModel::all();

        return response()->json([
            'status' => 'succes',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'link_power_bi' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Si la validación ok creamos la nueva categoría
            $data = new PowerBiModel();
            $data->nombre = $request->nombre;
            $data->link_power_bi = $request->link_power_bi;
            $data->save();

            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json(PowerBiModel::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => ['required', 'string'],
                'link_power_bi' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            // Obtener la categoría actual
            $data = PowerBiModel::findOrFail($id);

            $data->nombre = $request->nombre;
            $data->link_power_bi = $request->link_power_bi;
            $data->save();



            return response()->json([
                'status' => 'success',
                'data' => $data
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
        $categoria = PowerBiModel::find($id);

        $categoria->estado = !$categoria->estado;
        $categoria->update();
    }

    public function Rutas(Request $request)
    {
        // validar que llegue "ruta"
        $request->validate([
            'ruta' => 'required|string'
        ]);

        // buscar en la base de datos el link asociado a la ruta
        $data = PowerBiModel::where('ruta', $request->ruta)
            ->where('estado', 1)
            ->select('link_power_bi')
            ->first();

        return response()->json([
            'status' => 'success',
            'link' => $data ? $data->link_power_bi : null,
        ], 200);
    }
}
