<?php

namespace App\Http\Controllers\Api\CargueMasivo;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\ProyectosDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CarguesMasivosCotroller extends Controller
{
    public function cargueEmpleados(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls'
            ]);


            $archivo = $request->file('archivo');
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
                'nombres',
                'apellidos',
                'cedula',
                'telefono',
                'cargo_id',
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

                if (count($fila) < 5) {
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
                    'nombres'         => $fila[0],
                    'apellidos'       => $fila[1],
                    'cedula'          => $fila[2],
                    'telefono'        => $fila[3],
                    'cargo_id'        => $fila[4],
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

            foreach (array_chunk($datosInsertar, 500) as $lote) {
                foreach ($lote as $registro) {
                    Personal::create($registro);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo cargado correctamente.',
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

    public function cargueUpdateProyecto(Request $request)
    {
        DB::beginTransaction();

        info("Entro");
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls'
            ]);

            info("Entro--");

            $archivo = $request->file('archivo');
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
                'user_id',
                'proyecto_id',
                'torre',
                'piso',
                'apartamento',
                'consecutivo',
                'orden_proceso',
                'procesos_proyectos_id',
                'text_validacion',
                'estado',
                'validacion',
                'estado_validacion',
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

                if (count($fila) < 5) {
                    $errores[] = "Fila " . ($i + 2) . ": Incompleta";
                    continue;
                }


                foreach ([1, 2, 3, 4,5,6,7] as $indiceCampoObligatorio) {
                    if (empty($fila[$indiceCampoObligatorio])) {
                        $errores[] = "Fila " . ($i + 2) . ": El campo {$columnasEsperadas[$indiceCampoObligatorio]} está vacío";
                        continue 2;
                    }
                }

                $datosInsertar[] = [
                    'user_id'         => $fila[0],
                    'proyecto_id'     => $fila[1],
                    'torre'           => $fila[2],
                    'piso'            => $fila[3],
                    'apartamento'     => $fila[4],
                    'consecutivo'     => $fila[5],
                    'orden_proceso'  => $fila[6],
                    'procesos_proyectos_id'     => $fila[7],
                    'text_validaicon' => $fila[8],
                    'estado'          => $fila[9],
                    'fecha_habilitado'=>  now(),
                    'validacion'      => $fila[10],
                    'estado_validacion'      => $fila[11],
                    'fecha_fin'      =>  now(),
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

            foreach (array_chunk($datosInsertar, 500) as $lote) {
                foreach ($lote as $registro) {
                    ProyectosDetalle::create($registro);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Archivo cargado correctamente.',
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
}
