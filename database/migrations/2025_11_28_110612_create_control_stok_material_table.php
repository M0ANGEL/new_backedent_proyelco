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
        Schema::create('control_stok_material', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('movimiento')->nullable();  //salida, ajuste
            $table->string('tipo')->nullable();  //1, ajuste
            $table->decimal('cant_solicitada', 15, 4)->nullable(); //es la cantidad que se envia para solicitar
            $table->decimal('cant_disponible', 15, 4)->nullable(); //es la cantidad que habia en stok
            $table->decimal('cant_restante', 15, 4)->nullable(); //es la resta de stock - solicitud, es lo que queda 
            $table->timestamps();

             $table->foreign('user_id')->references('id')->on('users');
             $table->foreign('material_id')->references('id')->on('materiales');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('control_stok_material');
    }
};
