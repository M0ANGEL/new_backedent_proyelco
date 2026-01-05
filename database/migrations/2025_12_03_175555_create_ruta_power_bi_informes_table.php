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
        Schema::create('ruta_power_bi_informes', function (Blueprint $table) {
            $table->id();
            $table->string('ruta')->default("defaul");
            $table->string('link_power_bi')->require();
            $table->string('nombre')->default("ND");
            $table->tinyInteger('estado')->default(1); //1 activo 0 inactivo
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
        Schema::dropIfExists('ruta_power_bi_informes');
    }
};
