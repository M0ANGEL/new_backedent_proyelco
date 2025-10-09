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
        Schema::create('ubicacion_obras_th', function (Blueprint $table) {
            $table->id();
            //sede
            $table->decimal('latitud',10,6);
            $table->decimal('longitud',10,6);

            $table->string('serial'); //campo de array de seriales

            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('tipo_obra'); //1 apartamentos 2 casas
            $table->string('obra_id',15); //id
            $table->string('distancia')->default(20); //metros permitidos de la distancia
            $table->tinyInteger('estado')->default(1);
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
        Schema::dropIfExists('ubicacion_obras_th');
    }
};
