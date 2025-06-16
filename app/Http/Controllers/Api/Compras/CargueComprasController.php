<?php

namespace App\Http\Controllers\Api\Compras;

use App\Exports\DatosProveedorExport;
use App\Http\Controllers\Controller;
use App\Models\CargueComprasModel;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;


class CargueComprasController extends Controller
{
    public function index()
    {
        $papeleria = DB::connection('mysql')
            ->table('compras')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $papeleria
        ]);
    }

    public function cargarPlanoPapeleria(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls'
            ]);

            // 1️⃣ Obtener el último prefijo cargado
            $ultimoPrefijo = CargueComprasModel::where('prefijo', 'like', 'comp-%')
                ->orderBy('id', 'desc')
                ->value('prefijo');

            if ($ultimoPrefijo) {
                // Extraer el número del último prefijo
                $numero = (int) str_replace('comp-', '', $ultimoPrefijo);
                $nuevoNumero = $numero + 1;
            } else {
                $nuevoNumero = 1;
            }

            // Crear el nuevo prefijo
            $nuevoPrefijo = 'comp-' . $nuevoNumero;

            $archivo = $request->file('archivo');
            // $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $archivo)[0];
            $data = Excel::toArray([], $archivo)[0];


            if (count($data) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El archivo está vacío o no tiene encabezado.',
                    'errores' => ['El archivo está vacío o no tiene encabezado.']
                ], 200);
            }

            $encabezado = $data[0];
            $registros = array_slice($data, 1);

            $columnasEsperadas = [
                'codigo_insumo',
                'insumo_descripcion',
                'unidad',
                'mat_requerido',
                'agrupacion_descripcion',
                'nombre_tercero',
            ];

            $encabezadoNormalizado = array_map(fn($item) => strtolower(trim($item)), $encabezado);
            $errores = [];

            if ($encabezadoNormalizado !== $columnasEsperadas) {
                $faltantes = array_diff($columnasEsperadas, $encabezadoNormalizado);
                $adicionales = array_diff($encabezadoNormalizado, $columnasEsperadas);

                if (count($faltantes)) {
                    $errores[] = "Faltan las siguientes columnas: " . implode(', ', $faltantes);
                }

                if (count($adicionales)) {
                    $errores[] = "Existen columnas adicionales no esperadas: " . implode(', ', $adicionales);
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'Errores en columnas del archivo',
                    'errores' => $errores
                ], 200);
            }

            $datosInsertar = [];

            foreach ($registros as $i => $fila) {
                $fila = array_map('trim', $fila);

                if (count($fila) < 6) {
                    $errores[] = "Fila " . ($i + 2) . ": Incompleta";
                    continue;
                }


                foreach ([0, 1, 2, 3, 4] as $indiceCampoObligatorio) {
                    if (empty($fila[$indiceCampoObligatorio])) {
                        $errores[] = "Fila " . ($i + 2) . ": El campo {$columnasEsperadas[$indiceCampoObligatorio]} está vacío";
                        continue 2;
                    }
                }

                $datosInsertar[] = [
                    'codigo_insumo'         => $fila[0],
                    'insumo_descripcion'    => $fila[1],
                    'unidad'                => $fila[2],
                    'mat_requerido'         => $fila[3],
                    'agrupacion_descripcion' => $fila[4],
                    'nombre_tercero'        => $fila[5],
                    'prefijo'               => $nuevoPrefijo, // 👈 Aquí se asigna el nuevo prefijo
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
            }

            if (!empty($errores)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Errores detectados en filas del archivo',
                    'errores' => $errores
                ], 200);
            }

            foreach (array_chunk($datosInsertar, 500) as $lote) {
                foreach ($lote as $registro) {
                    CargueComprasModel::create($registro);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo cargado correctamente.',
                'prefijo' => $nuevoPrefijo, // Puedes retornar el prefijo generado
                'errores' => [],
                'data' => $datosInsertar,
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

    public function EnvioCotizacion(Request $request)
    {
        // 1. Traer los datos filtrados por prefijo
        $datosCotizacion = CargueComprasModel::where('prefijo', $request->prefijo)->get();

        // 2. Agrupar los datos por proveedor
        $proveedores = $datosCotizacion->groupBy('nombre_tercero');

        // 3. Recorrer cada proveedor
        foreach ($proveedores as $proveedor => $datos) {
            // Buscar el correo del proveedor
            $infoProveedor = Proveedor::where('nombre', $proveedor)->where('estado',1)->first();

            // 4. Solo enviar si tiene correo registrado
            if ($infoProveedor && !empty($infoProveedor->correo)) {
                // Exportar Excel solo con los datos de ese proveedor
                $excel = Excel::raw(new DatosProveedorExport($datos), \Maatwebsite\Excel\Excel::XLSX);

                // Enviar correo con el Excel adjunto
                Mail::raw("Estimado proveedor $proveedor, adjunto encontrará la solicitud de cotización correspondiente.", function ($message) use ($infoProveedor, $excel, $proveedor) {
                    $message->to($infoProveedor->correo)
                        ->subject('Cotización para ' . $proveedor)
                        ->attachData($excel, 'cotizacion_' . $proveedor . '.xlsx', [
                            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                });
            }
        }

        return response()->json(['message' => 'Cotizaciones enviadas correctamente.']);
    }

    public function plantilla()
    {
        $filePath = storage_path("app/public/template/plantilla_compras.xlsx");

        if (file_exists($filePath)) {
            return response()->download($filePath, 'plantilla_compras.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } else {
            return response()->json(['error' => 'Archivo no encontrado.'], 404);
        }
    }
}
