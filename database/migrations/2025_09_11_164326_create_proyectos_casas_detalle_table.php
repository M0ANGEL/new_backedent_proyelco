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
        Schema::create('proyectos_casas_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); //usuario que confirma la activida
            $table->unsignedBigInteger('proyecto_manzana_id'); //relacion con el proyecto padre de casas
            $table->string('manzana',3); // identificador
            $table->string('casa',3); // identificador
            $table->string('consecutivo_casa',10); // consecutivo para las casa nomenclaturas
            $table->string('piso',10); // piso de las casa
            $table->string('estapa',10); // estapas
            $table->string('orden_proceso',2); // orden de los procesos
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
            $table->foreign('proyecto_manzana_id')->references('id')->on('proyectos_casas');
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
        Schema::dropIfExists('proyectos_casas_detalle');
    }
};
