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
        Schema::create('nombre_xtore', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_torre');
            $table->string('torre',5);
            $table->unsignedBigInteger('proyecto_id');
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('proyecto'); 

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nombre_xtore');
    }
};
