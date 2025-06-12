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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('emp_nombre')->unique();
            $table->tinyInteger('estado')->default(1);
            $table->string('nit')->unique()->index();
            $table->string('direccion');
            $table->string('telefono');
            $table->string('cuenta_de_correo');
            $table->unsignedBigInteger('id_user');

            $table->timestamps();

            $table->foreign('id_user')->references('id')->on('users');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clientes');
    }
};
