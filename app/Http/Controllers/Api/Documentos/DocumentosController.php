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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentosController extends Controller
{
    //=========== CREAR DOCUMENTACION (INICIO) ==========
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
        $fecha_entrega = $datos->fechaEntrega;
        $nombre_etapa = $datos->nombre_etapa;

        //tipo de proyecto en caso que casas sea distinto
        $tipoProyecto_id = $datosUnicos->tipoProyecto_id;


        // Validar si ya existe un documento con este código_documento (debe ser único)
        $validarDocumento = Documentos::where('codigo_documento', $codigo_proyecto_documentos)->first();

        if ($validarDocumento) {
            return response()->json([
                'status' => 'error',
                'message' => 'El código de documento "' . $codigo_proyecto_documentos . '" ya existe. Debe ser único.'
            ], 404);
        }

        // Validar si este proyecto ya tiene registrada esta etapa
        $validarEtapa = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
            ->where('etapa', $etapaData)
            ->first();

        if ($validarEtapa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este proyecto ya tiene una etapa ' . $etapaData . ' registrada'
            ], 404);
        }

        $cronogramaGenerado = null;

        //logica para crear torres o manzanas para activacion retie, por el momento solo emcali
        if ($operador == 1) {
            // Verificar que torres existe y es un array
            if ($request->has('torres') && is_array($request->torres)) {
                foreach ($request->torres as $registro) {
                    // $registro es un string directo, no un array
                    DB::table('documentacion_torres')->insert([
                        'codigo_proyecto' => $codigo_proyecto,
                        'codigo_documento' => $codigo_proyecto_documentos,
                        'nombre_torre' => $registro, // Directamente el valor del array
                        'operador' => $operador,
                        'actividad_id' => 27,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        //OPERADORES DE RED - Usar la función específica según el operador
        if ($operador == 1) { //emcali
            $cronogramaGenerado = $this->generarCronogramaEmcali(
                $codigo_proyecto,
                $codigo_proyecto_documentos,
                $etapa,
                $fecha_entrega,
                $nombre_etapa,
                $etapaData
            );
        } else if ($operador == 2) { //celsia
            $cronogramaGenerado = $this->generarCronogramaCelsia(
                $codigo_proyecto,
                $codigo_proyecto_documentos,
                1, // Para Celsia siempre etapa 1 según tu código original
                $fecha_entrega,
                $nombre_etapa,
                $etapaData
            );
        }

        // Respuesta de éxito
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

    // Generar cronograma para OPERADOR 1 (EMCALI) - Con lógica específica de 237 días para etapa 1 y 113 días para otras etapas
    private function generarCronogramaEmcali($codigo_proyecto, $codigo_proyecto_documentos, $etapa, $fecha_entrega, $nombre_etapa, $etapaData)
    {
        // Consultar actividades desde la base de datos
        $actividades = DB::table('actividades_documentos')
            ->where('etapa', $etapa)
            ->where('operador', 1)
            ->where('estado', 1)
            ->orderBy('id')
            ->get();

        if ($actividades->isEmpty()) {
            return null;
        }

        // Convertir fecha_entrega a Carbon
        try {
            if (is_object($fecha_entrega) && method_exists($fecha_entrega, 'format')) {
                $fechaBase = $fecha_entrega;
            } else if (is_string($fecha_entrega)) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha_entrega);
                } else if (strpos($fecha_entrega, 'T') !== false) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $fecha_entrega);
                } else if (preg_match('/\d{1,2}-[A-Za-z]{3}-\d{2}/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('d-M-y', $fecha_entrega);
                } else {
                    $fechaBase = \Carbon\Carbon::parse($fecha_entrega);
                }
            } else {
                $fechaBase = \Carbon\Carbon::now();
            }
        } catch (\Exception $e) {
            $fechaBase = \Carbon\Carbon::now();
        }

        // Determinar días a restar según la etapa
        $diasQuemados = ($etapa == 1) ? 237 : 113;

        // Calcular fecha de la actividad inicial: fecha_entrega - díasQuemados
        $fechaActual = $fechaBase->copy()->subDays($diasQuemados);

        // Array para almacenar las fechas calculadas por ID de actividad
        $fechasPorActividad = [];

        // MAPA DE DEPENDENCIAS específico para EMCALI
        $dependencias = [
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 2,     // 2 depende de 1, 3-5 dependen de 2
            6 => 3,                              // 6 depende de 3
            7 => 6,                              // 7 depende de 6
            8 => 7,                              // 8 depende de 7
            9 => 8,                              // 9 depende de 8
            10 => 9,                             // 10 depende de 9
            11 => 10,                             // 11 depende de 10
            12 => 11,                             // 12 depende de 11
            13 => 12,                             // 13 depende de 12
            14 => 12,                             // 14 depende de 12 (simultánea con 13)
            15 => 14,                             // 15 depende de 14
            16 => 15,                             // 16 depende de 15
            17 => 16,                             // 17 depende de 16
            18 => 10,                             // 18 depende de 10
            19 => 18,                             // 19 depende de 18
            20 => 19,                             // 20 depende de 19
            21 => 17,                             // 21 depende de 17
            22 => 21,                             // 22 depende de 21
            23 => 22,                             // 23 depende de 22
            24 => 23,                             // 24 depende de 23
            25 => 24,                             // 25 depende de 24
            26 => 25,                             // 26 depende de 25
            27 => 26,                             // 27 depende de 26
            28 => 27,                             // 28 depende de 27
            29 => 28,                             // 29 depende de 28
            30 => 29,                             // 30 depende de 29
            31 => 30,                             // 31 depende de 30
            32 => 31,                             // 32 depende de 31
            33 => 32,                             // 33 depende de 32
            34 => 33,                             // 34 depende de 33
        ];

        // Dependencias para actividades de etapas posteriores (a partir de ID 35)
        // Estas son en cascada simple: 35 no depende de nadie, 36 depende de 35, etc.
        // Se generarán dinámicamente para IDs >= 35

        // Ordenar actividades por ID para procesar secuencialmente
        $actividades = $actividades->sortBy('id');

        //aqui vemos si es etapa 1 o diferente a 1
        if ($etapa == 1) {
            // [TODO EL CÓDIGO EXISTENTE PARA ETAPA 1 SE MANTIENE IGUAL]
            // Procesar actividad 1
            $actividad1 = $actividades->firstWhere('id', 1);
            if ($actividad1) {
                $fechasPorActividad[1] = [
                    'inicio' => $fechaActual->copy(),
                    'fin' => $fechaActual->copy() // tiempo 0 para actividad 1
                ];
            }

            // Procesar actividad 2 (depende de 1)
            $actividad2 = $actividades->firstWhere('id', 2);
            if ($actividad2 && isset($fechasPorActividad[1])) {
                $fechaInicio2 = $fechasPorActividad[1]['inicio']->copy()->addDays($actividad2->tiempo);
                $fechasPorActividad[2] = [
                    'inicio' => $fechaInicio2->copy(),
                    'fin' => $fechaInicio2->copy()->addDays($actividad2->tiempo)
                ];
            }

            // Procesar actividades 3,4,5 (dependen de 2 - MISMA FECHA QUE 2)
            foreach ([3, 4, 5] as $id) {
                $actividad = $actividades->firstWhere('id', $id);
                if ($actividad && isset($fechasPorActividad[2])) {
                    $fechasPorActividad[$id] = [
                        'inicio' => $fechasPorActividad[2]['inicio']->copy(),
                        'fin' => $fechasPorActividad[2]['inicio']->copy()->addDays($actividad->tiempo)
                    ];
                }
            }

            // Procesar actividad 6 (depende de 3)
            $actividad6 = $actividades->firstWhere('id', 6);
            if ($actividad6 && isset($fechasPorActividad[3])) {
                $fechaInicio6 = $fechasPorActividad[3]['inicio']->copy()->addDays($actividad6->tiempo);
                $fechasPorActividad[6] = [
                    'inicio' => $fechaInicio6->copy(),
                    'fin' => $fechaInicio6->copy()->addDays($actividad6->tiempo)
                ];
            }

            // Procesar actividad 7 (depende de 6)
            $actividad7 = $actividades->firstWhere('id', 7);
            if ($actividad7 && isset($fechasPorActividad[6])) {
                $fechaInicio7 = $fechasPorActividad[6]['inicio']->copy()->addDays($actividad7->tiempo);
                $fechasPorActividad[7] = [
                    'inicio' => $fechaInicio7->copy(),
                    'fin' => $fechaInicio7->copy()->addDays($actividad7->tiempo)
                ];
            }

            // Procesar actividad 8 (depende de 7)
            $actividad8 = $actividades->firstWhere('id', 8);
            if ($actividad8 && isset($fechasPorActividad[7])) {
                $fechaInicio8 = $fechasPorActividad[7]['inicio']->copy()->addDays($actividad8->tiempo);
                $fechasPorActividad[8] = [
                    'inicio' => $fechaInicio8->copy(),
                    'fin' => $fechaInicio8->copy()->addDays($actividad8->tiempo)
                ];
            }

            // Procesar actividad 9 (depende de 8)
            $actividad9 = $actividades->firstWhere('id', 9);
            if ($actividad9 && isset($fechasPorActividad[8])) {
                $fechaInicio9 = $fechasPorActividad[8]['inicio']->copy()->addDays($actividad9->tiempo);
                $fechasPorActividad[9] = [
                    'inicio' => $fechaInicio9->copy(),
                    'fin' => $fechaInicio9->copy()->addDays($actividad9->tiempo)
                ];
            }

            // Procesar actividad 10 (depende de 9)
            $actividad10 = $actividades->firstWhere('id', 10);
            if ($actividad10 && isset($fechasPorActividad[9])) {
                $fechaInicio10 = $fechasPorActividad[9]['inicio']->copy()->addDays($actividad10->tiempo);
                $fechasPorActividad[10] = [
                    'inicio' => $fechaInicio10->copy(),
                    'fin' => $fechaInicio10->copy()->addDays($actividad10->tiempo)
                ];
            }

            // Procesar actividad 11 (depende de 10)
            $actividad11 = $actividades->firstWhere('id', 11);
            if ($actividad11 && isset($fechasPorActividad[10])) {
                $fechaInicio11 = $fechasPorActividad[10]['inicio']->copy()->addDays($actividad11->tiempo);
                $fechasPorActividad[11] = [
                    'inicio' => $fechaInicio11->copy(),
                    'fin' => $fechaInicio11->copy()->addDays($actividad11->tiempo)
                ];
            }

            // Procesar actividad 12 (depende de 11)
            $actividad12 = $actividades->firstWhere('id', 12);
            if ($actividad12 && isset($fechasPorActividad[11])) {
                $fechaInicio12 = $fechasPorActividad[11]['inicio']->copy()->addDays($actividad12->tiempo);
                $fechasPorActividad[12] = [
                    'inicio' => $fechaInicio12->copy(),
                    'fin' => $fechaInicio12->copy()->addDays($actividad12->tiempo)
                ];
            }

            // Procesar actividad 13 (depende de 12)
            $actividad13 = $actividades->firstWhere('id', 13);
            if ($actividad13 && isset($fechasPorActividad[12])) {
                $fechaInicio13 = $fechasPorActividad[12]['inicio']->copy()->addDays($actividad13->tiempo);
                $fechasPorActividad[13] = [
                    'inicio' => $fechaInicio13->copy(),
                    'fin' => $fechaInicio13->copy()->addDays($actividad13->tiempo)
                ];
            }

            // Procesar actividad 14 (depende de 12 - SIMULTÁNEA CON 13)
            $actividad14 = $actividades->firstWhere('id', 14);
            if ($actividad14 && isset($fechasPorActividad[12])) {
                $fechaInicio14 = $fechasPorActividad[12]['inicio']->copy()->addDays($actividad14->tiempo);
                $fechasPorActividad[14] = [
                    'inicio' => $fechaInicio14->copy(),
                    'fin' => $fechaInicio14->copy()->addDays($actividad14->tiempo)
                ];
            }

            // Procesar actividad 15 (depende de 14)
            $actividad15 = $actividades->firstWhere('id', 15);
            if ($actividad15 && isset($fechasPorActividad[14])) {
                $fechaInicio15 = $fechasPorActividad[14]['inicio']->copy()->addDays($actividad15->tiempo);
                $fechasPorActividad[15] = [
                    'inicio' => $fechaInicio15->copy(),
                    'fin' => $fechaInicio15->copy()->addDays($actividad15->tiempo)
                ];
            }

            // Procesar actividad 16 (depende de 15)
            $actividad16 = $actividades->firstWhere('id', 16);
            if ($actividad16 && isset($fechasPorActividad[15])) {
                $fechaInicio16 = $fechasPorActividad[15]['inicio']->copy()->addDays($actividad16->tiempo);
                $fechasPorActividad[16] = [
                    'inicio' => $fechaInicio16->copy(),
                    'fin' => $fechaInicio16->copy()->addDays($actividad16->tiempo)
                ];
            }

            // Procesar actividad 17 (depende de 16)
            $actividad17 = $actividades->firstWhere('id', 17);
            if ($actividad17 && isset($fechasPorActividad[16])) {
                $fechaInicio17 = $fechasPorActividad[16]['inicio']->copy()->addDays($actividad17->tiempo);
                $fechasPorActividad[17] = [
                    'inicio' => $fechaInicio17->copy(),
                    'fin' => $fechaInicio17->copy()->addDays($actividad17->tiempo)
                ];
            }

            // Procesar actividad 18 (depende de 10)
            $actividad18 = $actividades->firstWhere('id', 18);
            if ($actividad18 && isset($fechasPorActividad[10])) {
                $fechaInicio18 = $fechasPorActividad[10]['inicio']->copy()->addDays($actividad18->tiempo);
                $fechasPorActividad[18] = [
                    'inicio' => $fechaInicio18->copy(),
                    'fin' => $fechaInicio18->copy()->addDays($actividad18->tiempo)
                ];
            }

            // Procesar actividad 19 (depende de 18)
            $actividad19 = $actividades->firstWhere('id', 19);
            if ($actividad19 && isset($fechasPorActividad[18])) {
                $fechaInicio19 = $fechasPorActividad[18]['inicio']->copy()->addDays($actividad19->tiempo);
                $fechasPorActividad[19] = [
                    'inicio' => $fechaInicio19->copy(),
                    'fin' => $fechaInicio19->copy()->addDays($actividad19->tiempo)
                ];
            }

            // Procesar actividad 20 (depende de 19)
            $actividad20 = $actividades->firstWhere('id', 20);
            if ($actividad20 && isset($fechasPorActividad[19])) {
                $fechaInicio20 = $fechasPorActividad[19]['inicio']->copy()->addDays($actividad20->tiempo);
                $fechasPorActividad[20] = [
                    'inicio' => $fechaInicio20->copy(),
                    'fin' => $fechaInicio20->copy()->addDays($actividad20->tiempo)
                ];
            }

            // Procesar actividad 21 (depende de 17)
            $actividad21 = $actividades->firstWhere('id', 21);
            if ($actividad21 && isset($fechasPorActividad[17])) {
                $fechaInicio21 = $fechasPorActividad[17]['inicio']->copy()->addDays($actividad21->tiempo);
                $fechasPorActividad[21] = [
                    'inicio' => $fechaInicio21->copy(),
                    'fin' => $fechaInicio21->copy()->addDays($actividad21->tiempo)
                ];
            }

            // Procesar actividad 22 (depende de 21)
            $actividad22 = $actividades->firstWhere('id', 22);
            if ($actividad22 && isset($fechasPorActividad[21])) {
                $fechaInicio22 = $fechasPorActividad[21]['inicio']->copy()->addDays($actividad22->tiempo);
                $fechasPorActividad[22] = [
                    'inicio' => $fechaInicio22->copy(),
                    'fin' => $fechaInicio22->copy()->addDays($actividad22->tiempo)
                ];
            }

            // Procesar actividad 23 (depende de 22)
            $actividad23 = $actividades->firstWhere('id', 23);
            if ($actividad23 && isset($fechasPorActividad[22])) {
                $fechaInicio23 = $fechasPorActividad[22]['inicio']->copy()->addDays($actividad23->tiempo);
                $fechasPorActividad[23] = [
                    'inicio' => $fechaInicio23->copy(),
                    'fin' => $fechaInicio23->copy()->addDays($actividad23->tiempo)
                ];
            }

            // Procesar actividad 24 (depende de 23)
            $actividad24 = $actividades->firstWhere('id', 24);
            if ($actividad24 && isset($fechasPorActividad[23])) {
                $fechaInicio24 = $fechasPorActividad[23]['inicio']->copy()->addDays($actividad24->tiempo);
                $fechasPorActividad[24] = [
                    'inicio' => $fechaInicio24->copy(),
                    'fin' => $fechaInicio24->copy()->addDays($actividad24->tiempo)
                ];
            }

            // Procesar actividad 25 (depende de 24)
            $actividad25 = $actividades->firstWhere('id', 25);
            if ($actividad25 && isset($fechasPorActividad[24])) {
                $fechaInicio25 = $fechasPorActividad[24]['inicio']->copy()->addDays($actividad25->tiempo);
                $fechasPorActividad[25] = [
                    'inicio' => $fechaInicio25->copy(),
                    'fin' => $fechaInicio25->copy()->addDays($actividad25->tiempo)
                ];
            }

            // Procesar actividad 26 (depende de 25)
            $actividad26 = $actividades->firstWhere('id', 26);
            if ($actividad26 && isset($fechasPorActividad[25])) {
                $fechaInicio26 = $fechasPorActividad[25]['inicio']->copy()->addDays($actividad26->tiempo);
                $fechasPorActividad[26] = [
                    'inicio' => $fechaInicio26->copy(),
                    'fin' => $fechaInicio26->copy()->addDays($actividad26->tiempo)
                ];
            }

            // Procesar actividad 27 (depende de 26)
            $actividad27 = $actividades->firstWhere('id', 27);
            if ($actividad27 && isset($fechasPorActividad[26])) {
                $fechaInicio27 = $fechasPorActividad[26]['inicio']->copy()->addDays($actividad27->tiempo);
                $fechasPorActividad[27] = [
                    'inicio' => $fechaInicio27->copy(),
                    'fin' => $fechaInicio27->copy()->addDays($actividad27->tiempo)
                ];
            }

            // Procesar actividad 28 (depende de 27)
            $actividad28 = $actividades->firstWhere('id', 28);
            if ($actividad28 && isset($fechasPorActividad[27])) {
                $fechaInicio28 = $fechasPorActividad[27]['inicio']->copy()->addDays($actividad28->tiempo);
                $fechasPorActividad[28] = [
                    'inicio' => $fechaInicio28->copy(),
                    'fin' => $fechaInicio28->copy()->addDays($actividad28->tiempo)
                ];
            }

            // Procesar actividad 29 (depende de 28)
            $actividad29 = $actividades->firstWhere('id', 29);
            if ($actividad29 && isset($fechasPorActividad[28])) {
                $fechaInicio29 = $fechasPorActividad[28]['inicio']->copy()->addDays($actividad29->tiempo);
                $fechasPorActividad[29] = [
                    'inicio' => $fechaInicio29->copy(),
                    'fin' => $fechaInicio29->copy()->addDays($actividad29->tiempo)
                ];
            }

            // Procesar actividad 30 (depende de 29)
            $actividad30 = $actividades->firstWhere('id', 30);
            if ($actividad30 && isset($fechasPorActividad[29])) {
                $fechaInicio30 = $fechasPorActividad[29]['inicio']->copy()->addDays($actividad30->tiempo);
                $fechasPorActividad[30] = [
                    'inicio' => $fechaInicio30->copy(),
                    'fin' => $fechaInicio30->copy()->addDays($actividad30->tiempo)
                ];
            }

            // Procesar actividad 31 (depende de 30)
            $actividad31 = $actividades->firstWhere('id', 31);
            if ($actividad31 && isset($fechasPorActividad[30])) {
                $fechaInicio31 = $fechasPorActividad[30]['inicio']->copy()->addDays($actividad31->tiempo);
                $fechasPorActividad[31] = [
                    'inicio' => $fechaInicio31->copy(),
                    'fin' => $fechaInicio31->copy()->addDays($actividad31->tiempo)
                ];
            }

            // Procesar actividad 32 (depende de 31)
            $actividad32 = $actividades->firstWhere('id', 32);
            if ($actividad32 && isset($fechasPorActividad[31])) {
                $fechaInicio32 = $fechasPorActividad[31]['inicio']->copy()->addDays($actividad32->tiempo);
                $fechasPorActividad[32] = [
                    'inicio' => $fechaInicio32->copy(),
                    'fin' => $fechaInicio32->copy()->addDays($actividad32->tiempo)
                ];
            }

            // Procesar actividad 33 (depende de 32)
            $actividad33 = $actividades->firstWhere('id', 33);
            if ($actividad33 && isset($fechasPorActividad[32])) {
                $fechaInicio33 = $fechasPorActividad[32]['inicio']->copy()->addDays($actividad33->tiempo);
                $fechasPorActividad[33] = [
                    'inicio' => $fechaInicio33->copy(),
                    'fin' => $fechaInicio33->copy()->addDays($actividad33->tiempo)
                ];
            }

            // Procesar actividad 34 (depende de 33)
            $actividad34 = $actividades->firstWhere('id', 34);
            if ($actividad34 && isset($fechasPorActividad[33])) {
                $fechaInicio34 = $fechasPorActividad[33]['inicio']->copy()->addDays($actividad34->tiempo);
                $fechasPorActividad[34] = [
                    'inicio' => $fechaInicio34->copy(),
                    'fin' => $fechaInicio34->copy()->addDays($actividad34->tiempo)
                ];
            }

            // Generar el cronograma para insertar
            $cronograma = [];
            $orden = 1;

            foreach ($actividades as $actividad) {
                $id = $actividad->id;

                // Verificar si la actividad tiene fecha calculada
                if (!isset($fechasPorActividad[$id])) {
                    // Si no tiene fecha, usar la lógica de dependencia
                    $dependenciaId = $dependencias[$id] ?? ($id - 1);

                    if (isset($fechasPorActividad[$dependenciaId])) {
                        $fechaInicio = $fechasPorActividad[$dependenciaId]['inicio']->copy()->addDays($actividad->tiempo);
                        $fechasPorActividad[$id] = [
                            'inicio' => $fechaInicio->copy(),
                            'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                        ];
                    } else {
                        // Fallback: usar la última fecha disponible
                        $ultimaFecha = end($fechasPorActividad);
                        $fechaInicio = $ultimaFecha['inicio']->copy()->addDays($actividad->tiempo);
                        $fechasPorActividad[$id] = [
                            'inicio' => $fechaInicio->copy(),
                            'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                        ];
                    }
                }

                // Obtener fecha calculada
                $fechaActividad = $fechasPorActividad[$id]['inicio'];

                // Determinar dependencia para el registro
                $dependenciaId = $dependencias[$id] ?? null;
                if (!$dependenciaId && $id > 1) {
                    $dependenciaId = $id - 1;
                }

                // SOLO el primer registro (orden 1) tiene estado 1, los demás estado 0
                $estado = ($orden == 1) ? 1 : 0;

                $registro = [
                    'nombre_etapa' => $nombre_etapa,
                    'codigo_proyecto' => $codigo_proyecto,
                    'codigo_documento' => $codigo_proyecto_documentos,
                    'etapa' => $etapaData,
                    'actividad_id' => $actividad->id,
                    'actividad_depende_id' => $dependenciaId,
                    'tipo' => ($actividad->tipo == 1) ? 'principal' : 'simultanea',
                    'orden' => $orden,
                    'fecha_proyeccion' => $fechaActividad->format('Y-m-d'),
                    'fecha_actual' => $fechaActividad->format('Y-m-d'),
                    'estado' => $estado,
                    'operador' => 1,
                    'created_at' => now(),
                ];

                $cronograma[] = $registro;
                $orden++;
            }
        } else {
            // PARA ETAPAS DIFERENTES A 1 (etapa 2, 3, etc.)
            // Iniciamos desde el ID 35 con dependencia en cascada

            // Encontrar la primera actividad (debe ser ID 35 o el mínimo ID en la consulta)
            $actividadesFiltradas = $actividades->filter(function ($actividad) {
                return $actividad->id >= 35;
            })->values();

            if ($actividadesFiltradas->isEmpty()) {
                return null;
            }

            // Ordenar por ID para procesar en cascada
            $actividadesFiltradas = $actividadesFiltradas->sortBy('id');

            // Variables para seguimiento
            $ultimoId = null;
            $cronograma = [];
            $orden = 1;

            foreach ($actividadesFiltradas as $actividad) {
                $id = $actividad->id;

                // Calcular fecha según dependencia
                if ($ultimoId === null) {
                    // Es la primera actividad (ID 35 o el mínimo) - No depende de nadie
                    $fechaInicio = $fechaActual->copy();
                    $dependenciaId = null;
                } else {
                    // Depende de la actividad anterior en cascada
                    $fechaInicio = $fechasPorActividad[$ultimoId]['inicio']->copy()->addDays($actividad->tiempo);
                    $dependenciaId = $ultimoId;
                }

                // Guardar fecha calculada
                $fechasPorActividad[$id] = [
                    'inicio' => $fechaInicio->copy(),
                    'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                ];

                // SOLO el primer registro (orden 1) tiene estado 1, los demás estado 0
                $estado = ($orden == 1) ? 1 : 0;

                $registro = [
                    'nombre_etapa' => $nombre_etapa,
                    'codigo_proyecto' => $codigo_proyecto,
                    'codigo_documento' => $codigo_proyecto_documentos,
                    'etapa' => $etapaData,
                    'actividad_id' => $actividad->id,
                    'actividad_depende_id' => $dependenciaId,
                    'tipo' => ($actividad->tipo == 1) ? 'principal' : 'simultanea',
                    'orden' => $orden,
                    'fecha_proyeccion' => $fechaInicio->format('Y-m-d'),
                    'fecha_actual' => $fechaInicio->format('Y-m-d'),
                    'estado' => $estado,
                    'operador' => 1,
                    'created_at' => now(),
                ];

                $cronograma[] = $registro;
                $ultimoId = $id;
                $orden++;
            }
        }

        // Insertar en la base de datos
        try {
            foreach ($cronograma as $registro) {
                DB::table('documentacion_operadores')->insert($registro);
            }
        } catch (\Exception $e) {
            Log::error('Error insertando cronograma EMCALI: ' . $e->getMessage());
            throw $e;
        }

        return $cronograma;
    }

    // Generar cronograma para OPERADOR 2 (CELSIA) - Con nueva lógica de dependencias
    private function generarCronogramaCelsia($codigo_proyecto, $codigo_proyecto_documentos, $etapa, $fecha_entrega, $nombre_etapa, $etapaData)
    {
        // Consultar actividades desde la base de datos
        $actividades = DB::table('actividades_documentos')
            ->where('etapa', $etapa)
            ->where('operador', 2)
            ->where('estado', 1)
            ->orderBy('id')
            ->get();

        if ($actividades->isEmpty()) {
            return null;
        }

        // Convertir fecha_entrega a Carbon
        try {
            if (is_object($fecha_entrega) && method_exists($fecha_entrega, 'format')) {
                $fechaBase = $fecha_entrega;
            } else if (is_string($fecha_entrega)) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha_entrega);
                } else if (strpos($fecha_entrega, 'T') !== false) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i:s.v\Z', $fecha_entrega);
                } else if (preg_match('/\d{1,2}-[A-Za-z]{3}-\d{2}/', $fecha_entrega)) {
                    $fechaBase = \Carbon\Carbon::createFromFormat('d-M-y', $fecha_entrega);
                } else {
                    $fechaBase = \Carbon\Carbon::parse($fecha_entrega);
                }
            } else {
                $fechaBase = \Carbon\Carbon::now();
            }
        } catch (\Exception $e) {
            $fechaBase = \Carbon\Carbon::now();
        }

        // DÍAS QUEMADOS para CELSIA (siempre 81 días)
        $diasQuemados = 81;

        // Calcular fecha de la actividad inicial: fecha_entrega - 81 días
        $fechaActual = $fechaBase->copy()->subDays($diasQuemados);

        // Array para almacenar las fechas calculadas por ID de actividad
        $fechasPorActividad = [];

        // MAPA DE DEPENDENCIAS específico para CELSIA según requerimiento
        $dependencias = [
            // 43 es la primera actividad, no depende de nadie
            44 => 43,
            45 => 44,
            46 => 45,
            47 => 46,
            48 => 47,
            49 => 48,
            50 => 46,      // 50 depende de 46 
            51 => 50,
            52 => 51,
            53 => 52,
            54 => 53,
            // Del 55 al 66 dependen de 52
            55 => 52,
            56 => 52,
            57 => 52,
            58 => 52,
            59 => 52,
            60 => 52,
            61 => 52,
            62 => 52,
            63 => 52,
            64 => 52,
            65 => 52,
            66 => 52,


            67 => 66,
            68 => 67,
            69 => 68,
            70 => 69,
            71 => 70,
            72 => 71,
            73 => 72,
        ];

        // Ordenar actividades por ID para procesar secuencialmente
        $actividades = $actividades->sortBy('id');

        // Procesar actividad 43 (primera actividad, no depende de nadie)
        $actividad43 = $actividades->firstWhere('id', 43);
        if ($actividad43) {
            $fechasPorActividad[43] = [
                'inicio' => $fechaActual->copy(),
                'fin' => $fechaActual->copy()->addDays($actividad43->tiempo)
            ];
        }

        // Procesar las demás actividades según sus dependencias
        $idsProcesados = [43];
        $iteraciones = 0;
        $maxIteraciones = 100; // Evitar bucle infinito

        while (count($idsProcesados) < $actividades->count() && $iteraciones < $maxIteraciones) {
            $iteraciones++;

            foreach ($actividades as $actividad) {
                $id = $actividad->id;

                // Si ya fue procesado, continuar
                if (in_array($id, $idsProcesados)) {
                    continue;
                }

                // Verificar si tiene dependencia definida
                if (!isset($dependencias[$id])) {
                    continue;
                }

                $dependenciaId = $dependencias[$id];

                // Si la dependencia ya fue procesada, calcular fecha
                if (in_array($dependenciaId, $idsProcesados)) {
                    $fechaInicio = $fechasPorActividad[$dependenciaId]['inicio']->copy()->addDays($actividad->tiempo);
                    $fechasPorActividad[$id] = [
                        'inicio' => $fechaInicio->copy(),
                        'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                    ];
                    $idsProcesados[] = $id;
                }
            }
        }

        // Generar el cronograma para insertar
        $cronograma = [];
        $orden = 1;

        foreach ($actividades as $actividad) {
            $id = $actividad->id;

            // Verificar si la actividad tiene fecha calculada
            if (!isset($fechasPorActividad[$id])) {
                // Si no tiene fecha, usar la lógica de dependencia
                $dependenciaId = $dependencias[$id] ?? null;

                if ($dependenciaId && isset($fechasPorActividad[$dependenciaId])) {
                    $fechaInicio = $fechasPorActividad[$dependenciaId]['inicio']->copy()->addDays($actividad->tiempo);
                    $fechasPorActividad[$id] = [
                        'inicio' => $fechaInicio->copy(),
                        'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                    ];
                } else {
                    // Fallback: usar la última fecha disponible
                    $ultimaFecha = end($fechasPorActividad);
                    $fechaInicio = $ultimaFecha['inicio']->copy()->addDays($actividad->tiempo);
                    $fechasPorActividad[$id] = [
                        'inicio' => $fechaInicio->copy(),
                        'fin' => $fechaInicio->copy()->addDays($actividad->tiempo)
                    ];
                }
            }

            // Obtener fecha calculada
            $fechaActividad = $fechasPorActividad[$id]['inicio'];

            // Determinar dependencia para el registro
            $dependenciaId = $dependencias[$id] ?? null;

            // SOLO el primer registro (orden 1) tiene estado 1, los demás estado 0
            $estado = ($orden == 1) ? 1 : 0;

            $registro = [
                'nombre_etapa' => $nombre_etapa,
                'codigo_proyecto' => $codigo_proyecto,
                'codigo_documento' => $codigo_proyecto_documentos,
                'etapa' => $etapaData,
                'actividad_id' => $actividad->id,
                'actividad_depende_id' => $dependenciaId,
                'tipo' => ($actividad->tipo == 1) ? 'principal' : 'simultanea',
                'orden' => $orden,
                'fecha_proyeccion' => $fechaActividad->format('Y-m-d'),
                'fecha_actual' => $fechaActividad->format('Y-m-d'),
                'estado' => $estado,
                'operador' => 2,
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
            Log::error('Error insertando cronograma CELSIA: ' . $e->getMessage());
            throw $e;
        }

        return $cronograma;
    }

    private function CreartTm($data) //sigue siendo el mismo
    {
        $codigoProyecto   = $data->codigo_proyecto;
        $codigoDocumento  = $data->codigoDocumentos;
        $usuarioId        = $data->usuarioId;
        $cantidadTm       = (int) $data->cantidad_tm;
        $organismos       = $data->organismoInspeccion;


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

        DB::table('torres_documentacion_organismos')->insert($registrosCreados);

        return [
            'success' => true,
            'total_registros' => count($registrosCreados),
        ];
    }
    //=========== CREAR DOCUMENTACION (FIN) ==========











    //============== GET DE DOCUMENTOS ==============

    //version para finish y llevar colores de estado de actividades
    public function indexEmcali()
    {
        $usuario = Auth::user();
        $rolesPermitidos = ['Directora Proyectos', 'Tramites', 'Administrador'];
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

        // Procesar los datos para agregar el campo 'finish'
        $data = $data->map(function ($proyecto) {
            // Verificar si el proyecto tiene documentación
            if ($proyecto->documentacion && count($proyecto->documentacion) > 0) {
                // Procesar cada etapa/documento
                $proyecto->documentacion = $proyecto->documentacion->map(function ($documento) {
                    // Verificar el estado de TODAS las actividades relacionadas
                    $actividades = Documentos::where('codigo_proyecto', $documento->codigo_proyecto)
                        ->where('codigo_documento', $documento->codigo_documento)
                        ->where('etapa', $documento->etapa)
                        ->where('operador', $documento->operador)
                        ->get();

                    // Verificar si TODAS las actividades están en estado 2
                    $todasEstado2 = $actividades->every(function ($actividad) {
                        return $actividad->estado == 2;
                    });

                    // Si TODAS son estado 2, finish = true, de lo contrario false
                    $documento->finish = $todasEstado2;

                    return $documento;
                });
            }

            return $proyecto;
        })->sortBy('codigo_proyecto')->values();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //version para finish y llevar colores de estado de actividades
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

            $proyectosAsignadosTotales = array_merge($proyectosAsignados, $proyectosCasaAsignados);

            if (!empty($proyectosAsignadosTotales)) {
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

        // Procesar los datos para agregar el campo 'finish'
        $data = $data->map(function ($proyecto) {
            // Verificar si el proyecto tiene documentación
            if ($proyecto->documentacion && count($proyecto->documentacion) > 0) {
                // Procesar cada etapa/documento
                $proyecto->documentacion = $proyecto->documentacion->map(function ($documento) {
                    // Verificar el estado de TODAS las actividades relacionadas
                    $actividades = Documentos::where('codigo_proyecto', $documento->codigo_proyecto)
                        ->where('codigo_documento', $documento->codigo_documento)
                        ->where('etapa', $documento->etapa)
                        ->where('operador', $documento->operador)
                        ->get();

                    // Verificar si TODAS las actividades están en estado 2
                    $todasEstado2 = $actividades->every(function ($actividad) {
                        return $actividad->estado == 2;
                    });

                    // Si TODAS son estado 2, finish = true, de lo contrario false
                    $documento->finish = $todasEstado2;

                    return $documento;
                });
            }

            return $proyecto;
        })->sortBy('codigo_proyecto')->values();

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

        $data = $data->sortBy('codigo_proyecto')->values();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //se envia codigo de proyecto pero se cambia por codigo del documento
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
    //==================== GET DE DOCUMENTOS (FIN) =====================











    //=========== LOGICA DE FLUJO PARA CONFIRMAR ACTIVIDADES EMCALI INICIO =========
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
                'observacion' => 'string',
                'fecha_confirmacion' => 'required|date_format:Y-m-d',
                'archivos' => 'array',
                'archivos.*' => 'file|mimes:jpg,jpeg,png,pdf|max:1048576',
            ]);

            // 1. Guardar archivos si existen
            $archivosGuardados = $this->guardarArchivos($request);

            // 2. Obtener la actividad actual
            $actividadActual = Documentos::find($request->id);

            // 3. Calcular diferencia de días y generar texto descriptivo
            $fechaProyeccion = Carbon::parse($actividadActual->fecha_proyeccion)->startOfDay();
            $fechaConfirmacion = Carbon::parse($request->fecha_confirmacion)->startOfDay();

            // Calcular diferencia absoluta en días
            $diasDiferencia = abs($fechaConfirmacion->diffInDays($fechaProyeccion));

            // Generar texto descriptivo según el caso
            if ($fechaConfirmacion->lt($fechaProyeccion)) {
                // Confirmación ANTES de lo planificado
                $textoDiferencia = $diasDiferencia . " días antes";
            } elseif ($fechaConfirmacion->gt($fechaProyeccion)) {
                // Confirmación DESPUÉS de lo planificado (retraso)
                $textoDiferencia = $diasDiferencia . " días de retraso";
            } else {
                // Mismo día
                $textoDiferencia = "Justo a tiempo";
            }

            // 4. Actualizar SOLO estado y fecha_confirmacion (NO TOCAR fecha_actual)
            $actividadActual->update([
                'estado' => 2, // Completado
                'observacion' => $request->observacion != "." ? $request->observacion : "Sin observación",
                'fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                'diferenciaDias' => $textoDiferencia, // Guardar texto descriptivo
                'usuario_id' => Auth::id(),
            ]);

            // Log para verificar
            Log::info('Diferencia calculada:', [
                'proyeccion' => $fechaProyeccion->format('Y-m-d'),
                'confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                'dias' => $diasDiferencia,
                'texto' => $textoDiferencia
            ]);

            // 5. Aplicar lógica de habilitación según etapa (SOLO CAMBIA ESTADOS)
            if ($request->etapa == 1) {
                $this->aplicarLogicaEtapa1(
                    $request->codigo_proyecto,
                    $request->codigo_documento,
                    $request->etapa,
                    $request->actividad_id
                );
            } else {
                $this->aplicarLogicaCascada(
                    $request->codigo_proyecto,
                    $request->codigo_documento,
                    $request->etapa,
                    $actividadActual
                );
            }

            // 6. Respuesta con el texto descriptivo
            return response()->json([
                'status' => 'success',
                'message' => 'Actividad confirmada exitosamente',
                'data' => [
                    'actual' => [
                        'id' => $actividadActual->id,
                        'actividad_id' => $actividadActual->actividad_id,
                        'estado' => 2,
                        'fecha_proyeccion' => $actividadActual->fecha_proyeccion,
                        'fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                        'diferenciaDias' => $textoDiferencia, // Texto descriptivo
                    ],
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

    // LOGICA ETAPA 1 - SOLO CAMBIA ESTADOS, NO FECHAS
    private function aplicarLogicaEtapa1($codigo_proyecto, $codigo_documento, $etapa, $actividad_id)
    {
        // Reglas de habilitación para etapa 1
        $reglas = [
            // Actividad 1 completada -> habilita 2,3,4,5
            1 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [2, 3, 4, 5]);
            },

            // Actividad 2 completada -> verifica para actividad 8 (con 7)
            2 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->verificarActivacionMultiple($codigo_proyecto, $codigo_documento, $etapa, 8, [2, 7]);
            },

            // Actividad 3 completada -> habilita 6
            3 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [6]);
            },

            // Actividad 4 completada -> verifica para actividad 14 (con 12)
            4 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->verificarActivacionMultiple($codigo_proyecto, $codigo_documento, $etapa, 14, [4, 12]);
            },

            // Actividad 6 completada -> habilita 7
            6 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [7]);
            },

            // Actividad 7 completada -> verifica para actividad 8 (con 2)
            7 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->verificarActivacionMultiple($codigo_proyecto, $codigo_documento, $etapa, 8, [2, 7]);
            },

            // Actividad 8 completada -> habilita 9
            8 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [9]);
            },

            // Actividad 9 completada -> habilita 10
            9 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [10]);
            },

            // Actividad 10 completada -> habilita 11 y 18
            10 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [11, 18]);
            },

            // Actividad 11 completada -> habilita 12
            11 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [12]);
            },

            // Actividad 12 completada -> habilita 13 y verifica para 14 (con 4)
            12 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [13]);
                $this->verificarActivacionMultiple($codigo_proyecto, $codigo_documento, $etapa, 14, [4, 12]);
            },

            // Actividad 13 completada -> (no activa nada directamente según especificación)
            13 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // No activa nada directamente, pero puede ser necesaria para otras validaciones
            },

            // Actividad 14 completada -> habilita 15
            14 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [15]);
            },

            // Actividad 15 completada -> habilita 16
            15 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [16]);
            },

            // Actividad 16 completada -> habilita 17
            16 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [17]);
            },

            // Actividad 17 completada -> habilita 21
            17 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [21]);
            },

            // Actividad 18 completada -> habilita 19
            18 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [19]);
            },

            // Actividad 19 completada -> habilita 20
            19 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [20]);
            },

            // Actividad 20 completada -> (no activa nada según especificación)
            20 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // No activa nada directamente
            },

            // Actividad 21 completada -> habilita 22
            21 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [22]);
            },

            // Actividad 22 completada -> habilita 23
            22 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [23]);
            },

            // Actividad 23 completada -> habilita 24
            23 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [24]);
            },

            // Actividad 24 completada -> habilita 25
            24 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [25]);
            },

            // Actividad 25 completada -> habilita 26
            25 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [26]);
            },

            // Actividad 26 completada -> (no activa nada según especificación)
            26 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // No activa nada directamente
            },

            // Actividad 27 completada -> habilita 28
            27 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [28]);
            },

            // Actividad 28 completada -> habilita 29
            28 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [29]);
            },

            // Actividad 29 completada -> habilita 30
            29 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [30]);
            },

            // Actividad 30 completada -> habilita 31
            30 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [31]);
            },

            // Actividad 31 completada -> habilita 32
            31 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [32]);
            },

            // Actividad 32 completada -> habilita 33
            32 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [33]);
            },

            // Actividad 33 completada -> habilita 34
            33 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [34]);
            },

            // Actividad 34 completada -> (última actividad, no activa nada)
            34 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // Actividad final, no activa nada más
            },
        ];

        // Ejecutar regla si existe
        if (isset($reglas[$actividad_id])) {
            $reglas[$actividad_id]();
        }
    }

    // LOGICA ETAPA 2 (CASCADA SIMPLE)
    private function aplicarLogicaCascada($codigo_proyecto, $codigo_documento, $etapa, $actividadActual)
    {
        // Reglas de habilitación para etapa 2 (cascada simple)
        $reglasCascada = [
            // 35 no depende de nadie (ya debería estar habilitada al crear)
            35 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [36]);
            },

            // 36 depende de 35
            36 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [37]);
            },

            // 37 depende de 36
            37 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [38]);
            },

            // 38 depende de 37
            38 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [39]);
            },

            // 39 depende de 38
            39 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [40]);
            },

            // 40 depende de 39
            40 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [41]);
            },

            // 41 depende de 40
            41 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [42]);
            },

            // 42 depende de 41 (última actividad)
            42 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // Actividad final, no activa nada más
            },
        ];

        // Verificar si es etapa 2 y si existe regla para la actividad actual
        if ($etapa == 2 && isset($reglasCascada[$actividadActual->actividad_id])) {
            $reglasCascada[$actividadActual->actividad_id]();
        } else {
            // Para otras etapas o actividades sin regla específica, usar lógica por orden
            $siguienteActividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('orden', $actividadActual->orden + 1)
                ->first();

            if ($siguienteActividad && $siguienteActividad->estado == 0) {
                $siguienteActividad->update([
                    'estado' => 1, // Habilitada
                ]);
            }
        }
    }

    // HABILITAR ACTIVIDADES - SOLO CAMBIA ESTADO (sin modificar)
    private function habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, $actividades_ids)
    {
        foreach ($actividades_ids as $actividad_id) {
            Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_id', $actividad_id)
                ->where('estado', 0)
                ->update([
                    'estado' => 1, // Habilitada
                    // NO SE ACTUALIZA NINGUNA FECHA
                ]);
        }
    }

    // VERIFICAR ACTIVACIÓN MÚLTIPLE - SOLO VERIFICA ESTADOS (sin modificar)
    private function verificarActivacionMultiple($codigo_proyecto, $codigo_documento, $etapa, $actividad_a_activar, $actividades_requeridas)
    {
        // Contar cuántas actividades requeridas están completadas (estado 2)
        $completadas = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->whereIn('actividad_id', $actividades_requeridas)
            ->where('estado', 2)
            ->count();

        // Si todas las requeridas están completadas, activar la siguiente
        if ($completadas == count($actividades_requeridas)) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [$actividad_a_activar]);
        }
    }

    // GUARDAR ARCHIVOS - IGUAL
    private function guardarArchivos(Request $request)
    {
        if (!$request->hasFile('archivos')) {
            return [];
        }

        $archivosGuardados = [];

        foreach ($request->file('archivos') as $archivo) {
            $nombreArchivo = $request->codigo_proyecto . '-' .
                $request->codigo_documento . '-' .
                $request->etapa . '-' .
                $request->actividad_id . '-' .
                time() . '-' .
                Str::random(10) . '.' .
                $archivo->getClientOriginalExtension();

            $archivo->storeAs('public/documentacion/red', $nombreArchivo);
            $rutaPublica = Storage::url('documentacion/red/' . $nombreArchivo);

            DocumentosAdjuntos::create([
                'documento_id' => $request->id,
                'ruta_archivo' => $rutaPublica,
                'nombre_original' => $archivo->getClientOriginalName(),
                'extension' => $archivo->getClientOriginalExtension(),
                'tamano' => $archivo->getSize(),
            ]);

            $archivosGuardados[] = [
                'nombre' => $archivo->getClientOriginalName(),
                'ruta' => $rutaPublica,
            ];
        }

        return $archivosGuardados;
    }
    //================== LOGICA DE FLUJO PARA CONFIRMAR ACTIVIDADES EMCALI FIN =================











    //================== LOGICA DE DOCUMENTOS DE ORGANISMOS ====================
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

    public function estadoTramitesAdmin()
    {
        $documentos = Documentos::select('codigo_proyecto', 'etapa', 'estado', 'operador', 'nombre_etapa')->get();
        $organismos = DocumentosOrganismos::select('codigo_proyecto', 'estado', 'operador', 'nombre_etapa')->get();

        // Función para documentos (agrupado por proyecto y etapa)
        $calcularPorcentajesDocumentos = function ($coleccion) {
            return $coleccion->groupBy('codigo_proyecto')->map(function ($items, $proyecto) {
                // Agrupar por etapas para calcular porcentajes por etapa
                $etapas = $items->groupBy('etapa')->map(function ($itemsEtapa, $etapa) {
                    $total = $itemsEtapa->count();
                    $completos = $itemsEtapa->where('estado', 2)->count();

                    // Obtener el nombre_etapa del primer registro (todos deberían tener el mismo)
                    $primerItem = $itemsEtapa->first();
                    $nombreEtapa = $primerItem ? $primerItem->nombre_etapa : "Etapa {$etapa}";

                    return [
                        'etapa' => $etapa,
                        'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
                        'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
                        'total' => $total,
                        'completados' => $completos,
                        'pendientes' => $total - $completos,
                        'info' => $nombreEtapa  // Agregado: nombre de la etapa
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

                    // Obtener el nombre_etapa del primer registro
                    $primerItem = $items->first();
                    $nombreEtapa = $primerItem ? $primerItem->nombre_etapa : "Etapa General";

                    $proyectoData['operadores'][] = [
                        'operador' => $operador,
                        'avance' => $total > 0 ? round(($completos / $total) * 100, 2) : 0,
                        'atrazo' => $total > 0 ? round((($total - $completos) / $total) * 100, 2) : 0,
                        'total' => $total,
                        'completados' => $completos,
                        'pendientes' => $total - $completos,
                        'info' => $nombreEtapa  // Agregado: nombre de la etapa
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
    //================== LOGICA DE DOCUMENTOS DE ORGANISMOS FIN ====================











    // ==================== CONFRIMAR DOCUMENTOS CELSIA INICIO ====================
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
                'observacion' => 'string',
                'fecha_confirmacion' => 'required|date_format:Y-m-d', // Agregar validación de fecha
                'archivos' => 'array',
                'archivos.*' => 'file|mimes:jpg,jpeg,png,pdf|max:1048576', // 1GB
            ]);

            // 1. Guardar archivos si existen
            $archivosGuardados = $this->guardarArchivos($request);

            // 2. Obtener la actividad actual
            $actividadActual = Documentos::find($request->id);

            // 3. Calcular diferencia de días y generar texto descriptivo
            $fechaProyeccion = Carbon::parse($actividadActual->fecha_proyeccion)->startOfDay();
            $fechaConfirmacion = Carbon::parse($request->fecha_confirmacion)->startOfDay();

            // Calcular diferencia absoluta en días
            $diasDiferencia = abs($fechaConfirmacion->diffInDays($fechaProyeccion));

            // Generar texto descriptivo según el caso
            if ($fechaConfirmacion->lt($fechaProyeccion)) {
                // Confirmación ANTES de lo planificado
                $textoDiferencia = $diasDiferencia . " días antes";
            } elseif ($fechaConfirmacion->gt($fechaProyeccion)) {
                // Confirmación DESPUÉS de lo planificado (retraso)
                $textoDiferencia = $diasDiferencia . " días de retraso";
            } else {
                // Mismo día
                $textoDiferencia = "Justo a tiempo";
            }

            // 4. Actualizar SOLO estado y fecha_confirmacion (NO TOCAR fecha_actual)
            $actividadActual->update([
                'estado' => 2, // Completado
                'observacion' => $request->observacion != "." ? $request->observacion : "Sin observación",
                'fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                'diferenciaDias' => $textoDiferencia, // Guardar texto descriptivo
                'usuario_id' => Auth::id(),
            ]);

            // Log para verificar
            Log::info('Diferencia calculada CELSIA:', [
                'proyeccion' => $fechaProyeccion->format('Y-m-d'),
                'confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                'dias' => $diasDiferencia,
                'texto' => $textoDiferencia
            ]);

            // 5. Aplicar lógica de habilitación para CELSIA (SOLO CAMBIA ESTADOS)
            $this->aplicarLogicaCelsia(
                $request->codigo_proyecto,
                $request->codigo_documento,
                $request->etapa,
                $request->actividad_id
            );

            // 6. Respuesta con el texto descriptivo
            return response()->json([
                'status' => 'success',
                'message' => 'Actividad confirmada exitosamente',
                'data' => [
                    'actual' => [
                        'id' => $actividadActual->id,
                        'actividad_id' => $actividadActual->actividad_id,
                        'estado' => 2,
                        'fecha_proyeccion' => $actividadActual->fecha_proyeccion,
                        'fecha_confirmacion' => $fechaConfirmacion->format('Y-m-d'),
                        'diferenciaDias' => $textoDiferencia,
                    ],
                    'archivos' => $archivosGuardados,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error confirmando actividad CELSIA: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // LOGICA CELSIA - NUEVAS DEPENDENCIAS
    private function aplicarLogicaCelsia($codigo_proyecto, $codigo_documento, $etapa, $actividad_id)
    {
        // Reglas de habilitación para CELSIA según nuevas dependencias
        $reglas = [
            // 43 activa 44
            43 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [44]);
            },

            // 44 activa 45
            44 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [45]);
            },

            // 45 activa 46
            45 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [46]);
            },

            // 46 activa 47 y 50
            46 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [47, 50]);
            },

            // 47 activa 48
            47 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [48]);
            },

            // 48 activa 49
            48 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [49]);
            },

            // 50 activa 51
            50 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [51]);
            },

            // 51 activa 52
            51 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [52]);
            },

            // 52 activa 53 y todas las del 55 al 66
            52 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [53]);
                $this->habilitarActividadesRango($codigo_proyecto, $codigo_documento, $etapa, 55, 66);
            },

            // 53 activa 54
            53 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [54]);
            },

            // 66 activa 67
            66 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [67]);
            },

            // 67 activa 68
            67 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [68]);
            },

            // 68 activa 69
            68 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [69]);
            },

            // 69 activa 70
            69 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [70]);
            },

            // 70 y bloque 55-65 activan 71 (verificación múltiple)
            70 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->verificarActivacion71($codigo_proyecto, $codigo_documento, $etapa);
            },

            // 71 activa 72
            71 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [72]);
            },

            // 72 activa 73
            72 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [73]);
            },

            // 73 es última actividad, no activa nada
            73 => function () use ($codigo_proyecto, $codigo_documento, $etapa) {
                // Actividad final
            },
        ];

        // Ejecutar regla si existe
        if (isset($reglas[$actividad_id])) {
            $reglas[$actividad_id]();
        }

        // Verificar actividades que dependen de múltiples fuentes (55-65)
        if (in_array($actividad_id, range(55, 65))) {
            $this->verificarActivacion71($codigo_proyecto, $codigo_documento, $etapa);
        }
    }

    // Función para habilitar un rango de actividades
    private function habilitarActividadesRango($codigo_proyecto, $codigo_documento, $etapa, $inicio, $fin)
    {
        $actividadesIds = range($inicio, $fin);
        $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, $actividadesIds);
    }

    // Función específica para verificar activación de actividad 71
    private function verificarActivacion71($codigo_proyecto, $codigo_documento, $etapa)
    {
        // Verificar si 70 está completada
        $actividad70Completa = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('actividad_id', 70)
            ->where('estado', 2)
            ->exists();

        if (!$actividad70Completa) {
            return;
        }

        // Verificar si todas las actividades del 55 al 65 están completadas
        $actividadesBloque = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->whereBetween('actividad_id', [55, 65])
            ->get();

        $todasCompletas = $actividadesBloque->every(function ($actividad) {
            return $actividad->estado == 2;
        });

        // Si ambas condiciones se cumplen, activar 71
        if ($todasCompletas) {
            $this->habilitarActividades($codigo_proyecto, $codigo_documento, $etapa, [71]);
        }
    }
    // ==================== CONFRIMAR DOCUMENTOS CELSIA FIN ====================











    // ==================== LÓGICA ANULACIÓN EMCALI ====================
    private function eliminarArchivosAdjuntos($documento_id)
    {
        $archivos = DocumentosAdjuntos::where('documento_id', $documento_id)->get();

        foreach ($archivos as $archivo) {
            // Extraer nombre del archivo de la ruta
            $rutaArchivo = str_replace('/storage/', 'public/', $archivo->ruta_archivo);

            // Eliminar del storage
            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            }

            // Eliminar registro de la base de datos
            $archivo->delete();
        }
    }

    // metodos auxiliares para anulacion de documentos Celsia
    private function anularActividadesDependientes($codigo_proyecto, $codigo_documento, $etapa, $actividades_ids)
    {
        foreach ($actividades_ids as $actividad_id) {
            $actividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_id', $actividad_id)
                ->where('estado', '!=', 0)
                ->first();

            if ($actividad) {
                $actividad->update([
                    'estado' => 0, // Solo las dependencias quedan en estado 0
                    'fecha_confirmacion' => null,
                    'fecha_actual' => $actividad->fecha_proyeccion,
                ]);

                // Eliminar archivos adjuntos
                $this->eliminarArchivosAdjuntos($actividad->id);
            }
        }
    }

    // LOGICA ANULACION ETAPA 1 - ACTUALIZADA CON NUEVAS DEPENDENCIAS
    private function aplicarLogicaAnulacionEtapa1($codigo_proyecto, $codigo_documento, $etapa, $actividad_id)
    {
        // Mapeo de dependencias según la nueva lógica
        $dependencias = [
            1 => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            2 => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            3 => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            4 => [14, 15, 16, 17, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34], // 4 afecta a 14 en adelante
            5 => [], // Sin dependientes
            6 => [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            7 => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            8 => [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            9 => [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            10 => [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            11 => [12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            12 => [13, 14, 15, 16, 17, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34], // 12 afecta a 13 y 14 en adelante
            13 => [], // No tiene dependientes directos según nueva lógica
            14 => [15, 16, 17, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            15 => [16, 17, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            16 => [17, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            17 => [21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            18 => [19, 20], // 18 solo afecta a 19 y 20
            19 => [20], // 19 solo afecta a 20
            20 => [], // 20 no tiene dependientes
            21 => [22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            22 => [23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            23 => [24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            24 => [25, 26, 27, 28, 29, 30, 31, 32, 33, 34],
            25 => [26, 27, 28, 29, 30, 31, 32, 33, 34],
            26 => [27, 28, 29, 30, 31, 32, 33, 34], // 26 afecta desde 27
            27 => [28, 29, 30, 31, 32, 33, 34],
            28 => [29, 30, 31, 32, 33, 34],
            29 => [30, 31, 32, 33, 34],
            30 => [31, 32, 33, 34],
            31 => [32, 33, 34],
            32 => [33, 34],
            33 => [34],
            34 => [], // Última actividad
        ];

        // Si la actividad tiene dependientes, anularlas
        if (isset($dependencias[$actividad_id]) && !empty($dependencias[$actividad_id])) {
            $this->anularActividadesDependientes(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $dependencias[$actividad_id]
            );
        }

        // Verificar actividades que dependen de múltiples fuentes
        $this->verificarDependenciasMultiplesAnulacion(
            $codigo_proyecto,
            $codigo_documento,
            $etapa,
            $actividad_id
        );
    }

    // LOGICA ANULACION ETAPA 2 (CASCADA)
    private function aplicarLogicaAnulacionCascada($codigo_proyecto, $codigo_documento, $etapa, $actividadAnular)
    {
        // Para etapa 2, anular todas las actividades siguientes en orden
        $actividadesSiguientes = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('orden', '>', $actividadAnular->orden)
            ->where('estado', '!=', 0)
            ->orderBy('orden')
            ->get();

        foreach ($actividadesSiguientes as $actividad) {
            $actividad->update([
                'estado' => 0,
                'fecha_confirmacion' => null,
                'diferenciaDias' => null,
                'observacion' => null,
                'usuario_id' => null,
                'fecha_actual' => $actividad->fecha_proyeccion,
            ]);

            // Eliminar archivos adjuntos de cada actividad anulada
            $this->eliminarArchivosAdjuntos($actividad->id);
        }
    }

    // VERIFICAR DEPENDENCIAS MÚLTIPLES EN ANULACIÓN - ACTUALIZADA
    private function verificarDependenciasMultiplesAnulacion($codigo_proyecto, $codigo_documento, $etapa, $actividad_anulada_id)
    {
        // Definir actividades que requieren múltiples dependencias según nueva lógica
        $dependenciasMultiples = [
            8 => [2, 7],      // Requiere 2 y 7
            14 => [4, 12],    // Requiere 4 y 12
        ];

        foreach ($dependenciasMultiples as $actividad_id => $dependenciasRequeridas) {
            // Si la actividad anulada está en las dependencias de alguna actividad múltiple
            if (in_array($actividad_anulada_id, $dependenciasRequeridas)) {
                // Verificar cuántas de las dependencias requeridas están completadas
                $completadas = Documentos::where('codigo_proyecto', $codigo_proyecto)
                    ->where('codigo_documento', $codigo_documento)
                    ->where('etapa', $etapa)
                    ->whereIn('actividad_id', $dependenciasRequeridas)
                    ->where('estado', 2)
                    ->count();

                // Si no están todas completadas, anular la actividad dependiente
                if ($completadas < count($dependenciasRequeridas)) {
                    // Verificar si la actividad dependiente existe y está completada
                    $actividadDependiente = Documentos::where('codigo_proyecto', $codigo_proyecto)
                        ->where('codigo_documento', $codigo_documento)
                        ->where('etapa', $etapa)
                        ->where('actividad_id', $actividad_id)
                        ->where('estado', 2)
                        ->first();

                    if ($actividadDependiente) {
                        $this->anularActividadesDependientes(
                            $codigo_proyecto,
                            $codigo_documento,
                            $etapa,
                            [$actividad_id]
                        );
                    }
                }
            }
        }

        // Verificar dependencias en cascada larga (ejemplo: si se anula 21, anular todo desde 22)
        $cascadas = [
            21 => range(22, 34),  // Si se anula 21, anular 22-34
            22 => range(23, 34),  // Si se anula 22, anular 23-34
            23 => range(24, 34),  // etc.
            24 => range(25, 34),
            25 => range(26, 34),
            26 => range(27, 34),
            27 => range(28, 34),
            28 => range(29, 34),
            29 => range(30, 34),
            30 => range(31, 34),
            31 => range(32, 34),
            32 => range(33, 34),
            33 => [34],
        ];

        if (isset($cascadas[$actividad_anulada_id])) {
            $this->anularActividadesDependientes(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $cascadas[$actividad_anulada_id]
            );
        }
    }

    // OBTENER DEPENDENCIAS ANULADAS - ACTUALIZADA
    private function getDependenciasAnuladas($codigo_proyecto, $codigo_documento, $etapa, $excluir_actividad_id = null)
    {
        $query = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('estado', 0);

        // Excluir la actividad principal si se especifica
        if ($excluir_actividad_id) {
            $query->where('actividad_id', '!=', $excluir_actividad_id);
        }

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'actividad_id' => $item->actividad_id,
                'actividad_nombre' => $item->actividad->actividad ?? 'Sin nombre',
                'orden' => $item->orden,
            ];
        })->toArray();
    }

    // MÉTODO PRINCIPAL DE ANULACIÓN ACTUALIZADO
    public function anularDocumento($id)
    {
        try {
            DB::beginTransaction();

            // 1. Obtener la actividad a anular con su relación de actividad
            $actividadAnular = Documentos::with('actividad')->find($id);

            if (!$actividadAnular) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Actividad no encontrada'
                ], 404);
            }

            // Guardar datos antes de modificar para las dependencias
            $codigo_proyecto = $actividadAnular->codigo_proyecto;
            $codigo_documento = $actividadAnular->codigo_documento;
            $etapa = $actividadAnular->etapa;
            $actividad_id = $actividadAnular->actividad_id;

            // 2. Aplicar lógica de anulación con dependencias PRIMERO
            if ($etapa == 1) {
                $this->aplicarLogicaAnulacionEtapa1(
                    $codigo_proyecto,
                    $codigo_documento,
                    $etapa,
                    $actividad_id
                );
            } else {
                $this->aplicarLogicaAnulacionCascada(
                    $codigo_proyecto,
                    $codigo_documento,
                    $etapa,
                    $actividadAnular
                );
            }

            // 3. Eliminar archivos adjuntos de la actividad principal
            $this->eliminarArchivosAdjuntos($id);

            // 4. Ahora actualizar la actividad principal a estado 1 (NO a 0)
            $actividadAnular->update([
                'estado' => 1, // Estado 1 = Habilitado/Activo
                'fecha_confirmacion' => null,
                'observacion' => null,
                'diferenciaDias' => null,
                'usuario_id' => null,
                'fecha_actual' => $actividadAnular->fecha_proyeccion,
            ]);

            // 5. Obtener lista de actividades anuladas (excluyendo la principal)
            $dependenciasAnuladas = $this->getDependenciasAnuladas(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $actividad_id // Excluir la actividad principal
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Actividad anulada exitosamente. Dependencias anuladas: ' . count($dependenciasAnuladas),
                'data' => [
                    'actividad_principal' => [
                        'id' => $actividadAnular->id,
                        'actividad_id' => $actividadAnular->actividad_id,
                        'actividad_nombre' => $actividadAnular->actividad->actividad ?? 'Sin nombre',
                        'estado' => 1,
                    ],
                    'total_dependencias_anuladas' => count($dependenciasAnuladas),
                    'dependencias_anuladas' => $dependenciasAnuladas
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error anulando actividad: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ==================== LÓGICA ANULACIÓN EMCALI FIN ====================











    // ==================== LÓGICA ANULACIÓN CELSIA ====================
    // LOGICA ANULACION CELSIA - CON NUEVAS DEPENDENCIAS
    private function aplicarLogicaAnulacionCelsia($codigo_proyecto, $codigo_documento, $etapa, $ordenActual, $actividad_id)
    {
        // MAPA DE DEPENDENCIAS DE CELSIA (qué actividades dependen de cuáles)
        $dependencias = [
            // 43 activa 44
            43 => [44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 44 activa 45
            44 => [45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 45 activa 46
            45 => [46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 46 activa 47 y 50
            46 => [47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 47 activa 48
            47 => [48, 49],

            // 48 activa 49
            48 => [49],

            // 49 no tiene dependientes
            49 => [],

            // 50 activa 51
            50 => [51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 51 activa 52
            51 => [52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 52 activa 53 y 55-66
            52 => [53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73],

            // 53 activa 54
            53 => [54],

            // 54 no tiene dependientes
            54 => [],

            // 55-65 activan en conjunto con 70 a 71
            55 => [71, 72, 73],
            56 => [71, 72, 73],
            57 => [71, 72, 73],
            58 => [71, 72, 73],
            59 => [71, 72, 73],
            60 => [71, 72, 73],
            61 => [71, 72, 73],
            62 => [71, 72, 73],
            63 => [71, 72, 73],
            64 => [71, 72, 73],
            65 => [71, 72, 73],

            // 66 activa 67
            66 => [67, 68, 69, 70, 71, 72, 73],

            // 67 activa 68
            67 => [68, 69, 70, 71, 72, 73],

            // 68 activa 69
            68 => [69, 70, 71, 72, 73],

            // 69 activa 70
            69 => [70, 71, 72, 73],

            // 70 activa 71 (junto con 55-65)
            70 => [71, 72, 73],

            // 71 activa 72
            71 => [72, 73],

            // 72 activa 73
            72 => [73],

            // 73 no tiene dependientes
            73 => [],
        ];

        // ANULAR POR ACTIVIDAD ID (basado en dependencias)
        if (isset($dependencias[$actividad_id]) && !empty($dependencias[$actividad_id])) {
            $this->anularActividadesDependientesCelsia(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $dependencias[$actividad_id]
            );
        }

        // VERIFICACIONES ESPECIALES PARA ACTIVACIÓN MÚLTIPLE

        // Caso especial: Si se anula 70, verificar 71
        if ($actividad_id == 70) {
            $this->verificarAnulacion71PorBloque55a65($codigo_proyecto, $codigo_documento, $etapa);
        }

        // Caso especial: Si se anula alguna del 55-65, verificar 71
        if (in_array($actividad_id, range(55, 65))) {
            $this->verificarAnulacion71PorBloque55a65($codigo_proyecto, $codigo_documento, $etapa);
        }

        // Caso especial: Si se anula 52, anular todo lo que depende de 52
        if ($actividad_id == 52) {
            $this->anularActividadesDependientesCelsia(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                [53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73]
            );
        }
    }

    // Función para anular actividades dependientes
    private function anularActividadesDependientesCelsia($codigo_proyecto, $codigo_documento, $etapa, $actividades_ids)
    {
        foreach ($actividades_ids as $actividad_id) {
            $actividad = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_id', $actividad_id)
                ->where('estado', '!=', 0)
                ->first();

            if ($actividad) {
                // Guardar el ID para posible recursión
                $idActual = $actividad->id;
                $actividadIdActual = $actividad->actividad_id;

                // Anular la actividad
                $actividad->update([
                    'estado' => 0,
                    'fecha_confirmacion' => null,
                    'observacion' => null,
                    'usuario_id' => null,
                    'diferenciaDias' => null,
                    'fecha_actual' => $actividad->fecha_proyeccion,
                ]);

                // Eliminar archivos adjuntos
                $this->eliminarArchivosAdjuntos($actividad->id);

                // Llamada recursiva para anular dependencias de esta actividad
                $this->aplicarLogicaAnulacionCelsia(
                    $codigo_proyecto,
                    $codigo_documento,
                    $etapa,
                    $actividad->orden,
                    $actividadIdActual
                );
            }
        }
    }

    // Función para verificar si se debe anular la actividad 71
    private function verificarAnulacion71PorBloque55a65($codigo_proyecto, $codigo_documento, $etapa)
    {
        // Verificar si 70 está completada
        $actividad70Completa = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('actividad_id', 70)
            ->where('estado', 2)
            ->exists();

        // Si 70 no está completada, no hay necesidad de verificar 71
        if (!$actividad70Completa) {
            return;
        }

        // Verificar cuántas actividades del 55 al 65 están completadas
        $actividadesBloque = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->whereBetween('actividad_id', [55, 65])
            ->get();

        $completadas = $actividadesBloque->filter(function ($actividad) {
            return $actividad->estado == 2;
        })->count();

        $totalBloque = $actividadesBloque->count();

        // Si no todas están completadas, anular 71
        if ($completadas < $totalBloque) {
            $actividad71 = Documentos::where('codigo_proyecto', $codigo_proyecto)
                ->where('codigo_documento', $codigo_documento)
                ->where('etapa', $etapa)
                ->where('actividad_id', 71)
                ->where('estado', '!=', 0)
                ->first();

            if ($actividad71) {
                $this->anularActividadesDependientesCelsia(
                    $codigo_proyecto,
                    $codigo_documento,
                    $etapa,
                    [71, 72, 73]
                );
            }
        }
    }

    // Función para obtener dependencias anuladas (mejorada)
    private function getDependenciasAnuladasCelsia($codigo_proyecto, $codigo_documento, $etapa, $excluir_actividad_id = null)
    {
        $query = Documentos::where('codigo_proyecto', $codigo_proyecto)
            ->where('codigo_documento', $codigo_documento)
            ->where('etapa', $etapa)
            ->where('estado', 0);

        // Excluir la actividad principal si se especifica
        if ($excluir_actividad_id) {
            $query->where('actividad_id', '!=', $excluir_actividad_id);
        }

        return $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'actividad_id' => $item->actividad_id,
                'orden' => $item->orden,
            ];
        })->toArray();
    }

    // Método principal de anulación para CELSIA (mejorado)
    public function anularDocumentoCelsia($id)
    {
        try {
            DB::beginTransaction();

            // 1. Obtener la actividad a anular con su relación de actividad
            $actividadAnular = Documentos::with('actividad')->find($id);

            if (!$actividadAnular) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Actividad no encontrada'
                ], 404);
            }

            // Guardar datos antes de modificar para las dependencias
            $codigo_proyecto = $actividadAnular->codigo_proyecto;
            $codigo_documento = $actividadAnular->codigo_documento;
            $etapa = $actividadAnular->etapa;
            $actividad_id = $actividadAnular->actividad_id;
            $orden = $actividadAnular->orden;


            // 2. Aplicar lógica de anulación con dependencias PRIMERO
            $this->aplicarLogicaAnulacionCelsia(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $orden,
                $actividad_id
            );

            // 3. Eliminar archivos adjuntos de la actividad principal
            $this->eliminarArchivosAdjuntos($id);

            // 4. Ahora actualizar la actividad principal a estado 1 (NO a 0)
            $actividadAnular->update([
                'estado' => 1, // Estado 1 = Habilitado/Activo
                'fecha_confirmacion' => null,
                'observacion' => null,
                'diferenciaDias' => null,
                'usuario_id' => null,
                'fecha_actual' => $actividadAnular->fecha_proyeccion,
            ]);

            // 5. Obtener lista de actividades anuladas (excluyendo la principal)
            $dependenciasAnuladas = $this->getDependenciasAnuladasCelsia(
                $codigo_proyecto,
                $codigo_documento,
                $etapa,
                $actividad_id // Excluir la actividad principal
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Actividad Celsia anulada exitosamente. Dependencias anuladas: ' . count($dependenciasAnuladas),
                'data' => [
                    'actividad_principal' => [
                        'id' => $actividadAnular->id,
                        'actividad_id' => $actividadAnular->actividad_id,
                        'actividad_nombre' => $actividadAnular->actividad->actividad ?? 'Sin nombre',
                        'orden' => $actividadAnular->orden,
                        'estado' => 1,
                    ],
                    'total_dependencias_anuladas' => count($dependenciasAnuladas),
                    'dependencias_anuladas' => $dependenciasAnuladas
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error anulando actividad CELSIA: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ==================== LÓGICA ANULACIÓN CELSIA FIN ====================











    //================ LOGICA PARA MENEJO DE TORRES PARA HABILITAR===============
    public function GetTorresDisponibleXPoryecto($codigoProyecto)
    {
        $tabla = null;
        $campoNombre = null;
        $campoRelacion = null;

        // Buscar en apartamentos
        $proyecto = Proyectos::where('codigo_proyecto', $codigoProyecto)->first();

        if ($proyecto) {
            $tabla = 'nombre_xtore';
            $campoNombre = 'nombre_torre';
            $campoRelacion = 'proyecto_id';
        } else {
            // Buscar en casas
            $proyecto = ProyectoCasa::where('codigo_proyecto', $codigoProyecto)->first();

            if ($proyecto) {
                $tabla = 'nombrexmanzana';
                $campoNombre = 'nombre_manzana';
                $campoRelacion = 'proyectos_casas_id'; // ✅ CORRECTO
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Proyecto no encontrado'
                ], 404);
            }
        }

        // Nombres ya utilizados
        $torresOcupadas = DB::table('documentacion_torres')
            ->where('codigo_proyecto', $codigoProyecto)
            ->pluck('nombre_torre');

        // Disponibles
        $disponibles = DB::table($tabla)
            ->where($campoRelacion, $proyecto->id)
            ->whereNotIn($campoNombre, $torresOcupadas)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $disponibles
        ]);
    }
    //================ LOGICA PARA MENEJO DE TORRES PARA HABILITAR FIN ==============


    //================ GET DE FECHA DE ENTREGA CALCULANDO LA FECHA ACTUAL ===============
    /*  public function CalculoFechaReal($codigoDocumento)
    {
        try {
            // 1. Buscar todos los documentos ordenados por ID ascendente (del 1 al 20)
            $documentos = Documentos::where('codigo_documento', $codigoDocumento)
                ->orderBy('id', 'asc') // Cambiado a ascendente para procesar de abajo arriba naturalmente
                ->get();

            if ($documentos->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron documentos'
                ], 404);
            }

            // 2. Buscar la última fecha_confirmación (de arriba hacia abajo, que sería el último registro)
            $fechaBase = null;
            $actividadesDesde = [];
            $indiceBase = -1;

            // Recorrer de atrás hacia adelante para encontrar la última fecha_confirmación
            for ($i = count($documentos) - 1; $i >= 0; $i--) {
                $doc = $documentos[$i];
                if ($doc->fecha_confirmacion) {
                    // Encontramos la última fecha_confirmación (la más reciente)
                    $fechaBase = Carbon::parse($doc->fecha_confirmacion);
                    $indiceBase = $i;
                    break;
                }
            }

            // 3. Si encontramos fecha base, guardamos las actividades desde ese punto hasta el final
            if ($indiceBase >= 0) {
                for ($i = $indiceBase + 1; $i < count($documentos); $i++) {
                    $actividadesDesde[] = $documentos[$i]->actividad_id;
                }
            }

            // 4. Calcular fechaPropietario (última fecha_proyeccion + 30 días)
            $ultimoRegistro = $documentos->last(); // El último registro (ID más alto)
            $fechaPropietario = Carbon::parse($ultimoRegistro->fecha_proyeccion)->addDays(30);

            // 5. Si no se encontró ninguna fecha_confirmación, usar la misma fechaPropietario para fechaReal
            if (!$fechaBase) {
                return response()->json([
                    'status' => 'success',
                    'fechaPropietario' => $fechaPropietario->format('d/m/Y'),
                    'fechaReal' => $fechaPropietario->format('d/m/Y'),
                    'desfase' => 0,
                    'actividades_sumadas' => []
                ]);
            }

            // 6. Obtener datos del primer documento para operador y etapa
            $primerDoc = $documentos->first(); // El primer registro (ID más bajo)
            $operador = $primerDoc->operador;
            $etapa = $primerDoc->etapa;

            // 7. Buscar en actividades_documentos los registros con esos IDs
            $actividades = DB::table('actividades_documentos')
                ->where('operador', $operador)
                ->where('etapa', $etapa)
                ->whereIn('id', $actividadesDesde)
                ->get();

            // 8. Sumar los tiempos solo de los que tienen calculo = 1 (días calendario)
            $desfase = 0;
            $actividadesSumadas = [];
            foreach ($actividades as $actividad) {
                if ($actividad->calculo == 1) {
                    $desfase += $actividad->tiempo;
                    $actividadesSumadas[] = $actividad->id;
                }
            }

            // 9. Calcular fechaReal = fechaBase + desfase
            $fechaReal = $fechaBase->copy()->addDays($desfase);

            return response()->json([
                'status' => 'success',
                'fechaPropietario' => $fechaPropietario->format('d/m/Y'),
                'fechaReal' => $fechaReal->format('d/m/Y'),
                'desfase' => $desfase,
                'actividades_sumadas' => $actividadesSumadas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    } */

    public function CalculoFechaReal($codigoDocumento)
    {
        try {
            // 1. Buscar todos los documentos
            $documentos = Documentos::where('codigo_documento', $codigoDocumento)
                ->orderBy('id', 'asc')
                ->get();

            if ($documentos->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron documentos'
                ], 404);
            }

            // 2. Calcular fechaPropietario (última fecha_proyeccion + 30 días)
            $ultimoRegistro = $documentos->last();
            $fechaPropietario = Carbon::parse($ultimoRegistro->fecha_proyeccion)->addDays(30);

            // 3. Buscar la PRIMER fecha_confirmación de abajo hacia arriba
            $fechaConfirmacion = null;
            $fechaProyeccionRegistro = null;

            for ($i = count($documentos) - 1; $i >= 0; $i--) {
                $doc = $documentos[$i];
                if ($doc->fecha_confirmacion) {
                    $fechaConfirmacion = Carbon::parse($doc->fecha_confirmacion);
                    $fechaProyeccionRegistro = Carbon::parse($doc->fecha_proyeccion);
                    break;
                }
            }

            // 4. Si no hay fecha_confirmación, fechaReal = fechaPropietario
            if (!$fechaConfirmacion) {
                return response()->json([
                    'status' => 'success',
                    'fechaPropietario' => $fechaPropietario->format('d/m/Y'),
                    'fechaReal' => $fechaPropietario->format('d/m/Y'),
                    'desfase' => 0,
                    'mensaje' => 'Sin fecha confirmación'
                ]);
            }

            // 5. Calcular el desfase: fecha_proyeccion - fecha_confirmacion
            // Usamos diffInDays con parámetro false para mantener el signo
            $desfase = $fechaProyeccionRegistro->diffInDays($fechaConfirmacion, false);

            // 6. APLICAR LÓGICA CORRECTA:
            $fechaReal = $fechaPropietario->copy()->addDays($desfase);

            // 7. Mensaje descriptivo
            if ($desfase < 0) {
                $dias = abs($desfase);
                $mensaje = "Se confirmó $dias día(s) ANTES → se RESTAN $dias día(s) a fechaPropietario";
            } elseif ($desfase > 0) {
                $dias = $desfase;
                $mensaje = "Se confirmó $dias día(s) DESPUÉS → se SUMAN $dias día(s) a fechaPropietario";
            } else {
                $mensaje = "Se confirmó el MISMO DÍA → fechaReal = fechaPropietario";
            }

            return response()->json([
                'status' => 'success',
                'fechaPropietario' => $fechaPropietario->format('d/m/Y'),
                'fechaReal' => $fechaReal->format('d/m/Y'),
                'desfase' => $desfase,
                'dias' => abs($desfase),
                'tipo' => $desfase < 0 ? 'antes' : ($desfase > 0 ? 'despues' : 'exacto'),
                'mensaje' => $mensaje,
                'detalle' => [
                    'fecha_confirmacion' => $fechaConfirmacion->format('d/m/Y'),
                    'fecha_proyeccion_del_registro' => $fechaProyeccionRegistro->format('d/m/Y'),
                    'ultima_fecha_proyeccion' => $ultimoRegistro->fecha_proyeccion
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
