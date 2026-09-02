<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = function (Blueprint $table): void {
            $table->integer('quota_a')->nullable()->after('fvm');
            $table->integer('quota_i')->nullable()->after('quota_a');
            $table->integer('diff_quota')->nullable()->after('quota_i');
            $table->integer('quota_a_m')->nullable()->after('diff_quota');
            $table->integer('quota_i_m')->nullable()->after('quota_a_m');
            $table->integer('diff_quota_m')->nullable()->after('quota_i_m');
            $table->integer('fvm_m')->nullable()->after('diff_quota_m');
        };

        Schema::table('fanta_quotazione', $columns);
        Schema::table('fanta_listone', $columns);
    }

    public function down(): void
    {
        $drop = ['quota_a', 'quota_i', 'diff_quota', 'quota_a_m', 'quota_i_m', 'diff_quota_m', 'fvm_m'];

        Schema::table('fanta_quotazione', fn (Blueprint $t) => $t->dropColumn($drop));
        Schema::table('fanta_listone',   fn (Blueprint $t) => $t->dropColumn($drop));
    }
};
