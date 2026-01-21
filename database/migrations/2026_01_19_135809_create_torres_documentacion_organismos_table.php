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
        Schema::create('torres_documentacion_organismos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_proyecto'); //codigo del proyecto
            $table->string('codigo_documento'); //codigo del documento
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario que realizó la confirmacio');
            $table->unsignedBigInteger('actividad_id')->nullable()->comment('Id de la actividad para mapear');
            $table->unsignedBigInteger('actividad_hijos_id')->nullable()->comment('Id de la actividad para mapear los hijos');
            $table->string('tm', 20); //esta sera la tore o la manzana, estas podran ser edictadas para que se cambie el consecutivo
            $table->tinyInteger('estado')->default(1); //1 habilitado 2 completado
            $table->timestamps();


            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('actividad_id')->references('id')->on('actividades_organismos');
            $table->foreign('actividad_hijos_id')->references('id')->on('actividades_organismos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torres_documentacion_organismos');
    }
};
