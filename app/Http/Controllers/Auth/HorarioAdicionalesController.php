<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\HorarioAdicional;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class HorarioAdicionalesController extends Controller
{
    public function index()
    {
        $perfileshorario = DB::connection('mysql')
            ->table('horarios_adicional')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $perfileshorario,
        ]);
    }


    public function store(Request $request)
    {

        // Validación de los datos
        try {
            $validator = Validator::make($request->all(), [
                'observacion' => ['required'],
                'fecha_inicio' => ['required', 'string'],
                'fecha_final' => ['required', 'string'],
                'proceso_autoriza_id' => ['required', 'string'],
            ]);

            //validación falla
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $horarioAdicional = new HorarioAdicional();
            $horarioAdicional->observacion = $request->observacion;
            $horarioAdicional->fecha_inicio = $request->fecha_inicio;
            $horarioAdicional->fecha_final = $request->fecha_final;
            $horarioAdicional->user_id = Auth::user()->id;
            $horarioAdicional->proceso_autoriza_id = $request->proceso_autoriza_id;
            $horarioAdicional->usuarios_autorizados = $request->usuarios_autorizados ? json_encode($request->usuarios_autorizados) : null; // Guardar como JSON
            $horarioAdicional->save();

            return response()->json(['message' => 'Usuarios guardados con horario adicional correctamente'], 201);
        } catch (Exception $e) {
            // Manejo de errores
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500); {
            }
        }
    }

    public function show($id)
    {

        return response()->json(HorarioAdicional::find($id), 200);
    }

    public function update(Request $request, $id)
    {
        try {

            $validator = Validator::make($request->all(), [
                'observacion' => ['required'],
                'fecha_inicio' => ['required', 'string'],
                'fecha_final' => ['required', 'string'],
                'proceso_autoriza_id' => ['required', 'string'],
            ]);


            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = HorarioAdicional::findOrFail($id);
            $horarioAdicional = clone $data;
            $horarioAdicional->observacion = $request->observacion;
            $horarioAdicional->fecha_inicio = $request->fecha_inicio;
            $horarioAdicional->fecha_final = $request->fecha_final;
            $horarioAdicional->proceso_autoriza_id = $request->proceso_autoriza_id;
            $horarioAdicional->usuarios_autorizados = $request->usuarios_autorizados ? json_encode($request->usuarios_autorizados) : null; // Guardar como JSON
            $horarioAdicional->save();


            return response()->json([
                'status' => 'success',
                'data' => $horarioAdicional
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e,
                'code' => $e->getCode()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $TkSubCategoria = HorarioAdicional::find($id);

        $TkSubCategoria->estado = !$TkSubCategoria->estado;
        $TkSubCategoria->update();
    }
}
