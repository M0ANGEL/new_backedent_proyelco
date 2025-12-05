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
        Schema::create('solicitud_material_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_id'); // referencia a documentos_organismos
            $table->string('codigo_proyecto');
            $table->string('ruta_archivo');
            $table->string('nombre_original');
            $table->string('extension');
            $table->integer('tamano');
            $table->timestamps();

            $table->foreign('solicitud_id')
                ->references('id')
                ->on('solicitud_material')
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
        Schema::dropIfExists('solicitud_material_adjuntos');
    }
};
