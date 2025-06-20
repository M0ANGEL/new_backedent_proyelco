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
        Schema::create('proyecto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipoProyecto_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('usuario_crea_id');
            $table->unsignedBigInteger('encargado_id'); //encargado de la obra
            $table->unsignedBigInteger('ingeniero_id'); //ingeniero de la obra
            $table->string('descripcion_proyecto');
            $table->date('fecha_inicio');
            $table->string('codigo_proyecto')->unique();
            $table->integer('torres'); // numericos
            $table->integer('cant_pisos');
            $table->integer('apt');
            $table->integer('pisosCambiarProceso');
            $table->json('usuarios_notificacion',10)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->integer('activador_pordia_apt')->nullable();
            $table->date('fecha_ini_proyecto')->nullable(); //fecha en la que inicia el proyecto
            $table->timestamps();

            $table->foreign('tipoProyecto_id')->references('id')->on('tipos_de_proyectos');
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->foreign('usuario_crea_id')->references('id')->on('users');
            $table->foreign('encargado_id')->references('id')->on('users');
            $table->foreign('ingeniero_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proyecto');
    }
};
