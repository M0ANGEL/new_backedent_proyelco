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
        Schema::create('documentacion_torres', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_proyecto'); //codigo del proyecto
            $table->string('codigo_documento'); //codigo del documento
            $table->string('nombre_torre'); //nombre de la torre
            $table->tinyInteger('operador');  //1 emcali 2 celsia
            $table->unsignedBigInteger('actividad_id')->nullable()->comment('Id de la actividad para activar cuando se complete');
            $table->tinyInteger('estado')->default(1); //0 nada 1 completado y debe habiliar un documento
            $table->timestamps();


            $table->foreign('actividad_id')->references('id')->on('actividades_organismos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentacion_torres');
    }
};
