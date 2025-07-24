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
        Schema::table('cambio_estado_apt_proyecto', function (Blueprint $table) {
            $table->json('apt_afectados',15)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cambio_estado_apt_proyecto', function (Blueprint $table) {
             $table->dropColumn('apt_afectados');
        });
    }
};
