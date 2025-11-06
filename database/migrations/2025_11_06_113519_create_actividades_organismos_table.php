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
        Schema::create('actividades_organismos', function (Blueprint $table) {
            $table->id();
            $table->string('actividad'); //nombre
            $table->tinyInteger('tipo'); //1 principal 2 con hijos 3 hijos
            $table->string('padre')->nullable();
            $table->tinyInteger('operador'); //1 retie 2 ritel 3 retialap
            $table->tinyInteger('estado')->default(1); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('actividades_organismos');
    }
};
