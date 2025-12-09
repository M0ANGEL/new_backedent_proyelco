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
        Schema::create('solicitud_material', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario que realizó la modificación');
            $table->string('numero_solicitud')->comment('Numero Unico de solicitud_erp para ver el orden');
            $table->string('numero_solicitud_sinco')->nullable()->comment('Numero Unico de solicitud_erp');
            $table->string('codigo_proyecto');
            $table->string('codigo_item');
            $table->string('codigo_insumo')->comment('Código del ítem');
            $table->text('descripcion')->comment('Descripción del ítem');
            $table->string('padre')->nullable()->comment('Ítem padre en la jerarquía');
            $table->integer('nivel')->comment('Nivel en la jerarquía del proyecto');
            $table->string('um')->comment('Unidad de medida');

            // Campos de cantidad
            $table->decimal('cant_unitaria', 15, 4)->comment('Cantidad por unidad');
            $table->decimal('cant_solicitada', 15, 4)->comment('Cantidad solicitada');
            $table->decimal('cant_total', 15, 4)->comment('Cantidad total, es la multiplicacin de la cantidad por cant_retante');

            $table->dateTime('fecha_solicitud')->comment('Fecha y hora que se solicita');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');

            // Índices recomendados para mejor performance
            $table->index(['codigo_proyecto']);
            $table->index(['codigo_insumo']);
            $table->index(['fecha_solicitud']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('solicitud_material');
    }
};
