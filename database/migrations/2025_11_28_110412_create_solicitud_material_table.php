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
        Schema::create('solicitud_material', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); //usuario que hace la solicitud
            $table->unsignedBigInteger('material_id')->nullable(); //el id del material
            $table->string('codigo'); // código de ítem
            $table->text('descripcion'); //descripcion del proyecto
            $table->decimal('cant_solicitada', 15, 4)->nullable(); //es la cantidad que se solicita
            $table->dateTime('fecha_solicitud'); //la fecha en la que se hace la solicitud
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('material_id')->references('id')->on('materiales');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('solicitud_material');
    }
};
