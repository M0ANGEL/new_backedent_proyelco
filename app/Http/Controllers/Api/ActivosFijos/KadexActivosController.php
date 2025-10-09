<?php

namespace App\Http\Controllers\Api\ActivosFijos;

use App\Http\Controllers\Controller;
use App\Models\Activo;
use App\Models\CategoriaActivos;
use App\Models\KadexActivosModel;
use App\Models\SolicitudActivoModel;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KadexActivosController extends Controller
{
    public function index()
    {
        //consulta a la bd los clientes
        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area', 'activo.ubicacion_actual_id', '=', 'bodegas_area.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodegas_area.nombre as bodega_actual'
            )
            ->whereIn('activo.aceptacion', ["0", "3"]) //todo los activos que no esten en transito de aceptacion
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => ['required'],
                'observacion' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $activoData = Activo::find($request->id);


            // Recupero los datos de la categoria por el id
            $CategoriaData = CategoriaActivos::where('id', $activoData->categoria_id)->first();

            // busco el ultimo nmero del tike con ese codigo_traslado
            $ultimoTike =  DB::connection('mysql')->table('kadex_activos')->where('codigo_traslado', 'like', "{$CategoriaData->prefijo}-%")
                ->orderBy('id', 'desc')
                ->first();

            // Calculo el siguiente numero
            if ($ultimoTike) {
                //  el número después del codigo_traslado
                $ultimoNumero = (int) str_replace("{$CategoriaData->prefijo}-", '', $ultimoTike->codigo_traslado);
                $siguienteNumero = $ultimoNumero + 1;
            } else {
                // Si no hay registros previos, iniciamos desde 1
                $siguienteNumero = 1;
            }

            // Generaro el número del tike final
            $numeroTrasladoFinal = "{$CategoriaData->prefijo}-" . str_pad($siguienteNumero, STR_PAD_LEFT);

            $user = Auth::user();

            if ($request->solicitud == 0) {
                $cliente = new KadexActivosModel();
                $cliente->codigo_traslado = $numeroTrasladoFinal;
                $cliente->activo_id = $request->id;
                $cliente->user_id = $user->id; //usuario quien crea el traslado
                $cliente->usuarios_asignados = $request->filled('usuarios') ? json_encode($request->usuarios) : null;
                $cliente->ubicacion_actual_id = $activoData->ubicacion_actual_id;
                $cliente->ubicacion_destino_id = $request->ubicacion_destino;
                $cliente->tipo_ubicacion = $request->tipo_ubicacion;
                $cliente->observacion = $request->observacion;
                $cliente->save(); // se guarda para obtener el ID

                $activoData->aceptacion = 1; //se pone el activo en estado 1 ya que esta en envio de aceptacion
                $activoData->usuarios_asignados = $request->filled('usuarios') ? json_encode($request->usuarios) : null;
                $activoData->ubicacion_destino_id = $request->ubicacion_destino;
                $activoData->usuarios_confirmaron = null;
                $activoData->save();
            } else {
                //aqui tomamos los datos del traslado
                $solicitud  = SolicitudActivoModel::where('activo_id', $request->id)->first();

                $userId = (string) $solicitud->user_id; // forzar a string

                $userSolicitaActivo = [];

                // convertir todo a string
                $userSolicitaActivo = array_map('strval', $userSolicitaActivo);

                // agregar el user logueado si no está
                if (!in_array($userId, $userSolicitaActivo)) {
                    $userSolicitaActivo[] = $userId;
                }



                $cliente = new KadexActivosModel();
                $cliente->codigo_traslado = $numeroTrasladoFinal;
                $cliente->activo_id = $solicitud->activo_id;
                $cliente->user_id = $user->id; //usuario quien crea el traslado
                $cliente->usuarios_asignados = $userSolicitaActivo ? json_encode($userSolicitaActivo) : null;
                $cliente->ubicacion_actual_id = $activoData->ubicacion_actual_id;
                $cliente->ubicacion_destino_id = $solicitud->bodega_solicita;
                $cliente->tipo_ubicacion = $solicitud->tipo_ubicacion;
                $cliente->observacion = $request->observacion;
                $cliente->save(); // se guarda para obtener el ID

                //info del activo 
                $activoData->aceptacion = 1; //se pone el activo en estado 1 ya que esta en envio de aceptacion
                $activoData->usuarios_asignados = $userSolicitaActivo ? json_encode($userSolicitaActivo) : null;
                $activoData->ubicacion_destino_id = $solicitud->bodega_solicita;
                $activoData->usuarios_confirmaron = null;
                $activoData->save();

                $solicitud->estado = 1;
                $solicitud->save();
            }


            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json(KadexActivosModel::find($id), 200);
    }

    public function activosPendientes()
    {
        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('kadex_activos', 'activo.id', '=', 'kadex_activos.activo_id')
            ->join('bodegas_area as bodega_origen', 'kadex_activos.ubicacion_actual_id', '=', 'bodega_origen.id')
            ->join('bodegas_area as bodega_destino', 'kadex_activos.ubicacion_destino_id', '=', 'bodega_destino.id')
            ->select(
                'activo.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodega_origen.nombre as bodega_origen',
                'bodega_destino.nombre as bodega_destino',
                'kadex_activos.codigo_traslado',
            )
            ->where('activo.aceptacion', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function infoActivo($id)
    {

        $clientes = DB::connection('mysql')
            ->table('kadex_activos')
            ->leftJoin('users as user_rechaza', 'kadex_activos.userRechaza_id', '=', 'user_rechaza.id')
            ->join('users', 'kadex_activos.user_id', '=', 'users.id')
            ->join('activo', 'kadex_activos.activo_id', '=', 'activo.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area as bodega_origen', 'kadex_activos.ubicacion_actual_id', '=', 'bodega_origen.id')
            ->join('bodegas_area as bodega_destino', 'kadex_activos.ubicacion_destino_id', '=', 'bodega_destino.id')
            ->select(
                'kadex_activos.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodega_origen.nombre as bodega_origen',
                'bodega_destino.nombre as bodega_destino',
                'activo.*',
                'user_rechaza.nombre as user_rechaza',
            )
            ->where('kadex_activos.id', $id)
            ->get();

        foreach ($clientes as $proyecto) {
            $encargadoIds = json_decode($proyecto->usuarios_asignados, true) ?? [];
            $ingenieroIds = json_decode($proyecto->usuarios_confirmaron, true) ?? [];

            $proyecto->usuariosAsignados = DB::table('users')
                ->whereIn('id', $encargadoIds)
                ->pluck('nombre');

            $proyecto->usuariosAceptaron = DB::table('users')
                ->whereIn('id', $ingenieroIds)
                ->pluck('nombre');
        }

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function activosSinConfirmar()
    {
        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->leftJoin('bodegas_area', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'bodegas_area.id')
                    ->where('activo.tipo_ubicacion', 1); // asegura que solo cruce cuando es bodega
            })
            ->leftJoin('proyecto', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'proyecto.id')
                    ->where('activo.tipo_ubicacion', 2); // asegura que solo cruce cuando es proyecto
            })
            ->select(
                'activo.*',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                DB::raw("
            CASE 
                WHEN activo.tipo_ubicacion = 1 THEN bodegas_area.nombre
                WHEN activo.tipo_ubicacion = 2 THEN proyecto.descripcion_proyecto
            END as bodega_origen
        ")
            )
            ->where(function ($query) {
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(activo.usuarios_asignados, '\"$userId\"')");
            })
            ->where('activo.aceptacion', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function misActivos()
    {
        $userId = Auth::id();

        $clientes = DB::connection('mysql')
            ->table('activo')
            ->join('users', 'activo.user_id', '=', 'users.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area as bodega_origen', 'activo.ubicacion_actual_id', '=', 'bodega_origen.id')
            ->leftJoin('solicitudes_activos', 'activo.id', '=', 'solicitudes_activos.activo_id')
            ->leftJoin('users as usuario_solicita', 'solicitudes_activos.user_id', '=', 'usuario_solicita.id')
            ->select(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodega_origen.nombre as bodega_actual',
                DB::raw("MAX(CASE WHEN solicitudes_activos.estado = 0 THEN solicitudes_activos.motivo ELSE NULL END) as motivo_solicitud"),
                DB::raw("MAX(CASE WHEN solicitudes_activos.estado = 0 THEN usuario_solicita.nombre ELSE NULL END) as usuario_solicita"),
                DB::raw("MAX(CASE WHEN solicitudes_activos.estado = 0 THEN 1 ELSE 0 END) as solicitud")
            )
            ->whereRaw("JSON_CONTAINS(activo.usuarios_confirmaron, '\"$userId\"')")
            ->where('activo.aceptacion', 2)
            ->groupBy(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'users.nombre',
                'categoria_activos.nombre',
                'subcategoria_activos.nombre',
                'bodega_origen.nombre'
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function aceptarActivo($id)
    {
        $userId = (string) Auth::id(); // forzar a string

        $activo = Activo::where(function ($query) use ($userId) {
            $query->whereRaw("JSON_CONTAINS(usuarios_asignados, '\"$userId\"')");
        })
            ->where('id', $id)
            ->where('aceptacion', 1)
            ->firstOrFail();

        // actualizar estado
        $activo->aceptacion = 2;

        // aseguramos que siempre sea un array
        $usuariosConfirmaron = $activo->usuarios_confirmaron ?? [];

        // convertir todo a string
        $usuariosConfirmaron = array_map('strval', $usuariosConfirmaron);

        // agregar el user logueado si no está
        if (!in_array($userId, $usuariosConfirmaron)) {
            $usuariosConfirmaron[] = $userId;
        }

        // actualizar ubicaciones y confirmaciones
        $activo->ubicacion_actual_id = $activo->ubicacion_destino_id;
        $activo->ubicacion_destino_id = null;
        $activo->usuarios_confirmaron = $usuariosConfirmaron;
        $activo->save();

        //actualizar kardex
        $cliente = KadexActivosModel::where('activo_id', $id)->where('aceptacion', 1)->first();
        $cliente->usuarios_confirmaron = $usuariosConfirmaron;
        $cliente->aceptacion = 2;

        $cliente->save(); // se guarda para obtener el ID

        return response()->json([
            'status' => 'success',
            'data' => $activo
        ]);
    }

    public function rechazarActivo(Request $request)
    {
        $userId = (string) Auth::id(); // forzar a string

        $activo = Activo::where(function ($query) use ($userId) {
            $query->whereRaw("JSON_CONTAINS(usuarios_asignados, '\"$userId\"')");
        })
            ->where('id', $request->id)
            ->where('aceptacion', 1)
            ->firstOrFail();

        // actualizar estado
        $activo->aceptacion = 3; //rechazado
        $activo->usuarios_asignados = null;
        $activo->ubicacion_destino_id = null;
        $activo->save();

        //actualizar kardex
        $cliente = KadexActivosModel::where('activo_id', $request->id)->where('aceptacion', 1)->first();
        $cliente->usuarios_confirmaron = null;
        $cliente->observacion_rachazo = $request->observacion;
        $cliente->userRechaza_id = Auth::id();
        $cliente->aceptacion = 3; //rechazo

        $cliente->save(); // se guarda para obtener el ID

        return response()->json([
            'status' => 'success',
            'data' => $activo
        ]);
    }

    public function historico()
    {
        $clientes = DB::connection('mysql')
            ->table('kadex_activos')
            ->join('users', 'kadex_activos.user_id', '=', 'users.id')
            ->join('activo', 'kadex_activos.activo_id', '=', 'activo.id')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->join('bodegas_area as bodega_origen', 'kadex_activos.ubicacion_actual_id', '=', 'bodega_origen.id')
            ->join('bodegas_area as bodega_destino', 'kadex_activos.ubicacion_destino_id', '=', 'bodega_destino.id')
            ->select(
                'kadex_activos.*',
                'users.nombre as usuario',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                'bodega_origen.nombre as bodega_origen',
                'bodega_destino.nombre as bodega_destino',
                'activo.numero_activo',
                'activo.valor',
                'activo.condicion',
                'activo.descripcion',
            )
            ->orderBy('id', 'asc')
            ->get();

        foreach ($clientes as $proyecto) {
            $encargadoIds = json_decode($proyecto->usuarios_asignados, true) ?? [];

            $proyecto->usuariosAsignados = DB::table('users')
                ->whereIn('id', $encargadoIds)
                ->pluck('nombre');
        }

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function solicitarActivos()
    {
        $userId = Auth::id();

        $ActivosSolicitar = DB::connection('mysql')
            ->table('activo')
            ->join('categoria_activos', 'activo.categoria_id', '=', 'categoria_activos.id')
            ->join('subcategoria_activos', 'activo.subcategoria_id', '=', 'subcategoria_activos.id')
            ->leftJoin('bodegas_area', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'bodegas_area.id')
                    ->where('activo.tipo_ubicacion', 1); // solo si es bodega
            })
            ->leftJoin('proyecto', function ($join) {
                $join->on('activo.ubicacion_actual_id', '=', 'proyecto.id')
                    ->where('activo.tipo_ubicacion', 2); // solo si es proyecto
            })
            ->leftJoin('solicitudes_activos', 'activo.id', '=', 'solicitudes_activos.activo_id') // 👈 cruce con solicitudes
            ->select(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'categoria_activos.nombre as categoria',
                'subcategoria_activos.nombre as subcategoria',
                DB::raw("
                CASE 
                    WHEN activo.tipo_ubicacion = 1 THEN bodegas_area.nombre
                    WHEN activo.tipo_ubicacion = 2 THEN proyecto.descripcion_proyecto
                END as bodega_actual
            "),
                DB::raw("
                MAX(CASE WHEN solicitudes_activos.estado = 0 THEN 1 ELSE 0 END) as solicitud
            ")
            )
            ->whereRaw("NOT JSON_CONTAINS(activo.usuarios_asignados, '\"$userId\"')")
            ->where('activo.aceptacion', '!=', 1)
            ->groupBy(
                'activo.id',
                'activo.numero_activo',
                'activo.descripcion',
                'activo.valor',
                'activo.condicion',
                'activo.marca',
                'activo.serial',
                'activo.estado',
                'activo.created_at',
                'categoria_activos.nombre',
                'subcategoria_activos.nombre',
                'bodega_actual',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $ActivosSolicitar
        ]);
    }

    public function envioSolicitudActivo(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'id' => ['required'],
                'observacion' => ['required'],
                'ubicacion_destino' => ['required'],
                'tipoUbicacion' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $user = Auth::user();

            $solicitud = new SolicitudActivoModel();
            $solicitud->activo_id = $request->id;
            $solicitud->user_id = $user->id; //usuario quien crea el traslado
            $solicitud->bodega_solicita = $request->ubicacion_destino;
            $solicitud->motivo = $request->observacion;
            $solicitud->tipo_ubicacion = $request->tipoUbicacion;
            $solicitud->save();




            return response()->json([
                'status' => 'success',
                'data' => $solicitud
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }

    public function liberarActivos(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => ['required'],
                'observacion' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $activoData = Activo::find($request->id);


            // Recupero los datos de la categoria por el id
            $CategoriaData = CategoriaActivos::where('id', $activoData->categoria_id)->first();

            // busco el ultimo nmero del tike con ese codigo_traslado
            $ultimoTike =  DB::connection('mysql')->table('kadex_activos')->where('codigo_traslado', 'like', "{$CategoriaData->prefijo}-%")
                ->orderBy('id', 'desc')
                ->first();

            // Calculo el siguiente numero
            if ($ultimoTike) {
                //  el número después del codigo_traslado
                $ultimoNumero = (int) str_replace("{$CategoriaData->prefijo}-", '', $ultimoTike->codigo_traslado);
                $siguienteNumero = $ultimoNumero + 1;
            } else {
                // Si no hay registros previos, iniciamos desde 1
                $siguienteNumero = 1;
            }

            // Generaro el número del tike final
            $numeroTrasladoFinal = "{$CategoriaData->prefijo}-" . str_pad($siguienteNumero, STR_PAD_LEFT);

            $user = Auth::user();


            $cliente = new KadexActivosModel();
            $cliente->codigo_traslado = $numeroTrasladoFinal;
            $cliente->activo_id = $request->id;
            $cliente->user_id = $user->id; //usuario quien crea el traslado
            $cliente->usuarios_asignados = null;
            $cliente->ubicacion_actual_id = $activoData->ubicacion_actual_id;
            $cliente->ubicacion_destino_id = 1;
            $cliente->tipo_ubicacion = 1;
            $cliente->observacion = $request->observacion;
            $cliente->aceptacion = 2;
            $cliente->save(); // se guarda para obtener el ID

            $activoData->aceptacion = 0; //se pone el activo en estado 1 ya que esta en envio de aceptacion
            $activoData->usuarios_asignados =  null;
            $activoData->ubicacion_actual_id = 1;
            $activoData->usuarios_confirmaron = null;
            $activoData->tipo_ubicacion = 1;
            $activoData->save();



            return response()->json([
                'status' => 'success',
                'data' => $cliente
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'error ' . $e->getMessage(),
            ], 500);
        }
    }
}
