<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFantaIndexToFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->smallInteger('fanta_index')->nullable()->after('score');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn('fanta_index');
        });
    }
}
