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
        Schema::create('procesos_proyectos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipoPoryecto_id');
            $table->string('nombre_proceso');
            $table->unsignedBigInteger('user_id'); // usuario que crea el proceso
            $table->timestamps();

            $table->foreign('tipoPoryecto_id')->references('id')->on('tipos_de_proyectos');
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
        Schema::dropIfExists('procesos_proyectos');
    }
};
