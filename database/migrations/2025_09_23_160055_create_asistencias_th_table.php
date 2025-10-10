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
        Schema::create('asistencias_th', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('tipo_empleado'); //1 proyelco 2 no proyelco
            $table->string('empleado_id');
            $table->string('identificacion');
            $table->date('fecha_ingreso');
            $table->time('hora_ingreso');
            $table->date('fecha_salida')->nullable();
            $table->time('hora_salida')->nullable();
            $table->time('horas_laborales')->nullable();
            $table->tinyInteger('tipo_obra'); //1 apartamentos 2 casas
            $table->string('obra_id');
            $table->timestamps();

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
        Schema::dropIfExists('asistencias_th');
    }
};
