<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpxUsu;
use Exception;
use Illuminate\Http\Request;

class EmpxUsuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $empresasxUsu = EmpxUsu::select(
                'id',
                'id_empresa',
                'id_user',
                'estado'
            )->get();
        
            return response()->json([
                'status' => 'success',
                'data' => $empresasxUsu,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se puede listar los datos de las empresas y sus usuarios.',
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $empresaUsu = EmpxUsu::findOrFail($id);
            $empresaUsu->estado = $empresaUsu->estado == 1 ? 0 : 1;
            $empresaUsu->save();

            return response()->json(['message' => 'El estado de la empresa y el usuario se actualizó correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ocurrió un error al actualizar el estado de la empresa y el usuario.'], 500);
        }
    }
}
