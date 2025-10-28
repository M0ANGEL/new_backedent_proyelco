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
        Schema::create('documentacion_operadores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_proyecto', 10);
            $table->string('codigo_documento', 100);
            $table->tinyInteger('etapa');

            $table->unsignedBigInteger('actividad_id')->nullable();
            $table->unsignedBigInteger('actividad_depende_id')->nullable(); 
            $table->string('tipo')->default('principal'); // principal, simultanea
            $table->integer('orden'); // Para mantener el orden de la lista

            $table->date('fecha_proyeccion');
            $table->date('fecha_actual');
            $table->date('fecha_confirmacion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();


            $table->tinyInteger('estado')->default(0);
            $table->tinyInteger('operador');  //1 emcali 2 celsia
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->foreign('actividad_id')->references('id')->on('actividades_documentos');
            $table->foreign('usuario_id')->references('id')->on('users');
            $table->foreign('actividad_depende_id')->references('id')->on('actividades_documentos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentacion_operadores');
    }
};
