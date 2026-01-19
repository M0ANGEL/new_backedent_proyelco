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

            //campos de logistica para control de la solicitud
            $table->integer('documento_sinco')->default(0)->comment('Campo para saber si hay documento de simco, si hay mostrar a logistica'); //0 no hay, 1 si hay
            $table->unsignedBigInteger('userAsinga_id')->nullable()->comment('Usuario que asigno las solicitud');
            $table->unsignedBigInteger('userAsignado_id')->nullable()->comment('Usuario que se asigno las solicitud');
            $table->integer('estado')->default(0)->comment('Estado del documento'); //0 sin asiganar, 1 asigando, 2 en proceso, 3 terminado
            $table->decimal('cant_entregada',15,4)->nullable()->comment('cantidad que se entrega en despacho');
            $table->string('observacion',255)->default('Sin Observacion al momento');
            $table->dateTime('fecha_finalizado')->nullable()->comment('fecha en la que se completa el alistamiento');

            $table->dateTime('fecha_solicitud')->comment('Fecha y hora que se solicita');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('userAsinga_id')->references('id')->on('users');
            $table->foreign('userAsignado_id')->references('id')->on('users');

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
