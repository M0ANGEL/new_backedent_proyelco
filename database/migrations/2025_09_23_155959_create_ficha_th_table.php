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
        Schema::create('ficha_th', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('estado')->default(1);
            $table->tinyInteger('tipo_empleado')->default(1);
            $table->string('identificacion');
            $table->string('empleado_id');
            $table->string('rh');
            $table->string('hijos');
            $table->string('eps');
            $table->string('afp');
            $table->unsignedBigInteger('contratista_id');
            $table->timestamps();

            $table->foreign('contratista_id')->references('id')->on('contratistas_th');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ficha_th');
    }
};
