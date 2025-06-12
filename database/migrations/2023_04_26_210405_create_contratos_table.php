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
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('contr_num');
            $table->string('contr_nit');
            $table->string('contr_nombre');
            $table->string('contr_tipo_doc');
            $table->string('contr_tipo_conv');
            $table->bigInteger('id_listpre')->index();
            $table->integer('estado')->default(1);
            $table->date('fec_ini');
            $table->date('fec_fin');
            $table->string('prefijo');
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
        Schema::dropIfExists('contratos');
    }
};
