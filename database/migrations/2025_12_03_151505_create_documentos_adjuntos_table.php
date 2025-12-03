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
        Schema::create('documentos_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documento_id');   // referencia a documentacion_operadores
            $table->string('ruta_archivo');
            $table->string('nombre_original');
            $table->string('extension');
            $table->integer('tamano');
            $table->timestamps();

            $table->foreign('documento_id')
                ->references('id')
                ->on('documentacion_operadores')
                ->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentos_adjuntos');
    }
};
