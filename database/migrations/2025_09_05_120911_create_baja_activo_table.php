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
        Schema::create('baja_activo', function (Blueprint $table) {
            $table->id();
            $table->date("fecha_baja");
            $table->string("observaciones");
            $table->unsignedBigInteger('activo_id')->nullable(); //relacion con activos
            $table->unsignedBigInteger('user_id')->nullable(); //relacion con usuario que da debaja
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
        Schema::dropIfExists('baja_activo');
    }
};
