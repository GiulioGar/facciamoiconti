<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClassicRoleToFantaRosaTable extends Migration
{
    public function up()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->char('classic_role', 1)->nullable()->after('costo'); // P/D/C/A
            $table->index('classic_role');
        });
    }

    public function down()
    {
        Schema::table('fanta_rosa', function (Blueprint $table) {
            $table->dropIndex(['classic_role']);
            $table->dropColumn('classic_role');
        });
    }
}
