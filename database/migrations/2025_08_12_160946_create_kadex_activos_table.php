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
            $table->tinyInteger('aceptacion')->default(0); //aceptacion del activo, 0 sin asignar, 1 asignado, 2 aceptado
            $table->unsignedBigInteger('ubicacion_actual_id'); //relacion con ubicacion
            $table->unsignedBigInteger('ubicacion_destino_id'); //relacion con ubicacion
            $table->date('fecha_Aceptacion')->nullable();
            $table->string('observacion')->nullable();
            $table->string('tipo')->nullable(); //los tipos de movimientos 1 traslados | 2 solicitud | 3 mantenimientos
            $table->timestamps();

            $table->foreign('activo_id')->references('id')->on('activo');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('ubicacion_actual_id')->references('id')->on('bodegas_area');
            $table->foreign('ubicacion_destino_id')->references('id')->on('bodegas_area');

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
