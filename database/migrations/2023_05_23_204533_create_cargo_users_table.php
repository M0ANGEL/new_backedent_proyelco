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
        Schema::create('cargo_users', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('estado')->default(1);
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_cargo');
            $table->timestamps();

            $table->foreign('id_user')->references('id')->on('users');
            $table->foreign('id_cargo')->references('id')->on('cargos');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cargo_users', function (Blueprint $table) {
            $table->dropForeign(['id_user']);
            $table->dropColumn('id_cargo');
        });

        Schema::dropIfExists('cargo_users');
    }
};
