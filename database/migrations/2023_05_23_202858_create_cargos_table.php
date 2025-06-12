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
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique()->index();
            $table->string('descripcion');
            $table->tinyInteger('estado')->default(1);
            $table->unsignedBigInteger('id_empresa');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cargos', function (Blueprint $table) {
            $table->dropForeign(['id_empresa']);
            $table->dropColumn('id_empresa');
        });

        Schema::dropIfExists('cargos');
    }
};
