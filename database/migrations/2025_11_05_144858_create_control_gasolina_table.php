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
        Schema::create('control_gasolina', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('user_id');
            $table->string('no_factura_venta', 100);
            $table->date('fecha_factura');
            $table->string('corte');  //formaro del 01/02/2025 al 15/02/2025
            $table->string('placa',50);  
            $table->string('comprobante',50);  
            $table->string('combustible',50);  
            $table->string('ppu',50);  
            $table->string('volumen',50);  
            $table->string('km',50);  
            $table->string('dinero');  

            $table->timestamps();


            $table->foreign('empleado_id')->references('id')->on('empleados_proyelco_th');
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
        Schema::dropIfExists('control_gasolina');
    }
};
