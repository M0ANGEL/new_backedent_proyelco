<?php

namespace App\Models;

use App\Http\Controllers\Api\NotaCreditoRvdCabeceraController;
use App\Models\Facturacion\FveDisCabecera;
use App\Models\Facturacion\FveRvdCabecera;
use App\Models\Facturacion\ResolucionFacturacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Convenio extends Model
{
    use HasFactory;

    protected $table = 'convenios';

    protected $fillable = [
        'id',
        'id_tipo_conv',
        'estado',
        'nit',
        'descripcion',
        'valor_total',
        'reg_conv',
        'fec_ini',
        'fec_fin',
        'num_contrato',
        'id_mod_contra',
        'id_cober_pb',
        'aut_cabecera',
        'aut_detalle',
        'num_caracter_det',
        'tipo_consul',
        'id_listapre',
        'id_tipo_factu',
        'centro_costo',
        'periodo_pago',
        'bodegas',
        'conceptos',
        'cuota_mod',
        'iva',
        'id_user',
        'redondeo_iva',
        'id_tipo_dispensacion'
    ];

   

   

    public function idUser()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

   
}
