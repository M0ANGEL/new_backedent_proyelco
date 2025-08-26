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
        Schema::create('solicitudes_activos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activo_id'); 
            $table->unsignedBigInteger('user_id'); 
            $table->string('bodega_solicita'); 
            $table->string("motivo");
            $table->string("estado")->default(0); //estados de la solicitud 0 creada, 1 aceptada, 2 rechazada
            $table->string("tipo_ubicacion"); 
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
        Schema::dropIfExists('solicitudes_activos');
    }
};
