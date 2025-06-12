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
        Schema::create('log_horarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id');
            $table->unsignedBigInteger('user_id');
            $table->string('hora_anterior');
            $table->string('hora_nueva');
            $table->timestamps();


            $table->foreign('horario_id')->references('id')->on('horarios');
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
        Schema::dropIfExists('log_horarios');
    }
};
