<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::create('expenses', function (Blueprint $table) {
        $table->id();

        // Collegamenti
        $table->foreignId('expense_category_id')
              ->constrained('expense_categories')
              ->onDelete('cascade');
        $table->foreignId('budget_category_id')
              ->constrained('budget_categories')
              ->onDelete('cascade');
        $table->foreignId('family_id')
              ->constrained('families')
              ->onDelete('cascade');
        $table->foreignId('user_id')
              ->constrained()
              ->onDelete('cascade');

        // Dati
        $table->string('description');
        $table->decimal('amount', 15, 2);
        $table->date('date');
        $table->text('note')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expenses');
    }
}
