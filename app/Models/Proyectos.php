<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proyectos extends Model
{
    use HasFactory;

    protected $table = "proyecto";

    protected $fillable = [
        'tipoProyecto_id',
        'cliente_id',
        'usuario_crea_id',
        'encargado_id',
        'descripcion_proyecto',
        'fecha_inicio',
        'codigo_proyecto',
        'torres',
        'cant_pisos',
        'apt',
        'pisosCambiarProcesos',
        'usuarios_notificacion',
        'estado',
        'fecha_ini_proyecto',
    ];

    public function detalles()
    {
        return $this->hasMany(ProyectosDetalle::class, 'proyecto_id');
    }


    public function ingeniero()
    {
        return $this->belongsTo(User::class, 'ingeniero_id');
    }

    public function documentacion()
    {
        return $this->hasMany(Documentos::class, 'codigo_proyecto', 'codigo_proyecto');
    }


    public function documentosOrganismos()
    {
        return $this->hasMany(DocumentosOrganismos::class, 'codigo_proyecto', 'codigo_proyecto');
    }
}
/* no se si la idea no se entendio, si en proyectosDetalle hay inactividad mas de 3 dias sin contar dias de la tabla festivos ni domingos, entonces se debe enviar un correo
informativo indicndo que hay inactivida, entonces mejoremos la loliga
*/
