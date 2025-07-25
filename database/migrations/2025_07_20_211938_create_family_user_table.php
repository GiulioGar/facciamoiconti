<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFamilyUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
public function up()
{
    Schema::create('family_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('family_id')
              ->constrained('families')
              ->onDelete('cascade');
        $table->foreignId('user_id')
              ->constrained('users')
              ->onDelete('cascade');
        $table->enum('status', ['pending','accepted','rejected'])
              ->default('pending');
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
        Schema::dropIfExists('family_user');
    }
}
