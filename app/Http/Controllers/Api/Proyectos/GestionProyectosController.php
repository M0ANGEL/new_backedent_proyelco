<?php

namespace App\Http\Controllers\Api\Proyectos;

use App\Exports\InformeProyectoExport;
use App\Http\Controllers\Controller;
use App\Models\AnulacionApt;
use App\Models\CambioProcesoProyectos;
use App\Models\ProcesosProyectos;
use App\Models\Proyectos;
use App\Models\ProyectosDetalle;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class GestionProyectosController extends Controller
{
    //index ingenieros
    public function index()
    {
        /**********************************APARTAMENTOS******************************** */
        // Traer proyectos con joins básicos
        $proyectos = DB::table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->where(function ($query) {
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
                // ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
            })
            ->where('proyecto.estado',1)
            ->select(
                'proyecto.*',
                'tipos_de_proyectos.nombre_tipo',
                'clientes.emp_nombre'
            )
            ->get();

        // 1️⃣ Recolectar todos los IDs de encargados e ingenieros
        $encargadoIdsGlobal = [];
        $ingenieroIdsGlobal = [];

        foreach ($proyectos as $proyecto) {
            $encargadoIdsGlobal = array_merge($encargadoIdsGlobal, json_decode($proyecto->encargado_id, true) ?? []);
            $ingenieroIdsGlobal = array_merge($ingenieroIdsGlobal, json_decode($proyecto->ingeniero_id, true) ?? []);
        }

        // 2️⃣ Obtener todos los usuarios de una sola consulta
        $usuarios = DB::table('users')
            ->whereIn('id', array_unique(array_merge($encargadoIdsGlobal, $ingenieroIdsGlobal)))
            ->pluck('nombre', 'id'); // => [id => nombre]

        // 3️⃣ Obtener todos los detalles de los proyectos en una sola consulta
        $detalles = DB::table('proyecto_detalle')
            ->whereIn('proyecto_id', $proyectos->pluck('id'))
            ->get()
            ->groupBy('proyecto_id');

        // 4️⃣ Asignar nombres y cálculos a cada proyecto
        foreach ($proyectos as $proyecto) {
            // Encargados
            $encargadoIds = json_decode($proyecto->encargado_id, true) ?? [];
            $proyecto->nombresEncargados = collect($encargadoIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Ingenieros
            $ingenieroIds = json_decode($proyecto->ingeniero_id, true) ?? [];
            $proyecto->nombresIngenieros = collect($ingenieroIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Detalles del proyecto
            $detalleProyecto = $detalles[$proyecto->id] ?? collect();

            // Cálculo de atraso
            $ejecutando = $detalleProyecto->where('estado', 1)->count();
            $terminado = $detalleProyecto->where('estado', 2)->count();
            $total = $ejecutando + $terminado;

            $proyecto->porcentaje = $total > 0 ? round(($ejecutando / $total) * 100, 2) : 0;

            // Cálculo de avance
            $totalApartamentos = $detalleProyecto->count();
            $apartamentosRealizados = $terminado;
            $proyecto->avance = $totalApartamentos > 0 ? round(($apartamentosRealizados / $totalApartamentos) * 100, 2) : 0;
        }

        // 5️⃣ Ordenar por atraso (porcentaje) de mayor a menor
        $proyectos = $proyectos->sortByDesc('porcentaje')->values();

        /************************************CASAS************************************ */
        // Traer proyectos con joins básicos
        $proyectos_casa = DB::table('proyectos_casas')
            ->join('tipos_de_proyectos', 'proyectos_casas.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyectos_casas.cliente_id', '=', 'clientes.id')
             ->where(function ($query) {
                $userId = Auth::id();
                $query->whereRaw("JSON_CONTAINS(proyectos_casas.ingeniero_id, '\"$userId\"')");
                // ->orWhereRaw("JSON_CONTAINS(proyecto.ingeniero_id, '\"$userId\"')");
            })
            ->select(
                'proyectos_casas.*',
                'tipos_de_proyectos.nombre_tipo',
                'clientes.emp_nombre'
            )
            ->get();

        // 1️⃣ Recolectar todos los IDs de encargados e ingenieros
        $encargadoIdsGlobal = [];
        $ingenieroIdsGlobal = [];

        foreach ($proyectos_casa as $proyecto) {
            $encargadoIdsGlobal = array_merge($encargadoIdsGlobal, json_decode($proyecto->encargado_id, true) ?? []);
            $ingenieroIdsGlobal = array_merge($ingenieroIdsGlobal, json_decode($proyecto->ingeniero_id, true) ?? []);
        }

        // 2️⃣ Obtener todos los usuarios de una sola consulta
        $usuarios = DB::table('users')
            ->whereIn('id', array_unique(array_merge($encargadoIdsGlobal, $ingenieroIdsGlobal)))
            ->pluck('nombre', 'id'); // => [id => nombre]

        // 3️⃣ Obtener todos los detalles de los proyectos en una sola consulta
        $detalles = DB::table('proyectos_casas_detalle')
            ->whereIn('proyecto_casa_id', $proyectos_casa->pluck('id'))
            ->get()
            ->groupBy('proyecto_casa_id');

        // 4️⃣ Asignar nombres y cálculos a cada proyecto
        foreach ($proyectos_casa as $proyecto) {
            // Encargados
            $encargadoIds = json_decode($proyecto->encargado_id, true) ?? [];
            $proyecto->nombresEncargados = collect($encargadoIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Ingenieros
            $ingenieroIds = json_decode($proyecto->ingeniero_id, true) ?? [];
            $proyecto->nombresIngenieros = collect($ingenieroIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            // Detalles del proyecto
            $detalleProyecto = $detalles[$proyecto->id] ?? collect();

            // Cálculo de atraso
            $ejecutando = $detalleProyecto->where('estado', 1)->count();
            $terminado = $detalleProyecto->where('estado', 2)->count();
            $total = $ejecutando + $terminado;

            $proyecto->porcentaje = $total > 0 ? round(($ejecutando / $total) * 100, 2) : 0;

            // Cálculo de avance
            $totalApartamentos = $detalleProyecto->count();
            $apartamentosRealizados = $terminado;
            $proyecto->avance = $totalApartamentos > 0 ? round(($apartamentosRealizados / $totalApartamentos) * 100, 2) : 0;
        }

        // 5️⃣ Ordenar por atraso (porcentaje) de mayor a menor
        $proyectos_casa = $proyectos_casa->sortByDesc('porcentaje')->values();

        return response()->json([
            'status' => 'success',
            'data' => $proyectos,
            'data_casas' => $proyectos_casa
        ]);
    }

    public function indexEncargadoObra()
    {
        $userId = Auth::id();

        /**********************************APARTAMENTOS******************************** */
        // Traer proyectos con joins básicos filtrados por encargado
        $proyectos = DB::table('proyecto')
            ->join('tipos_de_proyectos', 'proyecto.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyecto.cliente_id', '=', 'clientes.id')
            ->where(function ($query) use ($userId) {
                $query->whereRaw("JSON_CONTAINS(proyecto.encargado_id, '\"$userId\"')");
            })
            ->select(
                'proyecto.*',
                'tipos_de_proyectos.nombre_tipo',
                'clientes.emp_nombre'
            )
            ->get();

        // 1️⃣ Recolectar todos los IDs de encargados e ingenieros
        $encargadoIdsGlobal = [];
        $ingenieroIdsGlobal = [];

        foreach ($proyectos as $proyecto) {
            $encargadoIdsGlobal = array_merge($encargadoIdsGlobal, json_decode($proyecto->encargado_id, true) ?? []);
            $ingenieroIdsGlobal = array_merge($ingenieroIdsGlobal, json_decode($proyecto->ingeniero_id, true) ?? []);
        }

        // 2️⃣ Obtener todos los usuarios de una sola consulta
        $usuarios = DB::table('users')
            ->whereIn('id', array_unique(array_merge($encargadoIdsGlobal, $ingenieroIdsGlobal)))
            ->pluck('nombre', 'id'); // => [id => nombre]

        // 3️⃣ Obtener todos los detalles de los proyectos
        $detalles = DB::table('proyecto_detalle')
            ->whereIn('proyecto_id', $proyectos->pluck('id'))
            ->get()
            ->groupBy('proyecto_id');

        // 4️⃣ Asignar nombres y cálculos a cada proyecto
        foreach ($proyectos as $proyecto) {
            $encargadoIds = json_decode($proyecto->encargado_id, true) ?? [];
            $proyecto->nombresEncargados = collect($encargadoIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            $ingenieroIds = json_decode($proyecto->ingeniero_id, true) ?? [];
            $proyecto->nombresIngenieros = collect($ingenieroIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            $detalleProyecto = $detalles[$proyecto->id] ?? collect();

            $ejecutando = $detalleProyecto->where('estado', 1)->count();
            $terminado = $detalleProyecto->where('estado', 2)->count();
            $total = $ejecutando + $terminado;

            $proyecto->porcentaje = $total > 0 ? round(($ejecutando / $total) * 100, 2) : 0;

            $totalApartamentos = $detalleProyecto->count();
            $apartamentosRealizados = $terminado;
            $proyecto->avance = $totalApartamentos > 0 ? round(($apartamentosRealizados / $totalApartamentos) * 100, 2) : 0;
        }

        $proyectos = $proyectos->sortByDesc('porcentaje')->values();

        /************************************CASAS************************************ */
        $proyectos_casa = DB::table('proyectos_casas')
            ->join('tipos_de_proyectos', 'proyectos_casas.tipoProyecto_id', '=', 'tipos_de_proyectos.id')
            ->join('clientes', 'proyectos_casas.cliente_id', '=', 'clientes.id')
            ->where(function ($query) use ($userId) {
                $query->whereRaw("JSON_CONTAINS(proyectos_casas.encargado_id, '\"$userId\"')");
            })
            ->select(
                'proyectos_casas.*',
                'tipos_de_proyectos.nombre_tipo',
                'clientes.emp_nombre'
            )
            ->get();

        $encargadoIdsGlobal = [];
        $ingenieroIdsGlobal = [];

        foreach ($proyectos_casa as $proyecto) {
            $encargadoIdsGlobal = array_merge($encargadoIdsGlobal, json_decode($proyecto->encargado_id, true) ?? []);
            $ingenieroIdsGlobal = array_merge($ingenieroIdsGlobal, json_decode($proyecto->ingeniero_id, true) ?? []);
        }

        $usuarios = DB::table('users')
            ->whereIn('id', array_unique(array_merge($encargadoIdsGlobal, $ingenieroIdsGlobal)))
            ->pluck('nombre', 'id');

        $detalles = DB::table('proyectos_casas_detalle')
            ->whereIn('proyecto_casa_id', $proyectos_casa->pluck('id'))
            ->get()
            ->groupBy('proyecto_casa_id');

        foreach ($proyectos_casa as $proyecto) {
            $encargadoIds = json_decode($proyecto->encargado_id, true) ?? [];
            $proyecto->nombresEncargados = collect($encargadoIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            $ingenieroIds = json_decode($proyecto->ingeniero_id, true) ?? [];
            $proyecto->nombresIngenieros = collect($ingenieroIds)->map(fn($id) => $usuarios[$id] ?? null)->filter();

            $detalleProyecto = $detalles[$proyecto->id] ?? collect();

            $ejecutando = $detalleProyecto->where('estado', 1)->count();
            $terminado = $detalleProyecto->where('estado', 2)->count();
            $total = $ejecutando + $terminado;

            $proyecto->porcentaje = $total > 0 ? round(($ejecutando / $total) * 100, 2) : 0;

            $totalCasas = $detalleProyecto->count();
            $casasRealizadas = $terminado;
            $proyecto->avance = $totalCasas > 0 ? round(($casasRealizadas / $totalCasas) * 100, 2) : 0;
        }

        $proyectos_casa = $proyectos_casa->sortByDesc('porcentaje')->values();

        return response()->json([
            'status' => 'success',
            'data' => $proyectos,
            'data_casas' => $proyectos_casa
        ]);
    }


    public function indexProgreso(Request $request)
    {
        // 1. CONFIGURACIÓN DE PROCESOS - Obtiene cuántos pisos se requieren completar para cada proceso
        $procesosConfig = DB::table('proyecto_detalle')
            ->join('cambio_procesos_x_proyecto', function ($join) {
                $join->on('cambio_procesos_x_proyecto.proyecto_id', '=', 'proyecto_detalle.proyecto_id')
                    ->on('cambio_procesos_x_proyecto.proceso', '=', 'proyecto_detalle.procesos_proyectos_id');
            })
            ->where('proyecto_detalle.proyecto_id', $request->id)
            ->select(
                'proyecto_detalle.orden_proceso',
                'proyecto_detalle.procesos_proyectos_id',
                'proyecto_detalle.proyecto_id',
                'cambio_procesos_x_proyecto.numero as pisos_requeridos'
            )
            ->get()
            ->keyBy('orden_proceso');

        // 2. NOMBRES DE TORRES - Obtiene los nombres personalizados de las torres
        $torresConNombre = DB::table('nombre_xtore')
            ->where('proyecto_id', $request->id)
            ->pluck('nombre_torre', 'torre')
            ->toArray();

        // 3. DATOS DEL PROYECTO - Obtiene el estado actual de todos los apartamentos
        $proyectosDetalle = DB::connection('mysql')
            ->table('proyecto_detalle')
            ->leftJoin('users', 'proyecto_detalle.user_id', '=', 'users.id')
            ->leftJoin('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->where('proyecto_detalle.proyecto_id', $request->id)
            ->select(
                'proyecto_detalle.torre',
                'proyecto_detalle.id',
                'proyecto_detalle.validacion',
                'proyecto_detalle.estado_validacion',
                'proyecto_detalle.consecutivo',
                'proyecto_detalle.orden_proceso',
                'proyecto_detalle.piso',
                'proyecto_detalle.apartamento',
                'proyecto_detalle.text_validacion',
                'proyecto_detalle.estado',
                'procesos_proyectos.nombre_proceso',
                'users.nombre as nombre'
            )
            ->orderBy('proyecto_detalle.orden_proceso')
            ->orderBy('proyecto_detalle.piso')
            ->orderByRaw('CAST(proyecto_detalle.apartamento AS UNSIGNED)')
            ->get();


        $resultado = []; // Almacenará todos los datos estructurados
        $torreResumen = []; // Resumen por torre

        // 4. PROCESAR CADA REGISTRO - Organiza la información por torre, proceso, piso y apartamento
        foreach ($proyectosDetalle as $item) {
            $torre = $item->torre;
            $orden_proceso = $item->orden_proceso;
            $piso = $item->piso;

            // Inicializar estructuras si no existen
            if (!isset($resultado[$torre])) {
                $resultado[$torre] = [];
            }
            if (!isset($torreResumen[$torre])) {
                $torreResumen[$torre] = [
                    'nombre_torre' => $torresConNombre[$torre] ?? $torre,
                    'total_atraso' => 0,
                    'total_realizados' => 0,
                    'porcentaje_atraso' => 0,
                    'porcentaje_avance' => 0,
                    'serial_avance' => '0/0',
                    'pisos_unicos' => [] // Para contar pisos únicos
                ];
            }

            // Registrar pisos únicos por torre
            if (!in_array($piso, $torreResumen[$torre]['pisos_unicos'])) {
                $torreResumen[$torre]['pisos_unicos'][] = $piso;
            }

            // Inicializar proceso si no existe
            if (!isset($resultado[$torre][$orden_proceso])) {
                $resultado[$torre][$orden_proceso] = [
                    'nombre_proceso' => $item->nombre_proceso,
                    'text_validacion' => $item->text_validacion,
                    'estado_validacion' => $item->estado_validacion,
                    'validacion' => $item->validacion,
                    'pisos' => [],
                    'total_apartamentos' => 0,
                    'apartamentos_atraso' => 0,
                    'apartamentos_realizados' => 0,
                    'porcentaje_atraso' => 0,
                    'porcentaje_avance' => 0,
                    'pisos_completados' => 0,
                    'pisos_requeridos' => $procesosConfig[$orden_proceso]->pisos_requeridos ?? 0
                ];
            }

            // Inicializar piso si no existe
            if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
                $resultado[$torre][$orden_proceso]['pisos'][$piso] = [];
            }

            // // 5. DETERMINAR ESTADO BLANCO (EB) - Solo para procesos dependientes (no Fundida)
            $eb = false;
            if ($orden_proceso != 1 && $item->estado == 0) {
                $eb = $this->determinarEstadoBlanco(
                    $resultado,
                    $torre,
                    $orden_proceso,
                    $piso,
                    $item->apartamento,
                    $procesosConfig
                );
            }

            // 6. AGREGAR APARTAMENTO AL RESULTADO
            $resultado[$torre][$orden_proceso]['pisos'][$piso][] = [
                'id' => $item->id,
                'apartamento' => $item->apartamento,
                'consecutivo' => $item->consecutivo,
                'estado' => $item->estado,
                'eb' => $eb, // Estado Blanco (depende de procesos anteriores)
            ];

            // 7. ACTUALIZAR CONTADORES
            $this->actualizarContadores($resultado, $torreResumen, $torre, $orden_proceso, $item->estado);

            // 8. VERIFICAR SI TODO EL PISO ESTÁ COMPLETO
            $this->verificarPisoCompleto($resultado, $torre, $orden_proceso, $piso);
        }

        // 9. CALCULAR PORCENTAJES FINALES
        $this->calcularPorcentajes($resultado, $torreResumen);

        // 10. RETORNAR RESULTADO FINAL
        return response()->json([
            'status' => 'success',
            'data' => $resultado,
            'torreResumen' => $torreResumen
        ]);
    }

    /* ************************************************ESTADO EN BLANCO********************************************** */
    private function determinarEstadoBlanco($resultado, $torre, $orden_proceso, $piso, $apartamento, $procesosConfig)
    {
        $pisosRequeridos = $procesosConfig[$orden_proceso]->pisos_requeridos ?? 0;

        // 1. Definir de qué proceso depende el actual
        $dependencia = null;
        if (in_array($orden_proceso, [2, 3])) { // Destapada y Prolongación dependen de Fundida
            $dependencia = 1;
        } elseif ($orden_proceso == 4) { // Alambrada depende de Destapada y Prolongación
            $dependencia = 2;
        } elseif ($orden_proceso == 5) { // Aparateada depende de Alambrada
            $dependencia = 4;
        } elseif ($orden_proceso == 6) { // Aparateada Fase 2 depende de Aparateada
            $dependencia = 5;
        } elseif ($orden_proceso == 7) { // Pruebas depende de Aparateada o Aparateada Fase 2
            $dependencia = isset($resultado[$torre][6]) ? 6 : 5;
        } elseif (in_array($orden_proceso, [8, 9])) { // Retie y Ritel dependen de Pruebas
            $dependencia = 7;
        } elseif ($orden_proceso == 10) { // Entrega depende de Retie y Ritel
            $depRetie = $this->verificarApartamentoCompletoEnProceso($resultado, $torre, 8, $piso, $apartamento);
            $depRitel = $this->verificarApartamentoCompletoEnProceso($resultado, $torre, 9, $piso, $apartamento);
            return $depRetie && $depRitel;
        }

        if (!$dependencia) {
            return false;
        }

        // 2. Contar pisos del proceso que depende en estado 1, 2 o EB
        $pisosConAvance = 0;
        $pisosMinimos = $pisosRequeridos;

        $dependencias = is_array($dependencia) ? $dependencia : [$dependencia];
        foreach ($dependencias as $dep) {
            if (!isset($resultado[$torre][$dep]['pisos'])) continue;

            foreach ($resultado[$torre][$dep]['pisos'] as $pisoDep => $apartamentos) {
                $completo = true;
                foreach ($apartamentos as $apt) {
                    if (!in_array($apt['estado'], [1, 2]) && !$apt['eb']) {
                        $completo = false;
                        break;
                    }
                }
                if ($completo) {
                    $pisosConAvance++;
                }
            }
        }

        // 3. Calcular cuántos pisos deben estar habilitados
        $resultadoCalc = ($pisosConAvance - $pisosMinimos) + 1;

        // 4. Verificar si el piso actual está dentro de los que deben habilitarse
        if ($piso <= $resultadoCalc) {
            return true; // marcar como EB si el estado es 0
        }

        return false;
    }

    //Verifica si un apartamento específico está completo (estado=2) en un proceso
    private function verificarApartamentoCompletoEnProceso($resultado, $torre, $ordenProceso, $piso, $apartamento)
    {
        if (!isset($resultado[$torre][$ordenProceso]['pisos'][$piso])) {
            return false;
        }

        foreach ($resultado[$torre][$ordenProceso]['pisos'][$piso] as $apt) {
            if ($apt['apartamento'] == $apartamento) {
                return $apt['estado'] == 2; // 2 = Completado
            }
        }

        return false;
    }

    //Actualiza los contadores de realizados y atrasos
    private function actualizarContadores(&$resultado, &$torreResumen, $torre, $orden_proceso, $estado)
    {
        $resultado[$torre][$orden_proceso]['total_apartamentos']++;

        if ($estado == 1) { // 1 = Atraso
            $resultado[$torre][$orden_proceso]['apartamentos_atraso']++;
            if ($orden_proceso != 1) { // No contar Fundida en resumen general
                $torreResumen[$torre]['total_atraso']++;
            }
        } elseif ($estado == 2) { // 2 = Completado
            $resultado[$torre][$orden_proceso]['apartamentos_realizados']++;
            if ($orden_proceso != 1) { // No contar Fundida en resumen general
                $torreResumen[$torre]['total_realizados']++;
            }
        }
    }

    //Verifica si todo un piso está completo y actualiza el contador
    private function verificarPisoCompleto(&$resultado, $torre, $orden_proceso, $piso)
    {
        if (!isset($resultado[$torre][$orden_proceso]['pisos'][$piso])) {
            return;
        }

        $completo = true;
        foreach ($resultado[$torre][$orden_proceso]['pisos'][$piso] as $apt) {
            if ($apt['estado'] != 2) { // 2 = Completado
                $completo = false;
                break;
            }
        }

        if ($completo) {
            $resultado[$torre][$orden_proceso]['pisos_completados']++;
        }
    }

    //Calcula porcentajes de avance y atraso para procesos y torres
    private function calcularPorcentajes(&$resultado, &$torreResumen)
    {
        // Porcentajes por proceso
        foreach ($resultado as $torre => &$procesos) {
            foreach ($procesos as $orden_proceso => &$proceso) {
                if ($orden_proceso === 'nombre_torre') continue;

                // Proceso Fundida (1) no lleva porcentajes
                if ($orden_proceso == 1) {
                    $proceso['porcentaje_atraso'] = 0;
                    $proceso['porcentaje_avance'] = 0;
                    continue;
                }

                $total_atraso = $proceso['apartamentos_atraso'];
                $total_realizados = $proceso['apartamentos_realizados'];
                $denominador = $total_atraso + $total_realizados;

                // % Atraso = (Atrasos / Total iniciados) * 100
                $proceso['porcentaje_atraso'] = $denominador > 0 ? round(($total_atraso / $denominador) * 100, 2) : 0;

                // % Avance = (Realizados / Total apartamentos) * 100
                $proceso['porcentaje_avance'] = $proceso['total_apartamentos'] > 0 ?
                    round(($total_realizados / $proceso['total_apartamentos']) * 100, 2) : 0;
            }
        }

        // Porcentajes por torre
        foreach ($torreResumen as $torre => &$datos) {
            $total_atraso = $datos['total_atraso'];
            $total_realizados = $datos['total_realizados'];
            $denominador = $total_atraso + $total_realizados;

            $datos['porcentaje_atraso'] = $denominador > 0 ? round(($total_atraso / $denominador) * 100, 2) : 0;
            $datos['porcentaje_avance'] = $denominador > 0 ? round(($total_realizados / $denominador) * 100, 2) : 0;
            $datos['serial_avance'] = $total_realizados . '/' . $denominador;
            $datos['total_pisos'] = count($datos['pisos_unicos']);

            unset($datos['pisos_unicos']); // Eliminar campo auxiliar
        }
    }

    /* ************************************************ESTADO EN BLANCO FIN********************************************** */


    public function destroy($id)
    {
        $iniciarProyecto = Proyectos::find($id);

        $iniciarProyecto->fecha_ini_proyecto = now();
        $iniciarProyecto->update();
    }

    public function IniciarTorre(Request $request)
    {
        // Validar los datos de entrada
        $validated = $request->validate([
            'proyecto' => 'required|exists:proyecto,id',
            'torre' => 'required'
        ]);

        $proyectoId = $validated['proyecto'];
        $torre = $validated['torre'];

        DB::beginTransaction();
        try {
            // 1. Actualizar todos los apartamentos del piso 1, proceso 1 para esta torre
            $updated = DB::table('proyecto_detalle')
                ->where('proyecto_id', $proyectoId)
                ->where('torre', $torre)
                ->where('piso', '1')
                ->where('orden_proceso', 1)
                ->update([
                    'estado' => '1',
                    'fecha_habilitado' => now(),
                    'fecha_ini_torre' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Torre iniciada correctamente',
                'data' => [
                    'proyecto_id' => $proyectoId,
                    'torre' => $torre,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar la torre: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function infoProyecto($id)
    {
        $info = Proyectos::find($id);

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    public function InformeDetalladoProyectos($id)
    {
        $proyectoId = $id;

        if (!$proyectoId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de proyecto no proporcionado.',
            ], 400);
        }

        // Obtener el listado de nombres de torre por código
        $torresConNombre = DB::table('nombre_xtore')
            ->where('proyecto_id', $proyectoId)
            ->pluck('nombre_torre', 'torre') // [codigo => nombre]
            ->toArray();

        // Obtener todos los detalles del proyecto incluyendo torre y proceso
        $detalles = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->select(
                'proyecto_detalle.torre',
                'proyecto_detalle.estado',
                'procesos_proyectos.nombre_proceso as proceso'
            )
            ->where('proyecto_detalle.proyecto_id', $proyectoId)
            ->get();

        if ($detalles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron detalles para el proyecto.',
            ], 404);
        }

        // Agrupar por proceso
        $procesos = $detalles->groupBy('proceso');

        // Obtener lista de torres con código y nombre
        $torres = $detalles->pluck('torre')->unique()->sort()->values()->map(function ($codigoTorre) use ($torresConNombre) {
            return [
                'codigo' => $codigoTorre,
                'nombre' => $torresConNombre[$codigoTorre] ?? "Torre {$codigoTorre}"
            ];
        });

        $resultado = [];

        foreach ($procesos as $proceso => $itemsProceso) {
            $fila = ['proceso' => $proceso];
            $totalGlobal = 0;
            $terminadosGlobal = 0;

            foreach ($torres as $torre) {
                $codigo = $torre['codigo'];
                $nombre = $torre['nombre'];

                $filtrados = $itemsProceso->where('torre', $codigo);
                $total = $filtrados->count();
                $terminados = $filtrados->where('estado', 2)->count();

                $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 2) : 0;
                $fila[$nombre] = "{$terminados}/{$total} ({$porcentaje}%)";

                $totalGlobal += $total;
                $terminadosGlobal += $terminados;
            }

            // Agregar total general por proceso
            $porcentajeGlobal = $totalGlobal > 0 ? round(($terminadosGlobal / $totalGlobal) * 100, 2) : 0;
            $fila["total"] = "{$terminadosGlobal}/{$totalGlobal} ({$porcentajeGlobal}%)";

            $resultado[] = $fila;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'torres' => $torres->pluck('nombre'),
                'reporte' => $resultado,
                'proyecto_id' => $proyectoId
            ]
        ]);
    }

    // cambio estado de apartamentos confirado por erro-mentira
    public function CambioEstadosApt(Request $request)
    {
        DB::beginTransaction();

        try {
            $info = ProyectosDetalle::findOrFail($request->aptId);

            // Obtener todos los APT con estado 2 que coincidan en proyecto, torre y consecutivo
            $aptosRelacionados = ProyectosDetalle::where('proyecto_id', $info->proyecto_id)
                ->where('torre', $info->torre)
                ->where('consecutivo', $info->consecutivo)
                ->where('estado', 2)
                ->where('orden_proceso', '>=', $info->orden_proceso)
                ->get();

            // Cambiar estado a 1 y limpiar campos
            $idsAfectados = [];
            foreach ($aptosRelacionados as $apt) {
                $apt->estado = 1;
                $apt->fecha_habilitado = now();
                $apt->fecha_fin = null;
                $apt->user_id = null;
                $apt->update();

                $idsAfectados[] = $apt->id;
            }

            // Guardar log de anulación
            $LogCambioEstadoApt = new AnulacionApt();
            $LogCambioEstadoApt->motivo = $request->detalle;
            $LogCambioEstadoApt->piso = (int) $info->piso;
            $LogCambioEstadoApt->apt = $request->aptId;
            $LogCambioEstadoApt->fecha_confirmo = $info->fecha_fin;
            $LogCambioEstadoApt->userConfirmo_id = $info->user_id;
            $LogCambioEstadoApt->user_id = Auth::id();
            $LogCambioEstadoApt->proyecto_id = $info->proyecto_id;
            $LogCambioEstadoApt->apt_afectados = json_encode($idsAfectados); // <<< aquí guardamos los IDs afectados
            $LogCambioEstadoApt->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $aptosRelacionados // devolvemos todos los afectados, no solo uno
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar apartamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ExportInformeExcelProyecto($id)
    {
        $proyectoId = $id;

        if (!$proyectoId) {
            return response()->json([
                'success' => false,
                'message' => 'ID de proyecto no proporcionado.',
            ], 400);
        }

        $torresConNombre = DB::table('nombre_xtore')
            ->where('proyecto_id', $proyectoId)
            ->pluck('nombre_torre', 'torre')
            ->toArray();

        $detalles = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->select(
                'proyecto_detalle.torre',
                'proyecto_detalle.estado',
                'procesos_proyectos.nombre_proceso as proceso'
            )
            ->where('proyecto_detalle.proyecto_id', $proyectoId)
            ->get();

        if ($detalles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron detalles para el proyecto.',
            ], 404);
        }

        $procesos = $detalles->groupBy('proceso');
        $torres = $detalles->pluck('torre')->unique()->sort()->values()->map(function ($codigoTorre) use ($torresConNombre) {
            return [
                'codigo' => $codigoTorre,
                'nombre' => $torresConNombre[$codigoTorre] ?? "Torre {$codigoTorre}"
            ];
        });

        $resultado = [];

        foreach ($procesos as $proceso => $itemsProceso) {
            $fila = ['Proceso' => $proceso];
            $totalGlobal = 0;
            $terminadosGlobal = 0;

            foreach ($torres as $torre) {
                $codigo = $torre['codigo'];
                $nombre = $torre['nombre'];

                $filtrados = $itemsProceso->where('torre', $codigo);
                $total = $filtrados->count();
                $terminados = $filtrados->where('estado', 2)->count();

                $porcentaje = $total > 0 ? round(($terminados / $total) * 100, 2) : 0;
                $fila[$nombre] = "{$terminados}/{$total} ({$porcentaje}%)";

                $totalGlobal += $total;
                $terminadosGlobal += $terminados;
            }

            $porcentajeGlobal = $totalGlobal > 0 ? round(($terminadosGlobal / $totalGlobal) * 100, 2) : 0;
            $fila["Total"] = "{$terminadosGlobal}/{$totalGlobal} ({$porcentajeGlobal}%)";

            $resultado[] = $fila;
        }

        return Excel::download(new InformeProyectoExport($resultado), 'informe-proyecto.xlsx');
    }

    //----------------------------------------------------------------------- nuevo toque minimos piso

    public function confirmarAptNuevaLogica($id)
    {
        DB::beginTransaction();

        try {
            $info = ProyectosDetalle::findOrFail($id);

            $torre = $info->torre;
            $orden_proceso = (int) $info->orden_proceso;
            $piso = (int) $info->piso;

            $proyecto = Proyectos::findOrFail($info->proyecto_id);
            $TipoProceso = strtolower(ProcesosProyectos::where('id', $info->procesos_proyectos_id)->value('nombre_proceso'));

            if ($TipoProceso === "fundida" || $TipoProceso === "prolongacion" || $TipoProceso === "destapada" || $TipoProceso === "ritel" || $TipoProceso === "retie") {
                // Confirmar el apartamento sin reglas si es uno de estos procesos
                $info->estado = 2;
                $info->fecha_fin = now();
                $info->user_id = Auth::id();
                $info->save();
            } else {
                // Verificar si el mismo apartamento esta confirmado en el proceso anterior
                $verPisoAnteriorConfirmado = ProyectosDetalle::where('torre', $torre)
                    ->where('orden_proceso', $orden_proceso - 1)
                    ->where('proyecto_id', $proyecto->id)
                    ->where('consecutivo', $info->consecutivo)
                    ->where('piso', $piso)
                    ->first();


                // Confirmar el apartamento si el mismo apartamento esta confirmado en proceso anterior estado=2
                if ($verPisoAnteriorConfirmado->estado == "2") {
                    $info->estado = 2;
                    $info->fecha_fin = now();
                    $info->user_id = Auth::id();
                    $info->save();
                } else {
                    // si no esta confirmado no se confirma, se envia mensaje de error
                    $procesoError = strtolower(ProcesosProyectos::where('id', $verPisoAnteriorConfirmado->procesos_proyectos_id)->value('nombre_proceso'));

                    return response()->json([
                        'success' => false,
                        'message' => "El apartamento no puede ser confirmado por que el apartamento en proceso '{$procesoError}' no esta confirmado"
                    ], 400);
                }
            }

            $info->estado = 2;
            $info->fecha_fin = now();
            $info->user_id = Auth::id();
            $info->save();

            switch ($TipoProceso) {
                case 'fundida':
                    $this->confirmarFundida($proyecto, $torre, $orden_proceso, $piso);
                    break;
                case 'destapada':
                case 'prolongacion':
                    $this->intentarHabilitarAlambrada($info, $proyecto);
                    break;

                case 'alambrada':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'alambrada', 'aparateada');
                    break;
                case 'aparateada':
                    $fase2 = DB::table('proyecto_detalle')
                        ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                        ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', ['aparateada fase 2'])
                        ->where('proyecto_detalle.torre', $torre)
                        ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                        ->exists();

                    $siguienteProceso = $fase2 ? 'aparateada fase 2' : 'pruebas';

                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada', $siguienteProceso);
                    break;

                case 'aparateada fase 2':
                    $this->validarYHabilitarPorPiso($proyecto, $torre, $piso, 'aparateada fase 2', 'pruebas');
                    break;
                case 'pruebas':
                    $this->confirmarPruebas($proyecto, $torre, $orden_proceso, $piso);
                    break;

                case 'retie':
                case 'ritel':
                    $this->intentarHabilitarEntrega($info); // esta función no habilita entrega directamente, solo revisa
                    break;

                case 'entrega':
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'data' => 'ERROR, PROCESO NO EXISTENTE, COMUNICATE CON TI'
                    ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar apartamento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function confirmarFundida($proyecto, $torre, $orden_proceso, $piso)
    {
        // Revisar si todo el piso de fundida esta completo
        $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

        if ($confirmarInicioProceso) {
            // Habilitar siguiente piso fundida
            ProyectosDetalle::where('torre', $torre)
                ->where('orden_proceso', $orden_proceso)
                ->where('piso', $piso + 1)
                ->where('proyecto_id', $proyecto->id)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);

            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'destapada');
            $this->validarYHabilitarProceso($proyecto, $torre, $piso, 'prolongacion');
        }
    }

    private function validarYHabilitarProceso($proyecto, $torre, $piso, $procesoNombre)
    {
        //Buscamos los pisos minimo por proceso para poder activar este proceso
        $CambioProceso = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->select('cambio_procesos_x_proyecto.*')
            ->first();

        $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

        //validamos si ya el proceso esta iniciado, es decir que tenga en piso 1 un estado diferente a 0
        $inicioProceso = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('proyecto_detalle.torre', $torre)
            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
            ->where('proyecto_detalle.piso', 1)
            ->get();

        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

        //se compara, si tiene estado diferente a 0 entra en el if
        if ($yaIniciado) {

            $nuevoPiso = $piso - ($pisosRequeridos - 1);

            $verValidacion = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', $nuevoPiso)
                ->select('proyecto_detalle.*')
                ->get();

            $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

            if ($todosPendientes) {
                return; // espera validación externa
            }

            ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        } elseif ($piso >= $pisosRequeridos) {

            // Aún no iniciado, inicia en piso 1
            $detalle = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->select('proyecto_detalle.*')
                ->first();

            if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
                return; // espera validación externa
            }


            DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', 1)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        }
    }

    private function intentarHabilitarAlambrada($info, $proyecto)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;
        $aptMinimos = $proyecto->minimoApt;

        $procesos = ['destapada', 'prolongacion'];
        $aptosConfirmados = [];

        // 1. Buscar los consecutivos con estado == 2 de cada proceso
        foreach ($procesos as $proceso) {
            $aptosConfirmados[$proceso] = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
                )
                ->where('estado', 2)
                ->pluck('consecutivo') // consecutivo de apto
                ->toArray();
        }

        // 2. Intersección: solo los que están confirmados en ambos procesos
        $aptosValidos = array_intersect(
            $aptosConfirmados['destapada'] ?? [],
            $aptosConfirmados['prolongacion'] ?? []
        );

        // 3. Verificar si ya se cumplió al menos una vez el mínimo
        $alambradaHabilitados = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 1)
            ->pluck('consecutivo')
            ->toArray();

        // Si aún no se ha habilitado nada en alambrada (primera vez)
        if (empty($alambradaHabilitados)) {
            // Validar mínimo
            if (count($aptosValidos) < $aptMinimos) {
                return; // No cumple mínimo, aún no habilitamos
            }

            // Habilitar todos los aptos válidos en alambrada (fase inicial)
            $this->habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $aptosValidos);
        } else {
            // Fase 2: habilitar uno a uno los que no estén ya habilitados en alambrada
            $nuevosAptos = array_diff($aptosValidos, $alambradaHabilitados);

            if (!empty($nuevosAptos)) {
                $this->habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $nuevosAptos);
            }
        }
    }
    //Función auxiliar para habilitar consecutivos en alambrada
    private function habilitarAptosEnAlambrada($torre, $proyectoId, $piso, $consecutivos)
    {
        // Obtener los aptos que coinciden y están en estado 0
        $validacion = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->get();

        // Si alguno requiere validación externa, detener
        if ($validacion->contains(
            fn($item) =>
            $item->validacion == 1 && $item->estado_validacion == 0
        )) {
            return response()->json([
                'success' => true,
                'message' => 'espera validación externa',
            ], 200);
        }

        // Habilitar
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['alambrada'])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    private function validarYHabilitarPorPiso($proyecto, $torre, $piso, $procesoOrigen, $procesoDestino)
    {
        $aptMinimos = $proyecto->minimoApt;

        // 1. Consecutivos confirmados del procesoOrigen en el piso enviado
        $aptosValidos = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
            )
            ->where('estado', 2)
            ->pluck('consecutivo')
            ->toArray();

        if (empty($aptosValidos)) {
            return; // Nada confirmado aún
        }

        // 2. Obtener el mínimo de pisos requeridos
        $minimosPisos = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'cambio_procesos_x_proyecto.proceso', '=', 'procesos_proyectos.id')
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoDestino])
            ->value('numero');

        $minimosPisos = $minimosPisos ? (int)$minimosPisos : 0;

        // 3. Validar si el procesoDestino ya se inició (piso 1 con estado != 0)
        $inicioProceso = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', 1)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->get();

        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->contains(fn($apt) => $apt->estado != 0);


        // ========================= FASE 2: YA INICIADO =========================
        if ($yaIniciado) {
            $nuevoPiso = $piso - ($minimosPisos - 1);
            // Pendientes en el piso del procesoDestino
            $pendientesDestino = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
                )
                ->where('estado', 0)
                ->pluck('consecutivo')
                ->toArray();

            // --------> Transformación de consecutivos
            $pisoActualizado = $piso - $nuevoPiso;
            $aptosTransformados = array_map(function ($apt) use ($pisoActualizado) {
                return (int)$apt - ($pisoActualizado * 100);
            }, $aptosValidos);

            // Intersección con los pendientes en destino
            $aptosHabilitar = array_intersect($aptosTransformados, $pendientesDestino);

            if (empty($aptosHabilitar)) {
                return;
            }

            // Aptos habilitados actualmente en el destino
            $habilitadosDestino = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
                )
                ->where('estado', 1)
                ->pluck('consecutivo')
                ->toArray();

            /**
             * Si el total habilitado (actuales + los nuevos por habilitar) aún es menor que $aptMinimos,
             * habilitamos TODOS los aptos de este bloque.
             */
            if ((count($habilitadosDestino) + count($aptosHabilitar)) < $aptMinimos) {
                /**
                 * Aún no se cumple el mínimo: NO habilitar nada.
                 */
                return;
            } elseif ((count($habilitadosDestino) + count($aptosHabilitar)) == $aptMinimos) {
                /**
                 * Exactamente se cumple el mínimo con estos aptos: habilitarlos todos.
                 */
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoDestino, $aptosHabilitar);
            } else {
                /**
                 * Ya se cumplió el mínimo previamente. Habilitar de a uno.
                 */
                $nuevo = reset($aptosHabilitar);
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoDestino, [$nuevo]);
            }


            // ========================= FASE 1: NO INICIADO =========================
        } elseif ($piso >= $minimosPisos) {

            // Contar cuántos pisos cumplen mínimo aptos
            $pisosCumplen = ProyectosDetalle::select('piso')
                ->where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
                )
                ->where('estado', 2)
                ->groupBy('piso')
                ->havingRaw('COUNT(*) >= ?', [$aptMinimos])
                ->pluck('piso')
                ->toArray();

            // Si aún no cumplen los pisos mínimos → no hacer nada
            if (count($pisosCumplen) < $minimosPisos) {
                return;
            }

            // Habilitar en piso 1 los aptos confirmados en el piso 1 del procesoOrigen
            $aptosPiso1Origen = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', 1)
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoOrigen])
                )
                ->where('estado', 2)
                ->pluck('consecutivo')
                ->toArray();


            $this->habilitarPorConsecutivos($proyecto, $torre, 1, $procesoDestino, $aptosPiso1Origen);
        }
    }

    //Función auxiliar para habilitar consecutivos en el procesoDestino
    private function habilitarPorConsecutivos($proyecto, $torre, $piso, $procesoDestino, $consecutivos)
    {
        if (empty($consecutivos)) return;

        $verValidacion = ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->get();

        $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

        if ($todosPendientes) {
            return; // espera validación externa
        }



        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->whereIn('consecutivo', $consecutivos)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoDestino])
            )
            ->where('estado', 0)
            ->update(['estado' => 1, 'fecha_habilitado' => now()]);
    }

    private function intentarHabilitarEntrega($info)
    {
        $torre = $info->torre;
        $proyectoId = $info->proyecto_id;
        $piso = $info->piso;
        $consecutivo = $info->consecutivo;

        $procesos = ['retie', 'ritel'];

        foreach ($procesos as $proceso) {
            // Solo buscar el apartamento 1 en cada proceso
            $apto = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyectoId)
                ->where('piso', $piso)
                ->where('consecutivo', $consecutivo) // <-- Solo el apto 1
                ->whereHas(
                    'proceso',
                    fn($q) =>
                    $q->whereRaw('LOWER(nombre_proceso) = ?', [$proceso])
                )
                ->first();

            // Si no existe el apto 1 o no está en estado 2, no habilitar entrega
            if (!$apto || $apto->estado != 2) {
                return;
            }
        }

        // Si ambos procesos para el apto 1 están completos, habilitar entrega
        ProyectosDetalle::where('torre', $torre)
            ->where('proyecto_id', $proyectoId)
            ->where('piso', $piso)
            ->where('consecutivo', $consecutivo)
            ->whereHas(
                'proceso',
                fn($q) =>
                $q->whereRaw('LOWER(nombre_proceso) = ?', ['entrega'])
            )
            ->where('estado', 0)
            ->update([
                'estado' => 1,
                'fecha_habilitado' => now()
            ]);
    }

    private function confirmarPruebas($proyecto, $torre, $orden_proceso, $piso)
    {

        // Confirmar todo el piso pruebas
        $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        $confirmarInicioProceso = $aptosDelPiso->isNotEmpty() && $aptosDelPiso->every(fn($apt) => $apt->estado == 2);

        if ($confirmarInicioProceso) {
            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'retie');
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'ritel');
        }
    }

    private function validarYHabilitarRetieYRitel($proyecto, $torre, $piso, $procesoNombre)
    {
        //se buscan los pisos minimos para activar este proceso
        $CambioProceso = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->select('cambio_procesos_x_proyecto.*')
            ->first();

        $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

        $inicioProceso = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('proyecto_detalle.torre', $torre)
            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
            ->where('proyecto_detalle.piso', 1)
            ->get();


        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->every(fn($apt) => $apt->estado != 0);

        if ($yaIniciado) {

            $nuevoPiso = $piso - ($pisosRequeridos - 1);

            $verValidacion = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', $nuevoPiso)
                ->select('proyecto_detalle.*')
                ->get();


            $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

            if ($todosPendientes) {
                return; // espera validación externa
            }

            ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        } elseif ($piso >= $pisosRequeridos) {

            // Aún no iniciado, inicia en piso 1
            $detalle = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->select('proyecto_detalle.*')
                ->first();

            if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
                return; // espera validación externa
            }


            DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', 1)
                ->where('estado', 0)
                ->update(['estado' => 1, 'fecha_habilitado' => now()]);
        }
    }


    //futuro en caso tal retie y ritel 
/*     private function confirmarPruebas($proyecto, $torre, $orden_proceso, $piso)
    {
        $aptMinimos = $proyecto->minimoApt;

        // Obtener todos los aptos del piso para este proceso
        $aptosDelPiso = ProyectosDetalle::where('torre', $torre)
            ->where('orden_proceso', $orden_proceso)
            ->where('proyecto_id', $proyecto->id)
            ->where('piso', $piso)
            ->get();

        // Filtrar solo los que están confirmados (estado == 2)
        $confirmados = $aptosDelPiso->filter(fn($apt) => $apt->estado == 2);

        // Validar si al menos se cumple el mínimo requerido
        $confirmarInicioProceso = $confirmados->count() >= $aptMinimos;

        if ($confirmarInicioProceso) {
            // Validar y habilitar procesos dependientes
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'retie');
            $this->validarYHabilitarRetieYRitel($proyecto, $torre, $piso, 'ritel');
        }
    }


    private function validarYHabilitarRetieYRitel($proyecto, $torre, $piso, $procesoNombre)
    {
        $aptMinimos = $proyecto->minimoApt;

        // Obtener pisos requeridos
        $CambioProceso = DB::table('cambio_procesos_x_proyecto')
            ->join('procesos_proyectos', 'procesos_proyectos.id', '=', 'cambio_procesos_x_proyecto.proceso')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('cambio_procesos_x_proyecto.proyecto_id', $proyecto->id)
            ->select('cambio_procesos_x_proyecto.*')
            ->first();



        $pisosRequeridos = $CambioProceso ? (int) $CambioProceso->numero : 0;

        // Verificar si el proceso ya inició en piso 1
        $inicioProceso = DB::table('proyecto_detalle')
            ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
            ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
            ->where('proyecto_detalle.torre', $torre)
            ->where('proyecto_detalle.proyecto_id', $proyecto->id)
            ->where('proyecto_detalle.piso', 1)
            ->get();

        $yaIniciado = $inicioProceso->isNotEmpty() && $inicioProceso->contains(fn($apt) => $apt->estado != 0);


        if ($yaIniciado) {
            $nuevoPiso = $piso - ($pisosRequeridos - 1);

            $verValidacion = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.orden_proceso', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', $nuevoPiso)
                ->select('proyecto_detalle.*')
                ->get();

            $todosPendientes = $verValidacion->every(fn($apt) => $apt->validacion == 1 && $apt->estado_validacion == 0);

            if ($todosPendientes) {
                return;
            }

            // Aplicar lógica de mínimos para habilitar
            $aptosConfirmados = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $piso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 2)
                ->pluck('consecutivo')
                ->toArray();

            $pendientesDestino = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 0)
                ->pluck('consecutivo')
                ->toArray();

            $pisoActualizado = $piso - $nuevoPiso;
            $transformados = array_map(fn($apt) => (int)$apt - ($pisoActualizado * 100), $aptosConfirmados);

            $aptosHabilitar = array_intersect($transformados, $pendientesDestino);

            $yaHabilitados = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', $nuevoPiso)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 1)
                ->pluck('consecutivo')
                ->toArray();

            if ((count($yaHabilitados) + count($aptosHabilitar)) < $aptMinimos) {
                return;
            } elseif ((count($yaHabilitados) + count($aptosHabilitar)) == $aptMinimos) {
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoNombre, $aptosHabilitar);
            } else {
                $uno = reset($aptosHabilitar);
                $this->habilitarPorConsecutivos($proyecto, $torre, $nuevoPiso, $procesoNombre, [$uno]);
            }
        } elseif ($piso >= $pisosRequeridos) {
            // Verifica que se cumplan pisos suficientes con mínimo de aptos confirmados
            $pisosCumplen = ProyectosDetalle::select('piso')
                ->where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', ["pruebas"]))
                ->where('estado', 2)
                ->groupBy('piso')
                ->havingRaw('COUNT(*) >= ?', [$aptMinimos])
                ->pluck('piso')
                ->toArray();






            if (count($pisosCumplen) < $pisosRequeridos) {

                return;
            }

            // Validación externa antes de habilitar piso 1
            $detalle = DB::table('proyecto_detalle')
                ->join('procesos_proyectos', 'proyecto_detalle.procesos_proyectos_id', '=', 'procesos_proyectos.id')
                ->whereRaw('LOWER(procesos_proyectos.nombre_proceso) = ?', [$procesoNombre])
                ->where('proyecto_detalle.torre', $torre)
                ->where('proyecto_detalle.proyecto_id', $proyecto->id)
                ->where('proyecto_detalle.piso', 1)
                ->select('proyecto_detalle.*')
                ->first();

            if ($detalle && $detalle->validacion == 1 && $detalle->estado_validacion == 0) {
                return;
            }



            // Aptos confirmados del procesoNombre en piso 1
            $aptosConfirmados = ProyectosDetalle::where('torre', $torre)
                ->where('proyecto_id', $proyecto->id)
                ->where('piso', 1)
                ->whereHas('proceso', fn($q) => $q->whereRaw('LOWER(nombre_proceso) = ?', [$procesoNombre]))
                ->where('estado', 2)
                ->pluck('consecutivo')
                ->toArray();

            $this->habilitarPorConsecutivos($proyecto, $torre, 1, $procesoNombre, $aptosConfirmados);
        }
    } */
}
