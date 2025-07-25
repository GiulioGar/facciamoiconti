<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('income_allocations', function (Blueprint $table) {
            $table->id();
            // collegamento all’entrata
            $table->foreignId('income_id')
                  ->constrained('incomes')
                  ->cascadeOnDelete();

            // collegamento alla categoria di budget (opzionale)
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('budget_categories')
                  ->cascadeOnDelete();

            // fallback testuale, se non si usa category_id
            $table->string('type', 20);

            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('income_allocations');
    }
};
