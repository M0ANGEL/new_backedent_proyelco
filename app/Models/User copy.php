<?php

namespace App\Models;

use App\Models\Facturacion\FacturacionConvenio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $connection = 'sqlsrv';

    protected $table = 'users';

    protected $fillable = [
        'cedula',
        'nombre',
        'telefono',
        'cargo',
        'username',
        'password',
        'image',
        'last_login',
        'rol',
        'remember_token',
        'estado',
        'created_at',
        'updated_at',
        'correo',
        'can_config_telefono'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsToMany(Empresa::class, 'emp_x_usu', 'id_user', 'id_empresa');
    }

    public function empresas()
    {
        return $this->hasMany(EmpxUsu::class, 'id_user', 'id')->with('empresa');
    }

    public function perfiles()
    {
        return $this->belongsToMany(Perfil::class, 'users_perfiles', 'id_user', 'id_perfil');
    }


    public function cargos()
    {
        return $this->belongsToMany(Cargo::class, 'cargo_users', 'id_user', 'id_cargo');
    }


    //horarios
    public function horario()
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }
}
