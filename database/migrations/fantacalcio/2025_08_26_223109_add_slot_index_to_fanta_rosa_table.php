<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSlotIndexToFantaRosaTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot_index')->nullable()->after('classic_role'); // 0-based
            // Unicità: per ogni ruolo Classic uno slot può ospitare 1 giocatore
            $table->unique(['classic_role','slot_index']);
        });
    }

    public function down()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->dropUnique(['classic_role','slot_index']);
            $table->dropColumn('slot_index');
        });
    }
}
