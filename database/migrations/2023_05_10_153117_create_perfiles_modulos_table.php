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
        Schema::create('perfiles_modulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_modulo')->index()->nullable();
            $table->unsignedBigInteger('id_perfil')->index()->nullable();
            $table->unsignedBigInteger('id_menu')->index()->nullable();
            $table->unsignedBigInteger('id_submenu')->index()->nullable();
            $table->timestamps();

            $table->foreign('id_modulo')->references('id')->on('modulos')->onDelete('cascade');
            $table->foreign('id_perfil')->references('id')->on('perfiles')->onDelete('cascade');
            $table->foreign('id_menu')->references('id')->on('menu')->onDelete('cascade');
            $table->foreign('id_submenu')->references('id')->on('submenu')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('perfiles_modulos');
    }
};
