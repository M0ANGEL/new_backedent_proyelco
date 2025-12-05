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
        Schema::create('cambios_proyeccion_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario que realizó la modificación');
            $table->string('version_edicion')->comment('Versión o número de edición');
            $table->string('codigo_proyecto');
            $table->string('codigo_item');
            $table->string('codigo_insumo')->comment('Código del ítem');
            $table->text('descripcion')->comment('Descripción del ítem');
            $table->string('padre')->nullable()->comment('Ítem padre en la jerarquía');
            $table->integer('nivel')->comment('Nivel en la jerarquía del proyecto');
            $table->string('um')->comment('Unidad de medida');

            // Campos de cantidad
            $table->decimal('cant_old', 15, 4)->comment('Cantidad anterior');
            $table->decimal('cant_modificada', 15, 4)->comment('Cantidad modificada');
            $table->decimal('cant_final', 15, 4)->comment('Cantidad final');

            // Campos de cantidad APU
            $table->decimal('cant_apu_old', 15, 4)->comment('Cantidad APU anterior');
            $table->decimal('cant_apu_modificada', 15, 4)->comment('Cantidad APU modificada');
            $table->decimal('cant_apu_final', 15, 4)->comment('Cantidad APU final');

            $table->dateTime('fecha_modificacion')->comment('Fecha y hora de la modificación');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');

            // Índices recomendados para mejor performance
            $table->index(['codigo_proyecto']);
            $table->index(['codigo_insumo']);
            $table->index(['fecha_modificacion']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cambios_proyeccion_historial');
    }
};
