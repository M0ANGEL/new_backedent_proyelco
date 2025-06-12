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
        Schema::create('val_xpro_xpt', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_validacion');
            $table->integer('cant');
            $table->unsignedBigInteger('P1_id'); 
            $table->unsignedBigInteger('P1_depende_id'); 
            $table->unsignedBigInteger('proyecto_id'); 
            $table->timestamps();

            $table->foreign('P1_id')->references('id')->on('procesos_proyectos');
            $table->foreign('P1_depende_id')->references('id')->on('procesos_proyectos');
            $table->foreign('proyecto_id')->references('id')->on('proyecto');


/*
            VALIDACIONxPROCESO
ID
TIPOVALIDACION
CANT
PR_ID
PRDEPENDE_ID
PROYECTO_ID */

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('val_xpro_xpt');
    }
};
