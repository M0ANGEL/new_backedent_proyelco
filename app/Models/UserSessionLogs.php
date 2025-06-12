<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSessionLogs extends Model
{
    use HasFactory;

    protected $table = 'user_session_logs';

    protected $fillable = [
        'id',
        'start_session',
        'last_session',
        'action',
        'user_id'
    ];
}
