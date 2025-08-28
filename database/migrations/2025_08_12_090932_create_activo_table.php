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
            $table->tinyInteger('aceptacion')->default(0);  //0 sin asignar 1 en sin aceptar 2 asignado 
            $table->tinyInteger('tipo_ubicacion')->default(1); // 1 administrativas 2 obras
            $table->string('ubicacion_actual_id',5); //relacion con ubicacion
            $table->string('ubicacion_destino_id',5)->nullable(); //relacion con ubicacion
            
            //DATOS DE ASIGNACION DEL ACTIVO
            $table->json('usuarios_asignados')->nullable(); //quien se le asigna el activo
            $table->json('usuarios_confirmaron')->nullable(); //quien se le asigna el activo
            
            
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('categoria_id')->references('id')->on('categoria_activos');
            $table->foreign('subcategoria_id')->references('id')->on('subcategoria_activos');
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
