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
        Schema::create('mantenimiento_activos', function (Blueprint $table) {
            $table->id();
            $table->string("valor");
            $table->date("fecha_inicio");
            $table->date("fecha_fin")->nullable();
            $table->string("observaciones");
            $table->unsignedBigInteger('activo_id')->nullable(); //relacion con activos
            $table->unsignedBigInteger('user_id')->nullable(); //relacion con usuarios
            $table->tinyInteger('estado')->default(1); //mantenimiento activo o ya terminado 1 activo 2 terminado
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
        Schema::dropIfExists('mantenimiento_activos');
    }
};
