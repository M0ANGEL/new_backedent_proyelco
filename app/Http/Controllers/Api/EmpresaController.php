<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EmpresaLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    public function index()
    {
        try {
            $empresas = Empresa::select(
                'id',
                'emp_nombre',
                'estado',
                'nit',
                'direccion',
                'telefono',
                'cuenta_de_correo',
            )->get();

            return response()->json([
                'status' => 'success',
                'data' => $empresas,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emp_nombre' => ['required', 'string'],
                'nit' => ['required', 'string', 'unique:empresas,nit'],
                'direccion' => ['required', 'string'],
                'telefono' => ['required', 'string'],
                'cuenta_de_correo' => ['required', 'string', 'email'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $empresa = new Empresa();
            $empresa->emp_nombre = $request->emp_nombre;
            $empresa->nit = $request->nit;
            $empresa->direccion = $request->direccion;
            $empresa->telefono = $request->telefono;
            $empresa->cuenta_de_correo = $request->cuenta_de_correo;
            $empresa->save();

            //Capturo el nombre de la empresa para tomar los primeros 3 digitos
            $nombreEmpresa = $request->get('emp_nombre');
            //y con esto crear el id tenant y el dominio
            $dominioTenant = substr($nombreEmpresa, 0, 3);

            //Captura el ID generado en el nuevo registro de la tabla empresa
            $empresaId = $empresa->id;

            return response()->json([
                'status' => 'success',
                'data' => $empresa,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $empresa = Empresa::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $empresa,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }




    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emp_nombre' => ['required', 'string'],
                'direccion' => ['required', 'string'],
                'telefono' => ['required', 'string'],
                'estado' => ['required', 'string'],
                'cuenta_de_correo' => ['required', 'string', 'email'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $data = Empresa::findOrFail($id);
            $empresa = clone $data;
            $empresa->update($validator->validated());

            // registrar acción en user_logs
            $log = new EmpresaLog();
            $log->id_operario = Auth::user()->id; // id del usuario autenticado "quien lo modifico"
            $log->id_empresa = $empresa->id; // id de la empresa "recien creada"
            $log->accion = 'Se actualizo la empresa con id ' . $empresa->id;
            $log->data = $empresa;
            $log->old = $data;
            $log->save();

            return response()->json([
                'status' => 'success',
                'data' => $empresa,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function destroy($id)
    {
        try {
            $data = Empresa::findOrFail($id);
            $empresa = clone $data;
            $empresa->estado = $empresa->estado == 1 ? 0 : 1;
            $empresa->save();

            $accion = $empresa->estado == 1 ? 'Se activo' : 'Se desactivo';

            // registrar acción en empresas_logs
            $log = new EmpresaLog();
            $log->id_operario = Auth::user()->id;
            $log->id_empresa = $empresa->id;
            $log->accion = $accion . ' la empresa con id ' . $empresa->id;
            $log->data = $empresa;
            $log->old = $data;
            $log->save();

            return response()->json(['message' => 'El estado de la empresa se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
