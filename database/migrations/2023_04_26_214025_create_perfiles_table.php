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
        Schema::create('perfiles', function (Blueprint $table) {
            $table->id();
            $table->string('cod_perfil');
            $table->string('nom_perfil');
            $table->string('desc_perfil');
            $table->integer('estado')->default(1);
            $table->unsignedBigInteger('id_empresa')->index();
            $table->timestamps();      
            
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('perfiles');
    }
};
