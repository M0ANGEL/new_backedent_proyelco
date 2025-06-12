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
        Schema::create('empresa_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_operario'); // Persona que realizo la accion
            $table->string('accion'); // Crear, Editar, Eliminar
            $table->longText('data');
            $table->longText('old');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_operario')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_logs');
    }
};
