<?php

namespace App\Http\Controllers\Api\Documentos;

use App\Http\Controllers\Controller;
use App\Models\Documentos;
use App\Models\ProyectoCasa;
use App\Models\Proyectos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentosController extends Controller
{

    //CONSULTA DOCUMENTACION EMCALI
    public function indexEmcali()
    {
        // Obtener proyectos de apartamentos con su documentación del operador 1
        $proyectosApartamentos = Proyectos::with(['documentacion' => function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador')
                ->where('operador', 1) // Filtro por operador 1 (EMCALI)
                ->distinct();
        }])->whereHas('documentacion', function ($query) {
            $query->where('operador', 1); // Filtro en whereHas también
        })->get();

        // Obtener proyectos de casas con su documentación del operador 1
        $proyectosCasas = ProyectoCasa::with(['documentacion' => function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador')
                ->where('operador', 1) // Filtro por operador 1 (EMCALI)
                ->distinct();
        }])->whereHas('documentacion', function ($query) {
            $query->where('operador', 1); // Filtro en whereHas también
        })->get();

        // Combinar ambos resultados
        $data = $proyectosApartamentos->merge($proyectosCasas);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    //CONSULTA DOCUMENTACION CELSIA
    public function indexCelsia()
    {
        // Obtener proyectos de apartamentos con su documentación del operador 1
        $proyectosApartamentos = Proyectos::with(['documentacion' => function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador')
                ->where('operador', 2) // Filtro por operador 1 (CELSIA)
                ->distinct();
        }])->whereHas('documentacion', function ($query) {
            $query->where('operador', 2); // Filtro en whereHas también
        })->get();

        // Obtener proyectos de casas con su documentación del operador 1
        $proyectosCasas = ProyectoCasa::with(['documentacion' => function ($query) {
            $query->select('codigo_proyecto', 'codigo_documento', 'etapa', 'operador')
                ->where('operador', 2) // Filtro por operador 2 (CELSIA)
                ->distinct();
        }])->whereHas('documentacion', function ($query) {
            $query->where('operador', 2); // Filtro en whereHas también
        })->get();

        // Combinar ambos resultados
        $data = $proyectosApartamentos->merge($proyectosCasas);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    public function detalleDocumentos($codigo_proyecto)
    {
        $data = Documentos::with('actividad') // Asegúrate de tener esta relación
            ->where('codigo_proyecto', $codigo_proyecto)
            ->orderBy('orden')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    //confirmar documento
    // public function confirmarDocumento(Request $request)
    // {
    //     try {
    //         // Validar los datos recibidos
    //         $request->validate([
    //             'id' => 'required|exists:documentacion_operadores,id',
    //             'codigo_proyecto' => 'required|string',
    //             'codigo_documento' => 'required|string',
    //             'etapa' => 'required|integer',
    //             'actividad_id' => 'required|integer',
    //             'observacion' => 'required|string',
    //             'archivo' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
    //         ]);

    //         // Guardar el archivo
    //         if ($request->hasFile('archivo')) {
    //             $archivo = $request->file('archivo');

    //             // Generar nombre del archivo según el formato requerido
    //             $nombreArchivo = $request->codigo_proyecto . '-' .
    //                 $request->codigo_documento . '-' .
    //                 $request->etapa . '-' .
    //                 $request->actividad_id . '.' .
    //                 $archivo->getClientOriginalExtension();

    //             // Guardar en la ruta especificada
    //             $ruta = $archivo->storeAs(
    //                 'public/documentacion/red',
    //                 $nombreArchivo
    //             );

    //             $rutaArchivo = 'storage/documentacion/red/' . $nombreArchivo;
    //         }

    //         // 1. Actualizar la actividad actual a estado 2 (Completado)
    //         $actividadActual = Documentos::find($request->id);
    //         $actividadActual->update([
    //             'estado' => 2, // Completado
    //             'observacion' => $request->observacion,
    //             'fecha_confirmacion' => now(),
    //             'usaurio_id' => Auth::id(),
    //         ]);

    //         // 2. Verificar si la actividad actual es simultánea
    //         if ($actividadActual->tipo === 'simultanea') {
    //             // Obtener todas las actividades simultáneas del mismo grupo
    //             $actividadesSimultaneas = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
    //                 ->where('codigo_documento', $request->codigo_documento)
    //                 ->where('etapa', $request->etapa)
    //                 ->where('actividad_depende_id', $actividadActual->actividad_depende_id)
    //                 ->where('tipo', 'simultanea')
    //                 ->get();

    //             // Verificar si TODAS las actividades simultáneas están completas
    //             $todasCompletas = $actividadesSimultaneas->every(function ($actividad) {
    //                 return $actividad->estado == 2; // Todas deben estar en estado 2 (Completado)
    //             });

    //             if ($todasCompletas) {
    //                 // Buscar la siguiente actividad PRINCIPAL después del grupo simultáneo
    //                 $siguienteActividad = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
    //                     ->where('codigo_documento', $request->codigo_documento)
    //                     ->where('etapa', $request->etapa)
    //                     ->where('orden', '>', $actividadesSimultaneas->max('orden')) // Mayor orden del grupo simultáneo
    //                     ->where('tipo', 'principal') // Solo actividades principales
    //                     ->orderBy('orden')
    //                     ->first();

    //                 if ($siguienteActividad) {
    //                     $siguienteActividad->update([
    //                         'estado' => 1, // Disponible
    //                         'fecha_actual' => now(),
    //                     ]);
    //                 } else {
    //                     info("No hay siguiente actividad principal después del grupo simultáneo.");
    //                 }
    //             } else {
    //                 info("Grupo simultáneo incompleto. Actividades pendientes: " .
    //                     $actividadesSimultaneas->where('estado', '!=', 2)->count());
    //             }
    //         } else {
    //             // Si es actividad PRINCIPAL, habilitar la siguiente actividad normalmente
    //             $siguienteActividad = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
    //                 ->where('codigo_documento', $request->codigo_documento)
    //                 ->where('etapa', $request->etapa)
    //                 ->where('orden', $actividadActual->orden + 1) // Siguiente orden
    //                 ->first();

    //             if ($siguienteActividad) {
    //                 $siguienteActividad->update([
    //                     'estado' => 1, // Disponible
    //                     'fecha_actual' => now(),
    //                 ]);


    //                 // Si la siguiente actividad es simultánea, habilitar todas las del grupo
    //                 if ($siguienteActividad->tipo === 'simultanea') {
    //                     $actividadesSimultaneas = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
    //                         ->where('codigo_documento', $request->codigo_documento)
    //                         ->where('etapa', $request->etapa)
    //                         ->where('actividad_depende_id', $siguienteActividad->actividad_depende_id)
    //                         ->where('tipo', 'simultanea')
    //                         ->get();

    //                     foreach ($actividadesSimultaneas as $actividadSimultanea) {
    //                         if ($actividadSimultanea->id != $siguienteActividad->id) { // No actualizar la que ya se actualizó
    //                             $actividadSimultanea->update([
    //                                 'estado' => 1, // Disponible
    //                                 'fecha_actual' => now(),
    //                             ]);
    //                             info("Actividad simultánea habilitada: ID {$actividadSimultanea->id}, Orden {$actividadSimultanea->orden}");
    //                         }
    //                     }
    //                 }
    //             } else {
    //                 info("No hay siguiente actividad para habilitar. Última actividad del proceso.");
    //             }
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Actividad confirmada exitosamente',
    //             'data' => [
    //                 'actual' => $actividadActual,
    //                 'siguiente' => $siguienteActividad ?? null
    //             ]
    //         ]);
    //     } catch (\Exception $e) {
    //         info("Error al confirmar documento: " . $e->getMessage());

    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

public function confirmarDocumento(Request $request)
{
    try {
        // Validar los datos recibidos
        $request->validate([
            'id' => 'required|exists:documentacion_operadores,id',
            'codigo_proyecto' => 'required|string',
            'codigo_documento' => 'required|string',
            'etapa' => 'required|integer',
            'actividad_id' => 'required|integer',
            'observacion' => 'required|string',
            'archivo' => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        // Guardar el archivo
        if ($request->hasFile('archivo')) {
            $archivo = $request->file('archivo');

            // Generar nombre del archivo según el formato requerido
            $nombreArchivo = $request->codigo_proyecto . '-' .
                $request->codigo_documento . '-' .
                $request->etapa . '-' .
                $request->actividad_id . '.' .
                $archivo->getClientOriginalExtension();

            // Guardar en la ruta especificada
            $ruta = $archivo->storeAs(
                'public/documentacion/red',
                $nombreArchivo
            );

            $rutaArchivo = 'storage/documentacion/red/' . $nombreArchivo;
        }

        // 1. Obtener la actividad actual y calcular el retraso
        $actividadActual = Documentos::find($request->id);
        
        // Calcular días de retraso (diferencia entre fecha actual y fecha_proyeccion)
        $fechaProyeccion = \Carbon\Carbon::parse($actividadActual->fecha_proyeccion);
        $fechaHoy = now();
        $diasRetraso = $fechaProyeccion->diffInDays($fechaHoy, false); // false para diferencia negativa si está adelantada
        
        // Determinar si hay días vencidos (solo cuando la fecha actual es después de la proyección)
        $hayDiasVencidos = $diasRetraso > 0;
        $diasAAgregar = $hayDiasVencidos ? $diasRetraso : 0;
        
        info("Días de retraso calculados: {$diasRetraso}, días vencidos: {$diasAAgregar}");

        // 2. Actualizar la actividad actual a estado 2 (Completado)
        $actividadActual->update([
            'estado' => 2, // Completado
            'observacion' => $request->observacion,
            'fecha_confirmacion' => now(),
            'fecha_actual' => now(), // Actualizar fecha_actual a la fecha real de confirmación
            'usuario_id' => Auth::id(),
        ]);

        // 3. Actualizar fechas actuales de las actividades siguientes SOLO CUANDO HAY DÍAS VENCIDOS
        if ($hayDiasVencidos) {
            $this->actualizarFechasSiguientes(
                $request->codigo_proyecto,
                $request->codigo_documento,
                $request->etapa,
                $actividadActual->orden,
                $diasAAgregar
            );
        }

        // 4. Verificar si la actividad actual es simultánea
        if ($actividadActual->tipo === 'simultanea') {
            // Obtener todas las actividades simultáneas del mismo grupo
            $actividadesSimultaneas = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
                ->where('codigo_documento', $request->codigo_documento)
                ->where('etapa', $request->etapa)
                ->where('actividad_depende_id', $actividadActual->actividad_depende_id)
                ->where('tipo', 'simultanea')
                ->get();

            // Verificar si TODAS las actividades simultáneas están completas
            $todasCompletas = $actividadesSimultaneas->every(function ($actividad) {
                return $actividad->estado == 2; // Todas deben estar en estado 2 (Completado)
            });

            if ($todasCompletas) {
                // Buscar la siguiente actividad PRINCIPAL después del grupo simultáneo
                $siguienteActividad = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
                    ->where('codigo_documento', $request->codigo_documento)
                    ->where('etapa', $request->etapa)
                    ->where('orden', '>', $actividadesSimultaneas->max('orden')) // Mayor orden del grupo simultáneo
                    ->where('tipo', 'principal') // Solo actividades principales
                    ->orderBy('orden')
                    ->first();

                if ($siguienteActividad) {
                    $siguienteActividad->update([
                        'estado' => 1, // Disponible
                        // 'fecha_actual' => now(), // Esta fecha siempre se actualiza al habilitar la siguiente actividad
                    ]);
                } else {
                    info("No hay siguiente actividad principal después del grupo simultáneo.");
                }
            } else {
                info("Grupo simultáneo incompleto. Actividades pendientes: " .
                    $actividadesSimultaneas->where('estado', '!=', 2)->count());
            }
        } else {
            // Si es actividad PRINCIPAL, habilitar la siguiente actividad normalmente
            $siguienteActividad = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
                ->where('codigo_documento', $request->codigo_documento)
                ->where('etapa', $request->etapa)
                ->where('orden', $actividadActual->orden + 1) // Siguiente orden
                ->first();

            if ($siguienteActividad) {
                $siguienteActividad->update([
                    'estado' => 1, // Disponible
                    'fecha_actual' => now(), // Esta fecha siempre se actualiza al habilitar la siguiente actividad
                ]);

                // Si la siguiente actividad es simultánea, habilitar todas las del grupo
                if ($siguienteActividad->tipo === 'simultanea') {
                    $actividadesSimultaneas = Documentos::where('codigo_proyecto', $request->codigo_proyecto)
                        ->where('codigo_documento', $request->codigo_documento)
                        ->where('etapa', $request->etapa)
                        ->where('actividad_depende_id', $siguienteActividad->actividad_depende_id)
                        ->where('tipo', 'simultanea')
                        ->get();

                    foreach ($actividadesSimultaneas as $actividadSimultanea) {
                        if ($actividadSimultanea->id != $siguienteActividad->id) {
                            $actividadSimultanea->update([
                                'estado' => 1, // Disponible
                                'fecha_actual' => now(), // Esta fecha siempre se actualiza al habilitar actividades simultáneas
                            ]);
                        }
                    }
                }
            } else {
                info("No hay siguiente actividad para habilitar. Última actividad del proceso.");
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Actividad confirmada exitosamente' . ($hayDiasVencidos ? " con {$diasAAgregar} días de ajuste en fechas" : ""),
            'data' => [
                'actual' => $actividadActual,
                'siguiente' => $siguienteActividad ?? null,
                'dias_retraso' => $diasRetraso,
                'dias_vencidos' => $diasAAgregar,
                'ajuste_aplicado' => $hayDiasVencidos
            ]
        ]);

    } catch (\Exception $e) {
        info("Error al confirmar documento: " . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

// Función para actualizar fechas de actividades siguientes
private function actualizarFechasSiguientes($codigo_proyecto, $codigo_documento, $etapa, $ordenActual, $diasAAgregar)
{
    // Obtener todas las actividades siguientes
    $actividadesSiguientes = Documentos::where('codigo_proyecto', $codigo_proyecto)
        ->where('codigo_documento', $codigo_documento)
        ->where('etapa', $etapa)
        ->where('orden', '>', $ordenActual)
        ->where('estado', '!=', 2) // Solo las que no están completadas
        ->orderBy('orden')
        ->get();

    foreach ($actividadesSiguientes as $actividad) {
        // Calcular nueva fecha_actual sumando los días de retraso
        $nuevaFechaActual = \Carbon\Carbon::parse($actividad->fecha_actual)
            ->addDays($diasAAgregar)
            ->format('Y-m-d');

        $actividad->update([
            'fecha_actual' => $nuevaFechaActual,
        ]);

        info("Fecha actualizada para actividad ID {$actividad->id}: {$actividad->fecha_actual} -> {$nuevaFechaActual} (+{$diasAAgregar} días)");
    }

    info("Total de actividades actualizadas: " . $actividadesSiguientes->count());
}
}
