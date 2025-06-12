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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('cedula');
            $table->string('nombre');
            $table->string('telefono');
            $table->string('cargo');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('image')->default('img/default.jpg');
            $table->string('last_login');
            $table->string('rol');
            $table->string('correo')->nullable();
            $table->tinyInteger('estado')->length(1)->default(1);
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
