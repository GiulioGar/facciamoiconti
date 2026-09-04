<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTitolareSourcestoFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->unsignedTinyInteger('titolare_goat')->nullable()->after('titolare');
            $table->unsignedTinyInteger('titolare_esperto2')->nullable()->after('titolare_goat');
        });

        // Preserva i valori fantagoat già importati
        DB::statement('UPDATE fanta_listone SET titolare_goat = titolare WHERE titolare IS NOT NULL');
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn(['titolare_goat', 'titolare_esperto2']);
        });
    }
}
