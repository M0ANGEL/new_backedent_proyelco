<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersPerfiles extends Model
{
    protected $table = 'users_perfiles';

    protected $fillable = [
        'id_user', 'id_perfil'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'id_perfil');
    }
}
