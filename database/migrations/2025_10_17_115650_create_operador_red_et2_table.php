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
        Schema::create('operador_red_et2', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100);
            $table->unsignedBigInteger('actividad')->nullable();
            $table->date('fecha_pro');
            $table->date('fecha_confi')->nullable();
            $table->tinyInteger('estado')->default(0);
            $table->unsignedBigInteger('usaurio_id')->nullable();
            $table->string('tipo')->default('principal'); // principal, simultanea, division
            $table->unsignedBigInteger('actividad_depende')->nullable(); //simultaneo depende de??
            $table->integer('orden'); // Para mantener el orden de la lista
            $table->timestamps();

            $table->foreign('actividad')->references('id')->on('actividades_emcali');
            $table->foreign('usaurio_id')->references('id')->on('users');
            $table->foreign('actividad_depende')->references('id')->on('actividades_emcali');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('operador_red_et2');
    }
};
