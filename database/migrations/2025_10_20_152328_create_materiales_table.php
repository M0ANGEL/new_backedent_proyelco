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
        Schema::create('materiales', function (Blueprint $table) {
            $table->id();
            
            // Campos del Excel
            $table->unsignedBigInteger('user_id')->nullable(); //usaurio que carga el acrchivo
            $table->string('codigo_proyecto'); //codigo de proyecto ya que es unico por casas o apartamentos
            $table->string('codigo'); //codigo de iten
            $table->text('descripcion');
            $table->string('padre')->nullable();
            $table->string('um')->nullable();
            $table->decimal('cantidad', 15, 4)->nullable();
            $table->string('subcapitulo')->nullable();
            $table->decimal('cant_apu', 15, 4)->nullable();
           
            $table->decimal('rend', 15, 4)->nullable();
            $table->integer('iva')->default(0);
            $table->decimal('valor_sin_iva', 20, 4)->nullable();
            $table->string('tipo_insumo')->nullable();
            $table->string('agrupacion')->nullable();

            
            // Control de stock
            $table->decimal('cant_total', 15, 4)->nullable();
            $table->decimal('cant_restante', 15, 4)->nullable();
            $table->decimal('cant_solicitada', 15, 4)->nullable();
            $table->tinyInteger('estado')->default(1); //estados de itens  1: carga inicial 2:elminado 3: agregado 4: editado 


            
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('materiales');
    }
};
