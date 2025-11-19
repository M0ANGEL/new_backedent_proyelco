<?php

namespace App\Http\Controllers\Api\Materiales;

use App\Http\Controllers\Controller;
use App\Models\MaterialSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

    public function generarExcelAxuiliarMaterial(Request $request)
    {
        // Extraer los IDs del array items
        $ids = collect($request->items)->pluck('id')->toArray();

        // Se busca el proyecto con esa proyección usando los IDs recibidos
        $proyeccionData = DB::connection('mysql')
            ->table('materiales')
            ->where('codigo_proyecto', $request->codigo_proyecto)
            ->whereIn('id', $ids)
            ->orderBy('nivel')
            ->get();

        // Convertir a array para manipular más fácilmente
        $dataArray = $proyeccionData->toArray();

        // Para cada item de nivel 2, buscar y agregar sus niveles 3
        foreach ($dataArray as &$item) {
            if ($item->nivel == 2) {
                $item->niveles3 = DB::connection('mysql')
                    ->table('materiales')
                    ->where('codigo_proyecto', $request->codigo_proyecto)
                    ->where('nivel', 3)
                    ->where('codigo', $item->codigo)
                    ->where('padre', $item->descripcion)
                    ->get()
                    ->toArray();
            } else {
                $item->niveles3 = []; // Array vacío para niveles que no son 2
            }
        }


        return response()->json([
            'status' => 'success',
            'data' => $dataArray
        ]);
    }
}
