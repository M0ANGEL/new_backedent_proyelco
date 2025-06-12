<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use App\Models\HorarioDetalle;
use App\Models\LogHorarios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HorariosController extends Controller
{

    public function index()
    {
        $perfileshorario = DB::connection('mysql')
            ->table('horarios')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $perfileshorario,
        ]);
    }

    public function store(Request $request)
    {
        // Validación de los datos
        $validatedData = $request->validate([
            'perfil_id' => 'required',
            'horarios' => 'required|array',
            'horarios.*.dia' => 'required|string|max:10',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fin' => 'required|date_format:H:i',
        ]);

        // Validar que cada hora_fin sea después de hora_inicio
        foreach ($validatedData['horarios'] as $horario) {
            if ($horario['hora_fin'] <= $horario['hora_inicio']) {
                return response()->json(['message' => "La hora de fin debe ser después de la hora de inicio en {$horario['dia']}"], 400);
            }
        }

        // Verificar si el perfil ya existe
        $horarioNombre = DB::connection('mysql')
            ->table('horarios')
            ->where('nombre_perfil', $validatedData['perfil_id'])
            ->exists();

        if ($horarioNombre) {
            return response()->json(['message' => 'Este horario ya existe'], 400);
        }


        // Crear el horario
        $idPerfil = Horario::create([
            'nombre_perfil' => $validatedData['perfil_id'],
            'user_id' => Auth::id(),
        ]);

        // Lista de los 7 días de la semana
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

        foreach ($diasSemana as $dia) {
            // Buscar si el día ya fue enviado en la solicitud
            $horario = collect($validatedData['horarios'])->firstWhere('dia', $dia);

            if ($horario) {
                // Si el día fue enviado, se guarda con los datos originales
                HorarioDetalle::create([
                    'horario_id' => $idPerfil->id,
                    'dia' => $horario['dia'],
                    'hora_inicio' => $horario['hora_inicio'],
                    'hora_final' => $horario['hora_fin'],
                    'estado' => 1, // Activo
                    'user_id' => Auth::id(),
                ]);
            } else {
                // Si el día no fue enviado, se crea con estado = 0
                HorarioDetalle::create([
                    'horario_id' => $idPerfil->id,
                    'dia' => $dia,
                    'hora_inicio' => '00:00', // Puede ser NULL o un valor predeterminado
                    'hora_final' => '00:00',
                    'estado' => 0, // Inactivo
                    'user_id' => Auth::id(),
                ]);
            }
        }

        return response()->json(['message' => 'Horarios guardados correctamente'], 201);
    }


    public function crearPerfil(Request $request)
    {
        $request->validate([
            'nombre_perfil' => 'required|string|max:255',
        ]);

        Horario::create([ //horario
            'nombre_perfil' => $request->nombre_perfil,
            'user_id' => Auth::id(), // Usuario autenticado
        ]);

        return response()->json(['message' => 'Perfil creado con éxito'], 200);
    }


    public function getHorarios()
    {
        $perfileshorario = DB::connection('mysql')
            ->table('horarios')
            ->where('estado', "1")
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $perfileshorario,
        ]);
    }


    public function show($id)
    {
        $datahoras = DB::connection('mysql')
            ->table('horarios_detalle')
            ->join('horarios', 'horarios.id', '=', 'horarios_detalle.horario_id')
            ->select(
                'horarios_detalle.dia',
                'horarios_detalle.estado',
                'horarios_detalle.hora_inicio',
                'horarios_detalle.hora_final',
                'horarios_detalle.horario_id',
                'horarios.nombre_perfil'
            )
            ->where('horario_id', $id)
            ->get();


        return response()->json([
            'status' => 'success',
            'data' => $datahoras,
            'perfil' => $datahoras->first()->horario_id ?? null,
            'perfil_nombre' => $datahoras->first()->nombre_perfil ?? null,
        ]);
    }

    // public function update(Request $request, $id)
    // {
    //     $horario = HorarioDetalle::where('horario_id',$request->idPerfil);

    //     // Verificar si el perfil ya existe
    //     $horarioNombre = DB::connection('mysql')
    //         ->table('horarios')
    //         ->where('nombre_perfil', $request->perfil_id)
    //         ->where('id', '!=', $id) // Excluye el horario actual
    //         ->exists();


    //     if ($horarioNombre) {
    //         return response()->json(['message' => 'Este horario ya existe'], 400);
    //     }


    //     //actualizar el nombre de hroario de la tala horario

    //     $horarioTabla = Horario::find($request->idPerfil);

    //     $horarioTabla->update([
    //         'nombre_perfil' => $request->perfil_id,
    //     ]);


    //     if (!$horario) {
    //         return response()->json(['message' => 'Horario no encontrado'], 404);
    //     }

    //     // Convertir las fechas al formato H:i antes de validarlas
    //     $horarios = collect($request->horarios)->map(function ($horario) {
    //         return [
    //             'dia' => $horario['dia'],
    //             'hora_inicio' => date('H:i', strtotime($horario['hora_inicio'])),
    //             'hora_fin' => date('H:i', strtotime($horario['hora_fin'])),
    //         ];
    //     })->toArray();

    //     // Validar manualmente si hora_fin es mayor que hora_inicio, excepto si ambas son "00:00"
    //     foreach ($horarios as $index => $h) {
    //         if (!($h['hora_inicio'] === "00:00" && $h['hora_fin'] === "00:00")) {
    //             if ($h['hora_fin'] <= $h['hora_inicio']) {
    //                 return response()->json([
    //                     'message' => "Error en el día {$h['dia']}: La hora de fin debe ser mayor que la hora de inicio."
    //                 ], 422);
    //             }
    //         }
    //     }

    //     // Validar los datos ya convertidos
    //     $validatedData = validator(['horarios' => $horarios], [
    //         'horarios' => 'required|array',
    //         'horarios.*.dia' => 'required|string|max:10',
    //         'horarios.*.hora_inicio' => 'required|date_format:H:i',
    //         'horarios.*.hora_fin' => 'required|date_format:H:i',
    //     ])->validate();

    //     // Lista de los días de la semana
    //     $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    //     $diasRecibidos = array_column($validatedData['horarios'], 'dia');

    //     foreach ($diasSemana as $dia) {
    //         $horarioExistente = HorarioDetalle::where('horario_id', $id)->where('dia', $dia)->first();

    //         if (in_array($dia, $diasRecibidos)) {
    //             $horarioActualizado = collect($validatedData['horarios'])->firstWhere('dia', $dia);

    //             if ($horarioExistente) {
    //                 if ($horarioActualizado['hora_inicio'] === "00:00" && $horarioActualizado['hora_fin'] === "00:00") {
    //                     // Si ambas horas son "00:00", poner las horas en "00:00" y el estado en 0
    //                     $horarioExistente->update([
    //                         'hora_inicio' => "00:00",
    //                         'hora_final' => "00:00",
    //                         'estado' => 0
    //                     ]);
    //                 } else {

    //                     // Guardar el log de cambios
    //                     LogHorarios::create([
    //                         'user_id' => Auth::id(),
    //                         'hora_anterior' => $horarioExistente['hora_inicio']. ' - ' . $horarioExistente['hora_fin'],
    //                         'hora_nueva' => $horarioActualizado['hora_inicio'] . ' - ' . $horarioActualizado['hora_fin'],
    //                         'estado' => 1,
    //                         'horario_id' => $request->idPerfil,

    //                     ]);

    //                     // Si no, actualizar normalmente
    //                     $horarioExistente->update([
    //                         'hora_inicio' => $horarioActualizado['hora_inicio'],
    //                         'hora_final' => $horarioActualizado['hora_fin'],
    //                         'estado' => 1,
    //                     ]);

    //                 }
    //             } else {
    //                 // Crear nuevo horario solo si no es "00:00"
    //                 if (!($horarioActualizado['hora_inicio'] === "00:00" && $horarioActualizado['hora_fin'] === "00:00")) {
    //                     HorarioDetalle::create([
    //                         'horario_id' => $id,
    //                         'dia' => $horarioActualizado['dia'],
    //                         'hora_inicio' => $horarioActualizado['hora_inicio'],
    //                         'hora_final' => $horarioActualizado['hora_fin'],
    //                         'estado' => 1,
    //                         'user_id' => Auth::id(),
    //                     ]);
    //                 }
    //             }
    //         } else {
    //             if ($horarioExistente) {
    //                 $horarioExistente->update([
    //                     'hora_inicio' => "00:00",
    //                     'hora_final' => "00:00",
    //                     'estado' => 0
    //                 ]);
    //             }
    //         }
    //     }

    //     return response()->json(['message' => 'El horario ha sido actualizado correctamente'], 200);
    // }

    public function update(Request $request, $id)
    {
        // Validaciones iniciales...
        if (!$request->has('perfil_id') || !$request->has('idPerfil')) {
            return response()->json(['message' => 'Datos incompletos para la actualización'], 400);
        }

        // Verificar si el perfil ya existe...
        $horarioNombre = DB::connection('mysql')
            ->table('horarios')
            ->where('nombre_perfil', $request->perfil_id)
            ->where('id', '!=', $id)
            ->exists();

        if ($horarioNombre) {
            return response()->json(['message' => 'Este horario ya existe'], 400);
        }

        // Actualizar nombre del horario...
        $horarioTabla = Horario::find($id);
        if (!$horarioTabla) {
            return response()->json(['message' => 'Horario no encontrado'], 404);
        }
        $horarioTabla->update(['nombre_perfil' => $request->perfil_id]);

        // Validar horarios...
        if (!$request->has('horarios') || !is_array($request->horarios)) {
            return response()->json(['message' => 'Los datos de horarios son requeridos'], 400);
        }

        // Procesar horarios...
        $horarios = collect($request->horarios)->map(function ($horario) {
            return [
                'dia' => $horario['dia'],
                'hora_inicio' => date('H:i', strtotime($horario['hora_inicio'])),
                'hora_fin' => date('H:i', strtotime($horario['hora_fin'])),
            ];
        })->toArray();

        // Validaciones de horarios...
        foreach ($horarios as $h) {
            if (!($h['hora_inicio'] === "00:00" && $h['hora_fin'] === "00:00") && $h['hora_fin'] <= $h['hora_inicio']) {
                return response()->json([
                    'message' => "Error en el día {$h['dia']}: La hora de fin debe ser mayor que la hora de inicio."
                ], 422);
            }
        }

        $validatedData = validator(['horarios' => $horarios], [
            'horarios' => 'required|array',
            'horarios.*.dia' => 'required|string|max:10',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fin' => 'required|date_format:H:i',
        ])->validate();

        // Procesar días de la semana...
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $diasRecibidos = array_column($validatedData['horarios'], 'dia');

        foreach ($diasSemana as $dia) {
            $horarioExistente = HorarioDetalle::where('horario_id', $id)->where('dia', $dia)->first();

            if (in_array($dia, $diasRecibidos)) {
                $horarioActualizado = collect($validatedData['horarios'])->firstWhere('dia', $dia);

                if ($horarioExistente) {
                    if ($horarioActualizado['hora_inicio'] === "00:00" && $horarioActualizado['hora_fin'] === "00:00") {
                        $horarioExistente->update([
                            'hora_inicio' => "00:00",
                            'hora_final' => "00:00",
                            'estado' => 0
                        ]);
                    } else {
                        // REGISTRO EN LOG CON FORMATO HH:mm
                        LogHorarios::create([
                            'user_id' => Auth::id(),
                            'hora_anterior' => date('H:i', strtotime($horarioExistente->hora_inicio)) . ' - ' .
                                date('H:i', strtotime($horarioExistente->hora_final)),
                            'hora_nueva' => $horarioActualizado['hora_inicio'] . ' - ' . $horarioActualizado['hora_fin'],
                            'horario_id' => $id,
                            'dia' => $dia,
                        ]);

                        $horarioExistente->update([
                            'hora_inicio' => $horarioActualizado['hora_inicio'],
                            'hora_final' => $horarioActualizado['hora_fin'],
                            'estado' => 1,
                        ]);
                    }
                } else {
                    if (!($horarioActualizado['hora_inicio'] === "00:00" && $horarioActualizado['hora_fin'] === "00:00")) {
                        HorarioDetalle::create([
                            'horario_id' => $id,
                            'dia' => $dia,
                            'hora_inicio' => $horarioActualizado['hora_inicio'],
                            'hora_final' => $horarioActualizado['hora_fin'],
                            'estado' => 1,
                            'user_id' => Auth::id(),
                        ]);
                    }
                }
            } else {
                if ($horarioExistente) {
                    $horarioExistente->update([
                        'hora_inicio' => "00:00",
                        'hora_final' => "00:00",
                        'estado' => 0
                    ]);
                }
            }
        }

        return response()->json(['message' => 'El horario ha sido actualizado correctamente'], 200);
    }

    public function destroy($id)
    {

        $horarioDetalle = Horario::find($id);

        $perfileshorario = DB::connection('mysql')
            ->table('users')
            ->where('horario_id', $id)
            ->get();

        if ($perfileshorario->count() > 0) {

            return response()->json(['message' => 'No se puede inactivar el horario porque está asociado a usuarios'], 500);
        }

        $horarioDetalle->estado = !$horarioDetalle->estado;
        $horarioDetalle->update();
    }
}
