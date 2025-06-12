<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('festivos', function (Blueprint $table) {
            $table->id();
            $table->date('festivo_fecha');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('festivos');
    }
};
