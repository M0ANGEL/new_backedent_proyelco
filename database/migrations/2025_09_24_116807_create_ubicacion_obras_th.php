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
            $table->unsignedBigInteger('obra_id'); //ubicacion
            $table->string('rango')->default(150); //metros permitidos de la distancia
            $table->json('usuarios_permisos',100)->nullable(); //usuarios que tiene permisos de las obras
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('obra_id')->references('id')->on('bodegas_area');
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
