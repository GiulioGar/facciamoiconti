<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFantaFasciaToFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->unsignedTinyInteger('fanta_fascia')->nullable()->after('ia_sources');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn('fanta_fascia');
        });
    }
}
