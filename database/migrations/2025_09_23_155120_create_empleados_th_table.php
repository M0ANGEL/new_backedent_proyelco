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
        Schema::create('empleados_th', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('estado')->default(1);
            $table->string('identificacion')->unique();
            $table->string('tipo_documento');
            $table->string('nombre_completo');
            $table->date('fecha_expedicion')->nullable();
            $table->string('estado_civil');
            $table->unsignedBigInteger('ciuda_expedicion_id');
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedBigInteger('pais_residencia_id');
            $table->unsignedBigInteger('ciudad_resudencia_id');
            $table->string('genero',20)->nullable();
            $table->string('telefono_fijo',15)->nullable();
            $table->string('telefono_celular',15)->nullable();
            $table->string('direccion')->nullable();
            $table->string('correo')->nullable();
            $table->unsignedBigInteger('cargo_id');
            $table->unsignedBigInteger('user_id');
            $table->string("minimo",5);
            $table->string("salario")->default(0);
            $table->string("valor_hora")->default(0);
            $table->timestamps();

            $table->foreign('ciuda_expedicion_id')->references('id')->on('ciudad_th');
            $table->foreign('pais_residencia_id')->references('id')->on('pais_th');
            $table->foreign('ciudad_resudencia_id')->references('id')->on('ciudad_th');
            $table->foreign('cargo_id')->references('id')->on('cargos_th');
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
        Schema::dropIfExists('empleados_th');
    }
};
