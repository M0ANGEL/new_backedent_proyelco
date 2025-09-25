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
        Schema::create('ciudad_th', function (Blueprint $table) {
            $table->id();
            $table->string('ciudad');
            $table->tinyInteger('estado')->default(1); //estado de la ciudad si esta activo o no
            $table->unsignedBigInteger('pais_id');
            $table->unsignedBigInteger('user_id'); //usuario que crea la ciudad
            $table->timestamps();

            $table->foreign('pais_id')->references('id')->on('pais_th');
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
        Schema::dropIfExists('ciudad_th');
    }
};
