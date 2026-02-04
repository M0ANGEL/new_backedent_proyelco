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
        Schema::create('reporte_material_nc', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_proyecto', 20)->unique()->comment('Codigo unico del proyecto sea casa o edificio');
            $table->tinyInteger('tipo_reporte')->comment('1=necesario, 2=informativo 3=reparable etc...');
            $table->string('insumo');
            $table->string('codigo_insumo', 100);
            $table->string('factura', 100);
            $table->string('cantidad_reportada', 50)->comment('Cantidad de material que se reporta como no conforme');

            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->string('descripcion_nc', 500);
            $table->string('estado', 20)->default('1')->comment('1=evniado, 2=no enviado, 3=procesado');
            $table->unsignedBigInteger('id_user')->comment('Usuario que realiza el reporte');


            /* respuesta de el proveedor */
            $table->string('respuesta', 20)->default('1')->comment('1=si, 2=no aceptado por proveedor');
            $table->string('respuesta_proveedor', 500)->nullable();
            $table->string('cantidad_aceptada', 50)->nullable()->comment('Cantidad de material no conforme que el proveedor acepta como no conforme');


            $table->timestamps();


            $table->foreign('id_user')->references('id')->on('users');
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reporte_material_nc');
    }
};
