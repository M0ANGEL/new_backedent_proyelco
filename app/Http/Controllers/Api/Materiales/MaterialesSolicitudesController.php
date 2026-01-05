<?php

namespace App\Http\Controllers\Api\Materiales;

use App\Http\Controllers\Controller;
use App\Models\CambiosProyectionHistorial;
use App\Models\MaterialSolicitud;
use App\Models\SolicitudMaterialAdjunto;
use App\Models\SolicitudMaterialInge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialesSolicitudesController extends Controller
{

    public function cargueProyeccion(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls'
            ]);

            // Verificar si ya existe
            if (MaterialSolicitud::where('codigo_proyecto', $request->codigo_proyecto)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La proyección ya fue cargada a este proyecto.',
                ], 200);
            }

            $data = Excel::toArray([], $request->file('archivo'))[0];

            if (count($data) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo está vacío o no tiene encabezado.'
                ], 200);
            }

            $registros = array_slice($data, 1);

            $ultimoCodigo = '';
            $ultimaCantidad = 0;
            $errores = [];
            $datosInsertar = [];

            // Helpers
            $toDecimal = fn($v) => is_numeric(str_replace(',', '.', $v)) ? floatval(str_replace(',', '.', $v)) : 0;
            $toInt     = fn($v) => is_numeric($v) ? intval($v) : 0;
            $toText    = fn($v) => trim($v ?? '');

            // **PASO 1: Extraer todas las descripciones únicas del Excel (Módulo 4)**
            $descripcionesUnicas = [];

            foreach ($registros as $fila) {
                $fila = array_pad(array_map('trim', $fila), 12, '');
                $descripcion = $fila[1] ?? '';
                $codigo = $fila[0] ?? '';
                $padre = $fila[2] ?? '';

                // Solo procesar módulo 4
                if (str_starts_with($codigo, '4') || str_starts_with($padre, '4')) {
                    $descripcion = $toText($descripcion);
                    if (!empty($descripcion) && !in_array($descripcion, $descripcionesUnicas)) {
                        $descripcionesUnicas[] = $descripcion;
                    }
                }
            }

            // **PASO 2: Consultar SINCO para buscar códigos por descripción**
            $mapaCodigosSinco = [];
            if (!empty($descripcionesUnicas)) {
                try {
                    $query = DB::connection('sqlsrv_sinco')
                        ->table('ADP_DTM_DIM.Insumo')
                        ->select('Insumo Descripcion', 'Codigo Insumo');

                    foreach ($descripcionesUnicas as $descripcion) {
                        $descripcionNormalizada = $this->normalizarTexto($descripcion);
                        $query->orWhereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE([Insumo Descripcion], 'á', 'a'), 'é', 'e'), 'í', 'i')) LIKE ?",
                            ['%' . $descripcionNormalizada . '%']
                        );
                    }

                    $codigosSinco = $query->distinct()->get();

                    foreach ($codigosSinco as $item) {
                        $desc = $item->{'Insumo Descripcion'} ?? null;
                        $cod = $item->{'Codigo Insumo'} ?? null;

                        if ($desc && $cod) {
                            $mapaCodigosSinco[$desc] = $cod;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error al consultar SINCO: ' . $e->getMessage());
                }
            }

            // **PASO 3: Agrupar registros por código**
            $registrosPorCodigo = [];

            foreach ($registros as $i => $fila) {
                $fila = array_pad(array_map('trim', $fila), 12, '');
                $codigo = $fila[0];
                $descripcion = $fila[1];
                $padre = $fila[2];

                // Autorrellenar código
                if (!empty($codigo)) {
                    $ultimoCodigo = $codigo;
                } elseif (!empty($descripcion) && !empty($ultimoCodigo)) {
                    $codigo = $ultimoCodigo;
                    $fila[0] = $codigo;
                }

                // Filtrar módulo 4
                if (!str_starts_with($codigo, '4') && !str_starts_with($padre, '4')) {
                    continue;
                }

                if (empty($descripcion)) {
                    $errores[] = "Fila " . ($i + 2) . ": El campo descripción está vacío";
                    continue;
                }

                if (!isset($registrosPorCodigo[$codigo])) {
                    $registrosPorCodigo[$codigo] = [];
                }

                $registrosPorCodigo[$codigo][] = [
                    'fila' => $fila,
                    'indice' => $i + 2
                ];
            }

            // **PASO 4: Procesar cada bloque de código por separado**
            foreach ($registrosPorCodigo as $codigoBloque => $registrosBloque) {
                // Para este bloque, identificar niveles 2
                $descripcionesNivel2EnBloque = [];

                // Primera pasada: identificar niveles 1 y 2
                foreach ($registrosBloque as $registro) {
                    $fila = $registro['fila'];
                    $descripcion = $toText($fila[1]);
                    $padre = $toText($fila[2]);

                    // Si es nivel 2 (padre igual al código)
                    if ($padre == $codigoBloque) {
                        $descripcionesNivel2EnBloque[] = $descripcion;
                    }
                }

                // Segunda pasada: procesar todos los registros del bloque
                foreach ($registrosBloque as $registro) {
                    $fila = $registro['fila'];
                    $descripcion = $fila[1];
                    $cantidad = $fila[4];
                    $cant_apu = $fila[6];
                    $padre = $fila[2];
                    $indice = $registro['indice'];

                    // Autorrellenar cantidad para este bloque
                    if (!empty($cantidad) && is_numeric(str_replace(',', '.', $cantidad))) {
                        $ultimaCantidad = $toDecimal($cantidad);
                    } elseif (empty($cantidad) && $ultimaCantidad > 0 && !empty($descripcion)) {
                        $cantidad = $fila[4] = $ultimaCantidad;
                    }

                    // ✅ CÁLCULO DEL NIVEL - POR BLOQUE
                    $nivel = 1;
                    $padreText = $toText($padre);
                    $descripcionText = $toText($descripcion);

                    // NIVEL 1: Padre es exactamente "4"
                    if ($padreText === '4') {
                        $nivel = 1;
                    }
                    // NIVEL 2: Padre es igual al código del bloque
                    elseif ($padreText == $codigoBloque) {
                        $nivel = 2;
                    }
                    // NIVEL 3: Padre es texto y está en las descripciones de nivel 2 de este bloque
                    elseif (!empty($padreText) && in_array($padreText, $descripcionesNivel2EnBloque)) {
                        $nivel = 3;
                    }
                    // Por defecto: nivel 1
                    else {
                        $nivel = 1;
                    }

                    // 👇 CALCULAR CANT_TOTAL (cantidad * cant_apu)
                    $cantidadDecimal = $toDecimal($cantidad);
                    $cantApuDecimal = $toDecimal($cant_apu);
                    $cant_total = $cantidadDecimal * $cantApuDecimal;

                    // **BUSCAR CÓDIGO SINCO POR DESCRIPCIÓN**
                    $codigoInsumoSinco = 'CO-ERR';

                    if (!empty($descripcionText) && !empty($mapaCodigosSinco)) {
                        // 1. Búsqueda exacta
                        if (isset($mapaCodigosSinco[$descripcionText])) {
                            $codigoInsumoSinco = $mapaCodigosSinco[$descripcionText];
                        } else {
                            // 2. Búsqueda parcial
                            $descripcionNormalizada = $this->normalizarTexto($descripcionText);

                            foreach ($mapaCodigosSinco as $descSinco => $codSinco) {
                                $descSincoNormalizada = $this->normalizarTexto($descSinco);

                                if (
                                    stripos($descSincoNormalizada, $descripcionNormalizada) !== false ||
                                    stripos($descripcionNormalizada, $descSincoNormalizada) !== false
                                ) {
                                    $codigoInsumoSinco = $codSinco;
                                    break;
                                }
                            }
                        }
                    }

                    $datosInsertar[] = [
                        'user_id'         => Auth::id(),
                        'codigo_proyecto' => $request->codigo_proyecto,
                        'codigo'          => $codigoBloque,
                        'descripcion'     => $descripcionText,
                        'codigo_insumo'   => $codigoInsumoSinco,
                        'padre'           => $padreText,
                        'nivel'           => $nivel,
                        'um'              => $toText($fila[3]),
                        'cantidad'        => $cantidadDecimal,
                        'subcapitulo'     => $toText($fila[5]),
                        'cant_apu'        => $cantApuDecimal,
                        'cant_restante'   => $cantidadDecimal,
                        'rend'            => $toDecimal($fila[7]),
                        'iva'             => $toInt($fila[8]),
                        'valor_sin_iva'   => $toDecimal($fila[9]),
                        'tipo_insumo'     => $toText($fila[10]),
                        'agrupacion'      => $toText($fila[11]),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }
            }

            if (!empty($errores)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Errores detectados en filas del archivo',
                    'errores' => $errores
                ], 200);
            }

            if (empty($datosInsertar)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontraron registros del módulo 4.',
                ], 200);
            }

            foreach (array_chunk($datosInsertar, 500) as $lote) {
                MaterialSolicitud::insert($lote);
            }

            DB::commit();

            // Estadísticas
            $codigosEncontrados = count(array_filter($datosInsertar, fn($item) => $item['codigo_insumo'] !== 'CO-ERR'));

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo cargado correctamente.',
                'cantidad' => count($datosInsertar),
                'codigos_encontrados' => $codigosEncontrados,
                'codigos_no_encontrados' => count($datosInsertar) - $codigosEncontrados,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error inesperado al procesar el archivo.',
                'errores' => [$e->getMessage()]
            ], 500);
        }
    }

    private function normalizarTexto($texto)
    {
        if (empty($texto)) return '';

        $texto = mb_strtolower($texto, 'UTF-8');

        $caracteresEspeciales = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'Ü' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ];

        $texto = strtr($texto, $caracteresEspeciales);
        $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    public function index()
    {
        $materialesAgrupados = DB::connection('mysql')
            ->table('materiales')
            ->leftJoin('proyecto', 'materiales.codigo_proyecto', '=', 'proyecto.codigo_proyecto')
            ->leftJoin('proyectos_casas', 'materiales.codigo_proyecto', '=', 'proyectos_casas.codigo_proyecto')
            ->leftJoin('users', 'materiales.user_id', '=', 'users.id')
            ->select(
                'materiales.codigo_proyecto',
                DB::raw('COALESCE(proyecto.descripcion_proyecto, proyectos_casas.descripcion_proyecto) as descripcion_proyecto'),
                DB::raw('CASE 
                    WHEN proyecto.codigo_proyecto IS NOT NULL THEN "Apartamentos" 
                    WHEN proyectos_casas.codigo_proyecto IS NOT NULL THEN "Casas" 
                    ELSE "Sin Proyecto" 
                END as tipo_proyecto'),
                DB::raw('COUNT(materiales.id) as total_registros'),
                DB::raw('MAX(materiales.created_at) as fecha_ultimo_registro'),
                DB::raw('MIN(materiales.created_at) as fecha_primer_registro'),
                DB::raw('SUM(materiales.cantidad) as cantidad_total'),
                DB::raw('SUM(materiales.valor_sin_iva) as valor_total_sin_iva'),
                'users.nombre as usuario_carga'
            )
            ->groupBy(
                'materiales.codigo_proyecto',
                'descripcion_proyecto',
                'tipo_proyecto',
                'users.nombre'
            )
            ->orderBy('fecha_ultimo_registro', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $materialesAgrupados
        ]);
    }

    public function proyeccionData($codigo_proyecto)
    {
        $proyeccionData = DB::connection('mysql')
            ->table('materiales')
            ->where('codigo_proyecto', $codigo_proyecto)
            ->orderBy('codigo')
            ->orderBy('nivel')
            ->get();

        // Agrupar por bloques (código)
        $bloques = [];

        foreach ($proyeccionData as $item) {
            $codigo = $item->codigo;

            if (!isset($bloques[$codigo])) {
                $bloques[$codigo] = [
                    'bloque' => $codigo,
                    'items' => []
                ];
            }

            $bloques[$codigo]['items'][] = $item;
        }

        // Convertir a array indexado
        $bloquesArray = array_values($bloques);

        return response()->json([
            'status' => 'success',
            'data' => $bloquesArray,
        ]);
    }

    public function generarExcelAxuiliarMaterial(Request $request)
    {
        info($request->all());
        return;
        try {
            // Validar que la solicitud tenga updates
            if (!$request->has('updates') || !is_array($request->updates)) {
                throw new \Exception('No se proporcionaron datos para generar Excel');
            }

            $updates = $request->updates;
            $codigo_proyecto = $request->codigo_proyecto;
            $fecha = $request->fecha ?? now()->toISOString();

            // Buscar descripción del proyecto
            $descripcion_proyecto = $this->obtenerDescripcionProyecto($codigo_proyecto);

            // Obtener los IDs de los items modificados
            $idsModificados = collect($updates)->pluck('id')->toArray();

            if (empty($idsModificados)) {
                throw new \Exception('No se proporcionaron IDs válidos');
            }

            // Obtener los datos completos de los materiales modificados
            $materiales = MaterialSolicitud::whereIn('id', $idsModificados)
                ->orderBy('padre')
                ->orderBy('nivel')
                ->orderBy('codigo')
                ->get();

            if ($materiales->isEmpty()) {
                throw new \Exception('No se encontraron los materiales para generar el Excel');
            }

            // Crear el spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Materiales Auxiliar');

            // Definir los headers de la tabla CON LA NUEVA COLUMNA
            $headers = [
                'Proyecto',           // descripcion_proyecto de proyecto/proyectos_casas
                'Código Insumo',      // codigo_insumo
                'Descripción',        // descripcion de la tabla materiales
                'Padre',
                'Código',
                'Nivel',
                'UM',
                'Cantidad Actual',    // Lo que había ANTES de la operación
                'Cantidad Solicitada', // Nueva columna: Cuánto pidieron
                'Cantidad Nueva',     // Lo que queda DESPUÉS de la operación
                'SUBCAPITULO',
                'Cant APU Actual',    // Lo que había ANTES de la operación
                'Cant APU Solicitada', // Nueva columna: Cuánto pidieron en APU
                'Cant APU Nueva',     // Lo que queda DESPUÉS de la operación
                'Rend',
                'IVA',
                'VrUnitSinIVA',
                'Tipo Insumo',
                'Agrupacion'
            ];

            // Aplicar estilos a los headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            // Escribir headers
            foreach ($headers as $col => $header) {
                $cell = chr(65 + $col) . '1'; // A1, B1, C1, etc.
                $sheet->setCellValue($cell, $header);
                $sheet->getStyle($cell)->applyFromArray($headerStyle);
            }

            // Preparar datos para la tabla
            $row = 2;
            $itemsProcesados = [];

            foreach ($materiales as $material) {
                // Buscar el update correspondiente para este material
                $update = collect($updates)->firstWhere('id', $material->id);

                if (!$update) continue;

                $tipo = $update['tipo'] ?? 1; // 1 = suma, 2 = resta
                $cantidadSolicitada = floatval($update['cant_solicitada']);

                // Determinar operación (Suma/Resta) para mostrar signo
                $signoOperacion = $tipo == 1 ? '+' : '-';
                $cantidadSolicitadaFormateada = $signoOperacion . ' ' . number_format($cantidadSolicitada, 10, '.', '');

                // Determinar las cantidades según el nivel y tipo de operación
                if ($material->nivel == 1) {
                    // NIVEL 1 - usa campo 'cantidad'
                    $cantidadActual = floatval($material->cantidad);
                    $cantidadNueva = $tipo == 1 ? $cantidadActual + $cantidadSolicitada : $cantidadActual - $cantidadSolicitada;

                    $data = [
                        'proyecto' => $descripcion_proyecto,
                        'codigo_insumo' => $material->codigo_insumo,
                        'descripcion' => $material->descripcion,
                        'padre' => $material->padre ?? '',
                        'codigo' => $material->codigo,
                        'nivel' => $material->nivel ?? '',
                        'um' => $material->um ?? '',
                        'cantidad_actual' => number_format($cantidadActual, 10, '.', ''),
                        'cantidad_solicitada' => $cantidadSolicitadaFormateada,
                        'cantidad_nueva' => number_format($cantidadNueva, 10, '.', ''),
                        'subcapitulo' => $material->subcapitulo ?? '',
                        'cant_apu_actual' => '',
                        'cant_apu_solicitada' => '',
                        'cant_apu_nueva' => '',
                        'rend' => $material->rend ? number_format($material->rend, 4, '.', '') : '',
                        'iva' => $material->iva ?? 0,
                        'valor_sin_iva' => $material->valor_sin_iva ? number_format($material->valor_sin_iva, 4, '.', '') : '',
                        'tipo_insumo' => $material->tipo_insumo ?? '',
                        'agrupacion' => $material->agrupacion ?? ''
                    ];
                } else {
                    // NIVELES 2 y 3 - usa campo 'cant_apu'
                    $cantidadApuActual = floatval($material->cant_apu);
                    $cantidadApuNueva = $tipo == 1 ? $cantidadApuActual + $cantidadSolicitada : $cantidadApuActual - $cantidadSolicitada;

                    $data = [
                        'proyecto' => $descripcion_proyecto,
                        'codigo_insumo' => $material->codigo_insumo,
                        'descripcion' => $material->descripcion,
                        'padre' => $material->padre ?? '',
                        'codigo' => $material->codigo,
                        'nivel' => $material->nivel ?? '',
                        'um' => $material->um ?? '',
                        'cantidad_actual' => '',
                        'cantidad_solicitada' => '',
                        'cantidad_nueva' => '',
                        'subcapitulo' => $material->subcapitulo ?? '',
                        'cant_apu_actual' => number_format($cantidadApuActual, 10, '.', ''),
                        'cant_apu_solicitada' => $cantidadSolicitadaFormateada,
                        'cant_apu_nueva' => number_format($cantidadApuNueva, 10, '.', ''),
                        'rend' => $material->rend ? number_format($material->rend, 4, '.', '') : '',
                        'iva' => $material->iva ?? 0,
                        'valor_sin_iva' => $material->valor_sin_iva ? number_format($material->valor_sin_iva, 4, '.', '') : '',
                        'tipo_insumo' => $material->tipo_insumo ?? '',
                        'agrupacion' => $material->agrupacion ?? ''
                    ];
                }

                $itemsProcesados[] = $data;

                // Escribir datos en la hoja - CON LAS NUEVAS COLUMNAS
                $sheet->setCellValue('A' . $row, $data['proyecto']);              // Proyecto
                $sheet->setCellValue('B' . $row, $data['codigo_insumo']);         // Código Insumo
                $sheet->setCellValue('C' . $row, $data['descripcion']);           // Descripción
                $sheet->setCellValue('D' . $row, $data['padre']);                 // Padre
                $sheet->setCellValue('E' . $row, $data['codigo']);                // Código
                $sheet->setCellValue('F' . $row, $data['nivel']);                 // Nivel
                $sheet->setCellValue('G' . $row, $data['um']);                    // Unidad de medida
                $sheet->setCellValue('H' . $row, $data['cantidad_actual']);       // Cantidad Actual (antes)
                $sheet->setCellValue('I' . $row, $data['cantidad_solicitada']);   // Cantidad Solicitada (cuánto pidió)
                $sheet->setCellValue('J' . $row, $data['cantidad_nueva']);        // Cantidad Nueva (después)
                $sheet->setCellValue('K' . $row, $data['subcapitulo']);           // Subcapítulo
                $sheet->setCellValue('L' . $row, $data['cant_apu_actual']);       // Cant APU Actual (antes)
                $sheet->setCellValue('M' . $row, $data['cant_apu_solicitada']);   // Cant APU Solicitada (cuánto pidió)
                $sheet->setCellValue('N' . $row, $data['cant_apu_nueva']);        // Cant APU Nueva (después)
                $sheet->setCellValue('O' . $row, $data['rend']);                  // Rendimiento
                $sheet->setCellValue('P' . $row, $data['iva']);                   // IVA
                $sheet->setCellValue('Q' . $row, $data['valor_sin_iva']);         // Valor sin IVA
                $sheet->setCellValue('R' . $row, $data['tipo_insumo']);           // Tipo de insumo
                $sheet->setCellValue('S' . $row, $data['agrupacion']);            // Agrupación

                // Aplicar formato condicional para resaltar la cantidad solicitada
                if (!empty($data['cantidad_solicitada'])) {
                    $sheet->getStyle('I' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                    $sheet->getStyle('I' . $row)->getFill()->getStartColor()->setRGB($tipo == 1 ? 'C6EFCE' : 'FFC7CE'); // Verde para suma, Rojo para resta
                }
                if (!empty($data['cant_apu_solicitada'])) {
                    $sheet->getStyle('M' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('M' . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                    $sheet->getStyle('M' . $row)->getFill()->getStartColor()->setRGB($tipo == 1 ? 'C6EFCE' : 'FFC7CE');
                }

                $row++;
            }

            // Aplicar estilos a los datos
            $dataStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            $lastRow = $row - 1;
            if ($lastRow >= 2) {
                $sheet->getStyle('A2:S' . $lastRow)->applyFromArray($dataStyle);
            }

            // Autoajustar columnas
            foreach (range('A', 'S') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Agregar información adicional al final
            $sheet->setCellValue('A' . ($row + 1), 'Código Proyecto: ' . $codigo_proyecto);
            $sheet->setCellValue('A' . ($row + 2), 'Proyecto: ' . $descripcion_proyecto);
            $sheet->setCellValue('A' . ($row + 3), 'Fecha de generación: ' . date('Y-m-d H:i:s', strtotime($fecha)));
            $sheet->setCellValue('A' . ($row + 4), 'Total de items: ' . count($itemsProcesados));

            // Resumen de operaciones
            $sumas = collect($updates)->where('tipo', 1)->count();
            $restas = collect($updates)->where('tipo', 2)->count();
            $sheet->setCellValue('A' . ($row + 5), 'Sumas: ' . $sumas . ' | Restas: ' . $restas);

            // Crear respuesta para descargar el archivo
            $fileName = 'materiales_auxiliar_' . $codigo_proyecto . '_' . date('Ymd_His') . '.xlsx';

            return new StreamedResponse(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                },
                200,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Cache-Control' => 'max-age=0',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al generar Excel: ' . $e->getMessage());

            // En caso de error, crear un Excel con el mensaje de error
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Error al generar el reporte');
            $sheet->setCellValue('A2', $e->getMessage());

            $fileName = 'error_report_' . date('Ymd_His') . '.xlsx';

            return new StreamedResponse(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                },
                500,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ]
            );
        }
    }

    //se esta trabajadno en agregar el historial de modificaciones
    public function MaterialPudate(Request $request)
    {
        // Validar que la solicitud tenga updates
        if (!$request->has('updates') || !is_array($request->updates)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se proporcionaron datos para actualizar'
            ], 400);
        }

        $updates = $request->updates;
        $errores = [];
        $actualizacionesExitosas = [];

        // Obtener el ID del usuario autenticado
        $userId = auth()->id();

        // Variable para almacenar el código de proyecto actual (asumiendo que todos los updates son del mismo proyecto)
        $codigoProyecto = null;
        $proyectoNombre = null;

        // Primero, determinar el proyecto de los items
        foreach ($updates as $update) {
            if (isset($update['id'])) {
                $item = MaterialSolicitud::where('id', $update['id'])->first();
                if ($item) {
                    $codigoProyecto = $item->codigo_proyecto;
                    $proyectoNombre = $item->proyecto;
                    break;
                }
            }
        }

        if (!$codigoProyecto) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo determinar el proyecto'
            ], 400);
        }

        // Obtener la próxima versión para este proyecto
        $ultimaVersion = CambiosProyectionHistorial::where('codigo_proyecto', $codigoProyecto)
            ->max('version_edicion');

        $nuevaVersion = $ultimaVersion ? intval($ultimaVersion) + 1 : 1;

        foreach ($updates as $update) {
            // Validar campos requeridos
            if (!isset($update['id']) || !isset($update['cant_solicitada']) || !isset($update['tipo'])) {
                $errores[] = "Faltan campos requeridos en el update: " . json_encode($update);
                continue;
            }

            $idItm = $update['id'];
            $cantidad = floatval($update['cant_solicitada']);
            $tipo = intval($update['tipo']); // 1 = suma, 2 = resta

            // Buscar el item
            $item = MaterialSolicitud::where('id', $idItm)->first();

            if (!$item) {
                $errores[] = "No se encontró el item con ID: $idItm";
                continue;
            }

            // Verificar si es un item padre (padre = "4")
            $esPadre4 = $item->padre === "4";

            try {
                // Iniciar transacción para asegurar consistencia
                DB::beginTransaction();

                if ($esPadre4) {
                    // SI ES PADRE "4" - ACTUALIZAR TODOS LOS ITEMS CON EL MISMO CÓDIGO EN EL CAMPO 'cantidad'
                    $itemsParaActualizar = MaterialSolicitud::where('codigo', $item->codigo)->get();

                    foreach ($itemsParaActualizar as $itemActualizar) {
                        $cantidadActual = floatval($itemActualizar->cantidad);

                        // Calcular la nueva cantidad
                        if ($tipo === 1) { // SUMA - SIN LÍMITE MÁXIMO
                            $nuevaCantidad = $cantidadActual + $cantidad;
                        } elseif ($tipo === 2) { // RESTA - VALIDAR QUE NO SEA NEGATIVO
                            if ($cantidad > $cantidadActual) {
                                throw new \Exception("No se puede restar $cantidad de '$itemActualizar->descripcion'. La cantidad actual es $cantidadActual");
                            }
                            $nuevaCantidad = $cantidadActual - $cantidad;
                        } else {
                            throw new \Exception("Tipo de operación inválido: $tipo");
                        }

                        // Guardar en historial ANTES de actualizar
                        CambiosProyectionHistorial::create([
                            'user_id' => $userId,
                            'version_edicion' => $nuevaVersion,
                            'codigo_proyecto' => $codigoProyecto,
                            'codigo_item' => $itemActualizar->codigo,
                            'codigo_insumo' => $itemActualizar->codigo_insumo,
                            'descripcion' => $itemActualizar->descripcion,
                            'padre' => $itemActualizar->padre,
                            'nivel' => $itemActualizar->nivel,
                            'um' => $itemActualizar->um,
                            'cant_old' => $cantidadActual,
                            'cant_modificada' => $cantidad,
                            'cant_final' => $nuevaCantidad,
                            'cant_apu_old' => $itemActualizar->cant_apu,
                            'cant_apu_modificada' => 0, // No se modifica APU en padre 4
                            'cant_apu_final' => $itemActualizar->cant_apu,
                            'fecha_modificacion' => now(),
                        ]);

                        // Actualizar el campo 'cantidad'
                        $itemActualizar->cantidad = $nuevaCantidad;
                        $itemActualizar->cant_restante = $nuevaCantidad;
                        $itemActualizar->save();

                        $actualizacionesExitosas[] = [
                            'id' => $itemActualizar->id,
                            'descripcion' => $itemActualizar->descripcion,
                            'cantidad_anterior' => $cantidadActual,
                            'cantidad_nueva' => $nuevaCantidad,
                            'tipo' => $tipo,
                            'campo_actualizado' => 'cantidad',
                            'es_padre_4' => true,
                            'version' => $nuevaVersion
                        ];
                    }
                } else {
                    // SI NO ES PADRE "4" - ACTUALIZAR SOLO ESE ITEM ESPECÍFICO EN 'cant_apu' y 'cant_total'
                    $cantidadActual = floatval($item->cant_apu);
                    $cantidadTotalActual = floatval($item->cant_total);

                    // Calcular la nueva cantidad
                    if ($tipo === 1) { // SUMA - SIN LÍMITE MÁXIMO
                        $nuevaCantidad = $cantidadActual + $cantidad;
                        $nuevaCantidadTotal = $cantidadTotalActual + $cantidad;
                    } elseif ($tipo === 2) { // RESTA - VALIDAR QUE NO SEA NEGATIVO
                        if ($cantidad > $cantidadActual) {
                            throw new \Exception("No se puede restar $cantidad de '$item->descripcion'. La cantidad actual en cant_apu es $cantidadActual");
                        }
                        $nuevaCantidad = $cantidadActual - $cantidad;
                        $nuevaCantidadTotal = $cantidadTotalActual - $cantidad;
                    } else {
                        throw new \Exception("Tipo de operación inválido: $tipo");
                    }

                    // Guardar en historial ANTES de actualizar
                    CambiosProyectionHistorial::create([
                        'user_id' => $userId,
                        'version_edicion' => $nuevaVersion,
                        'codigo_proyecto' => $codigoProyecto,
                        'codigo_item' => $item->codigo,
                        'codigo_insumo' => $item->codigo_insumo,
                        'descripcion' => $item->descripcion,
                        'padre' => $item->padre,
                        'nivel' => $item->nivel,
                        'um' => $item->um,
                        'cant_old' => $item->cantidad,
                        'cant_modificada' => 0, // No se modifica cantidad regular
                        'cant_final' => $item->cantidad,
                        'cant_apu_old' => $cantidadActual,
                        'cant_apu_modificada' => $cantidad,
                        'cant_apu_final' => $nuevaCantidad,
                        'fecha_modificacion' => now(),
                    ]);

                    // Actualizar SOLO este item específico en 'cant_apu' y 'cant_total'
                    $item->cant_apu = $nuevaCantidad;
                    // También actualizar cant_total si es necesario
                    if ($item->cant_total !== null) {
                        $item->cant_total = $nuevaCantidadTotal;
                    }
                    $item->save();

                    $actualizacionesExitosas[] = [
                        'id' => $idItm,
                        'descripcion' => $item->descripcion,
                        'cantidad_anterior' => $cantidadActual,
                        'cantidad_nueva' => $nuevaCantidad,
                        'cant_total_anterior' => $cantidadTotalActual,
                        'cant_total_nuevo' => $nuevaCantidadTotal,
                        'tipo' => $tipo,
                        'campo_actualizado' => 'cant_apu y cant_total',
                        'es_padre_4' => false,
                        'version' => $nuevaVersion
                    ];
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $errores[] = "Error al actualizar '$item->descripcion': " . $e->getMessage();
            }
        }

        // Preparar respuesta
        $response = [
            'status' => 'success',
            'message' => 'Proceso completado',
            'data' => [
                'version' => $nuevaVersion,
                'proyecto' => $proyectoNombre,
                'codigo_proyecto' => $codigoProyecto,
                'actualizados' => $actualizacionesExitosas,
                'errores' => $errores,
                'total_cambios' => count($actualizacionesExitosas)
            ]
        ];

        // Si hay errores pero también actualizaciones exitosas
        if (!empty($errores)) {
            $response['status'] = 'partial';
            $response['message'] = 'Algunos items no pudieron ser actualizados';
        }

        // Si solo hay errores
        if (empty($actualizacionesExitosas) && !empty($errores)) {
            $response['status'] = 'error';
            $response['message'] = 'No se pudo actualizar ningún item';
        }

        return response()->json($response);
    }

    public function solicitudMaterialIngenieros(Request $request)
    {
        try {
            // Validar que la solicitud tenga actividades
            if (!$request->has('actividades') || !is_array($request->actividades)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionaron actividades para generar Excel'
                ], 400);
            }

            $actividades = $request->actividades;
            $codigo_proyecto = $request->codigo_proyecto;
            $fecha = $request->fecha ?? now()->toISOString();

            if (empty($actividades)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se proporcionaron actividades válidas'
                ], 400);
            }

            // Obtener el ID del usuario autenticado
            $userId = auth()->id();

            // Generar número de solicitud único
            $numeroSolicitud = $this->generarNumeroSolicitud($codigo_proyecto);

            // Crear el spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Solicitud de Materiales');

            // Escribir encabezado general
            $sheet->setCellValue('A1', 'SOLICITUD DE MATERIALES');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $sheet->setCellValue('A2', 'Código Proyecto: ' . $codigo_proyecto);
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $sheet->setCellValue('A3', 'Fecha: ' . date('Y-m-d H:i:s', strtotime($fecha)));
            $sheet->getStyle('A3')->getFont()->setBold(true);

            // Fila vacía
            $sheet->setCellValue('A4', '');

            // Definir los headers según el formato solicitado
            $headers = [
                'item',
                'codigo material',
                'descripcion',
                'cantidad x apartamento',
                'cant solicitada total',
                'total',
                'UM',
                'tipo insumo'
            ];

            // Aplicar estilos a los headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            // Preparar datos para la tabla
            $row = 5;
            $totalGeneral = 0;
            $itemsProcesados = [];
            $materialesParaActualizar = []; // Para actualizar cant_restante después
            $solicitudesRegistradas = []; // Para guardar en el historial

            foreach ($actividades as $actividad) {
                $nombreActividad = $actividad['actividad'] ?? 'Sin nombre';
                $itemActividad = $actividad['item'] ?? '';
                $dataActividad = $actividad['dataActividad'] ?? [];

                if (empty($dataActividad)) {
                    continue;
                }

                // Escribir título de la actividad (en varias filas según formato)
                $sheet->setCellValue('A' . $row, 'ACTIVIDAD: ' . $nombreActividad . ' (Item: ' . $itemActividad . ')');
                $sheet->mergeCells('A' . $row . ':H' . $row);
                $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE6F3FF');
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $row++;

                // Fila vacía
                $sheet->setCellValue('A' . $row, '');
                $row++;

                // Escribir encabezados de columnas
                foreach ($headers as $col => $header) {
                    $cell = chr(65 + $col) . $row; // A, B, C, etc.
                    $sheet->setCellValue($cell, $header);
                    $sheet->getStyle($cell)->applyFromArray($headerStyle);
                }
                $row++;

                // Procesar items de nivel 2
                foreach ($dataActividad as $nivel2) {
                    $cantidadSolicitada = floatval($nivel2['cantidad'] ?? 0);
                    $tipoOperacion = $nivel2['tipo'] ?? 2; // 2 = resta (solicitud de materiales)

                    // Buscar el material en la base de datos
                    $material = MaterialSolicitud::where('id', $nivel2['id'])
                        ->where('codigo_proyecto', $codigo_proyecto)
                        ->first();

                    if ($material) {
                        $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
                        $cantidadRestanteActual = floatval($material->cant_restante ?? 0);

                        // Calcular nueva cantidad restante
                        if ($tipoOperacion == 1) { // Suma (inventario)
                            $cantidadRestanteNueva = $cantidadRestanteActual + $cantidadSolicitada;
                        } else { // Resta (solicitud de materiales)
                            $cantidadRestanteNueva = $cantidadRestanteActual - $cantidadSolicitada;

                            // Validar que no sea negativo
                            if ($cantidadRestanteNueva < 0) {
                                $cantidadRestanteNueva = 0;
                            }
                        }

                        // Calcular total
                        $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
                        $totalGeneral += $cantidadTotal;

                        // Escribir datos del nivel 2
                        $sheet->setCellValue('A' . $row, $itemActividad); // item
                        $sheet->setCellValue('B' . $row, $material->codigo_insumo ?? '0'); // codigo material
                        $sheet->setCellValue('C' . $row, $material->descripcion); // descripcion
                        $sheet->setCellValue('D' . $row, $cantidadPorApartamento); // cantidad x apartamento
                        $sheet->setCellValue('E' . $row, $cantidadSolicitada); // cant solicitada total
                        $sheet->setCellValue('F' . $row, $cantidadTotal); // total
                        $sheet->setCellValue('G' . $row, $material->um ?? ''); // UM
                        $sheet->setCellValue('H' . $row, $material->tipo_insumo ?? ''); // tipo insumo

                        // Guardar registro para el historial de solicitudes
                        $solicitudesRegistradas[] = [
                            'user_id' => $userId,
                            'numero_solicitud' => $numeroSolicitud,
                            'numero_solicitud_sinco' => null,
                            'codigo_proyecto' => $codigo_proyecto,
                            'codigo_item' => $itemActividad,
                            'codigo_insumo' => $material->codigo_insumo ?? '',
                            'descripcion' => $material->descripcion,
                            'padre' => $material->padre,
                            'nivel' => $material->nivel,
                            'um' => $material->um,
                            'cant_unitaria' => $cantidadPorApartamento,
                            'cant_solicitada' => $cantidadSolicitada,
                            'cant_total' => $cantidadTotal,
                            'fecha_solicitud' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];

                        // Guardar datos para actualizar cant_restante
                        $materialesParaActualizar[] = [
                            'id' => $material->id,
                            'cant_restante_actual' => $cantidadRestanteActual,
                            'cant_restante_nueva' => $cantidadRestanteNueva,
                            'tipo' => $tipoOperacion,
                            'cantidad_solicitada' => $cantidadSolicitada
                        ];

                        $itemsProcesados[] = [
                            'actividad' => $nombreActividad,
                            'item' => $itemActividad,
                            'codigo_material' => $material->codigo_insumo,
                            'descripcion' => $material->descripcion,
                            'cant_apu' => $cantidadPorApartamento,
                            'cant_solicitada' => $cantidadSolicitada,
                            'total' => $cantidadTotal,
                            'um' => $material->um,
                            'tipo_insumo' => $material->tipo_insumo
                        ];

                        $row++;

                        // Procesar hijos (nivel 3) si existen
                        if (isset($nivel2['subHijos']) && is_array($nivel2['subHijos'])) {
                            foreach ($nivel2['subHijos'] as $hijo) {
                                $hijoMaterial = MaterialSolicitud::where('id', $hijo['id'])
                                    ->where('codigo_proyecto', $codigo_proyecto)
                                    ->first();

                                if ($hijoMaterial) {
                                    $hijoCantidadPorApartamento = floatval($hijoMaterial->cant_apu ?? 0);
                                    $hijoCantidadRestanteActual = floatval($hijoMaterial->cant_restante ?? 0);

                                    // Calcular nueva cantidad restante para el hijo
                                    if ($tipoOperacion == 1) {
                                        $hijoCantidadRestanteNueva = $hijoCantidadRestanteActual + $cantidadSolicitada;
                                    } else {
                                        $hijoCantidadRestanteNueva = $hijoCantidadRestanteActual - $cantidadSolicitada;

                                        // Validar que no sea negativo
                                        if ($hijoCantidadRestanteNueva < 0) {
                                            $hijoCantidadRestanteNueva = 0;
                                        }
                                    }

                                    $hijoCantidadTotal = $hijoCantidadPorApartamento * $cantidadSolicitada;
                                    $totalGeneral += $hijoCantidadTotal;

                                    // Escribir datos del hijo
                                    $sheet->setCellValue('A' . $row, $itemActividad);
                                    $sheet->setCellValue('B' . $row, $hijoMaterial->codigo_insumo ?? '0');
                                    $sheet->setCellValue('C' . $row, $hijoMaterial->descripcion);
                                    $sheet->setCellValue('D' . $row, $hijoCantidadPorApartamento);
                                    $sheet->setCellValue('E' . $row, $cantidadSolicitada);
                                    $sheet->setCellValue('F' . $row, $hijoCantidadTotal);
                                    $sheet->setCellValue('G' . $row, $hijoMaterial->um ?? '');
                                    $sheet->setCellValue('H' . $row, $hijoMaterial->tipo_insumo ?? '');

                                    // Guardar registro para el historial de solicitudes (hijo)
                                    $solicitudesRegistradas[] = [
                                        'user_id' => $userId,
                                        'numero_solicitud' => $numeroSolicitud,
                                        'numero_solicitud_sinco' => null,
                                        'codigo_proyecto' => $codigo_proyecto,
                                        'codigo_item' => $itemActividad,
                                        'codigo_insumo' => $hijoMaterial->codigo_insumo ?? '',
                                        'descripcion' => $hijoMaterial->descripcion,
                                        'padre' => $hijoMaterial->padre,
                                        'nivel' => $hijoMaterial->nivel,
                                        'um' => $hijoMaterial->um,
                                        'cant_unitaria' => $hijoCantidadPorApartamento,
                                        'cant_solicitada' => $cantidadSolicitada,
                                        'cant_total' => $hijoCantidadTotal,
                                        'fecha_solicitud' => now(),
                                        'created_at' => now(),
                                        'updated_at' => now()
                                    ];

                                    // Guardar datos del hijo para actualizar cant_restante
                                    $materialesParaActualizar[] = [
                                        'id' => $hijoMaterial->id,
                                        'cant_restante_actual' => $hijoCantidadRestanteActual,
                                        'cant_restante_nueva' => $hijoCantidadRestanteNueva,
                                        'tipo' => $tipoOperacion,
                                        'cantidad_solicitada' => $cantidadSolicitada
                                    ];

                                    $row++;
                                }
                            }
                        }
                    }
                }

                // Fila vacía entre actividades (3 filas vacías según formato)
                $row += 3;
            }

            // Aplicar estilos a los datos
            if ($row > 7) {
                $firstDataRow = 8; // Primera fila de datos
                $lastDataRow = $row - 4; // Última fila de datos (antes de las filas vacías finales)

                $dataStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ];

                $sheet->getStyle('A' . $firstDataRow . ':H' . $lastDataRow)->applyFromArray($dataStyle);

                // Formato numérico para las columnas de cantidad (con separador de miles y 2 decimales)
                $sheet->getStyle('D' . $firstDataRow . ':F' . $lastDataRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                // Ajustar anchos de columna
                $columnWidths = [
                    'A' => 12,  // item
                    'B' => 15,  // codigo material
                    'C' => 40,  // descripcion
                    'D' => 20,  // cantidad x apartamento
                    'E' => 20,  // cant solicitada total
                    'F' => 15,  // total
                    'G' => 8,   // UM
                    'H' => 12   // tipo insumo
                ];

                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Alinear columnas
                $sheet->getStyle('A' . $firstDataRow . ':A' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B' . $firstDataRow . ':B' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . $firstDataRow . ':F' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('G' . $firstDataRow . ':H' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // ACTUALIZAR cant_restante en la base de datos Y REGISTRAR EN HISTORIAL
            $updateMessage = '';
            if (!empty($materialesParaActualizar) && !empty($solicitudesRegistradas)) {
                try {
                    DB::beginTransaction();

                    // 1. Actualizar cant_restante en MaterialSolicitud
                    $actualizadosCount = 0;
                    foreach ($materialesParaActualizar as $itemActualizar) {
                        $material = MaterialSolicitud::find($itemActualizar['id']);
                        if ($material) {
                            $material->cant_restante = $itemActualizar['cant_restante_nueva'];
                            $material->save();
                            $actualizadosCount++;
                        }
                    }

                    // 2. Registrar en el historial de solicitudes (inserción masiva)
                    $historialCount = count($solicitudesRegistradas);
                    SolicitudMaterialInge::insert($solicitudesRegistradas);

                    DB::commit();

                    $updateMessage = "✓ Actualizados: $actualizadosCount items | ✓ Historial: $historialCount registros | ✓ Solicitud: $numeroSolicitud";
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error al actualizar/registrar: ' . $e->getMessage());
                    $updateMessage = "✗ Error: " . $e->getMessage();
                }
            } else {
                $updateMessage = "⚠ No hay datos para actualizar/registrar";
            }

            // Agregar información adicional al final
            $sheet->setCellValue('A' . $row, 'RESUMEN:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            $sheet->setCellValue('A' . $row, 'Número de Solicitud: ' . $numeroSolicitud);
            $row++;

            $sheet->setCellValue('A' . $row, $updateMessage);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);

            if (strpos($updateMessage, '✓') !== false) {
                $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('00B050'); // Verde para éxito
            } elseif (strpos($updateMessage, '✗') !== false) {
                $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('FF0000'); // Rojo para error
            } else {
                $sheet->getStyle('A' . $row)->getFont()->getColor()->setRGB('FF9900'); // Naranja para advertencia
            }

            // Crear respuesta para descargar
            $fileName = 'solicitud_materiales_' . $codigo_proyecto . '_' . date('Ymd_His') . '.xlsx';

            $response = new StreamedResponse(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                },
                200,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'Cache-Control' => 'max-age=0',
                ]
            );

            return $response;
        } catch (\Exception $e) {
            Log::error('Error al generar Excel: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar el Excel: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    // Método para generar número de solicitud único (sin cambios)
    private function generarNumeroSolicitud($codigoProyecto)
    {
        $fecha = now();
        $fechaStr = $fecha->format('Ymd');

        $ultimaSolicitud = SolicitudMaterialInge::where('codigo_proyecto', $codigoProyecto)
            ->whereDate('created_at', $fecha->toDateString())
            ->orderBy('created_at', 'desc')
            ->first();

        if ($ultimaSolicitud && $ultimaSolicitud->numero_solicitud) {
            $partes = explode('-', $ultimaSolicitud->numero_solicitud);
            $ultimaSecuencia = end($partes);

            if (is_numeric($ultimaSecuencia)) {
                $nuevaSecuencia = intval($ultimaSecuencia) + 1;
            } else {
                $nuevaSecuencia = 1;
            }
        } else {
            $nuevaSecuencia = 1;
        }

        $secuenciaFormateada = str_pad($nuevaSecuencia, 3, '0', STR_PAD_LEFT);

        return 'SOL-' . $codigoProyecto . '-' . $fechaStr . '-' . $secuenciaFormateada;
    }

    private function obtenerDescripcionProyecto($codigo_proyecto)
    {
        try {
            // Buscar en la tabla proyecto
            $proyecto = DB::table('proyecto')
                ->where('codigo_proyecto', $codigo_proyecto)
                ->first();

            if ($proyecto && isset($proyecto->descripcion_proyecto)) {
                return $proyecto->descripcion_proyecto;
            }

            // Si no se encuentra en proyecto, buscar en proyectos_casas
            $proyectoCasa = DB::table('proyectos_casas')
                ->where('codigo_proyecto', $codigo_proyecto)
                ->first();

            if ($proyectoCasa && isset($proyectoCasa->descripcion_proyecto)) {
                return $proyectoCasa->descripcion_proyecto;
            }

            // Si no se encuentra en ninguna tabla, retornar un valor por defecto
            return 'Proyecto no encontrado';
        } catch (\Exception $e) {
            Log::error('Error al buscar descripción del proyecto: ' . $e->getMessage());
            return 'Error al obtener descripción';
        }
    }

    //SOLICITUDES DATA PARA HISTORIAL DE MATERIALES

    //Historial de modificaion de cantidades de proyection

    public function getHistorialProyeccion($codigo)
    {
        $data = CambiosProyectionHistorial::where('codigo_proyecto', $codigo)->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function generarExcelHistorial(Request $request)
    {
        // Validación
        $request->validate([
            'codigo_proyecto' => 'required|string',
            'version_edicion' => 'nullable|string',
            'fecha' => 'nullable|string'
        ]);

        $codigo_proyecto = $request->codigo_proyecto;
        $versionEdicion = $request->version_edicion; // Nueva: para filtrar por versión
        $fecha = $request->fecha ?? now()->toISOString();

        try {
            // Obtener descripción del proyecto (debes implementar esta función)
            $descripcion_proyecto = $this->obtenerDescripcionProyecto($codigo_proyecto);

            // Consultar los datos del historial
            $query = CambiosProyectionHistorial::where('codigo_proyecto', $codigo_proyecto);

            // Filtrar por versión si se especifica
            if ($versionEdicion) {
                $query->where('version_edicion', $versionEdicion);
            }

            $historialItems = $query->orderBy('version_edicion', 'desc')
                ->orderBy('nivel')
                ->orderBy('codigo_insumo')
                ->get();

            if ($historialItems->isEmpty()) {
                throw new \Exception('No se encontraron datos de historial para generar el Excel');
            }

            // Crear el spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Historial de Cambios');

            // Título principal
            $sheet->setCellValue('A1', 'HISTORIAL DE CAMBIOS - PROYECTO: ' . $descripcion_proyecto);
            $sheet->mergeCells('A1:Q1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Definir los headers adaptados para historial
            $headers = [
                'Versión',
                'Fecha Modificación',
                'Proyecto',
                'Código Proyecto',
                'Código Insumo',
                'Descripción',
                'Padre',
                'Nivel',
                'UM',
                'Cantidad Anterior',
                'Cantidad Modificada',
                'Cantidad Final',
                'Cant APU Anterior',
                'Cant APU Modificada',
                'Cant APU Final',
                'Usuario ID',
                'Fecha Registro'
            ];

            // Aplicar estilos a los headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            // Escribir headers en la fila 3
            foreach ($headers as $col => $header) {
                $cell = chr(65 + $col) . '3';
                $sheet->setCellValue($cell, $header);
                $sheet->getStyle($cell)->applyFromArray($headerStyle);
            }

            // Escribir datos
            $row = 4;
            foreach ($historialItems as $item) {
                $sheet->setCellValue('A' . $row, $item->version_edicion);
                $sheet->setCellValue('B' . $row, $item->fecha_modificacion);
                $sheet->setCellValue('C' . $row, $descripcion_proyecto); // CORRECCIÓN: usar la variable, no propiedad del item
                $sheet->setCellValue('D' . $row, $item->codigo_proyecto);
                $sheet->setCellValue('E' . $row, $item->codigo_insumo);
                $sheet->setCellValue('F' . $row, $item->descripcion);
                $sheet->setCellValue('G' . $row, $item->padre);
                $sheet->setCellValue('H' . $row, $item->nivel);
                $sheet->setCellValue('I' . $row, $item->um);
                $sheet->setCellValue('J' . $row, $item->cant_old);
                $sheet->setCellValue('K' . $row, $item->cant_modificada);
                $sheet->setCellValue('L' . $row, $item->cant_final);
                $sheet->setCellValue('M' . $row, $item->cant_apu_old);
                $sheet->setCellValue('N' . $row, $item->cant_apu_modificada);
                $sheet->setCellValue('O' . $row, $item->cant_apu_final);
                $sheet->setCellValue('P' . $row, $item->user_id);
                $sheet->setCellValue('Q' . $row, $item->created_at);

                // Resaltar modificaciones
                if (floatval($item->cant_modificada) != 0) {
                    $sheet->getStyle('K' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                    $sheet->getStyle('K' . $row)->getFill()->getStartColor()
                        ->setRGB(floatval($item->cant_modificada) > 0 ? 'C6EFCE' : 'FFC7CE');
                }

                if (floatval($item->cant_apu_modificada) != 0) {
                    $sheet->getStyle('N' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('N' . $row)->getFill()->setFillType(Fill::FILL_SOLID);
                    $sheet->getStyle('N' . $row)->getFill()->getStartColor()
                        ->setRGB(floatval($item->cant_apu_modificada) > 0 ? 'C6EFCE' : 'FFC7CE');
                }

                $row++;
            }

            // Aplicar estilos a los datos
            $lastRow = $row - 1;
            if ($lastRow >= 4) {
                $sheet->getStyle('A4:Q' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);
            }

            // Autoajustar columnas
            foreach (range('A', 'Q') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Información adicional al final
            $infoRow = $row + 2;
            $sheet->setCellValue('A' . $infoRow, 'RESUMEN DEL HISTORIAL');
            $sheet->getStyle('A' . $infoRow)->getFont()->setBold(true);

            $sheet->setCellValue('A' . ($infoRow + 1), 'Proyecto: ' . $descripcion_proyecto);
            $sheet->setCellValue('A' . ($infoRow + 2), 'Código Proyecto: ' . $codigo_proyecto);
            $sheet->setCellValue('A' . ($infoRow + 3), 'Versión: ' . ($versionEdicion ? $versionEdicion : 'Todas las versiones'));
            $sheet->setCellValue('A' . ($infoRow + 4), 'Total registros: ' . $historialItems->count());
            $sheet->setCellValue('A' . ($infoRow + 5), 'Fecha generación: ' . date('Y-m-d H:i:s'));

            // Nombre del archivo
            $fileName = 'historial_cambios_' . $codigo_proyecto;
            if ($versionEdicion) {
                $fileName .= '_v' . $versionEdicion;
            }
            $fileName .= '_' . date('Ymd_His') . '.xlsx';

            // Limpiar buffers antes de enviar
            if (ob_get_length()) ob_end_clean();

            // Crear respuesta
            return response()->streamDownload(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                },
                $fileName,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                    'Pragma' => 'public',
                    'Expires' => '0'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al generar Excel de historial: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //Historial de solicitud de materia

    public function getHistorialSolicitudes($codigo)
    {
        $data = SolicitudMaterialInge::where('codigo_proyecto', $codigo)->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function subirPDFSolicitud(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB máximo
            'numero_solicitud' => 'required|string',
            'codigo_proyecto' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $numeroSolicitud = $request->numero_solicitud;
            $codigoProyecto = $request->codigo_proyecto;
            $file = $request->file('pdf');

            // Buscar la primera solicitud para obtener el ID
            $solicitud = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            if (!$solicitud) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La solicitud no existe'
                ], 404);
            }

            // Verificar si ya existe un adjunto para esta solicitud
            $adjuntoExistente = SolicitudMaterialAdjunto::where('solicitud_id', $solicitud->id)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            if ($adjuntoExistente) {
                // Eliminar el archivo anterior del storage
                if (Storage::disk('public')->exists($adjuntoExistente->ruta_archivo)) {
                    Storage::disk('public')->delete($adjuntoExistente->ruta_archivo);
                }

                // Eliminar el registro anterior de la base de datos
                $adjuntoExistente->delete();
            }

            // Generar nombre único para el archivo
            $timestamp = time();
            $fileName = 'solicitud_' . $numeroSolicitud . '_' . $timestamp . '.' . $file->getClientOriginalExtension();
            $folderPath = 'pdf-solicitudes/' . $codigoProyecto;

            // Crear la carpeta si no existe
            if (!Storage::disk('public')->exists($folderPath)) {
                Storage::disk('public')->makeDirectory($folderPath);
            }

            // Ruta completa del archivo
            $fullPath = $folderPath . '/' . $fileName;

            // Guardar el archivo
            $path = $file->storeAs($folderPath, $fileName, 'public');

            // Crear registro en la tabla de adjuntos
            $adjunto = SolicitudMaterialAdjunto::create([
                'solicitud_id' => $solicitud->id,
                'codigo_proyecto' => $codigoProyecto,
                'ruta_archivo' => $fullPath,
                'nombre_original' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'tamano' => $file->getSize(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'PDF subido exitosamente',
                'data' => [
                    'file_name' => $fileName,
                    'ruta_archivo' => $fullPath,
                    'nombre_original' => $adjunto->nombre_original,
                    'tamano' => $adjunto->tamano,
                    'extension' => $adjunto->extension,
                    'numero_solicitud' => $numeroSolicitud,
                    'codigo_proyecto' => $codigoProyecto,
                    'adjunto_id' => $adjunto->id
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al subir PDF: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al subir el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarSolicitudSinco(Request $request)
    {
        $request->validate([
            'numero_solicitud' => 'required|string',
            'numero_solicitud_sinco' => 'required|string',
            'codigo_proyecto' => 'required|string'
        ]);

        try {
            $numeroSolicitud = $request->numero_solicitud;
            $numeroSinco = $request->numero_solicitud_sinco;
            $codigoProyecto = $request->codigo_proyecto;

            // Buscar todas las solicitudes con ese número
            $solicitudes = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->get();

            if ($solicitudes->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró la solicitud'
                ], 404);
            }

            // Actualizar todas las solicitudes con el mismo número
            $actualizados = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->update(['numero_solicitud_sinco' => $numeroSinco]);

            return response()->json([
                'status' => 'success',
                'message' => 'Número SINCO actualizado exitosamente',
                'data' => [
                    'actualizados' => $actualizados,
                    'numero_solicitud' => $numeroSolicitud,
                    'numero_solicitud_sinco' => $numeroSinco
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar SINCO: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el número SINCO: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generarExcelSolicitud(Request $request)
    {
        $request->validate([
            'numero_solicitud' => 'required|string',
            'codigo_proyecto' => 'required|string'
        ]);

        $numeroSolicitud = $request->numero_solicitud;
        $codigoProyecto = $request->codigo_proyecto;

        try {
            // Obtener los datos de la solicitud
            $solicitudItems = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->orderBy('nivel')
                ->orderBy('codigo_item')
                ->orderBy('codigo_insumo')
                ->get();

            if ($solicitudItems->isEmpty()) {
                throw new \Exception('No se encontraron datos para la solicitud: ' . $numeroSolicitud);
            }

            // Obtener información de la primera solicitud
            $primerItem = $solicitudItems->first();
            $fechaSolicitud = $primerItem->fecha_solicitud;
            $itemCodigo = $primerItem->codigo_item;

            // Crear el spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Solicitud ' . $numeroSolicitud);

            // Escribir encabezado general
            $sheet->setCellValue('A1', 'SOLICITUD DE MATERIALES');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $sheet->setCellValue('A2', 'Número Solicitud: ' . $numeroSolicitud);
            $sheet->getStyle('A2')->getFont()->setBold(true);

            $sheet->setCellValue('A3', 'Código Proyecto: ' . $codigoProyecto);
            $sheet->getStyle('A3')->getFont()->setBold(true);

            $sheet->setCellValue('A4', 'Fecha: ' . $fechaSolicitud);
            $sheet->getStyle('A4')->getFont()->setBold(true);

            $sheet->setCellValue('A5', 'Item: ' . $itemCodigo);
            $sheet->getStyle('A5')->getFont()->setBold(true);

            // Fila vacía
            $sheet->setCellValue('A6', '');

            // Definir los headers
            $headers = [
                'item',
                'codigo material',
                'descripcion',
                'cantidad x apartamento',
                'cant solicitada total',
                'total',
                'UM',
                'tipo insumo'
            ];

            // Aplicar estilos a los headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ];

            // Escribir headers
            $row = 7;
            foreach ($headers as $col => $header) {
                $cell = chr(65 + $col) . $row;
                $sheet->setCellValue($cell, $header);
                $sheet->getStyle($cell)->applyFromArray($headerStyle);
            }
            $row++;

            // Agrupar por actividad si hay diferentes items
            $itemsGrouped = $solicitudItems->groupBy('codigo_item');

            $totalGeneral = 0;

            foreach ($itemsGrouped as $itemCodigo => $items) {
                // Escribir título de la actividad si hay más de un item
                if ($itemsGrouped->count() > 1) {
                    $sheet->mergeCells('A' . $row . ':H' . $row);
                    $sheet->setCellValue('A' . $row, 'ITEM: ' . $itemCodigo);
                    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
                    $sheet->getStyle('A' . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE6F3FF');
                    $row++;

                    // Escribir headers nuevamente para cada grupo
                    foreach ($headers as $col => $header) {
                        $cell = chr(65 + $col) . $row;
                        $sheet->setCellValue($cell, $header);
                        $sheet->getStyle($cell)->applyFromArray($headerStyle);
                    }
                    $row++;
                }

                // Escribir datos
                foreach ($items as $item) {
                    $cantUnitaria = floatval($item->cant_unitaria);
                    $cantSolicitada = floatval($item->cant_solicitada);
                    $cantTotal = floatval($item->cant_total);
                    $totalGeneral += $cantTotal;

                    $sheet->setCellValue('A' . $row, $itemCodigo);
                    $sheet->setCellValue('B' . $row, $item->codigo_insumo ?? '0');
                    $sheet->setCellValue('C' . $row, $item->descripcion);
                    $sheet->setCellValue('D' . $row, $cantUnitaria);
                    $sheet->setCellValue('E' . $row, $cantSolicitada);
                    $sheet->setCellValue('F' . $row, $cantTotal);
                    $sheet->setCellValue('G' . $row, $item->um ?? '');
                    $sheet->setCellValue('H' . $row, ''); // tipo_insumo si existe

                    $row++;
                }

                // Fila vacía entre grupos
                if ($itemsGrouped->count() > 1) {
                    $row++;
                }
            }

            // Aplicar estilos a los datos
            $firstDataRow = 8;
            $lastDataRow = $row - 1;

            if ($firstDataRow <= $lastDataRow) {
                $dataStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ];

                $sheet->getStyle('A' . $firstDataRow . ':H' . $lastDataRow)->applyFromArray($dataStyle);

                // Formato numérico
                $sheet->getStyle('D' . $firstDataRow . ':F' . $lastDataRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                // Ajustar anchos
                $columnWidths = ['A' => 12, 'B' => 15, 'C' => 40, 'D' => 20, 'E' => 20, 'F' => 15, 'G' => 8, 'H' => 12];
                foreach ($columnWidths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                // Alineación
                $sheet->getStyle('A' . $firstDataRow . ':B' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . $firstDataRow . ':F' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('G' . $firstDataRow . ':H' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Resumen
            $row += 2;
            $sheet->setCellValue('A' . $row, 'RESUMEN:');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            $sheet->setCellValue('A' . $row, 'Generado: ' . date('Y-m-d H:i:s'));
            $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->getColor()->setRGB('666666');

            // Crear respuesta para descargar
            $fileName = $numeroSolicitud . '.xlsx';

            return response()->streamDownload(
                function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                },
                $fileName,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error al generar Excel de solicitud: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 1. Verificar si existe PDF
    public function verificarPdfSolicitud(Request $request)
    {
        $request->validate([
            'numero_solicitud' => 'required|string',
            'codigo_proyecto' => 'required|string'
        ]);

        try {
            $numeroSolicitud = $request->numero_solicitud;
            $codigoProyecto = $request->codigo_proyecto;

            // Buscar la solicitud
            $solicitud = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            if (!$solicitud) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La solicitud no existe'
                ], 404);
            }

            // Buscar adjuntos para esta solicitud
            $adjunto = SolicitudMaterialAdjunto::where('solicitud_id', $solicitud->id)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            $existe = false;
            $data = null;

            if ($adjunto) {
                // Verificar que el archivo exista físicamente
                if (Storage::disk('public')->exists($adjunto->ruta_archivo)) {
                    $existe = true;
                    $data = [
                        'adjunto_id' => $adjunto->id,
                        'ruta_archivo' => $adjunto->ruta_archivo,
                        'nombre_original' => $adjunto->nombre_original,
                        'extension' => $adjunto->extension,
                        'tamano' => $adjunto->tamano,
                        'creado' => $adjunto->created_at,
                        'url_descarga' => Storage::disk('public')->url($adjunto->ruta_archivo)
                    ];
                } else {
                    // El registro existe pero el archivo no, eliminar registro
                    $adjunto->delete();
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'existe' => $existe,
                    'adjunto' => $data,
                    'numero_solicitud' => $numeroSolicitud
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error verificando PDF: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al verificar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    // 2. Descargar PDF
    public function descargarPdfSolicitud(Request $request)
    {
        $request->validate([
            'numero_solicitud' => 'required|string',
            'codigo_proyecto' => 'required|string'
        ]);

        try {
            $numeroSolicitud = $request->numero_solicitud;
            $codigoProyecto = $request->codigo_proyecto;

            // Buscar la solicitud
            $solicitud = SolicitudMaterialInge::where('numero_solicitud', $numeroSolicitud)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            if (!$solicitud) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La solicitud no existe'
                ], 404);
            }

            // Buscar adjunto para esta solicitud
            $adjunto = SolicitudMaterialAdjunto::where('solicitud_id', $solicitud->id)
                ->where('codigo_proyecto', $codigoProyecto)
                ->first();

            if (!$adjunto) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se encontró el archivo PDF para esta solicitud'
                ], 404);
            }

            // Verificar que el archivo exista físicamente
            if (!Storage::disk('public')->exists($adjunto->ruta_archivo)) {
                // Eliminar registro si el archivo no existe
                $adjunto->delete();
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo PDF no existe en el servidor'
                ], 404);
            }

            // Obtener ruta completa del archivo
            $filePath = Storage::disk('public')->path($adjunto->ruta_archivo);

            // Descargar el archivo con el nombre original
            return response()->download($filePath, $adjunto->nombre_original, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Content-Length' => $adjunto->tamano
            ]);
        } catch (\Exception $e) {
            Log::error('Error descargando PDF: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al descargar PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}
