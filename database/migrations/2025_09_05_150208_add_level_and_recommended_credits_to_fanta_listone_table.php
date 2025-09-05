<?php

// database/migrations/2025_09_05_000001_add_level_and_recommended_credits_to_fanta_listone_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLevelAndRecommendedCreditsToFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            // 1-5 (5=TOP, 1=Scarso). Default 3=Medio per dare un valore sensato ai record esistenti
            $table->unsignedTinyInteger('level')->default(3)->after('mv24')->comment('1=Scarso,2=Basso,3=Medio,4=Ottimo,5=Top');

            // 1..2500; opzionale, può essere null se non stimato
            $table->unsignedSmallInteger('recommended_credits')->nullable()->after('level')->comment('Crediti consigliati');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn(['level', 'recommended_credits']);
        });
    }
}
