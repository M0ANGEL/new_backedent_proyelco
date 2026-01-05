<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_organismos_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documento_id'); // referencia a documentos_organismos
            $table->string('ruta_archivo');
            $table->string('nombre_original');
            $table->string('extension');
            $table->integer('tamano');
            $table->timestamps();

            $table->foreign('documento_id')
                ->references('id')
                ->on('documentos_organismos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_organismos_adjuntos');
    }
};
