<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('horarios_detalle', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id');

            $table->string('dia', 10);
            $table->time('hora_inicio');
            $table->time('hora_final');
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('estado')->default(1);
            $table->timestamps();

            $table->foreign('horario_id')->references('id')->on('horarios');
            $table->foreign('user_id')->references('id')->on('users');

        });

    }

    public function down()
    {
        Schema::dropIfExists('horarios_detalle');
    }
};
