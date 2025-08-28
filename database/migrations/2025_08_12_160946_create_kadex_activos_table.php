<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kadex_activos', function (Blueprint $table) {
            $table->id();
            $table->string("codigo_traslado")->unique(); //prefijo + incremental
            $table->unsignedBigInteger('activo_id'); 
            $table->unsignedBigInteger('user_id'); //quien crea la asignacion
            $table->json('usuarios_asignados')->nullable(); //quien se le asigna el activo
            $table->json('usuarios_confirmaron')->nullable(); //quien se le asigna el activo
            $table->tinyInteger('aceptacion')->default(1); //aceptacion del activo, 0 sin asignar, 1 asignado, 2 aceptado
            $table->tinyInteger('tipo_ubicacion')->default(1); // 1 administrativas 2 obras
            $table->string('ubicacion_actual_id',5); //relacion con ubicacion
            $table->string('ubicacion_destino_id',5)->nullable(); //relacion con ubicacion
            $table->date('fecha_Aceptacion')->nullable();
            $table->string('observacion')->nullable();
            $table->string('tipo')->nullable(); //los tipos de movimientos 1 traslados | 2 solicitud | 3 mantenimientos
            $table->timestamps();

            $table->foreign('activo_id')->references('id')->on('activo');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kadex_activos');
    }
};
