<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCreditToFamiliesTable extends Migration
{
    public function up()
    {
        Schema::table('families', function (Blueprint $table) {
            $table->decimal('credit', 10, 2)->default(0)->after('owner_id');
        });

        // Porta il credito storico dal codice al DB
        DB::table('families')->where('id', 1)->update(['credit' => 1269]);
    }

    public function down()
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('credit');
        });
    }
}
