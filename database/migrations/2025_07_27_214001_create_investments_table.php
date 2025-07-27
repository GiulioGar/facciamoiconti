<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvestmentsTable extends Migration
{
    public function up()
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('family_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('category_id')
                  ->constrained('investment_categories')
                  ->onDelete('cascade');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('invested_balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('investments');
    }
}
