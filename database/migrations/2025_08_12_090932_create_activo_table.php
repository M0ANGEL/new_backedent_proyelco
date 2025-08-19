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
        Schema::create('activo', function (Blueprint $table) {
            $table->id();
            $table->string("numero_activo")->unique();
            $table->unsignedBigInteger('categoria_id'); //relacion con categorias
            $table->unsignedBigInteger('subcategoria_id'); //relacion con subcategorias
            $table->unsignedBigInteger('user_id'); //relacion con usuario que crea el activo
            $table->string("descripcion");
            $table->string("valor")->default(0);
            $table->date("fecha_fin_garantia")->nullable();
            $table->tinyInteger('condicion'); //1 bueno 2 malo 3 en reparacion
            $table->string("marca",50)->nullable();
            $table->string("serial",100)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->tinyInteger('salida')->default(0);  //0 sin asignar 1 en sin aceptar 2 asignado 
            $table->unsignedBigInteger('ubicacion_id'); //es la ubicacion actual de ese activo para los traslados no poner donde esta
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('categoria_id')->references('id')->on('categoria_activos');
            $table->foreign('subcategoria_id')->references('id')->on('subcategoria_activos');
            $table->foreign('ubicacion_id')->references('id')->on('bodegas_area');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activo');
    }
};
