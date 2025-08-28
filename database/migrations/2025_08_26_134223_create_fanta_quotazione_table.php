<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFantaQuotazioneTable extends Migration
{
    public function up()
    {
        Schema::create('fanta_quotazione', function (Blueprint $table) {
            $table->id(); // PK autoincrement
            $table->bigInteger('external_id')->unique(); // Id del file (es. 2428, 5876)
            $table->string('ruolo', 5); // R (es. P, D, C, A)
            $table->string('ruolo_esteso', 20); // RM (Por, Dif, Cent, Att)
            $table->string('nome', 100);
            $table->string('squadra', 100);
            $table->integer('fvm'); // quotazione / valore di mercato
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fanta_quotazione');
    }
}
