<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContratistasController extends Controller
{
     public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('contratistas_th')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function ContratistasActivos(){
         $clientes = DB::connection('mysql')
            ->table('contratistas_th')
            ->where('estado',1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

}
