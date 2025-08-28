<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::create('fanta_listone', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('external_id')->unique();
            $table->string('ruolo', 5);
            $table->string('ruolo_esteso', 20);
            $table->string('nome', 100);
            $table->string('squadra', 100);
            $table->integer('fvm');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fanta_listone');
    }
}
