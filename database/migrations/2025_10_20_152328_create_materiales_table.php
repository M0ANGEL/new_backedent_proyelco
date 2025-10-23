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
        Schema::create('materiales', function (Blueprint $table) {
            $table->id();

            // Campos del Excel
            $table->unsignedBigInteger('user_id')->nullable(); // usuario que carga el archivo
            $table->string('codigo_proyecto'); // código de proyecto ya que es único por casas o apartamentos
            $table->string('codigo'); // código de ítem
            $table->text('descripcion');
            $table->string('padre')->nullable();

            // ✅ Nuevo campo para jerarquía
            $table->unsignedTinyInteger('nivel')->default(1); // 1 = primer nivel, 2, 3, etc.

            $table->string('um')->nullable();
            $table->decimal('cantidad', 15, 4)->nullable();
            $table->string('subcapitulo')->nullable();
            $table->decimal('cant_apu', 15, 4)->nullable();
            $table->decimal('rend', 15, 4)->nullable();
            $table->integer('iva')->default(0);
            $table->decimal('valor_sin_iva', 20, 4)->nullable();
            $table->string('tipo_insumo')->nullable();
            $table->string('agrupacion')->nullable();

            // Control de stock
            $table->decimal('cant_total', 15, 4)->nullable();
            $table->decimal('cant_restante', 15, 4)->nullable();
            $table->decimal('cant_solicitada', 15, 4)->nullable();
            $table->tinyInteger('estado')->default(1);
            // estados del ítem: 1 = carga inicial, 2 = eliminado, 3 = agregado, 4 = editado

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
        Schema::dropIfExists('materiales');
    }
};
