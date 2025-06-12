<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submenu extends Model
{
    use HasFactory;

    protected $table = 'submenu';

    protected $fillable = [
        'nom_smenu',
        'link_smenu',
        'desc_smenu',
        'id_menu'
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'id_menu', 'id');
    }
}
