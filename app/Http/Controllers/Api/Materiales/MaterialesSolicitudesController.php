<?php

namespace App\Http\Controllers\Api\Materiales;

use App\Http\Controllers\Controller;
use App\Models\MaterialSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialesSolicitudesController extends Controller
{

    public function cargueProyecion(Request $request)
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
            $ultimaCantidad = 0; // 👈 Nueva variable para almacenar la última cantidad
            $errores = [];
            $datosInsertar = [];

            // Helpers
            $toDecimal = fn($v) => is_numeric(str_replace(',', '.', $v)) ? floatval(str_replace(',', '.', $v)) : 0;
            $toInt     = fn($v) => is_numeric($v) ? intval($v) : 0;
            $toText    = fn($v) => trim($v ?? '');

            foreach ($registros as $i => $fila) {
                $fila = array_pad(array_map('trim', $fila), 12, '');

                $codigo = $fila[0];
                $descripcion = $fila[1];
                $cantidad = $fila[4];
                $cant_apu = $fila[6];

                // Autorrellenar código
                if (!empty($codigo)) {
                    $ultimoCodigo = $codigo;
                } elseif (!empty($descripcion) && !empty($ultimoCodigo)) {
                    $codigo = $fila[0] = $ultimoCodigo;
                }

                // 👇 Autorrellenar cantidad
                if (!empty($cantidad) && is_numeric(str_replace(',', '.', $cantidad))) {
                    $ultimaCantidad = $toDecimal($cantidad);
                } elseif (empty($cantidad) && $ultimaCantidad > 0 && !empty($descripcion)) {
                    $cantidad = $fila[4] = $ultimaCantidad;
                }

                $padre = $fila[2];

                // Filtrar módulo 4
                if (!str_starts_with($codigo, '4') && !str_starts_with($padre, '4')) {
                    continue;
                }

                if (empty($descripcion)) {
                    $errores[] = "Fila " . ($i + 2) . ": El campo descripción está vacío";
                    continue;
                }

                // ✅ CÁLCULO DEL NIVEL
                $nivel = 1;

                if (is_numeric($padre)) {
                    // Padre numérico
                    if (str_contains($padre, '.') && $padre == $codigo) {
                        $nivel = 2;
                    } else {
                        $nivel = 1;
                    }
                } else {
                    // Padre es texto
                    $nivel = 3;
                }

                // 👇 CALCULAR CANT_TOTAL (cantidad * cant_apu)
                $cantidadDecimal = $toDecimal($cantidad);
                $cantApuDecimal = $toDecimal($cant_apu);
                $cant_total = $cantidadDecimal * $cantApuDecimal;

                $datosInsertar[] = [
                    'user_id'         => Auth::id(),
                    'codigo_proyecto' => $request->codigo_proyecto,
                    'codigo'          => $toText($codigo),
                    'descripcion'     => $toText($descripcion),
                    'padre'           => $toText($padre),
                    'nivel'           => $nivel,
                    'um'              => $toText($fila[3]),
                    'cantidad'        => $cantidadDecimal,
                    'subcapitulo'     => $toText($fila[5]),
                    'cant_apu'        => $cantApuDecimal,
                    'cant_total'      => $cant_total, // 👈 NUEVO CAMPO CALCULADO
                    'rend'            => $toDecimal($fila[7]),
                    'iva'             => $toInt($fila[8]),
                    'valor_sin_iva'   => $toDecimal($fila[9]),
                    'tipo_insumo'     => $toText($fila[10]),
                    'agrupacion'      => $toText($fila[11]),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
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

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo cargado correctamente.',
                'cantidad' => count($datosInsertar),
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

                        // Actualizar el campo 'cantidad'
                        $itemActualizar->cantidad = $nuevaCantidad;
                        $itemActualizar->save();

                        $actualizacionesExitosas[] = [
                            'id' => $itemActualizar->id,
                            'descripcion' => $itemActualizar->descripcion,
                            'cantidad_anterior' => $cantidadActual,
                            'cantidad_nueva' => $nuevaCantidad,
                            'tipo' => $tipo,
                            'campo_actualizado' => 'cantidad',
                            'es_padre_4' => true
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

                    // Actualizar SOLO este item específico en 'cant_apu' y 'cant_total'
                    $item->cant_apu = $nuevaCantidad;
                    $item->cant_total = $nuevaCantidadTotal;
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
                        'es_padre_4' => false
                    ];

                    // NO ACTUALIZAR HIJOS NI NIETOS - Solo el item específico
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
                'actualizados' => $actualizacionesExitosas,
                'errores' => $errores
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

    public function generarExcelAxuiliarMaterial(Request $request)
    {
        try {
            // Validar que la solicitud tenga updates
            if (!$request->has('updates') || !is_array($request->updates)) {
                throw new \Exception('No se proporcionaron datos para generar Excel');
            }

            $updates = $request->updates;
            $codigo_proyecto = $request->codigo_proyecto;
            $fecha = $request->fecha ?? now()->toISOString();

            // Buscar descripción del proyecto en las tablas proyecto y proyectos_casas
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

            // Definir los headers de la tabla - CORREGIDOS
            $headers = [
                'Proyecto',           // descripcion_proyecto de proyecto/proyectos_casas
                'Descripción',        // descripcion de la tabla materiales
                'Padre',
                'nivel',
                'UM',
                'CANTIDAD',
                'cantidad_nueva',
                'SUBCAPITULO',
                'Cant APU',
                'cant apu nueva',
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

                // Determinar las cantidades según el nivel y tipo de operación
                if ($material->nivel == 1) {
                    // NIVEL 1 - usa campo 'cantidad'
                    $cantidadActual = floatval($material->cantidad);
                    $cantidadNueva = $tipo == 1 ? $cantidadActual + $cantidadSolicitada : $cantidadActual - $cantidadSolicitada;

                    $data = [
                        'proyecto' => $descripcion_proyecto, // Descripción del proyecto
                        'descripcion' => $material->descripcion, // Descripción del material
                        'padre' => $material->padre ?? '',
                        'nivel' => $material->nivel,
                        'um' => $material->um ?? '',
                        'cantidad' => number_format($cantidadActual, 10, '.', ''),
                        'cantidad_nueva' => number_format($cantidadNueva, 10, '.', ''),
                        'subcapitulo' => $material->subcapitulo ?? '',
                        'cant_apu' => '',
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
                        'proyecto' => $descripcion_proyecto, // Descripción del proyecto
                        'descripcion' => $material->descripcion, // Descripción del material
                        'padre' => $material->padre ?? '',
                        'nivel' => $material->nivel,
                        'um' => $material->um ?? '',
                        'cantidad' => '',
                        'cantidad_nueva' => '',
                        'subcapitulo' => $material->subcapitulo ?? '',
                        'cant_apu' => number_format($cantidadApuActual, 10, '.', ''),
                        'cant_apu_nueva' => number_format($cantidadApuNueva, 10, '.', ''),
                        'rend' => $material->rend ? number_format($material->rend, 4, '.', '') : '',
                        'iva' => $material->iva ?? 0,
                        'valor_sin_iva' => $material->valor_sin_iva ? number_format($material->valor_sin_iva, 4, '.', '') : '',
                        'tipo_insumo' => $material->tipo_insumo ?? '',
                        'agrupacion' => $material->agrupacion ?? ''
                    ];
                }

                $itemsProcesados[] = $data;

                // Escribir datos en la hoja
                $sheet->setCellValue('A' . $row, $data['proyecto']);        // Proyecto (descripción del proyecto)
                $sheet->setCellValue('B' . $row, $data['descripcion']);     // Descripción (del material)
                $sheet->setCellValue('C' . $row, $data['padre']);
                $sheet->setCellValue('D' . $row, $data['nivel']);
                $sheet->setCellValue('E' . $row, $data['um']);
                $sheet->setCellValue('F' . $row, $data['cantidad']);
                $sheet->setCellValue('G' . $row, $data['cantidad_nueva']);
                $sheet->setCellValue('H' . $row, $data['subcapitulo']);
                $sheet->setCellValue('I' . $row, $data['cant_apu']);
                $sheet->setCellValue('J' . $row, $data['cant_apu_nueva']);
                $sheet->setCellValue('K' . $row, $data['rend']);
                $sheet->setCellValue('L' . $row, $data['iva']);
                $sheet->setCellValue('M' . $row, $data['valor_sin_iva']);
                $sheet->setCellValue('N' . $row, $data['tipo_insumo']);
                $sheet->setCellValue('O' . $row, $data['agrupacion']);

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
                $sheet->getStyle('A2:O' . $lastRow)->applyFromArray($dataStyle);
            }

            // Autoajustar columnas
            foreach (range('A', 'O') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Agregar información adicional al final
            $sheet->setCellValue('A' . ($row + 1), 'Código Proyecto: ' . $codigo_proyecto);
            $sheet->setCellValue('A' . ($row + 2), 'Proyecto: ' . $descripcion_proyecto);
            $sheet->setCellValue('A' . ($row + 3), 'Fecha de generación: ' . date('Y-m-d H:i:s', strtotime($fecha)));
            $sheet->setCellValue('A' . ($row + 4), 'Total de items: ' . count($itemsProcesados));

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

    /**
     * Obtener la descripción del proyecto desde las tablas proyecto y proyectos_casas
     */


    /* INGENIEROS */
    /* SOLICITUD MATERIAL  */



    // public function solicitudMaterialIngenieros(Request $request)
    // {
    //     try {
    //         info('Data recibida para Excel:', $request->all());

    //         // Validar que la solicitud tenga items
    //         if (!$request->has('items') || !is_array($request->items)) {
    //             throw new \Exception('No se proporcionaron datos para generar Excel');
    //         }

    //         $items = $request->items;
    //         $codigo_proyecto = $request->codigo_proyecto;
    //         $fecha = $request->fecha ?? now()->toISOString();

    //         info("ds");

    //         // Buscar descripción del proyecto
    //         $descripcion_proyecto = $this->obtenerDescripcionProyecto($codigo_proyecto);

    //         if (empty($items)) {
    //             throw new \Exception('No se proporcionaron items válidos');
    //         }

    //         // Crear el spreadsheet
    //         $spreadsheet = new Spreadsheet();
    //         $sheet = $spreadsheet->getActiveSheet();
    //         $sheet->setTitle('Solicitud de Materiales');

    //         // Definir los headers según el formato solicitado
    //         $headers = [
    //             'ACTIVIDAD',
    //             'item',
    //             'codigo material',
    //             'descripcion',
    //             'cantidad x apartamento',
    //             'cant solicitada total',
    //             'total',
    //             'UM',
    //             'tipo insumo'
    //         ];

    //         // Aplicar estilos a los headers
    //         $headerStyle = [
    //             'font' => [
    //                 'bold' => true,
    //                 'color' => ['rgb' => 'FFFFFF']
    //             ],
    //             'fill' => [
    //                 'fillType' => Fill::FILL_SOLID,
    //                 'startColor' => ['rgb' => '4472C4']
    //             ],
    //             'borders' => [
    //                 'allBorders' => [
    //                     'borderStyle' => Border::BORDER_THIN,
    //                     'color' => ['rgb' => '000000']
    //                 ]
    //             ],
    //             'alignment' => [
    //                 'horizontal' => Alignment::HORIZONTAL_CENTER,
    //                 'vertical' => Alignment::VERTICAL_CENTER
    //             ]
    //         ];

    //         info("ds");
    //         // Escribir encabezado general del archivo
    //         $sheet->mergeCells('A1:I1');
    //         $sheet->setCellValue('A1', 'SOLICITUD DE MATERIALES');
    //         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    //         $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //         $sheet->mergeCells('A2:I2');
    //         $sheet->setCellValue('A2', 'Proyecto: ' . $descripcion_proyecto);
    //         $sheet->getStyle('A2')->getFont()->setBold(true);

    //         $sheet->mergeCells('A3:I3');
    //         $sheet->setCellValue('A3', 'Fecha: ' . date('Y-m-d H:i:s', strtotime($fecha)));
    //         $sheet->getStyle('A3')->getFont()->setBold(true);

    //         // Fila vacía
    //         $sheet->setCellValue('A4', '');

    //         // Preparar datos para la tabla
    //         $row = 5; // Empezar en la fila 5
    //         $actividadAnterior = null;
    //         $totalGeneral = 0;
    //         $itemsProcesados = [];

    //         foreach ($items as $item) {
    //             // Verificar si es un padre (nivel 2)
    //             if (isset($item['es_padre']) && $item['es_padre'] && $item['nivel'] == 2) {

    //                 $actividadActual = $item['descripcion'];
    //                 $cantidadSolicitada = floatval($item['cantidad'] ?? 0);

    //                 // Si cambia la actividad, agregar separador
    //                 if ($actividadAnterior !== $actividadActual && $actividadAnterior !== null) {
    //                     // Fila vacía entre actividades
    //                     $row++;
    //                     $actividadAnterior = $actividadActual;
    //                 }

    //                 if ($actividadAnterior !== $actividadActual) {
    //                     $actividadAnterior = $actividadActual;

    //                     // 1. Escribir título de la actividad
    //                     $sheet->mergeCells('A' . $row . ':I' . $row);
    //                     $sheet->setCellValue('A' . $row, 'ACTIVIDAD: ' . $actividadActual);
    //                     $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    //                     $sheet->getStyle('A' . $row)->getFill()
    //                         ->setFillType(Fill::FILL_SOLID)
    //                         ->getStartColor()->setARGB('FFE6F3FF');
    //                     $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    //                     $row++;

    //                     // 2. Escribir encabezados de columnas para esta actividad
    //                     foreach ($headers as $col => $header) {
    //                         $cell = chr(65 + $col) . $row; // A5, B5, C5, etc.
    //                         $sheet->setCellValue($cell, $header);
    //                         $sheet->getStyle($cell)->applyFromArray($headerStyle);
    //                     }

    //                     $row++;
    //                 }
    //                  info("dssds");

    //                 // Procesar los items según si tiene hijos o no
    //                 if (isset($item['tiene_hijos_seleccionados']) && $item['tiene_hijos_seleccionados'] && isset($item['subHijos'])) {
    //                     // Caso 1: Padre con hijos seleccionados
    //                     foreach ($item['subHijos'] as $hijo) {
    //                         // Buscar el material en la base de datos para obtener cant_apu
    //                         $material = MaterialSolicitud::where('id', $hijo['id'])
    //                             ->where('codigo_proyecto', $codigo_proyecto)
    //                             ->first();

    //                         if ($material) {
    //                             $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
    //                             $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
    //                             $totalGeneral += $cantidadTotal;

    //                             // Escribir datos del hijo
    //                             $sheet->setCellValue('A' . $row, ''); // ACTIVIDAD vacío (ya está en el título)
    //                             $sheet->setCellValue('B' . $row, $material->codigo);
    //                             $sheet->setCellValue('C' . $row, '0'); // codigo material = 0
    //                             $sheet->setCellValue('D' . $row, $material->descripcion);
    //                             $sheet->setCellValue('E' . $row, $cantidadPorApartamento);
    //                             $sheet->setCellValue('F' . $row, $cantidadSolicitada);
    //                             $sheet->setCellValue('G' . $row, $cantidadTotal);
    //                             $sheet->setCellValue('H' . $row, $material->um ?? '');
    //                             $sheet->setCellValue('I' . $row, $material->tipo_insumo ?? '');

    //                             $itemsProcesados[] = [
    //                                 'actividad' => $actividadActual,
    //                                 'item' => $material->codigo,
    //                                 'descripcion' => $material->descripcion,
    //                                 'cant_apu' => $cantidadPorApartamento,
    //                                 'cant_solicitada' => $cantidadSolicitada,
    //                                 'total' => $cantidadTotal,
    //                                 'um' => $material->um,
    //                                 'tipo_insumo' => $material->tipo_insumo
    //                             ];

    //                             $row++;
    //                         }
    //                     }
    //                 } else {
    //                     // Caso 2: Padre sin hijos seleccionados (solo el padre)
    //                     $material = MaterialSolicitud::where('id', $item['id'])
    //                         ->where('codigo_proyecto', $codigo_proyecto)
    //                         ->first();

    //                     if ($material) {
    //                         $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
    //                         $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
    //                         $totalGeneral += $cantidadTotal;

    //                         // Escribir datos del padre
    //                         $sheet->setCellValue('A' . $row, '');
    //                         $sheet->setCellValue('B' . $row, $material->codigo);
    //                         $sheet->setCellValue('C' . $row, '0');
    //                         $sheet->setCellValue('D' . $row, $material->descripcion);
    //                         $sheet->setCellValue('E' . $row, $cantidadPorApartamento);
    //                         $sheet->setCellValue('F' . $row, $cantidadSolicitada);
    //                         $sheet->setCellValue('G' . $row, $cantidadTotal);
    //                         $sheet->setCellValue('H' . $row, $material->um ?? '');
    //                         $sheet->setCellValue('I' . $row, $material->tipo_insumo ?? '');

    //                         $itemsProcesados[] = [
    //                             'actividad' => $actividadActual,
    //                             'item' => $material->codigo,
    //                             'descripcion' => $material->descripcion,
    //                             'cant_apu' => $cantidadPorApartamento,
    //                             'cant_solicitada' => $cantidadSolicitada,
    //                             'total' => $cantidadTotal,
    //                             'um' => $material->um,
    //                             'tipo_insumo' => $material->tipo_insumo
    //                         ];

    //                         $row++;
    //                     }
    //                 }
    //             }
    //         }

    //         info("ds");

    //         // Aplicar estilos a los datos
    //         $dataStyle = [
    //             'borders' => [
    //                 'allBorders' => [
    //                     'borderStyle' => Border::BORDER_THIN,
    //                     'color' => ['rgb' => '000000']
    //                 ]
    //             ],
    //             'alignment' => [
    //                 'vertical' => Alignment::VERTICAL_CENTER
    //             ]
    //         ];

    //         // Aplicar formato numérico a las columnas de cantidades
    //         if ($row > 6) { // Si hay al menos una fila de datos
    //             $firstDataRow = 7; // Primera fila de datos después de encabezados
    //             $lastDataRow = $row - 1;

    //             // Aplicar bordes a todos los datos
    //             $sheet->getStyle('A' . $firstDataRow . ':I' . $lastDataRow)->applyFromArray($dataStyle);

    //             // Formato numérico para columnas E, F, G (cantidades)
    //             $sheet->getStyle('E' . $firstDataRow . ':G' . $lastDataRow)
    //                 ->getNumberFormat()
    //                 ->setFormatCode('#,##0.00');

    //             // Alinear celdas
    //             $sheet->getStyle('A' . $firstDataRow . ':I' . $lastDataRow)
    //                 ->getAlignment()
    //                 ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //                 ->setVertical(Alignment::VERTICAL_CENTER);

    //             // Ajustar anchos de columnas
    //             $columnWidths = [
    //                 'A' => 30,  // ACTIVIDAD
    //                 'B' => 15,  // item
    //                 'C' => 15,  // codigo material
    //                 'D' => 40,  // descripcion
    //                 'E' => 20,  // cantidad x apartamento
    //                 'F' => 20,  // cant solicitada total
    //                 'G' => 15,  // total
    //                 'H' => 10,  // UM
    //                 'I' => 15,  // tipo insumo
    //             ];

    //             foreach ($columnWidths as $column => $width) {
    //                 $sheet->getColumnDimension($column)->setWidth($width);
    //             }
    //         }

    //         // Agregar información adicional al final
    //         $sheet->setCellValue('A' . ($row + 2), 'Código Proyecto: ' . $codigo_proyecto);
    //         $sheet->setCellValue('A' . ($row + 3), 'Proyecto: ' . $descripcion_proyecto);
    //         $sheet->setCellValue('A' . ($row + 4), 'Fecha de generación: ' . date('Y-m-d H:i:s', strtotime($fecha)));
    //         $sheet->setCellValue('A' . ($row + 5), 'Total de items procesados: ' . count($itemsProcesados));
    //         $sheet->setCellValue('A' . ($row + 6), 'Total general calculado: ' . number_format($totalGeneral, 2));

    //         // Crear respuesta para descargar el archivo
    //         $fileName = 'solicitud_materiales_' . $codigo_proyecto . '_' . date('Ymd_His') . '.xlsx';

    //          info("ds1111");

    //         return new StreamedResponse(
    //             function () use ($spreadsheet) {
    //                 $writer = new Xlsx($spreadsheet);
    //                 $writer->save('php://output');
    //             },
    //             200,
    //             [
    //                 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //                 'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    //                 'Cache-Control' => 'max-age=0',
    //             ]
    //         );

    //         info("er");
    //     } catch (\Exception $e) {
    //         Log::error('Error al generar Excel: ' . $e->getMessage());
    //         Log::error('Trace: ' . $e->getTraceAsString());

    //         // En caso de error, crear un Excel con el mensaje de error
    //         $spreadsheet = new Spreadsheet();
    //         $sheet = $spreadsheet->getActiveSheet();
    //         $sheet->setCellValue('A1', 'Error al generar el reporte');
    //         $sheet->setCellValue('A2', $e->getMessage());
    //         $sheet->setCellValue('A3', 'Trace:');
    //         $sheet->setCellValue('A4', $e->getTraceAsString());

    //         $fileName = 'error_solicitud_materiales_' . date('Ymd_His') . '.xlsx';

    //         return new StreamedResponse(
    //             function () use ($spreadsheet) {
    //                 $writer = new Xlsx($spreadsheet);
    //                 $writer->save('php://output');
    //             },
    //             500,
    //             [
    //                 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //                 'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    //             ]
    //         );
    //     }
    // }

    // public function solicitudMaterialIngenieros(Request $request)
    // {

    //     info($request->all());
    //     try {
    //         // Validar que la solicitud tenga items
    //         if (!$request->has('items') || !is_array($request->items)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No se proporcionaron datos para generar Excel'
    //             ], 400);
    //         }

    //         $items = $request->items;
    //         $codigo_proyecto = $request->codigo_proyecto;
    //         $fecha = $request->fecha ?? now()->toISOString();

    //         // Buscar descripción del proyecto
    //         $descripcion_proyecto = $this->obtenerDescripcionProyecto($codigo_proyecto);

    //         if (empty($items)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No se proporcionaron items válidos'
    //             ], 400);
    //         }

    //         // Crear el spreadsheet
    //         $spreadsheet = new Spreadsheet();
    //         $sheet = $spreadsheet->getActiveSheet();
    //         $sheet->setTitle('Solicitud de Materiales');

    //         // Definir los headers según el formato solicitado
    //         $headers = [
    //             'ACTIVIDAD',
    //             'item',
    //             'codigo material',
    //             'descripcion',
    //             'cantidad x apartamento',
    //             'cant solicitada total',
    //             'total',
    //             'UM',
    //             'tipo insumo'
    //         ];

    //         // Aplicar estilos a los headers
    //         $headerStyle = [
    //             'font' => [
    //                 'bold' => true,
    //                 'color' => ['rgb' => 'FFFFFF']
    //             ],
    //             'fill' => [
    //                 'fillType' => Fill::FILL_SOLID,
    //                 'startColor' => ['rgb' => '4472C4']
    //             ],
    //             'borders' => [
    //                 'allBorders' => [
    //                     'borderStyle' => Border::BORDER_THIN,
    //                     'color' => ['rgb' => '000000']
    //                 ]
    //             ],
    //             'alignment' => [
    //                 'horizontal' => Alignment::HORIZONTAL_CENTER,
    //                 'vertical' => Alignment::VERTICAL_CENTER
    //             ]
    //         ];

    //         // Escribir encabezado general del archivo
    //         $sheet->mergeCells('A1:I1');
    //         $sheet->setCellValue('A1', 'SOLICITUD DE MATERIALES');
    //         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    //         $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    //         $sheet->mergeCells('A2:I2');
    //         $sheet->setCellValue('A2', 'Proyecto: ' . $descripcion_proyecto);
    //         $sheet->getStyle('A2')->getFont()->setBold(true);

    //         $sheet->mergeCells('A3:I3');
    //         $sheet->setCellValue('A3', 'Fecha: ' . date('Y-m-d H:i:s', strtotime($fecha)));
    //         $sheet->getStyle('A3')->getFont()->setBold(true);

    //         // Fila vacía
    //         $sheet->setCellValue('A4', '');

    //         // Preparar datos para la tabla
    //         $row = 5; // Empezar en la fila 5
    //         $actividadAnterior = null;
    //         $totalGeneral = 0;
    //         $itemsProcesados = [];

    //         // Grupo items por actividad (descripción del padre nivel 2)
    //         $itemsPorActividad = [];

    //         foreach ($items as $item) {
    //             // Solo procesar padres de nivel 2
    //             if (isset($item['es_padre']) && $item['es_padre'] && $item['nivel'] == 2) {
    //                 $actividad = $item['descripcion'];
    //                 if (!isset($itemsPorActividad[$actividad])) {
    //                     $itemsPorActividad[$actividad] = [];
    //                 }
    //                 $itemsPorActividad[$actividad][] = $item;
    //             }
    //         }

    //         // Procesar cada actividad
    //         foreach ($itemsPorActividad as $actividad => $itemsActividad) {
    //             // 1. Escribir título de la actividad
    //             $sheet->mergeCells('A' . $row . ':I' . $row);
    //             $sheet->setCellValue('A' . $row, 'ACTIVIDAD: ' . $actividad);
    //             $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
    //             $sheet->getStyle('A' . $row)->getFill()
    //                 ->setFillType(Fill::FILL_SOLID)
    //                 ->getStartColor()->setARGB('FFE6F3FF');
    //             $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    //             $row++;

    //             // 2. Escribir encabezados de columnas para esta actividad
    //             foreach ($headers as $col => $header) {
    //                 $cell = chr(65 + $col) . $row; // A5, B5, C5, etc.
    //                 $sheet->setCellValue($cell, $header);
    //                 $sheet->getStyle($cell)->applyFromArray($headerStyle);
    //             }
    //             $row++;

    //             // 3. Procesar items de esta actividad
    //             foreach ($itemsActividad as $item) {
    //                 $cantidadSolicitada = floatval($item['cantidad'] ?? 0);

    //                 if (isset($item['tiene_hijos_seleccionados']) && $item['tiene_hijos_seleccionados'] && isset($item['subHijos']) && is_array($item['subHijos'])) {
    //                     // Caso 1: Padre con hijos seleccionados
    //                     foreach ($item['subHijos'] as $hijo) {
    //                         // Buscar el material en la base de datos para obtener cant_apu
    //                         $material = MaterialSolicitud::where('id', $hijo['id'])
    //                             ->where('codigo_proyecto', $codigo_proyecto)
    //                             ->first();

    //                         if ($material) {
    //                             $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
    //                             $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
    //                             $totalGeneral += $cantidadTotal;

    //                             // Escribir datos del hijo
    //                             $sheet->setCellValue('A' . $row, ''); // ACTIVIDAD vacío (ya está en el título)
    //                             $sheet->setCellValue('B' . $row, $material->codigo);
    //                             $sheet->setCellValue('C' . $row, '0'); // codigo material = 0
    //                             $sheet->setCellValue('D' . $row, $material->descripcion);
    //                             $sheet->setCellValue('E' . $row, $cantidadPorApartamento);
    //                             $sheet->setCellValue('F' . $row, $cantidadSolicitada);
    //                             $sheet->setCellValue('G' . $row, $cantidadTotal);
    //                             $sheet->setCellValue('H' . $row, $material->um ?? '');
    //                             $sheet->setCellValue('I' . $row, $material->tipo_insumo ?? '');

    //                             $itemsProcesados[] = [
    //                                 'actividad' => $actividad,
    //                                 'item' => $material->codigo,
    //                                 'descripcion' => $material->descripcion,
    //                                 'cant_apu' => $cantidadPorApartamento,
    //                                 'cant_solicitada' => $cantidadSolicitada,
    //                                 'total' => $cantidadTotal,
    //                                 'um' => $material->um,
    //                                 'tipo_insumo' => $material->tipo_insumo
    //                             ];

    //                             $row++;
    //                         }
    //                     }
    //                 } else {
    //                     // Caso 2: Padre sin hijos seleccionados (solo el padre)
    //                     $material = MaterialSolicitud::where('id', $item['id'])
    //                         ->where('codigo_proyecto', $codigo_proyecto)
    //                         ->first();

    //                     if ($material) {
    //                         $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
    //                         $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
    //                         $totalGeneral += $cantidadTotal;

    //                         // Escribir datos del padre
    //                         $sheet->setCellValue('A' . $row, '');
    //                         $sheet->setCellValue('B' . $row, $material->codigo);
    //                         $sheet->setCellValue('C' . $row, '0');
    //                         $sheet->setCellValue('D' . $row, $material->descripcion);
    //                         $sheet->setCellValue('E' . $row, $cantidadPorApartamento);
    //                         $sheet->setCellValue('F' . $row, $cantidadSolicitada);
    //                         $sheet->setCellValue('G' . $row, $cantidadTotal);
    //                         $sheet->setCellValue('H' . $row, $material->um ?? '');
    //                         $sheet->setCellValue('I' . $row, $material->tipo_insumo ?? '');

    //                         $itemsProcesados[] = [
    //                             'actividad' => $actividad,
    //                             'item' => $material->codigo,
    //                             'descripcion' => $material->descripcion,
    //                             'cant_apu' => $cantidadPorApartamento,
    //                             'cant_solicitada' => $cantidadSolicitada,
    //                             'total' => $cantidadTotal,
    //                             'um' => $material->um,
    //                             'tipo_insumo' => $material->tipo_insumo
    //                         ];

    //                         $row++;
    //                     }
    //                 }
    //             }

    //             // Fila vacía entre actividades
    //             $row++;
    //         }

    //         // Aplicar estilos a los datos
    //         $dataStyle = [
    //             'borders' => [
    //                 'allBorders' => [
    //                     'borderStyle' => Border::BORDER_THIN,
    //                     'color' => ['rgb' => '000000']
    //                 ]
    //             ],
    //             'alignment' => [
    //                 'vertical' => Alignment::VERTICAL_CENTER
    //             ]
    //         ];

    //         // Aplicar formato numérico a las columnas de cantidades
    //         $firstDataRow = 6; // Primera fila de datos (después de encabezados)
    //         $lastDataRow = $row - 1;

    //         if ($lastDataRow >= $firstDataRow) {
    //             // Aplicar bordes a todos los datos
    //             $sheet->getStyle('A' . $firstDataRow . ':I' . $lastDataRow)->applyFromArray($dataStyle);

    //             // Formato numérico para columnas E, F, G (cantidades)
    //             $sheet->getStyle('E' . $firstDataRow . ':G' . $lastDataRow)
    //                 ->getNumberFormat()
    //                 ->setFormatCode('#,##0.00');

    //             // Alinear celdas
    //             $sheet->getStyle('A' . $firstDataRow . ':I' . $lastDataRow)
    //                 ->getAlignment()
    //                 ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    //                 ->setVertical(Alignment::VERTICAL_CENTER);

    //             // Ajustar anchos de columnas
    //             $columnWidths = [
    //                 'A' => 30,  // ACTIVIDAD
    //                 'B' => 15,  // item
    //                 'C' => 15,  // codigo material
    //                 'D' => 40,  // descripcion
    //                 'E' => 20,  // cantidad x apartamento
    //                 'F' => 20,  // cant solicitada total
    //                 'G' => 15,  // total
    //                 'H' => 10,  // UM
    //                 'I' => 15,  // tipo insumo
    //             ];

    //             foreach ($columnWidths as $column => $width) {
    //                 $sheet->getColumnDimension($column)->setWidth($width);
    //             }
    //         }

    //         // Agregar información adicional al final
    //         $row += 2;
    //         $sheet->setCellValue('A' . $row, 'Código Proyecto: ' . $codigo_proyecto);
    //         $sheet->setCellValue('A' . ($row + 1), 'Proyecto: ' . $descripcion_proyecto);
    //         $sheet->setCellValue('A' . ($row + 2), 'Fecha de generación: ' . date('Y-m-d H:i:s', strtotime($fecha)));
    //         $sheet->setCellValue('A' . ($row + 3), 'Total de items procesados: ' . count($itemsProcesados));
    //         $sheet->setCellValue('A' . ($row + 4), 'Total general calculado: ' . number_format($totalGeneral, 2));

    //         // Crear respuesta para descargar el archivo
    //         $fileName = 'solicitud_materiales_' . $codigo_proyecto . '_' . date('Ymd_His') . '.xlsx';

    //         // Guardar temporalmente para debug (opcional)
    //         // $writer = new Xlsx($spreadsheet);
    //         // $writer->save(storage_path('app/' . $fileName));

    //         $response = new StreamedResponse(
    //             function () use ($spreadsheet) {
    //                 $writer = new Xlsx($spreadsheet);
    //                 $writer->save('php://output');
    //             },
    //             200,
    //             [
    //                 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //                 'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    //                 'Cache-Control' => 'max-age=0',
    //             ]
    //         );

    //         return $response;
    //     } catch (\Exception $e) {
    //         Log::error('Error al generar Excel: ' . $e->getMessage());
    //         Log::error('Trace: ' . $e->getTraceAsString());

    //         // IMPORTANTE: Cuando hay error, devolver JSON, no Excel
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error al generar el Excel: ' . $e->getMessage(),
    //             'trace' => config('app.debug') ? $e->getTraceAsString() : null
    //         ], 500);
    //     }
    // }

    public function solicitudMaterialIngenieros(Request $request)
{

    info($request->all());
    try {
        info('Data recibida para Excel:', $request->all());

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

        // Crear el spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Solicitud de Materiales');

        // Definir los headers
        $headers = [
            'ACTIVIDAD',
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

        // Escribir encabezado general
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'SOLICITUD DE MATERIALES');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', 'Código Proyecto: ' . $codigo_proyecto);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A3', 'Fecha: ' . date('Y-m-d H:i:s', strtotime($fecha)));
        $sheet->getStyle('A3')->getFont()->setBold(true);

        // Fila vacía
        $sheet->setCellValue('A4', '');

        // Preparar datos para la tabla
        $row = 5;
        $totalGeneral = 0;
        $itemsProcesados = [];

        foreach ($actividades as $actividad) {
            $nombreActividad = $actividad['actividad'] ?? 'Sin nombre';
            $itemActividad = $actividad['item'] ?? '';
            $dataActividad = $actividad['dataActividad'] ?? [];

            if (empty($dataActividad)) {
                continue;
            }

            // Escribir título de la actividad
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $sheet->setCellValue('A' . $row, 'ACTIVIDAD: ' . $nombreActividad . ' (Item: ' . $itemActividad . ')');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('A' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE6F3FF');
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $row++;

            // Escribir encabezados de columnas
            foreach ($headers as $col => $header) {
                $cell = chr(65 + $col) . $row;
                $sheet->setCellValue($cell, $header);
                $sheet->getStyle($cell)->applyFromArray($headerStyle);
            }
            $row++;

            // Procesar items de nivel 2
            foreach ($dataActividad as $nivel2) {
                $cantidadSolicitada = floatval($nivel2['cantidad'] ?? 0);
                
                // Buscar el material en la base de datos
                $material = MaterialSolicitud::where('id', $nivel2['id'])
                    ->where('codigo_proyecto', $codigo_proyecto)
                    ->first();

                if ($material) {
                    $cantidadPorApartamento = floatval($material->cant_apu ?? 0);
                    $cantidadTotal = $cantidadPorApartamento * $cantidadSolicitada;
                    $totalGeneral += $cantidadTotal;

                    // Escribir datos del nivel 2
                    $sheet->setCellValue('A' . $row, ''); // ACTIVIDAD vacío (ya está en el título)
                    $sheet->setCellValue('B' . $row, $material->codigo);
                    $sheet->setCellValue('C' . $row, '0'); // codigo material = 0
                    $sheet->setCellValue('D' . $row, $material->descripcion);
                    $sheet->setCellValue('E' . $row, $cantidadPorApartamento);
                    $sheet->setCellValue('F' . $row, $cantidadSolicitada);
                    $sheet->setCellValue('G' . $row, $cantidadTotal);
                    $sheet->setCellValue('H' . $row, $material->um ?? '');
                    $sheet->setCellValue('I' . $row, $material->tipo_insumo ?? '');

                    $itemsProcesados[] = [
                        'actividad' => $nombreActividad,
                        'item' => $material->codigo,
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
                                $hijoCantidadTotal = $hijoCantidadPorApartamento * $cantidadSolicitada;
                                $totalGeneral += $hijoCantidadTotal;

                                // Escribir datos del hijo
                                $sheet->setCellValue('A' . $row, '');
                                $sheet->setCellValue('B' . $row, $hijoMaterial->codigo);
                                $sheet->setCellValue('C' . $row, '0');
                                $sheet->setCellValue('D' . $row, $hijoMaterial->descripcion);
                                $sheet->setCellValue('E' . $row, $hijoCantidadPorApartamento);
                                $sheet->setCellValue('F' . $row, $cantidadSolicitada);
                                $sheet->setCellValue('G' . $row, $hijoCantidadTotal);
                                $sheet->setCellValue('H' . $row, $hijoMaterial->um ?? '');
                                $sheet->setCellValue('I' . $row, $hijoMaterial->tipo_insumo ?? '');

                                $row++;
                            }
                        }
                    }
                }
            }
            
            // Fila vacía entre actividades
            $row++;
        }

        // Aplicar estilos a los datos
        if ($row > 6) {
            $firstDataRow = 7;
            $lastDataRow = $row - 1;

            $dataStyle = [
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

            $sheet->getStyle('A' . $firstDataRow . ':I' . $lastDataRow)->applyFromArray($dataStyle);
            
            // Formato numérico
            $sheet->getStyle('E' . $firstDataRow . ':G' . $lastDataRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');

            // Ajustar anchos
            $columnWidths = [
                'A' => 30, 'B' => 15, 'C' => 15, 'D' => 40,
                'E' => 20, 'F' => 20, 'G' => 15, 'H' => 10, 'I' => 15,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }

        // Agregar información adicional
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Total actividades procesadas: ' . count($actividades));
        $sheet->setCellValue('A' . ($row + 1), 'Total items procesados: ' . count($itemsProcesados));
        $sheet->setCellValue('A' . ($row + 2), 'Total general calculado: ' . number_format($totalGeneral, 2));

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
}
