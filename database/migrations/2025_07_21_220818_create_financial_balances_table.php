<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinancialBalancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::create('financial_balances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('family_id');
        $table->date('accounting_month')->default(DB::raw('CURRENT_DATE'));
        $table->decimal('bank_balance', 12, 2)->default(0);
        $table->decimal('other_accounts', 12, 2)->default(0);
        $table->decimal('cash', 12, 2)->default(0);
        $table->decimal('insurances', 12, 2)->default(0);
        $table->decimal('investments', 12, 2)->default(0);
        $table->decimal('debt_credit', 12, 2)->default(0);
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('family_id')->references('id')->on('families')->onDelete('cascade');
        $table->unique(['user_id','family_id','accounting_month']);
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('financial_balances');
    }
}
