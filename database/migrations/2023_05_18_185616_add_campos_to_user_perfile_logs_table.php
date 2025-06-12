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
        Schema::create('user_perfile_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_perfil');
            $table->unsignedBigInteger('id_user'); // Persona que realizo la accion
            $table->string('accion'); // Crear, Editar, Eliminar
            $table->longText('data');
            $table->longText('old');
            $table->timestamps();

            $table->foreign('id_perfil')->references('id')->on('perfiles');
            $table->foreign('id_user')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_perfile_logs');
    }
};
