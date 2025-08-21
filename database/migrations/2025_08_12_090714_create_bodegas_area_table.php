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
        Schema::create('bodegas_area', function (Blueprint $table) {
            $table->id();
            $table->string("direccion");
            $table->string("nombre");
            $table->tinyInteger('estado')->default(1);
            $table->unsignedBigInteger('user_id')->nullable(); 
            $table->timestamps();

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
        Schema::dropIfExists('bodegas_area');
    }
};
