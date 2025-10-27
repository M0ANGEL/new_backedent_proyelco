<?php

use App\Http\Controllers\Api\ActivosFijos\ActivosController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/status', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = '✅ Conectado a la base de datos';
    } catch (\Exception $e) {
        $dbStatus = '❌ Error al conectar: ' . $e->getMessage();
    }

    return view('status', compact('dbStatus'));
});

// routes/web.php
Route::get('activos/{id}/qr', [ActivosController::class, 'verActivoQR']);

