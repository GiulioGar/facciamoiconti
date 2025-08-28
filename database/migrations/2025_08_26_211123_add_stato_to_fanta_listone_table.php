<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatoToFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->tinyInteger('stato')
                  ->default(0)
                  ->comment('0 = disponibile, 1 = assegnato')
                  ->after('fvm');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn('stato');
        });
    }
}
