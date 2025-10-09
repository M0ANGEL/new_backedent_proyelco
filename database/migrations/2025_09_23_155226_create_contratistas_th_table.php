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
        Schema::create('contratistas_th', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nit')->unique();
            $table->string('contratista');
            $table->string('arl',100);
            $table->string('actividad',100);
            $table->string('contacto',100);
            $table->string('telefono',15)->default(0);
            $table->string('direccion',150)->default(0);
            $table->string('correo',150)->default(0);
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
        Schema::dropIfExists('contratistas_th');
    }
};
