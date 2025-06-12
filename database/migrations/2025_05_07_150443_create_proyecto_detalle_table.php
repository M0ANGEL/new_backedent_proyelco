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
        Schema::create('proyecto_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); //usuario que confirma entrega del piso
            $table->unsignedBigInteger('proyecto_id'); //relacion con el proyecto padre
            $table->string('torre',3); // identificador
            $table->string('piso',3); // identificador
            $table->string('apartamento',3); // identificador
            $table->string('consecutivo',10); // consecutivo por apartamento
            $table->string('orden_proceso',2); // orden de los procesos
            $table->string('cambio_proceos',2); // numero para cambio de los procesos
            $table->unsignedBigInteger('procesos_proyectos_id'); //relacion con los procesos
            $table->string('text_validacion')->nullable(); // orden de los procesos
            $table->date('fecha_ini_torre')->nullable(); //fecha en la que inicia la torre
            $table->string('estado',2)->default(0);  // 0: no disponible 1: disponible 2: realizado
            $table->date('fecha_habilitado')->nullable(); 
            $table->tinyInteger('validacion')->default(0); //si tienen validacion el proceso 0: no tiene 1: tienen
            $table->tinyInteger('estado_validacion')->default(0); //confirmacion de la validacion si la necesita 0: no validado 1: validado
            $table->date('fecha_validacion')->nullable(); // fecha cuando se confirma la validacion
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('proyecto_id')->references('id')->on('proyecto');
            $table->foreign('procesos_proyectos_id')->references('id')->on('procesos_proyectos');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proyecto_detalle');
    }
};
