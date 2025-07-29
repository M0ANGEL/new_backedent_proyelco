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
    Schema::table('proyecto', function (Blueprint $table) {
        if (Schema::hasColumn('proyecto', 'encargado_id')) {
            $table->dropForeign(['encargado_id']);
            $table->dropColumn('encargado_id');
        }

        if (Schema::hasColumn('proyecto', 'ingeniero_id')) {
            $table->dropForeign(['ingeniero_id']);
            $table->dropColumn('ingeniero_id');
        }
    });

    Schema::table('proyecto', function (Blueprint $table) {
        $table->json('encargado_id')->nullable();
        $table->json('ingeniero_id')->nullable();
    });
}


    public function down()
    {
        Schema::table('proyecto', function (Blueprint $table) {
            $table->dropColumn('encargado_id');
            $table->dropColumn('ingeniero_id');

            $table->unsignedBigInteger('encargado_id');
            $table->unsignedBigInteger('ingeniero_id');

            $table->foreign('encargado_id')->references('id')->on('users');
            $table->foreign('ingeniero_id')->references('id')->on('users');
        });
    }
};
