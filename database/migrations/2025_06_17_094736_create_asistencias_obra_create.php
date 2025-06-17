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
        Schema::create('asistencias_obra_create', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personal_id');
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('usuario_asigna');
            $table->unsignedBigInteger('usuario_confirma')->nullable();
            $table->tinyInteger('confirmacion')->default(0);
            $table->string('detalle')->nullable();
            $table->date('fecha_programacion');
            $table->date('fecha_confirmacion')->nullable();


            $table->timestamps();
            $table->foreign('personal_id')->references('id')->on('personal'); //relacion con el personal
            $table->foreign('proyecto_id')->references('id')->on('proyecto');
            $table->foreign('usuario_asigna')->references('id')->on('users');
            $table->foreign('usuario_confirma')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asistencias_obra_create');
    }
};
