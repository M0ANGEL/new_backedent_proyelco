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
            $table->tinyInteger('tipo_ubicacion')->default(1); // 1 administrativas 2 obras
            $table->string('bodega_solicita',5); 
            $table->string("motivo");
            $table->string("estado")->default(0); //estados de la solicitud 0 creada, 1 aceptada, 2 rechazada
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
