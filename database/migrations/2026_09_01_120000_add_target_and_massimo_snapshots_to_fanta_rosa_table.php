<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTargetAndMassimoSnapshotsToFantaRosaTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->unsignedInteger('target_snapshot')->nullable()->after('costo');
            $table->unsignedInteger('massimo_snapshot')->nullable()->after('target_snapshot');
        });
    }

    public function down()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->dropColumn(['target_snapshot', 'massimo_snapshot']);
        });
    }
}
