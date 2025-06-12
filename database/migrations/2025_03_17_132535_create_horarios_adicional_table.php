<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('horarios_adicional', function (Blueprint $table) {
            $table->id();
            $table->string('observacion');
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_final');
            $table->string('proceso_autoriza_id', 1);
            $table->json('usuarios_autorizados',10)->nullable();
            $table->tinyInteger('estado')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('horarios_adicional');
    }
};
