<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIaToFantaListoneTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->unsignedTinyInteger('ia')->nullable()->after('fanta_index');
            // Metadati interni — non esposti in UI
            $table->unsignedTinyInteger('ia_confidence')->nullable()->after('ia');
            $table->string('ia_status', 20)->nullable()->after('ia_confidence');
            $table->unsignedTinyInteger('ia_sources')->nullable()->after('ia_status');
        });
    }

    public function down()
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn(['ia', 'ia_confidence', 'ia_status', 'ia_sources']);
        });
    }
}
