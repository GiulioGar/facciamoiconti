<?php

// database/migrations/2025_07_25_000001_create_budget_categories_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // es. "Personale"
            $table->string('slug')->unique();      // es. "personale"
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('budget_categories');
    }
};

