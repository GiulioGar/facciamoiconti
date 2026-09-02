<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fanta_player_stats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('external_id');
            $table->string('season', 7);          // es. '2025-26'
            $table->smallInteger('pv')->nullable();
            $table->decimal('mv', 4, 2)->nullable();
            $table->decimal('fm', 4, 2)->nullable();
            $table->smallInteger('gf')->nullable();
            $table->smallInteger('gs')->nullable();
            $table->smallInteger('rp')->nullable();
            $table->smallInteger('rc')->nullable();
            $table->smallInteger('rplus')->nullable();
            $table->smallInteger('rminus')->nullable();
            $table->smallInteger('ass')->nullable();
            $table->smallInteger('amm')->nullable();
            $table->smallInteger('esp')->nullable();
            $table->smallInteger('au')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'season']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fanta_player_stats');
    }
};
