<?php

namespace App\Http\Controllers\Api\Materiales;

use App\Http\Controllers\Controller;
use App\Models\MaterialSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Svg\Tag\Rect;

class MaterialesSolicitudesController extends Controller
{
    // public function cargueProyecion(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $request->validate([
    //             'archivo' => 'required|file|mimes:xlsx,xls'
    //         ]);

    //         //valdiar si esta proyeccion ya fue cargada
    //         $proyeccionExistente = MaterialSolicitud::where('codigo_proyecto', $request->codigo_proyecto)->exists();

    //         if ($proyeccionExistente) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'La proyección ya fue cargada a este proyecto.',
    //             ], 200);
    //         }

    //         $archivo = $request->file('archivo');
    //         $data = Excel::toArray([], $archivo)[0];

    //         if (count($data) < 2) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'El archivo está vacío o no tiene encabezado.',
    //                 'errores' => ['El archivo está vacío o no tiene encabezado.']
    //             ], 200);
    //         }

    //         $encabezado = $data[0];
    //         $registros = array_slice($data, 1);

    //         $columnasEsperadas = [
    //             'codigo',
    //             'descripcion',
    //             'padre',
    //             'um',
    //             'cantidad',
    //             'subcapitulo',
    //             'cant_apu',
    //             'rend',
    //             'iva',
    //             'valor_sin_iva',
    //             'tipo_insumo',
    //             'agrupacion',
    //         ];

    //         $encabezadoNormalizado = array_map(fn($item) => strtolower(trim($item)), $encabezado);
    //         $errores = [];

    //         $datosInsertar = [];

    //         // ✅ VARIABLE PARA AUTO-RELLENAR CÓDIGOS
    //         $ultimoCodigo = '';

    //         foreach ($registros as $i => $fila) {
    //             // Asegurar que la fila tenga al menos 12 columnas
    //             $fila = array_pad($fila, 12, '');
    //             $fila = array_map('trim', $fila);

    //             // ✅ AUTO-RELLENAR CÓDIGOS VACÍOS
    //             $codigo = $fila[0] ?? '';
    //             $descripcion = $fila[1] ?? '';

    //             // Si la fila actual tiene código, actualizamos el último código
    //             if (!empty($codigo)) {
    //                 $ultimoCodigo = $codigo;
    //             }
    //             // Si la fila no tiene código pero tenemos un último código, lo usamos
    //             elseif (!empty($ultimoCodigo) && !empty($descripcion)) {
    //                 $codigo = $ultimoCodigo;
    //                 $fila[0] = $ultimoCodigo; // Actualizar el array también
    //             }

    //             // ✅ FILTRAR SOLO MÓDULO 4 (después del auto-relleno)
    //             $padre = $fila[2] ?? '';

    //             $esModulo4 = (str_starts_with($codigo, '4') || str_starts_with($padre, '4'));

    //             if (!$esModulo4) {
    //                 continue; // Saltar registros que no son del módulo 4
    //             }

    //             // Campos obligatorios
    //             if (empty($descripcion)) {
    //                 $errores[] = "Fila " . ($i + 2) . ": El campo descripcion está vacío";
    //                 continue;
    //             }

    //             // ✅ FUNCIÓN PARA PROCESAR VALORES DECIMALES
    //             $procesarDecimal = function ($valor) {
    //                 if ($valor === null || $valor === '' || $valor === ' ') {
    //                     return 0;
    //                 }
    //                 $valor = str_replace(',', '.', $valor); // Convertir coma a punto decimal
    //                 $numero = floatval($valor);
    //                 return is_numeric($numero) ? $numero : 0;
    //             };

    //             // ✅ FUNCIÓN PARA PROCESAR VALORES ENTEROS
    //             $procesarEntero = function ($valor) {
    //                 if ($valor === null || $valor === '' || $valor === ' ') {
    //                     return 0;
    //                 }
    //                 $entero = intval($valor);
    //                 return is_numeric($entero) ? $entero : 0;
    //             };

    //             // ✅ FUNCIÓN PARA PROCESAR TEXTO
    //             $procesarTexto = function ($valor) {
    //                 return $valor === null || $valor === '' ? '' : trim($valor);
    //             };

    //             $datosInsertar[] = [
    //                 'user_id'               => Auth::id(),
    //                 'codigo_proyecto'       => $request->codigo_proyecto,
    //                 'codigo'                => $procesarTexto($codigo), // Usar el código (posiblemente auto-rellenado)
    //                 'descripcion'           => $procesarTexto($descripcion),
    //                 'padre'                 => $procesarTexto($fila[2]),
    //                 'um'                    => $procesarTexto($fila[3]),
    //                 'cantidad'              => $procesarDecimal($fila[4]),
    //                 'subcapitulo'           => $procesarTexto($fila[5]),
    //                 'cant_apu'              => $procesarDecimal($fila[6]),
    //                 'rend'                  => $procesarDecimal($fila[7]),
    //                 'iva'                   => $procesarEntero($fila[8]),
    //                 'valor_sin_iva'         => $procesarDecimal($fila[9]),
    //                 'tipo_insumo'           => $procesarTexto($fila[10]),
    //                 'agrupacion'            => $procesarTexto($fila[11]),
    //                 'cant_total'            => $procesarDecimal($fila[4]), // Mismo que cantidad
    //                 'cant_restante'         => $procesarDecimal($fila[4]), // Mismo que cantidad inicialmente
    //                 'cant_solicitada'       => 0, // Iniciar en 0
    //                 'estado'                => 1, // Estado inicial
    //                 'created_at'            => now(),
    //                 'updated_at'            => now(),
    //             ];
    //         }

    //         if (!empty($errores)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'Errores detectados en filas del archivo',
    //                 'errores' => $errores
    //             ], 200);
    //         }

    //         // ✅ Validar que hay datos para insertar
    //         if (empty($datosInsertar)) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'No se encontraron registros del módulo 4 en el archivo',
    //                 'errores' => ['El archivo no contiene registros del módulo 4 (OBRA ELÉCTRICA)']
    //             ], 200);
    //         }

    //         // ✅ Insertar en lotes
    //         foreach (array_chunk($datosInsertar, 500) as $lote) {
    //             MaterialSolicitud::insert($lote);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Archivo cargado correctamente.',
    //             'errores' => [],
    //             'data' => $datosInsertar,
    //             'cantidad' => count($datosInsertar),
    //         ], 200);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Error inesperado al procesar el archivo.',
    //             'errores' => [$e->getMessage()]
    //         ], 500);
    //     }
    // }

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

            // Autorrellenar código
            if (!empty($codigo)) {
                $ultimoCodigo = $codigo;
            } elseif (!empty($descripcion) && !empty($ultimoCodigo)) {
                $codigo = $fila[0] = $ultimoCodigo;
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

            $datosInsertar[] = [
                'user_id'         => Auth::id(),
                'codigo_proyecto' => $request->codigo_proyecto,
                'codigo'          => $toText($codigo),
                'descripcion'     => $toText($descripcion),
                'padre'           => $toText($padre),
                'nivel'           => $nivel, // 👈 Nuevo campo
                'um'              => $toText($fila[3]),
                'cantidad'        => $toDecimal($fila[4]),
                'subcapitulo'     => $toText($fila[5]),
                'cant_apu'        => $toDecimal($fila[6]),
                'rend'            => $toDecimal($fila[7]),
                'iva'             => $toInt($fila[8]),
                'valor_sin_iva'   => $toDecimal($fila[9]),
                'tipo_insumo'     => $toText($fila[10]),
                'agrupacion'      => $toText($fila[11]),
                'cant_total'      => $toDecimal($fila[4]),
                'cant_restante'   => $toDecimal($fila[4]),
                'cant_solicitada' => 0,
                'estado'          => 1,
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


    public function modificacionesMaterial(Request $request) {}

    public function solicitudMaterial(Request $request) {}

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


    //envio de proyeccion
    public function proyeccionData($codigo_proyecto)
    {
        $proyeccionData = DB::connection('mysql')
            ->table('materiales')
            ->where('codigo_proyecto', $codigo_proyecto)
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $proyeccionData,
        ]);
    }
}
