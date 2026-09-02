<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->decimal('score', 5, 2)->nullable()->after('fvm_m');
        });
    }

    public function down(): void
    {
        Schema::table('fanta_listone', function (Blueprint $table) {
            $table->dropColumn('score');
        });
    }
};
