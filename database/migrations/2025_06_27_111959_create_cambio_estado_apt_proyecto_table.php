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
        Schema::create('cambio_estado_apt_proyecto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('userConfirmo_id');
            $table->unsignedBigInteger('proyecto_id');
            $table->string('motivo');
            $table->string('piso',2);
            $table->string('apt',2);
            $table->dateTime('fecha_confirmo');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users'); //usuario que cambio estado
            $table->foreign('userConfirmo_id')->references('id')->on('users'); //usuario que confirmo el apt 
            $table->foreign('proyecto_id')->references('id')->on('proyecto'); //proyecto

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cambio_estado_apt_proyecto');
    }
};
