<?php

namespace App\Http\Controllers\Api\Documentos;

use App\Http\Controllers\Controller;
use App\Models\ActividadesOrganismos;
use App\Models\Documentos;
use App\Models\DocumentosAdjuntos;
use App\Models\DocumentosOrganismos;
use App\Models\DocumentosOrganismosAdjuntos;
use App\Models\ProyectoCasa;
use App\Models\Proyectos;
use App\Models\TMorganismos;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\TryCatch;

class DocumentosController extends Controller
{

    //CREAR DOCUMENTACION (INICIO)
    //documentacion

    public function StoreDocumentacionRed(Request $request)
    {


        //ENVIAR DATOS PARA CREAR DOCUMENTOS DE ORGANISMO
        if ($request->requiereOrganismos == "1") {
            $this->documentosOrganismos($request);
            $this->CreartTm($request);
        }

        // Buscar primero en Proyectos
        $datosBusqueda = Proyectos::where('codigo_proyecto', $request->codigo_proyecto)->first();

        // Si no se encuentra en Proyectos, buscar en ProyectoCasa
        if (!$datosBusqueda) {
            $datosBusqueda = ProyectoCasa::where('codigo_proyecto', $request->codigo_proyecto)->first();
        }

        // Validar si se encontró algún dato
        if (!$datosBusqueda) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontró el proyecto con el código: ' . $request->codigo_proyecto
            ], 404);
        }

        $datosUnicos = $datosBusqueda;
        $datos = $request;

        //datos unicos del proyecto
        $codigo_proyecto = $datos->codigo_proyecto;
        $codigo_proyecto_documentos = $datos->codigoDocumentos;
        $etapa = $datos->etapaProyecto != 1 ? 2 : $datos->etapaProyecto;
        $etapaData = $datos->etapaProyecto;

        //datos de operadores para plantilla
        $operador = $datos->operadorRed;
        $organismo = $datos->organismoInspeccion;
        $fecha_entrega = $datos->fechaEntrega;
        $nombre_etapa = $datos->nombre_etapa;

        //tipo de proyecto en caso que casas sea distinto
        $tipoProyecto_id = $datosUnicos->tipoProyecto_id;

        //se valdia si ya hay docuemnto con este proyecto tiene la misma etapa
        $validarEtpa = Documentos::where('codigo_proyecto', $request->codigo_proyecto)->where('etapa', $etapaData)->first();

        // Validar si se encontró algún dato
        if ($validarEtpa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este proyecto ya tiene una etapa ' . $etapaData
            ], 404);
        }

        $cronogramaGenerado = null;

        // //ENVIAR DATOS PARA CREAR DOCUMENTOS DE ORGANISMO
        // if ($request->requiereOrganismos == "1") {
        //     $this->documentosOrganismos($request->organismoInspeccion);
        // }


        //OPERADORES DE RED
        //APARTAMENTOS
        // if ($tipoProyecto_id == 1) {
        if ($etapa == '1') {
            if ($operador == "1") { //emcali
                $cronogramaGenerado = $this->generarCronogramaDesdeBD($codigo_proyecto, $codigo_proyecto_documentos, $etapa, $fecha_entrega, $operador, $tipoProyecto_id, $nombre_etapa, $etapaData);
            } else if ($operador == "2") { //celsia
                // Para Celsia
                $cronogramaGenerado = $this->generarCronogramaDesdeBD($codigo_proyecto, $codigo_proyecto_documentos, 1, $fecha_entrega, $operador, $tipoProyecto_id, $nombre_etapa, $etapaData);
            }
        } else {
            //aqui cual quier etapa >1
            if ($operador == "1") { //emcali
                $cronogramaGenerado = $this->generarCronogramaDesdeBD($codigo_proyecto, $codigo_proyecto_documentos, $etapa, $fecha_entrega, $operador, $tipoProyecto_id, $nombre_etapa, $etapaData);
            } else if ($operador == "2") { //celsia
                // Para Celsia
                $cronogramaGenerado = $this->generarCronogramaDesdeBD($codigo_proyecto, $codigo_proyecto_documentos, 1, $fecha_entrega, $operador, $tipoProyecto_id, $nombre_etapa, $etapaData);
            }
        }
        // } else if ($tipoProyecto_id == 2) {
        //     //CASAS - Implementar lógica para casas si es necesario
        //     $cronogramaGenerado = [];
        // }

        // ✅ AGREGAR ESTE RETURN AL FINAL - Respuesta de éxito
        return response()->json([
            'status' => 'success',
            'message' => 'Documentación creada exitosamente',
            'data' => [
                'proyecto' => $codigo_proyecto,
                'codigo_documento' => $codigo_proyecto_documentos,
                'etapa' => $etapaData,
                'operador' => $operador,
                'cronograma_generado' => $cronogramaGenerado ? count($cronogramaGenerado) . ' actividades' : 'No se generó cronograma',
                'fecha_entrega' => $fecha_entrega,
                'nombre_etapa' => $nombre_etapa
            ]
        ], 201);
    }

    private function generarCronogramaDesdeBD($codigo_proyecto, $codigo_proyecto_documentos, $etapa, $fecha_entrega, $operador, $tipoProyecto_id, $nombre_etapa, $etapaData)
    {
        // Consultar actividades desde la base de datos
        $actividades = DB::table('actividades_documentos')
            ->where('etapa', $etapa)
            ->where('operador', $operador)
            ->where('estado', 1)
            ->orderBy('id')
            ->get();

        if ($actividades->isEmpty()) {
            return;
            return response()->json([
                'status' => 'error',
                'message' => 'No se encontraron actividades para etapa: ' .  $etapa . ' operador: ' . $operador
            ], 404);
        }

        // Convertir fecha_entrega a Carbon - VERSIÓN COMPLETAMENTE CORREGIDA
        try {

            if (is_object($fecha_entrega) && method_exists($fecha_entrega, 'format')) {
                $fechaBase = $fecha_entrega;
            } else if (is_string($fecha_entrega)) {
                // PRIMERO: Intentar formato Y-m-d (2026-11-05)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha_entrega);
                }
                // SEGUNDO: Formato ISO 8601: 2026-10-22T05:00:00.000Z
                else if (strpos($fecha_entrega, 'T') !== false) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $fecha_entrega);
                }
                // TERCERO: Formato con guiones (1-Oct-25)
                else if (preg_match('/\d{1,2}-[A-Za-z]{3}-\d{2}/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('d-M-y', $fecha_entrega);
                }
                // CUARTO: Intentar parsear automáticamente
                else {
                    $fechaBase = \Carbon\Carbon::parse($fecha_entrega);
                }
            } else {
                throw new Exception("Formato de fecha no reconocido: " . gettype($fecha_entrega));
            }
        } catch (\Exception $e) {
            // Usar fecha actual como fallback
            $fechaBase = \Carbon\Carbon::now();
        }

        $cronograma = [];
        $orden = 1;

        // Calcular todas las fechas
        $fechasCalculadas = [];
        $totalDias = 0;

        // Sumar todos los días para calcular la fecha inicial
        foreach ($actividades as $actividad) {
            $totalDias += $actividad->tiempo;
        }

        // Fecha inicial (la más antigua) - CORREGIDO: restar el total de días
        $fechaInicial = $fechaBase->copy()->subDays($totalDias);
        $fechaActual = $fechaInicial->copy();

        // Identificar actividades simultáneas para operador 1
        $actividadesSimultaneasIds = [2, 3, 4, 5];
        $fechaSimultaneas = null;
        $maxTiempoSimultaneas = 0;

        // Primera pasada: calcular fechas normales y detectar simultáneas
        foreach ($actividades as $actividad) {
            // Si es operador 1 y está en las actividades simultáneas
            if ($operador == 1 && in_array($actividad->id, $actividadesSimultaneasIds)) {
                // Para la primera actividad simultánea, guardar la fecha de inicio
                if ($fechaSimultaneas === null) {
                    $fechaSimultaneas = $fechaActual->copy();
                }
                // Encontrar el tiempo máximo entre las actividades simultáneas
                if ($actividad->tiempo > $maxTiempoSimultaneas) {
                    $maxTiempoSimultaneas = $actividad->tiempo;
                }

                // Todas las simultáneas comparten la misma fecha de inicio
                $fechaFinActividad = $fechaSimultaneas->copy()->addDays($actividad->tiempo);
                $fechasCalculadas[$actividad->id] = [
                    'inicio' => $fechaSimultaneas->copy(),
                    'fin' => $fechaFinActividad->copy()
                ];
            } else {
                // Actividades normales
                $fechaFinActividad = $fechaActual->copy()->addDays($actividad->tiempo);
                $fechasCalculadas[$actividad->id] = [
                    'inicio' => $fechaActual->copy(),
                    'fin' => $fechaFinActividad->copy()
                ];
                $fechaActual = $fechaFinActividad->copy();
            }
        }

        // Segunda pasada: ajustar fechas después de las simultáneas
        $fechaActual = $fechaInicial->copy();
        foreach ($actividades as $actividad) {
            if ($operador == 1 && in_array($actividad->id, $actividadesSimultaneasIds)) {
                // Para actividades simultáneas, usar la fecha compartida
                $fechaActual = $fechaSimultaneas->copy();
            } else {
                // Para actividades normales, calcular normalmente
                $fechaFinActividad = $fechaActual->copy()->addDays($actividad->tiempo);
                $fechasCalculadas[$actividad->id] = [
                    'inicio' => $fechaActual->copy(),
                    'fin' => $fechaFinActividad->copy()
                ];
                $fechaActual = $fechaFinActividad->copy();
            }
        }

        // Ajustar: después de todas las simultáneas, avanzar con el tiempo máximo
        if ($operador == 1 && $maxTiempoSimultaneas > 0) {
            $fechaActual = $fechaSimultaneas->copy()->addDays($maxTiempoSimultaneas);
        }

        // Generar el cronograma
        foreach ($actividades as $actividad) {
            $fechas = $fechasCalculadas[$actividad->id];

            // Determinar dependencia
            $dependencia = $this->determinarDependencia($actividad->id, $actividades, $operador);

            // SOLO el primer registro (orden 1) tiene estado 1, los demás estado 0
            $estado = ($orden == 1) ? 1 : 0;

            $registro = [
                'nombre_etapa' => $nombre_etapa,
                'codigo_proyecto' => $codigo_proyecto,
                'codigo_documento' => $codigo_proyecto_documentos,
                'etapa' => $etapaData,
                'actividad_id' => $actividad->id,
                'actividad_depende_id' => $dependencia,
                'tipo' => ($actividad->tipo == 1) ? 'principal' : 'simultanea',
                'orden' => $orden,
                'fecha_proyeccion' => $fechas['inicio']->format('Y-m-d'),
                'fecha_actual' => $fechas['inicio']->format('Y-m-d'),
                'estado' => $estado,
                'operador' => $operador,
                'created_at' => now(),
            ];

            $cronograma[] = $registro;
            $orden++;
        }

        // Insertar en la base de datos
        try {
            foreach ($cronograma as $registro) {
                DB::table('documentacion_operadores')->insert($registro);
            }
        } catch (\Exception $e) {
            throw $e; // Relanzar la excepción para ver el error completo
        }

        return $cronograma;
    }

    private function determinarDependencia($actividadId, $actividades, $operador = null)
    {
        // Para operador 1, actividades simultáneas (2,3,4,5) dependen de la actividad 1
        if ($operador == 1 && in_array($actividadId, [2, 3, 4, 5])) {
            return 1;
        }

        // Para actividades simultáneas (14-19) que dependen de 13
        if ($actividadId >= 14 && $actividadId <= 19) {
            return 13;
        }

        // Para actividad 20 que depende de 13 (las simultáneas se completan)
        if ($actividadId == 20) {
            return 13;
        }

        // Para las demás, dependen de la actividad anterior
        if ($actividadId > 1) {
            return $actividadId - 1;
        }

        // Primera actividad no tiene dependencia
        return null;
    }

    //creacion de TM de organismos
    private function CreartTm($data)
    {
        $codigoProyecto   = $data->codigo_proyecto;
        $codigoDocumento  = $data->codigoDocumentos;
        $usuarioId        = $data->usuarioId;
        $cantidadTm       = (int) $data->cantidad_tm;
        $organismos       = $data->organismoInspeccion;

        /**
         * Configuración fija por operador
         */
        $configOperadores = [
            1 => [ // RETIE
                'actividad_id' => 28,
                'hijos' => [34, 35, 36],
            ],
            2 => [ // RITEL
                'actividad_id' => 65,
                'hijos' => [67],
            ],
            3 => [ // RETIALP
                'actividad_id' => 90,
                'hijos' => [92],
            ],
        ];

        $registrosCreados = [];

        foreach ($organismos as $operador) {

            if (!isset($configOperadores[$operador])) {
                continue;
            }

            $actividadId  = $configOperadores[$operador]['actividad_id'];
            $hijos        = $configOperadores[$operador]['hijos'];

            /**
             * Crear registros por cantidad de TM
             */
            for ($tm = 1; $tm <= $cantidadTm; $tm++) {

                foreach ($hijos as $actividadHijoId) {

                    $registrosCreados[] = [
                        'codigo_proyecto'       => $codigoProyecto,
                        'codigo_documento'      => $codigoDocumento,
                        'user_id'               => $usuarioId,
                        'actividad_id'          => $actividadId,
                        'actividad_hijos_id'    => $actividadHijoId,
                        'tm'                    => $tm,
                        'estado'                => 1,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }
            }
        }
        /**
         * Inserción masiva
         */
        DB::table('torres_documentacion_organismos')->insert($registrosCreados);

        return [
            'success' => true,
            'total_registros' => count($registrosCreados),
        ];
    }


    //CREAR DOCUMENTACION (FIN)

    //GESTION DE CONTUL DE PROYECTOS Y ACTIVIDADES INICIO
    public function indexEmcali()
    {
        $usuario = Auth::user();
        $rolesPermitidos = ['Directora Proyectos', 'Tramites', 'Administrador']; //roles permitidos para ver todo los tramites sin filtros
        $tieneRolPermitido = in_array($usuario->rol, $rolesPermitidos);

        $data = collect([]);

        $documentacionQuery = function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador', 'nombre_etapa')
                ->where('operador', 1)
                ->distinct();
        };

        if ($tieneRolPermitido) {
            // Trae todos los proyectos
            $proyectosApartamentos = Proyectos::with(['documentacion' => $documentacionQuery])
                ->whereHas('documentacion', fn($q) => $q->where('operador', 1))
                ->get();

            $proyectosCasas = ProyectoCasa::with(['documentacion' => $documentacionQuery])
                ->whereHas('documentacion', fn($q) => $q->where('operador', 1))
                ->get();

            $data = $proyectosApartamentos->merge($proyectosCasas);
        } else {
            // Solo proyectos asignados al usuario
            $userId = Auth::id();

            $proyectosAsignados = DB::table('proyecto')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            $proyectosCasaAsignados = DB::table('proyectos_casas')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            $proyectosAsignadosTotales = array_merge($proyectosAsignados, $proyectosCasaAsignados);

            if (!empty($proyectosAsignadosTotales)) {
                $proyectosApartamentos = Proyectos::with(['documentacion' => $documentacionQuery])
                    ->whereHas('documentacion', fn($q) => $q->where('operador', 1))
                    ->whereIn('codigo_proyecto', $proyectosAsignados)
                    ->get();

                $proyectosCasas = ProyectoCasa::with(['documentacion' => $documentacionQuery])
                    ->whereHas('documentacion', fn($q) => $q->where('operador', 1))
                    ->whereIn('codigo_proyecto', $proyectosCasaAsignados)
                    ->get();

                $data = $proyectosApartamentos->merge($proyectosCasas);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //CONSULTA DOCUMENTACION CELSIA
    public function indexCelsia()
    {
        $usuario = Auth::user();
        $rolesPermitidos = ['Directora Proyectos', 'Ingeniero Obra', 'Tramites', 'Administrador'];
        $tieneRolPermitido = in_array($usuario->rol, $rolesPermitidos);

        $data = collect([]);

        // Función reutilizable para la relación documentacion
        $documentacionQuery = function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador', 'nombre_etapa')
                ->where('operador', 2) // Operador CELSIA
                ->distinct();
        };

        if ($tieneRolPermitido) {
            // Trae todos los proyectos
            $proyectosApartamentos = Proyectos::with(['documentacion' => $documentacionQuery])
                ->whereHas('documentacion', fn($q) => $q->where('operador', 2))
                ->get();

            $proyectosCasas = ProyectoCasa::with(['documentacion' => $documentacionQuery])
                ->whereHas('documentacion', fn($q) => $q->where('operador', 2))
                ->get();

            $data = $proyectosApartamentos->merge($proyectosCasas);
        } else {
            // Solo proyectos asignados al usuario
            $userId = Auth::id();

            $proyectosAsignados = DB::table('proyecto')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            $proyectosCasaAsignados = DB::table('proyectos_casas')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            if (!empty($proyectosAsignados) || !empty($proyectosCasaAsignados)) {
                $proyectosApartamentos = Proyectos::with(['documentacion' => $documentacionQuery])
                    ->whereHas('documentacion', fn($q) => $q->where('operador', 2))
                    ->whereIn('codigo_proyecto', $proyectosAsignados)
                    ->get();

                $proyectosCasas = ProyectoCasa::with(['documentacion' => $documentacionQuery])
                    ->whereHas('documentacion', fn($q) => $q->where('operador', 2))
                    ->whereIn('codigo_proyecto', $proyectosCasaAsignados)
                    ->get();

                $data = $proyectosApartamentos->merge($proyectosCasas);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //CONSULTA DOCUMENTACION ORGANISMOS se va a manejar que si alguno del proyecto tiene estado 1 aun no esta completo
    public function indexORGANISMOS($operador = null)
    {
        $usuario = Auth::user();
        $rolesPermitidos = ['Directora Proyectos', 'Ingeniero Obra', 'Tramites', 'Administrador'];
        $tieneRolPermitido = in_array($usuario->rol, $rolesPermitidos);

        $data = collect([]);

        // Función reutilizable para la relación documentosOrganismos
        $documentosQuery = function ($query) use ($operador) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador')
                ->when($operador, fn($q) => $q->where('operador', $operador))
                ->distinct();
        };

        if ($tieneRolPermitido) {
            // Trae todos los proyectos
            $proyectosApartamentos = Proyectos::with(['documentosOrganismos' => $documentosQuery])
                ->whereHas('documentosOrganismos', fn($q) => $operador ? $q->where('operador', $operador) : $q)
                ->get();

            $proyectosCasas = ProyectoCasa::with(['documentosOrganismos' => $documentosQuery])
                ->whereHas('documentosOrganismos', fn($q) => $operador ? $q->where('operador', $operador) : $q)
                ->get();

            $data = $proyectosApartamentos->merge($proyectosCasas);
        } else {
            // Solo proyectos asignados al usuario
            $userId = Auth::id();

            $proyectosAsignados = DB::table('proyecto')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            $proyectosCasaAsignados = DB::table('proyectos_casas')
                ->whereRaw("JSON_CONTAINS(ingeniero_id, '\"$userId\"')")
                ->pluck('codigo_proyecto')
                ->toArray();

            if (!empty($proyectosAsignados) || !empty($proyectosCasaAsignados)) {
                $proyectosApartamentos = Proyectos::with(['documentosOrganismos' => $documentosQuery])
                    ->whereHas('documentosOrganismos', fn($q) => $operador ? $q->where('operador', $operador) : $q)
                    ->whereIn('codigo_proyecto', $proyectosAsignados)
                    ->get();

                $proyectosCasas = ProyectoCasa::with(['documentosOrganismos' => $documentosQuery])
                    ->whereHas('documentosOrganismos', fn($q) => $operador ? $q->where('operador', $operador) : $q)
                    ->whereIn('codigo_proyecto', $proyectosCasaAsignados)
                    ->get();

                $data = $proyectosApartamentos->merge($proyectosCasas);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //se envia codigo de proyecto pero se cambia por codigo del documento
    //para traer datos unicos
    public function detalleDocumentos($codigo_documento)
    {

        $data = Documentos::with('actividad')
            ->where('codigo_documento', $codigo_documento)
            ->orderBy('orden')
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function detalleDocumentosOrganismos(Request $request)
    {
        $codigo_documento = $request->codigo_documento;
        $operador = $request->operador;


        $data = DocumentosOrganismos::with('actividad')
            ->where('codigo_documento', $codigo_documento)
            ->where('operador', $operador)
            ->orderBy('orden')
            ->get();

        // Separar por tipo
        $principales = $data->where('tipo', 'principal'); // Tipo 1 - sin hijos
        $conHijos = $data->where('tipo', 'principal_hijos'); // Tipo 2 - con hijos
        $hijos = $data->where('tipo', 'hijos'); // Tipo 3 - hijos

        $dataEstructurada = [];

        // Procesar elementos PRINCIPALES (tipo 1) - SIN campo hijos
        foreach ($principales as $principal) {
            $dataEstructurada[] = [
                'id' => $principal->id,
                'nombre_etapa' => $principal->nombre_etapa,
                'codigo_proyecto' => $principal->codigo_proyecto,
                'codigo_documento' => $principal->codigo_documento,
                'etapa' => $principal->etapa,
                'actividad_id' => $principal->actividad_id,
                'actividad_depende_id' => $principal->actividad_depende_id,
                'tipo' => $principal->tipo,
                'orden' => $principal->orden,
                'fecha_confirmacion' => $principal->fecha_confirmacion,
                'usuario_id' => $principal->usuario_id,
                'estado' => $principal->estado,
                'operador' => $principal->operador,
                'observacion' => $principal->observacion,
                'created_at' => $principal->created_at,
                'updated_at' => $principal->updated_at,
                'actividad' => $principal->actividad
                // NO incluir campo 'hijos'
            ];
        }

        // Procesar elementos CON HIJOS (tipo 2 - principal_hijos) - CON campo hijos
        foreach ($conHijos as $itemConHijos) {
            // Buscar los hijos de este elemento (donde actividad_depende_id = actividad_id del padre)
            $hijosDelItem = $hijos->where('actividad_depende_id', $itemConHijos->actividad_id);

            $dataEstructurada[] = [
                'id' => $itemConHijos->id,
                'nombre_etapa' => $itemConHijos->nombre_etapa,
                'codigo_proyecto' => $itemConHijos->codigo_proyecto,
                'codigo_documento' => $itemConHijos->codigo_documento,
                'etapa' => $itemConHijos->etapa,
                'actividad_id' => $itemConHijos->actividad_id,
                'actividad_depende_id' => $itemConHijos->actividad_depende_id,
                'tipo' => $itemConHijos->tipo,
                'orden' => $itemConHijos->orden,
                'fecha_confirmacion' => $itemConHijos->fecha_confirmacion,
                'usuario_id' => $itemConHijos->usuario_id,
                'estado' => $itemConHijos->estado,
                'operador' => $itemConHijos->operador,
                'observacion' => $itemConHijos->observacion,
                'created_at' => $itemConHijos->created_at,
                'updated_at' => $itemConHijos->updated_at,
                'actividad' => $itemConHijos->actividad,
                'hijos' => $hijosDelItem->values()->toArray() // SOLO tipo principal_hijos tiene hijos
            ];
        }

        // Ordenar por el campo orden
        $dataEstructurada = collect($dataEstructurada)->sortBy('orden')->values()->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $dataEstructurada
        ]);
    }

    //proyectos para crear documentacion
    public function proyectosCodigo()
    {
        try {
            $dataApt = Proyectos::where('estado', 1)
                ->select('descripcion_proyecto', 'codigo_proyecto')
                ->orderBy('descripcion_proyecto')
                ->get();

            $dataCasas = ProyectoCasa::where('estado', 1)
                ->select('descripcion_proyecto', 'codigo_proyecto')
                ->orderBy('descripcion_proyecto')
                ->get();

            // Si ProyectoCasa está vacío, retornar solo Proyectos
            if ($dataCasas->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => $dataApt
                ]);
            }

            // Si ambas tienen datos, combinarlas
            $data = $dataApt->concat($dataCasas)
                ->sortBy('descripcion_proyecto')
                ->values();


            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => 'Error al obtener los proyectos'
            ], 500);
        }
    }

    public function confirmarDocumento(Request $request)
    {
        try {
            // Validación de campos y múltiples archivos
            $request->validate([
                'id' => 'required|exists:documentacion_operadores,id',
                'codigo_proyecto' => 'required|string',
                'codigo_documento' => 'required|string',
                'etapa' => 'required|integer',
                'actividad_id' => 'required|integer',
                'observacion' => 'required|string',

                // Array de archivos
                'archivos' => 'array',
                'archivos.*' => 'file|mimes:jpg,jpeg,png,pdf|max:1048576', // 1GB
            ]);

            $archivosGuardados = [];

            // Guardar archivos si existen
            if ($request->hasFile('archivos')) {
                foreach ($request->file('archivos') as $archivo) {

                    // Generar nombre único para cada archivo
                    $nombreArchivo = $request->codigo_proyecto . '-' .
                        $request->codigo_documento . '-' .
                        $request->etapa . '-' .
                        $request->actividad_id . '-' .
                        time() . '-' .
                        uniqid() . '.' .
                        $archivo->getClientOriginalExtension();

                    // Guardar archivo en storage
                    $archivo->storeAs('public/documentacion/red', $nombreArchivo);

                    // Obtener ruta pública
                    $rutaPublica = Storage::url('documentacion/red/' . $nombreArchivo);

                    // Guardar en tabla documentos_adjuntos
                    DocumentosAdjuntos::create([
                        'documento_id' => $request->id,
                        'ruta_archivo' => $rutaPublica,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'extension' => $archivo->getClientOriginalExtension(),
                        'tamano' => $archivo->getSize(),
                    ]);

                    // Agregar a array de respuesta
                    $archivosGuardados[] = [
                        'nombre' => $archivo->getClientOriginalName(),
                        'ruta' => $rutaPublica,
                        'mime' => $archivo->getMimeType(),
                    ];
                }
            }

            // 1. Obtener la actividad actual y calcular diferencia de días
            $actividadActual = Documentos::find($request->id);
            $fechaProyeccion = \Carbon\Carbon::parse($actividadActual->fecha_proyeccion);
            $fechaHoy = now();
            $diasDiferencia = $fechaProyeccion->diffInDays($fechaHoy, false);

            // 2. Actualizar actividad a estado 2 (Completado)
            $actividadActual->update([
                'estado' => 2,
                'observacion' => $request->observacion,
                'fecha_confirmacion' => now(),
                'fecha_actual' => now(),
                'usuario_id' => Auth::id(),
            ]);

            // 3. Actualizar fechas de actividades siguientes
            if ($diasDiferencia != 0) {
                $this->actualizarFechasSiguientes(
                    $request->codigo_proyecto,
                    $request->codigo_documento,
                    $request->etapa,
                    $actividadActual->orden,
                    $diasDiferencia
                );
            }

            // 4. Aplicar reglas específicas para actividades 1-9
            $this->aplicarReglasEspeciales(
                $request->codigo_proyecto,
                $request->codigo_documento,
                $request->etapa,
                $request->actividad_id,
                $actividadActual->orden
            );

            // 5. Para actividades >= 9, aplicar lógica normal
            if ($request->actividad_id >= 9) {
                $this->aplicarLogicaNormalFlujo(
                    $request->codigo_proyecto,
                    $request->codigo_documento,
                    $request->etapa,
                    $actividadActual
                );
            }

            // 6. Respuesta completa incluyendo archivos subidos
            return response()->json([
                'status' => 'success',
                'message' => 'Actividad confirmada exitosamente' .
                    ($diasDiferencia != 0 ?
                        ($diasDiferencia > 0 ?
                            " con {$diasDiferencia} días de retraso aplicados" :
                            " con " . abs($diasDiferencia) . " días de adelanto aplicados")
                        : ""
                    ),
                'data' => [
                    'actual' => $actividadActual,
                    'dias_diferencia' => $diasDiferencia,
                    'archivos' => $archivosGuardados, // ← aquí van todos los archivos subidos
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Función para aplicar reglas específicas de habilitación (solo para actividades 1-9)
    private function aplicarReglasEspeciales($codigo_proyecto, $codigo_documento, $etapa, $actividad_id, $ordenActual)
    {
        // Regla 1: Si se confirma actividad 1, habilitar simultáneas [2,3,4,5]
        if ($actividad_id == 1) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [2, 3, 4, 5]);
        }

        // Regla 2: Si se confirma actividad 3, habilitar actividad 6
        if ($actividad_id == 3) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [6]);
        }

        // Regla 3: Si se confirma actividad 6, habilitar actividad 7
        if ($actividad_id == 6) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [7]);
        }

        // Regla 4: Para habilitar actividad 8, deben estar confirmadas 2 y 7
        if ($actividad_id == 2 || $actividad_id == 7) {
            $this->verificarHabilitacionActividad8($codigo_proyecto, $codigo_documento, $etapa);
        }

        // Regla 5: Si se confirma actividad 8, habilitar actividad 9
        if ($actividad_id == 8) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [9]);
        }

        // Para actividades 4 y 5 no se hace nada especial (solo se confirman)
    }

    // Función para verificar habilitación de actividad 8
    private function verificarHabilitacionActividad8($codigo_proyecto, $codigo_documento, $etapa)
    {
        $actividad2 = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('actividad_id', 2)
            ->first();

        $actividad7 = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('actividad_id', 7)
            ->first();

        if (
            $actividad2 && $actividad2->estado == 2 &&
            $actividad7 && $actividad7->estado == 2
        ) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [8]);
        }
    }

    // Función para aplicar lógica normal de flujo (para actividades 9 en adelante)
    private function aplicarLogicaNormalFlujo($codigo_proyecto, $codigo_documento, $etapa, $actividadActual)
    {
        // Si es actividad SIMULTÁNEA, verificar si todas las simultáneas están completas
        if ($actividadActual->tipo === 'simultanea') {
            // Obtener todas las actividades simultáneas del mismo grupo
            $actividadesSimultaneas = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_depende_id', $actividadActual->actividad_depende_id)
                ->where('tipo', 'simultanea')
                ->get();

            // Verificar si TODAS las actividades simultáneas están completas
            $todasCompletas = $actividadesSimultaneas->every(function ($actividad) {
                return $actividad->estado == 2; // Todas deben estar en estado 2 (Completado)
            });

            if ($todasCompletas) {
                // Buscar la siguiente actividad PRINCIPAL después del grupo simultáneo
                $siguienteActividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                    ->where('codigo_documento', $codigo_documento)
                    ->where('etapa', $etapa)
                    ->where('orden', '>', $actividadesSimultaneas->max('orden'))
                    ->where('tipo', 'principal')
                    ->orderBy('orden')
                    ->first();

                if ($siguienteActividad) {
                    $siguienteActividad->update([
                        'estado' => 1, // Disponible
                        'fecha_actual' => now(),
                    ]);
                }
            }
        }
        // Si es actividad PRINCIPAL, habilitar la siguiente actividad normalmente
        else {
            $siguienteActividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('orden', $actividadActual->orden + 1)
                ->first();

            if ($siguienteActividad) {
                $siguienteActividad->update([
                    'estado' => 1, // Disponible
                    'fecha_actual' => now(),
                ]);

                // Si la siguiente actividad es simultánea, habilitar todas las del grupo
                if ($siguienteActividad->tipo === 'simultanea') {
                    $actividadesSimultaneas = Documentos::where('codigo_proyecto', $codigo_proyecto)
                        ->where('codigo_documento', $codigo_documento)
                        ->where('etapa', $etapa)
                        ->where('actividad_depende_id', $siguienteActividad->actividad_depende_id)
                        ->where('tipo', 'simultanea')
                        ->get();

                    foreach ($actividadesSimultaneas as $actividadSimultanea) {
                        if ($actividadSimultanea->id != $siguienteActividad->id) {
                            $actividadSimultanea->update([
                                'estado' => 1, // Disponible
                                'fecha_actual' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    // Función para habilitar actividades específicas (sin cambios)
    private function habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, $actividades_ids)
    {
        foreach ($actividades_ids as $actividad_id) {
            $actividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_id', $actividad_id)
                ->first();

            if ($actividad && $actividad->estado == 0) {
                $actividad->update([
                    'estado' => 1,
                    'fecha_actual' => now(),
                ]);
            }
        }
    }

    // Función para actualizar fechas de actividades siguientes (sin cambios)
    private function actualizarFechasSiguientes($codigo_proyecto, $codigo_documento, $etapa, $ordenActual, $diasDiferencia)
    {
        $actividadesSiguientes = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('orden', '>', $ordenActual)
            ->where('estado', '!=', 2)
            ->orderBy('orden')
            ->get();

        foreach ($actividadesSiguientes as $actividad) {
            $nuevaFechaActual = \Carbon\Carbon::parse($actividad->fecha_actual);

            if ($diasDiferencia > 0) {
                $nuevaFechaActual = $nuevaFechaActual->addDays($diasDiferencia);
            } else {
                $nuevaFechaActual = $nuevaFechaActual->subDays(abs($diasDiferencia));
            }

            $actividad->update([
                'fecha_actual' => $nuevaFechaActual->format('Y-m-d'),
            ]);

            $tipoAjuste = $diasDiferencia > 0 ? "sumar" : "restar";
        }
    }

    private function documentosOrganismos($data)
    {
        $etapa = $data->etapaProyecto;
        $nombre_etapa = $data->nombre_etapa;
        $codigoDocumentos = $data->codigoDocumentos;
        $codigo_proyecto = $data->codigo_proyecto;
        $organismoInspeccion = $data->organismoInspeccion;
        $fechaEntrega = $data->fechaEntrega;
        $usuarioId = $data->usuarioId;

        // Usar el modelo correcto - ActividadesOrganismos
        $dataActividades = ActividadesOrganismos::where('estado', 1)->get();

        // Mapeo de organismos
        $organismosMap = [
            1 => 'RETIE',
            2 => 'RITEL',
            3 => 'RETIALP'
        ];

        $operadoresMap = [
            'RETIE' => 1,
            'RITEL' => 2,
            'RETIALP' => 3
        ];

        $documentosInsertados = [];

        foreach ($organismoInspeccion as $organismoId) {
            $nombreOrganismo = $organismosMap[$organismoId] ?? null;
            $operador = $operadoresMap[$nombreOrganismo] ?? null;

            if (!$nombreOrganismo || !$operador) {
                continue;
            }


            // Obtener TODAS las actividades para este operador
            $actividadesOperador = $dataActividades
                ->where('operador', $operador)
                ->sortBy('id');


            $orden = 1;

            foreach ($actividadesOperador as $actividad) {

                // Determinar el tipo para documentos_organismos
                $tipoDocumento = '';
                switch ($actividad->tipo) {
                    case 1:
                        $tipoDocumento = 'principal';
                        break;
                    case 2:
                        $tipoDocumento = 'principal_hijos';
                        break;
                    case 3:
                        $tipoDocumento = 'hijos';
                        break;
                }

                // Determinar actividad_depende_id
                $actividadDependeId = null;
                if ($actividad->tipo == 3 && !empty($actividad->padre)) {
                    // Para actividades tipo 3 (hijos), usar el padre
                    $actividadDependeId = $actividad->padre;
                }
                // Para actividades tipo 1 y 2, actividad_depende_id queda como null

                // Insertar la actividad
                $documentoId = DB::table('documentos_organismos')->insertGetId([
                    'nombre_etapa' => $nombre_etapa,
                    'codigo_proyecto' => $codigo_proyecto,
                    'codigo_documento' => $codigoDocumentos,
                    'etapa' => $etapa,
                    'actividad_id' => $actividad->id,
                    'actividad_depende_id' => $actividadDependeId,
                    'tipo' => $tipoDocumento,
                    'orden' => $orden,
                    'fecha_confirmacion' => $fechaEntrega,
                    'usuario_id' => $usuarioId,
                    'operador' => $operador,
                    'observacion' => null,
                    'estado' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $documentosInsertados[] = $documentoId;

                $orden++;
            }
        }


        return [
            'success' => true,
            'documentos_insertados' => count($documentosInsertados),
            'ids_documentos' => $documentosInsertados
        ];
    }

    public function confirmarDocumentoOrganismo(Request $request)
    {
        // Validar los datos de entrada
        $validated = $request->validate([
            'id' => 'required|integer|exists:documentos_organismos,id',
            'observacion' => 'nullable|string|max:500',
            'archivos.*' => 'file|mimes:jpg,jpeg,png,pdf|max:1073741824', // hasta 1GB
        ]);

        try {
            // Buscar el documento
            $documento = DocumentosOrganismos::find($validated['id']);

            if (!$documento) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            // Verificar que el documento no esté ya confirmado
            if ($documento->estado == 2) {
                return response()->json([
                    'status' => 'warning',
                    'message' => 'El documento ya está confirmado'
                ], 422);
            }

            // Actualizar el documento
            $documento->update([
                'estado' => 2, // Completado
                'observacion' => $validated['observacion'] ?? null,
                'fecha_confirmacion' => now(),
                'usuario_id' => Auth::id(),
            ]);

            $archivosGuardados = [];

            // Subir archivos (si existen)
            if ($request->hasFile('archivos')) {
                foreach ($request->file('archivos') as $archivo) {

                    // Generar nombre único
                    $nombreArchivo = $documento->id . '-' . time() . '-' . uniqid() . '.' . $archivo->getClientOriginalExtension();

                    // Guardar archivo en storage
                    $archivo->storeAs('public/documentacion/organismos', $nombreArchivo);

                    // Obtener ruta pública
                    $rutaPublica = Storage::url('documentacion/organismos/' . $nombreArchivo);

                    // Guardar en tabla documentos_organismos_adjuntos
                    \App\Models\DocumentosOrganismosAdjuntos::create([
                        'documento_id' => $documento->id,
                        'ruta_archivo' => $rutaPublica,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'extension' => $archivo->getClientOriginalExtension(),
                        'tamano' => $archivo->getSize(),
                    ]);

                    // Agregar a array de respuesta
                    $archivosGuardados[] = [
                        'nombre' => $archivo->getClientOriginalName(),
                        'ruta' => $rutaPublica,
                        'mime' => $archivo->getMimeType(),
                    ];
                }
            }

            // Recargar el modelo para obtener los datos actualizados
            $documento->refresh();

            // Verificar si el documento tiene un padre y actualizarlo si todos los hijos están en estado 2
            if ($documento->actividad_depende_id) {
                $this->actualizarEstadoPadre($documento->actividad_depende_id, $documento->codigo_documento);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Documento confirmado y archivos subidos exitosamente',
                'data' => $documento,
                'archivos' => $archivosGuardados,
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Error al confirmar documento organismo: ' . $e->getMessage(), [
                'request' => $validated,
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al confirmar el documento'
            ], 500);
        }
    }


    private function actualizarEstadoPadre($actividadDependeId, $codigo)
    {
        try {
            // Buscar el documento padre usando actividad_depende_id
            $documentoPadre = DocumentosOrganismos::where('actividad_id', $actividadDependeId)
                ->where('codigo_documento', $codigo) // Asumiendo que el padre es tipo principal
                ->first();


            if (!$documentoPadre) {
                return;
            }

            // Verificar si el padre ya está en estado 2
            if ($documentoPadre->estado == 2) {
                return;
            }

            // Contar todos los documentos hijos del padre (donde actividad_depende_id apunta al actividad_id del padre)
            $totalHijos = DocumentosOrganismos::where('actividad_depende_id', $actividadDependeId)->where('codigo_documento', $codigo)->count();

            // Contar los documentos hijos que están en estado 2
            $hijosCompletados = DocumentosOrganismos::where('actividad_depende_id', $actividadDependeId)
                ->where('codigo_documento', $codigo)
                ->where('estado', 2)
                ->count();

            // Si todos los hijos están en estado 2, actualizar el padre
            if ($totalHijos > 0 && $hijosCompletados == $totalHijos) {
                $documentoPadre->update([
                    'estado' => 2,
                    'fecha_confirmacion' => now(), // Agregar fecha de confirmación para el padre
                    'usuario_id' => Auth::id(),
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('Error al actualizar estado del padre: ' . $e->getMessage(), [
                'actividad_depende_id' => $actividadDependeId,
                'user_id' => Auth::id(),
                'exception' => $e
            ]);
        }
    }


    public function proyectoName($codigo_proyecto)
    {
        // Buscar primero en Proyectos
        $data = Proyectos::where('codigo_proyecto', $codigo_proyecto)
            ->select('descripcion_proyecto')
            ->first();

        // Si no existe, buscar en ProyectoCasa
        if (!$data) {
            $data = ProyectoCasa::where('codigo_proyecto', $codigo_proyecto)
                ->select('descripcion_proyecto')
                ->first();
        }

        return response()->json($data);
    }


    public function obtenerAdjuntos($documentoId)
    {
        $data =  DocumentosAdjuntos::where('documento_id', $documentoId)->get();
        return response()->json($data);
    }

    public function obtenerAdjuntosOrganismos($documentoId)
    {
        $data =  DocumentosOrganismosAdjuntos::where('documento_id', $documentoId)->get();
        return response()->json($data);
    }


    //ESTADO Y PROCENTAJES DE LA DOCUMENTACION POR PROYECTO
    // public function estadoTramitesAdmin()
    // {
    //     $documentos = Documentos::select('codigo_proyecto', 'estado', 'operador')->get();
    //     $organismos = DocumentosOrganismos::select('codigo_proyecto', 'estado', 'operador')->get();

    //     // Función para documentos (agrupado por proyecto)
    //     $calcularPorcentajesDocumentos = function ($coleccion) {
    //         return $coleccion->groupBy('codigo_proyecto')->map(function ($items, $proyecto) {
    //             $total = $items->count();
    //             $completos = $items->where('estado', 2)->count();

    //             return [
    //                 'tipo' => 'documentos',
    //                 'codigo_proyecto' => $proyecto,
    //                 'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
    //                 'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
    //             ];
    //         })->values();
    //     };

    //     // Función para organismos (estructura anidada: proyecto -> operadores)
    //     $calcularPorcentajesOrganismos = function ($coleccion) {
    //         $resultados = [];

    //         // Agrupar por proyecto
    //         $porProyecto = $coleccion->groupBy('codigo_proyecto');

    //         foreach ($porProyecto as $proyecto => $itemsProyecto) {
    //             $proyectoData = [
    //                 'codigo_proyecto' => $proyecto,
    //                 'tipo' => 'organismos',
    //                 'operadores' => []
    //             ];

    //             // Agrupar por operador dentro del proyecto
    //             $porOperador = $itemsProyecto->groupBy('operador');

    //             foreach ($porOperador as $operador => $items) {
    //                 $total = $items->count();
    //                 $completos = $items->where('estado', 2)->count();

    //                 $proyectoData['operadores'][] = [
    //                     'operador' => $operador,
    //                     'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
    //                     'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
    //                     'total' => $total,
    //                     'completados' => $completos,
    //                     'pendientes' => $total - $completos
    //                 ];
    //             }

    //             $resultados[] = $proyectoData;
    //         }

    //         return collect($resultados);
    //     };

    //     return response()->json([
    //         'status' => 'success',
    //         'porcentajes' => [
    //             'documentos' => $calcularPorcentajesDocumentos($documentos),
    //             'organismos' => $calcularPorcentajesOrganismos($organismos)
    //         ]
    //     ]);
    // }
    // public function estadoTramitesAdmin()
    // {
    //     // Asumiendo que tienes un campo 'etapa' en tus modelos
    //     $documentos = Documentos::select('codigo_proyecto', 'etapa', 'estado', 'operador')->get();
    //     $organismos = DocumentosOrganismos::select('codigo_proyecto', 'etapa', 'estado', 'operador')->get();

    //     // Función para documentos agrupados por proyecto y etapa
    //     $calcularPorcentajesDocumentos = function ($coleccion) {
    //         return $coleccion->groupBy('codigo_proyecto')->map(function ($itemsProyecto, $proyecto) {
    //             // Agrupar por etapas dentro del proyecto
    //             $etapas = $itemsProyecto->groupBy('etapa')->map(function ($itemsEtapa, $etapa) {
    //                 $total = $itemsEtapa->count();
    //                 $completos = $itemsEtapa->where('estado', 2)->count();
    //                 $avance = $total > 0 ? round(($completos / $total) * 100, 2) : 0;

    //                 return [
    //                     'etapa' => $etapa,
    //                     'avance' => $avance,
    //                     'atrazo' => 100 - $avance,
    //                     'detalle' => [
    //                         'total' => $total,
    //                         'completados' => $completos,
    //                         'pendientes' => $total - $completos
    //                     ]
    //                 ];
    //             })->values();

    //             // Calcular promedio general del proyecto
    //             $avanceProyecto = $etapas->avg('avance') ?? 0;

    //             return [
    //                 'tipo' => 'documentos',
    //                 'codigo_proyecto' => $proyecto,
    //                 'avance_general' => $avanceProyecto,
    //                 'atrazo_general' => 100 - $avanceProyecto,
    //                 'etapas' => $etapas,
    //                 'total_etapas' => $etapas->count()
    //             ];
    //         })->values();
    //     };

    //     // Función para organismos agrupados por proyecto, etapa y operador
    //     $calcularPorcentajesOrganismos = function ($coleccion) {
    //         return $coleccion->groupBy('codigo_proyecto')->map(function ($itemsProyecto, $proyecto) {
    //             // Agrupar por etapas
    //             $etapas = $itemsProyecto->groupBy('etapa')->map(function ($itemsEtapa, $etapa) {
    //                 // Agrupar por operadores dentro de la etapa
    //                 $operadores = $itemsEtapa->groupBy('operador')->map(function ($itemsOperador, $operador) {
    //                     $total = $itemsOperador->count();
    //                     $completos = $itemsOperador->where('estado', 2)->count();
    //                     $avance = $total > 0 ? round(($completos / $total) * 100, 2) : 0;

    //                     return [
    //                         'operador' => $operador,
    //                         'avance' => $avance,
    //                         'atrazo' => 100 - $avance,
    //                         'detalle' => [
    //                             'total' => $total,
    //                             'completados' => $completos,
    //                             'pendientes' => $total - $completos
    //                         ]
    //                     ];
    //                 })->values();

    //                 // Calcular promedio de la etapa
    //                 $avanceEtapa = $operadores->avg('avance') ?? 0;

    //                 return [
    //                     'etapa' => $etapa,
    //                     'avance_etapa' => $avanceEtapa,
    //                     'atrazo_etapa' => 100 - $avanceEtapa,
    //                     'operadores' => $operadores,
    //                     'total_operadores' => $operadores->count()
    //                 ];
    //             })->values();

    //             // Calcular promedio general del proyecto
    //             $avanceGeneral = $etapas->avg('avance_etapa') ?? 0;

    //             return [
    //                 'tipo' => 'organismos',
    //                 'codigo_proyecto' => $proyecto,
    //                 'avance_general' => $avanceGeneral,
    //                 'atrazo_general' => 100 - $avanceGeneral,
    //                 'etapas' => $etapas,
    //                 'total_etapas' => $etapas->count()
    //             ];
    //         })->values();
    //     };

    //     return response()->json([
    //         'status' => 'success',
    //         'resumen' => [
    //             'total_proyectos_documentos' => $documentos->groupBy('codigo_proyecto')->count(),
    //             'total_proyectos_organismos' => $organismos->groupBy('codigo_proyecto')->count(),
    //             'total_documentos' => $documentos->count(),
    //             'total_organismos' => $organismos->count()
    //         ],
    //         'detalle' => [
    //             'documentos' => $calcularPorcentajesDocumentos($documentos),
    //             'organismos' => $calcularPorcentajesOrganismos($organismos)
    //         ]
    //     ]);
    // }
    public function estadoTramitesAdmin()
{
    $documentos = Documentos::select('codigo_proyecto', 'etapa', 'estado', 'operador')->get();
    $organismos = DocumentosOrganismos::select('codigo_proyecto', 'estado', 'operador')->get();

    // Función para documentos (agrupado por proyecto y etapa)
    $calcularPorcentajesDocumentos = function ($coleccion) {
        return $coleccion->groupBy('codigo_proyecto')->map(function ($items, $proyecto) {
            // Agrupar por etapas para calcular porcentajes por etapa
            $etapas = $items->groupBy('etapa')->map(function ($itemsEtapa, $etapa) {
                $total = $itemsEtapa->count();
                $completos = $itemsEtapa->where('estado', 2)->count();
                
                return [
                    'etapa' => $etapa,
                    'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
                    'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
                    'total' => $total,
                    'completados' => $completos,
                    'pendientes' => $total - $completos
                ];
            })->values();
            
            // Calcular totales del proyecto
            $total = $items->count();
            $completos = $items->where('estado', 2)->count();

            return [
                'tipo' => 'documentos',
                'codigo_proyecto' => $proyecto,
                'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
                'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
                'etapas' => $etapas, // Agregado: detalle por etapas
                'total' => $total,
                'completados' => $completos,
                'pendientes' => $total - $completos
            ];
        })->values();
    };

    // Función para organismos (estructura anidada: proyecto -> operadores)
    $calcularPorcentajesOrganismos = function ($coleccion) {
        $resultados = [];

        // Agrupar por proyecto
        $porProyecto = $coleccion->groupBy('codigo_proyecto');

        foreach ($porProyecto as $proyecto => $itemsProyecto) {
            $proyectoData = [
                'codigo_proyecto' => $proyecto,
                'tipo' => 'organismos',
                'operadores' => []
            ];

            // Agrupar por operador dentro del proyecto
            $porOperador = $itemsProyecto->groupBy('operador');

            foreach ($porOperador as $operador => $items) {
                $total = $items->count();
                $completos = $items->where('estado', 2)->count();

                $proyectoData['operadores'][] = [
                    'operador' => $operador,
                    'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
                    'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
                    'total' => $total,
                    'completados' => $completos,
                    'pendientes' => $total - $completos
                ];
            }

            $resultados[] = $proyectoData;
        }

        return collect($resultados);
    };

    return response()->json([
        'status' => 'success',
        'porcentajes' => [
            'documentos' => $calcularPorcentajesDocumentos($documentos),
            'organismos' => $calcularPorcentajesOrganismos($organismos)
        ]
    ]);
}


    //consular docuemtos disponibles
    public function DocumentosDisponibles($codigo)
    {
        $data = DB::connection('mysql')
            ->table('documentacion_operadores')
            ->join('actividades_documentos', 'documentacion_operadores.actividad_id', '=', 'actividades_documentos.id')
            ->where('documentacion_operadores.codigo_documento', $codigo)
            ->where('documentacion_operadores.estado', 1)
            ->select(
                // Datos básicos de ficha_th
                'actividades_documentos.actividad',
            )
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //consultar doumentos por tm en Dictamen de inspección de cada tipo de organismo, retie, retial y ritel
    public function TmDisponiblesOrganismos(Request $request)
    {
        //data
        $codigo_proyecto = $request->codigo_proyecto;
        $codigo_documento = $request->codigo_documento;
        $operador = $request->operador;
        $actividad_id = 0;

        if ($operador == 1) {
            $actividad_id = 28;
        } else if ($operador == 2) {
            $actividad_id = 65;
        } else {
            $actividad_id = 90;
        }


        $data = TMorganismos::where('codigo_proyecto', $codigo_proyecto)
            ->where('actividad_id', $actividad_id)
            ->where('codigo_documento', $codigo_documento)
            ->get();

        //retornamos la data de Tm(torres - mazanas) disponibles para los organismos 
        return response()->json([
            'status' => 'succes',
            'data' => $data
        ]);
    }

    public function ConfirmarTM(Request $request)
    {
        $request->validate([
            'codigo_proyecto'       => 'required|string',
            'codigo_documento'      => 'required|string',
            'actividad_depende_id'  => 'required|integer',
            'actividad_id'          => 'required|integer',
            'tm'                    => 'required',
        ]);

        try {
            $tmOrganismo = TMorganismos::where([
                'codigo_proyecto'      => $request->codigo_proyecto,
                'actividad_id'         => $request->actividad_depende_id,
                'codigo_documento'     => $request->codigo_documento,
                'actividad_hijos_id'   => $request->actividad_id,
                'tm'                   => $request->tm,
            ])->firstOrFail();

            $tmOrganismo->update([
                'estado'    => 2,
                'user_id'   => Auth::id(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'El TM se confirmó correctamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No se encontró el TM solicitado',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al confirmar el TM',
            ], 500);
        }
    }


    /* logica para confirmar documentos de celsia */

    public function confirmarDocumentoCelsia(Request $request)
    {
        try {
            // Validación de campos y múltiples archivos
            $request->validate([
                'id' => 'required|exists:documentacion_operadores,id',
                'codigo_proyecto' => 'required|string',
                'codigo_documento' => 'required|string',
                'etapa' => 'required|integer',
                'actividad_id' => 'required|integer',
                'observacion' => 'required|string',

                // Array de archivos
                'archivos' => 'array',
                'archivos.*' => 'file|mimes:jpg,jpeg,png,pdf|max:1048576', // 1GB
            ]);

            $archivosGuardados = [];

            // Guardar archivos si existen
            if ($request->hasFile('archivos')) {
                foreach ($request->file('archivos') as $archivo) {

                    // Generar nombre único para cada archivo
                    $nombreArchivo = $request->codigo_proyecto . '-' .
                        $request->codigo_documento . '-' .
                        $request->etapa . '-' .
                        $request->actividad_id . '-' .
                        time() . '-' .
                        uniqid() . '.' .
                        $archivo->getClientOriginalExtension();

                    // Guardar archivo en storage
                    $archivo->storeAs('public/documentacion/red', $nombreArchivo);

                    // Obtener ruta pública
                    $rutaPublica = Storage::url('documentacion/red/' . $nombreArchivo);

                    // Guardar en tabla documentos_adjuntos
                    DocumentosAdjuntos::create([
                        'documento_id' => $request->id,
                        'ruta_archivo' => $rutaPublica,
                        'nombre_original' => $archivo->getClientOriginalName(),
                        'extension' => $archivo->getClientOriginalExtension(),
                        'tamano' => $archivo->getSize(),
                    ]);

                    // Agregar a array de respuesta
                    $archivosGuardados[] = [
                        'nombre' => $archivo->getClientOriginalName(),
                        'ruta' => $rutaPublica,
                        'mime' => $archivo->getMimeType(),
                    ];
                }
            }

            // 1. Obtener la actividad actual y calcular diferencia de días
            $actividadActual = Documentos::find($request->id);
            $fechaProyeccion = \Carbon\Carbon::parse($actividadActual->fecha_proyeccion);
            $fechaHoy = now();
            $diasDiferencia = $fechaProyeccion->diffInDays($fechaHoy, false);

            // 2. Actualizar actividad a estado 2 (Completado)
            $actividadActual->update([
                'estado' => 2,
                'observacion' => $request->observacion,
                'fecha_confirmacion' => now(),
                'fecha_actual' => now(),
                'usuario_id' => Auth::id(),
            ]);

            // 3. Actualizar fechas de actividades siguientes
            if ($diasDiferencia != 0) {
                $this->actualizarFechasSiguientesCelsia(
                    $request->codigo_proyecto,
                    $request->codigo_documento,
                    $request->etapa,
                    $actividadActual->orden,
                    $diasDiferencia
                );
            }

            // 4. Aplicar lógica de escalera basada en el orden
            $this->aplicarLogicaEscaleraCelsia(
                $request->codigo_proyecto,
                $request->codigo_documento,
                $request->etapa,
                $actividadActual->orden,
                $actividadActual->actividad_id
            );

            // 5. Respuesta completa incluyendo archivos subidos
            return response()->json([
                'status' => 'success',
                'message' => 'Actividad confirmada exitosamente' .
                    ($diasDiferencia != 0 ?
                        ($diasDiferencia > 0 ?
                            " con {$diasDiferencia} días de retraso aplicados" :
                            " con " . abs($diasDiferencia) . " días de adelanto aplicados")
                        : ""
                    ),
                'data' => [
                    'actual' => $actividadActual,
                    'dias_diferencia' => $diasDiferencia,
                    'archivos' => $archivosGuardados,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Función principal para aplicar lógica de escalera
    private function aplicarLogicaEscaleraCelsia($codigo_proyecto, $codigo_documento, $etapa, $ordenActual, $actividad_id)
    {
        // Caso 1: Ordenes del 1 al 9 (escalera simple)
        if ($ordenActual >= 1 && $ordenActual <= 9) {
            // Habilitar solo la siguiente actividad en orden
            $siguienteOrden = $ordenActual + 1;

            // Para orden 10 hay lógica especial
            if ($ordenActual == 10) {
                $siguienteOrden = 11;
            }

            $this->habilitarSiguienteActividadCelsia($codigo_proyecto, $codigo_documento, $etapa, $siguienteOrden);
        }

        // Caso 2: Confirmación de orden 10 - habilitar todas las actividades del 11 al 23
        elseif ($ordenActual == 10) {
            $this->habilitarActividadesRangoCelsia($codigo_proyecto, $codigo_documento, $etapa, 11, 23);
        }

        // Caso 3: Actividades del 11 al 23 - verificar si todas están completas para habilitar orden 24
        elseif ($ordenActual >= 11 && $ordenActual <= 23) {
            $this->verificarCompletitudGrupo11a23Celsia($codigo_proyecto, $codigo_documento, $etapa);
        }

        // Caso 4: Orden 24 en adelante - escalera normal
        elseif ($ordenActual >= 24) {
            $siguienteOrden = $ordenActual + 1;
            $this->habilitarSiguienteActividadCelsia($codigo_proyecto, $codigo_documento, $etapa, $siguienteOrden);
        }
    }

    // Función para habilitar solo la siguiente actividad por orden
    private function habilitarSiguienteActividadCelsia($codigo_proyecto, $codigo_documento, $etapa, $siguienteOrden)
    {
        $siguienteActividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('orden', $siguienteOrden)
            ->first();

        if ($siguienteActividad && $siguienteActividad->estado == 0) {
            $siguienteActividad->update([
                'estado' => 1,
                'fecha_actual' => now(),
            ]);
        }
    }

    // Función para habilitar un rango completo de actividades
    private function habilitarActividadesRangoCelsia($codigo_proyecto, $codigo_documento, $etapa, $ordenInicio, $ordenFin)
    {
        $actividades = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->whereBetween('orden', [$ordenInicio, $ordenFin])
            ->where('estado', 0)
            ->get();

        foreach ($actividades as $actividad) {
            $actividad->update([
                'estado' => 1,
                'fecha_actual' => now(),
            ]);
        }
    }

    // Función para verificar si todas las actividades del 11 al 23 están completas
    private function verificarCompletitudGrupo11a23Celsia($codigo_proyecto, $codigo_documento, $etapa)
    {
        $actividadesGrupo = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->whereBetween('orden', [11, 23])
            ->get();

        // Verificar si TODAS las actividades están en estado 2 (Completado)
        $todasCompletas = $actividadesGrupo->every(function ($actividad) {
            return $actividad->estado == 2;
        });

        if ($todasCompletas) {
            // Habilitar orden 24
            $this->habilitarSiguienteActividadCelsia($codigo_proyecto, $codigo_documento, $etapa, 24);
        }
    }

    // Función para actualizar fechas de actividades siguientes (sin cambios)
    private function actualizarFechasSiguientesCelsia($codigo_proyecto, $codigo_documento, $etapa, $ordenActual, $diasDiferencia)
    {
        $actividadesSiguientes = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('orden', '>', $ordenActual)
            ->where('estado', '!=', 2)
            ->orderBy('orden')
            ->get();

        foreach ($actividadesSiguientes as $actividad) {
            $nuevaFechaActual = \Carbon\Carbon::parse($actividad->fecha_actual);

            if ($diasDiferencia > 0) {
                $nuevaFechaActual = $nuevaFechaActual->addDays($diasDiferencia);
            } else {
                $nuevaFechaActual = $nuevaFechaActual->subDays(abs($diasDiferencia));
            }

            $actividad->update([
                'fecha_actual' => $nuevaFechaActual->format('Y-m-d'),
            ]);

            $tipoAjuste = $diasDiferencia > 0 ? "sumar" : "restar";
        }
    }
}
