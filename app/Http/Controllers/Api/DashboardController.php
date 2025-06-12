<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdenCompraCabecera;
use App\Models\PendienteDetalle;
use App\Models\ProductoLote;
use App\Models\TrasladoSalidaCabecera;
use App\Models\UsersDocumentos;
use App\Models\UsersPerfiles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use stdClass;

class DashboardController extends Controller
{
    public function getStatistics(Request $request)
    {
        try {
            $bodega = $request->bodega;
            $empresa_id = $request->empresa;
            // Consulta de cantidad de lotes que se vencen en un tiempo menor o igual a 6 meses
            $now = Carbon::now()->format('d/m/Y');
            $sixMonths = Carbon::createFromFormat('d/m/Y', $now)->addMonths(6);
            $productos = collect(ProductoLote::select('id')->where('fecha_vencimiento', '<=', $sixMonths)
                ->where('stock', '>', 0)
                ->where('bodega_id', $bodega)
                ->get())->count();

            // Consulta de TRASLADOS pendientes por aceptar en la bodega
            $traslados_origen = collect(TrasladoSalidaCabecera::select('id')->where('estado', 1)
                ->where('bod_destino', $bodega)
                ->get())->count();

            // Consulta de TRASLADOS pendientes que me acepten
            $traslados_destino = collect(TrasladoSalidaCabecera::select('id')->where('estado', 1)
                ->where('bod_origen', $bodega)
                ->get())->count();

            // Consulta de ORDENES DE COMPRA pendientes por completar ingreso de FACTURAS DE PROVEEDOR
            $ordenes_compra = collect(OrdenCompraCabecera::select('id')->where('estado', 1)
                ->where('bodega_id', $bodega)
                ->get())->count();

            // Consulta de PENDIENTES DE DISPENSACION
            $dis_pendientes = collect(PendienteDetalle::select('pendientes_id')->whereHas('pendientes', function (Builder $query) use ($bodega) {
                $query->where('bodega_id', $bodega)->where('estado', 1);
            })->whereRaw('cantidad_pagada < cantidad_saldo')->get())->groupBy('pendientes_id')->values()->count();

            $permisos = new stdClass();
            $user_id = Auth::user()->id;
            $permisos->vencimientos = UsersPerfiles::whereHas('perfil', function (Builder $perfil) use ($empresa_id) {
                $perfil->whereHas('empresa', function (Builder $empresa) use ($empresa_id) {
                    $empresa->where('id', $empresa_id);
                })->whereHas('modulos.menu', function (Builder $modulo) {
                    $modulo->where('menu.link_menu', 'vencimientos');
                });
            })->where('id_user', $user_id)->first() ? true : false;

            $permisos->orden_compra = UsersDocumentos::whereHas('documentoInfo', function (Builder $documento) {
                $documento->where('codigo', 'OC');
            })->where([
                ['id_user', '=', $user_id],
                ['id_empresa', '=', $empresa_id],
                ['consultar', '=', '1']
            ])->first() ? true : false;

            $permisos->traslado_salida = UsersDocumentos::whereHas('documentoInfo', function (Builder $documento) {
                $documento->where('codigo', 'TRS');
            })->where([
                ['id_user', '=', $user_id],
                ['id_empresa', '=', $empresa_id],
                ['consultar', '=', '1']
            ])->first() ? true : false;

            $permisos->traslado_pendiente = UsersDocumentos::whereHas('documentoInfo', function (Builder $documento) {
                $documento->where('codigo', 'TRP');
            })->where([
                ['id_user', '=', $user_id],
                ['id_empresa', '=', $empresa_id],
                ['consultar', '=', '1']
            ])->first() ? true : false;

            $permisos->pendientes = UsersDocumentos::whereHas('documentoInfo', function (Builder $documento) {
                $documento->where('codigo', 'PEN');
            })->where([
                ['id_user', '=', $user_id],
                ['id_empresa', '=', $empresa_id],
                ['consultar', '=', '1']
            ])->first() ? true : false;

            return response()->json([
                'status' => 'success',
                'data' => compact('productos', 'traslados_origen', 'traslados_destino', 'ordenes_compra', 'dis_pendientes', 'permisos'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
