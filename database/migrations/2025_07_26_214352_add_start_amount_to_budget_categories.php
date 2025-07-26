<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStartAmountToBudgetCategories extends Migration
{
    public function up()
    {
        Schema::table('budget_categories', function (Blueprint $table) {
            $table->decimal('start_amount', 15, 2)->default(0)->after('sort_order');
        });
    }

    public function down()
    {
        Schema::table('budget_categories', function (Blueprint $table) {
            $table->dropColumn('start_amount');
        });
    }
}
