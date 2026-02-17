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
        Schema::create('actividades_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('id'); //idSeguimiento para llevar control sin depender del id 
            $table->string('actividad'); //nombre
            $table->string('tiempo',5); // en dias
            $table->string('descripcion')->nullable();
            $table->tinyInteger('tipo'); //1 principal 2 simultaneo
            $table->tinyInteger('etapa'); 
            $table->tinyInteger('operador'); //emcali o celsia
            $table->tinyInteger('calculo'); //0 nada 1 si suma
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
        Schema::dropIfExists('actividades_documentos');
    }
};
